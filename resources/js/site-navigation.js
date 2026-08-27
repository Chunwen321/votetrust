const dropdowns = [...document.querySelectorAll('.site-nav-dropdown')];

dropdowns.forEach((dropdown) => {
    dropdown.addEventListener('toggle', () => {
        if (!dropdown.open) {
            return;
        }

        dropdowns.forEach((otherDropdown) => {
            if (otherDropdown !== dropdown) {
                otherDropdown.removeAttribute('open');
            }
        });
    });
});

document.addEventListener('click', (event) => {
    if (event.target instanceof Element && event.target.closest('.site-nav-dropdown')) {
        return;
    }

    dropdowns.forEach((dropdown) => dropdown.removeAttribute('open'));
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
        return;
    }

    const openDropdown = dropdowns.find((dropdown) => dropdown.open);
    if (!openDropdown) {
        return;
    }

    openDropdown.removeAttribute('open');
    openDropdown.querySelector('summary')?.focus();
});
