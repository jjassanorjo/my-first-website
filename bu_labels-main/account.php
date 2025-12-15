<?php
// account.php

require_once 'includes/config.php';
require_once 'includes/functions.php';

$page_title = "My Account";
$body_class = "account-page";

$action = isset($_GET['action']) ? $_GET['action'] : '';
$login = isset($_GET['login']) ? $_GET['login'] : '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = escape($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);
    
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT id, name, email, password, role, campus FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user'] = $user;
            $_SESSION['user_role'] = $user['role'];
            
            // Set remember me cookie
            // In the login section (around line 78):
if ($remember) {
    $token = bin2hex(random_bytes(32));
    $expiry = time() + (30 * 24 * 60 * 60); // 30 days
    $expiry_date = date('Y-m-d H:i:s', $expiry); // Store in variable
    
    $update_stmt = $conn->prepare("UPDATE users SET remember_token = ?, token_expiry = ? WHERE id = ?");
    $update_stmt->bind_param("ssi", $token, $expiry_date, $user['id']); // Use variable
    $update_stmt->execute();
    
    setcookie('remember_token', $token, $expiry, '/');
}
            
            // Redirect to intended page or dashboard
            if (isset($_SESSION['redirect_url'])) {
                $redirect_url = $_SESSION['redirect_url'];
                unset($_SESSION['redirect_url']);
                redirect($redirect_url);
            } else {
                if ($user['role'] == 'admin') {
                    redirect('pages/admin/dashboard.php');
                } elseif ($user['role'] == 'director') {
                    redirect('pages/campus-director/dashboard.php');
                } else {
                    redirect('account.php?action=dashboard');
                }
            }
        } else {
            $login_error = "Invalid email or password";
        }
    } else {
        $login_error = "Invalid email or password";
    }
}

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $name = escape($_POST['name']);
    $email = escape($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $campus = escape($_POST['campus']);
    
    // Validation
    $errors = [];
    
    if (empty($name)) $errors[] = "Name is required";
    if (empty($email)) $errors[] = "Email is required";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
    if (empty($password)) $errors[] = "Password is required";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match";
    if (empty($campus)) $errors[] = "Please select your campus";
    
    // Check if email exists
    if (empty($errors)) {
        $conn = getDBConnection();
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            $errors[] = "Email already registered";
        }
    }
    
    if (empty($errors)) {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user
        $insert_stmt = $conn->prepare("INSERT INTO users (name, email, password, campus) VALUES (?, ?, ?, ?)");
        $insert_stmt->bind_param("ssss", $name, $email, $hashed_password, $campus);
        
        if ($insert_stmt->execute()) {
            $success = "Registration successful! Please login.";
            $show_login = true;
        } else {
            $errors[] = "Registration failed. Please try again.";
        }
    }
}

// Check if user is logged in
if (isLoggedIn()) {
    $user = getCurrentUser();
    
    // Handle profile update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
        $name = escape($_POST['name']);
        $email = escape($_POST['email']);
        $campus = escape($_POST['campus']);
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        
        $conn = getDBConnection();
        
        // Check if email is being changed and if it's already taken
        if ($email != $user['email']) {
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check_stmt->bind_param("si", $email, $user['id']);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows > 0) {
                $profile_error = "Email already taken";
            }
        }
        
        // Update password if provided
        if (!empty($new_password)) {
            if (empty($current_password)) {
                $profile_error = "Current password is required to change password";
            } elseif (!password_verify($current_password, $user['password'])) {
                $profile_error = "Current password is incorrect";
            } elseif (strlen($new_password) < 6) {
                $profile_error = "New password must be at least 6 characters";
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update_stmt->bind_param("si", $hashed_password, $user['id']);
                $update_stmt->execute();
                
                // Update session user
                $user['password'] = $hashed_password;
                $_SESSION['user'] = $user;
                
                $profile_success = "Password updated successfully";
            }
        }
        
        // Update profile if no errors
        if (!isset($profile_error)) {
            $update_stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, campus = ? WHERE id = ?");
            $update_stmt->bind_param("sssi", $name, $email, $campus, $user['id']);
            
            if ($update_stmt->execute()) {
                // Update session
                $user['name'] = $name;
                $user['email'] = $email;
                $user['campus'] = $campus;
                $_SESSION['user'] = $user;
                
                $profile_success = "Profile updated successfully";
            } else {
                $profile_error = "Update failed. Please try again.";
            }
        }
    }
    
    // Get user's recent orders
    $conn = getDBConnection();
    $orders_stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $orders_stmt->bind_param("i", $user['id']);
    $orders_stmt->execute();
    $recent_orders = $orders_stmt->get_result();
    
    // Get wishlist count
    $wishlist_stmt = $conn->prepare("SELECT COUNT(*) as count FROM wishlist WHERE user_id = ?");
    $wishlist_stmt->bind_param("i", $user['id']);
    $wishlist_stmt->execute();
    $wishlist_result = $wishlist_stmt->get_result();
    $wishlist_count = $wishlist_result->fetch_assoc()['count'];
}

