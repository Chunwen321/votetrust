<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('user', 'username')) {
            return;
        }

        foreach (Schema::getIndexes('user') as $index) {
            if (($index['unique'] ?? false) && ($index['columns'] ?? []) === ['username']) {
                Schema::table('user', function (Blueprint $table) use ($index): void {
                    $table->dropUnique((string) $index['name']);
                });
            }
        }

        Schema::table('user', function (Blueprint $table): void {
            $table->dropColumn('username');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('user', 'username')) {
            return;
        }

        Schema::table('user', function (Blueprint $table): void {
            $table->string('username', 50)->nullable()->unique();
        });
    }
};
