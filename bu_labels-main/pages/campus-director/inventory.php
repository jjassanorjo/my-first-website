<?php
// pages/campus-director/inventory.php

require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is campus director
if (!isLoggedIn() || $_SESSION['user_role'] != 'director') {
    redirect('../../account.php');
}

$page_title = "Inventory Management";
$body_class = "director-inventory";

$campus = $_SESSION['user']['campus'];
$conn = getDBConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_inventory'])) {
        // Add new inventory item
        $item_name = escape($_POST['item_name']);
        $category = escape($_POST['category']);
        $quantity = (int)$_POST['quantity'];
        $unit = escape($_POST['unit']);
        $low_stock_threshold = (int)$_POST['low_stock_threshold'];
        $price_per_unit = floatval($_POST['price_per_unit']);
        $supplier = escape($_POST['supplier']);
        $description = escape($_POST['description']);
        
        $stmt = $conn->prepare("INSERT INTO inventory (campus, item_name, category, quantity, unit, low_stock_threshold, price_per_unit, supplier, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssss", $campus, $item_name, $category, $quantity, $unit, $low_stock_threshold, $price_per_unit, $supplier, $description);
        
        if ($stmt->execute()) {
            $success_message = "Inventory item added successfully!";
        } else {
            $error_message = "Error adding inventory item: " . $conn->error;
        }
    } elseif (isset($_POST['update_inventory'])) {
        // Update inventory item
        $inventory_id = (int)$_POST['inventory_id'];
        $quantity = (int)$_POST['quantity'];
        $low_stock_threshold = (int)$_POST['low_stock_threshold'];
        $price_per_unit = floatval($_POST['price_per_unit']);
        $supplier = escape($_POST['supplier']);
        
        $stmt = $conn->prepare("UPDATE inventory SET quantity = ?, low_stock_threshold = ?, price_per_unit = ?, supplier = ? WHERE id = ? AND campus = ?");
        $stmt->bind_param("ssssss", $quantity, $low_stock_threshold, $price_per_unit, $supplier, $inventory_id, $campus);
        
        if ($stmt->execute()) {
            $success_message = "Inventory updated successfully!";
        } else {
            $error_message = "Error updating inventory: " . $conn->error;
        }
    }
}

