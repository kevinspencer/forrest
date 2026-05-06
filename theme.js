(function () {
    var stored = localStorage.getItem('theme');
    if (stored) document.documentElement.setAttribute('data-theme', stored);
})();

function isDarkActive() {
    var t = document.documentElement.getAttribute('data-theme');
    return t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches);
}

function toggleTheme() {
    var next = isDarkActive() ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('theme', next);
    updateToggleBtn();
    document.dispatchEvent(new CustomEvent('themechange'));
}

function updateToggleBtn() {
    var btn = document.getElementById('theme-toggle');
    if (!btn) return;
    var dark = isDarkActive();
    btn.textContent = dark ? '☀️' : '🌙';
    btn.title = dark ? 'Switch to light mode' : 'Switch to dark mode';
}
