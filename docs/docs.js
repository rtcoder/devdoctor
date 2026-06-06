const currentPage = window.location.pathname.split('/').pop() || 'index.html';
const pageTitle = document.querySelector('h1')?.textContent?.trim() || 'Documentation';

for (const link of document.querySelectorAll('.primary-nav a')) {
    const linkPage = link.getAttribute('href')?.split('#')[0];
    const active = linkPage === currentPage || (currentPage === 'index.html' && linkPage === 'index.html');

    if (active) {
        link.classList.add('active');
        link.setAttribute('aria-current', 'page');
    }
}

const hero = document.querySelector('.hero');

if (hero && currentPage !== 'index.html') {
    const breadcrumb = document.createElement('nav');
    breadcrumb.className = 'breadcrumbs';
    breadcrumb.setAttribute('aria-label', 'Breadcrumb');
    breadcrumb.innerHTML = '<a href="index.html">Docs</a><span aria-hidden="true">/</span><span></span>';
    breadcrumb.querySelector('span:last-child').textContent = pageTitle;
    hero.prepend(breadcrumb);
}

for (const pre of document.querySelectorAll('pre')) {
    if (pre.querySelector('.copy-snippet')) {
        continue;
    }

    const code = pre.querySelector('code');

    if (! code) {
        continue;
    }

    const button = document.createElement('button');
    button.className = 'copy-snippet';
    button.type = 'button';
    button.textContent = 'Copy';
    button.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(code.textContent || '');
            button.textContent = 'Copied';
            window.setTimeout(() => {
                button.textContent = 'Copy';
            }, 1400);
        } catch {
            button.textContent = 'Select';
        }
    });

    pre.append(button);
}
