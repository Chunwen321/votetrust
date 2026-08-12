<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// PIC: Chew Zhexuan — User module eKYC provider/session audit fields.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user', function (Blueprint $table): void {
            $table->string('verification_provider', 30)->nullable()->after('verification_reference');
            $table->string('verification_session_id', 100)->nullable()->unique()->after('verification_provider');
            $table->string('verification_status', 30)->default('NOT_STARTED')->after('verification_session_id');
            $table->timestamp('verification_requested_at')->nullable()->after('verification_status');
            $table->timestamp('verification_consent_at')->nullable()->after('verification_requested_at');
            $table->string('verification_last_event_id', 64)->nullable()->after('verification_consent_at');
            $table->string('verification_failure_reason', 255)->nullable()->after('verification_last_event_id');
        });

        // Preserve the state of accounts verified before provider/session tracking was introduced.
        DB::table('user')->where('is_verified', true)->update(['verification_status' => 'APPROVED']);
    }

    public function down(): void
    {
        Schema::table('user', function (Blueprint $table): void {
            $table->dropUnique(['verification_session_id']);
            $table->dropColumn([
                'verification_provider',
                'verification_session_id',
                'verification_status',
                'verification_requested_at',
                'verification_consent_at',
                'verification_last_event_id',
                'verification_failure_reason',
            ]);
        });
    }
};
