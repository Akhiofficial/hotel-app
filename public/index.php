<?php
require_once __DIR__ . '/../db.php';
$config = require __DIR__ . '/../config.php';

// Get all active rooms
$allRooms = $DB->query("SELECT * FROM rooms WHERE status='active' ORDER BY price ASC")->fetch_all(MYSQLI_ASSOC);
$premiumRooms = $DB->query("SELECT * FROM rooms WHERE status='active' ORDER BY price DESC LIMIT 4")->fetch_all(MYSQLI_ASSOC);

// Get room types for filtering
$roomTypes = [];
foreach ($allRooms as $room) {
    $type = $room['title'];
    if (!in_array($type, $roomTypes)) {
        $roomTypes[] = $type;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel reservation system - Book Your Perfect Stay</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <!-- Top Header -->
    <div class="top-header">
        <div class="container">
            <div class="top-header-left">
                <span><i class="fas fa-envelope"></i> reservations@bookinn.com</span>
                <span><i class="fas fa-phone"></i> +91 12345 67890</span>
            </div>
            <div class="top-header-right">
                <a href="check_bookings.php"><i class="fas fa-calendar-check"></i> View Bookings</a>
                <a href="#about">About Us</a>
                <a href="#services">Services</a>
                <a href="#contact">Contact</a>
            </div>
        </div>
    </div>

    <!-- Main Header -->
    <header class="main-header">
        <div class="container">
            <div class="logo">Hotel reservation system</div>
            <nav class="main-nav">
                <a href="#" class="active">Home</a>
                <a href="#rooms">Rooms</a>
                <a href="check_bookings.php"><i class="fas fa-list-alt"></i> My Bookings</a>
                <a href="#services">Services</a>
                <a href="#contact">Contact</a>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <h1>WELCOME TO Hotel reservation system<br>YOUR PERFECT STAY AWAITS</h1>
            <p>Experience luxury and comfort in the heart of the city. Book your room today and enjoy world-class
                hospitality.</p>
            <div class="search-form">
                <div class="search-field-group">
                    <label><i class="fas fa-calendar-alt"></i> Check In</label>
                    <input type="date" class="search-field" id="search_checkin" name="checkin" required>
                </div>
                <div class="search-field-group">
                    <label><i class="fas fa-calendar-check"></i> Check Out</label>
                    <input type="date" class="search-field" id="search_checkout" name="checkout" required>
                </div>
                <div class="search-field-group">
                    <label><i class="fas fa-users"></i> Guests</label>
                    <select class="search-field" id="search_guests" name="guests">
                        <option value="1">1 Guest</option>
                        <option value="2" selected>2 Guests</option>
                        <option value="3">3 Guests</option>
                        <option value="4">4 Guests</option>
                        <option value="5">5 Guests</option>
                        <option value="6">6+ Guests</option>
                    </select>
                </div>
                <div class="search-field-group">
                    <label><i class="fas fa-bed"></i> Room Type</label>
                    <select class="search-field" id="search_room_type" name="room_type">
                        <option value="">All Room Types</option>
                        <?php foreach ($roomTypes as $type): ?>
                            <option value="<?= esc($type) ?>"><?= esc($type) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="button" class="btn-search" id="btnSearch">
                    <i class="fas fa-search"></i> Check Availability
                </button>
            </div>
        </div>
    </section>

    <!-- Room Types Section -->
    <section class="property-types" id="rooms">
        <div class="container">
            <h2 class="section-title">Our Room Types</h2>
            <p class="section-subtitle">Choose from our variety of comfortable and luxurious rooms</p>
            <div class="property-types-grid">
                <?php
                $roomTypeCounts = [];
                foreach ($allRooms as $room) {
                    $type = $room['title'];
                    if (!isset($roomTypeCounts[$type])) {
                        $roomTypeCounts[$type] = 0;
                    }
                    $roomTypeCounts[$type]++;
                }
                $icons = ['fa-bed', 'fa-couch', 'fa-home', 'fa-building', 'fa-hotel', 'fa-key'];
                $iconIndex = 0;
                foreach ($roomTypeCounts as $type => $count):
                    ?>
                    <div class="property-type-card" data-room-type="<?= esc($type) ?>">
                        <div class="property-icon"><i class="fas <?= $icons[$iconIndex % count($icons)] ?>"></i></div>
                        <h3><?= esc($type) ?></h3>
                        <p><?= $count ?> Room<?= $count > 1 ? 's' : '' ?> Available</p>
                    </div>
                    <?php
                    $iconIndex++;
                endforeach;
                ?>
            </div>
        </div>
    </section>

    <!-- Premium Rooms -->
    <section class="premium-stays">
        <div class="container">
            <div class="section-header">
                <div>
                    <span class="section-tag">Premium Rooms</span>
                    <h2 class="section-title">Featured Rooms</h2>
                </div>
            </div>
            <div class="properties-grid">
                <?php foreach ($premiumRooms as $room):
                    // Fix image path for premium rooms too
                    $imagePath = '';
                    if (!empty($room['image'])) {
                        // Remove any duplicate uploads/ prefix
                        $imagePath = preg_replace('/^uploads\/uploads\//', 'uploads/', $room['image']);

                        // If it doesn't start with uploads/ or http, add uploads/
                        if (strpos($imagePath, 'uploads/') !== 0 && strpos($imagePath, 'http') !== 0) {
                            $imagePath = 'uploads/' . $imagePath;
                        }

                        // Check if file exists
                        $fullPath = __DIR__ . '/' . $imagePath;
                        if (!file_exists($fullPath)) {
                            // Use placeholder instead of broken image
                            $imagePath = 'https://via.placeholder.com/600x400/20B2AA/ffffff?text=' . urlencode($room['title'] ?? 'Room');
                        }
                    } else {
                        $imagePath = 'https://via.placeholder.com/600x400/20B2AA/ffffff?text=' . urlencode($room['title'] ?? 'Room');
                    }
                    ?>
                    <div class="property-card" data-room-type="<?= esc($room['title']) ?>">
                        <div class="property-image"
                            style="background-image: url('<?= esc($imagePath) ?>'); background-size: cover; background-position: center;">
                            <div class="room-badge">Premium</div>
                        </div>
                        <div class="property-info">
                            <div class="property-price">₹<?= number_format($room['price'], 2) ?> /night</div>
                            <h3><?= esc($room['title']) ?></h3>
                            <p class="property-code">Room Code: <?= esc($room['code']) ?></p>
                            <p class="property-details">
                                <i class="fas fa-users"></i> Capacity: <?= esc($room['capacity']) ?>
                                Guest<?= esc($room['capacity']) > 1 ? 's' : '' ?>
                                <?php if (!empty($room['quantity'])): ?>
                                    <span style="margin-left: 15px;">
                                        <i class="fas fa-bed"></i> Available: <?= esc($room['quantity']) ?>
                                        Room<?= esc($room['quantity']) > 1 ? 's' : '' ?>
                                    </span>
                                <?php endif; ?>
                            </p>
                            <p class="property-description">
                                <?= esc($room['description'] ?? 'Comfortable and well-appointed room with modern amenities.') ?>
                            </p>
                            <div class="property-rating">
                                <?php for ($i = 0; $i < 5; $i++): ?>
                                    <i class="fas fa-star"></i>
                                <?php endfor; ?>
                                <span class="rating-text">5.0</span>
                            </div>
                            <button class="bookBtn btn-book-now" data-room='<?= json_encode($room) ?>'>
                                <i class="fas fa-calendar-check"></i> Book Now
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- All Rooms Section -->
    <section class="all-hotels" id="all-rooms">
        <div class="container">
            <div class="section-header">
                <div>
                    <span class="section-tag">All Rooms</span>
                    <h2 class="section-title">Explore All Our Rooms</h2>
                </div>
            </div>

            <div class="properties-grid" id="roomsGrid">
                <?php foreach ($allRooms as $room):
                    // Fix image path
                    $imagePath = '';
                    if (!empty($room['image'])) {
                        // Remove any duplicate uploads/ prefix
                        $imagePath = preg_replace('/^uploads\/uploads\//', 'uploads/', $room['image']);

                        // If it doesn't start with uploads/ or http, add uploads/
                        if (strpos($imagePath, 'uploads/') !== 0 && strpos($imagePath, 'http') !== 0) {
                            $imagePath = 'uploads/' . $imagePath;
                        }

                        // Check if file exists
                        $fullPath = __DIR__ . '/' . $imagePath;
                        if (!file_exists($fullPath)) {
                            // Use placeholder instead of broken image
                            $imagePath = 'https://via.placeholder.com/600x400/20B2AA/ffffff?text=' . urlencode($room['title'] ?? 'Room');
                        }
                    } else {
                        $imagePath = 'https://via.placeholder.com/600x400/20B2AA/ffffff?text=' . urlencode($room['title'] ?? 'Room');
                    }
                    ?>
                    <div class="property-card" data-room-type="<?= esc($room['title']) ?>"
                        data-room-id="<?= esc($room['id']) ?>">
                        <div class="property-image"
                            style="background-image: url('<?= esc($imagePath) ?>'); background-size: cover; background-position: center;">
                        </div>
                        <div class="property-info">
                            <div class="property-price">₹<?= number_format($room['price'], 2) ?> /night</div>
                            <h3><?= esc($room['title']) ?></h3>
                            <p class="property-code">Room Code: <?= esc($room['code']) ?></p>
                            <p class="property-details">
                                <i class="fas fa-users"></i> Capacity: <?= esc($room['capacity']) ?>
                                Guest<?= esc($room['capacity']) > 1 ? 's' : '' ?>
                                <?php if (!empty($room['quantity'])): ?>
                                    <span style="margin-left: 15px;">
                                        <i class="fas fa-bed"></i> Available: <?= esc($room['quantity']) ?>
                                        Room<?= esc($room['quantity']) > 1 ? 's' : '' ?>
                                    </span>
                                <?php endif; ?>
                            </p>
                            <p class="property-description">
                                <?= esc($room['description'] ?? 'Comfortable and well-appointed room with modern amenities.') ?>
                            </p>
                            <div class="property-rating">
                                <?php for ($i = 0; $i < 5; $i++): ?>
                                    <i class="fas fa-star"></i>
                                <?php endfor; ?>
                                <span class="rating-text">5.0</span>
                            </div>
                            <button class="bookBtn btn-book-now" data-room='<?= json_encode($room) ?>'>
                                <i class="fas fa-calendar-check"></i> Book Now
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section class="services-section" id="services">
        <div class="container">
            <div class="section-header">
                <div>
                    <span class="section-tag">Our Services</span>
                    <h2 class="section-title">Hotel Services & Amenities</h2>
                </div>
            </div>
            <div class="services-carousel">
                <div class="service-card">
                    <div class="service-image"
                        style="background-image: url('https://images.unsplash.com/photo-1534438327276-14e5300c3a48?w=600')">
                    </div>
                    <div class="service-overlay">
                        <h3>Fitness Center</h3>
                        <p>24/7 Gym Access</p>
                    </div>
                </div>
                <div class="service-card">
                    <div class="service-image"
                        style="background-image: url('https://images.unsplash.com/photo-1571896349842-33c89424de2d?w=600')">
                    </div>
                    <div class="service-overlay">
                        <h3>Swimming Pool</h3>
                        <p>Outdoor Pool & Spa</p>
                    </div>
                </div>
                <div class="service-card">
                    <div class="service-image"
                        style="background-image: url('https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?w=600')">
                    </div>
                    <div class="service-overlay">
                        <h3>Restaurant</h3>
                        <p>Fine Dining Experience</p>
                    </div>
                </div>
                <div class="service-card">
                    <div class="service-image"
                        style="background-image: url('https://images.unsplash.com/photo-1502920917128-1aa500764cbd?w=600')">
                    </div>
                    <div class="service-overlay">
                        <h3>Airport Transfer</h3>
                        <p>Pick Up & Drop Service</p>
                    </div>
                </div>
                <div class="service-card">
                    <div class="service-image"
                        style="background-image: url('https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=600')">
                    </div>
                    <div class="service-overlay">
                        <h3>Parking</h3>
                        <p>Free Valet Parking</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section class="video-section" id="about">
        <div class="container">
            <div class="video-wrapper">
                <div class="video-placeholder">
                    <i class="fas fa-play-circle"></i>
                    <p>Watch Our Hotel Tour</p>
                </div>
            </div>
            <div class="stats-grid">
                <div class="stat-item">
                    <h3>1000+</h3>
                    <p>Happy Guests</p>
                </div>
                <div class="stat-item">
                    <h3>15+</h3>
                    <p>Years Experience</p>
                </div>
                <div class="stat-item">
                    <h3><?= count($allRooms) ?>+</h3>
                    <p>Rooms Available</p>
                </div>
                <div class="stat-item">
                    <h3>24/7</h3>
                    <p>Customer Support</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials -->
    <section class="testimonials">
        <div class="container">
            <div class="section-header">
                <div>
                    <span class="section-tag">Testimonials</span>
                    <h2 class="section-title">What Our Guests Say</h2>
                </div>
            </div>
            <div class="testimonial-carousel">
                <button class="carousel-btn prev"><i class="fas fa-chevron-left"></i></button>
                <div class="testimonial-card">
                    <i class="fas fa-quote-left quote-icon"></i>
                    <p class="testimonial-text">Staying at BOOKINN Hotel was an absolute delight! The rooms were
                        spotless, the staff was incredibly friendly, and the service was exceptional. The seamless
                        check-in process and the delicious breakfast made my trip even more memorable. I can't wait to
                        come back!</p>
                    <h4>Emily Watson</h4>
                    <div class="property-rating">
                        <?php for ($i = 0; $i < 5; $i++): ?>
                            <i class="fas fa-star"></i>
                        <?php endfor; ?>
                    </div>
                </div>
                <button class="carousel-btn next"><i class="fas fa-chevron-right"></i></button>
            </div>
        </div>
    </section>

    <!-- CTA Banner -->
    <section class="cta-banner" id="contact">
        <div class="container">
            <span class="section-tag">Contact Us</span>
            <h2>Have Questions? We're Here to Help</h2>
            <p>Reach out to us for reservations, inquiries, or special requests</p>
            <button class="btn-cta">Contact Us <i class="fas fa-arrow-right"></i></button>
        </div>
    </section>

    <!-- Newsletter -->
    <section class="newsletter">
        <div class="container">
            <span class="section-tag">Newsletter</span>
            <h2>Subscribe To Our Newsletter</h2>
            <p>Get exclusive offers and updates about our hotel</p>
            <form class="newsletter-form">
                <input type="email" placeholder="Enter your email" required>
                <button type="submit" class="btn-newsletter">Subscribe <i class="fas fa-arrow-right"></i></button>
            </form>
        </div>
    </section>

    <!-- Footer -->
    <footer class="main-footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-col">
                    <h3 class="footer-logo">BOOKINN HOTEL</h3>
                    <p>Experience luxury and comfort in the heart of the city. We're committed to providing exceptional
                        hospitality and unforgettable stays for all our guests.</p>
                    <div class="social-icons">
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-facebook"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-linkedin"></i></a>
                        <a href="#"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
                <div class="footer-col">
                    <h4>Services</h4>
                    <ul>
                        <li><a href="#rooms">Room Booking</a></li>
                        <li><a href="#services">Hotel Services</a></li>
                        <li><a href="#services">Restaurant</a></li>
                        <li><a href="#services">Spa & Wellness</a></li>
                        <li><a href="#services">Event Hall</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="#about">About Us</a></li>
                        <li><a href="#rooms">Our Rooms</a></li>
                        <li><a href="check_bookings.php">View Bookings</a></li>
                        <li><a href="#services">Amenities</a></li>
                        <li><a href="#contact">Contact Us</a></li>
                        <li><a href="../admin/login.php">Admin Login</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Contact Us</h4>
                    <p><i class="fas fa-envelope"></i> reservations@bookinn.com</p>
                    <p><i class="fas fa-phone"></i> +91 12345 67890</p>
                    <p><i class="fas fa-map-marker-alt"></i> City Center, Main Street</p>
                    <p class="footer-stat">Open 24/7 for Reservations</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>Copyright © 2024 BOOKINN Hotel. All Rights Reserved.</p>
                <div>
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms & Conditions</a>
                </div>
            </div>
        </div>
    </footer>

    <?php include __DIR__ . '/booking_modal.php'; ?>
    <script src="main.js?v=<?= time() ?>"></script>
    <script src="assets.js?v=<?= time() ?>"></script>
</body>

</html>