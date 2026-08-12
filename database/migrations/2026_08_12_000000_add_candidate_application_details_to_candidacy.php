<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// PIC: Wong Shun Bin — Store distinct candidate application answers separately.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidacy', function (Blueprint $table): void {
            $table->text('candidate_introduction')->nullable()->after('manifesto');
            $table->text('qualifications')->nullable()->after('candidate_introduction');
            $table->text('reason_for_candidacy')->nullable()->after('qualifications');
        });

        DB::table('candidacy')
            ->whereNotNull('supporting_details')
            ->orderBy('id')
            ->eachById(function (object $candidacy): void {
                $details = (string) $candidacy->supporting_details;
                preg_match('/Candidate Introduction:\R(.*?)(?=\R{2}Qualifications \/ Experience:|\R{2}Reason for Candidacy:|\z)/s', $details, $introduction);
                preg_match('/Qualifications \/ Experience:\R(.*?)(?=\R{2}Reason for Candidacy:|\z)/s', $details, $qualifications);
                preg_match('/Reason for Candidacy:\R(.*)\z/s', $details, $reason);

                if ($introduction !== [] || $qualifications !== [] || $reason !== []) {
                    DB::table('candidacy')->where('id', $candidacy->id)->update([
                        'candidate_introduction' => isset($introduction[1]) ? trim($introduction[1]) : null,
                        'qualifications' => isset($qualifications[1]) ? trim($qualifications[1]) : null,
                        'reason_for_candidacy' => isset($reason[1]) ? trim($reason[1]) : null,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('candidacy', function (Blueprint $table): void {
            $table->dropColumn([
                'candidate_introduction',
                'qualifications',
                'reason_for_candidacy',
            ]);
        });
    }
};
