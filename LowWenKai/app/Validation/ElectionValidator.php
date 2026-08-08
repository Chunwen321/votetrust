<?php

declare(strict_types=1);

namespace VoteTrust\Election\Validation;

use DateTimeImmutable;
use VoteTrust\Election\Exceptions\ValidationException;
use VoteTrust\Election\ValueObjects\EligibilityRules;

final class ElectionValidator
{
    /** @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    public function validate(array $input): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $eligibilityMode = strtoupper(trim((string) ($input['eligibility_mode'] ?? '')));
        $minimumAgeInput = trim((string) ($input['eligibility_minimum_age'] ?? ''));
        $occupation = strtoupper(trim((string) ($input['eligibility_occupation'] ?? 'ANY')));
        $nationality = strtoupper(trim((string) ($input['eligibility_nationality'] ?? 'ANY')));
        $currency = strtoupper(trim((string) ($input['currency'] ?? 'MYR')));
        $fee = filter_var($input['hosting_fee'] ?? null, FILTER_VALIDATE_FLOAT);
        $errors = [];

        if (mb_strlen($title) < 5 || mb_strlen($title) > 120) {
            $errors['title'] = 'Title must contain between 5 and 120 characters.';
        }
        if (mb_strlen($description) < 20 || mb_strlen($description) > 2000) {
            $errors['description'] = 'Description must contain between 20 and 2,000 characters.';
        }
        if (!in_array($eligibilityMode, [EligibilityRules::PUBLIC, EligibilityRules::RESTRICTED], true)) {
            $errors['eligibility_mode'] = 'Choose whether this election is public or restricted.';
        }

        $minimumAge = null;
        if ($minimumAgeInput !== '') {
            $parsedAge = filter_var($minimumAgeInput, FILTER_VALIDATE_INT);
            if ($parsedAge === false || !in_array((int) $parsedAge, EligibilityRules::minimumAgeOptions(), true)) {
                $errors['eligibility_minimum_age'] = 'Choose a valid minimum age.';
            } else {
                $minimumAge = (int) $parsedAge;
            }
        }
        if (!array_key_exists($occupation, EligibilityRules::occupationOptions())) {
            $errors['eligibility_occupation'] = 'Choose a valid occupation.';
        }
        if (!array_key_exists($nationality, EligibilityRules::nationalityOptions())) {
            $errors['eligibility_nationality'] = 'Choose a valid nationality.';
        }
        if ($eligibilityMode === EligibilityRules::RESTRICTED
            && $minimumAge === null
            && $occupation === 'ANY'
            && $nationality === 'ANY') {
            $errors['eligibility_criteria'] = 'Select at least one eligibility requirement.';
        }
        if ($fee === false || $fee < 1 || $fee > 10000) {
            $errors['hosting_fee'] = 'Hosting fee must be between 1.00 and 10,000.00.';
        }
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            $errors['currency'] = 'Currency must be a three-letter ISO code.';
        }

        $dates = [];
        foreach (['nomination_start', 'nomination_end', 'voting_start', 'voting_end'] as $field) {
            $parsed = $this->parseDate((string) ($input[$field] ?? ''));
            if (!$parsed instanceof DateTimeImmutable) {
                $errors[$field] = 'Enter a valid date and time.';
            } else {
                $dates[$field] = $parsed;
            }
        }

        if (count($dates) === 4) {
            if ($dates['nomination_start'] >= $dates['nomination_end']) {
                $errors['nomination_end'] = 'Nomination end must be after nomination start.';
            }
            if ($dates['nomination_end'] > $dates['voting_start']) {
                $errors['voting_start'] = 'Voting cannot start before nominations end.';
            }
            if ($dates['voting_start'] >= $dates['voting_end']) {
                $errors['voting_end'] = 'Voting end must be after voting start.';
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $rules = $eligibilityMode === EligibilityRules::PUBLIC
            ? EligibilityRules::publicElection()
            : EligibilityRules::restricted($minimumAge, $occupation, $nationality);

        return [
            'title' => $title,
            'description' => $description,
            'eligibility_rules' => $rules->toStorage(),
            'nomination_start' => $dates['nomination_start']->format('Y-m-d H:i:s'),
            'nomination_end' => $dates['nomination_end']->format('Y-m-d H:i:s'),
            'voting_start' => $dates['voting_start']->format('Y-m-d H:i:s'),
            'voting_end' => $dates['voting_end']->format('Y-m-d H:i:s'),
            'hosting_fee' => number_format((float) $fee, 2, '.', ''),
            'currency' => $currency,
        ];
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        foreach (['Y-m-d\\TH:i', 'Y-m-d H:i:s'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            $errors = DateTimeImmutable::getLastErrors();
            if ($date instanceof DateTimeImmutable && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date;
            }
        }

        return null;
    }
}

