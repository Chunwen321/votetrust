<?php
/** @var array{id:int,name:string} $voter */
/** @var list<array<string,mixed>> $elections */
$pageTitle = 'Voting portal';
?>
<section class="page-heading split-heading">
    <div>
        <p class="eyebrow">Secure voting</p>
        <h1>Choose an open election.</h1>
        <p>Only elections authorised by the Election Module appear here. Each voter can submit one final choice.</p>
    </div>
</section>

<?php if ($elections === []): ?>
    <section class="card empty-state">
        <div class="empty-icon" aria-hidden="true">&#10003;</div>
        <h2>No elections are open</h2>
        <p class="muted">Return when an organiser opens a verified and approved election.</p>
    </section>
<?php else: ?>
    <section class="election-grid" aria-label="Open elections">
        <?php foreach ($elections as $election): ?>
            <?php
            $period = is_array($election['votingPeriod'] ?? null) ? $election['votingPeriod'] : [];
            $ends = isset($period['end']) ? new DateTimeImmutable((string) $period['end']) : null;
            ?>
            <article class="card election-card">
                <div class="card-topline">
                    <span class="status status-voting-open">Voting open</span>
                    <span class="muted">#<?= e((int) $election['id']) ?></span>
                </div>
                <h2><?= e((string) $election['title']) ?></h2>
                <p><?= e((string) $election['description']) ?></p>
                <dl class="compact-details">
                    <div><dt>Closes</dt><dd><?= $ends ? e($ends->format('d M Y, H:i')) : 'Scheduled by organiser' ?></dd></div>
                    <div><dt>Your status</dt><dd><?= !empty($election['hasVoted']) ? 'Vote recorded' : 'Ready to vote' ?></dd></div>
                </dl>
                <?php if (!empty($election['hasVoted'])): ?>
                    <span class="result-pill result-good">Completed</span>
                <?php else: ?>
                    <a class="text-link" href="<?= e($url('/voting/ballot?electionId=' . (int) $election['id'])) ?>">Open secure ballot <span aria-hidden="true">&rarr;</span></a>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
