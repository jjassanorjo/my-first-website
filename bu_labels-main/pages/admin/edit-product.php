<?php
// pages/admin/edit-product.php

require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is admin
if (!isLoggedIn() || $_SESSION['user_role'] != 'admin') {
    redirect('../../account.php');
}

$page_title = "Edit Product";
$body_class = "admin-edit-product";

// Get product ID from URL
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$product_id) {
    $_SESSION['error'] = "Product ID is required";
    redirect('products.php');
}

$conn = getDBConnection();

// Get product data
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product_result = $stmt->get_result();
$product = $product_result->fetch_assoc();

if (!$product) {
    $_SESSION['error'] = "Product not found";
    redirect('products.php');
}

// Decode sizes
$sizes = json_decode($product['sizes'] ?? '[]', true);

// Decode gallery images
$gallery_images = [];
if (!empty($product['image_gallery'])) {
    $gallery_images = json_decode($product['image_gallery'], true);
    if (!is_array($gallery_images)) {
        $gallery_images = [];
    }
}

// Get categories for dropdown
$categories_result = $conn->query("SELECT * FROM categories ORDER BY name");

// Handle gallery image removal via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_gallery_image'])) {
    $image_index = (int)$_POST['image_index'];
    
    if (isset($gallery_images[$image_index])) {
        $image_to_remove = $gallery_images[$image_index];
        $image_path = '../../assets/images/' . $image_to_remove;
        
        // Remove from array
        unset($gallery_images[$image_index]);
        $gallery_images = array_values($gallery_images); // Re-index array
        
        // Update database
        $gallery_json = json_encode($gallery_images);
        $update_stmt = $conn->prepare("UPDATE products SET image_gallery = ? WHERE id = ?");
        $update_stmt->bind_param("si", $gallery_json, $product_id);
        
        if ($update_stmt->execute()) {
            // Delete physical file
            if (file_exists($image_path)) {
                unlink($image_path);
            }
            echo json_encode(['success' => true, 'message' => 'Image removed successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update database']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Image not found']);
    }
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    $name = escape($_POST['name']);
    $slug = strtolower(str_replace(' ', '-', $name));
    $description = escape($_POST['description']);
    $price = (float)$_POST['price'];
    $category_id = (int)$_POST['category_id'];
    $featured = isset($_POST['featured']) ? 1 : 0;
    
    // Handle sizes
    $new_sizes = [];
    if (isset($_POST['sizes']) && is_array($_POST['sizes'])) {
        $new_sizes = array_map('escape', $_POST['sizes']);
    }
    $sizes_json = json_encode($new_sizes);
    
    // Handle stock
    $stock_main = (int)$_POST['stock_main'];
    $stock_east = (int)$_POST['stock_east'];
    $stock_west = (int)$_POST['stock_west'];
    $stock_north = (int)$_POST['stock_north'];
    $stock_south = (int)$_POST['stock_south'];
    
    // Prepare update statement
    $stmt = $conn->prepare("UPDATE products SET 
        name = ?, slug = ?, description = ?, price = ?, 
        category_id = ?, sizes = ?, featured = ?,
        stock_main = ?, stock_east = ?, stock_west = ?, 
        stock_north = ?, stock_south = ?,
        updated_at = NOW()
        WHERE id = ?");
    
    $stmt->bind_param("sssdisiiiiiis", 
        $name, $slug, $description, $price, $category_id, 
        $sizes_json, $featured,
        $stock_main, $stock_east, $stock_west, 
        $stock_north, $stock_south,
        $product_id
    );
    
    if ($stmt->execute()) {
        // Handle main image upload - FIXED VERSION
        if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] == 0) {
            // Ensure upload directory exists
            $upload_dir = '../../assets/images/products/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Check if upload directory is writable
            if (!is_writable($upload_dir)) {
                $_SESSION['error'] = "Upload directory is not writable. Please check permissions.";
                redirect('edit-product.php?id=' . $product_id);
            }
            
            // Validate file type
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $file_type = $_FILES['main_image']['type'];
            
            if (!in_array($file_type, $allowed_types)) {
                $_SESSION['error'] = "Only JPG, PNG, GIF, and WebP images are allowed.";
                redirect('edit-product.php?id=' . $product_id);
            }
            
            // Validate file size (max 2MB)
            $max_size = 2 * 1024 * 1024; // 2MB in bytes
            if ($_FILES['main_image']['size'] > $max_size) {
                $_SESSION['error'] = "Image size must be less than 2MB.";
                redirect('edit-product.php?id=' . $product_id);
            }
            
            // Delete old image if exists
            if (!empty($product['image_main'])) {
                $old_image = '../../assets/images/' . $product['image_main'];
                if (file_exists($old_image)) {
                    unlink($old_image);
                }
            }
            
            // Generate unique filename
            $file_extension = pathinfo($_FILES['main_image']['name'], PATHINFO_EXTENSION);
            $filename = 'product-' . $product_id . '-' . time() . '.' . strtolower($file_extension);
            $target_file = $upload_dir . $filename;
            
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
                }
            } else {
                $_SESSION['error'] = "Failed to upload image. Please try again.";
            }
        }
        
        // Handle gallery images upload - FIXED VERSION
        if (isset($_FILES['gallery_images']) && is_array($_FILES['gallery_images']['name'])) {
            $new_gallery_images = [];
            $upload_dir = '../../assets/images/products/';
            
            // Ensure upload directory exists
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            foreach ($_FILES['gallery_images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['gallery_images']['error'][$key] == 0) {
                    // Validate file type
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    $file_type = $_FILES['gallery_images']['type'][$key];
                    
                    if (!in_array($file_type, $allowed_types)) {
                        continue; // Skip invalid file types
                    }
                    
                    // Validate file size (max 5MB)
                    $max_size = 5 * 1024 * 1024; // 5MB in bytes
                    if ($_FILES['gallery_images']['size'][$key] > $max_size) {
                        continue; // Skip files that are too large
                    }
                    
                    $file_extension = pathinfo($_FILES['gallery_images']['name'][$key], PATHINFO_EXTENSION);
                    $filename = 'product-' . $product_id . '-gallery-' . time() . '-' . $key . '.' . strtolower($file_extension);
                    $target_file = $upload_dir . $filename;
                    
                    if (move_uploaded_file($tmp_name, $target_file)) {
                        $new_gallery_images[] = 'products/' . $filename;
                    }
                }
            }
            
            // Merge with existing gallery images
            if (!empty($new_gallery_images)) {
                $merged_gallery = array_merge($gallery_images, $new_gallery_images);
                $gallery_json = json_encode($merged_gallery);
                
                $update_stmt = $conn->prepare("UPDATE products SET image_gallery = ? WHERE id = ?");
                $update_stmt->bind_param("si", $gallery_json, $product_id);
                if (!$update_stmt->execute()) {
                    $_SESSION['error'] = "Failed to update gallery images in database: " . $conn->error;
                }
            }
        }
        
        // Refresh product data after update
        $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $product_result = $stmt->get_result();
        $product = $product_result->fetch_assoc();
        
        $_SESSION['success'] = "Product updated successfully!";
        redirect('edit-product.php?id=' . $product_id);
    } else {
        $error = "Error updating product: " . $conn->error;
    }
}

