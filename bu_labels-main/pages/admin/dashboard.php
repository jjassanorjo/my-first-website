<?php
// pages/admin/dashboard.php

require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is admin
if (!isLoggedIn() || $_SESSION['user_role'] != 'admin') {
    redirect('../../account.php');
}

$page_title = "Admin Dashboard";
$body_class = "admin-dashboard";

// Get statistics
$conn = getDBConnection();
$today = date('Y-m-d');
$firstDayOfMonth = date('Y-m-01');
$lastDayOfMonth = date('Y-m-t');

// Total sales
$sales_stmt = $conn->prepare("SELECT SUM(total_amount) as total_sales FROM orders WHERE order_status != 'cancelled'");
$sales_stmt->execute();
$total_sales = $sales_stmt->get_result()->fetch_assoc()['total_sales'] ?? 0;

// Monthly sales
$monthly_stmt = $conn->prepare("SELECT SUM(total_amount) as monthly_sales FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND order_status != 'cancelled'");
$monthly_stmt->bind_param("ss", $firstDayOfMonth, $lastDayOfMonth);
$monthly_stmt->execute();
$monthly_sales = $monthly_stmt->get_result()->fetch_assoc()['monthly_sales'] ?? 0;

// Today's sales
$today_stmt = $conn->prepare("SELECT SUM(total_amount) as today_sales FROM orders WHERE DATE(created_at) = ? AND order_status != 'cancelled'");
$today_stmt->bind_param("s", $today);
$today_stmt->execute();
$today_sales = $today_stmt->get_result()->fetch_assoc()['today_sales'] ?? 0;

// Total orders
$orders_stmt = $conn->prepare("SELECT COUNT(*) as total_orders FROM orders");
$orders_stmt->execute();
$total_orders = $orders_stmt->get_result()->fetch_assoc()['total_orders'];

// Pending orders
$pending_stmt = $conn->prepare("SELECT COUNT(*) as pending_orders FROM orders WHERE order_status = 'processing'");
$pending_stmt->execute();
$pending_orders = $pending_stmt->get_result()->fetch_assoc()['pending_orders'];

// Total products
$products_stmt = $conn->prepare("SELECT COUNT(*) as total_products FROM products");
$products_stmt->execute();
$total_products = $products_stmt->get_result()->fetch_assoc()['total_products'];

// Total users
$users_stmt = $conn->prepare("SELECT COUNT(*) as total_users FROM users");
$users_stmt->execute();
$total_users = $users_stmt->get_result()->fetch_assoc()['total_users'];

// Recent orders
$recent_stmt = $conn->prepare("SELECT o.*, u.name as customer_name FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC LIMIT 10");
$recent_stmt->execute();
$recent_orders = $recent_stmt->get_result();

// Low stock products
$low_stock_stmt = $conn->prepare("SELECT * FROM products WHERE (stock_main + stock_east + stock_west + stock_north + stock_south) <= 10 ORDER BY (stock_main + stock_east + stock_west + stock_north + stock_south) ASC LIMIT 10");
$low_stock_stmt->execute();
$low_stock_products = $low_stock_stmt->get_result();

// Top selling products
$top_products_stmt = $conn->prepare("SELECT p.name, SUM(oi.quantity) as total_sold FROM order_items oi JOIN products p ON oi.product_id = p.id GROUP BY p.id ORDER BY total_sold DESC LIMIT 5");
$top_products_stmt->execute();
$top_products = $top_products_stmt->get_result();

