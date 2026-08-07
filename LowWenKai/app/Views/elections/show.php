<?php

use VoteTrust\Election\Core\Csrf;
use VoteTrust\Election\Models\Candidacy;
use VoteTrust\Election\Models\Election;

$pageTitle = (string) $election->get('title');
$stateOrder = [
    Election::DRAFT,
    Election::PENDING_PAYMENT,
    Election::PENDING_ADMIN_APPROVAL,
    Election::NOMINATION_OPEN,
    Election::VOTING_OPEN,
    Election::CLOSED,
];
$currentIndex = array_search($election->status(), $stateOrder, true);
$cancelled = $election->status() === Election::CANCELLED;
$rejected = $election->status() === Election::REJECTED;
$statusLabel = static fn (string $status): string => ucwords(strtolower(str_replace('_', ' ', $status)));
$primaryActions = array_values(array_filter(
    $actions,
    static fn (array $action): bool => $action['tone'] !== 'danger'
));
$secondaryActions = array_values(array_filter(
    $actions,
    static fn (array $action): bool => $action['tone'] === 'danger'
));
$primaryAction = $primaryActions[0] ?? null;
$candidateSummary = is_array($candidateSummary ?? null) ? $candidateSummary : [
    'applications' => [],
    'total' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'ready' => false,
];
$candidateApplications = is_array($candidateSummary['applications'] ?? null)
    ? $candidateSummary['applications']
    : [];
$showCandidateManagement = $election->status() === Election::NOMINATION_OPEN;
$now = new DateTimeImmutable('now');
$votingWindowReached = $now >= $election->date('nomination_end')
    && $now >= $election->date('voting_start');
if ($showCandidateManagement && (!(bool) $candidateSummary['ready'] || !$votingWindowReached)) {
    $primaryAction = null;
}
$isPaymentAction = is_array($primaryAction) && $primaryAction['action'] === 'verify_payment';
$primaryButtonLabel = $isPaymentAction
    ? 'Pay ' . (string) $election->get('currency') . ' ' . number_format((float) $election->get('hosting_fee'), 2)
    : (is_array($primaryAction) ? (string) $primaryAction['label'] : 'Continue');
$waitingMessage = match ($election->status()) {
    Election::PENDING_ADMIN_APPROVAL => $isAdminView
        ? 'Review the payment and election configuration before making a decision.'
        : 'No action is needed from you. A system administrator must review this election.',
    Election::CLOSED => 'This election is complete and no further state changes are available.',
    Election::CANCELLED => 'This election was cancelled and no further state changes are permitted.',
    Election::REJECTED => 'This election was rejected by the administrator.',
    default => 'There is no action available for your account at this stage.',
};
$approvedBy = $election->approvedBy()?->username() ?? (
    $election->get('approved_by') !== null ? 'User #' . $election->get('approved_by') : null
);
?>

<a class="back-link" href="<?= e($backUrl) ?>">&larr; <?= $isAdminView ? 'Approval workspace' : 'All elections' ?></a>

<section class="page-heading split-heading election-heading">
    <div>
        <div class="heading-meta">
            <span class="status status-<?= e(strtolower(str_replace('_', '-', $election->status()))) ?>"><?= e($availability['lifecycleLabel']) ?></span>
            <span>Election #<?= e($election->id()) ?></span>
        </div>
        <h1><?= e($election->get('title')) ?></h1>
        <p><?= e($election->get('description')) ?></p>
    </div>
    <?php if (!$isAdminView && $election->status() === Election::DRAFT): ?>
        <a class="button button-quiet" href="<?= e(url('/elections/' . $election->id() . '/edit')) ?>">Edit election</a>
    <?php endif; ?>
</section>

