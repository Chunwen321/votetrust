<?php

declare(strict_types=1);
?>
<section class="dashboard-hero admin-hero">
    <div>
        <p class="eyebrow">System administration</p>
        <h1>Election control centre</h1>
        <p>Create and govern elections, manage voter access and candidate entries, and control the election lifecycle from one secure workspace.</p>
    </div>
    <div class="hero-actions">
        <a class="button button-inverse" href="<?= e(url('/admin/elections/create')) ?>">Create election</a>
        <a class="button button-ghost-light" href="<?= e(url('/admin/elections')) ?>">Manage elections</a>
    </div>
</section>

<section class="metrics admin-metrics" aria-label="System summary">
    <a class="metric-card" href="<?= e(url('/admin/elections')) ?>"><span>Total elections</span><strong><?= e($counts['elections']) ?></strong><small>All election records</small></a>
    <a class="metric-card status-approved" href="<?= e(url('/admin/elections')) ?>"><span>Active elections</span><strong><?= e($counts['active']) ?></strong><small>Approved, nomination, or voting</small></a>
    <a class="metric-card" href="<?= e(url('/admin/elections')) ?>"><span>Approved voters</span><strong><?= e($counts['voters']) ?></strong><small>Across all elections</small></a>
    <a class="metric-card status-pending" href="<?= e(url('/admin/elections')) ?>"><span>Pending candidates</span><strong><?= e($counts['pending_candidates']) ?></strong><small>Require an admin decision</small></a>
</section>

<section class="card table-card">
    <div class="section-title">
        <div><p class="eyebrow">Election oversight</p><h2>Recent elections</h2></div>
        <a class="text-link" href="<?= e(url('/admin/elections')) ?>">View all elections</a>
    </div>
    <?php if ($recentElections === []): ?>
        <div class="empty-state">
            <span class="empty-symbol" aria-hidden="true">+</span>
            <h3>No elections created</h3>
            <p>Create the first election, then add and approve its voters and candidates.</p>
            <a class="button" href="<?= e(url('/admin/elections/create')) ?>">Create election</a>
        </div>
    <?php else: ?>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Election</th><th>Lifecycle</th><th>Participants</th><th>Updated</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($recentElections as $election): ?>
                    <tr>
                        <td><strong><?= e($election['title']) ?></strong><br><small>#<?= e($election['id']) ?> &middot; <?= e($election['organizer_username'] ?: 'System managed') ?></small></td>
                        <td><span class="badge status-<?= e(strtolower(str_replace('_', '-', (string) $election['status']))) ?>"><?= e(str_replace('_', ' ', (string) $election['status'])) ?></span></td>
                        <td><strong><?= e($election['approved_voter_total']) ?></strong> approved voters<br><small><?= e($election['approved_candidate_total']) ?> approved candidates</small></td>
                        <td><?= e($election['updated_at'] ?: $election['created_at'] ?: '—') ?></td>
                        <td><a class="button button-small" href="<?= e(url('/admin/elections/' . $election['id'])) ?>">Manage</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
