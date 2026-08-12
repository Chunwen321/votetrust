<?php

// PIC: Low Wen Kai — Election Module API summaries consumed by User and Voter modules.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Security\ApiRequestValidator;
use App\Services\ApiEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class ElectionSummaryController extends Controller
{
    public function __construct(private readonly ApiRequestValidator $validator) {}

    public function summaries(Request $request): JsonResponse
    {
        return $this->respond($request, function (array $input): array {
            $ids = array_values(array_unique(array_map('intval', (array) ($input['electionIds'] ?? []))));
            if ($ids === [] || count($ids) > 100 || collect($ids)->contains(static fn (int $id): bool => $id < 1)) {
                throw ValidationException::withMessages(['electionIds' => 'Provide 1 to 100 valid election IDs.']);
            }

            return [
                'elections' => Election::query()->whereIn('id', $ids)->get()->map($this->summary(...))->values()->all(),
            ];
        });
    }

    public function organizer(Request $request, int $user): JsonResponse
    {
        return $this->respond($request, function (array $input) use ($user): array {
            $limit = (int) ($input['limit'] ?? 0);
            if ($limit < 0 || $limit > 100) {
                throw ValidationException::withMessages(['limit' => 'limit must be between 0 and 100.']);
            }

            $query = Election::query()->where('organizer_id', $user)->latest('updated_at');
            if ($limit > 0) {
                $query->limit($limit);
            }

            return ['elections' => $query->get()->map($this->summary(...))->values()->all()];
        });
    }

    public function adminOverview(Request $request): JsonResponse
    {
        return $this->respond($request, function (): array {
            $recent = Election::query()->latest('updated_at')->limit(5)->get();
            $pending = Election::query()
                ->whereIn('status', [Election::PENDING_FEE_QUOTE, Election::PENDING_ADMIN_APPROVAL])
                ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [Election::PENDING_FEE_QUOTE])
                ->orderBy('updated_at')
                ->limit(4)
                ->get();

            return [
                'counts' => [
                    'elections' => Election::query()->count(),
                    'active' => Election::query()->whereIn('status', ['APPROVED', 'NOMINATION_OPEN', 'VOTING_OPEN'])->count(),
                    'pendingApprovals' => Election::query()
                        ->whereIn('status', [Election::PENDING_FEE_QUOTE, Election::PENDING_ADMIN_APPROVAL])
                        ->count(),
                ],
                'recentElections' => $recent->map($this->summary(...))->values()->all(),
                'pendingApprovals' => $pending->map($this->summary(...))->values()->all(),
            ];
        });
    }

    /** @param callable(array<string, mixed>): array<string, mixed> $resolver */
    private function respond(Request $request, callable $resolver): JsonResponse
    {
        $requestId = (string) $request->input('requestID', 'UNKNOWN');
        $providedKey = (string) $request->header('X-Internal-Api-Key', '');
        $expectedKey = (string) config('election.api.key', '');
        if ($expectedKey === '' || ! hash_equals($expectedKey, $providedKey)) {
            return response()->json(ApiEnvelope::failure($requestId, 'Election API access denied.'), 403);
        }

        try {
            $envelope = $this->validator->validateEnvelope($request->query());
            $requestId = $envelope['requestID'];

            return response()->json(ApiEnvelope::success($requestId, $resolver($request->query()), 'Election summaries retrieved.'));
        } catch (ValidationException $exception) {
            return response()->json(ApiEnvelope::failure($requestId, 'The API request is invalid.', $exception->errors()), 422);
        }
    }

    /** @return array{electionId:int,title:string,lifecycleState:string,organizerUserId:?int,updatedAt:?string} */
    private function summary(Election $election): array
    {
        return [
            'electionId' => (int) $election->getKey(),
            'title' => (string) $election->title,
            'lifecycleState' => (string) $election->status,
            'organizerUserId' => $election->organizer_id === null ? null : (int) $election->organizer_id,
            'updatedAt' => $election->updated_at?->toAtomString(),
        ];
    }
}
