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
// index.php

$page_title = "Home";
$body_class = "home";
require_once 'includes/header.php';

// Get featured products
$conn = getDBConnection();
$featured_products = $conn->query("SELECT * FROM products WHERE featured = 1 ORDER BY created_at DESC LIMIT 4");
$latest_products = $conn->query("SELECT * FROM products ORDER BY created_at DESC LIMIT 12");
?>

<!-- ======================= HERO / LANDING ======================= -->
<header class="main">
    <div class="header__container">
        <h1>
            <span><img src="<?php echo SITE_URL; ?>assets/images/bunique.PNG" alt="logo" /></span>
        </h1>

        <h2>VARSITY JACKETS</h2>

        <p>
            <?php echo SITE_TAGLINE; ?>. Shop official university merchandise including varsity jackets, hoodies, accessories and more.
        </p>

        <div class="header__btn">
            <a href="<?php echo SITE_URL; ?>products.php" class="btn">
                SHOP NOW
                <i class="ri-arrow-right-long-line"></i>
            </a>
        </div>
    </div>
</header>

<!-- ======================= CATEGORIES SLIDER ======================= -->
<div class="categories">
    <div class="small container">
        <div class="slider-wrapper">
            <button id="prev-slide" class="slide-button material-symbols-rounded">chevron_left</button>

            <div class="image-list">
                <?php
                $categories = getCategories();
                foreach ($categories as $category):
                ?>
                <a href="products.php?category=<?php echo $category['slug']; ?>" class="image-item">
                    <img src="<?php echo SITE_URL; ?>assets/images/<?php echo $category['image']; ?>" alt="<?php echo sanitize($category['name']); ?>" />
                    <h4><?php echo sanitize($category['name']); ?></h4>
                </a>
                <?php endforeach; ?>
                <!-- Additional category images -->
                <img src="<?php echo SITE_URL; ?>assets/images/category-5.png" class="image-item" alt="category 5" />
                <img src="<?php echo SITE_URL; ?>assets/images/category-6.png" class="image-item" alt="category 6" />
                <img src="<?php echo SITE_URL; ?>assets/images/category-7.png" class="image-item" alt="category 7" />
                <img src="<?php echo SITE_URL; ?>assets/images/category-8.png" class="image-item" alt="category 8" />
                <img src="<?php echo SITE_URL; ?>assets/images/category-9.png" class="image-item" alt="category 9" />
                <img src="<?php echo SITE_URL; ?>assets/images/category-10.png" class="image-item" alt="category 10" />
            </div>

            <button id="next-slide" class="slide-button material-symbols-rounded">chevron_right</button>
        </div>

        <div class="slider-scroller">
            <div class="scrollbar-track">
                <div class="scrollbar-thumb"></div>
            </div>
        </div>
    </div>
</div>

<!-- ======================= FEATURED PRODUCTS ======================= -->
<div class="small container">
    <h2 class="title">Featured Products</h2>

    <div class="row">
        <?php while ($product = $featured_products->fetch_assoc()): ?>
        <div class="col-4">
            <a href="product-detail.php?slug=<?php echo $product['slug']; ?>">
                <img src="<?php echo SITE_URL; ?>assets/images/<?php echo $product['image_main']; ?>" alt="<?php echo sanitize($product['name']); ?>" />
            </a>
            <a href="product-detail.php?slug=<?php echo $product['slug']; ?>">
                <h4><?php echo sanitize($product['name']); ?></h4>
            </a>
            <p><?php echo formatPrice($product['price']); ?></p>
            <a href="product-detail.php?slug=<?php echo $product['slug']; ?>" class="btn btn-small">View Product</a>
        </div>
        <?php endwhile; ?>
    </div>

    <!-- ======================= LATEST PRODUCTS ======================= -->
    <h2 class="title">Latest Products</h2>

    <div class="row">
        <?php 
        $counter = 0;
        while ($product = $latest_products->fetch_assoc()): 
            $counter++;
            if ($counter > 4) break;
        ?>
        <div class="col-4">
            <a href="product-detail.php?slug=<?php echo $product['slug']; ?>">
                <img src="<?php echo SITE_URL; ?>assets/images/<?php echo $product['image_main']; ?>" alt="<?php echo sanitize($product['name']); ?>" />
            </a>
            <a href="product-detail.php?slug=<?php echo $product['slug']; ?>">
                <h4><?php echo sanitize($product['name']); ?></h4>
            </a>
            <p><?php echo formatPrice($product['price']); ?></p>
            <a href="product-detail.php?slug=<?php echo $product['slug']; ?>" class="btn btn-small">View Product</a>
        </div>
        <?php endwhile; ?>
    </div>

    <div class="row">
        <?php 
        $counter = 0;
        $latest_products->data_seek(0);
        while ($product = $latest_products->fetch_assoc()): 
            $counter++;
            if ($counter > 4 && $counter <= 8): 
        ?>
        <div class="col-4">
            <a href="product-detail.php?slug=<?php echo $product['slug']; ?>">
                <img src="<?php echo SITE_URL; ?>assets/images/<?php echo $product['image_main']; ?>" alt="<?php echo sanitize($product['name']); ?>" />
            </a>
            <a href="product-detail.php?slug=<?php echo $product['slug']; ?>">
                <h4><?php echo sanitize($product['name']); ?></h4>
            </a>
            <p><?php echo formatPrice($product['price']); ?></p>
            <a href="product-detail.php?slug=<?php echo $product['slug']; ?>" class="btn btn-small">View Product</a>
        </div>
        <?php 
            endif;
        endwhile; 
        ?>
    </div>

    <div class="row">
        <?php 
        $counter = 0;
        $latest_products->data_seek(0);
        while ($product = $latest_products->fetch_assoc()): 
            $counter++;
            if ($counter > 8 && $counter <= 12): 
        ?>
        <div class="col-4">
            <a href="product-detail.php?slug=<?php echo $product['slug']; ?>">
                <img src="<?php echo SITE_URL; ?>assets/images/<?php echo $product['image_main']; ?>" alt="<?php echo sanitize($product['name']); ?>" />
            </a>
            <a href="product-detail.php?slug=<?php echo $product['slug']; ?>">
                <h4><?php echo sanitize($product['name']); ?></h4>
            </a>
            <p><?php echo formatPrice($product['price']); ?></p>
            <a href="product-detail.php?slug=<?php echo $product['slug']; ?>" class="btn btn-small">View Product</a>
        </div>
        <?php 
            endif;
        endwhile; 
        ?>
    </div>
