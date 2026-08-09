<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\ValueObjects;

use App\Support\CountryList;

final readonly class EligibilityRules
{
    public const PUBLIC = 'PUBLIC';

    public const RESTRICTED = 'RESTRICTED';

    private const LEGACY_NON_MALAYSIAN = 'NON_MALAYSIAN';

    /**
     * @param  array{type:string,minimumAge:?int,nationalityCountryCode:?string,excludedCountryCode:?string}  $rules
     */
    private function __construct(private array $rules, private ?string $legacySummary = null) {}

    public static function publicElection(): self
    {
        return new self([
            'type' => self::PUBLIC,
            'minimumAge' => null,
            'nationalityCountryCode' => null,
            'excludedCountryCode' => null,
        ]);
    }

    public static function restricted(?int $minimumAge, string $nationalityCountryCode): self
    {
        $countryCode = strtoupper(trim($nationalityCountryCode));

        return new self([
            'type' => self::RESTRICTED,
            'minimumAge' => $minimumAge !== null && $minimumAge >= 1 && $minimumAge <= 130
                ? $minimumAge
                : null,
            'nationalityCountryCode' => CountryList::isValidCode($countryCode) ? $countryCode : null,
            'excludedCountryCode' => null,
        ]);
    }

    public static function fromStored(?string $stored): self
    {
        $stored = trim((string) $stored);
        if ($stored === '') {
            return self::publicElection();
        }

        $decoded = json_decode($stored, true);
        if (is_array($decoded)) {
            $type = strtoupper((string) ($decoded['type'] ?? $decoded['mode'] ?? ''));
            if (in_array($type, [self::PUBLIC, self::RESTRICTED], true)) {
                if ($type === self::PUBLIC) {
                    return self::publicElection();
                }

                $minimumAge = isset($decoded['minimumAge']) && is_numeric($decoded['minimumAge'])
                    ? (int) $decoded['minimumAge']
                    : null;
                $countryCode = self::storedCountryCode($decoded);
                if ($countryCode === self::LEGACY_NON_MALAYSIAN) {
                    return new self([
                        'type' => self::RESTRICTED,
                        'minimumAge' => $minimumAge,
                        'nationalityCountryCode' => null,
                        'excludedCountryCode' => 'MY',
                    ]);
                }

                return self::restricted($minimumAge, $countryCode);
            }
        }

        // Plain-text legacy eligibility remains readable, but removed occupation
        // values are never interpreted as an authorization requirement.
        return new self([
            'type' => self::RESTRICTED,
            'minimumAge' => null,
            'nationalityCountryCode' => null,
            'excludedCountryCode' => null,
        ], $stored);
    }

    /** @return list<int> */
    public static function minimumAgeOptions(): array
    {
        return [16, 18, 21, 25, 30, 40, 60];
    }

    /** @return array<string, string> */
    public static function nationalityOptions(): array
    {
        return CountryList::options();
    }

    public function mode(): string
    {
        return $this->rules['type'];
    }

    public function minimumAge(): ?int
    {
        return $this->rules['minimumAge'];
    }

    public function nationalityCountryCode(): ?string
    {
        return $this->rules['nationalityCountryCode'];
    }

    public function nationalityFormValue(): string
    {
        return $this->nationalityCountryCode() ?? CountryList::ANY;
    }

    /**
     * @return array{
     *     type:string,
     *     minimumAge:?int,
     *     nationality:array{restricted:bool,countryCode:?string,countryName:?string,excludedCountryCode?:string}
     * }
     */
    public function toArray(): array
    {
        $countryCode = $this->nationalityCountryCode();
        $excludedCountryCode = $this->rules['excludedCountryCode'];
        $nationality = [
            'restricted' => $countryCode !== null || $excludedCountryCode !== null,
            'countryCode' => $countryCode,
            'countryName' => CountryList::name($countryCode),
        ];

        if ($excludedCountryCode !== null) {
            $nationality['excludedCountryCode'] = $excludedCountryCode;
            $nationality['countryName'] = 'Any country except '.CountryList::name($excludedCountryCode).' (legacy rule)';
        }

        return [
            'type' => $this->mode(),
            'minimumAge' => $this->minimumAge(),
            'nationality' => $nationality,
        ];
    }

    public function toStorage(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    public function summary(): string
    {
        if ($this->legacySummary !== null) {
            return $this->legacySummary;
        }
        if ($this->mode() === self::PUBLIC) {
            return 'Public election - no voter restrictions';
        }

        $criteria = [];
        if ($this->minimumAge() !== null) {
            $criteria[] = 'Age '.$this->minimumAge().' or older';
        }
        if ($this->nationalityCountryCode() !== null) {
            $criteria[] = 'Nationality: '.CountryList::name($this->nationalityCountryCode());
        } elseif ($this->rules['excludedCountryCode'] !== null) {
            $criteria[] = 'Nationality: any country except '.CountryList::name($this->rules['excludedCountryCode']);
        }

        return $criteria === [] ? 'Restricted election' : implode(' | ', $criteria);
    }

    /** @param array<string, mixed> $decoded */
    private static function storedCountryCode(array $decoded): string
    {
        $nationality = $decoded['nationality'] ?? null;
        if (is_array($nationality)) {
            $raw = $nationality['countryCode'] ?? null;
            if (($nationality['excludedCountryCode'] ?? null) === 'MY') {
                $raw = self::LEGACY_NON_MALAYSIAN;
            }
        } else {
            $raw = $decoded['nationalityCountryCode'] ?? $nationality ?? CountryList::ANY;
        }
        $value = strtoupper(trim((string) $raw));

        return match ($value) {
            'MALAYSIAN' => 'MY',
            self::LEGACY_NON_MALAYSIAN => self::LEGACY_NON_MALAYSIAN,
            default => $value,
        };
    }
}