// Get inventory items
$search = isset($_GET['search']) ? escape($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? escape($_GET['category']) : 'all';
$stock_filter = isset($_GET['stock']) ? escape($_GET['stock']) : 'all';

$query = "SELECT * FROM inventory WHERE campus = ?";
$params = [$campus];
$types = "s";

if (!empty($search)) {
    $query .= " AND (item_name LIKE ? OR description LIKE ? OR supplier LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}

if ($category_filter != 'all') {
    $query .= " AND category = ?";
    $params[] = $category_filter;
    $types .= "s";
}

if ($stock_filter == 'low') {
    $query .= " AND quantity <= low_stock_threshold";
} elseif ($stock_filter == 'out') {
    $query .= " AND quantity = 0";
}

$query .= " ORDER BY item_name ASC";

$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$inventory_items = $stmt->get_result();

// Get inventory statistics
$stats_query = "SELECT 
    COUNT(*) as total_items,
    SUM(quantity) as total_quantity,
    SUM(quantity * price_per_unit) as total_value,
    SUM(CASE WHEN quantity <= low_stock_threshold THEN 1 ELSE 0 END) as low_stock_items,
    SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END) as out_of_stock_items
    FROM inventory 
    WHERE campus = ?";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("s", $campus);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

// Get categories for filter
$categories_stmt = $conn->prepare("SELECT DISTINCT category FROM inventory WHERE campus = ? ORDER BY category");
$categories_stmt->bind_param("s", $campus);
$categories_stmt->execute();
$categories = $categories_stmt->get_result();
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

        .btn-warning {
            background: var(--warning);
            color: var(--dark);
        }

        .btn-danger {
            background: var(--danger);
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

        .stat-icon:nth-child(1) { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-icon:nth-child(2) { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stat-icon:nth-child(3) { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
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

        .admin-table tr.low-stock {
            background: #fef3c7;
        }

        .admin-table tr.out-of-stock {
            background: #fed7d7;
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

        .category-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: #e9ecef;
            border-radius: 2rem;
            font-size: 0.75rem;
            color: #495057;
        }

        .action-buttons {
            display: flex;
            gap: 0.25rem;
        }

        /* Filters */
        .filters-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-bar {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .search-bar i {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }

        .search-bar input {
            width: 100%;
            padding: 0.5rem 0.75rem 0.5rem 2.5rem;
            border: 1px solid var(--gray);
            border-radius: var(--border-radius);
            font-size: 0.875rem;
            transition: var(--transition);
        }

        .search-bar input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 54, 93, 0.1);
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

        select {
            padding: 0.5rem;
            border: 1px solid var(--gray);
            border-radius: var(--border-radius);
            font-size: 0.875rem;
            transition: var(--transition);
        }

        select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 54, 93, 0.1);
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

        .alert-danger {
            background: #fed7d7;
            color: #742a2a;
            border-left: 4px solid var(--danger);
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

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--gray);
            border-radius: var(--border-radius);
            font-size: 0.875rem;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 54, 93, 0.1);
        }

        .text-muted {
            color: var(--dark-gray);
            font-size: 0.75rem;
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
            
            .filters-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-bar {
                min-width: auto;
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
        }
    </style>
</head>
<body>
<div class="admin-container">
    <?php include 'sidebar.php'; ?>
    
    <main class="admin-content">
        <div class="admin-header">
            <h1>Inventory Management</h1>
            <div class="admin-actions">
                <button onclick="openAddItemModal()" class="btn btn-primary">
                    <i class="ri-add-line"></i> Add Item
                </button>
                <button onclick="exportInventory()" class="btn btn-secondary">
                    <i class="ri-download-line"></i> Export
                </button>
            </div>
        </div>
        
        <?php if (isset($success_message)): ?>
        <div class="alert alert-success">
            <i class="ri-checkbox-circle-line"></i>
            <?php echo $success_message; ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
        <div class="alert alert-danger">
            <i class="ri-error-warning-line"></i>
            <?php echo $error_message; ?>
        </div>
        <?php endif; ?>
        
        <!-- Inventory Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="ri-box-3-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['total_items'] ?? 0; ?></h3>
                    <p>Total Items</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="ri-stack-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['total_quantity'] ?? 0; ?></h3>
                    <p>Total Quantity</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="ri-money-dollar-circle-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo formatPrice($stats['total_value'] ?? 0); ?></h3>
                    <p>Total Value</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="ri-alert-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['low_stock_items'] ?? 0; ?></h3>
                    <p>Low Stock Items</p>
                    <?php if ($stats['out_of_stock_items'] > 0): ?>
                    <small style="color: var(--danger);"><?php echo $stats['out_of_stock_items']; ?> out of stock</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters-bar">
            <div class="search-bar">
                <i class="ri-search-line"></i>
                <input type="text" id="searchInput" placeholder="Search items..." 
                       value="<?php echo htmlspecialchars($search); ?>"
                       onkeyup="searchInventory()">
            </div>
            
            <div class="filter-group">
                <label for="categoryFilter">Category:</label>
                <select id="categoryFilter" onchange="filterInventory()">
                    <option value="all">All Categories</option>
                    <?php while ($category = $categories->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($category['category']); ?>"
                                <?php echo $category_filter == $category['category'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['category']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="stockFilter">Stock Status:</label>
                <select id="stockFilter" onchange="filterInventory()">
                    <option value="all" <?php echo $stock_filter == 'all' ? 'selected' : ''; ?>>All Items</option>
                    <option value="low" <?php echo $stock_filter == 'low' ? 'selected' : ''; ?>>Low Stock</option>
                    <option value="out" <?php echo $stock_filter == 'out' ? 'selected' : ''; ?>>Out of Stock</option>
                </select>
            </div>
        </div>
        
        <!-- Inventory Table -->
        <div class="card">
            <div class="card-header">
                <h3>Inventory Items</h3>
                <span class="badge" style="background: var(--gray); color: var(--dark); padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.875rem;">
                    <?php echo $inventory_items->num_rows; ?> items
                </span>
            </div>
            
            <div class="card-body">
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>Quantity</th>
                                <th>Unit</th>
                                <th>Low Stock</th>
                                <th>Price/Unit</th>
                                <th>Total Value</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($inventory_items->num_rows > 0): ?>
                                <?php while ($item = $inventory_items->fetch_assoc()): 
                                    $total_value = $item['quantity'] * $item['price_per_unit'];
                                    $is_low_stock = $item['quantity'] <= $item['low_stock_threshold'];
                                    $is_out_of_stock = $item['quantity'] == 0;
                                ?>
                                <tr class="<?php echo $is_out_of_stock ? 'out-of-stock' : ($is_low_stock ? 'low-stock' : ''); ?>">
                                    <td>
                                        <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                        <?php if (!empty($item['description'])): ?>
                                        <br><small style="color: var(--dark-gray);"><?php echo htmlspecialchars($item['item_name']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="category-badge"><?php echo htmlspecialchars($item['category']); ?></span>
                                    </td>
                                    <td>
                                        <span style="font-weight: 600;"><?php echo $item['quantity']; ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['unit']); ?></td>
                                    <td><?php echo $item['low_stock_threshold']; ?></td>
                                    <td><?php echo formatPrice($item['price_per_unit']); ?></td>
                                    <td><?php echo formatPrice($total_value); ?></td>
                                    <td>
                                        <?php if ($is_out_of_stock): ?>
                                            <span class="status-badge status-danger">Out of Stock</span>
                                        <?php elseif ($is_low_stock): ?>
                                            <span class="status-badge status-warning">Low Stock</span>
                                        <?php else: ?>
                                            <span class="status-badge status-success">In Stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button onclick="openEditItemModal(<?php echo $item['id']; ?>)" 
                                                    class="btn btn-sm btn-secondary" title="Edit">
                                                <i class="ri-edit-line"></i>
                                            </button>
                                            <button onclick="deleteItem(<?php echo $item['id']; ?>)" 
                                                    class="btn btn-sm btn-danger" title="Delete">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center">
                                        <div class="empty-state">
                                            <i class="ri-box-3-line"></i>
                                            <p>No inventory items found</p>
                                            <button onclick="openAddItemModal()" class="btn btn-primary">
                                                Add Your First Item
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Add Item Modal -->
<div id="addItemModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New Inventory Item</h3>
            <button class="modal-close" onclick="closeModal('addItemModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <div class="form-group">
                    <label for="item_name">Item Name *</label>
                    <input type="text" id="item_name" name="item_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="category">Category *</label>
                    <select id="category" name="category" class="form-control" required>
                        <option value="">Select Category</option>
                        <option value="Food">Food</option>
                        <option value="Beverage">Beverage</option>
                        <option value="Paper Goods">Paper Goods</option>
                        <option value="Cleaning Supplies">Cleaning Supplies</option>
                        <option value="Office Supplies">Office Supplies</option>
                        <option value="Packaging">Packaging</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="quantity">Initial Quantity *</label>
                    <input type="number" id="quantity" name="quantity" class="form-control" min="0" step="0.01" required>
                </div>
                
                <div class="form-group">
                    <label for="unit">Unit *</label>
                    <select id="unit" name="unit" class="form-control" required>
                        <option value="">Select Unit</option>
                        <option value="pcs">Pieces</option>
                        <option value="kg">Kilograms</option>
                        <option value="g">Grams</option>
                        <option value="L">Liters</option>
                        <option value="ml">Milliliters</option>
                        <option value="box">Box</option>
                        <option value="pack">Pack</option>
                        <option value="bottle">Bottle</option>
                        <option value="can">Can</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="low_stock_threshold">Low Stock Threshold *</label>
                    <input type="number" id="low_stock_threshold" name="low_stock_threshold" class="form-control" min="0" required>
                    <small class="text-muted">Alert when quantity falls below this number</small>
                </div>
                
                <div class="form-group">
                    <label for="price_per_unit">Price per Unit *</label>
                    <input type="number" id="price_per_unit" name="price_per_unit" class="form-control" min="0" step="0.01" required>
                </div>
                
                <div class="form-group">
                    <label for="supplier">Supplier</label>
                    <input type="text" id="supplier" name="supplier" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addItemModal')">Cancel</button>
                <button type="submit" name="add_inventory" class="btn btn-primary">Add Item</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Item Modal -->
<div id="editItemModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Inventory Item</h3>
            <button class="modal-close" onclick="closeModal('editItemModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="modal-body" id="editItemContent">
                <!-- Content will be loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editItemModal')">Cancel</button>
                <button type="submit" name="update_inventory" class="btn btn-primary">Update Item</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddItemModal() {
    document.getElementById('addItemModal').style.display = 'flex';
}

function openEditItemModal(itemId) {
    fetch(`../../actions/get_inventory_item.php?id=${itemId}`)
        .then(response => response.json())
        .then(data => {
            const modal = document.getElementById('editItemModal');
            const content = document.getElementById('editItemContent');
            
            content.innerHTML = `
                <input type="hidden" name="inventory_id" value="${data.id}">
                <div class="form-group">
                    <label>Item Name</label>
                    <input type="text" class="form-control" value="${data.item_name}" readonly>
                </div>
                
                <div class="form-group">
                    <label>Category</label>
                    <input type="text" class="form-control" value="${data.category}" readonly>
                </div>
                
                <div class="form-group">
                    <label for="edit_quantity">Quantity *</label>
                    <input type="number" id="edit_quantity" name="quantity" class="form-control" 
                           value="${data.quantity}" min="0" step="0.01" required>
                </div>
                
                <div class="form-group">
                    <label>Unit</label>
                    <input type="text" class="form-control" value="${data.unit}" readonly>
                </div>
                
                <div class="form-group">
                    <label for="edit_low_stock_threshold">Low Stock Threshold *</label>
                    <input type="number" id="edit_low_stock_threshold" name="low_stock_threshold" 
                           class="form-control" value="${data.low_stock_threshold}" min="0" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_price_per_unit">Price per Unit *</label>
                    <input type="number" id="edit_price_per_unit" name="price_per_unit" 
                           class="form-control" value="${data.price_per_unit}" min="0" step="0.01" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_supplier">Supplier</label>
                    <input type="text" id="edit_supplier" name="supplier" class="form-control" 
                           value="${data.supplier || ''}">
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea class="form-control" readonly>${data.description || ''}</textarea>
                </div>
            `;
            
            modal.style.display = 'flex';
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading item details');
        });
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function searchInventory() {
    const searchInput = document.getElementById('searchInput').value;
    const categoryFilter = document.getElementById('categoryFilter').value;
    const stockFilter = document.getElementById('stockFilter').value;
    
    let url = `?search=${encodeURIComponent(searchInput)}`;
    if (categoryFilter !== 'all') url += `&category=${encodeURIComponent(categoryFilter)}`;
    if (stockFilter !== 'all') url += `&stock=${encodeURIComponent(stockFilter)}`;
    
    window.location.href = url;
}

function filterInventory() {
    const searchInput = document.getElementById('searchInput').value;
    const categoryFilter = document.getElementById('categoryFilter').value;
    const stockFilter = document.getElementById('stockFilter').value;
    
    let url = '?';
    if (searchInput) url += `search=${encodeURIComponent(searchInput)}&`;
    if (categoryFilter !== 'all') url += `category=${encodeURIComponent(categoryFilter)}&`;
    if (stockFilter !== 'all') url += `stock=${encodeURIComponent(stockFilter)}&`;
    
    // Remove trailing & or ?
    url = url.replace(/[&?]$/, '');
    window.location.href = url;
}

function deleteItem(itemId) {
    if (confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
        fetch(`../../actions/delete_inventory_item.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: itemId })
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

function exportInventory() {
    const search = document.getElementById('searchInput').value;
    const category = document.getElementById('categoryFilter').value;
    const stock = document.getElementById('stockFilter').value;
    
    let url = `../../actions/export_inventory.php?campus=<?php echo urlencode($campus); ?>`;
    if (search) url += `&search=${encodeURIComponent(search)}`;
    if (category !== 'all') url += `&category=${encodeURIComponent(category)}`;
    if (stock !== 'all') url += `&stock=${encodeURIComponent(stock)}`;
    
    window.open(url, '_blank');
}

// Close modals when clicking outside
window.onclick = function(event) {
    const modals = ['addItemModal', 'editItemModal'];
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    });
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