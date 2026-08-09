<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// PIC: Chew Zhexuan, Low Wen Kai, Goh Chun Wen - verified voter facts used by Election eligibility.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->date('date_of_birth')->nullable();
            $table->char('nationality_country_code', 2)->nullable();
        });

        $today = new DateTimeImmutable('today');
        DB::table('users')
            ->select(['id', 'ic_number'])
            ->whereNotNull('ic_number')
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($today): void {
                foreach ($users as $user) {
                    $icNumber = preg_replace('/[\s-]+/', '', trim((string) $user->ic_number)) ?? '';
                    if (preg_match('/^\d{12}$/', $icNumber) !== 1) {
                        continue;
                    }

                    $year = 2000 + (int) substr($icNumber, 0, 2);
                    if ($year > (int) $today->format('Y')) {
                        $year -= 100;
                    }
                    $value = sprintf('%04d%s', $year, substr($icNumber, 2, 4));
                    $date = DateTimeImmutable::createFromFormat('!Ymd', $value);
                    if ($date === false || $date->format('Ymd') !== $value || $date > $today) {
                        continue;
                    }

                    DB::table('users')->where('id', $user->id)->update([
                        'date_of_birth' => $date->format('Y-m-d'),
                        'nationality_country_code' => 'MY',
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['date_of_birth', 'nationality_country_code']);
        });
    }
};
