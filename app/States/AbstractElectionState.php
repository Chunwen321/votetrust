<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\States;

use App\Exceptions\BusinessRuleException;
use App\Models\Election;
use App\Services\TransitionContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

abstract class AbstractElectionState implements ElectionState
{
    public function canRegisterCandidate(Election $election, CarbonImmutable $now): bool
    {
        return false;
    }

    public function canVote(Election $election, CarbonImmutable $now): bool
    {
        return false;
    }

    protected function cancel(ElectionLifecycle $lifecycle, string $action, TransitionContext $context): bool
    {
        if ($action !== 'cancel') {
            return false;
        }
        if (! $context->actorOwns($lifecycle->election()) || $context->actorIsAdmin()) {
            throw new AuthorizationException('Only the owning organiser can cancel this election.');
        }

        $reason = trim((string) $context->input('reason', '')) ?: 'Cancelled by organiser';
        $lifecycle->moveTo(ElectionStatus::CANCELLED->value, $action, ['reason' => $reason]);

        return true;
    }

    protected function reject(string $action): never
    {
        throw new BusinessRuleException(sprintf(
            'Action "%s" is not allowed while the election is %s.',
            $action,
            strtolower(str_replace('_', ' ', $this->name())),
        ));
    }

    protected function date(Election $election, string $field): CarbonImmutable
    {
        $value = $election->getAttribute($field);
        if ($value === null || $value === '') {
            throw new BusinessRuleException("Election date is not configured: {$field}");
        }

        return CarbonImmutable::parse($value);
    }

    protected function paymentVerified(Election $election): bool
    {
        return $election->payment_verified_at !== null && $election->payment_verified_at !== '';
    }

    protected function adminApproved(Election $election): bool
    {
        return (int) $election->approved_by > 0
            && $election->approved_at !== null
            && $election->approved_at !== '';
    }
}
