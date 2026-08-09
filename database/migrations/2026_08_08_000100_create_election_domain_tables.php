<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PIC: Low Wen Kai — Election Management Module schema.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizers', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->decimal('amount', 10, 2);
            $table->char('currency', 3)->default('MYR');
            $table->string('status', 32)->default('Pending');
            $table->foreignId('organizer_id')->nullable()->constrained('users')->nullOnDelete();
            // PIC: Wong Shun Bin — Candidate application deposit owner.
            $table->foreignId('candidate_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider_reference', 100)->nullable();
            $table->string('request_id', 64)->nullable()->index();
            $table->json('response_json')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('elections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->text('eligibility_rules')->nullable();
            $table->dateTime('nomination_start')->nullable();
            $table->dateTime('nomination_end')->nullable();
            $table->dateTime('voting_start')->nullable();
            $table->dateTime('voting_end')->nullable();
            $table->decimal('hosting_fee', 10, 2)->nullable();
            $table->char('currency', 3)->default('MYR');
            $table->string('status', 32)->default('DRAFT')->index();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_reference', 64)->nullable();
            $table->dateTime('payment_verified_at')->nullable();
            $table->string('payment_request_id', 64)->nullable();
            $table->string('payment_provider_status', 32)->nullable();
            $table->json('payment_response_json')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('rejected_at')->nullable();
            $table->string('rejection_reason', 250)->nullable();
            $table->json('transition_history_json')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('candidacies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('Pending')->index();
            $table->timestamp('registered_at')->useCurrent();
            $table->timestamps();
            $table->unique(['user_id', 'election_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidacies');
        Schema::dropIfExists('elections');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('organizers');
    }
};
