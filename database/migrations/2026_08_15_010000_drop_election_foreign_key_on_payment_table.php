<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PIC: Risky Puah Kaho - Payment Module — the FK forced InnoDB to lock the referenced election row
// on every hosting-payment insert, deadlocking against ElectionService's own
// lockForUpdate() transaction when the admin-approval flow calls back into
// this same app over HTTP. The column stays; only the constraint is dropped.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment', function (Blueprint $table): void {
            $table->dropForeign(['election_id']);
        });
    }

    public function down(): void
    {
        Schema::table('payment', function (Blueprint $table): void {
            $table->foreign('election_id')->references('id')->on('election')->nullOnDelete();
        });
    }
};
