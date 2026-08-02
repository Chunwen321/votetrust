<?php

use VoteTrust\Election\Core\Csrf;

$pageTitle = 'Organiser sign in';
?>
<section class="auth-grid">
    <div class="auth-copy">
        <p class="eyebrow">Secure election operations</p>
        <h1>Manage every election stage with confidence.</h1>
        <p>VoteTrust keeps hosting payment, nominations, voting, and closure inside one auditable lifecycle.</p>
        <div class="trust-points">
            <span>Payment-gated publishing</span>
            <span>State-pattern lifecycle</span>
            <span>Critical-action reauthentication</span>
        </div>
    </div>

    <form class="card auth-card" method="post" action="<?= e(url('/login')) ?>" novalidate>
        <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
        <p class="eyebrow">Secure account portal</p>
        <h2>Welcome back</h2>
        <p class="muted">Use the seeded local account to review the module.</p>

        <label for="username">Username</label>
        <input id="username" name="username" autocomplete="username" value="<?= e(old('username', 'LowWenKaiOrganizer')) ?>" required aria-describedby="username-error">
        <?php if (isset($errors['username'])): ?><small id="username-error" class="field-error"><?= e($errors['username']) ?></small><?php endif; ?>

        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required aria-describedby="password-error">
        <?php if (isset($errors['password'])): ?><small id="password-error" class="field-error"><?= e($errors['password']) ?></small><?php endif; ?>

        <button class="button button-primary button-block" type="submit">Sign in securely</button>
        <p class="demo-note"><strong>Organiser:</strong> LowWenKaiOrganizer / VoteTrust!2026<br><strong>Administrator:</strong> SystemAdmin / VoteTrustAdmin!2026</p>
    </form>
</section>
