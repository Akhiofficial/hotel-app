// Mobile Sidebar Toggle Functionality
document.addEventListener('DOMContentLoaded', function() {
    const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
    const adminSidebar = document.getElementById('adminSidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    
    if (mobileSidebarToggle && adminSidebar && sidebarOverlay) {
        // Toggle sidebar on button click
        mobileSidebarToggle.addEventListener('click', function() {
            adminSidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
        });
        
        // Close sidebar when clicking overlay
        sidebarOverlay.addEventListener('click', function() {
            adminSidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
        });
        
        // Close sidebar when clicking a nav item on mobile
        const navItems = adminSidebar.querySelectorAll('.nav-item');
        navItems.forEach(item => {
            item.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    adminSidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                }
            });
        });
    }
});