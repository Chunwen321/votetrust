<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// PIC: Low Wen Kai, Wong Shun Bin - An election has one fixed position for every candidate.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('election', function (Blueprint $table): void {
            $table->string('position_sought', 100)->nullable()->after('title');
        });

        DB::table('election')
            ->select('id')
            ->orderBy('id')
            ->eachById(function (object $election): void {
                $position = DB::table('candidacy')
                    ->where('election_id', $election->id)
                    ->whereNotNull('position_sought')
                    ->where('position_sought', '!=', '')
                    ->orderBy('id')
                    ->value('position_sought');

                if (is_string($position) && trim($position) !== '') {
                    DB::table('election')
                        ->where('id', $election->id)
                        ->update(['position_sought' => trim($position)]);
                }
            });

        Schema::table('candidacy', function (Blueprint $table): void {
            $table->dropColumn('position_sought');
        });
    }

    public function down(): void
    {
        Schema::table('candidacy', function (Blueprint $table): void {
            $table->string('position_sought', 100)->nullable()->after('registered_at');
        });

        DB::table('candidacy')
            ->select(['id', 'election_id'])
            ->orderBy('id')
            ->eachById(function (object $candidacy): void {
                $position = DB::table('election')
                    ->where('id', $candidacy->election_id)
                    ->value('position_sought');

                DB::table('candidacy')
                    ->where('id', $candidacy->id)
                    ->update(['position_sought' => $position]);
            });

        Schema::table('election', function (Blueprint $table): void {
            $table->dropColumn('position_sought');
        });
    }
};
