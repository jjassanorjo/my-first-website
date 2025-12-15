<?php
// pages/campus-director/dashboard.php

require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is campus director
if (!isLoggedIn() || $_SESSION['user_role'] != 'director') {
    redirect('../../account.php');
}

$page_title = "Campus Director Dashboard";
$body_class = "director-dashboard";

$campus = $_SESSION['user']['campus'];
$conn = getDBConnection();

// Get campus statistics
$today = date('Y-m-d');
$firstDayOfMonth = date('Y-m-01');
$lastDayOfMonth = date('Y-m-t');

// Campus sales
$sales_stmt = $conn->prepare("SELECT SUM(total_amount) as campus_sales FROM orders WHERE pickup_campus = ? AND order_status != 'cancelled'");
$sales_stmt->bind_param("s", $campus);
$sales_stmt->execute();
$campus_sales = $sales_stmt->get_result()->fetch_assoc()['campus_sales'] ?? 0;

// Monthly sales
$monthly_stmt = $conn->prepare("SELECT SUM(total_amount) as monthly_sales FROM orders WHERE pickup_campus = ? AND DATE(created_at) BETWEEN ? AND ? AND order_status != 'cancelled'");
$monthly_stmt->bind_param("sss", $campus, $firstDayOfMonth, $lastDayOfMonth);
$monthly_stmt->execute();
$monthly_sales = $monthly_stmt->get_result()->fetch_assoc()['monthly_sales'] ?? 0;

// Today's sales
$today_stmt = $conn->prepare("SELECT SUM(total_amount) as today_sales FROM orders WHERE pickup_campus = ? AND DATE(created_at) = ? AND order_status != 'cancelled'");
$today_stmt->bind_param("ss", $campus, $today);
$today_stmt->execute();
$today_sales = $today_stmt->get_result()->fetch_assoc()['today_sales'] ?? 0;

// Total orders
$orders_stmt = $conn->prepare("SELECT COUNT(*) as total_orders FROM orders WHERE pickup_campus = ?");
$orders_stmt->bind_param("s", $campus);
$orders_stmt->execute();
$total_orders = $orders_stmt->get_result()->fetch_assoc()['total_orders'];

// Pending orders
$pending_stmt = $conn->prepare("SELECT COUNT(*) as pending_orders FROM orders WHERE pickup_campus = ? AND order_status = 'processing'");
$pending_stmt->bind_param("s", $campus);
$pending_stmt->execute();
$pending_orders = $pending_stmt->get_result()->fetch_assoc()['pending_orders'];

// Ready for pickup
$ready_stmt = $conn->prepare("SELECT COUNT(*) as ready_orders FROM orders WHERE pickup_campus = ? AND order_status = 'ready_for_pickup'");
$ready_stmt->bind_param("s", $campus);
$ready_stmt->execute();
$ready_orders = $ready_stmt->get_result()->fetch_assoc()['ready_orders'];

// Recent campus orders
$recent_stmt = $conn->prepare("SELECT o.*, u.name as customer_name FROM orders o JOIN users u ON o.user_id = u.id WHERE o.pickup_campus = ? ORDER BY o.created_at DESC LIMIT 10");
$recent_stmt->bind_param("s", $campus);
$recent_stmt->execute();
$recent_orders = $recent_stmt->get_result();

