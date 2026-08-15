<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PIC: Risky Puah Kaho - Payment Module — gateway metadata needed to settle and re-derive status.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment', function (Blueprint $table): void {
            $table->string('purpose', 32)->nullable()->after('candidate_id');
            $table->string('gateway', 32)->default('mock')->after('status');
            $table->foreignId('election_id')->nullable()->after('candidate_id')->constrained('election')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('verified_at');
            $table->timestamp('refunded_at')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('payment', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('election_id');
            $table->dropColumn(['purpose', 'gateway', 'cancelled_at', 'refunded_at']);
        });
    }
};
