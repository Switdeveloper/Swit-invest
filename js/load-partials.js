// Load partials (header/footer) into pages
document.addEventListener('DOMContentLoaded', async () => {
    // Load header
    const headerContainer = document.getElementById('header-container');
    if (headerContainer) {
        try {
            const response = await fetch('partials/header.html');
            const headerHTML = await response.text();
            headerContainer.innerHTML = headerHTML;
            
            // Add mobile menu toggle after header loads
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const mobileMenu = document.getElementById('mobileMenu');
            if (mobileMenuBtn && mobileMenu) {
                mobileMenuBtn.addEventListener('click', () => {
                    mobileMenu.classList.toggle('hidden');
                });
            }
        } catch (e) {
            console.log('Header loaded inline');
        }
    }
    
    // Load footer
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