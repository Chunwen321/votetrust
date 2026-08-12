/* PIC: Low Wen Kai - Election Management Module */

document.querySelectorAll('[data-election-form]').forEach((form) => {
    const choices = form.querySelectorAll('input[name="eligibility_mode"]');
    const criteria = form.querySelector('[data-eligibility-criteria]');
    const fields = Array.from(form.querySelectorAll('input:not([type="hidden"]):not([type="radio"]), textarea, select'));
    const validationSummary = document.querySelector('[data-validation-summary]');

    const value = (name) => form.elements.namedItem(name)?.value.trim() ?? '';

    const selectedEligibilityMode = () => form.querySelector('input[name="eligibility_mode"]:checked')?.value ?? '';

    const dateValue = (name) => {
        const raw = value(name);
        const timestamp = raw === '' ? Number.NaN : Date.parse(raw);

        return Number.isNaN(timestamp) ? null : timestamp;
    };

    const validationMessage = (control) => {
        const fieldValue = control.value.trim();

        if (control.required && fieldValue === '') {
            return 'This field is required.';
        }

        switch (control.name) {
            case 'title': {
                const remaining = 5 - fieldValue.length;

                return remaining > 0
                    ? `Enter at least 5 characters (${remaining} more ${remaining === 1 ? 'character' : 'characters'} needed).`
                    : '';
            }
            case 'description': {
                const remaining = 20 - fieldValue.length;

                return remaining > 0
                    ? `Enter at least 20 characters (${remaining} more ${remaining === 1 ? 'character' : 'characters'} needed).`
                    : '';
            }
            case 'nomination_start':
                return dateValue('nomination_start') === null ? 'Enter a valid nomination start date and time.' : '';
            case 'nomination_end': {
                const start = dateValue('nomination_start');
                const end = dateValue('nomination_end');

                if (end === null) {
                    return 'Enter a valid nomination end date and time.';
                }

                return start !== null && end <= start ? 'Nomination end must be after nomination start.' : '';
            }
            case 'voting_start': {
                const nominationEnd = dateValue('nomination_end');
                const votingStart = dateValue('voting_start');

                if (votingStart === null) {
                    return 'Enter a valid voting start date and time.';
                }

                return nominationEnd !== null && votingStart < nominationEnd ? 'Voting start cannot be before nomination end.' : '';
            }
            case 'voting_end': {
                const votingStart = dateValue('voting_start');
                const votingEnd = dateValue('voting_end');

                if (votingEnd === null) {
                    return 'Enter a valid voting end date and time.';
                }

                return votingStart !== null && votingEnd <= votingStart ? 'Voting end must be after voting start.' : '';
            }
            default:
                return '';
        }
    };

    const liveError = (control) => {
        let error = form.querySelector(`[data-live-error-for="${control.name}"]`);

        if (!error) {
            error = document.createElement('small');
            error.className = 'election-field-error election-field-error--live';
            error.id = `${control.id}-live-error`;
            error.dataset.liveErrorFor = control.name;
            error.setAttribute('aria-live', 'polite');
            error.hidden = true;
            control.insertAdjacentElement('afterend', error);
        }

        return error;
    };

    const hideServerErrors = (fieldName) => {
        form.querySelectorAll(`[data-server-error-for="${fieldName}"]`).forEach((serverError) => {
            serverError.hidden = true;
        });

        validationSummary?.querySelectorAll('[data-summary-error-for]').forEach((summaryError) => {
            if (summaryError.dataset.summaryErrorFor === fieldName) {
                summaryError.hidden = true;
            }
        });

        if (validationSummary && !validationSummary.querySelector('[data-summary-error-for]:not([hidden])')) {
            validationSummary.hidden = true;
        }
    };

    const validateField = (control, showMessage = true) => {
        const message = validationMessage(control);

        if (showMessage) {
            const error = liveError(control);
            error.textContent = message;
            error.hidden = message === '';
            control.setAttribute('aria-invalid', String(message !== ''));
            control.setAttribute('aria-errormessage', error.id);
            hideServerErrors(control.name);
        }

        return message === '';
    };

    const criteriaMessage = () => {
        if (selectedEligibilityMode() !== 'RESTRICTED') {
            return '';
        }

        return value('eligibility_minimum_age') === '' && value('eligibility_nationality') === 'ANY'
            ? 'Select at least one eligibility requirement.'
            : '';
    };

    const validateCriteria = (showMessage = true) => {
        const message = criteriaMessage();
        const age = form.elements.namedItem('eligibility_minimum_age');
        const nationality = form.elements.namedItem('eligibility_nationality');

        if (showMessage) {
            let error = form.querySelector('[data-live-error-for="eligibility_criteria"]');

            if (!error) {
                error = document.createElement('small');
                error.className = 'election-field-error election-field-error--live';
                error.dataset.liveErrorFor = 'eligibility_criteria';
                error.setAttribute('aria-live', 'polite');
                criteria.insertAdjacentElement('afterend', error);
            }

            error.textContent = message;
            error.hidden = message === '';
            age.setAttribute('aria-invalid', String(message !== ''));
            nationality.setAttribute('aria-invalid', String(message !== ''));
            hideServerErrors('eligibility_criteria');
        }

        return message === '';
    };

    const formIsValid = (showMessages = false) => {
        const fieldsValid = fields.every((control) => validateField(control, showMessages));

        return fieldsValid && selectedEligibilityMode() !== '' && validateCriteria(showMessages);
    };

    const hideResolvedSummary = () => {
        if (validationSummary && formIsValid(false)) {
            validationSummary.hidden = true;
        }
    };

    const revalidateDependents = (control) => {
        const dependents = {
            nomination_start: ['nomination_end'],
            nomination_end: ['voting_start'],
            voting_start: ['voting_end'],
        }[control.name] ?? [];

        dependents.forEach((name) => {
            const dependent = form.elements.namedItem(name);

            if (dependent?.value !== '') {
                validateField(dependent);
            }
        });
    };

    if (!criteria || choices.length === 0) {
        return;
    }

    const updateEligibility = () => {
        const selected = form.querySelector('input[name="eligibility_mode"]:checked');
        const restricted = selected?.value === 'RESTRICTED';
        criteria.hidden = !restricted;
        criteria.querySelectorAll('select').forEach((control) => {
            control.disabled = !restricted;
        });

        if (!restricted) {
            validateCriteria();
        }
    };

    choices.forEach((choice) => choice.addEventListener('change', () => {
        updateEligibility();
        hideServerErrors('eligibility_mode');
        validateCriteria();
        hideResolvedSummary();
    }));

    fields.forEach((control) => {
        const validate = () => {
            control.dataset.touched = 'true';
            validateField(control);

            if (['eligibility_minimum_age', 'eligibility_nationality'].includes(control.name)) {
                validateCriteria();
            }

            revalidateDependents(control);
            hideResolvedSummary();
        };

        control.addEventListener('input', validate);
        control.addEventListener('change', validate);
        control.addEventListener('blur', validate);
    });

    form.addEventListener('submit', (event) => {
        fields.forEach((control) => {
            control.dataset.touched = 'true';
            validateField(control);
        });

        const validCriteria = validateCriteria();
        const invalidField = fields.find((control) => control.getAttribute('aria-invalid') === 'true');

        if (invalidField || !validCriteria || selectedEligibilityMode() === '') {
            event.preventDefault();
            (invalidField ?? form.querySelector('input[name="eligibility_mode"]'))?.focus();
        }
    });

    updateEligibility();
});
