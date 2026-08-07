<?php

declare(strict_types=1);

use VoteTrust\UserAdmin\Core\Csrf;

$statusClass = strtolower(str_replace('_', '-', (string) $election['status']));
?>
<a class="back-link" href="<?= e(url('/admin/elections')) ?>">&larr; All elections</a>
<section class="page-heading split-heading election-heading">
    <div>
        <p class="eyebrow">Election #<?= e($election['id']) ?> / control centre</p>
        <h1><?= e($election['title']) ?></h1>
        <p><?= e($election['description'] ?: 'No description has been provided for this election.') ?></p>
    </div>
    <div class="heading-actions">
        <span class="badge badge-large status-<?= e($statusClass) ?>"><?= e(str_replace('_', ' ', (string) $election['status'])) ?></span>
        <a class="button button-quiet" href="<?= e(url('/admin/elections/' . $election['id'] . '/edit')) ?>">Edit election</a>
    </div>
</section>

<section class="card lifecycle-card">
    <div class="progress-heading"><div><p class="eyebrow">Lifecycle control</p><h2>Administrator actions</h2></div><p class="muted">Actions are recorded in the election audit history.</p></div>
    <div class="lifecycle-actions">
        <form method="post" action="<?= e(url('/admin/elections/' . $election['id'] . '/action')) ?>"><input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>"><button name="action" value="approve" type="submit">Approve nominations</button></form>
        <form method="post" action="<?= e(url('/admin/elections/' . $election['id'] . '/action')) ?>"><input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>"><button name="action" value="open_voting" type="submit" class="button-secondary">Open voting</button></form>
        <form method="post" action="<?= e(url('/admin/elections/' . $election['id'] . '/action')) ?>"><input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>"><button name="action" value="close" type="submit" class="button-warning">Close election</button></form>
        <form method="post" action="<?= e(url('/admin/elections/' . $election['id'] . '/action')) ?>"><input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>"><button name="action" value="cancel" type="submit" class="button-danger">Cancel election</button></form>
        <form method="post" action="<?= e(url('/admin/elections/' . $election['id'] . '/action')) ?>"><input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>"><button name="action" value="restore_draft" type="submit" class="button-quiet">Return to draft</button></form>
    </div>
</section>

<section class="admin-overview-grid">
    <section class="card overview-card"><p class="eyebrow">Participation</p><h2><?= e($election['approved_voter_total']) ?> voters</h2><p><?= e($election['voter_total']) ?> voter records in total.</p><a class="text-link" href="#voters">Manage voters</a></section>
    <section class="card overview-card"><p class="eyebrow">Ballot</p><h2><?= e($election['approved_candidate_total']) ?> candidates</h2><p><?= e($election['candidate_total']) ?> candidate entries in total.</p><a class="text-link" href="#candidates">Manage candidates</a></section>
    <section class="card overview-card"><p class="eyebrow">Voting window</p><h2><?= e($election['voting_start'] ?: 'Not scheduled') ?></h2><p>Ends: <?= e($election['voting_end'] ?: 'Not scheduled') ?></p><a class="text-link" href="<?= e(url('/admin/elections/' . $election['id'] . '/edit')) ?>">Edit schedule</a></section>
</section>

<section id="voters" class="card management-section">
    <div class="section-title"><div><p class="eyebrow">Voter access</p><h2>Add and manage voters</h2></div><p><?= count($voters) ?> record<?= count($voters) === 1 ? '' : 's' ?></p></div>
    <form method="post" action="<?= e(url('/admin/elections/' . $election['id'] . '/voters')) ?>" class="management-add-form">
        <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
        <label>User<select name="user_id" required><option value="">Choose a registered user</option><?php foreach ($eligibleUsers as $person): ?><option value="<?= e($person['id']) ?>"><?= e(($person['full_name'] ?: $person['username']) . ' (@' . $person['username'] . ')') ?></option><?php endforeach; ?></select></label>
        <label>Status<select name="status"><option value="APPROVED">Approved</option><option value="PENDING">Pending</option><option value="SUSPENDED">Suspended</option><option value="REJECTED">Rejected</option></select></label>
        <button type="submit">Add voter</button>
    </form>
    <?php if ($voters === []): ?><p class="empty-state compact-empty">No voters have been added to this election.</p><?php else: ?>
        <div class="table-scroll"><table><thead><tr><th>User</th><th>Account</th><th>Status</th><th>Added</th><th>Control</th></tr></thead><tbody>
        <?php foreach ($voters as $voter): ?><tr>
            <td><strong><?= e($voter['full_name'] ?: $voter['username']) ?></strong><br><small>@<?= e($voter['username']) ?> &middot; <?= e($voter['email'] ?: 'No email') ?></small></td>
            <td><span class="badge status-<?= e(strtolower((string) $voter['account_status'])) ?>"><?= e($voter['account_status']) ?></span></td>
            <td><span class="badge status-<?= e(strtolower((string) $voter['status'])) ?>"><?= e($voter['status']) ?></span></td>
            <td><?= e($voter['created_at']) ?></td>
            <td><form method="post" action="<?= e(url('/admin/elections/' . $election['id'] . '/voters/' . $voter['user_id'])) ?>" class="row-action-form"><input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>"><select name="status"><option value="APPROVED">Approve</option><option value="PENDING">Pending</option><option value="SUSPENDED">Suspend</option><option value="REJECTED">Reject</option></select><button type="submit" class="button-small">Save</button></form></td>
        </tr><?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>

<section id="candidates" class="card management-section">
    <div class="section-title"><div><p class="eyebrow">Ballot management</p><h2>Add and manage candidates</h2></div><p><?= count($candidates) ?> record<?= count($candidates) === 1 ? '' : 's' ?></p></div>
    <form method="post" action="<?= e(url('/admin/elections/' . $election['id'] . '/candidates')) ?>" class="management-add-form">
        <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
        <label>User<select name="user_id" required><option value="">Choose a registered user</option><?php foreach ($eligibleUsers as $person): ?><option value="<?= e($person['id']) ?>"><?= e(($person['full_name'] ?: $person['username']) . ' (@' . $person['username'] . ')') ?></option><?php endforeach; ?></select></label>
        <label>Status<select name="status"><option value="Approved">Approved</option><option value="Pending">Pending</option><option value="Rejected">Rejected</option></select></label>
        <button type="submit">Add candidate</button>
    </form>
    <?php if ($candidates === []): ?><p class="empty-state compact-empty">No candidates have been added to this election.</p><?php else: ?>
        <div class="table-scroll"><table><thead><tr><th>Candidate</th><th>Account</th><th>Status</th><th>Registered</th><th>Control</th></tr></thead><tbody>
        <?php foreach ($candidates as $candidate): ?><tr>
            <td><strong><?= e($candidate['full_name'] ?: $candidate['username']) ?></strong><br><small>@<?= e($candidate['username']) ?></small></td>
            <td><span class="badge status-<?= e(strtolower((string) $candidate['account_status'])) ?>"><?= e($candidate['account_status']) ?></span></td>
            <td><span class="badge status-<?= e(strtolower((string) $candidate['status'])) ?>"><?= e($candidate['status']) ?></span></td>
            <td><?= e($candidate['registered_at']) ?></td>
            <td><form method="post" action="<?= e(url('/admin/elections/' . $election['id'] . '/candidates/' . $candidate['candidate_id'])) ?>" class="row-action-form"><input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>"><select name="status"><option value="Approved">Approve</option><option value="Pending">Pending</option><option value="Rejected">Reject</option></select><button type="submit" class="button-small">Save</button></form></td>
        </tr><?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>
