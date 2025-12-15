<?php
// pages/admin/products.php

require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is admin
if (!isLoggedIn() || $_SESSION['user_role'] != 'admin') {
    redirect('../../account.php');
}

$page_title = "Manage Products";
$body_class = "admin-products";

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$conn = getDBConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_product'])) {
        $name = escape($_POST['name']);
        $slug = strtolower(str_replace(' ', '-', $name));
        $description = escape($_POST['description']);
        $price = (float)$_POST['price'];
        $category_id = (int)$_POST['category_id'];
        $featured = isset($_POST['featured']) ? 1 : 0;
        
        // Handle sizes
        $sizes = [];
        if (isset($_POST['sizes']) && is_array($_POST['sizes'])) {
            $sizes = array_map('escape', $_POST['sizes']);
        }
        $sizes_json = json_encode($sizes);
        
        // Handle stock
        $stock_main = (int)$_POST['stock_main'];
        $stock_east = (int)$_POST['stock_east'];
        $stock_west = (int)$_POST['stock_west'];
        $stock_north = (int)$_POST['stock_north'];
        $stock_south = (int)$_POST['stock_south'];
        
        if ($product_id > 0) {
            // Update existing product
            $stmt = $conn->prepare("UPDATE products SET name = ?, slug = ?, description = ?, price = ?, category_id = ?, sizes = ?, stock_main = ?, stock_east = ?, stock_west = ?, stock_north = ?, stock_south = ?, featured = ? WHERE id = ?");
            $stmt->bind_param("sssdisiiiiiis", $name, $slug, $description, $price, $category_id, $sizes_json, $stock_main, $stock_east, $stock_west, $stock_north, $stock_south, $featured, $product_id);
        } else {
            // Insert new product
            $stmt = $conn->prepare("INSERT INTO products (name, slug, description, price, category_id, sizes, stock_main, stock_east, stock_west, stock_north, stock_south, featured) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssdisiiiiis", $name, $slug, $description, $price, $category_id, $sizes_json, $stock_main, $stock_east, $stock_west, $stock_north, $stock_south, $featured);
        }
        
        if ($stmt->execute()) {
            // Get the product ID for new products
            if ($product_id == 0) {
                $product_id = $conn->insert_id;
            }
            
            // Handle image upload - FIXED VERSION
            if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] == 0) {
                // Ensure upload directory exists
                $upload_dir = '../../assets/images/products/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Check if upload directory is writable
                if (!is_writable($upload_dir)) {
                    $_SESSION['error'] = "Upload directory is not writable. Please check permissions.";
                    redirect('products.php?action=' . $action . ($product_id > 0 ? '&id=' . $product_id : ''));
                }
                
                // Validate file type
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $file_type = $_FILES['main_image']['type'];
                
                if (!in_array($file_type, $allowed_types)) {
                    $_SESSION['error'] = "Only JPG, PNG, GIF, and WebP images are allowed.";
                    redirect('products.php?action=' . $action . ($product_id > 0 ? '&id=' . $product_id : ''));
                }
                
                // Validate file size (max 2MB)
                $max_size = 2 * 1024 * 1024; // 2MB in bytes
                if ($_FILES['main_image']['size'] > $max_size) {
                    $_SESSION['error'] = "Image size must be less than 2MB.";
                    redirect('products.php?action=' . $action . ($product_id > 0 ? '&id=' . $product_id : ''));
                }
                
                // Delete old image if exists (for updates)
                if ($product_id > 0) {
                    $old_image_stmt = $conn->prepare("SELECT image_main FROM products WHERE id = ?");
                    $old_image_stmt->bind_param("i", $product_id);
                    $old_image_stmt->execute();
                    $old_image_result = $old_image_stmt->get_result();
                    $old_product = $old_image_result->fetch_assoc();
                    
                    if (!empty($old_product['image_main'])) {
                        $old_image_path = '../../assets/images/' . $old_product['image_main'];
                        if (file_exists($old_image_path)) {
                            unlink($old_image_path);
                        }
                    }
                }
                
                // Generate unique filename
                $file_extension = pathinfo($_FILES['main_image']['name'], PATHINFO_EXTENSION);
                $filename = 'product-' . $product_id . '-' . time() . '.' . strtolower($file_extension);
                $target_file = $upload_dir . $filename;
                
                // Debug info
                error_log("DEBUG - Uploading file: " . $_FILES['main_image']['name']);
                error_log("DEBUG - Target file: " . $target_file);
                
                // Move uploaded file
                if (move_uploaded_file($_FILES['main_image']['tmp_name'], $target_file)) {
                    // Update database with relative path
                    $image_path = 'products/' . $filename;
                    $update_stmt = $conn->prepare("UPDATE products SET image_main = ? WHERE id = ?");
                    $update_stmt->bind_param("si", $image_path, $product_id);
                    
                    if (!$update_stmt->execute()) {
                        $_SESSION['error'] = "Failed to update image in database: " . $conn->error;
                        // Delete the uploaded file since DB update failed
                        if (file_exists($target_file)) {
                            unlink($target_file);
                        }
                    } else {
                        error_log("DEBUG - Image saved to DB: " . $image_path);
                    }
                } else {
                    $upload_error = error_get_last();
                    $_SESSION['error'] = "Failed to upload image: " . ($upload_error['message'] ?? 'Unknown error');
                    error_log("DEBUG - Upload failed: " . ($upload_error['message'] ?? 'Unknown error'));
                }
            }
            
            $_SESSION['success'] = "Product saved successfully!";
            redirect('products.php?action=edit&id=' . $product_id);
        } else {
            $error = "Error saving product: " . $conn->error;
        }
    }
    
    if (isset($_POST['delete_product'])) {
        $delete_id = (int)$_POST['delete_id'];
        
        // Get product image before deleting
        $stmt = $conn->prepare("SELECT image_main FROM products WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result->fetch_assoc();
        
        // Delete product image if exists
        if (!empty($product['image_main'])) {
            $image_path = '../../assets/images/' . $product['image_main'];
            if (file_exists($image_path)) {
                unlink($image_path);
            }
        }
        
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Product deleted successfully!";
            redirect('products.php');
        } else {
            $error = "Error deleting product: " . $conn->error;
        }
    }
}

