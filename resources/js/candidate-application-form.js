// PIC: Wong Shun Bin — live length feedback for candidate application fields.
const initializeCandidateApplicationForm = () => {
    document.querySelectorAll('[data-live-length]').forEach((field) => {
        if (!(field instanceof HTMLTextAreaElement)) {
            return;
        }

        const describedBy = (field.getAttribute('aria-describedby') || '').split(/\s+/);
        const feedbackId = describedBy.find((id) => id.endsWith('-live-error'));
        const feedback = feedbackId ? document.getElementById(feedbackId) : null;

        if (!feedback) {
            return;
        }

        const minimum = Number.parseInt(field.getAttribute('minlength') || '0', 10);
        const maximum = Number.parseInt(field.getAttribute('maxlength') || '0', 10);

        const update = () => {
            const length = Array.from(field.value).length;
            const missing = Math.max(0, minimum - length);

            feedback.classList.remove('candidate-live-validation--error');
            field.classList.remove('candidate-input--invalid');

            if (missing > 0 && length > 0) {
                const noun = missing === 1 ? 'character' : 'characters';
                feedback.textContent = `${missing} more ${noun} needed (minimum ${minimum}).`;
                feedback.classList.add('candidate-live-validation--error');
                field.classList.add('candidate-input--invalid');
                return;
            }

            if (length >= minimum && length > 0) {
                feedback.textContent = maximum > 0
                    ? `${length} of ${maximum} characters used.`
                    : `${length} characters used.`;
                return;
            }

            if (field.required && field.dataset.liveTouched === 'true') {
                feedback.textContent = `This field is required (minimum ${minimum} characters).`;
                feedback.classList.add('candidate-live-validation--error');
                field.classList.add('candidate-input--invalid');
                return;
            }

            feedback.textContent = maximum > 0 && minimum === 0
                ? `0 of ${maximum} characters used.`
                : '';
        };

        field.addEventListener('input', () => {
            field.dataset.liveTouched = 'true';
            update();
        });
        field.addEventListener('blur', () => {
            field.dataset.liveTouched = 'true';
            update();
        });

        if (field.value.length > 0) {
            field.dataset.liveTouched = 'true';
        }
        update();
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeCandidateApplicationForm);
} else {
    initializeCandidateApplicationForm();
}
