<?php

use VoteTrust\Election\Core\Csrf;

$value = static function (string $key, mixed $fallback = '') use ($election, $defaults): mixed {
    if (isset($_SESSION['_old_input']) && array_key_exists($key, $_SESSION['_old_input'])) {
        return old($key);
    }
    if ($election !== null) {
        $raw = $election->get($key, $fallback);
        if (in_array($key, ['nomination_start', 'nomination_end', 'voting_start', 'voting_end'], true)) {
            return $election->date($key)->format('Y-m-d\\TH:i');
        }

        return $raw;
    }

    return $defaults[$key] ?? $fallback;
};
?>
<form class="form-stack" method="post" action="<?= e($formAction) ?>" novalidate>
    <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
    <?php if ($election !== null): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>

    <section class="card form-section">
        <div class="section-intro">
            <span class="step-number">1</span>
            <div><h2>Election identity</h2><p>Clear details help candidates and voters understand the purpose of this election.</p></div>
        </div>
        <div class="form-grid">
            <div class="field field-wide">
                <label for="title">Election title</label>
                <input id="title" name="title" maxlength="120" value="<?= e($value('title')) ?>" required>
                <?php if (isset($errors['title'])): ?><small class="field-error"><?= e($errors['title']) ?></small><?php endif; ?>
            </div>
            <div class="field field-wide">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4" maxlength="2000" required><?= e($value('description')) ?></textarea>
                <?php if (isset($errors['description'])): ?><small class="field-error"><?= e($errors['description']) ?></small><?php endif; ?>
            </div>
            <div class="field field-wide">
                <label for="eligibility_rules">Voter eligibility rules</label>
                <textarea id="eligibility_rules" name="eligibility_rules" rows="3" maxlength="1000" required><?= e($value('eligibility_rules')) ?></textarea>
                <?php if (isset($errors['eligibility_rules'])): ?><small class="field-error"><?= e($errors['eligibility_rules']) ?></small><?php endif; ?>
            </div>
        </div>
    </section>

    <section class="card form-section">
        <div class="section-intro">
            <span class="step-number">2</span>
            <div><h2>Nomination and voting schedule</h2><p>The lifecycle enforces this sequence; voting cannot start before nominations finish.</p></div>
        </div>
        <div class="form-grid">
            <?php foreach ([
                'nomination_start' => 'Nomination starts',
                'nomination_end' => 'Nomination ends',
                'voting_start' => 'Voting starts',
                'voting_end' => 'Voting ends',
            ] as $field => $label): ?>
                <div class="field">
                    <label for="<?= e($field) ?>"><?= e($label) ?></label>
                    <input id="<?= e($field) ?>" name="<?= e($field) ?>" type="datetime-local" value="<?= e($value($field)) ?>" required>
                    <?php if (isset($errors[$field])): ?><small class="field-error"><?= e($errors[$field]) ?></small><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="card form-section">
        <div class="section-intro">
            <span class="step-number">3</span>
            <div><h2>Hosting fee</h2><p>The payment service must confirm this exact amount before nominations can open.</p></div>
        </div>
        <div class="form-grid form-grid-compact">
            <div class="field">
                <label for="hosting_fee">Amount</label>
                <input id="hosting_fee" name="hosting_fee" type="number" min="1" max="10000" step="0.01" value="<?= e($value('hosting_fee')) ?>" required>
                <?php if (isset($errors['hosting_fee'])): ?><small class="field-error"><?= e($errors['hosting_fee']) ?></small><?php endif; ?>
            </div>
            <div class="field">
                <label for="currency">Currency</label>
                <select id="currency" name="currency">
                    <option value="MYR"<?= selected($value('currency', 'MYR'), 'MYR') ?>>MYR · Malaysian Ringgit</option>
                    <option value="USD"<?= selected($value('currency'), 'USD') ?>>USD · US Dollar</option>
                </select>
                <?php if (isset($errors['currency'])): ?><small class="field-error"><?= e($errors['currency']) ?></small><?php endif; ?>
            </div>
        </div>
    </section>

    <div class="form-actions">
        <a class="button button-quiet" href="<?= e($cancelUrl) ?>">Cancel</a>
        <button class="button button-primary" type="submit"><?= e($submitLabel) ?></button>
    </div>
</form>