// Low stock products for this campus
$stock_field = 'stock_' . $campus;
$low_stock_stmt = $conn->prepare("SELECT * FROM products WHERE $stock_field <= 10 ORDER BY $stock_field ASC LIMIT 10");
$low_stock_stmt->execute();
$low_stock_products = $low_stock_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BU Labels - <?php echo $page_title; ?></title>
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

        /* Sidebar (same as orders.php) */
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
            margin-bottom: 0.25rem;
        }

        .sidebar-header .campus-name {
            font-size: 0.9rem;
            color: rgba(255,255,255,0.7);
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

        /* Quick Actions */
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
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

        .view-all {
            font-size: 0.875rem;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .view-all:hover {
            text-decoration: underline;
        }

        .card-body {
            padding: 1.5rem;
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

        .price {
            color: var(--success);
            font-weight: 600;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: inline-flex;
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

        .btn-success {
            background: #c6f6d5;
            color: #22543d;
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

        /* Timeline for Pickup Schedule */
        .timeline {
            position: relative;
            padding-left: 2rem;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--gray);
        }

        .timeline-item {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .timeline-time {
            position: absolute;
            left: -2rem;
            top: 0;
            width: 60px;
            font-weight: 600;
            color: var(--primary);
        }

        .timeline-content {
            background: var(--light);
            padding: 1rem;
            border-radius: var(--border-radius);
        }

        .timeline-content h4 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--primary);
        }

        .timeline-content p {
            font-size: 0.875rem;
            color: var(--dark-gray);
            margin-bottom: 0.25rem;
        }

        .timeline-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.75rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--dark-gray);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        /* Charts */
        .chart-container {
            padding: 1.5rem;
            height: 250px;
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
                grid-template-columns: 1fr;
            }
            
            .quick-actions-grid {
                grid-template-columns: 1fr;
            }
            
            .timeline-actions {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .admin-content {
                padding: 1rem;
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
    <?php include 'sidebar.php'; ?>
    
    <main class="admin-content">
        <div class="admin-header">
            <h1>Campus Director Dashboard</h1>
            <div class="admin-actions">
                <span class="welcome">Welcome, <?php echo sanitize($_SESSION['user']['name']); ?> (<?php echo ucfirst($campus); ?> Campus)</span>
                <a href="../../account.php" class="btn btn-outline btn-small">
                    <i class="ri-user-line"></i> My Account
                </a>
                <a href="../../logout.php" class="btn btn-secondary btn-small">
                    <i class="ri-logout-box-line"></i> Logout
                </a>
            </div>
        </div>
        
        <!-- Campus Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="ri-money-dollar-circle-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo formatPrice($campus_sales); ?></h3>
                    <p>Total Campus Sales</p>
                </div>
                <div class="stat-trend up">
                    <i class="ri-arrow-up-line"></i> 15.2%
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
                    <i class="ri-arrow-up-line"></i> 10.5%
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="ri-calendar-event-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $pending_orders + $ready_orders; ?></h3>
                    <p>Pending Pickups</p>
                </div>
                <div class="stat-trend up">
                    <i class="ri-arrow-up-line"></i> 8.7%
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="ri-bar-chart-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo formatPrice($monthly_sales); ?></h3>
                    <p>Monthly Sales</p>
                </div>
                <div class="stat-trend up">
                    <i class="ri-arrow-up-line"></i> 12.3%
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions-grid">
            <div class="action-card">
                <div class="action-icon">
                    <i class="ri-checkbox-circle-line"></i>
                </div>
                <div class="action-content">
                    <h4>Process Pickups</h4>
                    <p><?php echo $ready_orders; ?> orders ready for pickup</p>
                    <a href="orders.php?status=ready_for_pickup" class="btn btn-primary btn-small">View Ready Orders</a>
                </div>
            </div>
            
            <div class="action-card">
                <div class="action-icon">
                    <i class="ri-alarm-warning-line"></i>
                </div>
                <div class="action-content">
                    <h4>Low Stock Alert</h4>
                    <p><?php echo $low_stock_products->num_rows; ?> products low in stock</p>
                    <a href="inventory.php?action=lowstock" class="btn btn-warning btn-small">View Low Stock</a>
                </div>
            </div>
            
            <div class="action-card">
                <div class="action-icon">
                    <i class="ri-file-list-line"></i>
                </div>
                <div class="action-content">
                    <h4>Daily Report</h4>
                    <p>Generate today's sales report</p>
                    <a href="reports.php?type=daily" class="btn btn-success btn-small">Generate Report</a>
                </div>
            </div>
            
            <div class="action-card">
                <div class="action-icon">
                    <i class="ri-calendar-event-line"></i>
                </div>
                <div class="action-content">
                    <h4>Pickup Schedule</h4>
                    <p>View today's pickup schedule</p>
                    <a href="pickup.php" class="btn btn-info btn-small">View Schedule</a>
                </div>
            </div>
        </div>
        
        <!-- Recent Orders -->
        <div class="card">
            <div class="card-header">
                <h3>Recent Campus Orders</h3>
                <a href="orders.php" class="view-all">View All</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_orders->num_rows > 0): ?>
                                <?php while ($order = $recent_orders->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $order['order_number']; ?></td>
                                    <td><?php echo sanitize($order['customer_name']); ?></td>
                                    <td class="price"><?php echo formatPrice($order['total_amount']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $order['order_status']; ?>">
                                            <?php echo ucwords(str_replace('_', ' ', $order['order_status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="orders.php?action=view&id=<?php echo $order['id']; ?>" class="btn-icon">
                                            <i class="ri-eye-line"></i>
                                        </a>
                                        <?php if ($order['order_status'] == 'ready_for_pickup'): ?>
                                        <button onclick="markAsPickedUp(<?php echo $order['id']; ?>)" class="btn-icon btn-success" title="Mark as Picked Up">
                                            <i class="ri-check-line"></i>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">
                                        <div class="empty-state">
                                            <i class="ri-shopping-bag-line"></i>
                                            <p>No recent orders</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Low Stock Products -->
        <div class="card">
            <div class="card-header">
                <h3>Low Stock Products</h3>
                <a href="inventory.php?action=lowstock" class="view-all">View All</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Current Stock</th>
                                <th>Min Level</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($low_stock_products->num_rows > 0): ?>
                                <?php while ($product = $low_stock_products->fetch_assoc()): 
                                    $current_stock = $product['stock_' . $campus];
                                    $min_level = 10;
                                ?>
                                <tr>
                                    <td><?php echo sanitize($product['name']); ?></td>
                                    <td>
                                        <div class="stock-meter">
                                            <div class="meter-bar" style="width: <?php echo min(100, ($current_stock / $min_level) * 100); ?>%"></div>
                                            <span class="meter-text"><?php echo $current_stock; ?></span>
                                        </div>
                                    </td>
                                    <td><?php echo $min_level; ?></td>
                                    <td>
                                        <?php if ($current_stock <= 3): ?>
                                        <span class="status-badge status-critical">Critical</span>
                                        <?php elseif ($current_stock <= 5): ?>
                                        <span class="status-badge status-warning">Low</span>
                                        <?php else: ?>
                                        <span class="status-badge status-good">Good</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button onclick="requestStock(<?php echo $product['id']; ?>)" class="btn btn-warning btn-small">
                                            Request Stock
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">
                                        <div class="empty-state">
                                            <i class="ri-checkbox-circle-line"></i>
                                            <p>No low stock products</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Today's Pickups -->
        <div class="card">
            <div class="card-header">
                <h3>Today's Pickup Schedule</h3>
                <a href="pickup.php" class="view-all">View Full Schedule</a>
            </div>
            <div class="card-body">
                <div class="pickup-schedule">
                    <?php
                    $today_pickups_stmt = $conn->prepare("SELECT o.*, u.name as customer_name FROM orders o JOIN users u ON o.user_id = u.id WHERE o.pickup_campus = ? AND DATE(o.created_at) = ? AND o.order_status IN ('ready_for_pickup', 'processing') ORDER BY o.created_at");
                    $today_pickups_stmt->bind_param("ss", $campus, $today);
                    $today_pickups_stmt->execute();
                    $today_pickups = $today_pickups_stmt->get_result();
                    
                    if ($today_pickups->num_rows > 0):
                    ?>
                    <div class="timeline">
                        <?php while ($order = $today_pickups->fetch_assoc()): ?>
                        <div class="timeline-item">
                            <div class="timeline-time"><?php echo date('g:i A', strtotime($order['created_at'])); ?></div>
                            <div class="timeline-content">
                                <h4>Order #<?php echo $order['order_number']; ?></h4>
                                <p>Customer: <?php echo sanitize($order['customer_name']); ?></p>
                                <p>Amount: <?php echo formatPrice($order['total_amount']); ?></p>
                                <div class="timeline-actions">
                                    <span class="status-badge status-<?php echo $order['order_status']; ?>">
                                        <?php echo ucwords(str_replace('_', ' ', $order['order_status'])); ?>
                                    </span>
                                    <a href="orders.php?action=view&id=<?php echo $order['id']; ?>" class="btn btn-small btn-outline">View Details</a>
                                    <?php if ($order['order_status'] == 'ready_for_pickup'): ?>
                                    <button onclick="markAsPickedUp(<?php echo $order['id']; ?>)" class="btn btn-small btn-primary">Mark as Picked Up</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="ri-calendar-line"></i>
                        <p>No pickups scheduled for today</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
function markAsPickedUp(orderId) {
    if (confirm('Mark this order as picked up?')) {
        fetch('../../api/director.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=mark_picked_up&order_id=${orderId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

function requestStock(productId) {
    const quantity = prompt('Enter quantity to request:');
    if (quantity && !isNaN(quantity) && quantity > 0) {
        fetch('../../api/director.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=request_stock&product_id=${productId}&quantity=${quantity}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Stock request submitted successfully!');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

// Mobile menu toggle
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
});
</script>
</body>
</html>