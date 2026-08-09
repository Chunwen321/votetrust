<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Candidate is an election-specific role of a normal user. Existing profile
 * details are copied to user, then candidacy.user_id becomes the direct link.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Fresh installations already use the simplified schema in the
        // original migrations, so only convert databases created earlier.
        if (! Schema::hasTable('candidate')) {
            return;
        }

        if (! Schema::hasColumn('user', 'phone_number')) {
            Schema::table('user', function (Blueprint $table): void {
                $table->string('phone_number', 30)->nullable();
            });
        }

        $this->copyCandidateProfilesToUsers();

        $this->dropMySqlForeignKey('candidacy', 'candidate_id');
        Schema::table('candidacy', function (Blueprint $table): void {
            $table->dropUnique('candidacies_candidate_id_election_id_unique');
            $table->renameColumn('candidate_id', 'user_id');
        });
        Schema::table('candidacy', function (Blueprint $table): void {
            $table->foreign('user_id')->references('id')->on('user')->cascadeOnDelete();
            $table->unique(['user_id', 'election_id'], 'candidacy_user_id_election_id_unique');
        });

        // Votes already store the candidate user's ID, so only their foreign
        // key needs to point directly to user before candidate is removed.
        $this->dropMySqlForeignKey('vote', 'candidate_id');
        Schema::table('vote', function (Blueprint $table): void {
            $table->foreign('candidate_id')->references('id')->on('user')->cascadeOnDelete();
        });

        Schema::drop('candidate');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql'
            || ! Schema::hasTable('candidacy')
            || Schema::hasTable('candidate')) {
            return;
        }

        Schema::create('candidate', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained('user')->cascadeOnDelete();
            $table->timestamps();
            $table->string('full_name', 100)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone_number', 30)->nullable();
        });

        $candidateIds = DB::table('candidacy')->pluck('user_id')
            ->merge(DB::table('vote')->pluck('candidate_id'))
            ->unique()
            ->values();
        foreach (DB::table('user')->whereIn('id', $candidateIds)->orderBy('id')->cursor() as $user) {
            DB::table('candidate')->insert([
                'user_id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ]);
        }

        $this->dropMySqlForeignKey('candidacy', 'user_id');
        Schema::table('candidacy', function (Blueprint $table): void {
            $table->dropUnique('candidacy_user_id_election_id_unique');
            $table->renameColumn('user_id', 'candidate_id');
        });
        Schema::table('candidacy', function (Blueprint $table): void {
            $table->foreign('candidate_id')->references('user_id')->on('candidate')->cascadeOnDelete();
            $table->unique(['candidate_id', 'election_id'], 'candidacies_candidate_id_election_id_unique');
        });

        $this->dropMySqlForeignKey('vote', 'candidate_id');
        Schema::table('vote', function (Blueprint $table): void {
            $table->foreign('candidate_id')->references('user_id')->on('candidate')->cascadeOnDelete();
        });
    }

    private function copyCandidateProfilesToUsers(): void
    {
        $columns = ['user_id'];
        foreach (['full_name', 'email', 'phone_number'] as $column) {
            if (Schema::hasColumn('candidate', $column)) {
                $columns[] = $column;
            }
        }

        foreach (DB::table('candidate')->select($columns)->orderBy('user_id')->cursor() as $candidate) {
            $user = DB::table('user')->where('id', $candidate->user_id)->first();
            if ($user === null) {
                throw new RuntimeException("Candidate {$candidate->user_id} has no matching user record.");
            }

            $updates = [];
            foreach (['full_name', 'phone_number'] as $column) {
                $value = trim((string) ($candidate->{$column} ?? ''));
                if ($value !== '') {
                    $updates[$column] = $value;
                }
            }

            $email = trim((string) ($candidate->email ?? ''));
            if ($email !== '' && $email !== $user->email) {
                $alreadyUsed = DB::table('user')
                    ->where('email', $email)
                    ->where('id', '<>', $candidate->user_id)
                    ->exists();
                if ($alreadyUsed) {
                    throw new RuntimeException("Candidate email {$email} is already used by another user.");
                }

                $updates['email'] = $email;
            }

            if ($updates !== []) {
                DB::table('user')->where('id', $candidate->user_id)->update($updates);
            }
        }
    }

    private function dropMySqlForeignKey(string $table, string $column): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $foreignKey = DB::selectOne(
            'SELECT constraint_name AS name FROM information_schema.key_column_usage
             WHERE table_schema = ? AND table_name = ? AND column_name = ?
             AND referenced_table_name IS NOT NULL
             LIMIT 1',
            [DB::connection()->getDatabaseName(), $table, $column],
        );
        if ($foreignKey === null) {
            return;
        }

        DB::statement(
            'ALTER TABLE '.$this->quoteIdentifier($table)
            .' DROP FOREIGN KEY '.$this->quoteIdentifier((string) $foreignKey->name),
        );
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }
};
