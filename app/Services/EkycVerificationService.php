<?php

// PIC: Chew Zhexuan — eKYC verification boundary for User and Voter access.

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Support\Identity;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Intl\Countries;
use Throwable;

final class EkycVerificationService
{
    public function __construct(private readonly Factory $http) {}

    /**
     * Starts the configured verification journey.
     *
     * @return array{completed:bool,redirectUrl:?string}
     */
    public function start(User $user): array
    {
        if ($user->isAdmin()) {
            return ['completed' => true, 'redirectUrl' => null];
        }

        if ($user->isVerified()) {
            return ['completed' => true, 'redirectUrl' => null];
        }

        [$documentType, $documentNumber] = $this->identityDocument($user);
        $user->forceFill(['verification_consent_at' => now()])->save();

        $driver = strtolower(trim((string) config('votetrust-user.ekyc.driver', 'local')));

        if ($driver === 'didit') {
            return $this->startDiditSession($user->refresh());
        }

        $reference = match ($driver) {
            'local' => $this->verifyWithLocalAdapter($user, $documentType, $documentNumber),
            'http' => $this->verifyWithHttpProvider($user, $documentType, $documentNumber),
            default => throw ValidationException::withMessages([
                'verification' => 'The configured eKYC provider is not supported.',
            ]),
        };

        $this->completeVerification($user, strtoupper($driver), $reference);

        return ['completed' => true, 'redirectUrl' => null];
    }

    /**
     * Compatibility wrapper for the immediate local/generic-provider flows.
     */
    public function verify(User $user): User
    {
        $result = $this->start($user);
        if (! $result['completed']) {
            throw new \LogicException('Hosted eKYC verification must be completed by the user before it can be approved.');
        }

        return $user->refresh();
    }

    /**
     * Validates a Didit V3 webhook, then applies only the event that belongs to the currently tracked user session.
     *
     * @param  array<array-key, mixed>  $payload
     */
    public function processDiditWebhook(array $payload, string $timestampHeader, string $signature): void
    {
        $secret = (string) config('votetrust-user.ekyc.didit.webhook_secret', '');
        if ($secret === '' || ! $this->validDiditSignature($payload, $timestampHeader, $signature, $secret)) {
            throw new UnauthorizedHttpException('Didit', 'The Didit webhook signature is invalid.');
        }

        if (($payload['webhook_type'] ?? null) !== 'status.updated') {
            return;
        }

        $sessionId = $payload['session_id'] ?? null;
        $eventId = $payload['event_id'] ?? null;
        $status = $payload['status'] ?? null;
        if (! is_string($sessionId) || ! Str::isUuid($sessionId) || ! is_string($status)) {
            return;
        }

        $user = User::query()
            ->where('verification_provider', 'DIDIT')
            ->where('verification_session_id', $sessionId)
            ->first();
        if (! $user instanceof User || ($payload['vendor_data'] ?? null) !== $this->diditVendorData($user)) {
            return;
        }

        $workflowId = (string) config('votetrust-user.ekyc.didit.workflow_id', '');
        if ($workflowId !== '' && ($payload['workflow_id'] ?? null) !== $workflowId) {
            return;
        }

        if (is_string($eventId) && $eventId !== '' && hash_equals((string) $user->verification_last_event_id, $eventId)) {
            return;
        }

        $updates = [
            'verification_status' => $this->normaliseDiditStatus($status),
            'verification_last_event_id' => is_string($eventId) ? $eventId : null,
        ];

        if ($status === 'Approved') {
            $updates += [
                'is_verified' => true,
                'verified_at' => now(),
                'verification_reference' => $sessionId,
                'verification_failure_reason' => null,
            ];
        } elseif (! $user->isVerified()) {
            $updates += [
                'is_verified' => false,
                'verified_at' => null,
                'verification_failure_reason' => $this->diditFailureReason($status),
            ];
        }

        $user->forceFill($updates)->save();
    }

    /** @return array{string,string} */
    private function identityDocument(User $user): array
    {
        if (strtoupper((string) $user->nationality_country_code) === 'MY') {
            $icNumber = Identity::normaliseIcNumber((string) $user->ic_number);
            $birthDate = Identity::dateOfBirthFromMalaysianIc($icNumber);

            if ($birthDate === null || $user->date_of_birth?->toDateString() !== $birthDate->toDateString()) {
                throw ValidationException::withMessages([
                    'identity_document' => 'Your Malaysian IC details are incomplete or invalid. Update your profile before verification.',
                ]);
            }

            return ['MY_IC', $icNumber];
        }

        $passportNumber = Identity::normalisePassportNumber((string) $user->passport_number);
        if (! Identity::isValidPassportNumber($passportNumber) || $user->date_of_birth === null) {
            throw ValidationException::withMessages([
                'identity_document' => 'A valid passport number and date of birth are required for eKYC verification.',
            ]);
        }

        return ['PASSPORT', $passportNumber];
    }

