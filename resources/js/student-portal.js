import QRCode from 'qrcode';

const accountMenu = document.querySelector('[data-account-menu]');
document.addEventListener('click', event => { if (accountMenu?.open && !accountMenu.contains(event.target)) accountMenu.removeAttribute('open'); });
document.addEventListener('keydown', event => { if (event.key === 'Escape') accountMenu?.removeAttribute('open'); });

const studentSidebar = document.querySelector('[data-student-sidebar]');
const studentSidebarToggle = document.querySelector('[data-student-sidebar-toggle]');
const studentSidebarContent = [...document.querySelectorAll('[data-student-sidebar-content]')];
const setStudentSidebar = expanded => {
    if (!studentSidebar || !studentSidebarToggle) return;
    studentSidebar.classList.toggle('w-64', expanded);
    studentSidebar.classList.toggle('w-20', !expanded);
    studentSidebar.querySelector('[data-sidebar-header]')?.classList.toggle('justify-between', expanded);
    studentSidebar.querySelectorAll('[data-sidebar-label]').forEach(label => {
        label.classList.toggle('hidden', !expanded);
        label.classList.toggle('flex', expanded && label.tagName === 'A');
    });
    studentSidebarContent.forEach(content => {
        content.classList.toggle('lg:ml-64', expanded);
        content.classList.toggle('lg:ml-20', !expanded);
    });
    studentSidebarToggle.setAttribute('aria-expanded', String(expanded));
    studentSidebarToggle.setAttribute('aria-label', expanded ? 'Collapse navigation' : 'Expand navigation');
};
if (studentSidebar && studentSidebarToggle) {
    setStudentSidebar(false);
    studentSidebarToggle.addEventListener('click', () => setStudentSidebar(studentSidebarToggle.getAttribute('aria-expanded') !== 'true'));
}

const themeChoices = [...document.querySelectorAll('[data-theme-choice]')];
const systemTheme = window.matchMedia('(prefers-color-scheme: dark)');
const applyTheme = theme => {
    const resolvedDark = theme === 'dark' || (theme === 'system' && systemTheme.matches);
    document.documentElement.classList.toggle('dark', resolvedDark);
    document.documentElement.dataset.theme = theme;
    themeChoices.forEach(choice => {
        const active = choice.dataset.themeChoice === theme;
        choice.setAttribute('aria-pressed', String(active));
        choice.classList.toggle('ring-2', active);
        choice.classList.toggle('ring-[#397565]', active);
        choice.querySelector('[data-theme-check]')?.classList.toggle('opacity-0', !active);
    });
};
if (themeChoices.length) {
    const selectedTheme = window.localStorage.getItem('cite-theme') || 'light';
    applyTheme(selectedTheme);
    themeChoices.forEach(choice => choice.addEventListener('click', () => {
        const theme = choice.dataset.themeChoice;
        window.localStorage.setItem('cite-theme', theme);
        applyTheme(theme);
    }));
    systemTheme.addEventListener('change', () => {
        if ((window.localStorage.getItem('cite-theme') || 'light') === 'system') applyTheme('system');
    });
}

document.querySelectorAll('[data-image-input]').forEach(input => input.addEventListener('change', () => {
    const label = input.closest('form')?.querySelector('[data-file-name]');
    if (label) label.textContent = input.files?.[0]?.name || 'No file selected';
}));

const submissionsDialog = document.querySelector('[data-submissions-dialog]');
document.querySelector('[data-submissions-open]')?.addEventListener('click', () => submissionsDialog?.showModal());
document.querySelector('[data-submissions-close]')?.addEventListener('click', () => submissionsDialog?.close());
submissionsDialog?.addEventListener('click', event => { if (event.target === submissionsDialog) submissionsDialog.close(); });

const qrCanvas = document.querySelector('[data-qr-canvas]');
const qrError = document.querySelector('[data-qr-error]');
const renderQr = async payload => {
    if (!qrCanvas || !payload) return;
    try {
        if (qrError) qrError.textContent = '';
        await QRCode.toCanvas(qrCanvas, payload, { width: 320, margin: 2, errorCorrectionLevel: 'M', color: { dark: '#121017', light: '#ffffff' } });
    } catch { if (qrError) qrError.textContent = 'The QR code could not be generated. Refresh and try again.'; }
};
const qrTabs = [...document.querySelectorAll('[data-qr-tab]')];
if (qrTabs.length) {
    renderQr(qrTabs[0].dataset.payload);
    qrTabs.forEach(tab => tab.addEventListener('click', () => {
        qrTabs.forEach(item => { item.dataset.active = String(item === tab); item.setAttribute('aria-selected', String(item === tab)); });
        renderQr(tab.dataset.payload);
    }));
}

const rankTabs = [...document.querySelectorAll('[data-rank-tab]')];
const rankList = document.querySelector('[data-ranking-list]');
rankTabs.forEach(tab => tab.addEventListener('click', () => {
    const category = tab.dataset.category;
    rankTabs.forEach(item => {
        const active = item === tab;
        item.setAttribute('aria-selected', String(active));
        item.classList.toggle('bg-[#397565]', active); item.classList.toggle('text-white', active);
        item.classList.toggle('bg-white', !active); item.classList.toggle('text-[#121017]/55', !active);
    });
    const rows = [...rankList.querySelectorAll('[data-rank-row]')];
    const rowScore = row => Number(category === 'overall' ? row.dataset.overall : row.getAttribute(`data-score-${category}`)) || 0;
    const rowIsScored = row => (category === 'overall' ? row.dataset.scoredOverall : row.getAttribute(`data-scored-${category}`)) === '1';
    rows.sort((a, b) => {
        if (rowIsScored(a) !== rowIsScored(b)) return rowIsScored(a) ? -1 : 1;
        return rowScore(b) - rowScore(a) || a.dataset.teamName.localeCompare(b.dataset.teamName);
    });
    let previousScore = null;
    let previousRank = null;
    rows.forEach((row, index) => {
        const score = rowScore(row);
        const isScored = rowIsScored(row);
        const rank = !isScored ? '—' : (previousScore !== null && Math.abs(score - previousScore) < 0.00001 ? previousRank : index + 1);
        row.querySelector('[data-rank-number]').textContent = rank;
        row.querySelector('[data-rank-score]').textContent = Number(score || 0).toLocaleString(undefined, { maximumFractionDigits: 1 });
        rankList.appendChild(row);
        if (isScored) {
            previousScore = score;
            previousRank = rank;
        }
    });
}));
