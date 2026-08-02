<?php $pageTitle = 'Error ' . $statusCode; ?>
<section class="card error-page">
    <p class="error-code"><?= e($statusCode) ?></p>
    <h1>We could not complete that request.</h1>
    <p><?= e($message) ?></p>
    <?php if ($details !== []): ?>
        <ul>
            <?php foreach ($details as $detail): ?><li><?= e($detail) ?></li><?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <a class="button button-primary" href="<?= e(url('/elections')) ?>">Return to elections</a>
</section>

