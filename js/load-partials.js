document.addEventListener('DOMContentLoaded', async () => {
    const headerContainer = document.getElementById('header-container');
    if (headerContainer) {
        try {
            const response = await fetch('partials/header.html');
            const headerHTML = await response.text();
            headerContainer.innerHTML = headerHTML;

            setupDesktopDropdown();
            setupMobileMenu();
            setupMobileServices();
        } catch (e) {
            console.log('Header loaded inline');
            setupDesktopDropdown();
            setupMobileMenu();
            setupMobileServices();
        }
    }

    const footerContainer = document.getElementById('footer-container');
    if (footerContainer) {
        try {
            const response = await fetch('partials/footer.html');
            const footerHTML = await response.text();
            footerContainer.innerHTML = footerHTML;
        } catch (e) {
            console.log('Footer loaded inline');
        }
    }
});

function setupDesktopDropdown() {
    const dropdownBtn = document.getElementById('servicesDropdownBtn');
    const menu = document.getElementById('servicesMenu');
    const arrow = document.getElementById('servicesArrow');

    if (!dropdownBtn || !menu) return;

    dropdownBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = !menu.classList.contains('hidden');
        menu.classList.toggle('hidden');
        if (arrow) {
            arrow.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)';
        }
    });

    document.addEventListener('click', (e) => {
        const wrapper = document.getElementById('desktopServicesDropdown');
        if (wrapper && !wrapper.contains(e.target) && !menu.classList.contains('hidden')) {
            menu.classList.add('hidden');
            if (arrow) arrow.style.transform = 'rotate(0deg)';
        }
    });
}

function setupMobileMenu() {
    const btn = document.getElementById('mobileMenuBtn');
    const menu = document.getElementById('mobileMenu');

    if (!btn || !menu) return;

    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        menu.classList.toggle('hidden');
        const icon = btn.querySelector('i');
        if (icon) {
            if (menu.classList.contains('hidden')) {
                icon.className = 'fas fa-bars fa-lg';
            } else {
                icon.className = 'fas fa-times fa-lg';
            }
        }
    });

    const mobileNavLinks = menu.querySelectorAll('a:not([href="#"])');
    mobileNavLinks.forEach(link => {
        link.addEventListener('click', () => {
            menu.classList.add('hidden');
            const icon = btn.querySelector('i');
            if (icon) icon.className = 'fas fa-bars fa-lg';
            const servicesSub = document.getElementById('mobileServicesSub');
            if (servicesSub) servicesSub.classList.add('hidden');
        });
    });

    document.addEventListener('click', (e) => {
        const header = document.querySelector('header');
        if (menu && !menu.classList.contains('hidden') && header && !header.contains(e.target)) {
            menu.classList.add('hidden');
            const icon = btn.querySelector('i');
            if (icon) icon.className = 'fas fa-bars fa-lg';
        }
    });
}

function setupMobileServices() {
    const toggle = document.getElementById('mobileServicesToggle');
    const sub = document.getElementById('mobileServicesSub');
    const arrow = document.getElementById('mobileServicesArrow');

    if (!toggle || !sub) return;

    toggle.addEventListener('click', () => {
        sub.classList.toggle('hidden');
        if (arrow) {
            arrow.style.transform = sub.classList.contains('hidden') ? 'rotate(0deg)' : 'rotate(180deg)';
        }
    });
}
