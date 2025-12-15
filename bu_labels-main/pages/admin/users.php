<?php
// pages/admin/users.php

require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is admin
if (!isLoggedIn() || $_SESSION['user_role'] != 'admin') {
    redirect('../../account.php');
}

$page_title = "Manage Users";
$body_class = "admin-users";

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';

$conn = getDBConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_user'])) {
        $name = escape($_POST['name']);
        $email = escape($_POST['email']);
        $role = escape($_POST['role']);
        $campus = escape($_POST['campus']);
        $password = $_POST['password'];
        
        // Validation
        $errors = [];
        
        if (empty($name)) $errors[] = "Name is required";
        if (empty($email)) $errors[] = "Email is required";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
        if (empty($role)) $errors[] = "Role is required";
        if (empty($campus)) $errors[] = "Campus is required";
        
        // Check if email exists (for new users or when email is changed)
        if ($user_id > 0) {
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check_stmt->bind_param("si", $email, $user_id);
        } else {
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check_stmt->bind_param("s", $email);
        }
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            $errors[] = "Email already exists";
        }
        
        if (empty($errors)) {
            if ($user_id > 0) {
                // Update existing user
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ?, campus = ?, password = ? WHERE id = ?");
                    $stmt->bind_param("sssssi", $name, $email, $role, $campus, $hashed_password, $user_id);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ?, campus = ? WHERE id = ?");
                    $stmt->bind_param("ssssi", $name, $email, $role, $campus, $user_id);
                }
            } else {
                // Create new user
                if (empty($password)) {
                    $errors[] = "Password is required for new users";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO users (name, email, role, campus, password) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssss", $name, $email, $role, $campus, $hashed_password);
                }
            }
            
            if (empty($errors) && $stmt->execute()) {
                $_SESSION['success'] = "User saved successfully!";
                redirect('users.php?action=edit&id=' . ($user_id > 0 ? $user_id : $conn->insert_id));
            } else if (!empty($errors)) {
                $error = implode('<br>', $errors);
            } else {
                $error = "Error saving user: " . $conn->error;
            }
        } else {
            $error = implode('<br>', $errors);
        }
    }
    
    if (isset($_POST['delete_user'])) {
        $delete_id = (int)$_POST['delete_id'];
        
        // Prevent deleting own account
        if ($delete_id == $_SESSION['user_id']) {
            $error = "You cannot delete your own account!";
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $delete_id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "User deleted successfully!";
                redirect('users.php');
            } else {
                $error = "Error deleting user: " . $conn->error;
            }
        }
    }
}

// Get user data for edit
if ($action == 'edit' && $user_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_result = $stmt->get_result();
    $user = $user_result->fetch_assoc();
    
    if (!$user) {
        redirect('users.php');
    }
}

// Get users for listing
if ($action == 'list') {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    // Build query with filters
    $sql = "SELECT * FROM users WHERE 1=1";
    $params = [];
    $types = "";
    
    if ($role_filter) {
        $sql .= " AND role = ?";
        $params[] = $role_filter;
        $types .= "s";
    }
    
    // Search
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search = escape($_GET['search']);
        $sql .= " AND (name LIKE ? OR email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $types .= "ss";
    }
    
    // Get total count
    $count_sql = str_replace("SELECT *", "SELECT COUNT(*) as total", $sql);
    $count_stmt = $conn->prepare($count_sql);
    if ($params) {
        $count_stmt->bind_param($types, ...$params);
    }
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_rows = $count_result->fetch_assoc()['total'];
    $total_pages = ceil($total_rows / $limit);
    
    // Add sorting and pagination
    $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    
    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $users_result = $stmt->get_result();
}

