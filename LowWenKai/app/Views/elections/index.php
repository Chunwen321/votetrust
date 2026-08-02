<?php $pageTitle = 'Your elections'; ?>
<section class="page-heading split-heading">
    <div>
        <p class="eyebrow">Organiser workspace</p>
        <h1>Your elections</h1>
        <p>Configure schedules, verify the hosting fee, and move each election through its controlled lifecycle.</p>
    </div>
    <a class="button button-primary" href="<?= e(url('/elections/create')) ?>">Create election</a>
</section>

<?php if ($elections === []): ?>
    <section class="card empty-state">
        <div class="empty-icon" aria-hidden="true">＋</div>
        <h2>Create your first election</h2>
        <p>Start with a draft, then use the guided lifecycle to publish it safely.</p>
        <a class="button button-primary" href="<?= e(url('/elections/create')) ?>">New election</a>
    </section>
<?php else: ?>
    <section class="election-grid" aria-label="Election list">
        <?php foreach ($elections as $election): ?>
            <?php $statusClass = strtolower(str_replace('_', '-', $election->status())); ?>
            <article class="card election-card">
                <div class="card-topline">
                    <span class="status status-<?= e($statusClass) ?>"><?= e(ucwords(strtolower(str_replace('_', ' ', $election->status())))) ?></span>
                    <span class="muted">#<?= e($election->id()) ?></span>
                </div>
                <h2><?= e($election->get('title')) ?></h2>
                <p><?= e($election->get('description')) ?></p>
                <dl class="compact-details">
                    <div><dt>Nominations</dt><dd><?= e($election->date('nomination_start')->format('d M')) ?> – <?= e($election->date('nomination_end')->format('d M Y')) ?></dd></div>
                    <div><dt>Voting</dt><dd><?= e($election->date('voting_start')->format('d M')) ?> – <?= e($election->date('voting_end')->format('d M Y')) ?></dd></div>
                </dl>
                <a class="text-link" href="<?= e(url('/elections/' . $election->id())) ?>">Open management console <span aria-hidden="true">→</span></a>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

