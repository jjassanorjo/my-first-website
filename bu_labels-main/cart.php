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
// cart.php



require_once 'includes/config.php';
require_once 'includes/functions.php';

$page_title = "Shopping Cart";
$body_class = "cart-page";
$page_scripts = ['cart.js'];

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isLoggedIn()) {
        $_SESSION['redirect_url'] = 'cart.php';
        redirect('account.php?login=1');
    }
    
    $action = $_POST['action'] ?? '';
    $product_id = (int)($_POST['product_id'] ?? 0);
    $size = $_POST['size'] ?? '';
    $quantity = (int)($_POST['quantity'] ?? 1);
    
    $conn = getDBConnection();
    $user_id = $_SESSION['user_id'];
    
    switch ($action) {
        case 'add':
            // Check if item already exists
            $check_stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ? AND size = ?");
            $check_stmt->bind_param("iis", $user_id, $product_id, $size);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $row = $check_result->fetch_assoc();
                $new_quantity = $row['quantity'] + $quantity;
                $update_stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
                $update_stmt->bind_param("ii", $new_quantity, $row['id']);
                $update_stmt->execute();
            } else {
                $insert_stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, size, quantity) VALUES (?, ?, ?, ?)");
                $insert_stmt->bind_param("iisi", $user_id, $product_id, $size, $quantity);
                $insert_stmt->execute();
            }
            break;
            
        case 'update':
            $cart_id = (int)$_POST['cart_id'];
            $update_stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
            $update_stmt->bind_param("iii", $quantity, $cart_id, $user_id);
            $update_stmt->execute();
            break;
            
        case 'remove':
            $cart_id = (int)$_POST['cart_id'];
            $remove_stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
            $remove_stmt->bind_param("ii", $cart_id, $user_id);
            $remove_stmt->execute();
            break;
            
        case 'clear':
            $clear_stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
            $clear_stmt->bind_param("i", $user_id);
            $clear_stmt->execute();
            break;
    }
    
    // Redirect to prevent form resubmission
    redirect('cart.php');
}



require_once 'includes/header.php';

