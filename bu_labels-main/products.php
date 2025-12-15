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
// products.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/config.php';
require_once 'includes/functions.php';

$page_title = "Products";
$body_class = "products";
$page_scripts = ['products.js'];

// Get filters
$category_filter = isset($_GET['category']) ? escape($_GET['category']) : '';
$search_query = isset($_GET['search']) ? escape($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Initialize connection
$conn = getDBConnection();

// Build WHERE clause
$where_conditions = [];
$params = [];
$types = "";

if ($category_filter) {
    $where_conditions[] = "c.slug = ?";
    $params[] = $category_filter;
    $types .= "s";
}

if ($search_query) {
    $where_conditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
    $types .= "ss";
}

$where_clause = "";
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// Sorting
$order_by = "ORDER BY p.created_at DESC";
switch ($sort_by) {
    case 'price_low':
        $order_by = "ORDER BY p.price ASC";
        break;
    case 'price_high':
        $order_by = "ORDER BY p.price DESC";
        break;
    case 'name':
        $order_by = "ORDER BY p.name ASC";
        break;
    default:
        $order_by = "ORDER BY p.created_at DESC";
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Get total count
$count_sql = "SELECT COUNT(*) as total 
              FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              $where_clause";
              
$count_stmt = $conn->prepare($count_sql);
if ($params) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_row = $count_result->fetch_assoc();
$total_rows = $total_row['total'] ?? 0;
$total_pages = ceil($total_rows / $limit);

// Main query
$sql = "SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        $where_clause 
        $order_by 
        LIMIT ? OFFSET ?";

// Add pagination parameters
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

// Execute query
$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result();

// Get categories for filter
$categories = $conn->query("SELECT * FROM categories ORDER BY name");

require_once 'includes/header.php';
?>

<!-- ======================= PRODUCTS PAGE ======================= -->
<div class="products-page">
    <div class="small container">
        <!-- Header with filter controls -->
        <div class="row-2">
            <h2>All Products</h2>
            
            <div class="filter-controls">
                <!-- Category Filter -->
                <form method="GET" class="filter-form">
                    <select name="category" class="form-control">
                        <option value="">All Categories</option>
                        <?php while($cat = $categories->fetch_assoc()): ?>
                        <option value="<?php echo $cat['slug']; ?>" 
                            <?php echo ($category_filter == $cat['slug']) ? 'selected' : ''; ?>>
                            <?php echo sanitize($cat['name']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                    
                    <!-- Sort Filter -->
                    <select name="sort" class="form-control">
                        <option value="newest" <?php echo ($sort_by == 'newest') ? 'selected' : ''; ?>>Newest First</option>
                        <option value="price_low" <?php echo ($sort_by == 'price_low') ? 'selected' : ''; ?>>Sort by Price (Low to High)</option>
                        <option value="price_high" <?php echo ($sort_by == 'price_high') ? 'selected' : ''; ?>>Sort by Price (High to Low)</option>
                        <option value="name" <?php echo ($sort_by == 'name') ? 'selected' : ''; ?>>Sort by Name</option>
                    </select>
                    
                    <!-- Hidden fields to preserve search -->
                    <?php if ($search_query): ?>
                    <input type="hidden" name="search" value="<?php echo sanitize($search_query); ?>">
                    <?php endif; ?>
                    
                    <button type="submit" class="btn-filter">Apply Filters</button>
                </form>
                
                <!-- Search Form -->
                <form method="GET" class="search-form">
                    <div class="search-box">
                        <input type="text" name="search" placeholder="Search products..." 
                               value="<?php echo sanitize($search_query); ?>">
                        <button type="submit" class="btn-search">
                            <i class="ri-search-line"></i>
                        </button>
                    </div>
                    <?php if ($category_filter): ?>
                    <input type="hidden" name="category" value="<?php echo $category_filter; ?>">
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <?php if ($products->num_rows > 0): ?>
            <!-- Results Count -->
            <div class="results-info">
                <p>Showing <?php echo $products->num_rows; ?> of <?php echo $total_rows; ?> products</p>
                <?php if ($category_filter || $search_query): ?>
                <a href="products.php" class="clear-filters">Clear Filters</a>
                <?php endif; ?>
            </div>
            
            <!-- Products Grid -->
            <div class="row">
                <?php while ($product = $products->fetch_assoc()): ?>
                <div class="col-4">
                    <!-- Product Image -->
                    <a href="product-detail.php?slug=<?php echo $product['slug']; ?>">
                        <img src="<?php echo SITE_URL; ?>assets/images/<?php echo $product['image_main']; ?>" 
                             alt="<?php echo sanitize($product['name']); ?>">
                    </a>
                    
                    <!-- Product Title/Name -->
                    <h4><?php echo sanitize($product['name']); ?></h4>
                    
                    <!-- Rating Stars -->
                    <div class="rating">
                        <?php
                        $rating = isset($product['rating']) ? (float)$product['rating'] : 4.0;
                        $full_stars = floor($rating);
                        $has_half = ($rating - $full_stars) >= 0.5;
                        $empty_stars = 5 - $full_stars - ($has_half ? 1 : 0);
                        
                        // Full stars
                        for ($i = 0; $i < $full_stars; $i++):
                        ?>
                        <i class="fa fa-star" aria-hidden="true"></i>
                        <?php endfor; ?>
                        
                        <!-- Half star -->
                        <?php if ($has_half): ?>
                        <i class="fa fa-star-half-o" aria-hidden="true"></i>
                        <?php endif; ?>
                        
                        <!-- Empty stars -->
                        <?php for ($i = 0; $i < $empty_stars; $i++): ?>
                        <i class="fa fa-star-o" aria-hidden="true"></i>
                        <?php endfor; ?>
                    </div>
                    
                    
                    <!-- Action Buttons -->
                    <div class="product-actions">
                        <a href="product-detail.php?slug=<?php echo $product['slug']; ?>" 
                           class="btn-small">
                            View Details
                        </a>
                        
                        <button class="action-btn add-to-wishlist" 
                                data-product-id="<?php echo $product['id']; ?>"
                                title="Add to Wishlist">
                            <i class="ri-heart-line"></i>
                        </button>
                        <button class="action-btn add-to-cart" 
                                data-product-id="<?php echo $product['id']; ?>"
                                title="Add to Cart">
                            <i class="ri-shopping-cart-line"></i>
                        </button>
                    </div>

                     <!-- Price -->
                    <p><?php echo formatPrice($product['price']); ?></p>
                </div>
                <?php endwhile; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="page-btn">
                <?php if ($page > 1): ?>
                <a href="?<?php 
                    $query = $_GET;
                    $query['page'] = $page - 1;
                    echo http_build_query($query);
                ?>" style="text-decoration: none;">
                    <span style="margin-right: 10px;">&laquo;</span>
                </a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): 
                    if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                    <a href="?<?php 
                        $query = $_GET;
                        $query['page'] = $i;
                        echo http_build_query($query);
                    ?>" style="text-decoration: none;">
                        <span class="<?php echo ($i == $page) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </span>
                    </a>
                    <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                    <span>...</span>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                <a href="?<?php 
                    $query = $_GET;
                    $query['page'] = $page + 1;
                    echo http_build_query($query);
                ?>" style="text-decoration: none;">
                    <span style="margin-left: 10px;">&raquo;</span>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
        <?php else: ?>
            <!-- No Products Found -->
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="ri-search-line"></i>
                </div>
                <h3>No products found</h3>
                <p>Try adjusting your search or filter criteria.</p>
                <a href="products.php" class="btn btn-primary">View All Products</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>