<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PIC: Goh Chun Wen — Voting Process module schema.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('cast_at')->useCurrent();
            $table->unique(['voter_id', 'election_id']);
            $table->index(['election_id', 'candidate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('votes');
    }
};
