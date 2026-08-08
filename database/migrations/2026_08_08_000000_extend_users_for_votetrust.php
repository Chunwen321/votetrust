<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PIC: Chew Zhexuan — User, Voter, and Admin module schema.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 50)->nullable()->unique();
            $table->string('full_name', 100)->nullable();
            $table->string('ic_number', 20)->nullable()->unique();
            $table->boolean('is_admin')->default(false);
            $table->string('account_status', 20)->default('ACTIVE');
            $table->unsignedSmallInteger('failed_login_attempts')->default(0);
            $table->timestamp('account_disabled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['username']);
            $table->dropUnique(['ic_number']);
            $table->dropColumn([
                'username',
                'full_name',
                'ic_number',
                'is_admin',
                'account_status',
                'failed_login_attempts',
                'account_disabled_at',
            ]);
        });
    }
};
