<?php

declare(strict_types=1);

use VoteTrust\UserAdmin\Core\Csrf;

$pageTitle = 'Create account';
?>
<section class="auth-grid">
    <div class="auth-copy">
        <p class="eyebrow">User registration</p>
        <h1>Start with a trusted VoteTrust account.</h1>
        <p>Registration creates a user account only. An organiser enrols registered users as voters for a specific election by matching their name and IC number.</p>
        <div class="trust-points">
            <span>Strong-password policy</span>
            <span>IC list matching</span>
            <span>Election-specific access</span>
        </div>
    </div>

    <form class="card auth-card" method="post" action="<?= e(url('/register')) ?>" novalidate>
        <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
        <p class="eyebrow">Secure account portal</p>
        <h2>Create your account</h2>
        <p class="muted">Use your real name and IC number so an organiser can match you to an election voter list.</p>

        <label for="username">Username</label>
        <input id="username" name="username" value="<?= e(old('username')) ?>" autocomplete="username" pattern="[A-Za-z0-9_.-]{3,50}" maxlength="50" required aria-describedby="username-error">
        <?php if (isset($errors['username'])): ?><small id="username-error" class="field-error"><?= e($errors['username']) ?></small><?php endif; ?>

        <label for="full-name">Full name</label>
        <input id="full-name" name="full_name" value="<?= e(old('full_name')) ?>" autocomplete="name" maxlength="100" required aria-describedby="full-name-error">
        <?php if (isset($errors['full_name'])): ?><small id="full-name-error" class="field-error"><?= e($errors['full_name']) ?></small><?php endif; ?>

        <label for="email">Email address</label>
        <input id="email" name="email" type="email" value="<?= e(old('email')) ?>" autocomplete="email" maxlength="190" required aria-describedby="email-error">
        <?php if (isset($errors['email'])): ?><small id="email-error" class="field-error"><?= e($errors['email']) ?></small><?php endif; ?>

        <label for="ic-number">IC number</label>
        <input id="ic-number" name="ic_number" value="<?= e(old('ic_number')) ?>" inputmode="numeric" pattern="[0-9-]{12,14}" maxlength="14" required aria-describedby="ic-number-help ic-number-error">
        <small id="ic-number-help" class="field-help">Use your 12-digit Malaysian IC number. It is used to match organiser voter lists.</small>
        <?php if (isset($errors['ic_number'])): ?><small id="ic-number-error" class="field-error"><?= e($errors['ic_number']) ?></small><?php endif; ?>

        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="new-password" required aria-describedby="password-help password-error">
        <small id="password-help" class="field-help">At least 12 characters with uppercase, lowercase, number, and symbol.</small>
        <?php if (isset($errors['password'])): ?><small id="password-error" class="field-error"><?= e($errors['password']) ?></small><?php endif; ?>

        <label for="password-confirmation">Confirm password</label>
        <input id="password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" required aria-describedby="password-confirmation-error">
        <?php if (isset($errors['password_confirmation'])): ?><small id="password-confirmation-error" class="field-error"><?= e($errors['password_confirmation']) ?></small><?php endif; ?>

        <button class="button button-primary button-block" type="submit">Create user account</button>
        <p class="auth-switch">Already registered? <a class="text-link" href="<?= e(url('/login')) ?>">Sign in.</a></p>
    </form>
</section>
