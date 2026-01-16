// Admin Sidebar Toggle Logic
document.addEventListener('DOMContentLoaded', function () {
    const toggleBtn = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.admin-sidebar');
    const content = document.querySelector('.admin-content');

    // Create overlay
    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    document.body.appendChild(overlay);

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');

            // Force styles via JS to ensure visibility
            if (sidebar.classList.contains('active')) {
                sidebar.style.transform = 'translateX(0)';
                sidebar.style.position = 'fixed';
                sidebar.style.zIndex = '99999';
                sidebar.style.display = 'block';
                sidebar.style.left = '0';
                sidebar.style.top = '0';
                sidebar.style.height = '100vh';
                sidebar.style.width = '260px';
                sidebar.style.backgroundColor = '#1A4D2E'; // primary-dark
                document.body.style.overflow = 'hidden';
            } else {
                sidebar.style.transform = '';
                sidebar.style.position = '';
                sidebar.style.zIndex = '';
                sidebar.style.display = '';
                sidebar.style.width = '';
                document.body.style.overflow = '';
            }
        });
    }

    // Close when clicking overlay
    overlay.addEventListener('click', function () {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');

        // Reset styles
        sidebar.style.transform = '';
        sidebar.style.position = '';
        sidebar.style.zIndex = '';
        sidebar.style.display = '';
        document.body.style.overflow = '';
    });

    // Close on route change (optional, but good for UX)
    const links = sidebar.querySelectorAll('a');
    links.forEach(link => {
        link.addEventListener('click', () => {
            // Only close if it's mobile (width check)
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    });
});