<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables created by an earlier implementation. They are archived before
     * the current tables take these singular UML/class-diagram names.
     *
     * @var array<string, string>
     */
    private const TABLE_RENAMES = [
        'candidate_documents' => 'candidate_document',
        'candidacies' => 'candidacy',
        'voter_enrollments' => 'voter',
        'votes' => 'vote',
        'elections' => 'election',
        'payments' => 'payment',
        'candidates' => 'candidate',
        'organizers' => 'organizer',
        'users' => 'user',
    ];

    public function up(): void
    {
        $legacyTables = array_values(array_filter(
            self::TABLE_RENAMES,
            static fn (string $singular): bool => Schema::hasTable($singular),
        ));

        if ($legacyTables !== []) {
            $this->archiveLegacyTables($legacyTables);
            $this->dropLegacyTables($legacyTables);
        }

        foreach (self::TABLE_RENAMES as $plural => $singular) {
            if (Schema::hasTable($plural) && ! Schema::hasTable($singular)) {
                Schema::rename($plural, $singular);
            }
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLE_RENAMES, true) as $plural => $singular) {
            if (Schema::hasTable($singular) && ! Schema::hasTable($plural)) {
                Schema::rename($singular, $plural);
            }
        }
    }

    /** @param list<string> $legacyTables */
    private function archiveLegacyTables(array $legacyTables): void
    {
        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('Legacy domain tables must be archived from MySQL before this migration can run.');
        }

        $sourceDatabase = (string) DB::connection()->getDatabaseName();
        if ($sourceDatabase === '') {
            throw new RuntimeException('The current MySQL database name could not be determined.');
        }

        $archiveDatabase = $sourceDatabase.'_legacy_archive';
        DB::statement('CREATE DATABASE IF NOT EXISTS '.$this->quoteIdentifier($archiveDatabase).' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

        foreach ($legacyTables as $table) {
            $source = $this->qualifiedTable($sourceDatabase, $table);
            $archive = $this->qualifiedTable($archiveDatabase, $table);
            $sourceCount = (int) DB::table($table)->count();
            $archiveExists = (bool) (DB::selectOne(
                'SELECT COUNT(*) AS aggregate FROM information_schema.tables WHERE table_schema = ? AND table_name = ?',
                [$archiveDatabase, $table],
            )->aggregate ?? 0);

            if (! $archiveExists) {
                DB::statement("CREATE TABLE {$archive} LIKE {$source}");
                DB::statement("INSERT INTO {$archive} SELECT * FROM {$source}");
            }

            $archiveCount = (int) (DB::selectOne("SELECT COUNT(*) AS aggregate FROM {$archive}")->aggregate ?? 0);
            if ($archiveCount !== $sourceCount) {
                throw new RuntimeException("Legacy table {$table} was not archived completely.");
            }
        }
    }

    /** @param list<string> $legacyTables */
    private function dropLegacyTables(array $legacyTables): void
    {
        foreach (['vote', 'candidacy', 'voter', 'election', 'payment', 'candidate', 'organizer', 'user', 'candidate_document'] as $table) {
            if (in_array($table, $legacyTables, true)) {
                Schema::drop($table);
            }
        }
    }

    private function qualifiedTable(string $database, string $table): string
    {
        return $this->quoteIdentifier($database).'.'.$this->quoteIdentifier($table);
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }
};
