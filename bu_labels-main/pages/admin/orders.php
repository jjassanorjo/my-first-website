<?php
// pages/admin/orders.php

require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is admin
if (!isLoggedIn() || $_SESSION['user_role'] != 'admin') {
    redirect('../../account.php');
}

$page_title = "Manage Orders";
$body_class = "admin-page admin-orders";
$page_scripts = ['admin.js'];

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
    }
    
    if (isset($_POST['update_payment'])) {
        $order_id = (int)$_POST['order_id'];
        $payment_status = escape($_POST['payment_status']);
        
        $stmt = $conn->prepare("UPDATE orders SET payment_status = ? WHERE id = ?");
        $stmt->bind_param("si", $payment_status, $order_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Payment status updated successfully!";
            redirect('orders.php?action=view&id=' . $order_id);
        } else {
            $error = "Error updating payment status: " . $conn->error;
        }
    }
    
    if (isset($_POST['delete_order'])) {
        $delete_id = (int)$_POST['delete_id'];
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Delete order items first
            $stmt1 = $conn->prepare("DELETE FROM order_items WHERE order_id = ?");
            $stmt1->bind_param("i", $delete_id);
            $stmt1->execute();
            
            // Delete status logs
            $stmt2 = $conn->prepare("DELETE FROM order_status_logs WHERE order_id = ?");
            $stmt2->bind_param("i", $delete_id);
            $stmt2->execute();
            
            // Delete order
            $stmt3 = $conn->prepare("DELETE FROM orders WHERE id = ?");
            $stmt3->bind_param("i", $delete_id);
            $stmt3->execute();
            
            $conn->commit();
            $_SESSION['success'] = "Order deleted successfully!";
            redirect('orders.php');
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error deleting order: " . $e->getMessage();
        }
    }
}