// Handle delete product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    // Delete main image if exists
    if (!empty($product['image_main'])) {
        $image_path = '../../assets/images/' . $product['image_main'];
        if (file_exists($image_path)) {
            unlink($image_path);
        }
    }
    
    // Delete gallery images if exist
    if (!empty($gallery_images)) {
        foreach ($gallery_images as $image) {
            $image_path = '../../assets/images/' . $image;
            if (file_exists($image_path)) {
                unlink($image_path);
            }
        }
    }
    
    $delete_stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $delete_stmt->bind_param("i", $product_id);
    
    if ($delete_stmt->execute()) {
        $_SESSION['success'] = "Product deleted successfully!";
        redirect('products.php');
    } else {
        $error = "Error deleting product: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BU Labels - Admin | Edit Product</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        /* ... (keep all existing CSS styles) ... */
        
        /* New styles for gallery images */
        .gallery-section {
            margin-top: 2rem;
        }
        
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .gallery-item {
            position: relative;
            border-radius: var(--border-radius);
            overflow: hidden;
            border: 1px solid var(--gray);
            transition: var(--transition);
        }
        
        .gallery-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--box-shadow);
        }
        
        .gallery-item img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            display: block;
        }
        
        .gallery-remove {
            position: absolute;
            top: 5px;
            right: 5px;
            width: 28px;
            height: 28px;
            background: var(--danger);
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
            opacity: 0.8;
            transition: var(--transition);
        }
        
        .gallery-remove:hover {
            opacity: 1;
            transform: scale(1.1);
        }
        
        .gallery-empty {
            text-align: center;
            padding: 2rem;
            background: var(--light);
            border-radius: var(--border-radius);
            color: var(--dark-gray);
        }
        
        .gallery-empty i {
            font-size: 2rem;
            margin-bottom: 1rem;
            display: block;
        }
        
        .gallery-upload-area {
            border: 2px dashed var(--gray);
            border-radius: var(--border-radius);
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 1rem;
        }
        
        .gallery-upload-area:hover {
            border-color: var(--primary);
            background: rgba(26, 54, 93, 0.02);
        }
        
        .gallery-upload-area.dragover {
            border-color: var(--primary);
            background: rgba(26, 54, 93, 0.05);
        }
        
        .gallery-upload-area i {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }
        
        .gallery-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .gallery-preview-item {
            width: 80px;
            height: 80px;
            border-radius: var(--border-radius);
            overflow: hidden;
            position: relative;
            border: 1px solid var(--gray);
        }
        
        .gallery-preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .gallery-preview-remove {
            position: absolute;
            top: 2px;
            right: 2px;
            width: 20px;
            height: 20px;
            background: var(--danger);
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 0.625rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        
        .upload-progress {
            margin-top: 1rem;
        }
        
        .progress-bar {
            height: 6px;
            background: var(--gray);
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }
        
        .progress-fill {
            height: 100%;
            background: var(--primary);
            width: 0%;
            transition: width 0.3s ease;
        }
        
        .progress-text {
            font-size: 0.75rem;
            color: var(--dark-gray);
            text-align: center;
        }
        
        /* Image counter */
        .image-counter {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            background: var(--light);
            border-radius: 12px;
            font-size: 0.75rem;
            color: var(--dark-gray);
            margin-left: 0.5rem;
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
            display: none; /* Hide by default */
        }
    </style>
</head>
<body>
<div class="admin-container">
    <?php 
    // Include sidebar
    $current_page = 'products.php';
    include 'sidebar.php';
    ?>
    
    <main class="admin-content">
        <div class="admin-header">
            <h1>
                Edit Product
                <span style="font-size: 1rem; color: var(--dark-gray); font-weight: normal;">
                    #<?php echo $product_id; ?> - <?php echo sanitize($product['name']); ?>
                </span>
            </h1>
            
            <div class="admin-actions">
                <a href="products.php" class="btn btn-outline">
                    <i class="ri-arrow-left-line"></i> Back to Products
                </a>
                <a href="../../product-detail.php?slug=<?php echo $product['slug']; ?>" 
                   target="_blank" class="btn btn-outline">
                    <i class="ri-eye-line"></i> View Product
                </a>
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
        
        <!-- Debug Information (can be removed after testing) -->
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
        
        <!-- Product Stats -->
        <div class="product-stats">
            <div class="stat-item">
                <h4>
                    <?php 
                    $total_stock = $product['stock_main'] + $product['stock_east'] + $product['stock_west'] + 
                                  $product['stock_north'] + $product['stock_south'];
                    echo $total_stock;
                    ?>
                </h4>
                <p>Total Stock</p>
            </div>
            <div class="stat-item">
                <h4><?php echo formatPrice($product['price']); ?></h4>
                <p>Current Price</p>
            </div>
            <div class="stat-item">
                <h4><?php echo $product['category_id']; ?></h4>
                <p>Category ID</p>
            </div>
            <div class="stat-item">
                <span class="status-indicator <?php echo $product['featured'] ? 'status-active' : 'status-inactive'; ?>">
                    <i class="ri-<?php echo $product['featured'] ? 'star-fill' : 'star-line'; ?>"></i>
                    <?php echo $product['featured'] ? 'Featured' : 'Not Featured'; ?>
                    <span class="image-counter">
                        <i class="ri-image-line"></i> <?php echo count($gallery_images) + (!empty($product['image_main']) ? 1 : 0); ?>
                    </span>
                </span>
                <p>Featured Status</p>
            </div>
        </div>
        
        <!-- Product Form -->
        <div class="card">
            <div class="card-header">
                <h3>Edit Product Information</h3>
            </div>
            
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" class="product-form" id="editProductForm">
                    <input type="hidden" name="update_product" value="1">
                    
                    <div class="form-row">
                        <div class="form-group required">
                            <label for="name">Product Name</label>
                            <input type="text" id="name" name="name" required 
                                   value="<?php echo sanitize($product['name']); ?>"
                                   placeholder="Enter product name">
                        </div>
                        
                        <div class="form-group required">
                            <label for="category_id">Category</label>
                            <select id="category_id" name="category_id" required>
                                <option value="">Select Category</option>
                                <?php 
                                $categories_result->data_seek(0);
                                while ($cat = $categories_result->fetch_assoc()): 
                                ?>
                                <option value="<?php echo $cat['id']; ?>"
                                    <?php echo ($product['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo sanitize($cat['name']); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group required">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="5" required
                                  placeholder="Enter product description"><?php echo sanitize($product['description']); ?></textarea>
                        <small>HTML tags are allowed for formatting</small>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group required">
                            <label for="price">Price (₱)</label>
                            <input type="number" id="price" name="price" step="0.01" min="0" required 
                                   value="<?php echo $product['price']; ?>"
                                   placeholder="0.00">
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox">
                                <input type="checkbox" name="featured" value="1"
                                    <?php echo $product['featured'] ? 'checked' : ''; ?>>
                                <span>Featured Product</span>
                            </label>
                            <small>Featured products appear on homepage</small>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h4>Product Images</h4>
                        
                        <!-- Main Image -->
                        <div class="form-group">
                            <label for="main_image">Main Product Image</label>
                            <input type="file" id="main_image" name="main_image" accept="image/*"
                                   onchange="previewImage(this)">
                            
                            <?php if (!empty($product['image_main'])): ?>
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
                        
                        <!-- Gallery Images -->
                        <div class="gallery-section">
                            <h4>Gallery Images <small>(Additional product images)</small></h4>
                            
                            <!-- Current Gallery Images -->
                            <?php if (!empty($gallery_images)): ?>
                            <div class="form-group">
                                <label>Current Gallery Images</label>
                                <div class="gallery-grid" id="currentGallery">
                                    <?php foreach ($gallery_images as $index => $image): ?>
                                    <div class="gallery-item" data-index="<?php echo $index; ?>">
                                        <img src="../../assets/images/<?php echo $image; ?>" 
                                             alt="Gallery Image <?php echo $index + 1; ?>" 
                                             class="clickable-image">
                                        <button type="button" class="gallery-remove" 
                                                onclick="removeGalleryImage(<?php echo $index; ?>)">
                                            <i class="ri-close-line"></i>
                                        </button>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Gallery Upload Area -->
                            <div class="form-group">
                                <label>Add More Gallery Images</label>
                                <div class="gallery-upload-area" id="galleryUploadArea">
                                    <i class="ri-upload-cloud-2-line"></i>
                                    <p>Drag & drop images here or click to browse</p>
                                    <p class="text-small">Supports JPG, PNG, GIF. Max 5MB per image.</p>
                                    <input type="file" id="gallery_images" name="gallery_images[]" 
                                           accept="image/*" multiple style="display: none;">
                                </div>
                                
                                <!-- Gallery Preview -->
                                <div class="gallery-preview" id="galleryPreview"></div>
                                
                                <!-- Upload Progress -->
                                <div class="upload-progress" id="uploadProgress" style="display: none;">
                                    <div class="progress-bar">
                                        <div class="progress-fill" id="progressFill"></div>
                                    </div>
                                    <div class="progress-text" id="progressText">Uploading: 0%</div>
                                </div>
                                
                                <small>You can upload multiple images at once. Images will appear in product gallery.</small>
                            </div>
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
                                    <?php echo in_array($size, $sizes) ? 'checked' : ''; ?>>
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
                                       value="<?php echo $product['stock_main']; ?>">
                                <small>Main Campus inventory</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="stock_east">Polangui Campus Stock</label>
                                <input type="number" id="stock_east" name="stock_east" min="0"
                                       value="<?php echo $product['stock_east']; ?>">
                                <small>Poalngui Campus inventory</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="stock_west">Guinobatan Campus Stock</label>
                                <input type="number" id="stock_west" name="stock_west" min="0"
                                       value="<?php echo $product['stock_west']; ?>">
                                <small>Guinobatan Campus inventory</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="stock_north">Gubat Campus Stock</label>
                                <input type="number" id="stock_north" name="stock_north" min="0"
                                       value="<?php echo $product['stock_north']; ?>">
                                <small>Gubat Campus inventory</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="stock_south">BUIDEA  Stock</label>
                                <input type="number" id="stock_south" name="stock_south" min="0"
                                       value="<?php echo $product['stock_south']; ?>">
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
                                <i class="ri-save-line"></i> Save Changes
                            </button>
                            <button type="button" class="btn btn-warning" onclick="resetForm()">
                                <i class="ri-refresh-line"></i> Reset Form
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Danger Zone -->
        <div class="danger-zone">
            <h4><i class="ri-error-warning-line"></i> Danger Zone</h4>
            <p>
                <strong>Warning:</strong> Deleting this product will permanently remove it from the system. 
                This action cannot be undone. All associated data including inventory records will be lost.
            </p>
            <button type="button" class="btn btn-danger" onclick="showDeleteModal()">
                <i class="ri-delete-bin-line"></i> Delete Product
            </button>
        </div>
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
            <p>Are you sure you want to delete <strong>"<?php echo sanitize($product['name']); ?>"</strong>?</p>
            <p class="text-danger">
                <i class="ri-error-warning-line"></i> This action cannot be undone. All product data will be permanently deleted.
            </p>
            
            <div class="delete-details">
                <p><strong>Product Details:</strong></p>
                <ul>
                    <li>Product ID: #<?php echo $product_id; ?></li>
                    <li>Name: <?php echo sanitize($product['name']); ?></li>
                    <li>Price: <?php echo formatPrice($product['price']); ?></li>
                    <li>Total Stock: <?php echo $total_stock; ?> units</li>
                    <li>Images: <?php echo count($gallery_images) + (!empty($product['image_main']) ? 1 : 0); ?> files</li>
                </ul>
            </div>
            
            <div class="form-group">
                <label for="confirmDelete">Type "DELETE" to confirm:</label>
                <input type="text" id="confirmDelete" placeholder="Type DELETE here" 
                       oninput="checkDeleteConfirmation(this)">
            </div>
        </div>
        <div class="modal-footer">
            <form method="POST" id="deleteForm">
                <input type="hidden" name="delete_product" value="1">
                <button type="button" class="btn btn-outline modal-close">Cancel</button>
                <button type="submit" class="btn btn-danger" id="deleteButton" disabled>
                    Delete Product
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
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

// Gallery upload functionality
document.addEventListener('DOMContentLoaded', function() {
    const galleryUploadArea = document.getElementById('galleryUploadArea');
    const galleryInput = document.getElementById('gallery_images');
    const galleryPreview = document.getElementById('galleryPreview');
    
    // Click to browse
    galleryUploadArea.addEventListener('click', function() {
        galleryInput.click();
    });
    
    // File input change
    galleryInput.addEventListener('change', function() {
        previewGalleryImages(this.files);
    });
    
    // Drag and drop
    galleryUploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('dragover');
    });
    
    galleryUploadArea.addEventListener('dragleave', function() {
        this.classList.remove('dragover');
    });
    
    galleryUploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
        
        if (e.dataTransfer.files.length) {
            galleryInput.files = e.dataTransfer.files;
            previewGalleryImages(e.dataTransfer.files);
        }
    });
});

