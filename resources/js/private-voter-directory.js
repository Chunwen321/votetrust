// PIC: Low Wen Kai and Chew Zhexuan — Election UI consuming the User/Voter roster API through Election.

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

const createElement = (tag, className, text) => {
    const element = document.createElement(tag);
    if (className) element.className = className;
    if (text !== undefined) element.textContent = text;

    return element;
};

document.querySelectorAll('[data-file-upload]').forEach((fileUpload) => {
    const input = fileUpload.querySelector('input[type="file"]');
    const fileName = fileUpload.querySelector('[data-file-name]');
    const trigger = fileUpload.querySelector('[data-file-trigger]');

    if (!input || !fileName) {
        return;
    }

    input.addEventListener('change', () => {
        fileName.textContent = input.files?.[0]?.name || 'No file chosen';
    });

    trigger?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        event.preventDefault();
        input.click();
    });
});

document.querySelectorAll('[data-private-voter-directory]').forEach((directory) => {
    const searchInput = directory.querySelector('[data-voter-search]');
    const list = directory.querySelector('[data-voter-directory-list]');
    const summary = directory.querySelector('[data-voter-directory-summary]');
    const loadMore = directory.querySelector('[data-voter-directory-more]');
    const countElements = document.querySelectorAll('[data-private-voter-count]');
    const directoryUrl = directory.dataset.directoryUrl;
    const selectionUrlTemplate = directory.dataset.selectionUrl;
    let page = 1;
    let hasMore = false;
    let searchTimer;
    let pendingDirectoryRequest;

    if (!searchInput || !list || !summary || !loadMore || !directoryUrl || !selectionUrlTemplate) {
        return;
    }

    const setVoterCount = (total) => {
        countElements.forEach((element) => {
            element.textContent = total;
        });
    };

    const showDirectoryMessage = (message, tone = '') => {
        list.replaceChildren(createElement('p', `election-directory-message ${tone}`, message));
    };

    const selectionUrlFor = (userId) => selectionUrlTemplate.replace('__USER_ID__', encodeURIComponent(String(userId)));

    const updateSelection = async (checkbox, user) => {
        const wantedState = checkbox.checked;
        checkbox.disabled = true;
        directory.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(selectionUrlFor(user.userId), {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'same-origin',
                body: JSON.stringify({ assigned: wantedState }),
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok || !payload?.data) {
                throw new Error(payload?.message || 'The voter selection could not be saved.');
            }

            checkbox.checked = payload.data.assigned;
            setVoterCount(payload.data.totalAssigned);
            summary.textContent = payload.data.assigned
                ? `${user.fullName} has voter access.`
                : `${user.fullName} no longer has voter access.`;
        } catch (error) {
            checkbox.checked = !wantedState;
            summary.textContent = error.message || 'The voter selection could not be saved. Please try again.';
        } finally {
            checkbox.disabled = false;
            directory.removeAttribute('aria-busy');
        }
    };

    const renderUsers = (users, append) => {
        if (!append) list.replaceChildren();
        if (users.length === 0 && !append) {
            showDirectoryMessage(searchInput.value.trim() === ''
                ? 'No registered users are available to assign.'
                : 'No registered user matches that IC or passport number.');

            return;
        }

        const fragment = document.createDocumentFragment();
        users.forEach((user) => {
            const label = createElement('label', 'election-directory-row');
            const copy = createElement('span', 'election-directory-user');
            copy.append(
                createElement('strong', '', user.fullName),
                createElement('span', 'election-directory-identity', user.identityNumber),
            );

            const control = createElement('input', 'election-directory-checkbox');
            control.type = 'checkbox';
            control.checked = user.assigned;
            control.setAttribute('aria-label', `Grant voter access to ${user.fullName}`);
            control.addEventListener('change', () => updateSelection(control, user));
            label.append(copy, control);
            fragment.append(label);
        });
        list.append(fragment);
    };

    const loadDirectory = async ({ append = false } = {}) => {
        if (pendingDirectoryRequest) pendingDirectoryRequest.abort();
        pendingDirectoryRequest = new AbortController();
        const targetPage = append ? page + 1 : 1;
        const url = new URL(directoryUrl, window.location.origin);
        url.searchParams.set('search', searchInput.value.trim());
        url.searchParams.set('page', String(targetPage));

        list.setAttribute('aria-busy', 'true');
        summary.textContent = 'Loading registered users…';
        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: pendingDirectoryRequest.signal,
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok || !payload?.data) {
                throw new Error(payload?.message || 'Registered users could not be loaded.');
            }

            page = payload.data.pagination.page;
            hasMore = payload.data.pagination.hasMore;
            renderUsers(payload.data.users, append);
            loadMore.hidden = !hasMore;
            summary.textContent = `${payload.data.pagination.total} registered user${payload.data.pagination.total === 1 ? '' : 's'} found.`;
        } catch (error) {
            if (error.name !== 'AbortError') {
                loadMore.hidden = true;
                showDirectoryMessage(error.message || 'Registered users could not be loaded.', 'is-error');
                summary.textContent = 'Directory unavailable.';
            }
        } finally {
            list.removeAttribute('aria-busy');
            pendingDirectoryRequest = null;
        }
    };

    searchInput.addEventListener('input', () => {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => loadDirectory(), 250);
    });
    loadMore.addEventListener('click', () => loadDirectory({ append: true }));
    loadDirectory();
});
