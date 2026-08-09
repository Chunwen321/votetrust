<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomePageTest extends TestCase
{
    public function test_public_root_loads_the_votetrust_landing_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Run elections people can trust.')
            ->assertSee('Why VoteTrust')
            ->assertSee('How it works')
            ->assertSee('For organisers')
            ->assertSee('For candidates')
            ->assertSee('For voters')
            ->assertDontSee('Goh Chun Wen')
            ->assertDontSee('Chew Zhexuan')
            ->assertDontSee('Wong Shun Bin')
            ->assertDontSee('Low Wen Kai');
    }
}
