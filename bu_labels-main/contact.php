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
// contact.php

require_once 'includes/config.php';
require_once 'includes/functions.php';

$page_title = "Contact Us";
$body_class = "contact-page";

// Handle contact form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = escape($_POST['name']);
    $email = escape($_POST['email']);
    $subject = escape($_POST['subject']);
    $message = escape($_POST['message']);
    
    // Basic validation
    $errors = [];
    
    if (empty($name)) $errors[] = "Name is required";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required";
    if (empty($subject)) $errors[] = "Subject is required";
    if (empty($message)) $errors[] = "Message is required";
    
    if (empty($errors)) {
        // In a real application, you would send an email here
        // For demo purposes, we'll just save to database
        
        $conn = getDBConnection();
        $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $email, $subject, $message);
        
        if ($stmt->execute()) {
            $success = "Thank you for your message! We'll get back to you within 24-48 hours.";
        } else {
            $errors[] = "Failed to send message. Please try again.";
        }
    }
}

require_once 'includes/header.php';
?>

<div class="small container contact-page">
    <div class="contact-header">
        <h1>Contact Us</h1>
        <p class="subtitle">Have questions? We're here to help!</p>
    </div>
    
    <div class="contact-content">
        <div class="row">
            <!-- Contact Information -->
            <div class="col-2">
                <div class="contact-info-card">
                    <h2>Get in Touch</h2>
                    <p>Reach out to us through any of the following channels:</p>
                    
                    <div class="contact-methods">
                        <div class="contact-method">
                            <div class="method-icon">
                                <i class="ri-phone-line"></i>
                            </div>
                            <div class="method-info">
                                <h3>Phone</h3>
                                <p>(123) 456-7890</p>
                                <small>Monday-Friday, 8:00 AM - 5:00 PM</small>
                            </div>
                        </div>
                        
                        <div class="contact-method">
                            <div class="method-icon">
                                <i class="ri-mail-line"></i>
                            </div>
                            <div class="method-info">
                                <h3>Email</h3>
                                <p>support@bulabels.com</p>
                                <small>We respond within 24-48 hours</small>
                            </div>
                        </div>
                        
                        <div class="contact-method">
                            <div class="method-icon">
                                <i class="ri-map-pin-line"></i>
                            </div>
                            <div class="method-info">
                                <h3>Main Office</h3>
                                <p>CSC Office, Main Building</p>
                                <p>University Main Campus</p>
                                <small>Open Monday-Friday, 8:00 AM - 5:00 PM</small>
                            </div>
                        </div>
                        
                        <div class="contact-method">
                            <div class="method-icon">
                                <i class="ri-time-line"></i>
                            </div>
                            <div class="method-info">
                                <h3>Business Hours</h3>
                                <p>Monday - Friday: 8:00 AM - 5:00 PM</p>
                                <p>Saturday: 9:00 AM - 12:00 PM</p>
                                <p>Sunday: Closed</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="emergency-contact">
                        <h4><i class="ri-alert-line"></i> For Order Issues</h4>
                        <p>If you have urgent order-related issues, please call:</p>
                        <p class="emergency-phone">(123) 456-7890 ext. 101</p>
                    </div>
                </div>
                
                <div class="faq-preview">
                    <h3>Frequently Asked Questions</h3>
                    <div class="faq-item">
                        <h4>How long does pickup take?</h4>
                        <p>Orders are typically ready for pickup within 3-5 business days after payment confirmation.</p>
                    </div>
                    <div class="faq-item">
                        <h4>What payment methods do you accept?</h4>
                        <p>We accept GCash, Cash on Delivery (campus pickup), and bank transfer.</p>
                    </div>
                    <div class="faq-item">
                        <h4>Can I return or exchange items?</h4>
                        <p>Yes, unworn items with tags can be returned within 7 days of pickup. See our Return Policy for details.</p>
                    </div>
                    <a href="#" class="view-all-faq">View All FAQs</a>
                </div>
            </div>
            
            <!-- Contact Form -->
            <div class="col-2">
                <div class="contact-form-card">
                    <h2>Send us a Message</h2>
                    
                    <?php if (isset($errors) && !empty($errors)): ?>
                    <div class="alert alert-error">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                            <li><i class="ri-error-warning-line"></i> <?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($success)): ?>
                    <div class="alert alert-success">
                        <i class="ri-checkbox-circle-line"></i>
                        <?php echo $success; ?>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" class="contact-form">
                        <div class="form-group">
                            <label for="name">Full Name *</label>
                            <input type="text" id="name" name="name" required 
                                   value="<?php echo isset($_POST['name']) ? sanitize($_POST['name']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" required 
                                   value="<?php echo isset($_POST['email']) ? sanitize($_POST['email']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="subject">Subject *</label>
                            <select id="subject" name="subject" required>
                                <option value="">Select a subject</option>
                                <option value="order" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'order') ? 'selected' : ''; ?>>Order Inquiry</option>
                                <option value="product" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'product') ? 'selected' : ''; ?>>Product Question</option>
                                <option value="shipping" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'shipping') ? 'selected' : ''; ?>>Shipping/Pickup</option>
                                <option value="payment" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'payment') ? 'selected' : ''; ?>>Payment Issue</option>
                                <option value="return" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'return') ? 'selected' : ''; ?>>Return/Exchange</option>
                                <option value="other" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="message">Message *</label>
                            <textarea id="message" name="message" rows="6" required 
                                      placeholder="Please provide details about your inquiry..."><?php echo isset($_POST['message']) ? sanitize($_POST['message']) : ''; ?></textarea>
                        </div>
                        
                        <div class="form-group checkbox">
                            <input type="checkbox" id="newsletter" name="newsletter" checked>
                            <label for="newsletter">Subscribe to our newsletter for updates and promotions</label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="ri-send-plane-line"></i> Send Message
                        </button>
                    </form>
                </div>
                
                <div class="social-contact">
                    <h3>Connect With Us</h3>
                    <p>Follow us on social media for updates and announcements:</p>
                    
                    <div class="social-links">
                        <a href="#" class="social-link facebook">
                            <i class="fab fa-facebook-f"></i>
                            <span>Facebook</span>
                        </a>
                        <a href="#" class="social-link instagram">
                            <i class="fab fa-instagram"></i>
                            <span>Instagram</span>
                        </a>
                        <a href="#" class="social-link twitter">
                            <i class="fab fa-twitter"></i>
                            <span>Twitter</span>
                        </a>
                        <a href="#" class="social-link youtube">
                            <i class="fab fa-youtube"></i>
                            <span>YouTube</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>