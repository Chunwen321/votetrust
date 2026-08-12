<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PIC: Chew Zhexuan — User identity and eKYC verification fields.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user', function (Blueprint $table): void {
            $table->string('passport_number', 30)->nullable()->unique()->after('ic_number');
            $table->boolean('is_verified')->default(false)->after('account_status');
            $table->timestamp('verified_at')->nullable()->after('is_verified');
            $table->string('verification_reference', 100)->nullable()->after('verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('user', function (Blueprint $table): void {
            $table->dropUnique(['passport_number']);
            $table->dropColumn(['passport_number', 'is_verified', 'verified_at', 'verification_reference']);
        });
    }
};
