<?php
// pages/campus-director/orders.php

require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is campus director
if (!isLoggedIn() || $_SESSION['user_role'] != 'director') {
    redirect('../../account.php');
}

$page_title = "Campus Orders";
$body_class = "director-orders";

$campus = $_SESSION['user']['campus'];
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$status = isset($_GET['status']) ? $_GET['status'] : '';

$conn = getDBConnection();

// Handle order updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $order_id = (int)$_POST['order_id'];
        $new_status = escape($_POST['status']);
        $status_notes = escape($_POST['status_notes'] ?? '');
        
        // Verify order belongs to director's campus
        $check_stmt = $conn->prepare("SELECT id FROM orders WHERE id = ? AND pickup_campus = ?");
        $check_stmt->bind_param("is", $order_id, $campus);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE orders SET order_status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("si", $new_status, $order_id);
            
            if ($stmt->execute()) {
                // Log status change
                $log_stmt = $conn->prepare("INSERT INTO order_status_logs (order_id, status, notes, updated_by) VALUES (?, ?, ?, ?)");
                $log_stmt->bind_param("isss", $order_id, $new_status, $status_notes, $_SESSION['user']['name']);
                $log_stmt->execute();
                
                $_SESSION['success'] = "Order status updated successfully!";
                redirect('orders.php?action=view&id=' . $order_id);
            } else {
                $error = "Error updating order status: " . $conn->error;
            }
        } else {
            $error = "Order not found in your campus";
        }
    }
}

// Get order details for view
if ($action == 'view' && $order_id > 0) {
    $order_stmt = $conn->prepare("SELECT o.*, u.name as customer_name, u.email, u.campus as user_campus 
                                 FROM orders o 
                                 JOIN users u ON o.user_id = u.id 
                                 WHERE o.id = ? AND o.pickup_campus = ?");
    $order_stmt->bind_param("is", $order_id, $campus);
    $order_stmt->execute();
    $order_result = $order_stmt->get_result();
    $order = $order_result->fetch_assoc();
    
    if (!$order) {
        redirect('orders.php');
    }
    
    // Get order items
    $items_stmt = $conn->prepare("SELECT oi.*, p.name, p.image_main 
                                 FROM order_items oi 
                                 JOIN products p ON oi.product_id = p.id 
                                 WHERE oi.order_id = ?");
    $items_stmt->bind_param("i", $order_id);
    $items_stmt->execute();
    $order_items = $items_stmt->get_result();
    
    // Get status history
    $history_stmt = $conn->prepare("SELECT * FROM order_status_logs WHERE order_id = ? ORDER BY created_at DESC");
    $history_stmt->bind_param("i", $order_id);
    $history_stmt->execute();
    $status_history = $history_stmt->get_result();
}

// Get orders for listing
if ($action == 'list') {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    // Build query with filters
    $sql = "SELECT o.*, u.name as customer_name FROM orders o JOIN users u ON o.user_id = u.id WHERE o.pickup_campus = ?";
    $params = [$campus];
    $types = "s";
    
    if ($status) {
        $sql .= " AND o.order_status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    // Search
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search = escape($_GET['search']);
        $sql .= " AND (o.order_number LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $types .= "sss";
    }
    
    // Date range
    if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
        $sql .= " AND DATE(o.created_at) >= ?";
        $params[] = $_GET['date_from'];
        $types .= "s";
    }
    
    if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
        $sql .= " AND DATE(o.created_at) <= ?";
        $params[] = $_GET['date_to'];
        $types .= "s";
    }
    
    // Get total count
    $count_sql = str_replace("SELECT o.*, u.name as customer_name", "SELECT COUNT(*) as total", $sql);
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_rows = $count_result->fetch_assoc()['total'];
    $total_pages = ceil($total_rows / $limit);
    
    // Add sorting and pagination
    $sql .= " ORDER BY 
        CASE o.order_status 
            WHEN 'ready_for_pickup' THEN 1
            WHEN 'processing' THEN 2
            WHEN 'picked_up' THEN 3
            WHEN 'cancelled' THEN 4
        END,
        o.created_at DESC 
        LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $orders_result = $stmt->get_result();
}

