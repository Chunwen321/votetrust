<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PIC: Goh Chun Wen - Stores successful request identifiers so ballot replays can be rejected.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vote', function (Blueprint $table): void {
            // Nullable keeps votes created before this migration valid.
            $table->string('request_id', 64)->nullable()->after('candidate_id');
            $table->unique('request_id');
        });
    }

    public function down(): void
    {
        Schema::table('vote', function (Blueprint $table): void {
            $table->dropUnique(['request_id']);
            $table->dropColumn('request_id');
        });
    }
};
