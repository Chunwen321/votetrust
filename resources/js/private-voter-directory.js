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

document.querySelectorAll('[data-participant-directory]').forEach((directory) => {
    const searchInput = directory.querySelector('[data-participant-search], [data-voter-search]');
    const passwordInput = directory.querySelector('[data-participant-password]');
    const list = directory.querySelector('[data-participant-directory-list], [data-voter-directory-list]');
    const summary = directory.querySelector('[data-participant-directory-summary], [data-voter-directory-summary]');
    const loadMore = directory.querySelector('[data-participant-directory-more], [data-voter-directory-more]');
    const singular = directory.dataset.participantSingular || 'participant';
    const role = directory.dataset.participantRole || `${singular} assignment`;
    const resultLabel = directory.dataset.resultLabel || 'registered user';
    const countElements = document.querySelectorAll(directory.dataset.countSelector || '[data-private-voter-count]');
    const emptyMessage = directory.dataset.emptyMessage || `No registered users are available to assign as ${singular}s.`;
    const noMatchMessage = directory.dataset.noMatchMessage || 'No registered user matches that search.';
    const directoryUrl = directory.dataset.directoryUrl;
    const selectionUrlTemplate = directory.dataset.selectionUrl;
    let page = 1;
    let hasMore = false;
    let searchTimer;
    let pendingDirectoryRequest;

    if (!searchInput || !passwordInput || !list || !summary || !loadMore || !directoryUrl || !selectionUrlTemplate) {
        return;
    }

    const setParticipantCount = (total) => {
        countElements.forEach((element) => {
            element.textContent = total;
        });
    };

    const setElectionVersion = (version) => {
        if (!Number.isInteger(version)) return;

        document.querySelectorAll('input[name="version"]').forEach((input) => {
            input.value = String(version);
        });
    };

    const showDirectoryMessage = (message, tone = '') => {
        list.replaceChildren(createElement('p', `election-directory-message ${tone}`, message));
    };

    const selectionUrlFor = (userId) => selectionUrlTemplate.replace('__USER_ID__', encodeURIComponent(String(userId)));

    const updateSelection = async (checkbox, user) => {
        const wantedState = checkbox.checked;
        if (passwordInput.value === '') {
            checkbox.checked = !wantedState;
            summary.textContent = `Enter your current password before changing ${role}.`;
            passwordInput.focus();

            return;
        }

        const currentPassword = passwordInput.value;
        passwordInput.value = '';
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
                body: JSON.stringify({ assigned: wantedState, current_password: currentPassword }),
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok || !payload?.data) {
                throw new Error(payload?.message || `The ${singular} selection could not be saved.`);
            }

            checkbox.checked = payload.data.assigned;
            setParticipantCount(payload.data.totalAssigned);
            setElectionVersion(payload.data.version);
            summary.textContent = payload.data.assigned
                ? `${user.fullName} now has ${role}.`
                : `${user.fullName} no longer has ${role}.`;
        } catch (error) {
            checkbox.checked = !wantedState;
            summary.textContent = error.message || `The ${singular} selection could not be saved. Please try again.`;
        } finally {
            checkbox.disabled = false;
            directory.removeAttribute('aria-busy');
        }
    };

    const renderUsers = (users, append) => {
        if (!append) list.replaceChildren();
        if (users.length === 0 && !append) {
            showDirectoryMessage(searchInput.value.trim() === '' ? emptyMessage : noMatchMessage);

            return;
        }

        const fragment = document.createDocumentFragment();
        users.forEach((user) => {
            const label = createElement('label', 'election-directory-row');
            const copy = createElement('span', 'election-directory-user');
            copy.append(createElement('strong', '', user.fullName));
            const secondaryText = user.secondaryText || user.identityNumber || user.email;
            if (secondaryText) {
                copy.append(createElement('span', 'election-directory-identity', secondaryText));
            }
            if (singular === 'voter' && user.verified === false) {
                copy.append(createElement('span', 'election-directory-verification', 'Verification required before voting'));
            }

            const control = createElement('input', 'election-directory-checkbox');
            control.type = 'checkbox';
            control.checked = user.assigned;
            control.setAttribute('aria-label', `${user.assigned ? 'Remove' : 'Grant'} ${role} ${user.assigned ? 'from' : 'to'} ${user.fullName}`);
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
            summary.textContent = `${payload.data.pagination.total} ${resultLabel}${payload.data.pagination.total === 1 ? '' : 's'} found.`;
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
