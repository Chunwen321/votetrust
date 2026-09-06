document.querySelectorAll('[data-demo]').forEach((demo) => {
    const buttons = demo.querySelectorAll('[data-show-state]');
    const states = demo.querySelectorAll('[data-state]');

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            const selectedState = button.dataset.showState;

            buttons.forEach((candidate) => {
                const isSelected = candidate === button;
                candidate.classList.toggle('is-active', isSelected);
                candidate.setAttribute('aria-pressed', String(isSelected));
            });

            states.forEach((state) => {
                state.hidden = state.dataset.state !== selectedState;
            });
        });
    });
});
