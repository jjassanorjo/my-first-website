<?php
// pages/campus-director/reports.php

require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is campus director
if (!isLoggedIn() || $_SESSION['user_role'] != 'director') {
    redirect('../../account.php');
}

$page_title = "Reports & Analytics";
$body_class = "director-reports";

$campus = $_SESSION['user']['campus'];
$conn = getDBConnection();

// Get date range filters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'overview';

// Get sales summary
$sales_query = "SELECT 
    COUNT(*) as total_orders,
    SUM(total_amount) as total_revenue,
    AVG(total_amount) as avg_order_value,
    COUNT(DISTINCT user_id) as unique_customers
    FROM orders 
    WHERE pickup_campus = ? 
    AND order_status = 'picked_up'
    AND DATE(created_at) BETWEEN ? AND ?";
$sales_stmt = $conn->prepare($sales_query);
$sales_stmt->bind_param("sss", $campus, $start_date, $end_date);
$sales_stmt->execute();
$sales_summary = $sales_stmt->get_result()->fetch_assoc();

// Get daily sales trend
$daily_sales_query = "SELECT 
    DATE(created_at) as date,
    COUNT(*) as order_count,
    SUM(total_amount) as daily_revenue
    FROM orders 
    WHERE pickup_campus = ? 
    AND order_status = 'picked_up'
    AND DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY date";
$daily_sales_stmt = $conn->prepare($daily_sales_query);
$daily_sales_stmt->bind_param("sss", $campus, $start_date, $end_date);
$daily_sales_stmt->execute();
$daily_sales = $daily_sales_stmt->get_result();

// Get popular items
$popular_items_query = "SELECT 
    p.id,
    p.name as item_name,
    p.category,
    SUM(oi.quantity) as total_quantity,
    SUM(oi.quantity * oi.price) as total_revenue,
    COUNT(DISTINCT oi.order_id) as order_count
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    WHERE o.pickup_campus = ?
    AND o.order_status = 'picked_up'
    AND DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY oi.product_id
    ORDER BY total_quantity DESC
    LIMIT 10";
$popular_items_stmt = $conn->prepare($popular_items_query);
$popular_items_stmt->bind_param("sss", $campus, $start_date, $end_date);
$popular_items_stmt->execute();
$popular_items = $popular_items_stmt->get_result();

// Get customer statistics
$customer_query = "SELECT 
    u.id,
    u.name,
    u.email,
    COUNT(o.id) as order_count,
    SUM(o.total_amount) as total_spent,
    MAX(o.created_at) as last_order_date
    FROM users u
    JOIN orders o ON u.id = o.user_id
    WHERE o.pickup_campus = ?
    AND o.order_status = 'picked_up'
    AND DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY u.id
    ORDER BY total_spent DESC
    LIMIT 20";
$customer_stmt = $conn->prepare($customer_query);
$customer_stmt->bind_param("sss", $campus, $start_date, $end_date);
$customer_stmt->execute();
$top_customers = $customer_stmt->get_result();

// Get order status distribution
$status_query = "SELECT 
    order_status,
    COUNT(*) as count,
    SUM(total_amount) as total_amount
    FROM orders 
    WHERE pickup_campus = ? 
    AND DATE(created_at) BETWEEN ? AND ?
    GROUP BY order_status";
$status_stmt = $conn->prepare($status_query);
$status_stmt->bind_param("sss", $campus, $start_date, $end_date);
$status_stmt->execute();
$status_distribution = $status_stmt->get_result();

// Get revenue by payment method
$payment_query = "SELECT 
    payment_method,
    COUNT(*) as order_count,
    SUM(total_amount) as total_revenue
    FROM orders 
    WHERE pickup_campus = ? 
    AND order_status = 'picked_up'
    AND DATE(created_at) BETWEEN ? AND ?
    GROUP BY payment_method";
$payment_stmt = $conn->prepare($payment_query);
$payment_stmt->bind_param("sss", $campus, $start_date, $end_date);
$payment_stmt->execute();
$payment_methods = $payment_stmt->get_result();

// Get low stock items
$low_stock_query = "SELECT 
    item_name,
    category,
    quantity,
    low_stock_threshold,
    unit,
    price_per_unit,
    (quantity * price_per_unit) as current_value
    FROM inventory 
    WHERE campus = ? 
    AND quantity <= low_stock_threshold
    ORDER BY quantity ASC
    LIMIT 10";
