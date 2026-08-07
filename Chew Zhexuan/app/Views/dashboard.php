<?php

declare(strict_types=1);

use VoteTrust\UserAdmin\Core\Auth;

$name = (string) ($user['full_name'] ?: $user['username']);
$accountStatus = (string) ($user['account_status'] ?? 'ACTIVE');
$latestEnrolments = array_slice($enrolments, 0, 3);
?>
<section class="dashboard-hero">
    <div>
        <p class="eyebrow">Account dashboard</p>
        <h1>Welcome back, <?= e($name) ?>.</h1>
        <p>Manage your account, check election access, and keep track of your voter enrolments in one place.</p>
    </div>
    <div class="account-summary">
        <span class="account-summary-label">Account status</span>
        <strong><?= e($accountStatus) ?></strong>
        <span class="badge status-<?= e(strtolower($accountStatus)) ?>"><?= e($accountStatus) ?></span>
    </div>
</section>

<section class="dashboard-grid" aria-label="Account overview">
    <a class="dashboard-card dashboard-card-primary" href="<?= e(url('/elections')) ?>">
        <span class="dashboard-icon" aria-hidden="true">✓</span>
        <span class="dashboard-card-label">Approved elections</span>
        <strong><?= e($counts['APPROVED']) ?></strong>
        <span>View your election access</span>
    </a>
    <a class="dashboard-card" href="<?= e(url('/elections')) ?>">
        <span class="dashboard-icon" aria-hidden="true">◷</span>
        <span class="dashboard-card-label">Awaiting review</span>
        <strong><?= e($counts['PENDING']) ?></strong>
        <span>Check enrolment status</span>
    </a>
    <a class="dashboard-card" href="<?= e(url('/profile')) ?>">
        <span class="dashboard-icon" aria-hidden="true">◉</span>
        <span class="dashboard-card-label">Profile</span>
        <strong>Account</strong>
        <span>Update your details and password</span>
    </a>
    <?php if (Auth::isAdmin($user)): ?>
        <a class="dashboard-card dashboard-card-admin" href="<?= e(url('/admin')) ?>">
            <span class="dashboard-icon" aria-hidden="true">⌁</span>
            <span class="dashboard-card-label">Administration</span>
            <strong>Review</strong>
            <span>Manage voter enrolments</span>
        </a>
    <?php endif; ?>
</section>

<section class="two-column dashboard-sections">
    <section class="card dashboard-panel">
        <div class="section-title section-title-plain">
            <div><p class="eyebrow">My elections</p><h2>Recent enrolments</h2></div>
            <a class="text-link" href="<?= e(url('/elections')) ?>">View all</a>
        </div>
        <?php if ($latestEnrolments === []): ?>
            <div class="empty-state compact-empty">
                <span class="empty-symbol" aria-hidden="true">+</span>
                <h3>No election enrolments yet</h3>
                <p>An organiser adds you to an election after matching your registered name and IC number.</p>
            </div>
        <?php else: ?>
            <div class="activity-list">
                <?php foreach ($latestEnrolments as $enrolment): ?>
                    <div class="activity-item">
                        <div>
                            <strong><?= e($enrolment['election_title']) ?></strong>
                            <span>Election #<?= e($enrolment['election_id']) ?> &middot; <?= e($enrolment['election_status']) ?></span>
                        </div>
                        <span class="badge status-<?= e(strtolower((string) $enrolment['eligibility_status'])) ?>"><?= e($enrolment['eligibility_status']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="card dashboard-panel quick-links">
        <p class="eyebrow">Quick links</p>
        <h2>Need to do something?</h2>
        <a href="<?= e(url('/profile')) ?>"><span>Profile and security</span><span aria-hidden="true">→</span></a>
        <a href="<?= e(url('/elections')) ?>"><span>My election enrolments</span><span aria-hidden="true">→</span></a>
        <?php if (Auth::isAdmin($user)): ?>
            <a href="<?= e(url('/admin')) ?>"><span>Open admin dashboard</span><span aria-hidden="true">→</span></a>
        <?php endif; ?>
    </section>
</section>