if (!isLoggedIn()) {
    // Show guest cart using session
    ?>
    <div class="small container cart-page">
        <div class="cart-header">
            <h1>Your Shopping Cart</h1>
            <p>Please <a href="account.php?login=1">login</a> to save your cart.</p>
        </div>
        
        <div class="empty-cart">
            <img src="<?php echo SITE_URL; ?>assets/images/empty-cart.png" alt="Empty Cart">
            <h3>Your cart is empty</h3>
            <p>Add some products to your cart to get started!</p>
            <a href="products.php" class="btn btn-primary">Continue Shopping</a>
        </div>
    </div>
    <?php
} else {
    // Get cart items for logged in user
    $conn = getDBConnection();
    $user_id = $_SESSION['user_id'];
    
    $cart_query = "SELECT c.*, p.name, p.price, p.image_main, p.stock_main 
                   FROM cart c 
                   JOIN products p ON c.product_id = p.id 
                   WHERE c.user_id = ? 
                   ORDER BY c.created_at DESC";
    $stmt = $conn->prepare($cart_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $cart_items = $stmt->get_result();
    
    $subtotal = 0;
    $shipping = 0;
    ?>

    
    
    <div class="small container cart-page">
        <div class="cart-header">
            <h1>Your Shopping Cart</h1>
            <?php if ($cart_items->num_rows > 0): ?>
            <a href="cart.php?action=clear" class="clear-cart" onclick="return confirm('Clear all items from cart?')">
                <i class="ri-delete-bin-line"></i> Clear Cart
            </a>
            <?php endif; ?>
        </div>
        
        <?php if ($cart_items->num_rows > 0): ?>
        <div class="cart-content">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Total</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($item = $cart_items->fetch_assoc()): 
                        $item_total = $item['price'] * $item['quantity'];
                        $subtotal += $item_total;
                    ?>
                    <tr class="cart-item" data-cart-id="<?php echo $item['id']; ?>">
                        <td>
                            <div class="cart-info">
                                <img src="<?php echo SITE_URL; ?>assets/images/<?php echo $item['image_main']; ?>" 
                                     alt="<?php echo sanitize($item['name']); ?>">
                                <div>
                                    <a href="product-detail.php?id=<?php echo $item['product_id']; ?>" class="product-name">
                                        <?php echo sanitize($item['name']); ?>
                                    </a>
                                    <?php if (!empty($item['size'])): ?>
                                    <div class="product-size">Size: <?php echo sanitize($item['size']); ?></div>
                                    <?php endif; ?>
                                    <div class="product-stock">
                                        <?php if ($item['stock_main'] > 0): ?>
                                        <span class="in-stock">In Stock</span>
                                        <?php else: ?>
                                        <span class="out-of-stock">Out of Stock</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="price"><?php echo formatPrice($item['price']); ?></td>
                        <td>
                            <div class="quantity-controls">
                                <button type="button" class="qty-btn minus" data-action="decrease">-</button>
                                <input type="number" class="quantity-input" value="<?php echo $item['quantity']; ?>" 
                                       min="1" max="10" data-cart-id="<?php echo $item['id']; ?>">
                                <button type="button" class="qty-btn plus" data-action="increase">+</button>
                            </div>
                        </td>
                        <td class="total"><?php echo formatPrice($item_total); ?></td>
                        <td>
                            <button class="remove-item" data-cart-id="<?php echo $item['id']; ?>">
                                <i class="ri-delete-bin-line"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            
            <div class="cart-summary">
                <div class="summary-card">
                    <h3>Order Summary</h3>
                    
                    <div class="summary-row">
                        <span>Subtotal</span>
                        <span class="amount"><?php echo formatPrice($subtotal); ?></span>
                    </div>
                    
                    <div class="summary-row">
                        <span>Shipping Fee</span>
                        <span class="amount"><?php echo formatPrice($shipping); ?></span>
                    </div>
                    
                    <div class="summary-row total">
                        <span>Total</span>
                        <span class="amount"><?php echo formatPrice($subtotal + $shipping); ?></span>
                    </div>
                    
                    <div class="summary-actions">
                        <a href="products.php" class="btn btn-outline">
                            <i class="ri-arrow-left-line"></i> Continue Shopping
                        </a>
                        <a href="checkout.php" class="btn btn-primary">
                            Proceed to Checkout <i class="ri-arrow-right-line"></i>
                        </a>
                    </div>
                    
                    <div class="payment-methods">
                        <p>We accept:</p>
                        <div class="payment-icons">
                            <img src="<?php echo SITE_URL; ?>assets/images/gcash.png" alt="GCash">
                            <img src="<?php echo SITE_URL; ?>assets/images/cod.png" alt="Cash on Delivery">
                            <img src="<?php echo SITE_URL; ?>assets/images/bank.png" alt="Bank Transfer">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <div class="empty-cart">
            <img src="<?php echo SITE_URL; ?>assets/images/empty-cart.png" alt="Empty Cart">
            <h3>Your cart is empty</h3>
            <p>Add some products to your cart to get started!</p>
            <a href="products.php" class="btn btn-primary">Continue Shopping</a>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
    // Cart quantity controls
    document.querySelectorAll('.quantity-controls').forEach(control => {
        const minusBtn = control.querySelector('.minus');
        const plusBtn = control.querySelector('.plus');
        const input = control.querySelector('.quantity-input');
        const cartId = input.dataset.cartId;
        
        minusBtn.addEventListener('click', () => {
            if (input.value > 1) {
                input.value = parseInt(input.value) - 1;
                updateCartItem(cartId, input.value);
            }
        });
        
        plusBtn.addEventListener('click', () => {
            if (input.value < 10) {
                input.value = parseInt(input.value) + 1;
                updateCartItem(cartId, input.value);
            }
        });
        
        input.addEventListener('change', () => {
            updateCartItem(cartId, input.value);
        });
    });
    
    // Remove item
    document.querySelectorAll('.remove-item').forEach(btn => {
        btn.addEventListener('click', function() {
            const cartId = this.dataset.cartId;
            removeCartItem(cartId);
        });
    });
    
    function updateCartItem(cartId, quantity) {
        fetch('api/cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=update&cart_id=${cartId}&quantity=${quantity}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
    }
    
    function removeCartItem(cartId) {
        if (confirm('Remove this item from cart?')) {
            fetch('api/cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=remove&cart_id=${cartId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }
    }
    </script>
    <?php
}

require_once 'includes/footer.php';
?>