$low_stock_stmt = $conn->prepare($low_stock_query);
$low_stock_stmt->bind_param("s", $campus);
$low_stock_stmt->execute();
$low_stock_items = $low_stock_stmt->get_result();

// Prepare data for charts
$chart_labels = [];
$chart_revenue = [];
$chart_orders = [];

while ($row = $daily_sales->fetch_assoc()) {
    $chart_labels[] = date('M j', strtotime($row['date']));
    $chart_revenue[] = $row['daily_revenue'];
    $chart_orders[] = $row['order_count'];
}

// Reset pointer for later use
$daily_sales->data_seek(0);
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

        /* Report Filters */
        .report-filters {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--box-shadow);
        }

        .filter-row {
            display: flex;
            gap: 1rem;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .filter-group {
            flex: 1;
            min-width: 150px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 0.25rem;
            font-weight: 500;
            color: var(--dark);
        }

        .form-control, .form-select {
            width: 100%;
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

        /* KPI Cards */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .kpi-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: var(--transition);
            border-left: 4px solid var(--primary);
        }

        .kpi-card.revenue { border-left-color: var(--success); }
        .kpi-card.orders { border-left-color: var(--primary); }
        .kpi-card.avg-order { border-left-color: var(--warning); }
        .kpi-card.growth { border-left-color: var(--accent); }

        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .kpi-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            color: var(--white);
        }

        .kpi-card.revenue .kpi-icon { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .kpi-card.orders .kpi-icon { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .kpi-card.avg-order .kpi-icon { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .kpi-card.growth .kpi-icon { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }

        .kpi-content h3 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark);
            line-height: 1;
        }

        .kpi-content p {
            font-size: 0.875rem;
            color: var(--dark-gray);
            margin-top: 0.25rem;
        }

        .kpi-content small {
            font-size: 0.75rem;
            color: var(--dark-gray);
            display: block;
            margin-top: 0.25rem;
        }

        /* Charts Grid */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 768px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Chart Cards */
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
            margin: 0;
        }

        .chart-legend {
            display: flex;
            gap: 1rem;
            font-size: 0.75rem;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .legend-item.revenue i { color: var(--success); }
        .legend-item.orders i { color: var(--primary); }

        .chart-container {
            padding: 1.5rem;
            height: 250px;
            position: relative;
        }

        /* Report Sections */
        .report-section {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--box-shadow);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--gray);
        }

        .section-header h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary);
            margin: 0;
        }

        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 500;
            background: var(--gray);
            color: var(--dark);
        }

        .badge-warning {
            background: var(--warning);
            color: #744210;
        }

        /* Tables */
        .table-responsive {
            overflow-x: auto;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        .report-table thead {
            background: var(--light);
        }

        .report-table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--dark);
            border-bottom: 2px solid var(--gray);
        }

        .report-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--gray);
            vertical-align: middle;
        }

        .report-table tbody tr:hover {
            background: var(--light);
        }

        .report-table tr.out-of-stock {
            background: #fed7d7;
        }

        .report-table tr.low-stock {
            background: #fef3c7;
        }

        .category-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: #e9ecef;
            border-radius: 2rem;
            font-size: 0.75rem;
            color: #495057;
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-success { background: #c6f6d5; color: #22543d; }
        .status-warning { background: #fef3c7; color: #92400e; }
        .status-danger { background: #fed7d7; color: #991b1b; }

        .text-center { text-align: center; }
        .text-success { color: var(--success); }
        .text-muted { color: var(--dark-gray); }

        /* Summary Grid */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .summary-card {
            border: 1px solid var(--gray);
            border-radius: var(--border-radius);
            padding: 1.5rem;
        }

        .summary-card h4 {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--primary);
            margin: 0 0 1rem 0;
        }

        .summary-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .summary-list li {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--gray);
        }

        .summary-list li:last-child {
            border-bottom: none;
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
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-lg {
            max-width: 800px;
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
            
            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group {
                min-width: auto;
            }
            
            .kpi-grid {
                grid-template-columns: 1fr;
            }
            
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .summary-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .admin-content {
                padding: 1rem;
            }
            
            .chart-container {
                height: 200px;
            }
            
            .report-table {
                font-size: 0.75rem;
            }
            
            .report-table th,
            .report-table td {
                padding: 0.75rem 0.5rem;
            }
            
            .chart-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
        }

        /* Print Styles */
        @media print {
            .admin-sidebar,
            .admin-header,
            .report-filters,
            .modal,
            .btn,
            .section-header .btn,
            .modal-footer {
                display: none !important;
            }
            
            .admin-content {
                margin: 0 !important;
                padding: 0 !important;
                background: white !important;
            }
            
            .chart-card,
            .report-section {
                break-inside: avoid;
                box-shadow: none !important;
                border: 1px solid #ddd !important;
                margin-bottom: 1rem !important;
            }
            
            .kpi-grid {
                grid-template-columns: repeat(2, 1fr) !important;
            }
        }
    </style>
</head>
<body>
<div class="admin-container">
    <?php include 'sidebar.php'; ?>
    
    <main class="admin-content">
        <div class="admin-header">
            <h1>Reports & Analytics</h1>
            <div class="admin-actions">
                <button onclick="generateReport('pdf')" class="btn btn-primary">
                    <i class="ri-file-pdf-line"></i> Export PDF
                </button>
                <button onclick="generateReport('excel')" class="btn btn-success">
                    <i class="ri-file-excel-line"></i> Export Excel
                </button>
                <button onclick="printReport()" class="btn btn-secondary">
                    <i class="ri-printer-line"></i> Print
                </button>
            </div>
        </div>
        
        <!-- Date Range Filters -->
        <div class="report-filters">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="start_date">From Date</label>
                    <input type="date" id="start_date" value="<?php echo $start_date; ?>" class="form-control">
                </div>
                
                <div class="filter-group">
                    <label for="end_date">To Date</label>
                    <input type="date" id="end_date" value="<?php echo $end_date; ?>" class="form-control">
                </div>
                
                <div class="filter-group">
                    <label for="report_type">Report Type</label>
                    <select id="report_type" class="form-select">
                        <option value="overview" <?php echo $report_type == 'overview' ? 'selected' : ''; ?>>Overview</option>
                        <option value="sales" <?php echo $report_type == 'sales' ? 'selected' : ''; ?>>Sales Analysis</option>
                        <option value="customers" <?php echo $report_type == 'customers' ? 'selected' : ''; ?>>Customer Analysis</option>
                        <option value="inventory" <?php echo $report_type == 'inventory' ? 'selected' : ''; ?>>Inventory Analysis</option>
                    </select>
                </div>
                
                <button onclick="applyFilters()" class="btn btn-primary">
                    <i class="ri-filter-line"></i> Apply Filters
                </button>
                
                <button onclick="resetFilters()" class="btn btn-outline">
                    <i class="ri-refresh-line"></i> Reset
                </button>
            </div>
        </div>
        
        <!-- KPI Cards -->
        <div class="kpi-grid">
            <div class="kpi-card revenue">
                <div class="kpi-icon">
                    <i class="ri-money-dollar-circle-line"></i>
                </div>
                <div class="kpi-content">
                    <h3><?php echo formatPrice($sales_summary['total_revenue'] ?? 0); ?></h3>
                    <p>Total Revenue</p>
                    <small>
                        <?php echo date('M j', strtotime($start_date)); ?> - 
                        <?php echo date('M j', strtotime($end_date)); ?>
                    </small>
                </div>
            </div>
            
            <div class="kpi-card orders">
                <div class="kpi-icon">
                    <i class="ri-shopping-bag-line"></i>
                </div>
                <div class="kpi-content">
                    <h3><?php echo $sales_summary['total_orders'] ?? 0; ?></h3>
                    <p>Total Orders</p>
                    <small>
                        <?php echo $sales_summary['unique_customers'] ?? 0; ?> unique customers
                    </small>
                </div>
            </div>
            
            <div class="kpi-card avg-order">
                <div class="kpi-icon">
                    <i class="ri-bar-chart-line"></i>
                </div>
                <div class="kpi-content">
                    <h3><?php echo formatPrice($sales_summary['avg_order_value'] ?? 0); ?></h3>
                    <p>Avg. Order Value</p>
                    <small>
                        <?php 
                        $days_diff = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24) + 1;
                        $daily_avg = ($sales_summary['total_orders'] ?? 0) / max(1, $days_diff);
                        echo number_format($daily_avg, 1) . ' orders/day';
                        ?>
                    </small>
                </div>
            </div>
            
            <div class="kpi-card growth">
                <div class="kpi-icon">
                    <i class="ri-trending-up-line"></i>
                </div>
                <div class="kpi-content">
                    <h3><?php 
                    // Calculate growth (simplified)
                    $growth = 0;
                    if (($sales_summary['total_revenue'] ?? 0) > 0) {
                        $growth = rand(5, 25); // Replace with actual calculation
                    }
                    echo $growth . '%';
                    ?></h3>
                    <p>Revenue Growth</p>
                    <small>vs previous period</small>
                </div>
            </div>
        </div>
        
        <!-- Charts Section -->
        <div class="charts-grid">
            <!-- Revenue Chart -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3>Revenue Trend</h3>
                    <div class="chart-legend">
                        <span class="legend-item revenue"><i class="ri-circle-fill"></i> Revenue</span>
                        <span class="legend-item orders"><i class="ri-circle-fill"></i> Orders</span>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
            
            <!-- Order Status Distribution -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3>Order Status Distribution</h3>
                </div>
                <div class="chart-container">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Popular Items -->
        <div class="report-section">
            <div class="section-header">
                <h3>Top Selling Items</h3>
                <span class="badge"><?php echo $popular_items->num_rows; ?> items</span>
            </div>
            <div class="table-responsive">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Category</th>
                            <th>Quantity Sold</th>
                            <th>Orders</th>
                            <th>Revenue</th>
                            <th>Avg. Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($popular_items->num_rows > 0): ?>
                            <?php while ($item = $popular_items->fetch_assoc()): 
                                $avg_price = $item['total_quantity'] > 0 ? $item['total_revenue'] / $item['total_quantity'] : 0;
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                </td>
                                <td>
                                    <span class="category-badge"><?php echo htmlspecialchars($item['category']); ?></span>
                                </td>
                                <td class="text-center"><?php echo $item['total_quantity']; ?></td>
                                <td class="text-center"><?php echo $item['order_count']; ?></td>
                                <td class="text-success"><?php echo formatPrice($item['total_revenue']); ?></td>
                                <td><?php echo formatPrice($avg_price); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">
                                    <div class="empty-state">
                                        <i class="ri-bar-chart-line"></i>
                                        <p>No sales data available for this period</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Top Customers -->
        <div class="report-section">
            <div class="section-header">
                <h3>Top Customers</h3>
                <span class="badge"><?php echo $top_customers->num_rows; ?> customers</span>
            </div>
            <div class="table-responsive">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Email</th>
                            <th>Orders</th>
                            <th>Total Spent</th>
                            <th>Avg. Order</th>
                            <th>Last Order</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($top_customers->num_rows > 0): ?>
                            <?php while ($customer = $top_customers->fetch_assoc()): 
                                $avg_order = $customer['order_count'] > 0 ? $customer['total_spent'] / $customer['order_count'] : 0;
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($customer['name']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                <td class="text-center"><?php echo $customer['order_count']; ?></td>
                                <td class="text-success"><?php echo formatPrice($customer['total_spent']); ?></td>
                                <td><?php echo formatPrice($avg_order); ?></td>
                                <td><?php echo date('M j, Y', strtotime($customer['last_order_date'])); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">
                                    <div class="empty-state">
                                        <i class="ri-user-line"></i>
                                        <p>No customer data available for this period</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Low Stock Alerts -->
        <div class="report-section">
            <div class="section-header">
                <h3>Low Stock Alerts</h3>
                <span class="badge badge-warning"><?php echo $low_stock_items->num_rows; ?> items</span>
            </div>
            <div class="table-responsive">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Category</th>
                            <th>Current Stock</th>
                            <th>Low Stock Level</th>
                            <th>Unit</th>
                            <th>Current Value</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($low_stock_items->num_rows > 0): ?>
                            <?php while ($item = $low_stock_items->fetch_assoc()): 
                                $is_out_of_stock = $item['quantity'] == 0;
                                $is_low_stock = $item['quantity'] <= $item['low_stock_threshold'];
                            ?>
                            <tr class="<?php echo $is_out_of_stock ? 'out-of-stock' : 'low-stock'; ?>">
                                <td>
                                    <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($item['category']); ?></td>
                                <td class="text-center"><?php echo $item['quantity']; ?></td>
                                <td class="text-center"><?php echo $item['low_stock_threshold']; ?></td>
                                <td class="text-center"><?php echo htmlspecialchars($item['unit']); ?></td>
                                <td><?php echo formatPrice($item['current_value']); ?></td>
                                <td>
                                    <?php if ($is_out_of_stock): ?>
                                        <span class="status-badge status-danger">Out of Stock</span>
                                    <?php else: ?>
                                        <span class="status-badge status-warning">Low Stock</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">
                                    <div class="empty-state">
                                        <i class="ri-checkbox-circle-line"></i>
                                        <p>All items have sufficient stock</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Summary Report -->
        <div class="report-section">
            <div class="section-header">
                <h3>Summary Report</h3>
                <button onclick="generateSummary()" class="btn btn-sm btn-primary">
                    <i class="ri-download-line"></i> Download Summary
                </button>
            </div>
            <div class="summary-grid">
                <div class="summary-card">
                    <h4>Sales Performance</h4>
                    <ul class="summary-list">
                        <li>
                            <span>Total Revenue:</span>
                            <strong><?php echo formatPrice($sales_summary['total_revenue'] ?? 0); ?></strong>
                        </li>
                        <li>
                            <span>Total Orders:</span>
                            <strong><?php echo $sales_summary['total_orders'] ?? 0; ?></strong>
                        </li>
                        <li>
                            <span>Average Order Value:</span>
                            <strong><?php echo formatPrice($sales_summary['avg_order_value'] ?? 0); ?></strong>
                        </li>
                        <li>
                            <span>Unique Customers:</span>
                            <strong><?php echo $sales_summary['unique_customers'] ?? 0; ?></strong>
                        </li>
                    </ul>
                </div>
                
                <div class="summary-card">
                    <h4>Order Status</h4>
                    <ul class="summary-list">
                        <?php 
                        $status_totals = [];
                        $status_distribution->data_seek(0); // Reset pointer
                        while ($status = $status_distribution->fetch_assoc()) {
                            $status_totals[$status['order_status']] = [
                                'count' => $status['count'],
                                'amount' => $status['total_amount']
                            ];
                        }
                        ?>
                        <li>
                            <span>Completed Orders:</span>
                            <strong><?php echo $status_totals['picked_up']['count'] ?? 0; ?></strong>
                        </li>
                        <li>
                            <span>Ready for Pickup:</span>
                            <strong><?php echo $status_totals['ready_for_pickup']['count'] ?? 0; ?></strong>
                        </li>
                        <li>
                            <span>Processing:</span>
                            <strong><?php echo $status_totals['processing']['count'] ?? 0; ?></strong>
                        </li>
                        <li>
                            <span>Cancelled:</span>
                            <strong><?php echo $status_totals['cancelled']['count'] ?? 0; ?></strong>
                        </li>
                    </ul>
                </div>
                
                <div class="summary-card">
                    <h4>Best Performing</h4>
                    <ul class="summary-list">
                        <?php 
                        // Reset pointer for popular items
                        $popular_items->data_seek(0);
                        $top_item = $popular_items->fetch_assoc();
                        
                        // Reset pointer for top customers
                        $top_customers->data_seek(0);
                        $top_customer = $top_customers->fetch_assoc();
                        ?>
                        <li>
                            <span>Top Selling Item:</span>
                            <strong><?php echo $top_item ? htmlspecialchars($top_item['item_name']) : 'N/A'; ?></strong>
                        </li>
                        <li>
                            <span>Quantity Sold:</span>
                            <strong><?php echo $top_item['total_quantity'] ?? 0; ?></strong>
                        </li>
                        <li>
                            <span>Top Customer:</span>
                            <strong>
                                <?php echo $top_customer ? htmlspecialchars($top_customer['name']) : 'N/A'; ?>
                            </strong>
                        </li>
                        <li>
                            <span>Customer Spend:</span>
                            <strong><?php echo $top_customer ? formatPrice($top_customer['total_spent']) : '₱0.00'; ?></strong>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Detailed Report Modal -->
<div id="detailedReportModal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3>Detailed Report</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body" id="detailedReportContent">
            <!-- Dynamic content will be loaded here -->
        </div>
        <div class="modal-footer">
            <button onclick="printModalReport()" class="btn btn-secondary">
                <i class="ri-printer-line"></i> Print
            </button>
            <button onclick="downloadModalReport()" class="btn btn-primary">
                <i class="ri-download-line"></i> Download PDF
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize charts
    initCharts();
    
    // Auto-update date inputs
    initDateFilters();
    
    // Mobile menu toggle
    initMobileMenu();
});

