<?php
// admin-login.php - Separate login page for administrators

require_once 'includes/config.php';
require_once 'includes/functions.php';

// If already logged in as admin, redirect to admin dashboard
if (isAdmin()) {
    redirect('pages/admin/dashboard.php');
}

// If logged in but not admin, show error
if (isLoggedIn() && !isAdmin()) {
    $error = "Access denied. This page is for administrators only.";
}

$page_title = "Admin Login";
$body_class = "admin-login-page";

// Handle admin login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = escape($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);
    
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT id, name, email, password, role, campus FROM users WHERE email = ? AND role IN ('admin', 'director')");
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
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $expiry = time() + (30 * 24 * 60 * 60); // 30 days
                $expiry_date = date('Y-m-d H:i:s', $expiry);
                
                $update_stmt = $conn->prepare("UPDATE users SET remember_token = ?, token_expiry = ? WHERE id = ?");
                $update_stmt->bind_param("ssi", $token, $expiry_date, $user['id']);
                $update_stmt->execute();
                
                setcookie('remember_token', $token, $expiry, '/');
            }
            
            // Redirect based on role
            if ($user['role'] == 'admin') {
                redirect('pages/admin/dashboard.php');
            } else {
                redirect('pages/campus-director/dashboard.php');
            }
        } else {
            $login_error = "Invalid email or password";
        }
    } else {
        $login_error = "Access denied. Admin privileges required.";
    }
}

