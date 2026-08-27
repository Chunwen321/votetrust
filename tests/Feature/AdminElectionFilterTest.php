<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Election;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminElectionFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_search_elections_by_content_organizer_and_id(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $organizer = User::factory()->create([
            'full_name' => 'Aisha Rahman',
            'email' => 'aisha.organizer@example.test',
        ]);
        $target = Election::query()->create([
            'organizer_id' => $organizer->getKey(),
            'title' => 'Engineering Faculty Council',
            'description' => 'Annual representative ballot',
            'status' => Election::DRAFT,
        ]);
        Election::query()->create([
            'title' => 'Sports Committee',
            'description' => 'Unrelated record',
            'status' => Election::CLOSED,
        ]);

        foreach (['Engineering Faculty', 'representative ballot', 'Aisha Rahman', (string) $target->getKey()] as $search) {
            $this->actingAs($admin)
                ->get(route('admin.elections.index', ['search' => $search]))
                ->assertOk()
                ->assertSee('Engineering Faculty Council')
                ->assertDontSee('Sports Committee');
        }
    }

    public function test_admin_can_combine_status_and_payment_filters(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Election::query()->create([
            'title' => 'Verified open election',
            'status' => Election::VOTING_OPEN,
            'payment_verified_at' => now(),
        ]);
        Election::query()->create([
            'title' => 'Unverified open election',
            'status' => Election::VOTING_OPEN,
        ]);
        Election::query()->create([
            'title' => 'Verified closed election',
            'status' => Election::CLOSED,
            'payment_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.elections.index', [
                'status' => Election::VOTING_OPEN,
                'payment' => 'verified',
            ]))
            ->assertOk()
            ->assertSee('Verified open election')
            ->assertDontSee('Unverified open election')
            ->assertDontSee('Verified closed election')
            ->assertSee('1 matching election');
    }

    public function test_active_filters_are_preserved_in_pagination_links(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        foreach (range(1, 21) as $index) {
            Election::query()->create([
                'title' => "Searchable election {$index}",
                'status' => Election::DRAFT,
            ]);
        }

        $response = $this->actingAs($admin)->get(route('admin.elections.index', [
            'search' => 'Searchable',
            'status' => Election::DRAFT,
            'payment' => 'unverified',
        ]));

        $response->assertOk()->assertViewHas('elections', function ($elections): bool {
            $nextPageUrl = $elections->nextPageUrl();

            return is_string($nextPageUrl)
                && str_contains($nextPageUrl, 'search=Searchable')
                && str_contains($nextPageUrl, 'status=DRAFT')
                && str_contains($nextPageUrl, 'payment=unverified');
        });
    }
}
