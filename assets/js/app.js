const toggleButton = document.querySelector('[data-nav-toggle]');
const nav = document.querySelector('[data-nav]');

if (toggleButton && nav) {
    toggleButton.addEventListener('click', () => {
        nav.classList.toggle('is-open');
    });
}

document.querySelectorAll('[data-confirm]').forEach((element) => {
    element.addEventListener('click', (event) => {
        const message = element.getAttribute('data-confirm') || 'Are you sure you want to continue?';

        if (!window.confirm(message)) {
            event.preventDefault();
        }
    });
});
