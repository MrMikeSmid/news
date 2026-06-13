const toggle = document.querySelector('.nav-toggle');
const nav = document.querySelector('.main-nav');

if (toggle && nav) {
    toggle.addEventListener('click', () => {
        const open = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', String(!open));
        nav.classList.toggle('open', !open);
    });
    nav.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => {
        toggle.setAttribute('aria-expanded', 'false');
        nav.classList.remove('open');
    }));
}

const observer = 'IntersectionObserver' in window
    ? new IntersectionObserver((entries) => entries.forEach((entry) => {
        if (entry.isIntersecting) {
            entry.target.classList.add('visible');
            observer.unobserve(entry.target);
        }
    }), { threshold: 0.12 })
    : null;

document.querySelectorAll('.reveal').forEach((element) => {
    if (observer) observer.observe(element);
    else element.classList.add('visible');
});