// Get status counts for stats
$status_counts = [];
$status_stmt = $conn->prepare("SELECT order_status, COUNT(*) as count FROM orders WHERE pickup_campus = ? GROUP BY order_status");
$status_stmt->bind_param("s", $campus);
$status_stmt->execute();
$status_result = $status_stmt->get_result();
while ($row = $status_result->fetch_assoc()) {
    $status_counts[$row['order_status']] = $row['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BU Labels - <?php echo $page_title; ?></title>
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

        .btn-block {
            width: 100%;
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

        .processing { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .ready_for_pickup { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .picked_up { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .total { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }

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

        .stat-link {
            margin-left: auto;
            font-size: 0.875rem;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .stat-link:hover {
            text-decoration: underline;
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

        .payment-status { font-size: 0.75rem; }
        .status-paid { color: var(--success); }
        .status-pending { color: var(--warning); }
        .status-failed { color: var(--danger); }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
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

        .btn-view { background: #bee3f8; color: #2c5282; }
        .btn-print { background: #feebc8; color: #9c4221; }
        .btn-success { background: #c6f6d5; color: #22543d; }

        /* Form Elements */
        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
        }

        select, input, textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--gray);
            border-radius: var(--border-radius);
            font-size: 0.875rem;
            transition: var(--transition);
        }

        select:focus, input:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 54, 93, 0.1);
        }

        /* Order Details View */
        .order-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .info-grid {
            display: grid;
            gap: 1rem;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--gray);
        }

        .info-item label {
            font-weight: 600;
            color: var(--dark-gray);
        }

        .info-item .value {
            color: var(--dark);
            font-weight: 500;
        }

        .price {
            color: var(--success);
            font-weight: 600;
        }

        .order-items-list {
            display: grid;
            gap: 1rem;
        }

        .order-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: var(--light);
            border-radius: var(--border-radius);
        }

        .order-item img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: var(--border-radius);
        }

        .item-info {
            flex: 1;
        }

        .item-info h4 {
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .item-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.75rem;
            color: var(--dark-gray);
        }

        .item-total {
            font-weight: 600;
            color: var(--success);
        }

        .order-summary {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 2px solid var(--gray);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
        }

        .summary-row.total {
            font-weight: 700;
            font-size: 1.125rem;
            border-top: 1px solid var(--gray);
            margin-top: 0.5rem;
            padding-top: 0.75rem;
        }

        /* Timeline */
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

        .timeline-marker {
            position: absolute;
            left: -2rem;
            top: 0;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--gray);
            border: 3px solid var(--white);
        }

        .timeline-marker.active {
            background: var(--success);
        }

        .timeline-content {
            background: var(--light);
            padding: 1rem;
            border-radius: var(--border-radius);
        }

        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .timeline-header .status {
            font-weight: 600;
            color: var(--primary);
        }

        .timeline-header .time {
            font-size: 0.75rem;
            color: var(--dark-gray);
        }

        .updated-by {
            font-size: 0.875rem;
            color: var(--dark-gray);
            margin-bottom: 0.5rem;
        }

        .notes {
            font-size: 0.875rem;
            color: var(--dark);
            background: var(--white);
            padding: 0.75rem;
            border-radius: var(--border-radius);
            margin-top: 0.5rem;
        }

        /* Filter Form */
        .filter-form {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-form input,
        .filter-form select {
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
        }

        .filter-form input[type="date"] {
            min-width: 150px;
        }

        .filter-form input[type="text"] {
            min-width: 200px;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid var(--gray);
        }

        .page-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: var(--border-radius);
            background: var(--white);
            color: var(--dark);
            text-decoration: none;
            font-weight: 500;
            border: 1px solid var(--gray);
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
            
            .order-grid {
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
            
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-form input,
            .filter-form select {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .admin-content {
                padding: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .data-table {
                font-size: 0.75rem;
            }
            
            .data-table th,
            .data-table td {
                padding: 0.75rem 0.5rem;
            }
            
            .order-item {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
<div class="admin-container">
    <?php 
    // Include sidebar
    include 'sidebar.php';
    ?>
    
    <main class="admin-content">
        <div class="admin-header">
            <h1>
                <?php 
                if ($action == 'view') echo "Order Details";
                else echo ucfirst($campus) . " Campus Orders";
                ?>
            </h1>
            
            <div class="admin-actions">
                <?php if ($action == 'view'): ?>
                <a href="orders.php" class="btn btn-outline">
                    <i class="ri-arrow-left-line"></i> Back to Orders
                </a>
                <?php endif; ?>
                
                <?php if ($action == 'list'): ?>
                <button onclick="printCampusReport()" class="btn btn-primary">
                    <i class="ri-printer-line"></i> Print Report
                </button>
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
        
        <?php if ($action == 'view' && isset($order)): ?>
        <!-- Order Details View -->
        <div class="order-grid">
            <!-- Order Information -->
            <div class="card">
                <div class="card-header">
                    <h3>Order Information</h3>
                </div>
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Order Number:</label>
                            <span class="value"><?php echo $order['order_number']; ?></span>
                        </div>
                        <div class="info-item">
                            <label>Date:</label>
                            <span class="value"><?php echo date('F j, Y, g:i a', strtotime($order['created_at'])); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Customer:</label>
                            <span class="value"><?php echo sanitize($order['customer_name']); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Email:</label>
                            <span class="value"><?php echo sanitize($order['email']); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Total Amount:</label>
                            <span class="value price"><?php echo formatPrice($order['total_amount']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Order Items -->
            <div class="card">
                <div class="card-header">
                    <h3>Order Items</h3>
                </div>
                <div class="card-body">
                    <div class="order-items-list">
                        <?php while ($item = $order_items->fetch_assoc()): ?>
                        <div class="order-item">
                            <img src="../../assets/images/<?php echo $item['image_main']; ?>" 
                                 alt="<?php echo sanitize($item['name']); ?>">
                            <div class="item-info">
                                <h4><?php echo sanitize($item['name']); ?></h4>
                                <div class="item-meta">
                                    <?php if (!empty($item['size'])): ?>
                                    <span class="size">Size: <?php echo $item['size']; ?></span>
                                    <?php endif; ?>
                                    <span class="quantity">Qty: <?php echo $item['quantity']; ?></span>
                                    <span class="price"><?php echo formatPrice($item['price']); ?></span>
                                </div>
                            </div>
                            <div class="item-total">
                                <?php echo formatPrice($item['price'] * $item['quantity']); ?>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    
                    <div class="order-summary">
                        <div class="summary-row">
                            <span>Subtotal:</span>
                            <span><?php echo formatPrice($order['total_amount']); ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Shipping:</span>
                            <span>Free (Campus Pickup)</span>
                        </div>
                        <div class="summary-row total">
                            <span>Total:</span>
                            <span><?php echo formatPrice($order['total_amount']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Status Update -->
            <div class="card">
                <div class="card-header">
                    <h3>Update Status</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="update_status" value="1">
                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                        
                        <div class="form-group">
                            <label for="status">Order Status</label>
                            <select id="status" name="status" required>
                                <option value="processing" <?php echo ($order['order_status'] == 'processing') ? 'selected' : ''; ?>>Processing</option>
                                <option value="ready_for_pickup" <?php echo ($order['order_status'] == 'ready_for_pickup') ? 'selected' : ''; ?>>Ready for Pickup</option>
                                <option value="picked_up" <?php echo ($order['order_status'] == 'picked_up') ? 'selected' : ''; ?>>Picked Up</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="status_notes">Notes (Optional)</label>
                            <textarea id="status_notes" name="status_notes" rows="3" placeholder="Add notes about this status change..."></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="ri-save-line"></i> Update Status
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Customer Information -->
            <div class="card">
                <div class="card-header">
                    <h3>Customer Information</h3>
                </div>
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Name:</label>
                            <span class="value"><?php echo sanitize($order['customer_name']); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Email:</label>
                            <span class="value"><?php echo sanitize($order['email']); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Campus:</label>
                            <span class="value"><?php echo ucfirst($order['user_campus']); ?> Campus</span>
                        </div>
                        <div class="info-item">
                            <label>Pickup Campus:</label>
                            <span class="value"><?php echo ucfirst($order['pickup_campus']); ?> Campus</span>
                        </div>
                        <?php if ($order['notes']): ?>
                        <div class="info-item">
                            <label>Customer Notes:</label>
                            <span class="value"><?php echo nl2br(sanitize($order['notes'])); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Status History -->
            <div class="card">
                <div class="card-header">
                    <h3>Status History</h3>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <?php if ($status_history->num_rows > 0): ?>
                            <?php while ($log = $status_history->fetch_assoc()): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker"></div>
                                <div class="timeline-content">
                                    <div class="timeline-header">
                                        <span class="status"><?php echo ucwords(str_replace('_', ' ', $log['status'])); ?></span>
                                        <span class="time"><?php echo date('M j, g:i a', strtotime($log['created_at'])); ?></span>
                                    </div>
                                    <p class="updated-by">Updated by: <?php echo sanitize($log['updated_by']); ?></p>
                                    <?php if ($log['notes']): ?>
                                    <p class="notes"><?php echo sanitize($log['notes']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="ri-time-line"></i>
                                <p>No status history available.</p>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Current Status -->
                        <div class="timeline-item">
                            <div class="timeline-marker active"></div>
                            <div class="timeline-content">
                                <div class="timeline-header">
                                    <span class="status">Current: <?php echo ucwords(str_replace('_', ' ', $order['order_status'])); ?></span>
                                </div>
                                <p class="updated-by">Last updated: <?php echo date('M j, g:i a', strtotime($order['updated_at'])); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Payment Information -->
            <div class="card">
                <div class="card-header">
                    <h3>Payment Information</h3>
                </div>
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Payment Method:</label>
                            <span class="value"><?php echo ucwords(str_replace('_', ' ', $order['payment_method'])); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Payment Status:</label>
                            <span class="value">
                                <span class="status-badge status-<?php echo $order['payment_status']; ?>">
                                    <?php echo ucwords($order['payment_status']); ?>
                                </span>
                            </span>
                        </div>
                    </div>
                    
                    <?php if ($order['payment_method'] == 'cod' && $order['order_status'] == 'ready_for_pickup'): ?>
                    <div class="alert alert-success" style="margin-top: 1rem;">
                        <i class="ri-money-dollar-circle-line"></i>
                        <p><strong>Cash on Delivery:</strong> Collect <?php echo formatPrice($order['total_amount']); ?> upon pickup</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Orders List -->
        <div class="stats-grid">
            <!-- Order Stats -->
            <div class="stat-card">
                <div class="stat-icon processing">
                    <i class="ri-refresh-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $status_counts['processing'] ?? 0; ?></h3>
                    <p>Processing</p>
                </div>
                <a href="orders.php?status=processing" class="stat-link">View</a>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon ready_for_pickup">
                    <i class="ri-checkbox-circle-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $status_counts['ready_for_pickup'] ?? 0; ?></h3>
                    <p>Ready for Pickup</p>
                </div>
                <a href="orders.php?status=ready_for_pickup" class="stat-link">View</a>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon picked_up">
                    <i class="ri-truck-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $status_counts['picked_up'] ?? 0; ?></h3>
                    <p>Completed</p>
                </div>
                <a href="orders.php?status=picked_up" class="stat-link">View</a>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon total">
                    <i class="ri-shopping-bag-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo array_sum($status_counts); ?></h3>
                    <p>Total Orders</p>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3><?php echo ucfirst($campus); ?> Campus Orders</h3>
                
                <div class="filter-form">
                    <input type="text" name="search" placeholder="Search orders..." 
                           value="<?php echo isset($_GET['search']) ? sanitize($_GET['search']) : ''; ?>"
                           onkeypress="if(event.keyCode==13) applyFilters()">
                    <input type="date" name="date_from" 
                           value="<?php echo isset($_GET['date_from']) ? $_GET['date_from'] : ''; ?>"
                           placeholder="From Date">
                    <input type="date" name="date_to" 
                           value="<?php echo isset($_GET['date_to']) ? $_GET['date_to'] : ''; ?>"
                           placeholder="To Date">
                    <select name="status" onchange="applyFilters()">
                        <option value="">All Status</option>
                        <option value="processing" <?php echo ($status == 'processing') ? 'selected' : ''; ?>>Processing</option>
                        <option value="ready_for_pickup" <?php echo ($status == 'ready_for_pickup') ? 'selected' : ''; ?>>Ready for Pickup</option>
                        <option value="picked_up" <?php echo ($status == 'picked_up') ? 'selected' : ''; ?>>Completed</option>
                    </select>
                    <button type="button" onclick="applyFilters()" class="btn btn-small">
                        <i class="ri-search-line"></i> Filter
                    </button>
                    <?php if (isset($_GET['search']) || isset($_GET['status']) || isset($_GET['date_from']) || isset($_GET['date_to'])): ?>
                    <a href="orders.php" class="btn btn-outline btn-small">Clear</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card-body">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($orders_result && $orders_result->num_rows > 0): ?>
                                <?php while ($order = $orders_result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo $order['order_number']; ?></strong>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo sanitize($order['customer_name']); ?></strong><br>
                                            <small><?php echo date('M j, Y', strtotime($order['created_at'])); ?></small>
                                        </div>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                                    <td class="price"><?php echo formatPrice($order['total_amount']); ?></td>
                                    <td>
                                        <span class="payment-method"><?php echo ucwords(str_replace('_', ' ', $order['payment_method'])); ?></span><br>
                                        <small class="payment-status status-<?php echo $order['payment_status']; ?>">
                                            <?php echo ucwords($order['payment_status']); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $order['order_status']; ?>">
                                            <?php echo ucwords(str_replace('_', ' ', $order['order_status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="orders.php?action=view&id=<?php echo $order['id']; ?>" 
                                               class="btn-icon btn-view" title="View">
                                                <i class="ri-eye-line"></i>
                                            </a>
                                            <a href="../../order-confirmation.php?id=<?php echo $order['id']; ?>" 
                                               target="_blank" class="btn-icon btn-print" title="Print">
                                                <i class="ri-printer-line"></i>
                                            </a>
                                            <?php if ($order['order_status'] == 'ready_for_pickup'): ?>
                                            <button type="button" class="btn-icon btn-success"
                                                    onclick="markAsPickedUp(<?php echo $order['id']; ?>)" 
                                                    title="Mark as Picked Up">
                                                <i class="ri-check-line"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">
                                        <div class="empty-state">
                                            <i class="ri-shopping-bag-line"></i>
                                            <p>No orders found</p>
                                            <?php if (isset($_GET['search']) || isset($_GET['status'])): ?>
                                            <a href="orders.php" class="btn btn-outline btn-small">
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
                <?php if (isset($total_pages) && $total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-link">
                        <i class="ri-arrow-left-line"></i>
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
                        <i class="ri-arrow-right-line"></i>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
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

function printCampusReport() {
    const params = new URLSearchParams(window.location.search);
    window.open(`reports.php?action=print&${params.toString()}`, '_blank');
}

function applyFilters() {
    const form = document.querySelector('.filter-form');
    const search = form.querySelector('[name="search"]').value;
    const dateFrom = form.querySelector('[name="date_from"]').value;
    const dateTo = form.querySelector('[name="date_to"]').value;
    const status = form.querySelector('[name="status"]').value;
    
    let url = 'orders.php?';
    if (search) url += `search=${encodeURIComponent(search)}&`;
    if (dateFrom) url += `date_from=${dateFrom}&`;
    if (dateTo) url += `date_to=${dateTo}&`;
    if (status) url += `status=${status}&`;
    
    // Remove trailing & or ?
    url = url.replace(/[&?]$/, '');
    window.location.href = url;
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
});
</script>
</body>
</html>