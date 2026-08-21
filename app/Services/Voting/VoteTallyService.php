<?php

namespace App\Services\Voting;

use App\Models\Vote;

// PIC: Goh Chun Wen - Produces privacy-preserving aggregate election results.
final class VoteTallyService
{
    /**
     * @param  list<array{candidateId:int, name:string}>  $approvedBallot
     * @return array{
     *     tally:list<array{candidateId:int, candidateName:string, votes:int}>,
     *     totalVotes:int
     * }
     */
    public function tally(int $electionId, array $approvedBallot): array
    {
        $counts = Vote::query()
            ->where('election_id', $electionId)
            ->selectRaw('candidate_id, COUNT(*) as total')
            ->groupBy('candidate_id')
            ->pluck('total', 'candidate_id');

        $tally = array_values(array_map(
            static fn (array $candidate): array => [
                'candidateId' => (int) $candidate['candidateId'],
                'candidateName' => (string) $candidate['name'],
                'votes' => (int) $counts->get((int) $candidate['candidateId'], 0),
            ],
            $approvedBallot,
        ));

        return [
            'tally' => $tally,
            'totalVotes' => (int) array_sum(array_column($tally, 'votes')),
        ];
    }
}