// Get product data for edit
if ($action == 'edit' && $product_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $product_result = $stmt->get_result();
    $product = $product_result->fetch_assoc();
    
    if (!$product) {
        redirect('products.php');
    }
    
    $sizes = json_decode($product['sizes'] ?? '[]', true);
}

// Get categories
$categories_result = $conn->query("SELECT * FROM categories ORDER BY name");

// Get products for listing
if ($action == 'list') {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    // Search and filter
    $search = isset($_GET['search']) ? escape($_GET['search']) : '';
    $category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
    $featured = isset($_GET['featured']) ? $_GET['featured'] : '';
    
    $sql = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE 1=1";
    $params = [];
    $types = "";
    
    if ($search) {
        $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $types .= "ss";
    }
    
    if ($category > 0) {
        $sql .= " AND p.category_id = ?";
        $params[] = $category;
        $types .= "i";
    }
    
    if ($featured === '1') {
        $sql .= " AND p.featured = 1";
    } elseif ($featured === '0') {
        $sql .= " AND p.featured = 0";
    }
    
    // Get total count
    $count_sql = str_replace("SELECT p.*, c.name as category_name", "SELECT COUNT(*) as total", $sql);
    $count_stmt = $conn->prepare($count_sql);
    if ($params) {
        $count_stmt->bind_param($types, ...$params);
    }
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_rows = $count_result->fetch_assoc()['total'];
    $total_pages = ceil($total_rows / $limit);
    
    // Add pagination
    $sql .= " ORDER BY p.id DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    
    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $products_result = $stmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BU Labels - Admin | <?php echo $action == 'add' ? 'Add Product' : ($action == 'edit' ? 'Edit Product' : 'Manage Products'); ?></title>
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

        /* Product Form */
        .product-form {
            max-width: 100%;
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
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--gray);
            border-radius: var(--border-radius);
            font-size: 0.875rem;
            background: var(--white);
            transition: var(--transition);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 54, 93, 0.1);
        }

        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }

        .form-group small {
            display: block;
            margin-top: 0.5rem;
            color: var(--dark-gray);
            font-size: 0.75rem;
        }

        .form-section {
            margin: 2rem 0;
            padding-top: 1.5rem;
            border-top: 1px solid var(--gray);
        }

        .form-section h4 {
            font-size: 1.125rem;
            color: var(--primary);
            margin-bottom: 1rem;
            font-weight: 600;
        }

        /* Checkbox Styles */
        .checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            user-select: none;
        }

        .checkbox input[type="checkbox"] {
            width: auto;
            margin: 0;
        }

        .checkbox span {
            font-weight: 500;
            color: var(--dark);
        }

        /* Sizes Grid */
        .sizes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 0.75rem;
        }

        .size-checkbox {
            padding: 0.75rem;
            border: 2px solid var(--gray);
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .size-checkbox:hover {
            border-color: var(--primary);
            background: rgba(26, 54, 93, 0.05);
        }

        .size-checkbox input[type="checkbox"]:checked + span {
            color: var(--primary);
            font-weight: 600;
        }

        .size-checkbox input[type="checkbox"]:checked ~ .size-checkbox {
            border-color: var(--primary);
            background: rgba(26, 54, 93, 0.1);
        }

        /* Stock Grid */
        .stock-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        /* Image Preview */
        .image-preview {
            margin-top: 1rem;
        }

        .image-preview img {
            max-width: 200px;
            height: auto;
            border-radius: var(--border-radius);
            border: 1px solid var(--gray);
        }

        .image-preview p {
            margin-top: 0.5rem;
            font-size: 0.875rem;
            color: var(--dark-gray);
        }

        /* Current Image */
        .current-image {
            margin-top: 1rem;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }

        .current-image img {
            max-width: 150px;
            height: auto;
            border-radius: var(--border-radius);
            border: 1px solid var(--gray);
            cursor: pointer;
        }

        .image-info {
            flex: 1;
        }

        .image-info p {
            font-size: 0.875rem;
            color: var(--dark-gray);
            margin-bottom: 0.25rem;
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--gray);
        }

        /* Search Form */
        .search-form {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .search-form input,
        .search-form select {
            padding: 0.625rem;
            border: 1px solid var(--gray);
            border-radius: var(--border-radius);
            font-size: 0.875rem;
            background: var(--white);
            min-width: 180px;
        }

        .search-form input:focus,
        .search-form select:focus {
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

        /* Product Info in Table */
        .product-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .product-thumb {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: var(--border-radius);
            border: 1px solid var(--gray);
        }

        .product-info div {
            flex: 1;
        }

        .product-info strong {
            display: block;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .product-info small {
            color: var(--dark-gray);
            font-size: 0.75rem;
            line-height: 1.4;
        }

        /* Status Badges */
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

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-good { background: #c6f6d5; color: #22543d; }
        .status-critical { background: #fed7d7; color: #742a2a; }

        /* Stock Indicator */
        .stock-indicator {
            position: relative;
            height: 24px;
            background: var(--gray);
            border-radius: 12px;
            overflow: hidden;
        }

        .stock-bar {
            height: 100%;
            background: var(--success);
            transition: width 0.3s ease;
        }

        .stock-indicator span {
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
        .btn-view:hover { background: var(--accent); }
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

        /* Loading Overlay */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 3000;
            align-items: center;
            justify-content: center;
        }

        .loading-overlay.active {
            display: flex;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Debug info */
        .debug-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 10px;
            margin: 10px 0;
            font-family: monospace;
            font-size: 12px;
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
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .stock-grid {
                grid-template-columns: 1fr;
            }
            
            .sizes-grid {
                grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            }
            
            .search-form {
                flex-direction: column;
            }
            
            .search-form input,
            .search-form select {
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
            
            .current-image {
                flex-direction: column;
            }
            
            .current-image img {
                max-width: 100%;
            }
        }

        @media (max-width: 480px) {
            .admin-content {
                padding: 1rem;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
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
    $current_page = 'products.php';
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
                    <li class="active">
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
            <h1>
                <?php 
                if ($action == 'add') echo "Add New Product";
                elseif ($action == 'edit') echo "Edit Product";
                else echo "Manage Products";
                ?>
            </h1>
            
            <div class="admin-actions">
                <?php if ($action == 'list'): ?>
                <a href="products.php?action=add" class="btn btn-primary">
                    <i class="ri-add-line"></i> Add Product
                </a>
                <?php else: ?>
                <a href="products.php" class="btn btn-outline">
                    <i class="ri-arrow-left-line"></i> Back to Products
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
        
        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <i class="ri-error-warning-line"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <i class="ri-error-warning-line"></i>
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <!-- Debug Information -->
        <?php if (isset($_FILES['main_image'])): ?>
        <div class="debug-info">
            <strong>Debug Info:</strong><br>
            File Name: <?php echo $_FILES['main_image']['name']; ?><br>
            File Size: <?php echo $_FILES['main_image']['size']; ?> bytes<br>
            File Error: <?php echo $_FILES['main_image']['error']; ?><br>
            Upload Dir: <?php echo '../../assets/images/products/'; ?><br>
            Dir Exists: <?php echo is_dir('../../assets/images/products/') ? 'Yes' : 'No'; ?><br>
            Dir Writable: <?php echo is_writable('../../assets/images/products/') ? 'Yes' : 'No'; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($action == 'add' || $action == 'edit'): ?>
        <!-- Product Form -->
        <div class="card">
            <div class="card-header">
                <h3>Product Information</h3>
            </div>
            
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" class="product-form" id="productForm">
                    <input type="hidden" name="save_product" value="1">
                    
                    <div class="form-row">
                        <div class="form-group required">
                            <label for="name">Product Name *</label>
                            <input type="text" id="name" name="name" required 
                                   value="<?php echo isset($product['name']) ? sanitize($product['name']) : ''; ?>"
                                   placeholder="Enter product name">
                        </div>
                        
                        <div class="form-group required">
                            <label for="category_id">Category *</label>
                            <select id="category_id" name="category_id" required>
                                <option value="">Select Category</option>
                                <?php 
                                $categories_result->data_seek(0);
                                while ($cat = $categories_result->fetch_assoc()): 
                                ?>
                                <option value="<?php echo $cat['id']; ?>"
                                    <?php echo (isset($product['category_id']) && $product['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo sanitize($cat['name']); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group required">
                        <label for="description">Description *</label>
                        <textarea id="description" name="description" rows="5" required 
                                  placeholder="Enter product description"><?php echo isset($product['description']) ? sanitize($product['description']) : ''; ?></textarea>
                        <small>HTML tags are allowed for formatting</small>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group required">
                            <label for="price">Price (₱) *</label>
                            <input type="number" id="price" name="price" step="0.01" min="0" required 
                                   value="<?php echo isset($product['price']) ? $product['price'] : ''; ?>"
                                   placeholder="0.00">
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox">
                                <input type="checkbox" name="featured" value="1"
                                    <?php echo (isset($product['featured']) && $product['featured']) ? 'checked' : ''; ?>>
                                <span>Featured Product</span>
                            </label>
                            <small>Featured products appear on homepage</small>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h4>Product Images</h4>
                        
                        <div class="form-group">
                            <label for="main_image">Main Product Image <?php echo ($action == 'edit') ? '' : '*'; ?></label>
                            <input type="file" id="main_image" name="main_image" accept="image/*" 
                                   <?php echo ($action == 'add') ? 'required' : ''; ?>
                                   onchange="previewImage(this)">
                            
                            <?php if (isset($product['image_main']) && $product['image_main']): ?>
                            <div class="current-image">
                                <img src="../../assets/images/<?php echo $product['image_main']; ?>" 
                                     alt="Current Image" id="currentImage" class="clickable-image">
                                <div class="image-info">
                                    <p>Current Main Image: <?php echo basename($product['image_main']); ?></p>
                                    <small>Click the image to view full size</small>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="image-preview" id="imagePreview" style="display: none;">
                                <p>New Main Image Preview:</p>
                                <img id="previewImage" src="" alt="Preview">
                            </div>
                            
                            <small>Recommended: 800x800px, JPG/PNG, max 2MB. This will be the featured image.</small>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h4>Available Sizes</h4>
                        <div class="sizes-grid">
                            <?php $size_options = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'One Size']; ?>
                            <?php foreach ($size_options as $size): ?>
                            <div class="size-checkbox">
                                <input type="checkbox" id="size_<?php echo strtolower($size); ?>" 
                                       name="sizes[]" value="<?php echo $size; ?>"
                                    <?php echo (isset($sizes) && in_array($size, $sizes)) ? 'checked' : ''; ?>>
                                <label for="size_<?php echo strtolower($size); ?>">
                                    <?php echo $size; ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <small>Select all sizes available for this product</small>
                    </div>
                    
                    <div class="form-section">
                        <h4>Stock Management</h4>
                        <div class="stock-grid">
                            <div class="form-group">
                                <label for="stock_main">Main Campus Stock</label>
                                <input type="number" id="stock_main" name="stock_main" min="0" 
                                       value="<?php echo isset($product['stock_main']) ? $product['stock_main'] : 0; ?>">
                                <small>Main Campus inventory</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="stock_east">Polangui Campus Stock</label>
                                <input type="number" id="stock_east" name="stock_east" min="0"
                                       value="<?php echo isset($product['stock_east']) ? $product['stock_east'] : 0; ?>">
                                <small>Polangui Campus inventory</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="stock_west">Guinobatan Campus Stock</label>
                                <input type="number" id="stock_west" name="stock_west" min="0"
                                       value="<?php echo isset($product['stock_west']) ? $product['stock_west'] : 0; ?>">
                                <small>Guinobatan Campus inventory</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="stock_north">Gubat Campus Stock</label>
                                <input type="number" id="stock_north" name="stock_north" min="0"
                                       value="<?php echo isset($product['stock_north']) ? $product['stock_north'] : 0; ?>">
                                <small>Gubat Campus inventory</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="stock_south">BUIDEA Stock</label>
                                <input type="number" id="stock_south" name="stock_south" min="0"
                                       value="<?php echo isset($product['stock_south']) ? $product['stock_south'] : 0; ?>">
                                <small>BUIDEA inventory</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <div>
                            <button type="button" class="btn btn-outline" onclick="window.history.back()">
                                <i class="ri-close-line"></i> Cancel
                            </button>
                        </div>
                        <div class="action-buttons">
                            <button type="submit" class="btn btn-primary">
                                <i class="ri-save-line"></i> Save Product
                            </button>
                            <?php if ($action == 'edit'): ?>
                            <button type="button" class="btn btn-outline" onclick="resetForm()">
                                <i class="ri-refresh-line"></i> Reset
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if ($action == 'edit'): ?>
        <!-- Danger Zone -->
        <div class="card" style="border-left: 4px solid var(--danger);">
            <div class="card-header">
                <h3><i class="ri-error-warning-line"></i> Danger Zone</h3>
            </div>
            <div class="card-body">
                <p><strong>Warning:</strong> Deleting this product will permanently remove it from the system. 
                This action cannot be undone. All associated data including inventory records will be lost.</p>
                <button type="button" class="btn btn-danger" onclick="confirmDelete(<?php echo $product_id; ?>, '<?php echo sanitize($product['name']); ?>')">
                    <i class="ri-delete-bin-line"></i> Delete Product
                </button>
            </div>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <!-- Products List -->
        <div class="card">
            <div class="card-header">
                <h3>Products List</h3>
                
                <div class="card-tools">
                    <form method="GET" class="search-form">
                        <input type="text" name="search" placeholder="Search products..." 
                               value="<?php echo isset($_GET['search']) ? sanitize($_GET['search']) : ''; ?>">
                        <select name="category" onchange="this.form.submit()">
                            <option value="">All Categories</option>
                            <?php 
                            $categories_result->data_seek(0);
                            while ($cat = $categories_result->fetch_assoc()): 
                            ?>
                            <option value="<?php echo $cat['id']; ?>"
                                <?php echo (isset($_GET['category']) && $_GET['category'] == $cat['id']) ? 'selected' : ''; ?>>
                                <?php echo sanitize($cat['name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                        <select name="featured" onchange="this.form.submit()">
                            <option value="">All Products</option>
                            <option value="1" <?php echo (isset($_GET['featured']) && $_GET['featured'] === '1') ? 'selected' : ''; ?>>Featured Only</option>
                            <option value="0" <?php echo (isset($_GET['featured']) && $_GET['featured'] === '0') ? 'selected' : ''; ?>>Non-Featured</option>
                        </select>
                        <button type="submit" class="btn btn-small">
                            <i class="ri-search-line"></i>
                        </button>
                        <a href="products.php" class="btn btn-small btn-outline">
                            <i class="ri-refresh-line"></i> Reset
                        </a>
                    </form>
                </div>
            </div>
            
            <div class="card-body">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th width="50">ID</th>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Featured</th>
                                <th>Status</th>
                                <th width="150">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($products_result->num_rows > 0): ?>
                                <?php while ($product = $products_result->fetch_assoc()): 
                                    $total_stock = $product['stock_main'] + $product['stock_east'] + $product['stock_west'] + $product['stock_north'] + $product['stock_south'];
                                ?>
                                <tr>
                                    <td>#<?php echo $product['id']; ?></td>
                                    <td>
                                        <div class="product-info">
                                            <?php if (!empty($product['image_main'])): ?>
                                            <img src="../../assets/images/<?php echo $product['image_main']; ?>" 
                                                 alt="<?php echo sanitize($product['name']); ?>"
                                                 class="product-thumb">
                                            <?php else: ?>
                                            <img src="../../assets/images/placeholder.png" 
                                                 alt="No image"
                                                 class="product-thumb">
                                            <?php endif; ?>
                                            <div>
                                                <strong><?php echo sanitize($product['name']); ?></strong>
                                                <small><?php echo substr($product['description'], 0, 50); ?>...</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo sanitize($product['category_name']); ?></td>
                                    <td>₱<?php echo number_format($product['price'], 2); ?></td>
                                    <td>
                                        <div class="stock-indicator">
                                            <div class="stock-bar" style="width: <?php echo min(100, ($total_stock / 50) * 100); ?>%"></div>
                                            <span><?php echo $total_stock; ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($product['featured']): ?>
                                        <span class="badge badge-success">Yes</span>
                                        <?php else: ?>
                                        <span class="badge">No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($total_stock > 0): ?>
                                        <span class="status-badge status-good">In Stock</span>
                                        <?php else: ?>
                                        <span class="status-badge status-critical">Out of Stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="products.php?action=edit&id=<?php echo $product['id']; ?>" 
                                               class="btn-icon btn-edit" title="Edit">
                                                <i class="ri-edit-line"></i>
                                            </a>
                                            <a href="../../product-detail.php?slug=<?php echo $product['slug']; ?>" 
                                               target="_blank" class="btn-icon btn-view" title="View">
                                                <i class="ri-eye-line"></i>
                                            </a>
                                            <button type="button" class="btn-icon btn-delete" 
                                                    onclick="confirmDelete(<?php echo $product['id']; ?>, '<?php echo sanitize($product['name']); ?>')"
                                                    title="Delete">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center">
                                        <div class="empty-state">
                                            <i class="ri-box-3-line"></i>
                                            <p>No products found</p>
                                            <a href="products.php?action=add" class="btn btn-primary btn-small">
                                                Add Your First Product
                                            </a>
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

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal" id="deleteModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Confirm Delete</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete <strong id="deleteProductName"></strong>?</p>
            <p class="text-danger">This action cannot be undone.</p>
        </div>
        <div class="modal-footer">
            <form method="POST" id="deleteForm">
                <input type="hidden" name="delete_product" value="1">
                <input type="hidden" name="delete_id" id="deleteProductId">
                <button type="button" class="btn btn-outline modal-close">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete</button>
            </form>
        </div>
    </div>
</div>

<script>
// Main image preview function
function previewImage(input) {
    const preview = document.getElementById('imagePreview');
    const previewImage = document.getElementById('previewImage');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            previewImage.src = e.target.result;
            preview.style.display = 'block';
        }
        
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.style.display = 'none';
    }
}

// View image in full size
document.querySelectorAll('.clickable-image').forEach(img => {
    img.addEventListener('click', function() {
        window.open(this.src, '_blank');
    });
});

// Reset form to original values (for edit)
function resetForm() {
    if (confirm('Are you sure you want to reset the form? All unsaved changes will be lost.')) {
        // Reload the page to get original values
        window.location.reload();
    }
}

function confirmDelete(productId, productName) {
    document.getElementById('deleteProductId').value = productId;
    document.getElementById('deleteProductName').textContent = productName;
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

// Form submission with loading and validation
document.addEventListener('DOMContentLoaded', function() {
    const productForm = document.getElementById('productForm');
    if (productForm) {
        productForm.addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            
            // Validate required fields
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.style.borderColor = 'var(--danger)';
                    field.style.boxShadow = '0 0 0 3px rgba(245, 101, 101, 0.1)';
                }
            });
            
            // Validate price
            const priceField = document.getElementById('price');
            if (priceField && (priceField.value <= 0 || isNaN(priceField.value))) {
                isValid = false;
                priceField.style.borderColor = 'var(--danger)';
                priceField.style.boxShadow = '0 0 0 3px rgba(245, 101, 101, 0.1)';
                showAlert('Please enter a valid price (greater than 0)', 'error');
            }
            
            // Validate sizes (at least one size should be selected)
            const sizeCheckboxes = document.querySelectorAll('input[name="sizes[]"]:checked');
            if (sizeCheckboxes.length === 0) {
                isValid = false;
                showAlert('Please select at least one size', 'error');
            }
            
            if (!isValid) {
                e.preventDefault();
                showAlert('Please fill in all required fields correctly', 'error');
                
                // Scroll to first error
                const firstError = document.querySelector('[required]:invalid');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstError.focus();
                }
                
                return;
            }
            
            // Show loading overlay
            document.getElementById('loadingOverlay').classList.add('active');
        });
    }
    
    // Remove error styles on input
    const formFields = document.querySelectorAll('input, textarea, select');
    formFields.forEach(field => {
        field.addEventListener('input', function() {
            if (this.value.trim()) {
                this.style.borderColor = '';
                this.style.boxShadow = '';
            }
        });
    });
});

// Show alert message
function showAlert(message, type) {
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.innerHTML = `
        <i class="ri-${type === 'success' ? 'checkbox-circle-line' : 'error-warning-line'}"></i>
        ${message}
    `;
    
    // Insert after header
    const adminHeader = document.querySelector('.admin-header');
    adminHeader.parentNode.insertBefore(alert, adminHeader.nextSibling);
    
    // Remove after 3 seconds
    setTimeout(() => {
        alert.remove();
    }, 3000);
}

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
    
    .form-group.required label::after {
        content: " *";
        color: var(--danger);
    }
    
    .clickable-image {
        cursor: pointer;
        transition: transform 0.2s ease;
    }
    
    .clickable-image:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
`;
document.head.appendChild(style);
</script>
</body>
</html>