<section class="card lifecycle-card" aria-labelledby="lifecycle-heading">
    <div class="progress-heading">
        <div>
            <p class="eyebrow">State pattern</p>
            <h2 id="lifecycle-heading">Election progress</h2>
        </div>
        <?php if (is_int($currentIndex)): ?>
            <span class="progress-count">Step <?= e($currentIndex + 1) ?> of <?= e(count($stateOrder)) ?></span>
        <?php endif; ?>
    </div>
    <?php if ($cancelled): ?>
        <div class="cancelled-banner"><strong>Cancelled.</strong> No further transitions are permitted.</div>
    <?php elseif ($rejected): ?>
        <div class="cancelled-banner"><strong>Rejected.</strong> <?= e($election->get('rejection_reason', 'No reason supplied.')) ?></div>
    <?php else: ?>
        <ol class="lifecycle">
            <?php foreach ($stateOrder as $index => $state): ?>
                <?php $isActive = $index === $currentIndex; $isComplete = is_int($currentIndex) && $index < $currentIndex; ?>
                <li class="<?= $isActive ? 'active' : ($isComplete ? 'complete' : '') ?>">
                    <span class="lifecycle-dot"><?= $isComplete ? '&#10003;' : (string) ($index + 1) ?></span>
                    <span><?= e($statusLabel($state)) ?></span>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>