require_once 'includes/header.php';
?>

<?php if (!isLoggedIn()): ?>
<!-- Login/Registration Page for Guests -->
<div class="account-page">
    <div class="container">
        <div class="row">
            <div class="col-2">
                <img src="<?php echo SITE_URL; ?>assets/images/bunique.PNG" alt="BU Labels">
                <div class="welcome-text">
                    <h2>Welcome to BU Labels</h2>
                    <p>Shop official university merchandise and manage your orders all in one place.</p>
                    <div class="features">
                        <div class="feature">
                            <i class="ri-shopping-bag-line"></i>
                            <span>Shop Campus Merch</span>
                        </div>
                        <div class="feature">
                            <i class="ri-truck-line"></i>
                            <span>Campus Pickup</span>
                        </div>
                        <div class="feature">
                            <i class="ri-shield-check-line"></i>
                            <span>Secure Payments</span>
                        </div>
                        <div class="feature">
                            <i class="ri-history-line"></i>
                            <span>Order Tracking</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-2">
                <div class="form-container">
                    <div class="form-tabs">
                        <button class="form-tab <?php echo ($login || $action != 'register') ? 'active' : ''; ?>" onclick="showLoginForm()">
                            Login
                        </button>
                        <button class="form-tab <?php echo (!$login && $action == 'register') ? 'active' : ''; ?>" onclick="showRegisterForm()">
                            Register
                        </button>
                        <div class="tab-indicator" id="tabIndicator"></div>
                    </div>

                    <!-- Login Form -->
                    <form id="LoginForm" method="POST" class="<?php echo ($login || $action != 'register') ? 'active' : ''; ?>">
                        <input type="hidden" name="login" value="1">
                        
                        <?php if (isset($login_error)): ?>
                        <div class="alert alert-error">
                            <i class="ri-error-warning-line"></i>
                            <?php echo $login_error; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($success)): ?>
                        <div class="alert alert-success">
                            <i class="ri-checkbox-circle-line"></i>
                            <?php echo $success; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="login-email">Email Address</label>
                            <input type="email" id="login-email" name="email" required 
                                   value="<?php echo isset($_POST['email']) ? sanitize($_POST['email']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="login-password">Password</label>
                            <input type="password" id="login-password" name="password" required>
                        </div>
                        
                        <div class="form-options">
                            <label class="checkbox">
                                <input type="checkbox" name="remember">
                                <span>Remember me</span>
                            </label>
                            <a href="account.php?action=forgot" class="forgot-link">Forgot Password?</a>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">Login</button>
                        
                        <div class="divider">or</div>
                        
                        <div class="social-login">
                            <button type="button" class="btn btn-outline btn-block">
                                <i class="ri-google-fill"></i> Continue with Google
                            </button>
                            <button type="button" class="btn btn-outline btn-block">
                                <i class="ri-facebook-fill"></i> Continue with Facebook
                            </button>
                        </div>
                    </form>

                    <!-- Registration Form -->
                    <form id="RegForm" method="POST" class="<?php echo (!$login && $action == 'register') ? 'active' : ''; ?>">
                        <input type="hidden" name="register" value="1">
                        
                        <?php if (isset($errors) && !empty($errors)): ?>
                        <div class="alert alert-error">
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                <li><i class="ri-error-warning-line"></i> <?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="reg-name">Full Name</label>
                            <input type="text" id="reg-name" name="name" required 
                                   value="<?php echo isset($_POST['name']) ? sanitize($_POST['name']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="reg-email">Email Address</label>
                            <input type="email" id="reg-email" name="email" required 
                                   value="<?php echo isset($_POST['email']) ? sanitize($_POST['email']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="reg-campus">Campus</label>
                            <select id="reg-campus" name="campus" required>
                                <option value="">Select Campus</option>
                                <option value="main" <?php echo (isset($_POST['campus']) && $_POST['campus'] == 'main') ? 'selected' : ''; ?>>Main Campus</option>
                                <option value="east" <?php echo (isset($_POST['campus']) && $_POST['campus'] == 'east') ? 'selected' : ''; ?>>East Campus</option>
                                <option value="west" <?php echo (isset($_POST['campus']) && $_POST['campus'] == 'west') ? 'selected' : ''; ?>>West Campus</option>
                                <option value="north" <?php echo (isset($_POST['campus']) && $_POST['campus'] == 'north') ? 'selected' : ''; ?>>North Campus</option>
                                <option value="south" <?php echo (isset($_POST['campus']) && $_POST['campus'] == 'south') ? 'selected' : ''; ?>>South Campus</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="reg-password">Password</label>
                            <input type="password" id="reg-password" name="password" required minlength="6">
                            <small>At least 6 characters</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="reg-confirm-password">Confirm Password</label>
                            <input type="password" id="reg-confirm-password" name="confirm_password" required>
                        </div>
                        
                        <div class="form-group checkbox">
                            <input type="checkbox" id="reg-terms" name="terms" required>
                            <label for="reg-terms">
                                I agree to the <a href="#" target="_blank">Terms and Conditions</a> and 
                                <a href="#" target="_blank">Privacy Policy</a>
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">Create Account</button>
                        
                        <p class="login-link">
                            Already have an account? <a href="account.php?login=1">Login here</a>
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Dashboard for Logged-in Users -->
<div class="account-dashboard">
    <div class="container">
        <div class="dashboard-header">
            <div class="welcome-message">
                <h1>Welcome back, <?php echo sanitize($user['name']); ?>!</h1>
                <p>Here's what's happening with your account.</p>
            </div>
            
            <div class="account-role">
                <span class="role-badge role-<?php echo $user['role']; ?>">
                    <?php echo ucfirst($user['role']); ?>
                </span>
                <span class="campus-badge">
                    <i class="ri-map-pin-line"></i> <?php echo ucfirst($user['campus']); ?> Campus
                </span>
            </div>
        </div>
        
        <div class="dashboard-content">
            <!-- Quick Stats -->
            <div class="quick-stats">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="ri-shopping-bag-line"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $recent_orders->num_rows; ?></h3>
                        <p>Recent Orders</p>
                    </div>
                    <a href="orders.php" class="stat-link">View All</a>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="ri-heart-line"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $wishlist_count; ?></h3>
                        <p>Wishlist Items</p>
                    </div>
                    <a href="account.php?action=wishlist" class="stat-link">View All</a>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="ri-truck-line"></i>
                    </div>
                    <div class="stat-info">
                        <h3>
                            <?php
                            // Count ready for pickup orders
                            $pickup_stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE user_id = ? AND order_status = 'ready_for_pickup'");
                            $pickup_stmt->bind_param("i", $user['id']);
                            $pickup_stmt->execute();
                            $pickup_count = $pickup_stmt->get_result()->fetch_assoc()['count'];
                            echo $pickup_count;
                            ?>
                        </h3>
                        <p>Ready for Pickup</p>
                    </div>
                    <a href="orders.php?filter=ready_for_pickup" class="stat-link">Pick Up Now</a>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="ri-wallet-3-line"></i>
                    </div>
                    <div class="stat-info">
                        <h3>
                            <?php
                            // Calculate total spent
                            $spent_stmt = $conn->prepare("SELECT SUM(total_amount) as total FROM orders WHERE user_id = ? AND order_status = 'picked_up'");
                            $spent_stmt->bind_param("i", $user['id']);
                            $spent_stmt->execute();
                            $spent_total = $spent_stmt->get_result()->fetch_assoc()['total'] ?? 0;
                            echo formatPrice($spent_total);
                            ?>
                        </h3>
                        <p>Total Spent</p>
                    </div>
                </div>
            </div>
            
            <div class="dashboard-grid">
                <!-- Recent Orders -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>Recent Orders</h3>
                        <a href="orders.php" class="view-all">View All</a>
                    </div>
                    
                    <?php if ($recent_orders->num_rows > 0): ?>
                    <div class="orders-list">
                        <?php while ($order = $recent_orders->fetch_assoc()): ?>
                        <div class="order-item">
                            <div class="order-info">
                                <h4>Order #<?php echo $order['order_number']; ?></h4>
                                <div class="order-meta">
                                    <span class="date"><?php echo date('M j, Y', strtotime($order['created_at'])); ?></span>
                                    <span class="total"><?php echo formatPrice($order['total_amount']); ?></span>
                                </div>
                            </div>
                            <div class="order-status">
                                <span class="status-badge status-<?php echo $order['order_status']; ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $order['order_status'])); ?>
                                </span>
                            </div>
                            <a href="order-confirmation.php?order_id=<?php echo $order['id']; ?>" class="order-link">
                                <i class="ri-arrow-right-line"></i>
                            </a>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="ri-shopping-bag-line"></i>
                        <p>No orders yet</p>
                        <a href="products.php" class="btn btn-small">Start Shopping</a>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Profile Information -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>Profile Information</h3>
                        <button class="btn-edit" onclick="editProfile()">Edit</button>
                    </div>
                    
                    <div class="profile-info" id="profileView">
                        <div class="info-row">
                            <span class="label">Name:</span>
                            <span class="value"><?php echo sanitize($user['name']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Email:</span>
                            <span class="value"><?php echo sanitize($user['email']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Campus:</span>
                            <span class="value"><?php echo ucfirst($user['campus']); ?> Campus</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Member Since:</span>
                            <span class="value">
                                <?php 
                                $member_since = new DateTime($user['created_at']);
                                echo $member_since->format('F Y');
                                ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Edit Profile Form (hidden by default) -->
                    <form method="POST" class="profile-form" id="profileForm" style="display: none;">
                        <?php if (isset($profile_error)): ?>
                        <div class="alert alert-error">
                            <i class="ri-error-warning-line"></i>
                            <?php echo $profile_error; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($profile_success)): ?>
                        <div class="alert alert-success">
                            <i class="ri-checkbox-circle-line"></i>
                            <?php echo $profile_success; ?>
                        </div>
                        <?php endif; ?>
                        
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="form-group">
                            <label for="profile-name">Name</label>
                            <input type="text" id="profile-name" name="name" value="<?php echo sanitize($user['name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="profile-email">Email</label>
                            <input type="email" id="profile-email" name="email" value="<?php echo sanitize($user['email']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="profile-campus">Campus</label>
                            <select id="profile-campus" name="campus" required>
                                <option value="main" <?php echo ($user['campus'] == 'main') ? 'selected' : ''; ?>>Main Campus</option>
                                <option value="east" <?php echo ($user['campus'] == 'east') ? 'selected' : ''; ?>>East Campus</option>
                                <option value="west" <?php echo ($user['campus'] == 'west') ? 'selected' : ''; ?>>West Campus</option>
                                <option value="north" <?php echo ($user['campus'] == 'north') ? 'selected' : ''; ?>>North Campus</option>
                                <option value="south" <?php echo ($user['campus'] == 'south') ? 'selected' : ''; ?>>South Campus</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="current-password">Current Password (to change password)</label>
                            <input type="password" id="current-password" name="current_password">
                        </div>
                        
                        <div class="form-group">
                            <label for="new-password">New Password</label>
                            <input type="password" id="new-password" name="new_password" minlength="6">
                            <small>Leave blank to keep current password</small>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn btn-outline" onclick="cancelEdit()">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <!-- Option 1: Simple Button Grid -->
<div class="quick-actions">
    <h3><i class="ri-rocket-line"></i> Quick Actions</h3>
    <div class="action-buttons">
        <a href="orders.php" class="action-btn orders">
            <i class="ri-shopping-bag-line"></i>
            <span>My Orders</span>
        </a>
        <a href="account.php?page=wishlist" class="action-btn wishlist">
            <i class="ri-heart-line"></i>
            <span>Wishlist</span>
        </a>
        <a href="account.php?page=profile" class="action-btn profile">
            <i class="ri-user-line"></i>
            <span>Profile</span>
        </a>
        <a href="account.php?page=settings" class="action-btn settings">
            <i class="ri-settings-line"></i>
            <span>Settings</span>
        </a>
        <a href="logout.php" class="action-btn logout">
            <i class="ri-logout-box-line"></i>
            <span>Logout</span>
        </a>
    </div>
</div>

<!-- Option 2: Card-based Design -->
<div class="quick-actions-section">
    <h3 class="section-title">Quick Actions</h3>
    <div class="quick-actions-cards">
        <a href="orders.php" class="quick-action-card orders">
            <i class="ri-shopping-bag-line"></i>
            <h4>My Orders</h4>
            <p>View and track your orders</p>
        </a>
        <a href="account.php?page=wishlist" class="quick-action-card wishlist">
            <i class="ri-heart-line"></i>
            <h4>Wishlist</h4>
            <p>Your saved items</p>
        </a>
        <a href="account.php?page=profile" class="quick-action-card profile">
            <i class="ri-user-line"></i>
            <h4>Profile</h4>
            <p>Update your information</p>
        </a>
        <a href="account.php?page=settings" class="quick-action-card settings">
            <i class="ri-settings-line"></i>
            <h4>Settings</h4>
            <p>Account preferences</p>
        </a>
        <a href="logout.php" class="quick-action-card logout">
            <i class="ri-logout-box-line"></i>
            <h4>Logout</h4>
            <p>Sign out securely</p>
        </a>
    </div>
</div>

<!-- Option 3: For Product Cards -->
<div class="product-card">
    <!-- ... product image and info ... -->
    <div class="action-buttons">
        <a href="product-detail.php?id=1" class="btn-view">View Details</a>
        <div class="icon-actions">
            <button class="icon-btn wishlist" title="Add to Wishlist">
                <i class="ri-heart-line"></i>
            </button>
            <button class="icon-btn cart" title="Add to Cart">
                <i class="ri-shopping-cart-line"></i>
            </button>
        </div>
    </div>
</div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Form switching for login/register
function showLoginForm() {
    document.getElementById('LoginForm').classList.add('active');
    document.getElementById('RegForm').classList.remove('active');
    document.querySelectorAll('.form-tab')[0].classList.add('active');
    document.querySelectorAll('.form-tab')[1].classList.remove('active');
    document.getElementById('tabIndicator').style.transform = 'translateX(0)';
}

function showRegisterForm() {
    document.getElementById('RegForm').classList.add('active');
    document.getElementById('LoginForm').classList.remove('active');
    document.querySelectorAll('.form-tab')[1].classList.add('active');
    document.querySelectorAll('.form-tab')[0].classList.remove('active');
    document.getElementById('tabIndicator').style.transform = 'translateX(100%)';
}

// Profile editing
function editProfile() {
    document.getElementById('profileView').style.display = 'none';
    document.getElementById('profileForm').style.display = 'block';
}

function cancelEdit() {
    document.getElementById('profileForm').style.display = 'none';
    document.getElementById('profileView').style.display = 'block';
}

// Initialize form based on URL
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('register')) {
        showRegisterForm();
    } else {
        showLoginForm();
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>