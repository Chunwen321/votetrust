<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PIC: Wong Shun Bin — Candidate Registration module schema.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone_number', 30)->nullable();
        });

        Schema::table('candidacies', function (Blueprint $table): void {
            $table->string('position_sought', 100)->nullable();
            $table->text('manifesto')->nullable();
            $table->text('supporting_details')->nullable();
            $table->string('profile_photo', 255)->nullable();
            $table->string('profile_photo_mime', 100)->nullable();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::create('candidate_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('candidacy_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('stored_name')->unique();
            $table->string('mime_type', 100);
            $table->timestamp('uploaded_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_documents');

        Schema::table('candidacies', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('payment_id');
            $table->dropColumn([
                'position_sought',
                'manifesto',
                'supporting_details',
                'profile_photo',
                'profile_photo_mime',
            ]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('phone_number');
        });
    }
};