// Campus-wise sales
$campus_sales_stmt = $conn->prepare("SELECT pickup_campus, SUM(total_amount) as campus_sales FROM orders WHERE order_status != 'cancelled' GROUP BY pickup_campus");
$campus_sales_stmt->execute();
$campus_sales = $campus_sales_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BU Labels - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .welcome {
            color: var(--dark-gray);
            font-weight: 500;
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

        .btn-secondary {
            background: var(--secondary);
            color: var(--white);
        }

        .btn-secondary:hover {
            background: #dd6b20;
            transform: translateY(-1px);
            box-shadow: var(--box-shadow);
        }

        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.75rem;
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

        .stat-trend {
            margin-left: auto;
            font-size: 0.875rem;
            font-weight: 600;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
        }

        .stat-trend.up {
            background: rgba(72, 187, 120, 0.1);
            color: var(--success);
        }

        .stat-trend.down {
            background: rgba(245, 101, 101, 0.1);
            color: var(--danger);
        }

        /* Charts Row */
        .charts-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .chart-card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
        }

        .chart-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .chart-header h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary);
        }

        .chart-period {
            padding: 0.5rem;
            border: 1px solid var(--gray);
            border-radius: var(--border-radius);
            font-size: 0.875rem;
            background: var(--white);
        }

        .chart-container {
            padding: 1.5rem;
            height: 300px;
        }

        /* Tables Row */
        .tables-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .table-card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
        }

        .table-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-header h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary);
        }

        .view-all {
            font-size: 0.875rem;
            color: var(--primary);
            font-weight: 500;
            text-decoration: none;
        }

        .view-all:hover {
            text-decoration: underline;
        }

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

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-processing { background: #feebc8; color: #9c4221; }
        .status-ready_for_pickup { background: #bee3f8; color: #2c5282; }
        .status-picked_up { background: #c6f6d5; color: #22543d; }
        .status-cancelled { background: #fed7d7; color: #742a2a; }

        .status-critical { background: #fed7d7; color: #742a2a; }
        .status-warning { background: #feebc8; color: #9c4221; }
        .status-good { background: #c6f6d5; color: #22543d; }

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

        /* Stock Meter */
        .stock-meter {
            position: relative;
            height: 24px;
            background: var(--gray);
            border-radius: 12px;
            overflow: hidden;
        }

        .meter-bar {
            height: 100%;
            background: var(--success);
            transition: width 0.3s ease;
        }

        .meter-text {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--dark);
        }

        /* Quick Actions */
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .action-card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 1.5rem;
            transition: var(--transition);
        }

        .action-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .action-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            color: var(--white);
            margin-bottom: 1rem;
        }

        .action-card:nth-child(1) .action-icon { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .action-card:nth-child(2) .action-icon { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .action-card:nth-child(3) .action-icon { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .action-card:nth-child(4) .action-icon { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }

        .action-content h4 {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .action-content p {
            font-size: 0.875rem;
            color: var(--dark-gray);
            margin-bottom: 1rem;
            line-height: 1.5;
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

        /* Mobile Responsive */
        @media (max-width: 1024px) {
            .admin-sidebar {
                width: 240px;
            }
            
            .admin-content {
                margin-left: 240px;
            }
            
            .charts-row,
            .tables-row {
                grid-template-columns: 1fr;
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
                grid-template-columns: 1fr;
            }
            
            .charts-row {
                grid-template-columns: 1fr;
            }
            
            .chart-container {
                height: 250px;
            }
            
            .tables-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .admin-content {
                padding: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .chart-card,
            .table-card {
                margin: 0 -1rem;
                border-radius: 0;
            }
            
            .data-table {
                font-size: 0.75rem;
            }
            
            .data-table th,
            .data-table td {
                padding: 0.75rem 0.5rem;
            }
        }
    </style>
</head>
<body>
<div class="admin-container">
    <?php 
    // Include sidebar with current page detection
    $current_page = 'dashboard.php';
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
                    <li class="active">
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
                            <span class="badge"><?php echo $pending_orders; ?></span>
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
                    <li>
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
            <h1>Admin Dashboard</h1>
            <div class="admin-actions">
                <span class="welcome">Welcome, <?php echo sanitize($_SESSION['user']['name']); ?></span>
                <a href="../../account.php" class="btn btn-outline btn-small">
                    <i class="ri-user-line"></i> My Account
                </a>
                <a href="../../logout.php" class="btn btn-secondary btn-small">
                    <i class="ri-logout-box-line"></i> Logout
                </a>
            </div>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <i class="ri-checkbox-circle-line"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
        <?php endif; ?>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="ri-money-dollar-circle-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo formatPrice($total_sales); ?></h3>
                    <p>Total Sales</p>
                </div>
                <div class="stat-trend up">
                    <i class="ri-arrow-up-line"></i> 12.5%
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="ri-shopping-bag-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $total_orders; ?></h3>
                    <p>Total Orders</p>
                </div>
                <div class="stat-trend up">
                    <i class="ri-arrow-up-line"></i> 8.2%
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="ri-box-3-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $total_products; ?></h3>
                    <p>Total Products</p>
                </div>
                <div class="stat-trend down">
                    <i class="ri-arrow-down-line"></i> 3.1%
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="ri-user-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $total_users; ?></h3>
                    <p>Total Users</p>
                </div>
                <div class="stat-trend up">
                    <i class="ri-arrow-up-line"></i> 5.7%
                </div>
            </div>
        </div>
        
        <!-- Charts Row -->
        <div class="charts-row">
            <div class="chart-card">
                <div class="chart-header">
                    <h3>Sales Overview</h3>
                    <select class="chart-period" onchange="updateChartPeriod(this.value)">
                        <option value="month">This Month</option>
                        <option value="week">This Week</option>
                        <option value="year">This Year</option>
                    </select>
                </div>
                <div class="chart-container">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <div class="chart-header">
                    <h3>Campus Sales</h3>
                </div>
                <div class="chart-container">
                    <canvas id="campusSalesChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Tables Row -->
        <div class="tables-row">
            <div class="table-card">
                <div class="table-header">
                    <h3>Recent Orders</h3>
                    <a href="orders.php" class="view-all">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($order = $recent_orders->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $order['order_number']; ?></td>
                                <td><?php echo sanitize($order['customer_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                <td><?php echo formatPrice($order['total_amount']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $order['order_status']; ?>">
                                        <?php echo ucwords(str_replace('_', ' ', $order['order_status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="orders.php?action=view&id=<?php echo $order['id']; ?>" class="btn-icon">
                                        <i class="ri-eye-line"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="table-card">
                <div class="table-header">
                    <h3>Low Stock Products</h3>
                    <a href="products.php" class="view-all">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Stock</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($product = $low_stock_products->fetch_assoc()): 
                                $total_stock = $product['stock_main'] + $product['stock_east'] + $product['stock_west'] + $product['stock_north'] + $product['stock_south'];
                            ?>
                            <tr>
                                <td><?php echo sanitize($product['name']); ?></td>
                                <td>
                                    <?php
                                    $cat_stmt = $conn->prepare("SELECT name FROM categories WHERE id = ?");
                                    $cat_stmt->bind_param("i", $product['category_id']);
                                    $cat_stmt->execute();
                                    $category = $cat_stmt->get_result()->fetch_assoc();
                                    echo sanitize($category['name'] ?? 'N/A');
                                    ?>
                                </td>
                                <td>
                                    <div class="stock-meter">
                                        <div class="meter-bar" style="width: <?php echo min(100, ($total_stock / 10) * 100); ?>%"></div>
                                        <span class="meter-text"><?php echo $total_stock; ?> units</span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($total_stock <= 5): ?>
                                    <span class="status-badge status-critical">Critical</span>
                                    <?php elseif ($total_stock <= 10): ?>
                                    <span class="status-badge status-warning">Low</span>
                                    <?php else: ?>
                                    <span class="status-badge status-good">Good</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="products.php?action=edit&id=<?php echo $product['id']; ?>" class="btn-icon">
                                        <i class="ri-edit-line"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions-grid">
            <div class="action-card">
                <div class="action-icon">
                    <i class="ri-add-circle-line"></i>
                </div>
                <div class="action-content">
                    <h4>Add New Product</h4>
                    <p>Create a new product listing</p>
                    <a href="products.php?action=add" class="btn btn-small">Add Product</a>
                </div>
            </div>
            
            <div class="action-card">
                <div class="action-icon">
                    <i class="ri-file-list-line"></i>
                </div>
                <div class="action-content">
                    <h4>Generate Report</h4>
                    <p>Generate sales and inventory reports</p>
                    <button onclick="exportData('sales', 'pdf')" class="btn btn-small">Export PDF</button>
                </div>
            </div>
            
            <div class="action-card">
                <div class="action-icon">
                    <i class="ri-settings-3-line"></i>
                </div>
                <div class="action-content">
                    <h4>System Settings</h4>
                    <p>Configure store settings and preferences</p>
                    <a href="settings.php" class="btn btn-small">Settings</a>
                </div>
            </div>
            
            <div class="action-card">
                <div class="action-icon">
                    <i class="ri-user-add-line"></i>
                </div>
                <div class="action-content">
                    <h4>Add Admin User</h4>
                    <p>Add new admin or campus director</p>
                    <a href="users.php?action=add" class="btn btn-small">Add User</a>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize charts
    initCharts();
    
    // Mobile sidebar toggle
    initMobileMenu();
    
    // Add fade-in animations to cards
    animateCards();
});

function initCharts() {
    // Sales Chart
    const salesCtx = document.getElementById('salesChart');
    if (salesCtx) {
        const salesChart = new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Sales',
                    data: [12000, 19000, 15000, 25000, 22000, 30000, 28000, 35000, 32000, 40000, 38000, 45000],
                    borderColor: '#1a365d',
                    backgroundColor: 'rgba(26, 54, 93, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return '₱' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    }
                }
            }
        });
    }
    
    // Campus Sales Chart
    const campusCtx = document.getElementById('campusSalesChart');
    if (campusCtx) {
        const campusChart = new Chart(campusCtx, {
            type: 'bar',
            data: {
                labels: ['Main', 'East', 'West', 'North', 'South'],
                datasets: [{
                    label: 'Sales per Campus',
                    data: [45000, 28000, 32000, 19000, 24000],
                    backgroundColor: [
                        '#1a365d',
                        '#2d4a8a',
                        '#ed8936',
                        '#38b2ac',
                        '#718096'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return '₱' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
}

function updateChartPeriod(period) {
    // In a real application, this would fetch new data based on the period
    console.log('Updating chart period to:', period);
    // You would typically make an AJAX call here to get new data
}

function exportData(type, format) {
    // Show loading state
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(btn => {
        const originalText = btn.innerHTML;
        if (originalText.includes('Export')) {
            btn.innerHTML = '<i class="ri-loader-4-line spin"></i> Exporting...';
            btn.disabled = true;
            
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
                showNotification(`${type} exported successfully as ${format.toUpperCase()}`, 'success');
            }, 1500);
        }
    });
}

function showNotification(message, type) {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `alert alert-${type === 'success' ? 'success' : 'error'}`;
    notification.innerHTML = `
        <i class="ri-${type === 'success' ? 'checkbox-circle-line' : 'error-warning-line'}"></i>
        ${message}
    `;
    
    // Insert after header
    const header = document.querySelector('.admin-header');
    header.parentNode.insertBefore(notification, header.nextSibling);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

function initMobileMenu() {
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
}

function animateCards() {
    const cards = document.querySelectorAll('.stat-card, .chart-card, .table-card, .action-card');
    cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        card.classList.add('fade-in');
    });
}

// Add spin animation
const style = document.createElement('style');
style.textContent = `
    .spin {
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .fade-in {
        animation: fadeIn 0.5s ease-out forwards;
        opacity: 0;
    }
    
    @keyframes fadeIn {
        to {
            opacity: 1;
        }
    }
`;
document.head.appendChild(style);
</script>
</body>
</html>