// Preview gallery images
function previewGalleryImages(files) {
    const galleryPreview = document.getElementById('galleryPreview');
    galleryPreview.innerHTML = '';
    
    Array.from(files).forEach((file, index) => {
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const previewItem = document.createElement('div');
                previewItem.className = 'gallery-preview-item';
                previewItem.innerHTML = `
                    <img src="${e.target.result}" alt="Preview ${index + 1}">
                    <button type="button" class="gallery-preview-remove" onclick="removePreviewImage(${index})">
                        <i class="ri-close-line"></i>
                    </button>
                `;
                galleryPreview.appendChild(previewItem);
            };
            
            reader.readAsDataURL(file);
        }
    });
}

// Remove preview image
function removePreviewImage(index) {
    const galleryInput = document.getElementById('gallery_images');
    const dt = new DataTransfer();
    const files = Array.from(galleryInput.files);
    
    files.splice(index, 1);
    files.forEach(file => dt.items.add(file));
    galleryInput.files = dt.files;
    
    // Refresh preview
    previewGalleryImages(galleryInput.files);
}

// Remove gallery image from server
function removeGalleryImage(index) {
    if (confirm('Are you sure you want to remove this image?')) {
        const formData = new FormData();
        formData.append('remove_gallery_image', '1');
        formData.append('image_index', index);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove from DOM
                const galleryItem = document.querySelector(`.gallery-item[data-index="${index}"]`);
                if (galleryItem) {
                    galleryItem.remove();
                }
                
                // Update remaining items
                document.querySelectorAll('.gallery-item').forEach(item => {
                    const currentIndex = parseInt(item.dataset.index);
                    if (currentIndex > index) {
                        item.dataset.index = currentIndex - 1;
                        const removeBtn = item.querySelector('.gallery-remove');
                        if (removeBtn) {
                            removeBtn.setAttribute('onclick', `removeGalleryImage(${currentIndex - 1})`);
                        }
                    }
                });
                
                showAlert('Image removed successfully', 'success');
            } else {
                showAlert(data.message || 'Failed to remove image', 'error');
            }
        })
        .catch(error => {
            showAlert('Error removing image', 'error');
            console.error('Error:', error);
        });
    }
}

