<?php
/** @var string $message */
$pageTitle = 'Unable to vote';
?>
<section class="card error-page">
    <p class="error-code">!</p>
    <h1>Unable to process the vote</h1>
    <p class="muted"><?= e($message) ?></p>
    <a class="button button-primary" href="<?= e($url('/voting')) ?>">Return to voting portal</a>
</section>
