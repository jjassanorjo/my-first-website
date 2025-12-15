<?php
// pages/campus-director/pickup.php

require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is campus director
if (!isLoggedIn() || $_SESSION['user_role'] != 'director') {
    redirect('../../account.php');
}

$page_title = "Pickup Management";
$body_class = "director-pickup";

$campus = $_SESSION['user']['campus'];
$conn = getDBConnection();

// Get date filter
$date_filter = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$view_type = isset($_GET['view']) ? $_GET['view'] : 'daily';

// Calculate date range based on view type
switch ($view_type) {
    case 'daily':
        $start_date = $date_filter;
        $end_date = $date_filter;
        break;
    case 'weekly':
        $start_date = date('Y-m-d', strtotime('monday this week', strtotime($date_filter)));
        $end_date = date('Y-m-d', strtotime('sunday this week', strtotime($date_filter)));
        break;
    case 'monthly':
        $start_date = date('Y-m-01', strtotime($date_filter));
        $end_date = date('Y-m-t', strtotime($date_filter));
        break;
}

// Get pickup statistics
$stats_stmt = $conn->prepare("SELECT 
    COUNT(*) as total_pickups,
    SUM(CASE WHEN order_status = 'ready_for_pickup' THEN 1 ELSE 0 END) as ready_pickups,
    SUM(CASE WHEN order_status = 'picked_up' THEN 1 ELSE 0 END) as completed_pickups,
    SUM(CASE WHEN order_status = 'picked_up' THEN total_amount ELSE 0 END) as total_collected
    FROM orders 
    WHERE pickup_campus = ? AND DATE(created_at) BETWEEN ? AND ?");
$stats_stmt->bind_param("sss", $campus, $start_date, $end_date);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

// Get pickup schedule
$pickup_stmt = $conn->prepare("SELECT 
    o.*, 
    u.name as customer_name,
    u.email,
    TIME(o.created_at) as pickup_time
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    WHERE o.pickup_campus = ? 
    AND DATE(o.created_at) BETWEEN ? AND ?
    AND o.order_status IN ('processing', 'ready_for_pickup')
    ORDER BY 
        CASE o.order_status 
            WHEN 'ready_for_pickup' THEN 1
            WHEN 'processing' THEN 2
        END,
        o.created_at");
$pickup_stmt->bind_param("sss", $campus, $start_date, $end_date);
$pickup_stmt->execute();
$pickup_schedule = $pickup_stmt->get_result();

// Get completed pickups
$completed_stmt = $conn->prepare("SELECT 
    o.*, 
    u.name as customer_name,
    TIME(o.updated_at) as pickup_time
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    WHERE o.pickup_campus = ? 
    AND DATE(o.updated_at) BETWEEN ? AND ?
    AND o.order_status = 'picked_up'
    ORDER BY o.updated_at DESC
    LIMIT 20");
$completed_stmt->bind_param("sss", $campus, $start_date, $end_date);
$completed_stmt->execute();
$completed_pickups = $completed_stmt->get_result();

// Get pickup times distribution
$times_stmt = $conn->prepare("SELECT 
    HOUR(created_at) as pickup_hour,
    COUNT(*) as order_count
    FROM orders 
    WHERE pickup_campus = ? 
    AND DATE(created_at) BETWEEN ? AND ?
    GROUP BY HOUR(created_at)
    ORDER BY pickup_hour");
$times_stmt->bind_param("sss", $campus, $start_date, $end_date);
$times_stmt->execute();
$times_distribution = $times_stmt->get_result();
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

        /* Sidebar (same as other pages) */
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

        .btn-secondary {
            background: var(--secondary);
            color: var(--white);
        }

        .btn-secondary:hover {
            background: #dd6b20;
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

        .btn-info {
            background: var(--accent);
            color: var(--white);
        }

        .btn-info:hover {
            background: #319795;
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

        .btn-sm {
            padding: 0.375rem 0.75rem;
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
        .stat-icon.ready { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .stat-icon.completed { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stat-icon.revenue { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }

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

        .stat-info small {
            font-size: 0.75rem;
            color: var(--dark-gray);
            display: block;
            margin-top: 0.25rem;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
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

        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 500;
            background: var(--gray);
            color: var(--dark);
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Tables */
        .table-responsive {
            overflow-x: auto;
        }

        .admin-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        .admin-table thead {
            background: var(--light);
        }

        .admin-table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--dark);
            border-bottom: 2px solid var(--gray);
        }

        .admin-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--gray);
            vertical-align: middle;
        }

        .admin-table tbody tr:hover {
            background: var(--light);
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-processing { background: #feebc8; color: #9c4221; }
        .status-ready_for_pickup { background: #bee3f8; color: #2c5282; }
        .status-picked_up { background: #c6f6d5; color: #22543d; }

        .status-paid { color: var(--success); }
        .status-pending { color: var(--warning); }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        /* Filters */
        .filters-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-group label {
            font-weight: 500;
            white-space: nowrap;
        }

        .form-control, .form-select {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--gray);
            border-radius: var(--border-radius);
            font-size: 0.875rem;
            transition: var(--transition);
        }

        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 54, 93, 0.1);
        }

        /* Chart Container */
        .chart-container {
            padding: 1.5rem;
            height: 250px;
            position: relative;
        }

        .chart-legend {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--gray);
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.5rem;
        }

        .legend-time {
            width: 60px;
            font-size: 0.875rem;
            color: var(--dark-gray);
        }

        .legend-bar {
            flex: 1;
            height: 8px;
            background: var(--gray);
            border-radius: 4px;
            overflow: hidden;
        }

        .bar-fill {
            height: 100%;
            background: var(--primary);
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .legend-count {
            width: 30px;
            text-align: right;
            font-size: 0.875rem;
            font-weight: 600;
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

        .empty-state p {
            margin-bottom: 1rem;
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
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--white);
            border-radius: var(--border-radius);
            width: 90%;
            max-width: 500px;
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
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary);
            margin: 0;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--dark-gray);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--gray);
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }

        /* Bulk Actions */
        .bulk-actions {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 1rem 1.5rem;
            box-shadow: var(--box-shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .bulk-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .bulk-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        /* Order Details */
        .order-details {
            display: grid;
            gap: 1rem;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--gray);
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .order-items {
            margin-top: 1rem;
        }

        .order-item {
            padding: 1rem;
            background: var(--light);
            border-radius: var(--border-radius);
            margin-bottom: 0.75rem;
        }

        .order-item:last-child {
            margin-bottom: 0;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        .summary-table th,
        .summary-table td {
            padding: 0.75rem;
            border-bottom: 1px solid var(--gray);
            text-align: left;
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
            
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .filters-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group {
                flex-direction: column;
                align-items: stretch;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: stretch;
            }
            
            .action-buttons button {
                width: 100%;
            }
            
            .bulk-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .bulk-buttons {
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .admin-content {
                padding: 1rem;
            }
            
            .admin-table {
                font-size: 0.75rem;
            }
            
            .admin-table th,
            .admin-table td {
                padding: 0.75rem 0.5rem;
            }
            
            .chart-container {
                height: 200px;
            }
        }
    </style>
</head>
<body>
<div class="admin-container">
    <?php include 'sidebar.php'; ?>
    
    <main class="admin-content">
        <div class="admin-header">
            <h1>Pickup Management</h1>
            <div class="admin-actions">
                <button onclick="printPickupSchedule()" class="btn btn-primary">
                    <i class="ri-printer-line"></i> Print Schedule
                </button>
                <button onclick="sendBulkNotifications()" class="btn btn-success">
                    <i class="ri-mail-send-line"></i> Send Notifications
                </button>
            </div>
        </div>
        
        <!-- Pickup Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="ri-calendar-event-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['total_pickups'] ?? 0; ?></h3>
                    <p>Total Pickups</p>
                    <small><?php echo date('M j, Y', strtotime($start_date)); ?> 
                        <?php if ($view_type != 'daily'): ?>
                        - <?php echo date('M j, Y', strtotime($end_date)); ?>
                        <?php endif; ?>
                    </small>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon ready">
                    <i class="ri-checkbox-circle-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['ready_pickups'] ?? 0; ?></h3>
                    <p>Ready for Pickup</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon completed">
                    <i class="ri-check-double-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['completed_pickups'] ?? 0; ?></h3>
                    <p>Completed</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon revenue">
                    <i class="ri-money-dollar-circle-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo formatPrice($stats['total_collected'] ?? 0); ?></h3>
                    <p>Total Collected</p>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters-bar">
            <div class="filter-group">
                <label for="viewType">View:</label>
                <select id="viewType" onchange="updateViewType(this.value)" class="form-select">
                    <option value="daily" <?php echo $view_type == 'daily' ? 'selected' : ''; ?>>Daily</option>
                    <option value="weekly" <?php echo $view_type == 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                    <option value="monthly" <?php echo $view_type == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="dateFilter">Date:</label>
                <input type="date" id="dateFilter" value="<?php echo $date_filter; ?>" 
                       onchange="updateDateFilter(this.value)" class="form-control">
            </div>
            
            <div class="filter-group">
                <label for="statusFilter">Status:</label>
                <select id="statusFilter" onchange="filterPickups()" class="form-select">
                    <option value="all">All</option>
                    <option value="processing">Processing</option>
                    <option value="ready_for_pickup">Ready</option>
                    <option value="picked_up">Completed</option>
                </select>
            </div>
        </div>
        
        <div class="content-grid">
            <!-- Pickup Schedule -->
            <div class="card">
                <div class="card-header">
                    <h3>Pickup Schedule</h3>
                    <span class="badge"><?php echo $pickup_schedule->num_rows; ?> orders</span>
                </div>
                
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Items</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                    <th>Amount</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($pickup_schedule->num_rows > 0): ?>
                                    <?php while ($order = $pickup_schedule->fetch_assoc()): 
                                        // Get item count
                                        $items_stmt = $conn->prepare("SELECT COUNT(*) as item_count FROM order_items WHERE order_id = ?");
                                        $items_stmt->bind_param("i", $order['id']);
                                        $items_stmt->execute();
                                        $items_result = $items_stmt->get_result();
                                        $item_count = $items_result->fetch_assoc()['item_count'];
                                    ?>
                                    <tr class="order-row status-<?php echo $order['order_status']; ?>">
                                        <td>#<?php echo $order['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($order['email']); ?></small>
                                        </td>
                                        <td>
                                            <?php echo $item_count . ' item' . ($item_count != 1 ? 's' : ''); ?>
                                        </td>
                                        <td><?php echo date('g:i A', strtotime($order['pickup_time'])); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $order['order_status']; ?>">
                                                <?php 
                                                $status_labels = [
                                                    'processing' => 'Processing',
                                                    'ready_for_pickup' => 'Ready',
                                                    'picked_up' => 'Picked Up'
                                                ];
                                                echo $status_labels[$order['order_status']] ?? $order['order_status'];
                                                ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatPrice($order['total_amount']); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if ($order['order_status'] == 'processing'): ?>
                                                    <button onclick="markAsReady(<?php echo $order['id']; ?>)" 
                                                            class="btn btn-sm btn-success">
                                                        <i class="ri-check-line"></i> Ready
                                                    </button>
                                                <?php elseif ($order['order_status'] == 'ready_for_pickup'): ?>
                                                    <button onclick="markAsPickedUp(<?php echo $order['id']; ?>)" 
                                                            class="btn btn-sm btn-primary">
                                                        <i class="ri-check-double-line"></i> Pick Up
                                                    </button>
                                                <?php endif; ?>
                                                <button onclick="viewOrderDetails(<?php echo $order['id']; ?>)" 
                                                        class="btn btn-sm btn-secondary">
                                                    <i class="ri-eye-line"></i>
                                                </button>
                                                <button onclick="sendNotification(<?php echo $order['id']; ?>)" 
                                                        class="btn btn-sm btn-info">
                                                    <i class="ri-notification-line"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center">
                                            <div class="empty-state">
                                                <i class="ri-calendar-empty-line"></i>
                                                <p>No pickups scheduled for this period</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Completed Pickups -->
            <div class="card">
                <div class="card-header">
                    <h3>Recently Completed</h3>
                    <span class="badge"><?php echo $completed_pickups->num_rows; ?> orders</span>
                </div>
                
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Pickup Time</th>
                                    <th>Amount</th>
                                    <th>Payment</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($completed_pickups->num_rows > 0): ?>
                                    <?php while ($order = $completed_pickups->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?php echo $order['id']; ?></td>
                                        <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                        <td><?php echo date('g:i A', strtotime($order['pickup_time'])); ?></td>
                                        <td><?php echo formatPrice($order['total_amount']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $order['payment_status']; ?>">
                                                <?php echo ucfirst($order['payment_status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center">
                                            <div class="empty-state">
                                                <i class="ri-check-double-line"></i>
                                                <p>No completed pickups</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Pickup Times Distribution -->
            <div class="card">
                <div class="card-header">
                    <h3>Pickup Times Distribution</h3>
                </div>
                <div class="chart-container">
                    <canvas id="pickupTimesChart"></canvas>
                </div>
                <div class="chart-legend">
                    <?php 
                    $hourly_data = [];
                    while ($row = $times_distribution->fetch_assoc()) {
                        $hourly_data[$row['pickup_hour']] = $row['order_count'];
                    }
                    
                    for ($hour = 8; $hour <= 20; $hour++): 
                        $count = $hourly_data[$hour] ?? 0;
                        $percentage = $stats['total_pickups'] > 0 ? ($count / $stats['total_pickups']) * 100 : 0;
                    ?>
                    <div class="legend-item">
                        <span class="legend-time"><?php echo date('g A', strtotime("$hour:00")); ?></span>
                        <div class="legend-bar">
                            <div class="bar-fill" style="width: <?php echo min($percentage, 100); ?>%"></div>
                        </div>
                        <span class="legend-count"><?php echo $count; ?></span>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
        
        <!-- Bulk Actions -->
        <div class="bulk-actions">
            <div class="bulk-checkbox">
                <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                <label for="selectAll">Select All Visible</label>
            </div>
            
            <div class="bulk-buttons">
                <button onclick="markSelectedAsReady()" class="btn btn-sm btn-success">
                    <i class="ri-check-line"></i> Mark as Ready
                </button>
                <button onclick="sendNotificationsToSelected()" class="btn btn-sm btn-info">
                    <i class="ri-mail-send-line"></i> Notify Selected
                </button>
                <button onclick="exportPickupSchedule()" class="btn btn-sm btn-secondary">
                    <i class="ri-download-line"></i> Export CSV
                </button>
            </div>
        </div>
    </main>
</div>

<!-- Order Details Modal -->
<div id="orderDetailsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Order Details</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body" id="orderDetailsContent">
            <!-- Dynamic content will be loaded here -->
        </div>
    </div>
</div>

<script>
// Initialize chart
document.addEventListener('DOMContentLoaded', function() {
    // Prepare data for chart
    const hourlyData = <?php echo json_encode($hourly_data); ?>;
    const labels = [];
    const data = [];
    
    for (let hour = 8; hour <= 20; hour++) {
        labels.push(hour + ':00');
        data.push(hourlyData[hour] || 0);
    }
    
    // Create chart
    const ctx = document.getElementById('pickupTimesChart');
    if (ctx) {
        new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Pickups per Hour',
                    data: data,
                    backgroundColor: 'rgba(59, 130, 246, 0.5)',
                    borderColor: 'rgb(59, 130, 246)',
                    borderWidth: 1
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
                                return context.parsed.y + ' pickups';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            precision: 0
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    }
                }
            }
        });
    }
});

function updateViewType(viewType) {
    const date = document.getElementById('dateFilter').value;
    window.location.href = `?view=${viewType}&date=${date}`;
}

function updateDateFilter(date) {
    const viewType = document.getElementById('viewType').value;
    window.location.href = `?view=${viewType}&date=${date}`;
}

function filterPickups() {
    const status = document.getElementById('statusFilter').value;
    const rows = document.querySelectorAll('.order-row');
    
    rows.forEach(row => {
        const rowStatus = row.classList.contains('status-processing') ? 'processing' :
                         row.classList.contains('status-ready_for_pickup') ? 'ready_for_pickup' :
                         row.classList.contains('status-picked_up') ? 'picked_up' : '';
        
        if (status === 'all' || rowStatus === status) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function markAsReady(orderId) {
    if (confirm('Mark this order as ready for pickup?')) {
        fetch('../../actions/mark_order_ready.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ order_id: orderId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred');
        });
    }
}

function markAsPickedUp(orderId) {
    if (confirm('Mark this order as picked up?')) {
        fetch('../../actions/mark_picked_up.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ order_id: orderId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred');
        });
    }
}

function viewOrderDetails(orderId) {
    fetch(`../../actions/get_order_details.php?order_id=${orderId}`)
        .then(response => response.json())
        .then(data => {
            const modal = document.getElementById('orderDetailsModal');
            const content = document.getElementById('orderDetailsContent');
            
            content.innerHTML = `
                <div class="order-details">
                    <h4>Order #${data.id}</h4>
                    <div class="detail-row">
                        <strong>Customer:</strong> ${data.customer_name}
                    </div>
                    <div class="detail-row">
                        <strong>Email:</strong> ${data.email}
                    </div>
                    <div class="detail-row">
                        <strong>Order Time:</strong> ${new Date(data.created_at).toLocaleString()}
                    </div>
                    <div class="detail-row">
                        <strong>Status:</strong> <span class="status-badge status-${data.order_status}">${data.order_status.replace('_', ' ').toUpperCase()}</span>
                    </div>
                    <div class="detail-row">
                        <strong>Total Amount:</strong> ${formatPrice(data.total_amount)}
                    </div>
                    <div class="detail-row">
                        <strong>Payment Status:</strong> <span class="status-badge status-${data.payment_status}">${data.payment_status.toUpperCase()}</span>
                    </div>
                    <hr>
                    <h5>Order Items</h5>
                    <div class="order-items">
                        ${data.items.map(item => `
                            <div class="order-item">
                                <strong>${item.name}</strong>
                                <div>Quantity: ${item.quantity}</div>
                                <div>Price: ${formatPrice(item.price)}</div>
                                ${item.instructions ? `<div><small>Instructions: ${item.instructions}</small></div>` : ''}
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
            
            modal.style.display = 'flex';
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading order details');
        });
}

function sendNotification(orderId) {
    if (confirm('Send pickup notification to customer?')) {
        fetch('../../actions/send_pickup_notification.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ order_id: orderId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Notification sent successfully');
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred');
        });
    }
}

function closeModal() {
    document.getElementById('orderDetailsModal').style.display = 'none';
}

function toggleSelectAll(checkbox) {
    const checkboxes = document.querySelectorAll('.order-select');
    checkboxes.forEach(cb => {
        cb.checked = checkbox.checked;
    });
}

function markSelectedAsReady() {
    const selected = document.querySelectorAll('.order-select:checked');
    if (selected.length === 0) {
        alert('Please select orders to mark as ready');
        return;
    }
    
    const orderIds = Array.from(selected).map(cb => cb.value);
    if (confirm(`Mark ${orderIds.length} orders as ready for pickup?`)) {
        fetch('../../actions/bulk_mark_ready.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ order_ids: orderIds })
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

function sendBulkNotifications() {
    const orderIds = Array.from(document.querySelectorAll('.order-row'))
        .filter(row => row.classList.contains('status-ready_for_pickup'))
        .map(row => row.querySelector('td:first-child').textContent.replace('#', ''));
    
    if (orderIds.length === 0) {
        alert('No orders ready for pickup notification');
        return;
    }
    
    if (confirm(`Send notifications for ${orderIds.length} ready orders?`)) {
        fetch('../../actions/send_bulk_notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ order_ids: orderIds })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Notifications sent successfully');
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

function sendNotificationsToSelected() {
    const selected = document.querySelectorAll('.order-select:checked');
    if (selected.length === 0) {
        alert('Please select orders to notify');
        return;
    }
    
    const orderIds = Array.from(selected).map(cb => cb.value);
    if (confirm(`Send notifications for ${orderIds.length} orders?`)) {
        fetch('../../actions/send_bulk_notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ order_ids: orderIds })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Notifications sent successfully');
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

function printPickupSchedule() {
    window.print();
}

function exportPickupSchedule() {
    const date = document.getElementById('dateFilter').value;
    const viewType = document.getElementById('viewType').value;
    window.location.href = `../../actions/export_pickup_schedule.php?date=${date}&view=${viewType}`;
}

// Helper function to format price
function formatPrice(amount) {
    return '₱' + parseFloat(amount).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('orderDetailsModal');
    if (event.target == modal) {
        modal.style.display = 'none';
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