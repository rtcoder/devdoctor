const searchInput = document.querySelector('#issue-code-search');
const resultCount = document.querySelector('#issue-code-result-count');
const cards = Array.from(document.querySelectorAll('[data-code-card]'));
const groups = Array.from(document.querySelectorAll('[data-code-group]'));
const moduleLinks = Array.from(document.querySelectorAll('[data-module-link]'));

function normalize(value) {
    return value.toLowerCase().trim();
}

function updateFilter() {
    const query = normalize(searchInput?.value ?? '');
    let visible = 0;

    for (const card of cards) {
        const haystack = normalize([
            card.dataset.code,
            card.dataset.module,
            card.dataset.description,
        ].join(' '));
        const matches = query === '' || haystack.includes(query);
        card.hidden = ! matches;
        visible += matches ? 1 : 0;
    }

    for (const group of groups) {
        const visibleCards = group.querySelectorAll('[data-code-card]:not([hidden])').length;
        group.hidden = visibleCards === 0;
    }

    if (resultCount) {
        resultCount.textContent = `${visible} ${visible === 1 ? 'code' : 'codes'} shown`;
    }
}

async function copyCode(button) {
    const code = button.dataset.copyCode;

    if (! code) {
        return;
    }

    try {
        await navigator.clipboard.writeText(code);
        button.textContent = 'Copied';
        window.setTimeout(() => {
            button.textContent = 'Copy';
        }, 1400);
    } catch {
        button.textContent = 'Select';
    }
}

function activateModuleLink() {
    const current = groups.find(group => {
        const box = group.getBoundingClientRect();

        return box.top <= 140 && box.bottom > 140;
    });

    for (const link of moduleLinks) {
        link.classList.toggle('active', current?.dataset.module === link.dataset.moduleLink);
    }
}

searchInput?.addEventListener('input', updateFilter);
document.addEventListener('click', event => {
    const button = event.target instanceof HTMLElement ? event.target.closest('[data-copy-code]') : null;

    if (button instanceof HTMLButtonElement) {
        void copyCode(button);
    }
});
document.addEventListener('scroll', activateModuleLink, { passive: true });

updateFilter();
activateModuleLink();
