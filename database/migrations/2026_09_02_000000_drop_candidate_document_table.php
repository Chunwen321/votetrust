<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('candidate_document');
    }

    public function down(): void
    {
        Schema::create('candidate_document', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('candidacy_id')->constrained('candidacy')->cascadeOnDelete();
            $table->string('original_name');
            $table->string('stored_name')->unique();
            $table->string('mime_type', 100);
            $table->timestamp('uploaded_at')->useCurrent();
        });
    }
};
