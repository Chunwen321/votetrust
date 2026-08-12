<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PIC: Low Wen Kai and Chew Zhexuan — private-election voter-list audit metadata.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('election', function (Blueprint $table): void {
            $table->timestamp('private_voter_list_uploaded_at')->nullable()->after('eligibility_rules');
            $table->unsignedInteger('private_voter_count')->default(0)->after('private_voter_list_uploaded_at');
            $table->unsignedInteger('private_voter_unmatched_count')->default(0)->after('private_voter_count');
        });

        Schema::table('voter', function (Blueprint $table): void {
            $table->string('enrollment_source', 20)->default('MANUAL')->after('status');
            $table->index(['election_id', 'enrollment_source']);
        });
    }

    public function down(): void
    {
        Schema::table('voter', function (Blueprint $table): void {
            $table->dropIndex(['election_id', 'enrollment_source']);
            $table->dropColumn('enrollment_source');
        });

        Schema::table('election', function (Blueprint $table): void {
            $table->dropColumn([
                'private_voter_list_uploaded_at',
                'private_voter_count',
                'private_voter_unmatched_count',
            ]);
        });
    }
};
