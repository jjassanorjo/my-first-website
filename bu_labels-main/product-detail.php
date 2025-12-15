<?php
// Add to top of index.php, cart.php, checkout.php, etc.
session_start();
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin') {
    header('Location: pages/admin/dashboard.php');
    exit();
}
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'director') {
    header('Location: pages/campus-director/dashboard.php');
    exit();
}
?>
<?php
// product-detail.php

require_once 'includes/config.php';
require_once 'includes/functions.php';

if (!isset($_GET['slug'])) {
    redirect('products.php');
}

$slug = escape($_GET['slug']);

// Get product details
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT p.*, c.name as category_name, c.slug as category_slug 
                       FROM products p 
                       LEFT JOIN categories c ON p.category_id = c.id 
                       WHERE p.slug = ?");
$stmt->bind_param("s", $slug);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    redirect('products.php');
}

$page_title = $product['name'];
$body_class = "product-detail";
$page_scripts = ['product-detail.js'];

// Get related products
$related_stmt = $conn->prepare("SELECT * FROM products 
                               WHERE category_id = ? AND id != ? 
                               ORDER BY RAND() LIMIT 4");
$related_stmt->bind_param("ii", $product['category_id'], $product['id']);
$related_stmt->execute();
$related_products = $related_stmt->get_result();

// Decode sizes and gallery images
$sizes = json_decode($product['sizes'] ?? '[]', true);
$gallery_images = json_decode($product['image_gallery'] ?? '[]', true);
$all_images = array_merge([$product['image_main']], $gallery_images);

require_once 'includes/header.php';

// Get cart item if exists
$cart_quantity = 0;
if (isLoggedIn()) {
    $cart_stmt = $conn->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?");
    $cart_stmt->bind_param("ii", $_SESSION['user_id'], $product['id']);
    $cart_stmt->execute();
    $cart_result = $cart_stmt->get_result();
    if ($cart_row = $cart_result->fetch_assoc()) {
        $cart_quantity = $cart_row['quantity'];
    }
}
?>

<div class="small-container single-product">
    <div class="row">
        <!-- Product Images -->
        <div class="col-2">
            <div class="main-image">
                <img src="<?php echo SITE_URL; ?>assets/images/<?php echo $all_images[0]; ?>" 
                     width="100%" id="product-img" alt="<?php echo sanitize($product['name']); ?>">
            </div>
            
            <?php if (count($all_images) > 1): ?>
            <div class="small-img-row">
                <?php foreach ($all_images as $index => $image): ?>
                <div class="small-img-col">
                    <img src="<?php echo SITE_URL; ?>assets/images/<?php echo $image; ?>" 
                         width="100%" class="small-img" 
                         alt="<?php echo sanitize($product['name']); ?> - View <?php echo $index + 1; ?>">
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Product Information -->
        <div class="col-2">
            <div class="breadcrumb">
                <a href="<?php echo SITE_URL; ?>">Home</a> / 
                <a href="products.php?category=<?php echo $product['category_slug']; ?>">
                    <?php echo sanitize($product['category_name']); ?>
                </a> / 
                <span><?php echo sanitize($product['name']); ?></span>
            </div>
            
            <h1><?php echo sanitize($product['name']); ?></h1>
            
            <div class="product-meta">
                <div class="rating">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <i class="fa fa-star<?php echo ($i <= 4) ? '' : '-half-alt'; ?>"></i>
                    <?php endfor; ?>
                    <span class="rating-count">(4.5)</span>
                </div>
                <span class="product-id">Product ID: BU<?php echo str_pad($product['id'], 4, '0', STR_PAD_LEFT); ?></span>
            </div>
            
            <div class="product-price">
                <h2><?php echo formatPrice($product['price']); ?></h2>
                <?php
                $total_stock = $product['stock_main'] + $product['stock_east'] + $product['stock_west'] + $product['stock_north'] + $product['stock_south'];
                if ($total_stock > 0):
                ?>
                <span class="stock in-stock">In Stock</span>
                <?php else: ?>
                <span class="stock out-of-stock">Out of Stock</span>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($sizes) && $total_stock > 0): ?>
            <div class="size-selection">
                <label>Select Size:</label>
                <div class="size-options">
                    <?php foreach ($sizes as $size): ?>
                    <button type="button" class="size-option" data-size="<?php echo $size; ?>">
                        <?php echo $size; ?>
                    </button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" id="selected-size" name="size" value="">
            </div>
            <?php endif; ?>
            
            <div class="quantity-section">
                <label>Quantity:</label>
                <div class="quantity-controls">
                    <button type="button" class="qty-btn minus">-</button>
                    <input type="number" id="quantity" name="quantity" value="1" min="1" max="<?php echo min($total_stock, 10); ?>">
                    <button type="button" class="qty-btn plus">+</button>
                </div>
                <?php if ($total_stock > 0): ?>
                <span class="stock-info"><?php echo $total_stock; ?> available</span>
                <?php endif; ?>
            </div>
            
            <div class="product-actions">
                <button class="btn btn-primary add-to-cart" 
                        data-product-id="<?php echo $product['id']; ?>"
                        <?php echo ($total_stock == 0) ? 'disabled' : ''; ?>>
                    <i class="ri-shopping-cart-line"></i>
                    <?php echo ($cart_quantity > 0) ? "Update Cart ($cart_quantity)" : "Add to Cart"; ?>
                </button>
                
                <button class="btn btn-secondary add-to-wishlist" 
                        data-product-id="<?php echo $product['id']; ?>">
                    <i class="ri-heart-line"></i> Add to Wishlist
                </button>
                
                <?php if (isLoggedIn()): ?>
                <button class="btn btn-outline buy-now" 
                        data-product-id="<?php echo $product['id']; ?>"
                        <?php echo ($total_stock == 0) ? 'disabled' : ''; ?>>
                    <i class="ri-flashlight-line"></i> Buy Now
                </button>
                <?php endif; ?>
            </div>
            
            <div class="product-details">
                <h3><i class="ri-information-line"></i> Product Details</h3>
                <p><?php echo nl2br(sanitize($product['description'])); ?></p>
                
                <div class="details-grid">
                    <div class="detail-item">
                        <strong>Category:</strong>
                        <span><?php echo sanitize($product['category_name']); ?></span>
                    </div>
                    <?php if (!empty($sizes)): ?>
                    <div class="detail-item">
                        <strong>Available Sizes:</strong>
                        <span><?php echo implode(', ', $sizes); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="detail-item">
                        <strong>Campus Availability:</strong>
                        <div class="campus-stock">
                            <?php foreach (['main', 'east', 'west', 'north', 'south'] as $campus): 
                                $stock_field = 'stock_' . $campus;
                                if ($product[$stock_field] > 0):
                            ?>
                            <span class="campus-tag"><?php echo ucfirst($campus); ?>: <?php echo $product[$stock_field]; ?></span>
                            <?php endif; endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="product-share">
                <span>Share:</span>
                <div class="social-share">
                    <a href="#" class="social-icon facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social-icon twitter"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="social-icon instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="social-icon whatsapp"><i class="fab fa-whatsapp"></i></a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Related Products -->
