<?php
/** @var array{id:int,title:string,description:string,eligibilityRules:string,status:string,candidacies:array} $election */
/** @var array{id:int,name:string} $voter */
/** @var string $requestId */
/** @var string $timestamp */
/** @var string $csrf */
/** @var bool $hasVoted */
$pageTitle = (string) $election['title'];
?>
<a class="back-link" href="<?= e($url('/voting')) ?>">&larr; All open elections</a>

<section class="page-heading ballot-heading">
    <div class="heading-meta">
        <span class="status status-voting-open">Voting open</span>
        <span>Election #<?= e($election['id']) ?></span>
    </div>
    <h1><?= e($election['title']) ?></h1>
    <p><?= e($election['description']) ?></p>
</section>

<?php if ($hasVoted): ?>
    <section class="card confirmation-card">
        <span class="confirmation-mark" aria-hidden="true">&#10003;</span>
        <h2>Your vote is already recorded</h2>
        <p>VoteTrust never shows your selected candidate after submission.</p>
        <a class="button button-primary" href="<?= e($url('/voting')) ?>">Return to voting portal</a>
    </section>
<?php elseif ($election['candidacies'] === []): ?>
    <section class="card empty-state">
        <h2>No approved candidates</h2>
        <p class="muted">The Election Module has not supplied any ballot-eligible candidates.</p>
    </section>
<?php else: ?>
    <div class="ballot-layout">
        <form class="card ballot-card" method="post" action="<?= e($url('/voting/cast')) ?>">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="electionId" value="<?= e($election['id']) ?>">
            <input type="hidden" name="requestID" value="<?= e($requestId) ?>">
            <input type="hidden" name="timestamp" value="<?= e($timestamp) ?>">

            <fieldset>
                <legend>Select one candidate</legend>
                <p class="muted">Your selection is final after you press cast vote.</p>
                <div class="candidate-options">
                    <?php foreach ($election['candidacies'] as $candidate): ?>
                        <label class="candidate-option">
                            <input type="radio" name="candidateId" value="<?= e((int) $candidate['candidateId']) ?>" required>
                            <span class="candidate-avatar" aria-hidden="true"><?= e(strtoupper(substr((string) $candidate['name'], 0, 1))) ?></span>
                            <span><strong><?= e((string) $candidate['name']) ?></strong><small>Approved candidate</small></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <button class="button button-primary button-block" type="submit">Cast vote securely</button>
        </form>

        <aside class="card overview-card">
            <h2>Before you vote</h2>
            <dl class="overview-list">
                <div><dt>Voter</dt><dd><?= e($voter['name']) ?></dd></div>
                <div><dt>Eligibility</dt><dd><?= e($election['eligibilityRules']) ?></dd></div>
                <div><dt>Privacy</dt><dd>Your selected candidate is never returned by the status API.</dd></div>
                <div><dt>Finality</dt><dd>The database permits one vote per voter and election.</dd></div>
            </dl>
        </aside>
    </div>
<?php endif; ?>