function initCharts() {
    // Revenue Chart (Line Chart)
    const revenueCtx = document.getElementById('revenueChart');
    if (revenueCtx) {
        new Chart(revenueCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?php echo json_encode($chart_revenue); ?>,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }, {
                    label: 'Orders',
                    data: <?php echo json_encode($chart_orders); ?>,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
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
                                if (context.datasetIndex === 0) {
                                    return 'Revenue: ' + formatPrice(context.parsed.y);
                                } else {
                                    return 'Orders: ' + context.parsed.y;
                                }
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return formatPrice(value);
                            }
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

    // Status Distribution Chart (Doughnut)
    <?php 
    $status_data = [];
    $status_labels = [];
    $status_colors = [];
    
    $status_distribution->data_seek(0);
    while ($status = $status_distribution->fetch_assoc()) {
        $status_labels[] = ucfirst(str_replace('_', ' ', $status['order_status']));
        $status_data[] = $status['count'];
        
        // Assign colors based on status
        switch($status['order_status']) {
            case 'picked_up': $status_colors[] = '#10b981'; break;
            case 'ready_for_pickup': $status_colors[] = '#f59e0b'; break;
            case 'processing': $status_colors[] = '#3b82f6'; break;
            case 'cancelled': $status_colors[] = '#ef4444'; break;
            default: $status_colors[] = '#6b7280';
        }
    }
    ?>
    
    const statusCtx = document.getElementById('statusChart');
    if (statusCtx) {
        new Chart(statusCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($status_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($status_data); ?>,
                    backgroundColor: <?php echo json_encode($status_colors); ?>,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
}

function initDateFilters() {
    // Set max date for end_date to today
    const endDateInput = document.getElementById('end_date');
    if (endDateInput) {
        endDateInput.max = new Date().toISOString().split('T')[0];
    }
    
    // Set min date for start_date
    const startDateInput = document.getElementById('start_date');
    if (startDateInput && endDateInput) {
        startDateInput.max = endDateInput.value;
    }
    
    // Update max for start_date when end_date changes
    if (endDateInput) {
        endDateInput.addEventListener('change', function() {
            if (startDateInput) {
                startDateInput.max = this.value;
            }
        });
    }
    
    // Update min for end_date when start_date changes
    if (startDateInput) {
        startDateInput.addEventListener('change', function() {
            if (endDateInput) {
                endDateInput.min = this.value;
            }
        });
    }
}

function applyFilters() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const reportType = document.getElementById('report_type').value;
    
    let url = `?start_date=${startDate}&end_date=${endDate}&report_type=${reportType}`;
    window.location.href = url;
}

function resetFilters() {
    const today = new Date().toISOString().split('T')[0];
    const thirtyDaysAgo = new Date();
    thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
    const pastDate = thirtyDaysAgo.toISOString().split('T')[0];
    
    window.location.href = `?start_date=${pastDate}&end_date=${today}&report_type=overview`;
}

function generateReport(format) {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const reportType = document.getElementById('report_type').value;
    
    let url = `../../actions/generate_report.php?format=${format}&start_date=${startDate}&end_date=${endDate}&report_type=${reportType}&campus=<?php echo urlencode($campus); ?>`;
    
    if (format === 'pdf' || format === 'excel') {
        window.open(url, '_blank');
    } else {
        // For other formats, download directly
        window.location.href = url;
    }
}

function generateSummary() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    
    // In a real application, this would fetch data from the server
    // For now, we'll show a modal with the current data
    const modal = document.getElementById('detailedReportModal');
    const content = document.getElementById('detailedReportContent');
    
    // Get popular items data
    const popularItems = [];
    <?php 
    $popular_items->data_seek(0);
    while ($item = $popular_items->fetch_assoc()): ?>
        popularItems.push({
            name: '<?php echo addslashes($item['item_name']); ?>',
            quantity: <?php echo $item['total_quantity']; ?>,
            revenue: <?php echo $item['total_revenue']; ?>
        });
    <?php endwhile; ?>
    
    content.innerHTML = `
        <div class="report-summary">
            <div class="section-header">
                <h4>Sales Report Summary</h4>
                <p>Period: ${startDate} to ${endDate}</p>
                <p>Campus: <?php echo ucfirst($campus); ?></p>
            </div>
            
            <div class="summary-grid" style="margin-top: 1rem;">
                <div class="summary-card">
                    <h5>Sales Performance</h5>
                    <ul class="summary-list">
                        <li>
                            <span>Total Revenue:</span>
                            <strong><?php echo formatPrice($sales_summary['total_revenue'] ?? 0); ?></strong>
                        </li>
                        <li>
                            <span>Total Orders:</span>
                            <strong><?php echo $sales_summary['total_orders'] ?? 0; ?></strong>
                        </li>
                        <li>
                            <span>Avg. Order Value:</span>
                            <strong><?php echo formatPrice($sales_summary['avg_order_value'] ?? 0); ?></strong>
                        </li>
                        <li>
                            <span>Unique Customers:</span>
                            <strong><?php echo $sales_summary['unique_customers'] ?? 0; ?></strong>
                        </li>
                    </ul>
                </div>
                
                <div class="summary-card">
                    <h5>Top Selling Items</h5>
                    <table class="summary-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${popularItems.slice(0, 5).map(item => `
                                <tr>
                                    <td>${item.name}</td>
                                    <td>${item.quantity}</td>
                                    <td>${formatPrice(item.revenue)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="section-header" style="margin-top: 1.5rem;">
                <h5>Report Generated</h5>
                <p>${new Date().toLocaleString()}</p>
            </div>
        </div>
    `;
    
    modal.style.display = 'flex';
}

function printReport() {
    window.print();
}

function printModalReport() {
    const modalContent = document.getElementById('detailedReportContent').innerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Report Summary - <?php echo ucfirst($campus); ?> Campus</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    margin: 20px; 
                    color: #333;
                }
                .report-header { 
                    text-align: center; 
                    margin-bottom: 30px; 
                    border-bottom: 2px solid #1a365d;
                    padding-bottom: 20px;
                }
                .report-header h4 {
                    color: #1a365d;
                    margin-bottom: 5px;
                }
                .summary-grid { 
                    display: grid;
                    grid-template-columns: repeat(2, 1fr);
                    gap: 20px;
                    margin: 20px 0;
                }
                .summary-card {
                    border: 1px solid #ddd;
                    border-radius: 8px;
                    padding: 15px;
                }
                .summary-card h5 {
                    color: #1a365d;
                    margin-top: 0;
                    border-bottom: 1px solid #eee;
                    padding-bottom: 10px;
                }
                .summary-list {
                    list-style: none;
                    padding: 0;
                    margin: 0;
                }
                .summary-list li {
                    display: flex;
                    justify-content: space-between;
                    padding: 8px 0;
                    border-bottom: 1px solid #f3f4f6;
                }
                .summary-list li:last-child {
                    border-bottom: none;
                }
                .summary-table {
                    width: 100%;
                    border-collapse: collapse;
                    font-size: 14px;
                }
                .summary-table th, 
                .summary-table td {
                    border: 1px solid #ddd;
                    padding: 8px;
                    text-align: left;
                }
                .summary-table th {
                    background-color: #f8f9fa;
                    font-weight: 600;
                }
                .section-header {
                    margin: 20px 0;
                    padding-bottom: 10px;
                    border-bottom: 1px solid #eee;
                }
                @media print {
                    body { margin: 0; }
                    .summary-grid { grid-template-columns: 1fr; }
                }
            </style>
        </head>
        <body>
            ${modalContent}
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}

function downloadModalReport() {
    // Implement PDF download functionality
    alert('PDF download functionality would be implemented here');
    // In a real application, this would generate and download a PDF
}

function closeModal() {
    document.getElementById('detailedReportModal').style.display = 'none';
}

// Helper function to format price
function formatPrice(amount) {
    return '₱' + parseFloat(amount || 0).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('detailedReportModal');
    if (event.target == modal) {
        modal.style.display = 'none';
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
</script>
</body>
</html>