    /** @return array{completed:bool,redirectUrl:string} */
    private function startDiditSession(User $user): array
    {
        $apiKey = (string) config('votetrust-user.ekyc.didit.api_key', '');
        $workflowId = (string) config('votetrust-user.ekyc.didit.workflow_id', '');
        if ($apiKey === '' || $workflowId === '') {
            throw ValidationException::withMessages([
                'verification' => 'Didit is not configured. Add DIDIT_API_KEY and DIDIT_WORKFLOW_ID before starting verification.',
            ]);
        }

        try {
            $response = $this->http->acceptJson()
                ->asJson()
                ->withHeaders(['x-api-key' => $apiKey])
                ->connectTimeout(max(1, (int) config('votetrust-user.ekyc.connect_timeout_seconds', 2)))
                ->timeout(max(1, (int) config('votetrust-user.ekyc.timeout_seconds', 10)))
                ->withOptions(['allow_redirects' => false])
                ->post($this->diditBaseUrl().'/v3/session/', [
                    'workflow_id' => $workflowId,
                    'vendor_data' => $this->diditVendorData($user),
                    'callback' => route('profile.verify'),
                    'callback_method' => 'both',
                    'language' => 'en',
                    'metadata' => ['application' => 'votetrust'],
                    'contact_details' => [
                        'email' => (string) $user->email,
                        'send_notification_emails' => false,
                        'email_lang' => 'en',
                    ],
                    'expected_details' => $this->diditExpectedDetails($user),
                ]);
            $payload = $response->json();
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'verification' => 'Didit is temporarily unavailable. Please try again later.',
            ]);
        }

        $sessionId = is_array($payload) ? ($payload['session_id'] ?? null) : null;
        $url = is_array($payload) ? ($payload['url'] ?? null) : null;
        if (! $response->successful() || ! is_string($sessionId) || ! Str::isUuid($sessionId) || ! $this->isSafeHostedUrl($url)) {
            Log::warning('Didit session creation failed.', [
                'http_status' => $response->status(),
                'provider_message' => $this->diditProviderMessage($payload),
                'workflow_id' => $workflowId,
                'user_id' => $user->getKey(),
            ]);

            throw ValidationException::withMessages([
                'verification' => 'Didit could not start a verification session. Check the workflow configuration and try again.',
            ]);
        }

        $user->forceFill([
            'is_verified' => false,
            'verified_at' => null,
            'verification_reference' => null,
            'verification_provider' => 'DIDIT',
            'verification_session_id' => $sessionId,
            'verification_status' => 'PENDING',
            'verification_requested_at' => now(),
            'verification_last_event_id' => null,
            'verification_failure_reason' => null,
        ])->save();

        return ['completed' => false, 'redirectUrl' => $url];
    }

    /** @return array<string, mixed> */
    private function diditExpectedDetails(User $user): array
    {
        $nameParts = preg_split('/\s+/', trim((string) $user->full_name)) ?: [];
        $firstName = array_shift($nameParts);
        $lastName = trim(implode(' ', $nameParts));
        $countryCode = strtoupper((string) $user->nationality_country_code);
        $isMalaysian = $countryCode === 'MY';

        $details = [
            'first_name' => $firstName ?: null,
            'last_name' => $lastName !== '' ? $lastName : null,
            'date_of_birth' => $user->date_of_birth?->toDateString(),
            'nationality' => Countries::getAlpha3Code($countryCode),
            // A foreign passport can be issued by a country other than the holder's nationality, so do not constrain it.
            'id_country' => $isMalaysian ? 'MYS' : null,
            'expected_document_types' => [$isMalaysian ? 'ID' : 'P'],
        ];

        return array_filter($details, static fn (mixed $value): bool => $value !== null);
    }

    private function diditVendorData(User $user): string
    {
        return 'votetrust-user:'.$user->getKey();
    }

    private function diditBaseUrl(): string
    {
        return rtrim((string) config('votetrust-user.ekyc.didit.base_url', 'https://verification.didit.me'), '/');
    }

    private function diditProviderMessage(mixed $payload): ?string
    {
        if (! is_array($payload)) {
            return null;
        }

        foreach (['message', 'detail', 'error.message', 'error'] as $key) {
            $value = data_get($payload, $key);

            if (is_string($value) && $value !== '') {
                return Str::limit($value, 500);
            }
        }

        return null;
    }

    private function isSafeHostedUrl(mixed $url): bool
    {
        if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);

        return ($parts['scheme'] ?? null) === 'https'
            && (($parts['host'] ?? null) === 'verify.didit.me' || str_ends_with((string) ($parts['host'] ?? ''), '.verify.didit.me'));
    }

    private function completeVerification(User $user, string $provider, string $reference): void
    {
        $user->forceFill([
            'is_verified' => true,
            'verified_at' => now(),
            'verification_reference' => $reference,
            'verification_provider' => $provider,
            'verification_session_id' => null,
            'verification_status' => 'APPROVED',
            'verification_requested_at' => now(),
            'verification_last_event_id' => null,
            'verification_failure_reason' => null,
        ])->save();
    }

    private function normaliseDiditStatus(string $status): string
    {
        return match ($status) {
            'Approved' => 'APPROVED',
            'Declined' => 'DECLINED',
            'In Review' => 'IN_REVIEW',
            'In Progress' => 'IN_PROGRESS',
            'Not Started' => 'NOT_STARTED',
            'Awaiting User' => 'AWAITING_USER',
            'Resubmitted' => 'RESUBMITTED',
            'Expired', 'Kyc Expired' => 'EXPIRED',
            'Abandoned' => 'ABANDONED',
            default => 'PENDING',
        };
    }

    private function diditFailureReason(string $status): ?string
    {
        return match ($status) {
            'Declined' => 'Didit declined this verification session.',
            'Expired', 'Kyc Expired' => 'The Didit verification session expired.',
            'Abandoned' => 'The Didit verification session was abandoned.',
            default => null,
        };
    }

    /** @param array<array-key, mixed> $payload */
    private function validDiditSignature(array $payload, string $timestampHeader, string $signature, string $secret): bool
    {
        if (! ctype_digit($timestampHeader) || $signature === '') {
            return false;
        }

        $maximumAge = max(1, (int) config('votetrust-user.ekyc.didit.webhook_tolerance_seconds', 300));
        if (abs(time() - (int) $timestampHeader) > $maximumAge) {
            return false;
        }

        try {
            $canonical = json_encode(
                $this->sortDiditPayload($payload),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $canonical, $secret), $signature);
    }

    private function sortDiditPayload(mixed $value): mixed
    {
        if (! is_array($value)) {
            return is_float($value) && floor($value) === $value ? (int) $value : $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->sortDiditPayload($item), $value);
        }

        ksort($value, SORT_STRING);

        foreach ($value as $key => $item) {
            $value[$key] = $this->sortDiditPayload($item);
        }

        return $value;
    }

    private function verifyWithLocalAdapter(User $user, string $documentType, string $documentNumber): string
    {
        // This adapter supports local development and automated testing only. It validates the stored identity data;
        // production must use a hosted provider for document, biometric, and liveness checks.
        if ($documentNumber === '' || $user->full_name === '' || $user->date_of_birth === null) {
            throw ValidationException::withMessages([
                'verification' => 'Your identity profile is incomplete and cannot be verified.',
            ]);
        }

        return 'LOCAL-'.strtoupper($documentType).'-'.str_replace('-', '', (string) Str::uuid());
    }

    private function verifyWithHttpProvider(User $user, string $documentType, string $documentNumber): string
    {
        $url = trim((string) config('votetrust-user.ekyc.url', ''));
        $apiKey = (string) config('votetrust-user.ekyc.api_key', '');
        if ($url === '' || $apiKey === '') {
            throw ValidationException::withMessages([
                'verification' => 'The eKYC provider is not configured. Please contact support.',
            ]);
        }

        $requestId = 'ekyc_'.str_replace('-', '', (string) Str::uuid());
        try {
            $response = $this->http->acceptJson()
                ->withHeaders(['X-eKYC-Api-Key' => $apiKey])
                ->connectTimeout(max(1, (int) config('votetrust-user.ekyc.connect_timeout_seconds', 2)))
                ->timeout(max(1, (int) config('votetrust-user.ekyc.timeout_seconds', 10)))
                ->withOptions(['allow_redirects' => false])
                ->post($url, [
                    'requestID' => $requestId,
                    'timestamp' => CarbonImmutable::now()->toAtomString(),
                    'userId' => (int) $user->getKey(),
                    'fullName' => (string) $user->full_name,
                    'nationalityCountryCode' => (string) $user->nationality_country_code,
                    'dateOfBirth' => $user->date_of_birth?->toDateString(),
                    'documentType' => $documentType,
                    'documentNumber' => $documentNumber,
                ]);
            $payload = $response->json();
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'verification' => 'The eKYC provider is temporarily unavailable. Please try again later.',
            ]);
        }

        if (! $response->successful() || ! is_array($payload) || ! $this->validProviderResponse($payload, $requestId)) {
            throw ValidationException::withMessages([
                'verification' => 'Your identity could not be verified. Check your details or try again later.',
            ]);
        }

        $data = $payload['data'];

        return is_string($data['verificationReference'] ?? null) && $data['verificationReference'] !== ''
            ? $data['verificationReference']
            : 'EKYC-'.str_replace('-', '', (string) Str::uuid());
    }

    /** @param array<array-key, mixed> $payload */
    private function validProviderResponse(array $payload, string $requestId): bool
    {
        if (($payload['status'] ?? null) !== 'S'
            || ($payload['requestID'] ?? null) !== $requestId
            || ! is_string($payload['timestamp'] ?? null)
            || ! is_array($payload['data'] ?? null)
            || ($payload['data']['verified'] ?? null) !== true) {
            return false;
        }

        try {
            $respondedAt = CarbonImmutable::parse($payload['timestamp']);
        } catch (Throwable) {
            return false;
        }

        return abs(CarbonImmutable::now()->diffInSeconds($respondedAt, false))
            <= max(1, (int) config('votetrust-user.ekyc.response_max_age_seconds', 300));
    }
}
