<?php
// pages/admin/reports.php

require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is admin
if (!isLoggedIn() || $_SESSION['user_role'] != 'admin') {
    redirect('../../account.php');
}

$page_title = "Sales Reports";
$body_class = "admin-reports";

$conn = getDBConnection();

// Get report period
$period = isset($_GET['period']) ? $_GET['period'] : 'month';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Calculate date ranges based on period
switch ($period) {
    case 'today':
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d');
        break;
    case 'yesterday':
        $start_date = date('Y-m-d', strtotime('-1 day'));
        $end_date = date('Y-m-d', strtotime('-1 day'));
        break;
    case 'week':
        $start_date = date('Y-m-d', strtotime('monday this week'));
        $end_date = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'month':
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
        break;
    case 'year':
        $start_date = date('Y-01-01');
        $end_date = date('Y-12-31');
        break;
    case 'custom':
        // Use custom dates from GET parameters
        break;
}

// Get sales summary
$summary_stmt = $conn->prepare("SELECT 
    COUNT(*) as total_orders,
    SUM(CASE WHEN order_status != 'cancelled' THEN total_amount ELSE 0 END) as total_sales,
    AVG(CASE WHEN order_status != 'cancelled' THEN total_amount ELSE 0 END) as avg_order_value,
    COUNT(DISTINCT user_id) as total_customers
    FROM orders 
    WHERE DATE(created_at) BETWEEN ? AND ?");
$summary_stmt->bind_param("ss", $start_date, $end_date);
$summary_stmt->execute();
$summary = $summary_stmt->get_result()->fetch_assoc();

// Get daily sales for chart
$daily_sales_stmt = $conn->prepare("SELECT 
    DATE(created_at) as date,
    COUNT(*) as order_count,
    SUM(CASE WHEN order_status != 'cancelled' THEN total_amount ELSE 0 END) as daily_sales
    FROM orders 
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY DATE(created_at)");
$daily_sales_stmt->bind_param("ss", $start_date, $end_date);
$daily_sales_stmt->execute();
$daily_sales = $daily_sales_stmt->get_result();

// Get campus-wise sales
$campus_sales_stmt = $conn->prepare("SELECT 
    pickup_campus,
    COUNT(*) as order_count,
    SUM(CASE WHEN order_status != 'cancelled' THEN total_amount ELSE 0 END) as campus_sales
    FROM orders 
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY pickup_campus
    ORDER BY campus_sales DESC");
$campus_sales_stmt->bind_param("ss", $start_date, $end_date);
$campus_sales_stmt->execute();
$campus_sales = $campus_sales_stmt->get_result();

// Get product sales
$product_sales_stmt = $conn->prepare("SELECT 
    p.name,
    SUM(oi.quantity) as total_quantity,
    SUM(oi.quantity * oi.price) as total_sales
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.order_status != 'cancelled'
    GROUP BY p.id
    ORDER BY total_sales DESC
    LIMIT 10");
$product_sales_stmt->bind_param("ss", $start_date, $end_date);
$product_sales_stmt->execute();
$product_sales = $product_sales_stmt->get_result();

// Get payment method distribution
$payment_methods_stmt = $conn->prepare("SELECT 
    payment_method,
    COUNT(*) as order_count,
    SUM(CASE WHEN order_status != 'cancelled' THEN total_amount ELSE 0 END) as total_sales
    FROM orders 
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY payment_method");
$payment_methods_stmt->bind_param("ss", $start_date, $end_date);
$payment_methods_stmt->execute();
$payment_methods = $payment_methods_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BU Labels - Admin | Sales Reports</title>
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

        .btn-success {
            background: var(--success);
            color: var(--white);
        }

        .btn-success:hover {
            background: #38a169;
            transform: translateY(-1px);
            box-shadow: var(--box-shadow);
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

        .btn-outline {
            background: transparent;
            color: var(--primary);
            border: 1px solid var(--primary);
        }

        .btn-outline:hover {
            background: var(--primary);
            color: var(--white);
        }

        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.75rem;
        }

        /* Report Filters */
        .report-filters {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--box-shadow);
            margin-bottom: 2rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
            font-size: 0.875rem;
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

        .form-group input:disabled {
            background: var(--light);
            color: var(--dark-gray);
            cursor: not-allowed;
        }

        .report-period {
            text-align: center;
            padding: 1rem;
            background: var(--light);
            border-radius: var(--border-radius);
            margin-top: 1.5rem;
        }

        .report-period h4 {
            color: var(--primary);
            font-weight: 600;
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

        .stat-change {
            margin-left: auto;
            text-align: right;
        }

        .trend {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .trend.up {
            background: rgba(72, 187, 120, 0.1);
            color: var(--success);
        }

        .trend.down {
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
        }

        .chart-header h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary);
        }

        .chart-container {
            padding: 1.5rem;
            height: 300px;
        }

        /* Cards and Tables */
        .row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .col-2 {
            grid-column: span 1;
        }

        .card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
        }

        .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--gray);
        }

        .card-header h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary);
        }

        .card-body {
            padding: 1.5rem;
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

        .price {
            color: var(--primary);
            font-weight: 600;
        }

        /* Progress Bars */
        .progress {
            position: relative;
            height: 24px;
            background: var(--gray);
            border-radius: 12px;
            overflow: hidden;
            margin: 0.5rem 0;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            transition: width 0.3s ease;
            position: relative;
        }

        .progress span {
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
            color: var(--white);
            z-index: 1;
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-success {
            background: #c6f6d5;
            color: #22543d;
        }

        .badge-warning {
            background: #feebc8;
            color: #9c4221;
        }

        /* Campus Performance */
        .campus-performance {
            margin-top: 2rem;
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
            .row {
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
                grid-template-columns: repeat(2, 1fr);
            }
            
            .charts-row {
                grid-template-columns: 1fr;
            }
            
            .chart-container {
                height: 250px;
            }
            
            .row {
                grid-template-columns: 1fr;
            }
            
            .form-row {
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
            .card {
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
            
            .admin-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .admin-actions .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
<div class="admin-container">
    <?php 
    // Include sidebar with current page detection
    $current_page = 'reports.php';
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
                    <li class="active">
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
            <h1>Sales Reports</h1>
            
            <div class="admin-actions">
                <button onclick="printReport()" class="btn btn-primary">
                    <i class="ri-printer-line"></i> Print Report
                </button>
                <button onclick="exportToExcel()" class="btn btn-success">
                    <i class="ri-file-excel-line"></i> Export Excel
                </button>
                <button onclick="exportToPDF()" class="btn btn-danger">
                    <i class="ri-file-pdf-line"></i> Export PDF
                </button>
            </div>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <i class="ri-checkbox-circle-line"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
        <?php endif; ?>
        
        <!-- Report Filters -->
        <div class="report-filters">
            <form method="GET" class="report-filters-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="period">Report Period</label>
                        <select id="period" name="period" onchange="updatePeriod()">
                            <option value="today" <?php echo ($period == 'today') ? 'selected' : ''; ?>>Today</option>
                            <option value="yesterday" <?php echo ($period == 'yesterday') ? 'selected' : ''; ?>>Yesterday</option>
                            <option value="week" <?php echo ($period == 'week') ? 'selected' : ''; ?>>This Week</option>
                            <option value="month" <?php echo ($period == 'month') ? 'selected' : ''; ?>>This Month</option>
                            <option value="year" <?php echo ($period == 'year') ? 'selected' : ''; ?>>This Year</option>
                            <option value="custom" <?php echo ($period == 'custom') ? 'selected' : ''; ?>>Custom Range</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="start_date">Start Date</label>
                        <input type="date" id="start_date" name="start_date" 
                               value="<?php echo $start_date; ?>"
                               <?php echo ($period != 'custom') ? 'disabled' : ''; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label for="end_date">End Date</label>
                        <input type="date" id="end_date" name="end_date" 
                               value="<?php echo $end_date; ?>"
                               <?php echo ($period != 'custom') ? 'disabled' : ''; ?>>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="ri-filter-line"></i> Apply Filters
                        </button>
                        <a href="reports.php" class="btn btn-outline">Reset</a>
                    </div>
                </div>
            </form>
            
            <div class="report-period">
                <h4>Reporting Period: <?php echo date('F j, Y', strtotime($start_date)); ?> to <?php echo date('F j, Y', strtotime($end_date)); ?></h4>
            </div>
        </div>
        
        <!-- Summary Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="ri-money-dollar-circle-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo formatPrice($summary['total_sales'] ?? 0); ?></h3>
                    <p>Total Sales</p>
                </div>
                <div class="stat-change">
                    <?php 
                    $change = 12.5;
                    $trend = ($change >= 0) ? 'up' : 'down';
                    ?>
                    <span class="trend <?php echo $trend; ?>">
                        <i class="ri-arrow-<?php echo $trend; ?>-line"></i>
                        <?php echo abs($change); ?>%
                    </span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="ri-shopping-bag-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $summary['total_orders'] ?? 0; ?></h3>
                    <p>Total Orders</p>
                </div>
                <div class="stat-change">
                    <?php $change = 8.2; $trend = 'up'; ?>
                    <span class="trend <?php echo $trend; ?>">
                        <i class="ri-arrow-<?php echo $trend; ?>-line"></i>
                        <?php echo abs($change); ?>%
                    </span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="ri-user-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $summary['total_customers'] ?? 0; ?></h3>
                    <p>Customers</p>
                </div>
                <div class="stat-change">
                    <?php $change = 5.7; $trend = 'up'; ?>
                    <span class="trend <?php echo $trend; ?>">
                        <i class="ri-arrow-<?php echo $trend; ?>-line"></i>
                        <?php echo abs($change); ?>%
                    </span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="ri-bar-chart-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo formatPrice($summary['avg_order_value'] ?? 0); ?></h3>
                    <p>Avg. Order Value</p>
                </div>
                <div class="stat-change">
                    <?php $change = 3.1; $trend = 'up'; ?>
                    <span class="trend <?php echo $trend; ?>">
                        <i class="ri-arrow-<?php echo $trend; ?>-line"></i>
                        <?php echo abs($change); ?>%
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Charts Row -->
        <div class="charts-row">
            <div class="chart-card">
                <div class="chart-header">
                    <h3>Daily Sales Trend</h3>
                </div>
                <div class="chart-container">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <div class="chart-header">
                    <h3>Campus Sales Distribution</h3>
                </div>
                <div class="chart-container">
                    <canvas id="campusChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Data Tables -->
        <div class="row">
            <div class="col-2">
                <div class="card">
                    <div class="card-header">
                        <h3>Top Selling Products</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Quantity</th>
                                        <th>Sales</th>
                                        <th>% of Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_product_sales = 0;
                                    $product_sales->data_seek(0);
                                    while ($product = $product_sales->fetch_assoc()):
                                        $total_product_sales += $product['total_sales'];
                                    endwhile;
                                    
                                    $product_sales->data_seek(0);
                                    while ($product = $product_sales->fetch_assoc()):
                                        $percentage = ($total_product_sales > 0) ? ($product['total_sales'] / $total_product_sales * 100) : 0;
                                    ?>
                                    <tr>
                                        <td><?php echo sanitize($product['name']); ?></td>
                                        <td><?php echo $product['total_quantity']; ?></td>
                                        <td class="price"><?php echo formatPrice($product['total_sales']); ?></td>
                                        <td>
                                            <div class="progress">
                                                <div class="progress-bar" style="width: <?php echo $percentage; ?>%"></div>
                                                <span><?php echo number_format($percentage, 1); ?>%</span>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-2">
                <div class="card">
                    <div class="card-header">
                        <h3>Payment Methods</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Payment Method</th>
                                        <th>Orders</th>
                                        <th>Sales</th>
                                        <th>% of Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_payment_sales = 0;
                                    $payment_methods->data_seek(0);
                                    while ($method = $payment_methods->fetch_assoc()):
                                        $total_payment_sales += $method['total_sales'];
                                    endwhile;
                                    
                                    $payment_methods->data_seek(0);
                                    while ($method = $payment_methods->fetch_assoc()):
                                        $percentage = ($total_payment_sales > 0) ? ($method['total_sales'] / $total_payment_sales * 100) : 0;
                                    ?>
                                    <tr>
                                        <td><?php echo ucwords(str_replace('_', ' ', $method['payment_method'])); ?></td>
                                        <td><?php echo $method['order_count']; ?></td>
                                        <td class="price"><?php echo formatPrice($method['total_sales']); ?></td>
                                        <td>
                                            <div class="progress">
                                                <div class="progress-bar" style="width: <?php echo $percentage; ?>%"></div>
                                                <span><?php echo number_format($percentage, 1); ?>%</span>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Campus Details -->
        <div class="card campus-performance">
            <div class="card-header">
                <h3>Campus Performance</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Campus</th>
                                <th>Orders</th>
                                <th>Sales Revenue</th>
                                <th>Avg. Order Value</th>
                                <th>% of Total</th>
                                <th>Performance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $campus_sales->data_seek(0);
                            while ($campus = $campus_sales->fetch_assoc()):
                                $avg_value = ($campus['order_count'] > 0) ? $campus['campus_sales'] / $campus['order_count'] : 0;
                                $percentage = ($summary['total_sales'] > 0) ? ($campus['campus_sales'] / $summary['total_sales'] * 100) : 0;
                            ?>
                            <tr>
                                <td><?php echo ucfirst($campus['pickup_campus']); ?> Campus</td>
                                <td><?php echo $campus['order_count']; ?></td>
                                <td class="price"><?php echo formatPrice($campus['campus_sales']); ?></td>
                                <td class="price"><?php echo formatPrice($avg_value); ?></td>
                                <td>
                                    <div class="progress">
                                        <div class="progress-bar" style="width: <?php echo $percentage; ?>%"></div>
                                        <span><?php echo number_format($percentage, 1); ?>%</span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($percentage > 30): ?>
                                    <span class="badge badge-success">Excellent</span>
                                    <?php elseif ($percentage > 20): ?>
                                    <span class="badge badge-warning">Good</span>
                                    <?php else: ?>
                                    <span class="badge">Average</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
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
    
    // Initialize period controls
    updatePeriod();
    
    // Add animations
    animateCards();
});

function updatePeriod() {
    const period = document.getElementById('period').value;
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    
    if (period !== 'custom') {
        startDate.disabled = true;
        endDate.disabled = true;
    } else {
        startDate.disabled = false;
        endDate.disabled = false;
    }
}

function printReport() {
    const buttons = document.querySelectorAll('.admin-actions .btn');
    buttons.forEach(btn => {
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="ri-loader-4-line spin"></i> Processing...';
        btn.disabled = true;
        
        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }, 1500);
    });
    
    window.print();
}

function exportToExcel() {
    const buttons = document.querySelectorAll('.admin-actions .btn');
    buttons.forEach(btn => {
        if (btn.textContent.includes('Excel')) {
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="ri-loader-4-line spin"></i> Exporting...';
            btn.disabled = true;
            
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
                showNotification('Excel report exported successfully', 'success');
            }, 2000);
        }
    });
}