// Simple header for admin login
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BU Labels - Admin Login</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        :root {
            --primary: #1a365d;
            --primary-light: #2d4a8a;
            --secondary: #ed8936;
            --accent: #38b2ac;
            --danger: #f56565;
            --light: #f7fafc;
            --gray: #e2e8f0;
            --dark: #2d3748;
            --white: #ffffff;
            --border-radius: 8px;
            --box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .admin-login-container {
            background: var(--white);
            border-radius: 16px;
            box-shadow: var(--box-shadow);
            width: 100%;
            max-width: 400px;
            overflow: hidden;
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .admin-login-header {
            background: var(--primary);
            color: var(--white);
            padding: 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .admin-login-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            animation: shine 3s infinite;
        }

        @keyframes shine {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }

        .admin-logo {
            display: block;
            margin: 0 auto 1rem;
            width: 80px;
            height: auto;
            filter: brightness(0) invert(1);
        }

        .admin-login-header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .admin-login-header p {
            font-size: 0.875rem;
            opacity: 0.9;
        }

        .admin-login-body {
            padding: 2rem;
        }

        .alert {
            padding: 0.875rem 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.875rem;
        }

        .alert-error {
            background: #fee;
            color: #c33;
            border-left: 4px solid var(--danger);
        }

        .alert-success {
            background: #efe;
            color: #3a3;
            border-left: 4px solid #48bb78;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
            font-size: 0.875rem;
        }

        .form-group input {
            width: 100%;
            padding: 0.875rem;
            border: 1px solid var(--gray);
            border-radius: var(--border-radius);
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 54, 93, 0.1);
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            font-size: 0.875rem;
        }

        .checkbox input {
            width: auto;
            margin: 0;
        }

        .forgot-link {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.875rem;
            transition: color 0.3s ease;
        }

        .forgot-link:hover {
            color: var(--primary-light);
            text-decoration: underline;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.875rem 1.5rem;
            border-radius: var(--border-radius);
            border: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            font-size: 0.875rem;
            width: 100%;
        }

        .btn-primary {
            background: var(--primary);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--primary-light);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .btn-outline {
            background: transparent;
            color: var(--primary);
            border: 1px solid var(--primary);
        }

        .btn-outline:hover {
            background: var(--primary);
            color: var(--white);
        }

        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--gray);
            font-size: 0.875rem;
            color: var(--dark);
        }

        .login-footer a {
            color: var(--primary);
            text-decoration: none;
        }

        .login-footer a:hover {
            text-decoration: underline;
        }

        .security-notice {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-top: 1.5rem;
            text-align: center;
            font-size: 0.75rem;
            color: var(--dark);
            border: 1px solid var(--gray);
        }

        .security-notice i {
            color: var(--secondary);
            margin-right: 0.5rem;
        }

        /* Password visibility toggle */
        .password-input {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--dark);
            cursor: pointer;
            padding: 0.25rem;
        }

        /* Loading state */
        .btn.loading {
            position: relative;
            color: transparent;
        }

        .btn.loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 480px) {
            .admin-login-container {
                max-width: 100%;
            }
            
            .admin-login-body {
                padding: 1.5rem;
            }
            
            body {
                padding: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="admin-login-container">
        <div class="admin-login-header">
            <img src="assets/images/logo.png" alt="BU Labels" class="admin-logo">
            <h1>Admin Panel</h1>
            <p>Restricted Access - Authorized Personnel Only</p>
        </div>
        
        <div class="admin-login-body">
            <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="ri-error-warning-line"></i>
                <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($login_error)): ?>
            <div class="alert alert-error">
                <i class="ri-error-warning-line"></i>
                <?php echo $login_error; ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" id="adminLoginForm">
                <input type="hidden" name="login" value="1">
                
                <div class="form-group">
                    <label for="admin-email">Admin Email</label>
                    <input type="email" id="admin-email" name="email" required 
                           placeholder="admin@example.com"
                           value="<?php echo isset($_POST['email']) ? sanitize($_POST['email']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="admin-password">Password</label>
                    <div class="password-input">
                        <input type="password" id="admin-password" name="password" required 
                               placeholder="••••••••" minlength="6">
                        <button type="button" class="toggle-password" onclick="togglePasswordVisibility()">
                            <i class="ri-eye-line"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-options">
                    <label class="checkbox">
                        <input type="checkbox" name="remember">
                        <span>Remember me</span>
                    </label>
                    <a href="#" class="forgot-link">Forgot Password?</a>
                </div>
                
                <button type="submit" class="btn btn-primary" id="loginButton">
                    <i class="ri-login-box-line"></i> Login to Admin Panel
                </button>
            </form>
            
            <div class="security-notice">
                <i class="ri-shield-keyhole-line"></i>
                This system is for authorized BU Labels administrators only.
            </div>
            
            <div class="login-footer">
                <p>Need help? <a href="mailto:support@bulabels.edu">Contact System Administrator</a></p>
                <p><a href="index.php">← Return to Main Site</a></p>
            </div>
        </div>
    </div>

    <script>
    function togglePasswordVisibility() {
        const passwordInput = document.getElementById('admin-password');
        const toggleButton = document.querySelector('.toggle-password i');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleButton.classList.remove('ri-eye-line');
            toggleButton.classList.add('ri-eye-off-line');
        } else {
            passwordInput.type = 'password';
            toggleButton.classList.remove('ri-eye-off-line');
            toggleButton.classList.add('ri-eye-line');
        }
    }

    // Form submission with loading state
    document.getElementById('adminLoginForm').addEventListener('submit', function(e) {
        const loginButton = document.getElementById('loginButton');
        const email = document.getElementById('admin-email').value.trim();
        const password = document.getElementById('admin-password').value.trim();
        
        // Basic validation
        if (!email || !password) {
            e.preventDefault();
            alert('Please fill in all fields');
            return;
        }
        
        // Email validation
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            e.preventDefault();
            alert('Please enter a valid email address');
            return;
        }
        
        // Show loading state
        loginButton.innerHTML = '';
        loginButton.classList.add('loading');
        
        // Simulate network delay for UX
        setTimeout(() => {
            loginButton.classList.remove('loading');
            loginButton.innerHTML = '<i class="ri-login-box-line"></i> Login to Admin Panel';
        }, 2000);
    });

    // Enter key submits form
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.target.matches('button, a')) {
            document.getElementById('adminLoginForm').requestSubmit();
        }
    });

    // Auto-focus email field on page load
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('admin-email').focus();
    });
    </script>
</body>
</html>