// Get order details for view/edit
if ($action == 'view' && $order_id > 0) {
    $order_stmt = $conn->prepare("SELECT o.*, u.name as customer_name, u.email, u.campus as user_campus 
                                 FROM orders o 
                                 JOIN users u ON o.user_id = u.id 
                                 WHERE o.id = ?");
    $order_stmt->bind_param("i", $order_id);
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
    $sql = "SELECT o.*, u.name as customer_name FROM orders o JOIN users u ON o.user_id = u.id WHERE 1=1";
    $params = [];
    $types = "";
    
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
    if ($params) {
        $count_stmt->bind_param($types, ...$params);
    }
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_rows = $count_result->fetch_assoc()['total'];
    $total_pages = ceil($total_rows / $limit);
    
    // Add sorting and pagination
    $sql .= " ORDER BY o.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    
    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $orders_result = $stmt->get_result();
}

// Get status counts for stats
$status_counts = [];
$status_stmt = $conn->prepare("SELECT order_status, COUNT(*) as count FROM orders GROUP BY order_status");
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
    <title>BU Labels - Admin | <?php echo $page_title; ?></title>
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-icon.processing { background: rgba(251, 211, 141, 0.2); color: #d69e2e; }
        .stat-icon.ready_for_pickup { background: rgba(144, 205, 244, 0.2); color: #3182ce; }
        .stat-icon.picked_up { background: rgba(154, 230, 180, 0.2); color: #38a169; }
        .stat-icon.cancelled { background: rgba(254, 178, 178, 0.2); color: #e53e3e; }

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
        }

        .filter-form input:focus,
        .filter-form select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 54, 93, 0.1);
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

        .payment-status {
            font-size: 0.75rem;
            padding: 0.125rem 0.5rem;
            border-radius: 4px;
            font-weight: 500;
        }

        .status-pending { background: #feebc8; color: #9c4221; }
        .status-paid { background: #c6f6d5; color: #22543d; }
        .status-failed { background: #fed7d7; color: #742a2a; }

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
        }

        .btn-icon:hover {
            background: var(--primary);
            color: var(--white);
        }

        /* Order Details */
        .order-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
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

        .info-item:last-child {
            border-bottom: none;
        }

        .info-item label {
            font-weight: 500;
            color: var(--dark-gray);
        }

        .info-item .value {
            font-weight: 600;
            color: var(--dark);
            text-align: right;
        }

        .price {
            color: var(--primary);
            font-weight: 600;
        }

        /* Form Groups */
        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
        }

        .form-group select,
        .form-group textarea,
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--gray);
            border-radius: var(--border-radius);
            font-size: 0.875rem;
            background: var(--white);
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-group select:focus,
        .form-group textarea:focus,
        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 54, 93, 0.1);
        }

        /* Order Items */
        .order-items-list {
            margin: 1.5rem 0;
        }

        .order-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid var(--gray);
            gap: 1rem;
        }

        .order-item:last-child {
            border-bottom: none;
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
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .item-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.875rem;
            color: var(--dark-gray);
        }

        .item-total {
            font-weight: 600;
            color: var(--primary);
            min-width: 100px;
            text-align: right;
        }

        .order-summary {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
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
            padding-top: 1rem;
        }

        /* Timeline */
        .timeline {
            position: relative;
            padding-left: 1.5rem;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 7px;
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
            left: -1.5rem;
            top: 4px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: var(--gray);
            border: 3px solid var(--white);
        }

        .timeline-item.current .timeline-marker {
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
            font-size: 0.875rem;
            color: var(--dark-gray);
        }

        .updated-by {
            font-size: 0.875rem;
            color: var(--dark-gray);
            margin-bottom: 0.5rem;
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
            }
            
            .admin-header {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
            
            .admin-actions {
                justify-content: flex-end;
            }
            
            .order-details-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-form {
                flex-direction: column;
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
            
            .card-header {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
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
            <h1>
                <?php 
                if ($action == 'view') echo "Order Details";
                else echo "Manage Orders";
                ?>
            </h1>
            
            <div class="admin-actions">
                <?php if ($action == 'view'): ?>
                <a href="orders.php" class="btn btn-outline">
                    <i class="ri-arrow-left-line"></i> Back to Orders
                </a>
                <?php endif; ?>
                
                <?php if ($action == 'list'): ?>
                <button class="btn btn-primary" onclick="printOrderReport()">
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
        <div class="order-details-grid">
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
                
                <!-- Status Update Form -->
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
                                <option value="cancelled" <?php echo ($order['order_status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
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
            
            <!-- Payment Information -->
            <div class="card">
                <div class="card-header">
                    <h3>Payment Information</h3>
                </div>
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Method:</label>
                            <span class="value"><?php echo ucwords(str_replace('_', ' ', $order['payment_method'])); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Status:</label>
                            <span class="value">
                                <span class="payment-status status-<?php echo $order['payment_status']; ?>">
                                    <?php echo ucwords($order['payment_status']); ?>
                                </span>
                            </span>
                        </div>
                    </div>
                    
                    <form method="POST" class="mt-3">
                        <input type="hidden" name="update_payment" value="1">
                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                        
                        <div class="form-group">
                            <select name="payment_status" required>
                                <option value="pending" <?php echo ($order['payment_status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="paid" <?php echo ($order['payment_status'] == 'paid') ? 'selected' : ''; ?>>Paid</option>
                                <option value="failed" <?php echo ($order['payment_status'] == 'failed') ? 'selected' : ''; ?>>Failed</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-outline btn-block">
                            Update Payment Status
                        </button>
                    </form>
                </div>
                
                <!-- Pickup Information -->
                <div class="card-header">
                    <h3>Pickup Information</h3>
                </div>
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Campus:</label>
                            <span class="value"><?php echo ucfirst($order['pickup_campus']); ?> Campus</span>
                        </div>
                        <div class="info-item">
                            <label>Location:</label>
                            <span class="value">CSC Office, <?php echo ucfirst($order['pickup_campus']); ?> Campus</span>
                        </div>
                        <?php if ($order['estimated_pickup']): ?>
                        <div class="info-item">
                            <label>Estimated Pickup:</label>
                            <span class="value"><?php echo date('F j, Y', strtotime($order['estimated_pickup'])); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($order['notes']): ?>
                        <div class="info-item">
                            <label>Customer Notes:</label>
                            <span class="value"><?php echo nl2br(sanitize($order['notes'])); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Order Items and Status History -->
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
                                    <span class="price">₱<?php echo number_format($item['price'], 2); ?></span>
                                </div>
                            </div>
                            <div class="item-total">
                                ₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
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
                
                <!-- Status History -->
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
                            <div class="timeline-item">
                                <div class="timeline-content">
                                    <p>No status history available.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Current Status -->
                        <div class="timeline-item current">
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
                
                <!-- Order Actions -->
                <div class="card-header">
                    <h3>Order Actions</h3>
                </div>
                <div class="card-body">
                    <div class="action-buttons">
                        <a href="order-confirmation.php?id=<?php echo $order['id']; ?>" target="_blank" class="btn btn-outline btn-block">
                            <i class="ri-eye-line"></i> View Customer Receipt
                        </a>
                        
                        <button onclick="printOrder(<?php echo $order['id']; ?>)" class="btn btn-outline btn-block">
                            <i class="ri-printer-line"></i> Print Order
                        </button>
                        
                        <button onclick="sendEmailNotification(<?php echo $order['id']; ?>)" class="btn btn-outline btn-block">
                            <i class="ri-mail-line"></i> Send Update Email
                        </button>
                        
                        <button onclick="confirmDeleteOrder(<?php echo $order['id']; ?>, '<?php echo $order['order_number']; ?>')" class="btn btn-danger btn-block">
                            <i class="ri-delete-bin-line"></i> Delete Order
                        </button>
                    </div>
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
            </div>
            
            <div class="stat-card">
                <div class="stat-icon ready_for_pickup">
                    <i class="ri-checkbox-circle-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $status_counts['ready_for_pickup'] ?? 0; ?></h3>
                    <p>Ready for Pickup</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon picked_up">
                    <i class="ri-truck-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $status_counts['picked_up'] ?? 0; ?></h3>
                    <p>Completed</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon cancelled">
                    <i class="ri-close-circle-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $status_counts['cancelled'] ?? 0; ?></h3>
                    <p>Cancelled</p>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3>All Orders</h3>
                
                <div class="card-tools">
                    <form method="GET" class="filter-form">
                        <input type="text" name="search" placeholder="Search orders..." 
                               value="<?php echo isset($_GET['search']) ? sanitize($_GET['search']) : ''; ?>">
                        <input type="date" name="date_from" 
                               value="<?php echo isset($_GET['date_from']) ? $_GET['date_from'] : ''; ?>"
                               placeholder="From Date">
                        <input type="date" name="date_to" 
                               value="<?php echo isset($_GET['date_to']) ? $_GET['date_to'] : ''; ?>"
                               placeholder="To Date">
                        <select name="status" onchange="this.form.submit()">
                            <option value="">All Status</option>
                            <option value="processing" <?php echo ($status == 'processing') ? 'selected' : ''; ?>>Processing</option>
                            <option value="ready_for_pickup" <?php echo ($status == 'ready_for_pickup') ? 'selected' : ''; ?>>Ready for Pickup</option>
                            <option value="picked_up" <?php echo ($status == 'picked_up') ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo ($status == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                        <button type="submit" class="btn btn-small">
                            <i class="ri-search-line"></i> Filter
                        </button>
                        <?php if (isset($_GET['search']) || isset($_GET['status']) || isset($_GET['date_from']) || isset($_GET['date_to'])): ?>
                        <a href="orders.php" class="btn btn-outline btn-small">Clear</a>
                        <?php endif; ?>
                    </form>
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
                                <th>Campus</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($orders_result->num_rows > 0): ?>
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
                                    <td><?php echo ucfirst($order['pickup_campus']); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="orders.php?action=view&id=<?php echo $order['id']; ?>" 
                                               class="btn-icon" title="View">
                                                <i class="ri-eye-line"></i>
                                            </a>
                                            <a href="order-confirmation.php?id=<?php echo $order['id']; ?>" 
                                               target="_blank" class="btn-icon" title="Print">
                                                <i class="ri-printer-line"></i>
                                            </a>
                                            <button type="button" class="btn-icon"
                                                    onclick="sendOrderEmail(<?php echo $order['id']; ?>)" 
                                                    title="Send Email">
                                                <i class="ri-mail-line"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center">
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

<!-- Delete Order Modal -->
<div class="modal" id="deleteOrderModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Confirm Delete</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete order <strong id="deleteOrderNumber"></strong>?</p>
            <p class="text-danger"><i class="ri-error-warning-line"></i> This will permanently delete the order and all associated data. This action cannot be undone.</p>
        </div>
        <div class="modal-footer">
            <form method="POST" id="deleteOrderForm">
                <input type="hidden" name="delete_order" value="1">
                <input type="hidden" name="delete_id" id="deleteOrderId">
                <button type="button" class="btn btn-outline modal-close">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete Order</button>
            </form>
        </div>
    </div>
</div>

<script>
function confirmDeleteOrder(orderId, orderNumber) {
    document.getElementById('deleteOrderId').value = orderId;
    document.getElementById('deleteOrderNumber').textContent = orderNumber;
    document.getElementById('deleteOrderModal').style.display = 'block';
}

function printOrder(orderId) {
    window.open(`order-confirmation.php?id=${orderId}&print=1`, '_blank');
}

function printOrderReport() {
    const params = new URLSearchParams(window.location.search);
    window.open(`reports.php?action=print&${params.toString()}`, '_blank');
}

function sendOrderEmail(orderId) {
    fetch(`api/orders.php?action=send_email&order_id=${orderId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Email notification sent successfully!');
            } else {
                alert('Error: ' + data.message);
            }
        });
}

function sendEmailNotification(orderId) {
    const message = prompt('Enter additional message for the email (optional):');
    if (message !== null) {
        fetch(`api/orders.php?action=send_email`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `order_id=${orderId}&message=${encodeURIComponent(message)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Email notification sent successfully!');
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

// Close modal handlers
document.querySelectorAll('.modal-close').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('deleteOrderModal').style.display = 'none';
    });
});

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('deleteOrderModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
});

// Mobile sidebar toggle
document.addEventListener('DOMContentLoaded', function() {
    const menuBtn = document.createElement('button');
    menuBtn.innerHTML = '<i class="ri-menu-line"></i>';
    menuBtn.className = 'btn btn-icon mobile-menu-btn';
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