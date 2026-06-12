// Google Analytics placeholder - uncomment and add your GA4 ID to enable
// window.dataLayer = window.dataLayer || [];
// function gtag(){dataLayer.push(arguments);}
// gtag('js', new Date());
// gtag('config', 'G-XXXXXXXXXX');
// const gaScript = document.createElement('script');
// gaScript.src = 'https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX';
// gaScript.async = true;
// document.head.appendChild(gaScript);

(function() {
    function addCookieBanner() {
        if (localStorage.getItem('cookieConsent')) return;
        const banner = document.createElement('div');
        banner.id = 'cookieBanner';
        banner.style.cssText = 'position:fixed;bottom:0;left:0;right:0;z-index:9999;background:#0d152e;color:#fff;padding:16px 24px;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;font-size:14px;font-family:Inter,sans-serif;box-shadow:0 -4px 20px rgba(0,0,0,0.2)';
        banner.innerHTML = '<span>We use cookies to improve your experience. By using this site you agree to our <a href="privacy.html" style="color:#c91414;text-decoration:underline">Privacy Policy</a>.</span><div style="display:flex;gap:8px"><button id="cookieAccept" style="background:#c91414;color:#fff;border:none;padding:8px 20px;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px">Accept</button><button id="cookieDecline" style="background:transparent;color:#9ca3af;border:1px solid #4b5563;padding:8px 20px;border-radius:8px;cursor:pointer;font-size:13px">Decline</button></div>';
        document.body.appendChild(banner);
        document.getElementById('cookieAccept').addEventListener('click', () => { localStorage.setItem('cookieConsent', 'accepted'); banner.remove(); });
        document.getElementById('cookieDecline').addEventListener('click', () => { localStorage.setItem('cookieConsent', 'declined'); banner.remove(); });
    }

    function addBackToTop() {
        const btn = document.createElement('button');
        btn.id = 'backToTop';
        btn.innerHTML = '<i class="fas fa-arrow-up"></i>';
        btn.style.cssText = 'position:fixed;bottom:90px;right:20px;z-index:9998;width:44px;height:44px;border-radius:50%;background:#c91414;color:#fff;border:none;font-size:16px;cursor:pointer;box-shadow:0 4px 15px rgba(201,20,20,0.3);transition:all 0.3s;opacity:0;transform:translateY(20px);pointer-events:none';
        document.body.appendChild(btn);
        window.addEventListener('scroll', () => {
            if (window.scrollY > 400) { btn.style.opacity = '1'; btn.style.transform = 'translateY(0)'; btn.style.pointerEvents = 'auto'; }
            else { btn.style.opacity = '0'; btn.style.transform = 'translateY(20px)'; btn.style.pointerEvents = 'none'; }
        });
        btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
    }

    function addChatWidget() {
        const btn = document.createElement('button');
        btn.id = 'chatWidgetBtn';
        btn.innerHTML = '<i class="fas fa-comment-dots" style="font-size:22px"></i>';
        btn.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:9997;width:56px;height:56px;border-radius:50%;background:#c91414;color:#fff;border:none;cursor:pointer;box-shadow:0 4px 20px rgba(201,20,20,0.4);transition:transform 0.2s;display:flex;align-items:center;justify-content:center';
        btn.onmouseenter = () => btn.style.transform = 'scale(1.1)';
        btn.onmouseleave = () => btn.style.transform = 'scale(1)';
        btn.onclick = () => window.open('ai-chat.html', '_blank');
        document.body.appendChild(btn);
    }

    document.addEventListener('DOMContentLoaded', () => {
        addCookieBanner();
        addBackToTop();
        addChatWidget();
    });
})();

document.addEventListener('DOMContentLoaded', async () => {
    const headerContainer = document.getElementById('header-container');
    if (headerContainer) {
        try {
            const response = await fetch('partials/header.html');
            const headerHTML = await response.text();
            headerContainer.innerHTML = headerHTML;
            setupDesktopDropdown();
            setupDesktopTools();
            setupMobileMenu();
            setupMobileServices();
            setupMobileTools();
        } catch (e) {
            setupDesktopDropdown();
            setupDesktopTools();
            setupMobileMenu();
            setupMobileServices();
            setupMobileTools();
        }
    }

    const footerContainer = document.getElementById('footer-container');
    if (footerContainer) {
        try {
            const response = await fetch('partials/footer.html');
            const footerHTML = await response.text();
            footerContainer.innerHTML = footerHTML;
        } catch (e) {}
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
        if (arrow) arrow.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)';
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
        if (icon) icon.className = menu.classList.contains('hidden') ? 'fas fa-bars fa-lg' : 'fas fa-times fa-lg';
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
        if (arrow) arrow.style.transform = sub.classList.contains('hidden') ? 'rotate(0deg)' : 'rotate(180deg)';
    });
}

function setupDesktopTools() {
    const dropdownBtn = document.getElementById('toolsDropdownBtn');
    const menu = document.getElementById('toolsMenu');
    const arrow = document.getElementById('toolsArrow');
    if (!dropdownBtn || !menu) return;
    dropdownBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = !menu.classList.contains('hidden');
        menu.classList.toggle('hidden');
        if (arrow) arrow.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)';
    });
    document.addEventListener('click', (e) => {
        const wrapper = document.getElementById('desktopToolsDropdown');
        if (wrapper && !wrapper.contains(e.target) && !menu.classList.contains('hidden')) {
            menu.classList.add('hidden');
            if (arrow) arrow.style.transform = 'rotate(0deg)';
        }
    });
}

function setupMobileTools() {
    const toggle = document.getElementById('mobileToolsToggle');
    const sub = document.getElementById('mobileToolsSub');
    const arrow = document.getElementById('mobileToolsArrow');
    if (!toggle || !sub) return;
    toggle.addEventListener('click', () => {
        sub.classList.toggle('hidden');
        if (arrow) arrow.style.transform = sub.classList.contains('hidden') ? 'rotate(0deg)' : 'rotate(180deg)';
    });
}
