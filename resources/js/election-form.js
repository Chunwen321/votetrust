/* PIC: Low Wen Kai - Election Management Module */

document.querySelectorAll('[data-election-form]').forEach((form) => {
    const choices = form.querySelectorAll('input[name="eligibility_mode"]');
    const criteria = form.querySelector('[data-eligibility-criteria]');

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
    };

    choices.forEach((choice) => choice.addEventListener('change', updateEligibility));
    updateEligibility();
});
