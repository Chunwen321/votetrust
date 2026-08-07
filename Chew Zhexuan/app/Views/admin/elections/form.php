<?php

declare(strict_types=1);

$isEditing = is_array($election);
$value = static function (string $field, string $default = '') use ($election): string {
    $stored = is_array($election) ? (string) ($election[$field] ?? $default) : $default;

    return (string) old($field, $stored);
};
$dateValue = static function (string $field) use ($value): string {
    $stored = $value($field);

    return $stored === '' ? '' : str_replace(' ', 'T', substr($stored, 0, 16));
};
$action = $isEditing ? '/admin/elections/' . $election['id'] : '/admin/elections';
?>
<section class="page-heading split-heading">
    <div>
        <p class="eyebrow">Administration / elections</p>
        <h1><?= $isEditing ? 'Edit election' : 'Create an election' ?></h1>
        <p>Administrators have direct control over election details and lifecycle settings.</p>
    </div>
    <a class="button button-quiet" href="<?= e(url($isEditing ? '/admin/elections/' . $election['id'] : '/admin/elections')) ?>">Cancel</a>
</section>

<form method="post" action="<?= e(url($action)) ?>" class="form-stack" novalidate>
    <input type="hidden" name="_token" value="<?= e(\VoteTrust\UserAdmin\Core\Csrf::token()) ?>">
    <section class="card form-section">
        <div class="section-intro"><span class="step-number">1</span><div><h2>Election information</h2><p>Provide the details visible to authorised participants.</p></div></div>
        <div class="form-grid">
            <label class="field-wide">Election title
                <input name="title" maxlength="150" value="<?= e($value('title')) ?>" required>
            </label>
            <?php if (isset($errors['title'])): ?><p class="field-error field-wide"><?= e($errors['title']) ?></p><?php endif; ?>
            <label class="field-wide">Description
                <textarea name="description" rows="4" maxlength="5000"><?= e($value('description')) ?></textarea>
            </label>
            <?php if (isset($errors['description'])): ?><p class="field-error field-wide"><?= e($errors['description']) ?></p><?php endif; ?>
            <label class="field-wide">Eligibility rules
                <textarea name="eligibility_rules" rows="3" maxlength="5000" placeholder="For example: Registered users included in the approved voter list may vote."><?= e($value('eligibility_rules')) ?></textarea>
            </label>
            <?php if (isset($errors['eligibility_rules'])): ?><p class="field-error field-wide"><?= e($errors['eligibility_rules']) ?></p><?php endif; ?>
        </div>
    </section>

    <section class="card form-section">
        <div class="section-intro"><span class="step-number">2</span><div><h2>Schedule</h2><p>Set the nomination and voting windows before approving the election.</p></div></div>
        <div class="form-grid">
            <label>Nomination starts<input name="nomination_start" type="datetime-local" value="<?= e($dateValue('nomination_start')) ?>"></label>
            <label>Nomination ends<input name="nomination_end" type="datetime-local" value="<?= e($dateValue('nomination_end')) ?>"></label>
            <label>Voting starts<input name="voting_start" type="datetime-local" value="<?= e($dateValue('voting_start')) ?>"></label>
            <label>Voting ends<input name="voting_end" type="datetime-local" value="<?= e($dateValue('voting_end')) ?>"></label>
            <?php foreach (['nomination_start', 'nomination_end', 'voting_start', 'voting_end', 'dates'] as $field): ?>
                <?php if (isset($errors[$field])): ?><p class="field-error field-wide"><?= e($errors[$field]) ?></p><?php endif; ?>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="card form-section">
        <div class="section-intro"><span class="step-number">3</span><div><h2>Hosting details</h2><p>Record any administrative fee for this election.</p></div></div>
        <div class="form-grid form-grid-compact">
            <label>Hosting fee<input name="hosting_fee" type="number" min="0" step="0.01" value="<?= e($value('hosting_fee')) ?>"></label>
            <label>Currency<input name="currency" maxlength="3" value="<?= e($value('currency', 'MYR')) ?>" required></label>
            <?php if (isset($errors['hosting_fee'])): ?><p class="field-error field-wide"><?= e($errors['hosting_fee']) ?></p><?php endif; ?>
            <?php if (isset($errors['currency'])): ?><p class="field-error field-wide"><?= e($errors['currency']) ?></p><?php endif; ?>
        </div>
    </section>

    <div class="form-actions">
        <a class="button button-quiet" href="<?= e(url($isEditing ? '/admin/elections/' . $election['id'] : '/admin/elections')) ?>">Discard</a>
        <button type="submit"><?= $isEditing ? 'Save changes' : 'Create draft election' ?></button>
    </div>
</form>
