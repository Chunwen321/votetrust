<?php

declare(strict_types=1);
?>
<section class="page-heading split-heading">
    <div>
        <p class="eyebrow">Elections</p>
        <h1>My election enrolments</h1>
        <p>See the elections where you are enrolled. Only an election organiser can add a registered user to a voter list.</p>
    </div>
</section>
<section class="card table-card">
    <div class="section-title">
        <h2>Your election access</h2>
        <p><?= count($applications) ?> enrolment<?= count($applications) === 1 ? '' : 's' ?></p>
    </div>
    <?php if ($applications === []): ?>
        <div class="empty-state">
            <p>You have not been enrolled for an election yet. Contact that election’s organiser after creating your user account.</p>
        </div>
    <?php else: ?>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Election</th><th>Eligibility</th><th>Latest update</th></tr></thead>
                <tbody>
                <?php foreach ($applications as $application): ?>
                    <tr>
                        <td><strong><?= e($application['election_title']) ?></strong><br><small>Election #<?= e($application['election_id']) ?> · <?= e($application['election_status']) ?></small></td>
                        <td><span class="badge status-<?= e(strtolower((string) $application['eligibility_status'])) ?>"><?= e($application['eligibility_status']) ?></span></td>
                        <td><?= e($application['updated_at'] ?? $application['created_at'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<section class="card narrow-card">
    <h2>How voting access is granted</h2>
    <p>The organiser uploads a CSV list of registered users’ names and IC numbers. A successful match creates your voter record and an <strong>APPROVED</strong> enrolment for that election. The Voting Module also checks that voting is open.</p>
</section>
