<?php

declare(strict_types=1);

use VoteTrust\UserAdmin\Core\Auth;
use VoteTrust\UserAdmin\Core\Csrf;
use VoteTrust\UserAdmin\Core\Session;

$currentUser = $user ?? Auth::user();
$success = Session::consumeFlash('success');
$error = Session::consumeFlash('error');
$displayName = is_array($currentUser)
    ? (string) ($currentUser['full_name'] ?: $currentUser['username'])
    : '';
$role = is_array($currentUser) && Auth::isAdmin($currentUser)
    ? 'Administrator'
    : (is_array($currentUser) && Auth::isVoter($currentUser) ? 'Voter' : 'Member');
$currentPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$isCurrent = static fn (string $path): bool => $currentPath === $path;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title><?= e($pageTitle ?? 'Member Services') ?> &middot; VoteTrust</title>
    <link rel="stylesheet" href="<?= e(url('/assets/app.css')) ?>">
</head>
<body>
<header class="site-header">
    <a class="brand" href="<?= e(url('/')) ?>" aria-label="VoteTrust home">
        <span class="brand-mark" aria-hidden="true">VT</span>
        <span><strong>VoteTrust</strong><small>Member Services</small></span>
    </a>
    <?php if (is_array($currentUser)): ?>
        <nav class="primary-nav" aria-label="Main navigation">
            <?php if (Auth::isAdmin($currentUser)): ?>
                <a class="<?= $isCurrent('/admin') ? 'is-active' : '' ?>" href="<?= e(url('/admin')) ?>">Dashboard</a>
                <a class="<?= str_starts_with($currentPath, '/admin/elections') ? 'is-active' : '' ?>" href="<?= e(url('/admin/elections')) ?>">Elections</a>
            <?php else: ?>
                <a class="<?= $isCurrent('/dashboard') ? 'is-active' : '' ?>" href="<?= e(url('/dashboard')) ?>">Dashboard</a>
                <a class="<?= $isCurrent('/elections') || $isCurrent('/voter') ? 'is-active' : '' ?>" href="<?= e(url('/elections')) ?>">Elections</a>
            <?php endif; ?>
            <a class="<?= $isCurrent('/profile') ? 'is-active' : '' ?>" href="<?= e(url('/profile')) ?>">Profile</a>
        </nav>
        <nav class="nav-actions" aria-label="Account navigation">
            <span class="user-chip"><?= e($displayName) ?> &middot; <?= e($role) ?></span>
            <form method="post" action="<?= e(url('/logout')) ?>">
                <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
                <button class="button button-quiet" type="submit">Sign out</button>
            </form>
        </nav>
    <?php else: ?>
        <nav class="nav-actions" aria-label="Account navigation">
            <a class="text-link" href="<?= e(url('/login')) ?>">Sign in</a>
            <a class="button button-quiet" href="<?= e(url('/register')) ?>">Create account</a>
        </nav>
    <?php endif; ?>
</header>
<main class="page-shell">
    <?php if (is_string($success) && $success !== ''): ?><div class="alert alert-success" role="status"><?= e($success) ?></div><?php endif; ?>
    <?php if (is_string($error) && $error !== ''): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?= $content ?>
</main>
<footer>VoteTrust · Secure account, voter eligibility, and administrator review service</footer>
</body>
</html>
