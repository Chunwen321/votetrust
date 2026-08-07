<?php
declare(strict_types=1);
namespace VoteTrust\Candidate\Services;
use VoteTrust\Candidate\Contracts\RegistrationService;
use VoteTrust\Candidate\Security\ApplicationValidator;
/** Proxy pattern: validates every request before it reaches the real registration service. */
final class RegistrationProxy implements RegistrationService {
    public function __construct(private RegistrationService $real, private ApplicationValidator $validator) {}
    public function apply(int $userId, int $electionId, array $input, array $files, array $photo): int {
        if ($userId < 1 || $electionId < 1) throw new \InvalidArgumentException('Invalid candidate or election.');
        return $this->real->apply($userId, $electionId, $this->validator->validate($input), $files, $photo);
    }
}
