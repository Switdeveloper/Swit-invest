document.addEventListener('DOMContentLoaded', async () => {
    const headerContainer = document.getElementById('header-container');
    if (headerContainer) {
        try {
            const response = await fetch('partials/header.html');
            const headerHTML = await response.text();
            headerContainer.innerHTML = headerHTML;

            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const mobileMenu = document.getElementById('mobileMenu');
            const mobileMenuClose = document.getElementById('mobileMenuClose');
            const mobileNavLinks = mobileMenu ? mobileMenu.querySelectorAll('a[href]:not([href="#"])') : [];

            const toggleMenu = (show) => {
                if (!mobileMenu || !mobileMenuBtn) return;
                if (show === undefined) {
                    mobileMenu.classList.toggle('hidden');
                } else if (show) {
                    mobileMenu.classList.remove('hidden');
                } else {
                    mobileMenu.classList.add('hidden');
                }
                const icon = mobileMenuBtn.querySelector('i');
                if (icon) {
                    if (mobileMenu.classList.contains('hidden')) {
                        icon.className = 'fas fa-bars fa-2x';
                    } else {
                        icon.className = 'fas fa-times fa-2x';
                    }
                }
            };

            if (mobileMenuBtn && mobileMenu) {
                mobileMenuBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    toggleMenu();
                });
            }

            if (mobileMenuClose && mobileMenu) {
                mobileMenuClose.addEventListener('click', () => {
                    toggleMenu(false);
                });
            }

            mobileNavLinks.forEach(link => {
                link.addEventListener('click', () => toggleMenu(false));
            });

            document.addEventListener('click', (e) => {
                if (mobileMenu && !mobileMenu.classList.contains('hidden')) {
                    const header = document.querySelector('header');
                    if (header && !header.contains(e.target)) {
                        toggleMenu(false);
                    }
                }
            });
        } catch (e) {
            console.log('Header loaded inline');
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