// Reset form to original values
function resetForm() {
    if (confirm('Are you sure you want to reset the form? All unsaved changes will be lost.')) {
        document.getElementById('editProductForm').reset();
        
        // Reset checkboxes manually
        const checkboxes = document.querySelectorAll('input[type="checkbox"]');
        const originalSizes = <?php echo json_encode($sizes); ?>;
        
        checkboxes.forEach(checkbox => {
            if (checkbox.name === 'featured') {
                checkbox.checked = <?php echo $product['featured'] ? 'true' : 'false'; ?>;
            } else if (checkbox.name === 'sizes[]') {
                checkbox.checked = originalSizes.includes(checkbox.value);
            }
        });
        
        // Hide main image preview
        document.getElementById('imagePreview').style.display = 'none';
        
        // Clear gallery preview
        document.getElementById('galleryPreview').innerHTML = '';
        document.getElementById('gallery_images').value = '';
        
        showAlert('Form has been reset to original values', 'success');
    }
}

// Show delete confirmation modal
function showDeleteModal() {
    document.getElementById('deleteModal').style.display = 'block';
    document.getElementById('confirmDelete').focus();
}

// Check delete confirmation text
function checkDeleteConfirmation(input) {
    const deleteButton = document.getElementById('deleteButton');
    deleteButton.disabled = input.value.toUpperCase() !== 'DELETE';
}

