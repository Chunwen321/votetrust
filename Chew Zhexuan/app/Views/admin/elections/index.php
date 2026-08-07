<?php

declare(strict_types=1);

$formatDate = static function (mixed $value, string $empty = 'Not scheduled'): string {
    if (!is_string($value) || $value === '') {
        return $empty;
    }

    try {
        return (new DateTimeImmutable($value))->format('d M Y, g:i A');
    } catch (Throwable) {
        return $empty;
    }
};
?>
<section class="page-heading split-heading">
    <div>
        <p class="eyebrow">Administration / elections</p>
        <h1>Election management</h1>
        <p>Open any election to update its details, lifecycle, voter list, and candidate list.</p>
    </div>
    <a class="button" href="<?= e(url('/admin/elections/create')) ?>">Create election</a>
</section>

<?php if ($elections === []): ?>
    <section class="card empty-state"><span class="empty-symbol" aria-hidden="true">+</span><h2>No elections yet</h2><p>Create an election to begin configuration.</p></section>
<?php else: ?>
    <section class="election-admin-grid">
        <?php foreach ($elections as $election): ?>
            <article class="card election-admin-card">
                <div class="card-topline election-card-topline">
                    <span class="badge status-<?= e(strtolower(str_replace('_', '-', (string) $election['status']))) ?>"><?= e(str_replace('_', ' ', (string) $election['status'])) ?></span>
                    <span class="election-id">Election #<?= e($election['id']) ?></span>
                </div>
                <h2><?= e($election['title']) ?></h2>
                <p class="election-card-description"><?= e($election['description'] ?: 'No election description has been provided.') ?></p>
                <div class="election-facts" aria-label="Election summary">
                    <div><span>Voters</span><strong><?= e($election['approved_voter_total']) ?> <small>approved</small></strong><small><?= e($election['voter_total']) ?> enrolled</small></div>
                    <div><span>Candidates</span><strong><?= e($election['approved_candidate_total']) ?> <small>approved</small></strong><small><?= e($election['candidate_total']) ?> registered</small></div>
                    <div><span>Voting starts</span><strong class="fact-date"><?= e($formatDate($election['voting_start'])) ?></strong></div>
                    <div><span>Last updated</span><strong class="fact-date"><?= e($formatDate($election['updated_at'] ?: $election['created_at'], 'Not available')) ?></strong></div>
                </div>
                <a class="button" href="<?= e(url('/admin/elections/' . $election['id'])) ?>">Open control centre</a>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