// Get role counts for stats
$role_counts = [];
$role_stmt = $conn->prepare("SELECT role, COUNT(*) as count FROM users GROUP BY role");
$role_stmt->execute();
$role_result = $role_stmt->get_result();
while ($row = $role_result->fetch_assoc()) {
    $role_counts[$row['role']] = $row['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BU Labels - Admin | <?php echo $action == 'add' ? 'Add User' : ($action == 'edit' ? 'Edit User' : 'Manage Users'); ?></title>
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
            --success: #48bb78;
            --warning: #ecc94b;
            --danger: #f56565;
            --light: #f7fafc;
            --gray: #e2e8f0;
            --dark-gray: #718096;
            --dark: #2d3748;
            --white: #ffffff;
            --border-radius: 8px;
            --box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            --transition: all 0.3s ease;
        }

        body {
            background: #f0f2f5;
            color: var(--dark);
            min-height: 100vh;
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .admin-sidebar {
            width: 260px;
            background: var(--primary);
            color: var(--white);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: var(--transition);
            z-index: 1000;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }

        .sidebar-header .logo {
            display: block;
            margin-bottom: 1rem;
        }

        .sidebar-header .logo img {
            height: 40px;
            width: auto;
        }

        .sidebar-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
        }

        .sidebar-menu {
            padding: 1.5rem 0;
        }

        .menu-section {
            margin-bottom: 1.5rem;
        }

        .menu-section h4 {
            font-size: 0.75rem;
            text-transform: uppercase;
            color: rgba(255,255,255,0.6);
            padding: 0 1.5rem;
            margin-bottom: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .menu-section ul {
            list-style: none;
        }

        .menu-section li {
            margin-bottom: 0.25rem;
        }

        .menu-section li a {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: var(--transition);
            font-weight: 500;
        }

        .menu-section li a:hover {
            background: rgba(255,255,255,0.1);
            color: var(--white);
        }

        .menu-section li.active a {
            background: rgba(255,255,255,0.15);
            color: var(--white);
            border-left: 4px solid var(--secondary);
        }

        .menu-section li a i {
            margin-right: 0.75rem;
            font-size: 1.25rem;
            width: 24px;
            text-align: center;
        }

        .menu-section li a .badge {
            margin-left: auto;
            background: var(--secondary);
            color: var(--white);
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            min-width: 24px;
            text-align: center;
        }

        .sidebar-footer {
            padding: 1.5rem;
            border-top: 1px solid rgba(255,255,255,0.1);
            margin-top: auto;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .user-info h5 {
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 0.125rem;
        }

        .user-info p {
            font-size: 0.75rem;
            color: rgba(255,255,255,0.6);
        }

        /* Main Content */
        .admin-content {
            flex: 1;
            margin-left: 260px;
            padding: 2rem;
        }

        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--gray);
        }

        .admin-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary);
        }

        .admin-actions {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            border-radius: var(--border-radius);
            border: none;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            font-size: 0.875rem;
        }

        .btn-primary {
            background: var(--primary);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--primary-light);
            transform: translateY(-1px);
            box-shadow: var(--box-shadow);
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

        .btn-danger {
            background: var(--danger);
            color: var(--white);
        }

        .btn-danger:hover {
            background: #e53e3e;
            transform: translateY(-1px);
            box-shadow: var(--box-shadow);
        }

        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.75rem;
        }

        .btn-block {
            display: block;
            width: 100%;
        }

        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: #c6f6d5;
            color: #22543d;
            border-left: 4px solid var(--success);
        }

        .alert-error {
            background: #fed7d7;
            color: #742a2a;
            border-left: 4px solid var(--danger);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            color: var(--white);
        }

        .stat-icon:nth-child(1) { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-icon:nth-child(2) { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stat-icon:nth-child(3) { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stat-icon:nth-child(4) { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }

        .stat-info h3 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark);
            line-height: 1;
        }

        .stat-info p {
            font-size: 0.875rem;
            color: var(--dark-gray);
            margin-top: 0.25rem;
        }

        /* Cards */
        .card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary);
        }

        .card-tools {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Filter Form */
        .filter-form {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .filter-form input,
        .filter-form select {
            padding: 0.625rem;
            border: 1px solid var(--gray);
            border-radius: var(--border-radius);
            font-size: 0.875rem;
            background: var(--white);
            min-width: 180px;
        }

        .filter-form input:focus,
        .filter-form select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 54, 93, 0.1);
        }

        /* User Form */
        .user-form {
            max-width: 800px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--gray);
            border-radius: var(--border-radius);
            font-size: 0.875rem;
            background: var(--white);
            transition: var(--transition);
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 54, 93, 0.1);
        }

        .form-group small {
            display: block;
            margin-top: 0.5rem;
            color: var(--dark-gray);
            font-size: 0.75rem;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--gray);
        }

        /* Tables */
        .table-responsive {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        .data-table thead {
            background: var(--light);
        }

        .data-table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--dark);
            border-bottom: 2px solid var(--gray);
        }

        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--gray);
            vertical-align: middle;
        }

        .data-table tbody tr:hover {
            background: var(--light);
        }

        /* User Info in Table */
        .user-info-cell {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .user-avatar-small {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .user-info-text {
            flex: 1;
        }

        .user-info-text strong {
            display: block;
            font-weight: 600;
            margin-bottom: 0.125rem;
        }

        .user-info-text small {
            color: var(--dark-gray);
            font-size: 0.75rem;
        }

        /* Role Badges */
        .role-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        .role-badge.role-customer { background: #c6f6d5; color: #22543d; }
        .role-badge.role-director { background: #bee3f8; color: #2c5282; }
        .role-badge.role-admin { background: #e9d8fd; color: #553c9a; }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-active {
            background: #c6f6d5;
            color: #22543d;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--light);
            color: var(--dark-gray);
            border: none;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .btn-icon:hover {
            background: var(--primary);
            color: var(--white);
        }

        .btn-edit:hover { background: var(--primary); }
        .btn-delete:hover { background: var(--danger); }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
        }

        .empty-state i {
            font-size: 3rem;
            color: var(--gray);
            margin-bottom: 1rem;
        }

        .empty-state p {
            color: var(--dark-gray);
            margin-bottom: 1.5rem;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .page-link {
            padding: 0.5rem 1rem;
            background: var(--white);
            border: 1px solid var(--gray);
            border-radius: var(--border-radius);
            color: var(--dark-gray);
            text-decoration: none;
            transition: var(--transition);
        }

        .page-link:hover {
            background: var(--primary);
            color: var(--white);
            border-color: var(--primary);
        }

        .page-link.active {
            background: var(--primary);
            color: var(--white);
            border-color: var(--primary);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--white);
            border-radius: var(--border-radius);
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            color: var(--primary);
            font-size: 1.25rem;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--dark-gray);
            cursor: pointer;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .modal-close:hover {
            background: var(--gray);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--gray);
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }

        .text-danger {
            color: var(--danger);
        }

        /* Mobile Responsive */
        @media (max-width: 1024px) {
            .admin-sidebar {
                width: 240px;
            }
            
            .admin-content {
                margin-left: 240px;
            }
        }

        @media (max-width: 768px) {
            .admin-container {
                flex-direction: column;
            }
            
            .admin-sidebar {
                position: fixed;
                width: 100%;
                height: auto;
                max-height: 0;
                overflow: hidden;
                transition: max-height 0.3s ease;
            }
            
            .admin-sidebar.active {
                max-height: 80vh;
                overflow-y: auto;
            }
            
            .admin-content {
                margin-left: 0;
                margin-top: 60px;
                padding: 1rem;
            }
            
            .admin-header {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
            
            .admin-actions {
                justify-content: flex-end;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .filter-form {
                flex-direction: column;
            }
            
            .filter-form input,
            .filter-form select {
                width: 100%;
                min-width: auto;
            }
            
            .data-table {
                font-size: 0.75rem;
            }
            
            .data-table th,
            .data-table td {
                padding: 0.75rem 0.5rem;
            }
        }

        @media (max-width: 480px) {
            .admin-content {
                padding: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .action-buttons {
                flex-wrap: wrap;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
<div class="admin-container">
    <?php 
    // Include sidebar with current page detection
    $current_page = 'users.php';
    ob_start();
    ?>
    <aside class="admin-sidebar">
        <div class="sidebar-header">
            <a href="../../" class="logo">
                <img src="../../assets/images/logo.png" alt="BU Labels">
            </a>
            <h3>Admin Panel</h3>
        </div>
        
        <div class="sidebar-menu">
            <div class="menu-section">
                <h4>Dashboard</h4>
                <ul>
                    <li>
                        <a href="dashboard.php">
                            <i class="ri-dashboard-line"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                </ul>
            </div>
            
            <div class="menu-section">
                <h4>Products</h4>
                <ul>
                    <li>
                        <a href="products.php">
                            <i class="ri-box-3-line"></i>
                            <span>All Products</span>
                        </a>
                    </li>
                    <li>
                        <a href="products.php?action=add">
                            <i class="ri-add-circle-line"></i>
                            <span>Add Product</span>
                        </a>
                    </li>
                    <li>
                        <a href="categories.php">
                            <i class="ri-list-check"></i>
                            <span>Categories</span>
                        </a>
                    </li>
                    <li>
                        <a href="inventory.php">
                            <i class="ri-store-2-line"></i>
                            <span>Inventory</span>
                        </a>
                    </li>
                </ul>
            </div>
            
            <div class="menu-section">
                <h4>Orders</h4>
                <ul>
                    <li>
                        <a href="orders.php">
                            <i class="ri-shopping-bag-line"></i>
                            <span>All Orders</span>
                        </a>
                    </li>
                    <li>
                        <a href="orders.php?status=processing">
                            <i class="ri-refresh-line"></i>
                            <span>Processing</span>
                        </a>
                    </li>
                    <li>
                        <a href="orders.php?status=ready_for_pickup">
                            <i class="ri-checkbox-circle-line"></i>
                            <span>Ready for Pickup</span>
                        </a>
                    </li>
                    <li>
                        <a href="orders.php?status=picked_up">
                            <i class="ri-truck-line"></i>
                            <span>Completed</span>
                        </a>
                    </li>
                </ul>
            </div>
            
            <div class="menu-section">
                <h4>Reports</h4>
                <ul>
                    <li>
                        <a href="reports.php">
                            <i class="ri-bar-chart-line"></i>
                            <span>Sales Reports</span>
                        </a>
                    </li>
                    <li>
                        <a href="reports.php?type=products">
                            <i class="ri-pie-chart-line"></i>
                            <span>Product Reports</span>
                        </a>
                    </li>
                    <li>
                        <a href="reports.php?type=campus">
                            <i class="ri-building-line"></i>
                            <span>Campus Reports</span>
                        </a>
                    </li>
                </ul>
            </div>
            
            <div class="menu-section">
                <h4>Users</h4>
                <ul>
                    <li class="active">
                        <a href="users.php">
                            <i class="ri-user-line"></i>
                            <span>All Users</span>
                        </a>
                    </li>
                    <li>
                        <a href="users.php?role=admin">
                            <i class="ri-shield-user-line"></i>
                            <span>Admins</span>
                        </a>
                    </li>
                    <li>
                        <a href="users.php?role=director">
                            <i class="ri-building-line"></i>
                            <span>Campus Directors</span>
                        </a>
                    </li>
                    <li>
                        <a href="users.php?role=customer">
                            <i class="ri-user-3-line"></i>
                            <span>Customers</span>
                        </a>
                    </li>
                </ul>
            </div>
            
            <div class="menu-section">
                <h4>System</h4>
                <ul>
                    <li>
                        <a href="settings.php">
                            <i class="ri-settings-3-line"></i>
                            <span>Settings</span>
                        </a>
                    </li>
                    <li>
                        <a href="../../logout.php">
                            <i class="ri-logout-box-line"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar">
                    <i class="ri-user-line"></i>
                </div>
                <div>
                    <h5><?php echo sanitize($_SESSION['user']['name']); ?></h5>
                    <p>Administrator</p>
                </div>
            </div>
        </div>
    </aside>
    <?php 
    $sidebar_content = ob_get_clean();
    echo $sidebar_content;
    ?>
    
    <main class="admin-content">
        <div class="admin-header">
            <h1>
                <?php 
                if ($action == 'add') echo "Add New User";
                elseif ($action == 'edit') echo "Edit User";
                else echo "Manage Users";
                ?>
            </h1>
            
            <div class="admin-actions">
                <?php if ($action == 'list'): ?>
                <a href="users.php?action=add" class="btn btn-primary">
                    <i class="ri-user-add-line"></i> Add User
                </a>
                <?php else: ?>
                <a href="users.php" class="btn btn-outline">
                    <i class="ri-arrow-left-line"></i> Back to Users
                </a>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <i class="ri-checkbox-circle-line"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <i class="ri-error-warning-line"></i>
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($action == 'add' || $action == 'edit'): ?>
        <!-- User Form -->
        <div class="card">
            <div class="card-header">
                <h3>User Information</h3>
            </div>
            
            <div class="card-body">
                <form method="POST" class="user-form">
                    <input type="hidden" name="save_user" value="1">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Full Name *</label>
                            <input type="text" id="name" name="name" required 
                                   value="<?php echo isset($user['name']) ? sanitize($user['name']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" required 
                                   value="<?php echo isset($user['email']) ? sanitize($user['email']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="role">User Role *</label>
                            <select id="role" name="role" required>
                                <option value="">Select Role</option>
                                <option value="customer" <?php echo (isset($user['role']) && $user['role'] == 'customer') ? 'selected' : ''; ?>>Customer</option>
                                <option value="director" <?php echo (isset($user['role']) && $user['role'] == 'director') ? 'selected' : ''; ?>>Campus Director</option>
                                <option value="admin" <?php echo (isset($user['role']) && $user['role'] == 'admin') ? 'selected' : ''; ?>>Administrator</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="campus">Campus *</label>
                            <select id="campus" name="campus" required>
                                <option value="">Select Campus</option>
                                <option value="main" <?php echo (isset($user['campus']) && $user['campus'] == 'main') ? 'selected' : ''; ?>>Main Campus</option>
                                <option value="east" <?php echo (isset($user['campus']) && $user['campus'] == 'east') ? 'selected' : ''; ?>>Polangui Campus</option>
                                <option value="west" <?php echo (isset($user['campus']) && $user['campus'] == 'west') ? 'selected' : ''; ?>>Guinobatan Campus</option>
                                <option value="north" <?php echo (isset($user['campus']) && $user['campus'] == 'north') ? 'selected' : ''; ?>>Gubat Campus</option>
                                <option value="south" <?php echo (isset($user['campus']) && $user['campus'] == 'south') ? 'selected' : ''; ?>>BUIDEA</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password <?php echo ($action == 'add') ? '*' : '(Leave blank to keep current)'; ?></label>
                        <input type="password" id="password" name="password" 
                               <?php echo ($action == 'add') ? 'required' : ''; ?>
                               minlength="6">
                        <small>Minimum 6 characters</small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-outline" onclick="window.history.back()">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="ri-save-line"></i> Save User
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Users List -->
        <div class="stats-grid">
            <!-- User Stats -->
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="ri-user-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $role_counts['customer'] ?? 0; ?></h3>
                    <p>Customers</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="ri-building-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $role_counts['director'] ?? 0; ?></h3>
                    <p>Campus Directors</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="ri-shield-user-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $role_counts['admin'] ?? 0; ?></h3>
                    <p>Administrators</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="ri-group-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo array_sum($role_counts); ?></h3>
                    <p>Total Users</p>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3>All Users</h3>
                
                <div class="card-tools">
                    <form method="GET" class="filter-form">
                        <input type="text" name="search" placeholder="Search users..." 
                               value="<?php echo isset($_GET['search']) ? sanitize($_GET['search']) : ''; ?>">
                        <select name="role" onchange="this.form.submit()">
                            <option value="">All Roles</option>
                            <option value="customer" <?php echo ($role_filter == 'customer') ? 'selected' : ''; ?>>Customers</option>
                            <option value="director" <?php echo ($role_filter == 'director') ? 'selected' : ''; ?>>Campus Directors</option>
                            <option value="admin" <?php echo ($role_filter == 'admin') ? 'selected' : ''; ?>>Administrators</option>
                        </select>
                        <button type="submit" class="btn btn-small">
                            <i class="ri-search-line"></i> Search
                        </button>
                        <?php if (isset($_GET['search']) || isset($_GET['role'])): ?>
                        <a href="users.php" class="btn btn-outline btn-small">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            
            <div class="card-body">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>User</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Campus</th>
                                <th>Joined</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($users_result->num_rows > 0): ?>
                                <?php while ($user = $users_result->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?php echo $user['id']; ?></td>
                                    <td>
                                        <div class="user-info-cell">
                                            <div class="user-avatar-small">
                                                <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                            </div>
                                            <div class="user-info-text">
                                                <strong><?php echo sanitize($user['name']); ?></strong>
                                                <small>ID: <?php echo $user['id']; ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo sanitize($user['email']); ?></td>
                                    <td>
                                        <span class="role-badge role-<?php echo $user['role']; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo ucfirst($user['campus']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <?php 
                                        // Check if user is currently logged in
                                        $is_current = ($user['id'] == $_SESSION['user_id']) ? ' (You)' : '';
                                        ?>
                                        <span class="status-badge status-active">Active<?php echo $is_current; ?></span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="users.php?action=edit&id=<?php echo $user['id']; ?>" 
                                               class="btn-icon btn-edit" title="Edit">
                                                <i class="ri-edit-line"></i>
                                            </a>
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <button type="button" class="btn-icon btn-delete" 
                                                    onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo sanitize($user['name']); ?>')"
                                                    title="Delete">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center">
                                        <div class="empty-state">
                                            <i class="ri-user-line"></i>
                                            <p>No users found</p>
                                            <?php if (isset($_GET['search']) || isset($_GET['role'])): ?>
                                            <a href="users.php" class="btn btn-outline btn-small">
                                                Clear Filters
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-link">
                        <i class="ri-arrow-left-line"></i> Previous
                    </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
                       class="page-link <?php echo ($i == $page) ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="page-link">
                        Next <i class="ri-arrow-right-line"></i>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </main>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal" id="deleteModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Confirm Delete</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete user <strong id="deleteUserName"></strong>?</p>
            <p class="text-danger">This action cannot be undone. All user data will be permanently deleted.</p>
        </div>
        <div class="modal-footer">
            <form method="POST" id="deleteForm">
                <input type="hidden" name="delete_user" value="1">
                <input type="hidden" name="delete_id" id="deleteUserId">
                <button type="button" class="btn btn-outline modal-close">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete User</button>
            </form>
        </div>
    </div>
</div>

<script>
function confirmDelete(userId, userName) {
    document.getElementById('deleteUserId').value = userId;
    document.getElementById('deleteUserName').textContent = userName;
    document.getElementById('deleteModal').style.display = 'block';
}

// Close modal
document.querySelectorAll('.modal-close').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('deleteModal').style.display = 'none';
    });
});

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('deleteModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
});

// Mobile sidebar toggle
document.addEventListener('DOMContentLoaded', function() {
    const menuBtn = document.createElement('button');
    menuBtn.innerHTML = '<i class="ri-menu-line"></i>';
    menuBtn.className = 'btn btn-primary mobile-menu-btn';
    menuBtn.style.cssText = 'position: fixed; top: 1rem; left: 1rem; z-index: 1001; display: none;';
    
    const adminHeader = document.querySelector('.admin-header');
    if (adminHeader) {
        adminHeader.appendChild(menuBtn);
    }
    
    const sidebar = document.querySelector('.admin-sidebar');
    
    menuBtn.addEventListener('click', function() {
        sidebar.classList.toggle('active');
        menuBtn.innerHTML = sidebar.classList.contains('active') ? 
            '<i class="ri-close-line"></i>' : '<i class="ri-menu-line"></i>';
    });
    
    // Show/hide mobile menu button
    function checkMobile() {
        if (window.innerWidth <= 768) {
            menuBtn.style.display = 'flex';
        } else {
            menuBtn.style.display = 'none';
            sidebar.classList.remove('active');
        }
    }
    
    checkMobile();
    window.addEventListener('resize', checkMobile);
    
    // Form validation
    const userForm = document.querySelector('.user-form');
    if (userForm) {
        userForm.addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.style.borderColor = 'var(--danger)';
                    field.style.boxShadow = '0 0 0 3px rgba(245, 101, 101, 0.1)';
                }
            });
            
            // Password validation for new users
            const passwordField = document.getElementById('password');
            const action = '<?php echo $action; ?>';
            
            if (action === 'add' && passwordField && passwordField.value.length < 6) {
                isValid = false;
                passwordField.style.borderColor = 'var(--danger)';
                passwordField.style.boxShadow = '0 0 0 3px rgba(245, 101, 101, 0.1)';
            }
            
            // Email validation
            const emailField = document.getElementById('email');
            if (emailField && !isValidEmail(emailField.value)) {
                isValid = false;
                emailField.style.borderColor = 'var(--danger)';
                emailField.style.boxShadow = '0 0 0 3px rgba(245, 101, 101, 0.1)';
            }
            
            if (!isValid) {
                e.preventDefault();
                // Create error notification
                const alert = document.createElement('div');
                alert.className = 'alert alert-error';
                alert.innerHTML = '<i class="ri-error-warning-line"></i> Please check all required fields';
                
                const existingAlert = document.querySelector('.alert');
                if (existingAlert) {
                    existingAlert.parentNode.insertBefore(alert, existingAlert.nextSibling);
                } else {
                    const adminHeader = document.querySelector('.admin-header');
                    adminHeader.parentNode.insertBefore(alert, adminHeader.nextSibling);
                }
                
                // Scroll to first error
                const firstError = document.querySelector('[required]:invalid') || 
                                  (passwordField && passwordField.value.length < 6 ? passwordField : emailField);
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstError.focus();
                }
                
                // Remove error styles on input
                requiredFields.forEach(field => {
                    field.addEventListener('input', function() {
                        if (this.value.trim()) {
                            this.style.borderColor = '';
                            this.style.boxShadow = '';
                        }
                    });
                });
                
                if (emailField) {
                    emailField.addEventListener('input', function() {
                        if (isValidEmail(this.value)) {
                            this.style.borderColor = '';
                            this.style.boxShadow = '';
                        }
                    });
                }
                
                if (passwordField) {
                    passwordField.addEventListener('input', function() {
                        if (this.value.length >= 6) {
                            this.style.borderColor = '';
                            this.style.boxShadow = '';
                        }
                    });
                }
            }
        });
    }
    
    function isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
});

// Add fade-in animation for table rows
document.addEventListener('DOMContentLoaded', function() {
    const rows = document.querySelectorAll('.data-table tbody tr');
    rows.forEach((row, index) => {
        row.style.animationDelay = `${index * 0.05}s`;
        row.style.animation = 'fadeIn 0.3s ease-out forwards';
        row.style.opacity = '0';
    });
});

// Add animation styles
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .mobile-menu-btn {
        display: none !important;
    }
    
    @media (max-width: 768px) {
        .mobile-menu-btn {
            display: flex !important;
        }
    }
`;
document.head.appendChild(style);
</script>
</body>
</html>