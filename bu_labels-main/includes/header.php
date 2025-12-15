<?php
// includes/header.php

require_once 'functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo SITE_NAME; ?></title>

    <!-- Styles -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/style.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@48,400,0,0" />
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.6.0/fonts/remixicon.css" rel="stylesheet" />

    <!-- Scripts -->
    <script src="<?php echo SITE_URL; ?>assets/js/main.js" defer></script>
    <script src="<?php echo SITE_URL; ?>assets/js/cart.js" defer></script>
    
    <style>
    body { padding-top: 70px !important; }
    nav { height: 70px !important; position: fixed !important; top: 0 !important; }
    </style>
</head>
<body class="<?php echo isset($body_class) ? $body_class : ''; ?>">

    <!-- ======================= NAVIGATION ======================= -->
    <nav>
        <div class="nav-container">
            <!-- Column 1: Logo -->
            <div class="nav-col nav-logo">
                <a href="<?php echo SITE_URL; ?>">
                    <img src="<?php echo SITE_URL; ?>assets/images/bunique.PNG" alt="logo" />
                </a>
            </div>

            <!-- Column 2: Navigation Links -->
            <div class="nav-col nav-links-container">
                <ul class="nav__links" id="nav-links">
                    <li><a href="<?php echo SITE_URL; ?>">Home</a></li>
                    <li><a href="<?php echo SITE_URL; ?>products.php">Products</a></li>
                    <li><a href="<?php echo SITE_URL; ?>about.php">About</a></li>
                    <li><a href="<?php echo SITE_URL; ?>contact.php">Contact</a></li>
                </ul>
            </div>

            <!-- Column 3: User Actions -->
            <div class="nav-col nav-actions-container">
                <div class="nav-actions">
                    <?php if (isLoggedIn()): ?>
                        <a href="<?php echo SITE_URL; ?>orders.php" class="nav-icon" title="My Orders">
                            <i class="ri-shopping-bag-line"></i>
                        </a>
                        <a href="<?php echo SITE_URL; ?>account.php?page=wishlist" class="nav-icon" title="Wishlist">
                            <i class="ri-heart-line"></i>
                        </a>
                        <a href="<?php echo SITE_URL; ?>account.php" class="nav-icon" title="My Account">
                            <i class="ri-user-line"></i>
                        </a>
                    <?php else: ?>
                        <a href="<?php echo SITE_URL; ?>account.php" class="nav-icon" title="Login/Register">
                            <i class="ri-user-line"></i>
                        </a>
                    <?php endif; ?>
                    
                    <a href="<?php echo SITE_URL; ?>cart.php" class="cart-icon">
                        <i class="ri-shopping-cart-line"></i>
                        <span class="cart-count"><?php echo getCartCount(); ?></span>
                    </a>
                </div>
            </div>

            <!-- Mobile Menu Button -->
            <div class="nav__menu__btn" id="menu-btn">
                <i class="ri-menu-3-line"></i>
            </div>
        </div>
    </nav>