<?php

use VoteTrust\Election\Core\Csrf;
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
?>
<a class="back-link" href="<?= e($backUrl) ?>">&larr; <?= $isAdminView ? 'Approval workspace' : 'All elections' ?></a>
<section class="page-heading split-heading election-heading">
    <div>
        <div class="heading-meta">
            <span class="status status-<?= e(strtolower(str_replace('_', '-', $election->status()))) ?>"><?= e($availability['lifecycleLabel']) ?></span>
            <span>Election #<?= e($election->id()) ?></span>
            <span>Version <?= e($election->get('version')) ?></span>
        </div>
        <h1><?= e($election->get('title')) ?></h1>
        <p><?= e($election->get('description')) ?></p>
    </div>
    <?php if (!$isAdminView && $election->status() === Election::DRAFT): ?>
        <a class="button button-quiet" href="<?= e(url('/elections/' . $election->id() . '/edit')) ?>">Edit configuration</a>
    <?php endif; ?>
</section>

<section class="card lifecycle-card" aria-labelledby="lifecycle-heading">
    <div class="card-topline"><h2 id="lifecycle-heading">Election lifecycle</h2><span class="muted">State pattern</span></div>
    <?php if ($cancelled): ?>
        <div class="cancelled-banner"><strong>This election was cancelled.</strong> No further transitions are permitted.</div>
    <?php elseif ($rejected): ?>
        <div class="cancelled-banner"><strong>This election was rejected by the administrator.</strong> <?= e($election->get('rejection_reason', 'No reason supplied.')) ?></div>
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

<div class="dashboard-grid dashboard-grid-four">
    <section class="card detail-card">
        <div class="card-topline"><h2>Configuration</h2><span class="muted">Locked after draft</span></div>
        <dl class="detail-list">
            <div><dt>Eligibility</dt><dd><?= e($election->get('eligibility_rules')) ?></dd></div>
            <div><dt>Nomination period</dt><dd><?= e($election->date('nomination_start')->format('d M Y, H:i')) ?><br>to <?= e($election->date('nomination_end')->format('d M Y, H:i')) ?></dd></div>
            <div><dt>Voting period</dt><dd><?= e($election->date('voting_start')->format('d M Y, H:i')) ?><br>to <?= e($election->date('voting_end')->format('d M Y, H:i')) ?></dd></div>
        </dl>
    </section>

    <section class="card payment-card">
        <div class="card-topline"><h2>Hosting payment</h2><span class="payment-indicator <?= $election->paymentVerified() ? 'verified' : '' ?>"><?= $election->paymentVerified() ? 'Verified' : 'Required' ?></span></div>
        <div class="money"><span><?= e($election->get('currency')) ?></span><?= e(number_format((float) $election->get('hosting_fee'), 2)) ?></div>
        <p><?= $election->paymentVerified() ? 'Confirmed by the Payment Module API; administrator approval is still required.' : 'The exact payer, amount, currency and reference must be verified before administrator review.' ?></p>
        <?php if ($election->paymentVerified()): ?>
            <code><?= e($election->get('payment_reference')) ?></code>
        <?php else: ?>
            <small class="muted">Sandbox confirmed reference: VT-HOST-PAID-001</small>
        <?php endif; ?>
    </section>

    <section class="card detail-card approval-card">
        <div class="card-topline"><h2>Administrator approval</h2><span class="payment-indicator <?= $election->adminApproved() ? 'verified' : '' ?>"><?= $election->adminApproved() ? 'Approved' : ($rejected ? 'Rejected' : 'Required') ?></span></div>
        <?php if ($election->adminApproved()): ?>
            <p>Approved by <strong><?= e($election->approvedBy()?->username() ?? ('User #' . $election->get('approved_by'))) ?></strong>.</p>
            <small class="muted"><?= e((new DateTimeImmutable((string) $election->get('approved_at')))->format('d M Y, H:i')) ?></small>
        <?php elseif ($rejected): ?>
            <p><?= e($election->get('rejection_reason')) ?></p>
        <?php elseif ($election->status() === Election::PENDING_ADMIN_APPROVAL): ?>
            <p>The organiser has paid and is waiting for an independent administrator decision.</p>
        <?php else: ?>
            <p>Approval becomes available only after the hosting payment is confirmed.</p>
        <?php endif; ?>
    </section>

    <section class="card availability-card">
        <div class="card-topline"><h2>Module availability</h2><span class="muted">Live API result</span></div>
        <div class="availability-row">
            <span>Candidate registration</span>
            <strong class="<?= $availability['candidateRegistrationAllowed'] ? 'yes' : 'no' ?>"><?= $availability['candidateRegistrationAllowed'] ? 'Allowed' : 'Blocked' ?></strong>
        </div>
        <div class="availability-row">
            <span>Vote casting</span>
            <strong class="<?= $availability['votingAllowed'] ? 'yes' : 'no' ?>"><?= $availability['votingAllowed'] ? 'Allowed' : 'Blocked' ?></strong>
        </div>
        <p class="api-path">GET /api/v1/elections/<?= e($election->id()) ?>/availability</p>
    </section>
</div>

<?php if ($actions !== []): ?>
<section class="page-section">
    <div class="section-heading"><div><p class="eyebrow">Critical operations</p><h2>Available state transitions</h2></div><p>Every interactive transition requires the current <?= $isAdminView ? 'administrator' : 'organiser' ?> password.</p></div>
    <div class="action-grid">
        <?php foreach ($actions as $action): ?>
            <form class="card action-card" method="post" action="<?= e($transitionUrl) ?>">
                <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
                <input type="hidden" name="action" value="<?= e($action['action']) ?>">
                <h3><?= e($action['label']) ?></h3>
                <p><?= e($action['description']) ?></p>
                <?php if ($action['requiresPaymentReference']): ?>
                    <label for="payment-<?= e($action['action']) ?>">Payment reference</label>
                    <input id="payment-<?= e($action['action']) ?>" name="payment_reference" value="VT-HOST-PAID-001" required>
                <?php endif; ?>
                <?php if ($action['requiresReason']): ?>
                    <label for="reason-<?= e($action['action']) ?>"><?= $action['action'] === 'reject' ? 'Rejection reason' : 'Cancellation reason' ?></label>
                    <input id="reason-<?= e($action['action']) ?>" name="reason" maxlength="250" placeholder="<?= $action['action'] === 'reject' ? 'Explain why the election is rejected' : 'Reason for withdrawal or cancellation' ?>" required>
                <?php endif; ?>
                <label for="password-<?= e($action['action']) ?>">Current password</label>
                <input id="password-<?= e($action['action']) ?>" name="current_password" type="password" autocomplete="current-password" required>
                <button class="button button-<?= $action['tone'] === 'danger' ? 'danger' : 'primary' ?> button-block" type="submit"><?= e($action['label']) ?></button>
            </form>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="card history-card">
    <div class="card-topline"><h2>Audit trail</h2><span class="muted"><?= e(count($logs)) ?> recorded event<?= count($logs) === 1 ? '' : 's' ?></span></div>
    <div class="table-wrap">
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
</section>
