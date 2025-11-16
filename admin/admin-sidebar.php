<div class="sidebar-logo">
    <i class="fas fa-hotel"></i>
    <h2>Hotel Dashboard</h2>
</div>

<nav class="sidebar-nav">
    <a href="dashboard.php" class="nav-item <?=basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''?>">
        <i class="fas fa-home"></i>
        <span>Dashboard</span>
    </a>
    <a href="bookings.php" class="nav-item <?=basename($_SERVER['PHP_SELF']) == 'bookings.php' ? 'active' : ''?>">
        <i class="fas fa-calendar-check"></i>
        <span>Bookings</span>
    </a>
    <a href="create-booking.php" class="nav-item <?=basename($_SERVER['PHP_SELF']) == 'create-booking.php' ? 'active' : ''?>">
        <i class="fas fa-plus-circle"></i>
        <span>New Booking</span>
    </a>
    <a href="rooms.php" class="nav-item <?=basename($_SERVER['PHP_SELF']) == 'rooms.php' ? 'active' : ''?>">
        <i class="fas fa-bed"></i>
        <span>Rooms</span>
    </a>
    <a href="bookings.php" class="nav-item">
        <i class="fas fa-users"></i>
        <span>Guests</span>
    </a>
    <a href="services.php" class="nav-item <?=basename($_SERVER['PHP_SELF']) == 'services.php' ? 'active' : ''?>">
        <i class="fas fa-concierge-bell"></i>
        <span>Services</span>
    </a>
    <a href="settings.php" class="nav-item <?=basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''?>">
        <i class="fas fa-cog"></i>
        <span>Settings</span>
    </a>
    <a href="../public/index.php" target="_blank" class="nav-item">
        <i class="fas fa-external-link-alt"></i>
        <span>View Website</span>
    </a>
</nav>

<div class="sidebar-footer">
    <div class="sidebar-user">
        <i class="fas fa-user-circle"></i>
        <span><?=esc($_SESSION['admin'])?></span>
    </div>
    <a href="logout.php" class="btn-logout-sidebar">
        <i class="fas fa-sign-out-alt"></i>
        <span>Logout</span>
    </a>
</div>



