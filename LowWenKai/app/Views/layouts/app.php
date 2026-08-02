<?php

use VoteTrust\Election\Core\Auth;
use VoteTrust\Election\Core\Csrf;
use VoteTrust\Election\Core\Session;

$authenticatedUser = Auth::user();
$flashSuccess = Session::consumeFlash('success');
$flashError = Session::consumeFlash('error');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title><?= e($pageTitle ?? 'Election Management') ?> · VoteTrust</title>
    <link rel="stylesheet" href="<?= e(url('/assets/app.css')) ?>">
</head>
<body>
<header class="site-header">
    <a class="brand" href="<?= e(url('/elections')) ?>" aria-label="VoteTrust home">
        <span class="brand-mark" aria-hidden="true">VT</span>
        <span><strong>VoteTrust</strong><small>Election Management</small></span>
    </a>
    <?php if ($authenticatedUser !== null): ?>
        <nav class="nav-actions" aria-label="Account navigation">
            <?php if ($authenticatedUser->isAdmin()): ?>
                <a class="text-link" href="<?= e(url('/admin/elections')) ?>">Approval workspace</a>
            <?php elseif ($authenticatedUser->isOrganizer()): ?>
                <a class="text-link" href="<?= e(url('/elections')) ?>">Organiser workspace</a>
            <?php endif; ?>
            <span class="user-chip"><?= e($authenticatedUser->name()) ?> · <?= e(ucfirst($authenticatedUser->role())) ?></span>
            <form method="post" action="<?= e(url('/logout')) ?>">
                <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
                <button class="button button-quiet" type="submit">Sign out</button>
            </form>
        </nav>
    <?php endif; ?>
</header>

<main class="page-shell">
    <?php if (is_string($flashSuccess) && $flashSuccess !== ''): ?>
        <div class="alert alert-success" role="status"><?= e($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if (is_string($flashError) && $flashError !== ''): ?>
        <div class="alert alert-error" role="alert"><?= e($flashError) ?></div>
    <?php endif; ?>
    <?= $content ?>
</main>

<footer class="site-footer">
    <span>SDG 16 · Peace, Justice and Strong Institutions</span>
    <span>Low Wen Kai · Election Module</span>
</footer>
</body>
</html>
