<?php
// Add to top of index.php, cart.php, checkout.php, etc.
session_start();
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin') {
    header('Location: pages/admin/dashboard.php');
    exit();
}
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'director') {
    header('Location: pages/campus-director/dashboard.php');
    exit();
}
?>
<?php
// about.php

require_once 'includes/config.php';
require_once 'includes/functions.php';

$page_title = "About Us";
$body_class = "about-page";

require_once 'includes/header.php';
?>

<div class="small container about-page">
    <div class="about-header">
        <h1>About BU Labels</h1>
        <p class="subtitle">Official University Merchandise Store</p>
    </div>
    
    <div class="about-content">
        <div class="about-section mission">
            <div class="section-image">
                <img src="<?php echo SITE_URL; ?>assets/images/bunique.PNG" alt="BU Labels">
            </div>
            <div class="section-content">
                <h2>Our Mission</h2>
                <p>To provide high-quality, officially licensed university merchandise that fosters school spirit, pride, and a sense of community among students, alumni, faculty, and staff.</p>
                <p>BU Labels is more than just a merchandise store - it's a platform for showcasing university pride and creating lasting memories of the college experience.</p>
            </div>
        </div>
        
        <div class="about-section values">
            <h2>Our Values</h2>
            <div class="values-grid">
                <div class="value-card">
                    <div class="value-icon">
                        <i class="ri-star-line"></i>
                    </div>
                    <h3>Quality</h3>
                    <p>We use premium materials and craftsmanship to ensure our products last and represent our university with pride.</p>
                </div>
                
                <div class="value-card">
                    <div class="value-icon">
                        <i class="ri-community-line"></i>
                    </div>
                    <h3>Community</h3>
                    <p>We're proud to be part of the university community and support campus activities and organizations.</p>
                </div>
                
                <div class="value-card">
                    <div class="value-icon">
                        <i class="ri-leaf-line"></i>
                    </div>
                    <h3>Sustainability</h3>
                    <p>We're committed to eco-friendly practices and materials where possible.</p>
                </div>
                
                <div class="value-card">
                    <div class="value-icon">
                        <i class="ri-customer-service-2-line"></i>
                    </div>
                    <h3>Service</h3>
                    <p>We provide excellent customer service and support to our university community.</p>
                </div>
            </div>
        </div>
        
        <div class="about-section team">
            <h2>Meet Our Team</h2>
            <div class="team-grid">
                <div class="team-member">
                    <img src="<?php echo SITE_URL; ?>assets/images/director-avatar.png" alt="Business Manager">
                    <h3>Business Manager</h3>
                    <p class="role">CSC Business Manager</p>
                    <p>Oversees operations and ensures quality service across all campuses.</p>
                </div>
                
                <div class="team-member">
                    <img src="<?php echo SITE_URL; ?>assets/images/admin-avatar.png" alt="Admin Team">
                    <h3>Admin Team</h3>
                    <p class="role">CSC Admin Staff</p>
                    <p>Manages inventory, orders, and customer service operations.</p>
                </div>
                
                <div class="team-member">
                    <img src="<?php echo SITE_URL; ?>assets/images/team-avatar.png" alt="Campus Reps">
                    <h3>Campus Representatives</h3>
                    <p class="role">CSC Members</p>
                    <p>Handles distribution and pickup at each campus location.</p>
                </div>
            </div>
        </div>
        
        <div class="about-section campus-locations">
            <h2>Campus Locations</h2>
            <div class="locations-grid">
                <div class="location-card">
                    <h3>BU Main Campus</h3>
                    <p><i class="ri-map-pin-line"></i> CSC Office, Main Building</p>
                    <p><i class="ri-time-line"></i> Mon-Fri: 8:00 AM - 5:00 PM</p>
                    <p><i class="ri-phone-line"></i> (123) 456-7890</p>
                </div>
                
                <div class="location-card">
                    <h3>BUIDEA</h3>
                    <p><i class="ri-map-pin-line"></i> CSC Office, Admin Building</p>
                    <p><i class="ri-time-line"></i> Mon-Fri: 9:00 AM - 4:00 PM</p>
                    <p><i class="ri-phone-line"></i> (123) 456-7891</p>
                </div>
                
                <div class="location-card">
                    <h3>Polangui Campus</h3>
                    <p><i class="ri-map-pin-line"></i> Student Center, Room 201</p>
                    <p><i class="ri-time-line"></i> Mon-Fri: 8:30 AM - 4:30 PM</p>
                    <p><i class="ri-phone-line"></i> (123) 456-7892</p>
                </div>
                
                <div class="location-card">
                    <h3>Guinobatan Campus</h3>
                    <p><i class="ri-map-pin-line"></i> CSC Office, Ground Floor</p>
                    <p><i class="ri-time-line"></i> Mon-Fri: 9:00 AM - 5:00 PM</p>
                    <p><i class="ri-phone-line"></i> (123) 456-7893</p>
                </div>
                
                <div class="location-card">
                    <h3>Gubat Campus</h3>
                    <p><i class="ri-map-pin-line"></i> Student Affairs Office</p>
                    <p><i class="ri-time-line"></i> Mon-Fri: 8:00 AM - 4:00 PM</p>
                    <p><i class="ri-phone-line"></i> (123) 456-7894</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>