</div>

<!-- ======================= SPECIAL OFFER ======================= -->
<section class="offer-section">
    <div class="offer__image">
        <img src="<?php echo SITE_URL; ?>assets/images/wb.png" alt="Special Offer" />
    </div>

    <div class="offer__content">
        <h2>SPECIAL OFFER</h2>
        <h1>BUNIQUE Windbreakers</h1>
        <p>
            Limited edition windbreakers now available. Get yours before they're gone!
            Limited stock available at 20% off for first-time buyers.
        </p>
        <div class="offer__btn">
            <a href="products.php?category=limited-edition" class="btn">BUY NOW</a>
        </div>
    </div>
</section>

<!-- ======================= TESTIMONIALS ======================= -->
<div class="testimonial">
    <div class="small container">
        <div class="row">
            <div class="col-3">
                <i class="fa fa-quote-left"></i>
                <p>Excellent quality varsity jacket! Fits perfectly and the material is premium.</p>
                <div class="rating">
                    <i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i>
                    <i class="fa fa-star"></i><i class="fa fa-star-half-alt"></i>
                </div>
                <img src="<?php echo SITE_URL; ?>assets/images/axel.jpg" alt="user 1" />
                <h3>Axel Orendain</h3>
            </div>

            <div class="col-3">
                <i class="fa fa-quote-left"></i>
                <p>Fast delivery and great customer service. Love my new BU hoodie!</p>
                <div class="rating">
                    <i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i>
                    <i class="fa fa-star"></i><i class="fa fa-star"></i>
                </div>
                <img src="<?php echo SITE_URL; ?>assets/images/ellyza.jpg" alt="user 2" />
                <h3>Ellyza Nicole Lomibao</h3>
            </div>

            <div class="col-3">
                <i class="fa fa-quote-left"></i>
                <p>The campus pickup system is very convenient. Great products at reasonable prices.</p>
                <div class="rating">
                    <i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i>
                    <i class="fa fa-star"></i><i class="fa fa-star-half-alt"></i>
                </div>
                <img src="<?php echo SITE_URL; ?>assets/images/julius.jpg" alt="user 3" />
                <h3>Julius Jeamel Sanorjo</h3>
            </div>
        </div>
    </div>
</div>

<!-- ======================= BRANDS ======================= -->
<div class="brands">
    <div class="small container">
        <div class="row">
            <div class="col-5"><img src="<?php echo SITE_URL; ?>assets/images/logo-godrej.png" alt="brand" /></div>
            <div class="col-5"><img src="<?php echo SITE_URL; ?>assets/images/logo-oppo.png" alt="brand" /></div>
            <div class="col-5"><img src="<?php echo SITE_URL; ?>assets/images/logo-coca-cola.png" alt="brand" /></div>
            <div class="col-5"><img src="<?php echo SITE_URL; ?>assets/images/logo-paypal.png" alt="brand" /></div>
            <div class="col-5"><img src="<?php echo SITE_URL; ?>assets/images/logo-philips.png" alt="brand" /></div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>