<div class="workspace-grid">
    <div class="workspace-primary">
        <?php if ($showCandidateManagement): ?>
            <section class="card candidate-review-card" aria-labelledby="candidate-review-heading">
                <div class="candidate-review-heading">
                    <div>
                        <p class="eyebrow">Ballot preparation</p>
                        <h2 id="candidate-review-heading">Candidate selection</h2>
                    </div>
                    <span class="result-pill <?= $candidateSummary['ready'] ? 'result-good' : 'result-waiting' ?>">
                        <?= $candidateSummary['ready'] ? 'Ballot ready' : 'Review required' ?>
                    </span>
                </div>
                <p class="focus-description">Candidates register through the Candidate Registration Module. Review each application before voting can open.</p>

                <div class="candidate-stats" aria-label="Candidate application summary">
                    <span><strong><?= e($candidateSummary['total']) ?></strong> Registered</span>
                    <span><strong><?= e($candidateSummary['approved']) ?></strong> Approved</span>
                    <span><strong><?= e($candidateSummary['pending']) ?></strong> Pending</span>
                </div>

                <?php if ($candidateApplications === []): ?>
                    <div class="candidate-empty">
                        <strong>No candidate applications yet</strong>
                        <p>Paid applications will appear here after they are submitted by the Candidate Registration Module.</p>
                    </div>
                <?php else: ?>
                    <?php if (!$isAdminView): ?>
                        <form method="post" action="<?= e((string) $candidateReviewUrl) ?>">
                            <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
                    <?php endif; ?>

                    <div class="candidate-table-wrap">
                        <table class="candidate-table">
                            <thead><tr><th>Candidate</th><th>Registered</th><th>Deposit</th><th>Ballot decision</th></tr></thead>
                            <tbody>
                            <?php foreach ($candidateApplications as $application): ?>
                                <?php
                                $candidateUser = $application->candidateUser();
                                $candidateName = $candidateUser?->username() ?? ('Candidate #' . $application->get('candidate_id'));
                                $registeredAt = new DateTimeImmutable((string) $application->get('registered_at'));
                                $applicationStatus = $application->status();
                                ?>
                                <tr>
                                    <td>
                                        <span class="candidate-name"><span class="candidate-avatar" aria-hidden="true"><?= e(strtoupper(substr($candidateName, 0, 1))) ?></span><?= e($candidateName) ?></span>
                                    </td>
                                    <td><?= e($registeredAt->format('d M Y, H:i')) ?></td>
                                    <td>
                                        <span class="candidate-deposit"><span class="result-pill result-good">Verified</span><small>Candidate Module</small></span>
                                    </td>
                                    <td>
                                        <?php if ($isAdminView): ?>
                                            <span class="result-pill <?= $applicationStatus === Candidacy::APPROVED ? 'result-good' : ($applicationStatus === Candidacy::REJECTED ? 'result-blocked' : 'result-waiting') ?>"><?= e($applicationStatus) ?></span>
                                        <?php else: ?>
                                            <select class="candidate-decision-select" name="decisions[<?= e($application->id()) ?>]" aria-label="Decision for <?= e($candidateName) ?>">
                                                <option value="<?= e(Candidacy::PENDING) ?>" <?= $applicationStatus === Candidacy::PENDING ? 'selected' : '' ?>>Pending review</option>
                                                <option value="<?= e(Candidacy::APPROVED) ?>" <?= $applicationStatus === Candidacy::APPROVED ? 'selected' : '' ?>>Approve for ballot</option>
                                                <option value="<?= e(Candidacy::REJECTED) ?>" <?= $applicationStatus === Candidacy::REJECTED ? 'selected' : '' ?>>Reject application</option>
                                            </select>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (!$isAdminView): ?>
                        <div class="candidate-review-actions">
                            <div>
                                <label for="candidate-review-password">Confirm current password</label>
                                <input id="candidate-review-password" name="current_password" type="password" autocomplete="current-password" required>
                                <small class="field-help">Required before changing who can appear on the ballot.</small>
                            </div>
                            <button class="button button-primary" type="submit">Save candidate decisions</button>
                        </div>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($candidateSummary['ready']): ?>
                    <div class="ballot-message ballot-ready">
                        <strong>Ballot ready.</strong>
                        <?= $votingWindowReached
                            ? 'All applications are reviewed and voting can now be opened.'
                            : 'Open voting becomes available on ' . e($election->date('voting_start')->format('d M Y, H:i')) . '.' ?>
                    </div>
                <?php else: ?>
                    <div class="ballot-message"><strong>Voting locked.</strong> Approve at least one candidate and resolve every pending application.</div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if (is_array($primaryAction)): ?>
            <section class="card focus-card" aria-labelledby="next-action-heading">
                <p class="eyebrow">Next action</p>
                <h2 id="next-action-heading"><?= e($primaryAction['label']) ?></h2>
                <p class="focus-description"><?= e($primaryAction['description']) ?></p>

                <form class="focus-form" method="post" action="<?= e($transitionUrl) ?>">
                    <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
                    <input type="hidden" name="action" value="<?= e($primaryAction['action']) ?>">

                    <?php if ($primaryAction['requiresPaymentReference']): ?>
                        <!-- Local simulator only: the real Payment Module will create and return this reference. -->
                        <input type="hidden" name="payment_reference" value="VT-HOST-PAID-001">
                        <div class="checkout-summary">
                            <span>Amount due</span>
                            <strong><?= e($election->get('currency')) ?> <?= e(number_format((float) $election->get('hosting_fee'), 2)) ?></strong>
                            <small>Secure payment handled by the Payment Module</small>
                        </div>
                    <?php endif; ?>

                    <?php if ($primaryAction['requiresReason']): ?>
                        <label for="reason-<?= e($primaryAction['action']) ?>">Reason</label>
                        <input id="reason-<?= e($primaryAction['action']) ?>" name="reason" maxlength="250" required>
                    <?php endif; ?>

                    <label for="password-<?= e($primaryAction['action']) ?>"><?= $isPaymentAction ? 'Confirm current password' : 'Current password' ?></label>
                    <input id="password-<?= e($primaryAction['action']) ?>" name="current_password" type="password" autocomplete="current-password" required>
                    <?php if ($isPaymentAction): ?>
                        <small class="field-help">This password stays in VoteTrust and is not shared with the Payment Module.</small>
                    <?php endif; ?>
                    <button class="button button-primary" type="submit"><?= e($primaryButtonLabel) ?></button>
                </form>
            </section>
        <?php elseif (!$showCandidateManagement): ?>
            <section class="card focus-card status-focus">
                <p class="eyebrow">Current status</p>
                <h2><?= e($availability['lifecycleLabel']) ?></h2>
                <p><?= e($waitingMessage) ?></p>
            </section>
        <?php endif; ?>

        <?php if ($secondaryActions !== []): ?>
            <details class="card disclosure-card danger-disclosure">
                <summary>
                    <span><strong>Other actions</strong><small>Cancel, withdraw or reject</small></span>
                    <span class="summary-toggle">Show</span>
                </summary>
                <div class="disclosure-content">
                    <?php foreach ($secondaryActions as $action): ?>
                        <form class="secondary-action" method="post" action="<?= e($transitionUrl) ?>">
                            <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
                            <input type="hidden" name="action" value="<?= e($action['action']) ?>">
                            <h3><?= e($action['label']) ?></h3>
                            <p><?= e($action['description']) ?></p>

                            <?php if ($action['requiresReason']): ?>
                                <label for="reason-<?= e($action['action']) ?>"><?= $action['action'] === 'reject' ? 'Rejection reason' : 'Cancellation reason' ?></label>
                                <input id="reason-<?= e($action['action']) ?>" name="reason" maxlength="250" required>
                            <?php endif; ?>

                            <label for="password-<?= e($action['action']) ?>">Current password</label>
                            <input id="password-<?= e($action['action']) ?>" name="current_password" type="password" autocomplete="current-password" required>
                            <button class="button button-danger" type="submit"><?= e($action['label']) ?></button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endif; ?>
    </div>

    <aside class="card overview-card" aria-labelledby="overview-heading">
        <div class="card-topline">
            <h2 id="overview-heading">Election overview</h2>
            <span class="muted">Version <?= e($election->get('version')) ?></span>
        </div>

        <dl class="overview-list">
            <div>
                <dt>Eligibility</dt>
                <dd><?= e($election->get('eligibility_rules')) ?></dd>
            </div>
            <div>
                <dt>Nominations</dt>
                <dd><?= e($election->date('nomination_start')->format('d M Y, H:i')) ?><br>to <?= e($election->date('nomination_end')->format('d M Y, H:i')) ?></dd>
            </div>
            <div>
                <dt>Voting</dt>
                <dd><?= e($election->date('voting_start')->format('d M Y, H:i')) ?><br>to <?= e($election->date('voting_end')->format('d M Y, H:i')) ?></dd>
            </div>
        </dl>

        <div class="status-list" aria-label="Election requirements and availability">
            <div>
                <span>Hosting payment</span>
                <strong class="result-pill <?= $election->paymentVerified() ? 'result-good' : 'result-waiting' ?>"><?= $election->paymentVerified() ? 'Verified' : 'Required' ?></strong>
            </div>
            <div>
                <span>Admin approval</span>
                <strong class="result-pill <?= $election->adminApproved() ? 'result-good' : ($rejected ? 'result-blocked' : 'result-waiting') ?>"><?= $election->adminApproved() ? 'Approved' : ($rejected ? 'Rejected' : 'Required') ?></strong>
            </div>
            <div>
                <span>Candidate registration</span>
                <strong class="result-pill <?= $availability['candidateRegistrationAllowed'] ? 'result-good' : 'result-blocked' ?>"><?= $availability['candidateRegistrationAllowed'] ? 'Allowed' : 'Blocked' ?></strong>
            </div>
            <div>
                <span>Vote casting</span>
                <strong class="result-pill <?= $availability['votingAllowed'] ? 'result-good' : 'result-blocked' ?>"><?= $availability['votingAllowed'] ? 'Allowed' : 'Blocked' ?></strong>
            </div>
        </div>

        <details class="technical-details">
            <summary>Payment and API details</summary>
            <div>
                <p><strong>Hosting fee:</strong> <?= e($election->get('currency')) ?> <?= e(number_format((float) $election->get('hosting_fee'), 2)) ?></p>
                <?php if ($election->paymentVerified()): ?>
                    <p><strong>Payment reference:</strong> <code><?= e($election->get('payment_reference')) ?></code></p>
                <?php endif; ?>
                <?php if ($approvedBy !== null): ?>
                    <p><strong>Approved by:</strong> <?= e($approvedBy) ?></p>
                <?php endif; ?>
                <p class="api-path">GET /api/v1/elections/<?= e($election->id()) ?>/availability</p>
            </div>
        </details>
    </aside>
</div>

<details class="card disclosure-card history-disclosure">
    <summary>
        <span><strong>Audit trail</strong><small><?= e(count($logs)) ?> recorded event<?= count($logs) === 1 ? '' : 's' ?></small></span>
        <span class="summary-toggle">Show</span>
    </summary>
    <div class="table-wrap disclosure-table">
        <table>
            <thead><tr><th>Time</th><th>Actor</th><th>Action</th><th>Transition</th><th>Request ID</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= e((new DateTimeImmutable((string) $log->get('created_at')))->format('d M Y H:i')) ?></td>
                    <td><?= e($log->actor()?->username() ?? ('User #' . $log->get('actor_user_id'))) ?></td>
                    <td><?= e(ucwords(str_replace('_', ' ', (string) $log->get('action')))) ?></td>
                    <td><code><?= e($log->get('from_state')) ?></code> &rarr; <code><?= e($log->get('to_state')) ?></code></td>
                    <td class="request-id"><?= e($log->get('request_id')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</details>
