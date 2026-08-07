<?php
/** @var \VoteTrust\Voting\Model\Vote $vote */
/** @var array{id:int,name:string} $voter */
$pageTitle = 'Vote recorded';
?>
<section class="card confirmation-card">
    <span class="confirmation-mark" aria-hidden="true">&#10003;</span>
    <p class="eyebrow">Submission complete</p>
    <h1>Vote recorded successfully.</h1>
    <p>Your candidate selection is not displayed or returned by the status API, protecting ballot privacy.</p>
    <dl class="receipt-grid">
        <div><dt>Receipt ID</dt><dd><code><?= e($vote->id) ?></code></dd></div>
        <div><dt>Recorded at</dt><dd><?= e($vote->castAt->format('d M Y, H:i:s')) ?></dd></div>
    </dl>
    <a class="button button-primary" href="<?= e($url('/voting')) ?>">Return to voting portal</a>
</section>
