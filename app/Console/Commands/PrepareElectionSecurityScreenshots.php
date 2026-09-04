<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\User;
use App\Services\ElectionService;
use App\States\ElectionStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

final class PrepareElectionSecurityScreenshots extends Command
{
    protected $signature = 'election:prepare-security-screenshots';

    protected $description = 'Create synthetic records in the guarded disposable screenshot database.';

    public function handle(ElectionService $elections): int
    {
        $password = (string) env('SCREENSHOT_ACCOUNT_PASSWORD', '');
        if (mb_strlen($password) < 12) {
            throw new RuntimeException('A synthetic screenshot password of at least 12 characters is required.');
        }
        if (DB::connection()->getDriverName() !== 'sqlite') {
            throw new RuntimeException('Screenshot fixtures require SQLite.');
        }
        if (User::query()->exists()) {
            throw new RuntimeException('The screenshot database must be empty before fixtures are created.');
        }

        $records = DB::transaction(function () use ($elections, $password): array {
            $now = now();
            $shared = [
                'email_verified_at' => $now,
                'password' => Hash::make($password),
                'date_of_birth' => '1990-01-01',
                'nationality_country_code' => 'MY',
                'account_status' => 'ACTIVE',
                'is_verified' => true,
                'verified_at' => $now,
                'verification_status' => 'APPROVED',
                'failed_login_attempts' => 0,
            ];

            $organizer = User::query()->create($shared + [
                'name' => 'Security Demo Organizer',
                'full_name' => 'Security Demo Organizer',
                'email' => 'organizer.security@example.test',
                'is_admin' => false,
            ]);
            $admin = User::query()->create($shared + [
                'name' => 'Security Demo Administrator',
                'full_name' => 'Security Demo Administrator',
                'email' => 'admin.security@example.test',
                'is_admin' => true,
            ]);

            $reauthentication = $elections->create($organizer, $this->electionInput(
                'SEC-DEMO Action-Bound Reauthentication',
                'Cancellation requires a current password in the secure version.',
            ));
            $lifecycle = $elections->create($organizer, $this->electionInput(
                'SEC-DEMO Lifecycle State Enforcement',
                'A draft election used to demonstrate rejection of an invalid lifecycle action.',
            ));
            $payment = $elections->create($organizer, $this->electionInput(
                'SEC-DEMO Payment Election-ID Binding',
                'A pending payment election used with a forged local Payment API response.',
            ));
            $payment = $elections->transition(
                $organizer,
                (int) $payment->getKey(),
                'submit_for_payment',
                '',
                ['version' => (int) $payment->version],
            );

            Payment::query()->create([
                'amount' => 100,
                'currency' => 'MYR',
                'status' => 'Paid',
                'gateway' => 'mock',
                'organizer_id' => $organizer->getKey(),
                'election_id' => $payment->getKey(),
                'purpose' => Payment::PURPOSE_ELECTION_HOSTING,
                'provider_reference' => 'SEC-PAY-0001',
                'request_id' => 'SEC-SCREENSHOT-FIXTURE',
                'verified_at' => $now,
            ]);

            return [
                'organizer' => $organizer->getKey(),
                'admin' => $admin->getKey(),
                'reauthentication' => $reauthentication->getKey(),
                'lifecycle' => $lifecycle->getKey(),
                'payment' => $payment->getKey(),
                'payment_status' => $payment->status,
                'payment_version' => $payment->version,
            ];
        });

        foreach ($records as $label => $value) {
            $this->line("{$label}: {$value}");
        }

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function electionInput(string $title, string $description): array
    {
        return [
            'title' => $title,
            'position_sought' => 'Security Demonstration Chair',
            'description' => $description,
            'eligibility_mode' => 'PUBLIC',
            'eligibility_minimum_age' => 18,
            'eligibility_nationality' => 'ANY',
            'nomination_start' => '2099-01-10 09:00:00',
            'nomination_end' => '2099-01-11 09:00:00',
            'voting_start' => '2099-01-12 09:00:00',
            'voting_end' => '2099-01-13 09:00:00',
        ];
    }
}
