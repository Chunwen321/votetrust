<?php
declare(strict_types=1);
namespace VoteTrust\Candidate\Security;
use InvalidArgumentException;
final class ApplicationValidator {
    /** @param array<string,mixed> $input @return array{position:string,manifesto:string,details:string} */
    public function validate(array $input): array {
        $position = trim((string)($input['position_sought'] ?? ''));
        $manifesto = trim((string)($input['manifesto'] ?? ''));
        $details = trim((string)($input['supporting_details'] ?? ''));
        if (!preg_match('/^[\p{L}\p{N} .,&()\/-]{2,100}$/u', $position)) throw new InvalidArgumentException('Position must be 2-100 valid characters.');
        if (mb_strlen($manifesto) < 50 || mb_strlen($manifesto) > 5000) throw new InvalidArgumentException('Manifesto must be 50-5,000 characters.');
        if (mb_strlen($details) > 3000) throw new InvalidArgumentException('Supporting details cannot exceed 3,000 characters.');
        return compact('position', 'manifesto', 'details');
    }
}
