<?php

declare(strict_types=1);

namespace VoteTrust\Election\ValueObjects;

/** Structured voter requirements stored as JSON in election.eligibility_rules. */
final class EligibilityRules
{
    public const PUBLIC = 'PUBLIC';
    public const RESTRICTED = 'RESTRICTED';

    /** @var array<string, string> */
    private const OCCUPATIONS = [
        'ANY' => 'Any occupation',
        'STUDENT' => 'Student',
        'ACADEMIC_STAFF' => 'Academic staff',
        'ADMINISTRATIVE_STAFF' => 'Administrative staff',
        'EMPLOYEE' => 'Employee',
        'SELF_EMPLOYED' => 'Self-employed',
        'RETIRED' => 'Retired',
    ];

    /** @var array<string, string> */
    private const NATIONALITIES = [
        'ANY' => 'Any nationality',
        'MALAYSIAN' => 'Malaysian',
        'NON_MALAYSIAN' => 'Non-Malaysian',
    ];

    /** @param array{mode:string,minimumAge:?int,occupation:string,nationality:string} $rules */
    private function __construct(
        private readonly array $rules,
        private readonly ?string $legacySummary = null
    ) {
    }

    public static function publicElection(): self
    {
        return new self([
            'mode' => self::PUBLIC,
            'minimumAge' => null,
            'occupation' => 'ANY',
            'nationality' => 'ANY',
        ]);
    }

    public static function restricted(?int $minimumAge, string $occupation, string $nationality): self
    {
        return new self([
            'mode' => self::RESTRICTED,
            'minimumAge' => $minimumAge,
            'occupation' => $occupation,
            'nationality' => $nationality,
        ]);
    }

    public static function fromStored(string $stored): self
    {
        $stored = trim($stored);
        if ($stored === '') {
            return self::publicElection();
        }

        $decoded = json_decode($stored, true);
        if (is_array($decoded) && in_array(($decoded['mode'] ?? null), [self::PUBLIC, self::RESTRICTED], true)) {
            return new self([
                'mode' => (string) $decoded['mode'],
                'minimumAge' => isset($decoded['minimumAge']) && is_numeric($decoded['minimumAge'])
                    ? (int) $decoded['minimumAge']
                    : null,
                'occupation' => array_key_exists((string) ($decoded['occupation'] ?? ''), self::OCCUPATIONS)
                    ? (string) $decoded['occupation']
                    : 'ANY',
                'nationality' => array_key_exists((string) ($decoded['nationality'] ?? ''), self::NATIONALITIES)
                    ? (string) $decoded['nationality']
                    : 'ANY',
            ]);
        }

        // Existing elections may still contain the original plain-text rule.
        return new self([
            'mode' => self::RESTRICTED,
            'minimumAge' => null,
            'occupation' => 'ANY',
            'nationality' => 'ANY',
        ], $stored);
    }

    /** @return list<int> */
    public static function minimumAgeOptions(): array
    {
        return [16, 18, 21, 25, 30, 40, 60];
    }

    /** @return array<string, string> */
    public static function occupationOptions(): array
    {
        return self::OCCUPATIONS;
    }

    /** @return array<string, string> */
    public static function nationalityOptions(): array
    {
        return self::NATIONALITIES;
    }

    public function mode(): string
    {
        return $this->rules['mode'];
    }

    public function minimumAge(): ?int
    {
        return $this->rules['minimumAge'];
    }

    public function occupation(): string
    {
        return $this->rules['occupation'];
    }

    public function nationality(): string
    {
        return $this->rules['nationality'];
    }

    /** @return array{mode:string,minimumAge:?int,occupation:string,nationality:string} */
    public function toArray(): array
    {
        return $this->rules;
    }

    public function toStorage(): string
    {
        return json_encode($this->rules, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    public function summary(): string
    {
        if ($this->legacySummary !== null) {
            return $this->legacySummary;
        }
        if ($this->mode() === self::PUBLIC) {
            return 'Public election — no voter restrictions';
        }

        $criteria = [];
        if ($this->minimumAge() !== null) {
            $criteria[] = 'Age ' . $this->minimumAge() . ' or older';
        }
        if ($this->occupation() !== 'ANY') {
            $criteria[] = 'Occupation: ' . self::OCCUPATIONS[$this->occupation()];
        }
        if ($this->nationality() !== 'ANY') {
            $criteria[] = 'Nationality: ' . self::NATIONALITIES[$this->nationality()];
        }

        return $criteria === [] ? 'Restricted election' : implode(' · ', $criteria);
    }
}
