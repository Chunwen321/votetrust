<?php

declare(strict_types=1);

use VoteTrust\UserAdmin\Core\Csrf;
use VoteTrust\UserAdmin\Core\Config;

$pageTitle = 'Sign in';
$maximumFailedAttempts = filter_var(
    Config::get('AUTH_MAX_FAILED_ATTEMPTS', 5),
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1, 'max_range' => 100]]
) ?: 5;
?>
<section class="auth-grid">
    <div class="auth-copy">
        <p class="eyebrow">Secure member access</p>
        <h1>Manage your voting access with confidence.</h1>
        <p>One protected account keeps your profile, voter eligibility, and election participation connected.</p>
        <div class="trust-points">
            <span>CSRF-protected access</span>
            <span>Per-election eligibility</span>
            <span>Secure session termination</span>
        </div>
    </div>

    <form class="card auth-card" method="post" action="<?= e(url('/login')) ?>" novalidate>
        <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
        <p class="eyebrow">Secure account portal</p>
        <h2>Welcome back</h2>
        <p class="muted">Sign in to review your profile or election eligibility.</p>

        <label for="username">Username</label>
        <input id="username" name="username" value="<?= e(old('username')) ?>" autocomplete="username" pattern="[A-Za-z0-9_.-]{3,50}" maxlength="50" required aria-describedby="username-error">
        <?php if (isset($errors['username'])): ?><small id="username-error" class="field-error"><?= e($errors['username']) ?></small><?php endif; ?>

        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required aria-describedby="password-error">
        <?php if (isset($errors['password'])): ?><small id="password-error" class="field-error"><?= e($errors['password']) ?></small><?php endif; ?>

        <button class="button button-primary button-block" type="submit">Sign in securely</button>
        <p class="demo-note">For your protection, <?= e($maximumFailedAttempts) ?> consecutive incorrect sign-in attempts disable the account.</p>
        <p class="auth-switch">New user? <a class="text-link" href="<?= e(url('/register')) ?>">Create an account.</a></p>
    </form>
</section>
