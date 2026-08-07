<?php
declare(strict_types=1);
namespace VoteTrust\Candidate\Contracts;
interface RegistrationService { /** @param array<string,mixed> $input @param list<array<string,mixed>> $files @param array<string,mixed> $photo */ public function apply(int $userId, int $electionId, array $input, array $files, array $photo): int; }
