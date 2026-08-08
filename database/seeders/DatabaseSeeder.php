<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // PIC: Chew Zhexuan, Low Wen Kai, Wong Shun Bin, and Goh Chun Wen.
        $this->call(VoteTrustDemoSeeder::class);
    }
}
