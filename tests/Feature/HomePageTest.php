<?php

namespace Tests\Feature;

use Tests\TestCase;

// PIC: Goh Chun Wen, Chew Zhexuan, Wong Shun Bin, Low Wen Kai - shared entry-route test.
class HomePageTest extends TestCase
{
    public function test_public_root_loads_the_integrated_module_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('One trusted place')
            ->assertSee('User')
            ->assertSee('Election')
            ->assertSee('Candidate')
            ->assertSee('Voting Process');
    }
}
