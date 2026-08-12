<?php

// PIC: Low Wen Kai

namespace Tests\Feature\Election;

use App\Models\Election;
use App\Models\Organizer;
use App\Models\User;
use App\ValueObjects\EligibilityRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ElectionEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_uses_iso_countries_and_does_not_show_occupation(): void
    {
        $organizer = $this->organizer();

        $this->actingAs($organizer)
            ->get(route('elections.create'))
            ->assertOk()
            ->assertSee('Any nationality')
            ->assertSee('Malaysia')
            ->assertSee('Singapore')
            ->assertDontSee('Occupation');
    }

    public function test_public_election_stores_submitted_restrictions_and_ignores_occupation(): void
    {
        $organizer = $this->organizer();
        $payload = $this->validPayload('Public Eligibility Election') + [
            'eligibility_mode' => 'PUBLIC',
            'eligibility_minimum_age' => 60,
            'eligibility_nationality' => 'SG',
            'eligibility_occupation' => 'STUDENT',
        ];

        $this->actingAs($organizer)->post(route('elections.store'), $payload)->assertRedirect();

        $rules = EligibilityRules::fromStored(Election::query()->where('title', $payload['title'])->value('eligibility_rules'));
        self::assertSame(EligibilityRules::PUBLIC, $rules->mode());
        self::assertSame(60, $rules->minimumAge());
        self::assertSame('SG', $rules->nationalityCountryCode());
        self::assertTrue($rules->toArray()['nationality']['restricted']);
        self::assertArrayNotHasKey('occupation', $rules->toArray());
    }

    public function test_public_elections_store_age_nationality_or_both(): void
    {
        $organizer = $this->organizer();
        $cases = [
            ['Age Public Election', 18, 'ANY', 18, null],
            ['Nationality Public Election', null, 'MY', null, 'MY'],
            ['Combined Public Election', 21, 'SG', 21, 'SG'],
        ];

        foreach ($cases as [$title, $age, $country, $expectedAge, $expectedCountry]) {
            $payload = $this->validPayload($title) + [
                'eligibility_mode' => 'PUBLIC',
                'eligibility_minimum_age' => $age,
                'eligibility_nationality' => $country,
            ];
            $this->actingAs($organizer)->post(route('elections.store'), $payload)->assertRedirect();

            $rules = EligibilityRules::fromStored(Election::query()->where('title', $title)->value('eligibility_rules'));
            self::assertSame($expectedAge, $rules->minimumAge());
            self::assertSame($expectedCountry, $rules->nationalityCountryCode());
            self::assertArrayNotHasKey('occupation', $rules->toArray());
        }
    }

    public function test_negative_or_missing_public_criteria_are_rejected(): void
    {
        $organizer = $this->organizer();
        $negative = $this->validPayload('Invalid Negative Age Election') + [
            'eligibility_mode' => 'PUBLIC',
            'eligibility_minimum_age' => -1,
            'eligibility_nationality' => 'ANY',
        ];
        $this->actingAs($organizer)
            ->from(route('elections.create'))
            ->post(route('elections.store'), $negative)
            ->assertSessionHasErrors('eligibility_minimum_age');

        $missing = $this->validPayload('Missing Eligibility Criteria Election') + [
            'eligibility_mode' => 'PUBLIC',
            'eligibility_minimum_age' => null,
            'eligibility_nationality' => 'ANY',
        ];
        $this->actingAs($organizer)
            ->from(route('elections.create'))
            ->post(route('elections.store'), $missing)
            ->assertSessionHasErrors('eligibility_criteria');
    }

    private function organizer(): User
    {
        $user = User::factory()->create(['is_admin' => false]);
        Organizer::query()->create(['user_id' => $user->getKey()]);

        return $user;
    }

    /** @return array<string, mixed> */
    private function validPayload(string $title): array
    {
        return [
            'title' => $title,
            'position_sought' => 'Community Representative',
            'description' => 'A sufficiently detailed description for eligibility configuration testing.',
            'nomination_start' => now()->addDay()->format('Y-m-d H:i:s'),
            'nomination_end' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'voting_start' => now()->addDays(3)->format('Y-m-d H:i:s'),
            'voting_end' => now()->addDays(4)->format('Y-m-d H:i:s'),
        ];
    }
}