// Close modal
document.querySelectorAll('.modal-close').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('deleteModal').style.display = 'none';
        document.getElementById('confirmDelete').value = '';
        document.getElementById('deleteButton').disabled = true;
    });
});

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('deleteModal');
    if (event.target === modal) {
        modal.style.display = 'none';
        document.getElementById('confirmDelete').value = '';
        document.getElementById('deleteButton').disabled = true;
    }
});

// Form submission with loading
document.getElementById('editProductForm').addEventListener('submit', function(e) {
    const form = this;
    const requiredFields = form.querySelectorAll('[required]');
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
        showAlert('Please enter a valid price', 'error');
    }
    
    if (!isValid) {
        e.preventDefault();
        showAlert('Please fill in all required fields correctly', 'error');
        return;
    }
    
    // Show loading overlay
    document.getElementById('loadingOverlay').classList.add('active');
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

// Add fade-in animation for form sections
document.addEventListener('DOMContentLoaded', function() {
    const formSections = document.querySelectorAll('.form-section');
    formSections.forEach((section, index) => {
        section.style.animationDelay = `${index * 0.1}s`;
        section.style.animation = 'fadeIn 0.3s ease-out forwards';
        section.style.opacity = '0';
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
    
    .delete-details {
        background: var(--light);
        padding: 1rem;
        border-radius: var(--border-radius);
        margin: 1rem 0;
    }
    
    .delete-details ul {
        list-style: none;
        margin: 0.5rem 0;
    }
    
    .delete-details li {
        padding: 0.25rem 0;
        color: var(--dark-gray);
    }
    
    .text-small {
        font-size: 0.75rem;
        color: var(--dark-gray);
    }
    
    .text-muted {
        color: var(--dark-gray);
    }
`;
document.head.appendChild(style);
</script>
</body>
</html>