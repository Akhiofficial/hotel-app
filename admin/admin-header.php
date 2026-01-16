<header class="admin-header">
    <div class="header-content">
        <div class="header-left">
            <button id="sidebarToggle" class="sidebar-toggle-btn">
                <i class="fas fa-bars"></i>
            </button>
            <h2><i class="fas fa-hotel"></i> Hotel Admin</h2>
        </div>
        <div class="header-right">
            <span class="admin-name"><i class="fas fa-user"></i> <?= esc($_SESSION['admin']) ?></span>
            <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
</header>