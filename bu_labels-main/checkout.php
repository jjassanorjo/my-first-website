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
// checkout.php

require_once 'includes/config.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    $_SESSION['redirect_url'] = 'checkout.php';
    redirect('account.php?login=1');
}

$page_title = "Checkout";
$body_class = "checkout-page";
$page_scripts = ['checkout.js'];

// Get user cart items
$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

$cart_query = "SELECT c.*, p.name, p.price, p.image_main 
               FROM cart c 
               JOIN products p ON c.product_id = p.id 
               WHERE c.user_id = ?";
$stmt = $conn->prepare($cart_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_items = $stmt->get_result();

if ($cart_items->num_rows == 0) {
    redirect('cart.php');
}

// Calculate totals
$subtotal = 0;
$shipping = 0;
$cart_data = [];

while ($item = $cart_items->fetch_assoc()) {
    $item_total = $item['price'] * $item['quantity'];
    $subtotal += $item_total;
    $cart_data[] = $item;
}

$total = $subtotal + $shipping;

// Get user info
$user_stmt = $conn->prepare("SELECT name, email, campus FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();

// Handle checkout submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = escape($_POST['payment_method']);
    $pickup_campus = escape($_POST['pickup_campus']);
    $notes = escape($_POST['notes']);
    
    // Generate order number
    $order_number = generateOrderNumber();
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Create order
        $order_stmt = $conn->prepare("INSERT INTO orders (order_number, user_id, total_amount, payment_method, pickup_campus, notes) 
                                     VALUES (?, ?, ?, ?, ?, ?)");
        $order_stmt->bind_param("sidsds", $order_number, $user_id, $total, $payment_method, $pickup_campus, $notes);
        $order_stmt->execute();
        $order_id = $conn->insert_id;
        
        // Add order items
        $item_stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, size, quantity, price) 
                                    VALUES (?, ?, ?, ?, ?)");
        
        foreach ($cart_data as $item) {
            $item_stmt->bind_param("iisid", $order_id, $item['product_id'], $item['size'], $item['quantity'], $item['price']);
            $item_stmt->execute();
        }
        
        // Clear cart
        $clear_stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
        $clear_stmt->bind_param("i", $user_id);
        $clear_stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        // Send confirmation email (simulated)
        $_SESSION['order_number'] = $order_number;
        $_SESSION['order_total'] = $total;
        
        redirect('order-confirmation.php?order_id=' . $order_id);
        
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error creating order: " . $e->getMessage();
    }
}

require_once 'includes/header.php';
?>

