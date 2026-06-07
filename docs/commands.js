const commandSearchInput = document.querySelector('#command-search');
const commandResultCount = document.querySelector('#command-result-count');
const commandCards = Array.from(document.querySelectorAll('[data-command-card]'));
const commandGroups = Array.from(document.querySelectorAll('[data-command-group]'));
const commandTypeLinks = Array.from(document.querySelectorAll('[data-command-type-link]'));
const commandIndexLinks = Array.from(document.querySelectorAll('[data-command-index-link]'));
const commandIndexSections = Array.from(document.querySelectorAll('[data-command-index-section]'));

function normalizeCommandText(value) {
    return value.toLowerCase().trim();
}

function updateCommandFilter() {
    const query = normalizeCommandText(commandSearchInput?.value ?? '');
    const isFiltering = query !== '';
    let visible = 0;
    const visibleCommands = new Set();

    for (const card of commandCards) {
        const haystack = normalizeCommandText([
            card.dataset.command,
            card.dataset.module,
            card.dataset.type,
            card.dataset.search,
        ].join(' '));
        const matches = ! isFiltering || haystack.includes(query);
        card.hidden = ! matches;
        card.classList.toggle('is-filtered-out', ! matches);
        visible += matches ? 1 : 0;

        if (matches && card.dataset.command) {
            visibleCommands.add(card.dataset.command);
        }
    }

    for (const group of commandGroups) {
        const visibleCards = group.querySelectorAll('[data-command-card]:not(.is-filtered-out)').length;
        group.hidden = visibleCards === 0;
        group.classList.toggle('is-filtered-out', visibleCards === 0);

        const groupCount = group.querySelector('[data-command-group-count]');

        if (groupCount) {
            const total = Number(groupCount.dataset.total ?? visibleCards);
            groupCount.textContent = isFiltering
                ? `${visibleCards} of ${total} ${total === 1 ? 'command' : 'commands'}`
                : `${total} ${total === 1 ? 'command' : 'commands'}`;
        }
    }

    for (const link of commandIndexLinks) {
        link.hidden = ! visibleCommands.has(link.dataset.command);
        link.classList.toggle('is-filtered-out', ! visibleCommands.has(link.dataset.command));
    }

    for (const section of commandIndexSections) {
        const visibleLinks = section.querySelectorAll('[data-command-index-link]:not(.is-filtered-out)').length;
        section.hidden = visibleLinks === 0;
        section.classList.toggle('is-filtered-out', visibleLinks === 0);

        const typeCount = section.querySelector('[data-command-type-count]');

        if (typeCount) {
            const total = Number(typeCount.dataset.total ?? visibleLinks);
            typeCount.textContent = isFiltering ? String(visibleLinks) : String(total);
        }
    }

    if (commandResultCount) {
        commandResultCount.textContent = `${visible} ${visible === 1 ? 'command' : 'commands'} shown`;
    }

    activateCommandTypeLink();
}

async function copyCommand(button) {
    const command = button.dataset.copyCommand;

    if (! command) {
        return;
    }

    try {
        await navigator.clipboard.writeText(command);
        button.textContent = 'Copied';
        window.setTimeout(() => {
            button.textContent = 'Copy';
        }, 1400);
    } catch {
        button.textContent = 'Select';
    }
}

function activateCommandTypeLink() {
    const currentGroup = commandGroups.find(group => {
        if (group.classList.contains('is-filtered-out')) {
            return false;
        }

        const box = group.getBoundingClientRect();

        return box.top <= 140 && box.bottom > 140;
    });
    const currentCard = commandCards.find(card => {
        if (card.classList.contains('is-filtered-out')) {
            return false;
        }

        const box = card.getBoundingClientRect();

        return box.top <= 180 && box.bottom > 180;
    });

    for (const link of commandTypeLinks) {
        link.classList.toggle('active', currentGroup?.dataset.commandType === link.dataset.commandTypeLink);
    }

    for (const link of commandIndexLinks) {
        link.classList.toggle('active', currentCard?.dataset.command === link.dataset.command);
    }
}

commandSearchInput?.addEventListener('input', updateCommandFilter);
document.addEventListener('click', event => {
    const button = event.target instanceof HTMLElement ? event.target.closest('[data-copy-command]') : null;

    if (button instanceof HTMLButtonElement) {
        void copyCommand(button);
    }
});
document.addEventListener('scroll', activateCommandTypeLink, { passive: true });

updateCommandFilter();
activateCommandTypeLink();
