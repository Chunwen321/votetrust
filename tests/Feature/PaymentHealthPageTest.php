<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * PIC: Risky Puah Kaho - Payment Module
 * Verifies the admin-only Payment API health page and its ping endpoint.
 */
final class PaymentHealthPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['payment.api.key' => 'health-page-secret']);
    }

    public function test_ping_reports_ok_for_a_valid_key(): void
    {
        $this->withHeader('X-API-Key', 'health-page-secret')
            ->getJson('/api/v1/payments/ping')
            ->assertOk()
            ->assertJsonPath('status', 'S')
            ->assertJsonPath('data.gateway', 'IDontPay99Gateway');
    }

    public function test_ping_fails_closed_without_a_valid_key(): void
    {
        $this->getJson('/api/v1/payments/ping')
            ->assertUnauthorized()
            ->assertJsonPath('status', 'F');
    }

    public function test_an_admin_can_view_the_payment_health_page(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            '*/api/v1/payments/ping' => Http::response([
                'status' => 'S',
                'message' => 'Payment module is reachable.',
                'requestID' => 'ping_test',
                'timestamp' => now()->toAtomString(),
                'data' => ['gateway' => 'IDontPay99Gateway'],
            ]),
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        Payment::query()->create([
            'amount' => '100.00',
            'currency' => 'MYR',
            'status' => 'Paid',
            'purpose' => Payment::PURPOSE_ELECTION_HOSTING,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.payments.health'))
            ->assertOk()
            ->assertSee('API reachable')
            ->assertSee('API key valid')
            ->assertSee('IDontPay99Gateway')
            ->assertSee('Paid');
    }

    public function test_a_non_admin_cannot_view_the_payment_health_page(): void
    {
        $member = User::factory()->create(['is_admin' => false]);

        $this->actingAs($member)
            ->get(route('admin.payments.health'))
            ->assertForbidden();
    }

    public function test_an_admin_can_run_a_test_payment_that_settles_paid(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('admin.payments.health.test'))
            ->assertRedirect(route('admin.payments.health'))
            ->assertSessionHas('payment_test_result', fn (array $result): bool => $result['status'] === 'Paid');

        $this->assertDatabaseHas('payment', [
            'purpose' => Payment::PURPOSE_SYSTEM_TEST,
            'status' => 'Paid',
        ]);
    }

    public function test_an_admin_can_run_a_test_payment_that_settles_failed(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('admin.payments.health.test'), ['simulate_failure' => '1'])
            ->assertRedirect(route('admin.payments.health'))
            ->assertSessionHas('payment_test_result', fn (array $result): bool => $result['status'] === 'Failed');

        $this->assertDatabaseHas('payment', [
            'purpose' => Payment::PURPOSE_SYSTEM_TEST,
            'status' => 'Failed',
        ]);
    }

    public function test_a_non_admin_cannot_run_a_test_payment(): void
    {
        $member = User::factory()->create(['is_admin' => false]);

        $this->actingAs($member)
            ->post(route('admin.payments.health.test'))
            ->assertForbidden();
    }
}
