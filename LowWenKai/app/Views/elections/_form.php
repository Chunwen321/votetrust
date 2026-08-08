<?php

use VoteTrust\Election\Core\Csrf;
use VoteTrust\Election\ValueObjects\EligibilityRules;

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
$storedEligibility = $election !== null
    ? $election->eligibilityRules()
    : EligibilityRules::publicElection();
$eligibilityMode = strtoupper((string) old('eligibility_mode', $storedEligibility->mode()));
$eligibilityMinimumAge = (string) old('eligibility_minimum_age', $storedEligibility->minimumAge() ?? '');
$eligibilityOccupation = strtoupper((string) old('eligibility_occupation', $storedEligibility->occupation()));
$eligibilityNationality = strtoupper((string) old('eligibility_nationality', $storedEligibility->nationality()));
?>
<form class="form-stack" method="post" action="<?= e($formAction) ?>" novalidate data-election-form>
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
            <fieldset class="field field-wide eligibility-fieldset">
                <legend>Set voter eligibility requirements?</legend>
                <div class="eligibility-choice-grid">
                    <label class="eligibility-choice">
                        <input type="radio" name="eligibility_mode" value="<?= e(EligibilityRules::PUBLIC) ?>"<?= $eligibilityMode === EligibilityRules::PUBLIC ? ' checked' : '' ?> required>
                        <span><strong>No — public election</strong><small>Any registered voter may participate.</small></span>
                    </label>
                    <label class="eligibility-choice">
                        <input type="radio" name="eligibility_mode" value="<?= e(EligibilityRules::RESTRICTED) ?>"<?= $eligibilityMode === EligibilityRules::RESTRICTED ? ' checked' : '' ?> required>
                        <span><strong>Yes — restricted election</strong><small>Only voters matching the selected requirements may participate.</small></span>
                    </label>
                </div>
                <?php if (isset($errors['eligibility_mode'])): ?><small class="field-error"><?= e($errors['eligibility_mode']) ?></small><?php endif; ?>

                <div class="eligibility-criteria" data-eligibility-criteria<?= $eligibilityMode !== EligibilityRules::RESTRICTED ? ' hidden' : '' ?>>
                    <div class="criteria-heading">
                        <strong>Choose at least one requirement</strong>
                        <small>The User/Voter Module will compare these values with the voter profile.</small>
                    </div>
                    <div class="criteria-grid">
                        <div class="field">
                            <label for="eligibility_minimum_age">Minimum age</label>
                            <select id="eligibility_minimum_age" name="eligibility_minimum_age">
                                <option value="">No age restriction</option>
                                <?php foreach (EligibilityRules::minimumAgeOptions() as $age): ?>
                                    <option value="<?= e($age) ?>"<?= selected($eligibilityMinimumAge, $age) ?>><?= e($age) ?> years or older</option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['eligibility_minimum_age'])): ?><small class="field-error"><?= e($errors['eligibility_minimum_age']) ?></small><?php endif; ?>
                        </div>
                        <div class="field">
                            <label for="eligibility_occupation">Occupation</label>
                            <select id="eligibility_occupation" name="eligibility_occupation">
                                <?php foreach (EligibilityRules::occupationOptions() as $key => $label): ?>
                                    <option value="<?= e($key) ?>"<?= selected($eligibilityOccupation, $key) ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['eligibility_occupation'])): ?><small class="field-error"><?= e($errors['eligibility_occupation']) ?></small><?php endif; ?>
                        </div>
                        <div class="field">
                            <label for="eligibility_nationality">Nationality</label>
                            <select id="eligibility_nationality" name="eligibility_nationality">
                                <?php foreach (EligibilityRules::nationalityOptions() as $key => $label): ?>
                                    <option value="<?= e($key) ?>"<?= selected($eligibilityNationality, $key) ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['eligibility_nationality'])): ?><small class="field-error"><?= e($errors['eligibility_nationality']) ?></small><?php endif; ?>
                        </div>
                    </div>
                    <?php if (isset($errors['eligibility_criteria'])): ?><small class="field-error eligibility-criteria-error"><?= e($errors['eligibility_criteria']) ?></small><?php endif; ?>
                </div>
            </fieldset>
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
<script src="<?= e(url('/assets/election-form.js')) ?>" defer></script>

