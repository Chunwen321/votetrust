<?php

use VoteTrust\Election\Models\Election;

$pageTitle = 'Administrator approvals';
$pending = array_values(array_filter(
    $elections,
    static fn (Election $election): bool => $election->status() === Election::PENDING_ADMIN_APPROVAL
));
?>
<section class="page-heading split-heading">
    <div>
        <p class="eyebrow">System administrator</p>
        <h1>Election approval workspace</h1>
        <p>Review confirmed hosting payments and election configuration. An organiser cannot approve their own submission.</p>
    </div>
    <span class="status status-pending-admin-approval"><?= e(count($pending)) ?> awaiting review</span>
</section>

<?php if ($elections === []): ?>
    <section class="card empty-state">
        <h2>No elections have been submitted</h2>
        <p>Paid elections will appear here when they reach the administrator approval state.</p>
    </section>
<?php else: ?>
    <section class="election-grid" aria-label="Election approval list">
        <?php foreach ($elections as $election): ?>
            <?php $organizer = $election->organizer(); ?>
            <article class="card election-card <?= $election->status() === Election::PENDING_ADMIN_APPROVAL ? 'approval-priority' : '' ?>">
                <div class="card-topline">
                    <span class="status status-<?= e(strtolower(str_replace('_', '-', $election->status()))) ?>"><?= e(ucwords(strtolower(str_replace('_', ' ', $election->status())))) ?></span>
                    <span class="muted">#<?= e($election->id()) ?></span>
                </div>
                <h2><?= e($election->get('title')) ?></h2>
                <p><?= e($election->get('description')) ?></p>
                <dl class="compact-details">
                    <div><dt>Organiser</dt><dd><?= e($organizer?->username() ?? 'Unknown') ?></dd></div>
                    <div><dt>Payment</dt><dd><?= $election->paymentVerified() ? 'Verified' : 'Not verified' ?></dd></div>
                </dl>
                <a class="text-link" href="<?= e(url('/admin/elections/' . $election->id())) ?>">Review election <span aria-hidden="true">→</span></a>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