function exportToPDF() {
    const buttons = document.querySelectorAll('.admin-actions .btn');
    buttons.forEach(btn => {
        if (btn.textContent.includes('PDF')) {
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="ri-loader-4-line spin"></i> Exporting...';
            btn.disabled = true;
            
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
                showNotification('PDF report exported successfully', 'success');
            }, 2000);
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

function initCharts() {
    if (typeof Chart === 'undefined') {
        console.error('Chart.js is not loaded');
        return;
    }

    // Sales Chart
    const salesCtx = document.getElementById('salesChart');
    if (salesCtx) {
        const dailySalesData = [
            <?php 
            $daily_sales->data_seek(0);
            $data = [];
            while ($day = $daily_sales->fetch_assoc()) {
                $data[] = $day['daily_sales'];
            }
            echo implode(', ', $data);
            ?>
        ];
        
        const labels = [
            <?php 
            $daily_sales->data_seek(0);
            $labels = [];
            while ($day = $daily_sales->fetch_assoc()) {
                $labels[] = "'" . date('M j', strtotime($day['date'])) . "'";
            }
            echo implode(', ', $labels);
            ?>
        ];
        
        const salesChart = new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: labels.length ? labels : ['No Data'],
                datasets: [{
                    label: 'Daily Sales',
                    data: dailySalesData.length ? dailySalesData : [0],
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
    
    // Campus Chart
    const campusCtx = document.getElementById('campusChart');
    if (campusCtx) {
        const campusData = [
            <?php 
            $campus_sales->data_seek(0);
            $data = [];
            while ($campus = $campus_sales->fetch_assoc()) {
                $data[] = $campus['campus_sales'];
            }
            echo implode(', ', $data);
            ?>
        ];
        
        const campusLabels = [
            <?php 
            $campus_sales->data_seek(0);
            $labels = [];
            while ($campus = $campus_sales->fetch_assoc()) {
                $labels[] = "'" . ucfirst($campus['pickup_campus']) . "'";
            }
            echo implode(', ', $labels);
            ?>
        ];
        
        const campusColors = [
            '#1a365d',
            '#2d4a8a',
            '#ed8936',
            '#38b2ac',
            '#718096'
        ];
        
        const campusChart = new Chart(campusCtx, {
            type: 'bar',
            data: {
                labels: campusLabels.length ? campusLabels : ['No Data'],
                datasets: [{
                    label: 'Sales per Campus',
                    data: campusData.length ? campusData : [0],
                    backgroundColor: campusColors
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
    const cards = document.querySelectorAll('.stat-card, .chart-card, .card');
    cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        card.style.animation = 'fadeIn 0.5s ease-out forwards';
        card.style.opacity = '0';
    });
    
    // Add spin animation
    const style = document.createElement('style');
    style.textContent = `
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
        
        .spin {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
}
</script>
</body>
</html>