<div class="small container checkout-page">
    <div class="checkout-header">
        <h1>Checkout</h1>
        <div class="checkout-steps">
            <div class="step active">
                <span class="step-number">1</span>
                <span class="step-label">Cart</span>
            </div>
            <div class="step active">
                <span class="step-number">2</span>
                <span class="step-label">Details</span>
            </div>
            <div class="step">
                <span class="step-number">3</span>
                <span class="step-label">Payment</span>
            </div>
            <div class="step">
                <span class="step-number">4</span>
                <span class="step-label">Confirmation</span>
            </div>
        </div>
    </div>
    
    <?php if (isset($error)): ?>
    <div class="alert alert-error">
        <i class="ri-error-warning-line"></i>
        <?php echo $error; ?>
    </div>
    <?php endif; ?>
    
    <div class="checkout-content">
        <div class="row">
            <!-- Order Summary -->
            <div class="col-2">
                <div class="order-summary-card">
                    <h3>Order Summary</h3>
                    
                    <div class="order-items">
                        <?php foreach ($cart_data as $item): ?>
                        <div class="order-item">
                            <img src="<?php echo SITE_URL; ?>assets/images/<?php echo $item['image_main']; ?>" 
                                 alt="<?php echo sanitize($item['name']); ?>">
                            <div class="item-details">
                                <h4><?php echo sanitize($item['name']); ?></h4>
                                <div class="item-meta">
                                    <?php if (!empty($item['size'])): ?>
                                    <span class="size">Size: <?php echo $item['size']; ?></span>
                                    <?php endif; ?>
                                    <span class="quantity">Qty: <?php echo $item['quantity']; ?></span>
                                </div>
                            </div>
                            <div class="item-price">
                                <?php echo formatPrice($item['price'] * $item['quantity']); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="order-totals">
                        <div class="total-row">
                            <span>Subtotal</span>
                            <span><?php echo formatPrice($subtotal); ?></span>
                        </div>
                        <div class="total-row">
                            <span>Shipping</span>
                            <span><?php echo formatPrice($shipping); ?></span>
                        </div>
                        <div class="total-row grand-total">
                            <span>Total</span>
                            <span><?php echo formatPrice($total); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="need-help">
                    <h4><i class="ri-question-line"></i> Need Help?</h4>
                    <p>Contact us at <strong>support@bulabels.com</strong> or call <strong>(123) 456-7890</strong></p>
                </div>
            </div>
            
            <!-- Checkout Form -->
            <div class="col-2">
                <form method="POST" class="checkout-form">
                    <div class="form-section">
                        <h3><i class="ri-user-line"></i> Customer Information</h3>
                        
                        <div class="form-group">
                            <label for="name">Full Name *</label>
                            <input type="text" id="name" name="name" value="<?php echo sanitize($user['name']); ?>" required readonly>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" value="<?php echo sanitize($user['email']); ?>" required readonly>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3><i class="ri-map-pin-line"></i> Pickup Information</h3>
                        
                        <div class="form-group">
                            <label for="pickup_campus">Pickup Campus *</label>
                            <select id="pickup_campus" name="pickup_campus" required>
                                <option value="">Select Campus</option>
                                <option value="main" <?php echo ($user['campus'] == 'main') ? 'selected' : ''; ?>>Main Campus</option>
                                <option value="east" <?php echo ($user['campus'] == 'east') ? 'selected' : ''; ?>>East Campus</option>
                                <option value="west" <?php echo ($user['campus'] == 'west') ? 'selected' : ''; ?>>West Campus</option>
                                <option value="north" <?php echo ($user['campus'] == 'north') ? 'selected' : ''; ?>>North Campus</option>
                                <option value="south" <?php echo ($user['campus'] == 'south') ? 'selected' : ''; ?>>South Campus</option>
                            </select>
                            <small>Orders will be available for pickup at the CSC Office of selected campus</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="estimated_pickup">Estimated Pickup Date</label>
                            <input type="text" id="estimated_pickup" value="3-5 business days" readonly>
                            <small>You'll receive an email when your order is ready for pickup</small>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3><i class="ri-bank-card-line"></i> Payment Method</h3>
                        
                        <div class="payment-options">
                            <div class="payment-option">
                                <input type="radio" id="gcash" name="payment_method" value="gcash" required checked>
                                <label for="gcash">
                                    <img src="<?php echo SITE_URL; ?>assets/images/gcash.png" alt="GCash">
                                    <span>GCash</span>
                                </label>
                                <div class="payment-details">
                                    <p>Pay via GCash. You'll receive payment instructions after order confirmation.</p>
                                </div>
                            </div>
                            
                            <div class="payment-option">
                                <input type="radio" id="cod" name="payment_method" value="cod">
                                <label for="cod">
                                    <img src="<?php echo SITE_URL; ?>assets/images/cod.png" alt="Cash on Delivery">
                                    <span>Cash on Delivery (Campus Pickup)</span>
                                </label>
                                <div class="payment-details">
                                    <p>Pay when you pick up your order at the campus CSC Office.</p>
                                </div>
                            </div>
                            
                            <div class="payment-option">
                                <input type="radio" id="bank" name="payment_method" value="bank_transfer">
                                <label for="bank">
                                    <img src="<?php echo SITE_URL; ?>assets/images/bank.png" alt="Bank Transfer">
                                    <span>Bank Transfer</span>
                                </label>
                                <div class="payment-details">
                                    <p>Pay via bank transfer. Account details will be provided after order confirmation.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3><i class="ri-file-text-line"></i> Additional Information</h3>
                        
                        <div class="form-group">
                            <label for="notes">Order Notes (Optional)</label>
                            <textarea id="notes" name="notes" rows="3" 
                                      placeholder="Special instructions, delivery preferences, etc."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <div class="checkbox">
                                <input type="checkbox" id="terms" name="terms" required>
                                <label for="terms">
                                    I agree to the <a href="#" target="_blank">Terms and Conditions</a> and 
                                    <a href="#" target="_blank">Privacy Policy</a>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="cart.php" class="btn btn-outline">
                            <i class="ri-arrow-left-line"></i> Back to Cart
                        </a>
                        <button type="submit" class="btn btn-primary">
                            Place Order <i class="ri-arrow-right-line"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>