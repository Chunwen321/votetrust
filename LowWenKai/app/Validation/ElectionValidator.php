<?php

declare(strict_types=1);

namespace VoteTrust\Election\Validation;

use DateTimeImmutable;
use VoteTrust\Election\Exceptions\ValidationException;

final class ElectionValidator
{
    /** @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    public function validate(array $input): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $rules = trim((string) ($input['eligibility_rules'] ?? ''));
        $currency = strtoupper(trim((string) ($input['currency'] ?? 'MYR')));
        $fee = filter_var($input['hosting_fee'] ?? null, FILTER_VALIDATE_FLOAT);
        $errors = [];

        if (mb_strlen($title) < 5 || mb_strlen($title) > 120) {
            $errors['title'] = 'Title must contain between 5 and 120 characters.';
        }
        if (mb_strlen($description) < 20 || mb_strlen($description) > 2000) {
            $errors['description'] = 'Description must contain between 20 and 2,000 characters.';
        }
        if (mb_strlen($rules) < 5 || mb_strlen($rules) > 1000) {
            $errors['eligibility_rules'] = 'Eligibility rules must contain between 5 and 1,000 characters.';
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

        return [
            'title' => $title,
            'description' => $description,
            'eligibility_rules' => $rules,
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

