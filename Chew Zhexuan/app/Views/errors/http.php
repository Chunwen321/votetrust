<?php

declare(strict_types=1);
?>
<section class="auth-card card error-page">
    <p class="eyebrow">Error <?= e($statusCode) ?></p>
    <h1>We could not complete that request</h1>
    <p><?= e($message) ?></p>
    <?php if ($details !== []): ?><ul><?php foreach ($details as $detail): ?><li><?= e($detail) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <a class="button" href="<?= e(url('/')) ?>">Return to VoteTrust</a>
</section>
