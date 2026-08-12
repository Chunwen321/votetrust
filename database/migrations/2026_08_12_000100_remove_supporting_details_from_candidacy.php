<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PIC: Wong Shun Bin — Superseded by the separate candidate-detail columns.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidacy', function (Blueprint $table): void {
            $table->dropColumn('supporting_details');
        });
    }

    public function down(): void
    {
        Schema::table('candidacy', function (Blueprint $table): void {
            $table->text('supporting_details')->nullable()->after('reason_for_candidacy');
        });
    }
};