<?php if ($related_products->num_rows > 0): ?>
<div class="small container related-products">
    <h2 class="title">Related Products</h2>
    <div class="row">
        <?php while ($related = $related_products->fetch_assoc()): ?>
        <div class="col-4">
            <a href="product-detail.php?slug=<?php echo $related['slug']; ?>">
                <img src="<?php echo SITE_URL; ?>assets/images/<?php echo $related['image_main']; ?>" 
                     alt="<?php echo sanitize($related['name']); ?>">
            </a>
            <a href="product-detail.php?slug=<?php echo $related['slug']; ?>">
                <h4><?php echo sanitize($related['name']); ?></h4>
            </a>
            <p><?php echo formatPrice($related['price']); ?></p>
            <a href="product-detail.php?slug=<?php echo $related['slug']; ?>" class="btn btn-small">View Product</a>
        </div>
        <?php endwhile; ?>
    </div>
</div>
<?php endif; ?>

<script>
// Product image switcher
const productImg = document.getElementById('product-img');
const smallImgs = document.querySelectorAll('.small-img');

smallImgs.forEach(img => {
    img.addEventListener('click', function() {
        productImg.src = this.src;
        
        // Update active state
        smallImgs.forEach(img => img.classList.remove('active'));
        this.classList.add('active');
    });
});

// Size selection
const sizeOptions = document.querySelectorAll('.size-option');
const selectedSizeInput = document.getElementById('selected-size');

sizeOptions.forEach(option => {
    option.addEventListener('click', function() {
        sizeOptions.forEach(opt => opt.classList.remove('active'));
        this.classList.add('active');
        selectedSizeInput.value = this.dataset.size;
    });
});

// Quantity controls
const quantityInput = document.getElementById('quantity');
const minusBtn = document.querySelector('.qty-btn.minus');
const plusBtn = document.querySelector('.qty-btn.plus');

minusBtn.addEventListener('click', () => {
    if (quantityInput.value > 1) {
        quantityInput.value = parseInt(quantityInput.value) - 1;
    }
});

plusBtn.addEventListener('click', () => {
    const max = parseInt(quantityInput.max);
    if (quantityInput.value < max) {
        quantityInput.value = parseInt(quantityInput.value) + 1;
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>