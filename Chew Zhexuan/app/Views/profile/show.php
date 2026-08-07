<?php

declare(strict_types=1);

use VoteTrust\UserAdmin\Core\Auth;
use VoteTrust\UserAdmin\Core\Csrf;
?>
<section class="page-heading">
    <p class="eyebrow">User module</p>
    <h1>Your profile</h1>
    <p>Keep your details current and protect your account with a strong password.</p>
</section>
<div class="profile-layout">
    <section class="card profile-summary-card">
        <h2>Account details</h2>
        <dl class="key-values">
            <div><dt>Username</dt><dd><?= e($user['username']) ?></dd></div>
            <div><dt>Account status</dt><dd><span class="badge status-<?= strtolower((string) ($user['account_status'] ?? 'active')) ?>"><?= e($user['account_status'] ?? 'ACTIVE') ?></span></dd></div>
            <div><dt>Roles</dt><dd><?= Auth::isAdmin($user) ? 'System administrator' : (Auth::isVoter($user) ? 'Voter' : 'User') ?></dd></div>
        </dl>
    </section>
    <section class="card profile-form-card">
        <h2>Contact information</h2>
        <p class="muted profile-card-intro">These details are used to identify and contact you.</p>
        <form class="profile-form" method="post" action="<?= e(url('/profile')) ?>" novalidate>
            <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
            <label>Full name
                <input name="full_name" value="<?= e(old('full_name', $user['full_name'] ?? '')) ?>" autocomplete="name" required>
            </label>
            <?php if (isset($errors['full_name'])): ?><p class="field-error"><?= e($errors['full_name']) ?></p><?php endif; ?>
            <label>Email address
                <input name="email" type="email" value="<?= e(old('email', $user['email'] ?? '')) ?>" autocomplete="email" required>
            </label>
            <?php if (isset($errors['email'])): ?><p class="field-error"><?= e($errors['email']) ?></p><?php endif; ?>
            <button type="submit">Save profile</button>
        </form>
    </section>
</div>
<section class="card password-card">
    <h2>Change password</h2>
    <p class="muted password-intro">Use a unique password that you do not use for any other account.</p>
    <form class="password-form" method="post" action="<?= e(url('/profile/password')) ?>" novalidate>
        <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
        <label>Current password<input name="current_password" type="password" autocomplete="current-password" required></label>
        <?php if (isset($errors['current_password'])): ?><p class="field-error"><?= e($errors['current_password']) ?></p><?php endif; ?>
        <label>New password<input name="new_password" type="password" autocomplete="new-password" required></label>
        <?php if (isset($errors['password'])): ?><p class="field-error"><?= e($errors['password']) ?></p><?php endif; ?>
        <label>Confirm new password<input name="password_confirmation" type="password" autocomplete="new-password" required></label>
        <?php if (isset($errors['password_confirmation'])): ?><p class="field-error"><?= e($errors['password_confirmation']) ?></p><?php endif; ?>
        <button type="submit">Update password</button>
    </form>
</section>
