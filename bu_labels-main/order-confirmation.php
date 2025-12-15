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
// order-confirmation.php

require_once 'includes/config.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('account.php');
}

$page_title = "Order Confirmation";
$body_class = "confirmation-page";

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id === 0 && isset($_SESSION['order_number'])) {
    // Get the latest order for this user
    $conn = getDBConnection();
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT id FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $order_id = $row['id'];
    }
}

if ($order_id === 0) {
    redirect('orders.php');
}

// Get order details
$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

$order_query = "SELECT o.*, u.name as customer_name, u.email 
                FROM orders o 
                JOIN users u ON o.user_id = u.id 
                WHERE o.id = ? AND o.user_id = ?";
$stmt = $conn->prepare($order_query);
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order_result = $stmt->get_result();
$order = $order_result->fetch_assoc();

if (!$order) {
    redirect('orders.php');
}

// Get order items
$items_query = "SELECT oi.*, p.name, p.image_main 
                FROM order_items oi 
                JOIN products p ON oi.product_id = p.id 
                WHERE oi.order_id = ?";
$items_stmt = $conn->prepare($items_query);
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$order_items = $items_stmt->get_result();

// Clear order session
unset($_SESSION['order_number']);
unset($_SESSION['order_total']);

require_once 'includes/header.php';
?>

<div class="small container confirmation-page">
    <div class="confirmation-header">
        <div class="success-icon">
            <i class="ri-checkbox-circle-fill"></i>
        </div>
        <h1>Order Confirmed!</h1>
        <p class="subtitle">Thank you for your purchase. Your order has been received.</p>
        
        <div class="order-info-card">
            <div class="info-item">
                <span class="label">Order Number:</span>
                <span class="value"><?php echo $order['order_number']; ?></span>
            </div>
            <div class="info-item">
                <span class="label">Date:</span>
                <span class="value"><?php echo date('F j, Y', strtotime($order['created_at'])); ?></span>
            </div>
            <div class="info-item">
                <span class="label">Total:</span>
                <span class="value"><?php echo formatPrice($order['total_amount']); ?></span>
            </div>
            <div class="info-item">
                <span class="label">Payment Method:</span>
                <span class="value"><?php echo ucwords(str_replace('_', ' ', $order['payment_method'])); ?></span>
            </div>
            <div class="info-item">
                <span class="label">Status:</span>
                <span class="value status-badge status-<?php echo $order['order_status']; ?>">
                    <?php echo ucwords(str_replace('_', ' ', $order['order_status'])); ?>
                </span>
            </div>
        </div>
    </div>
    
    <div class="confirmation-content">
        <div class="row">
            <!-- Order Details -->
            <div class="col-2">
                <div class="details-card">
                    <h3><i class="ri-shopping-bag-line"></i> Order Details</h3>
                    
                    <div class="order-items-list">
                        <?php while ($item = $order_items->fetch_assoc()): ?>
                        <div class="order-item">
                            <img src="<?php echo SITE_URL; ?>assets/images/<?php echo $item['image_main']; ?>" 
                                 alt="<?php echo sanitize($item['name']); ?>">
                            <div class="item-info">
                                <h4><?php echo sanitize($item['name']); ?></h4>
                                <div class="item-meta">
                                    <?php if (!empty($item['size'])): ?>
                                    <span class="size">Size: <?php echo $item['size']; ?></span>
                                    <?php endif; ?>
                                    <span class="quantity">Quantity: <?php echo $item['quantity']; ?></span>
                                </div>
                            </div>
                            <div class="item-price">
                                <?php echo formatPrice($item['price'] * $item['quantity']); ?>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    
                    <div class="order-summary">
                        <div class="summary-row">
                            <span>Subtotal:</span>
                            <span><?php echo formatPrice($order['total_amount']); ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Shipping:</span>
                            <span>Free (Campus Pickup)</span>
                        </div>
                        <div class="summary-row total">
                            <span>Total:</span>
                            <span><?php echo formatPrice($order['total_amount']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Customer & Pickup Info -->
            <div class="col-2">
                <div class="info-cards">
                    <div class="info-card">
                        <h3><i class="ri-user-line"></i> Customer Information</h3>
                        <div class="info-content">
                            <p><strong>Name:</strong> <?php echo sanitize($order['customer_name']); ?></p>
                            <p><strong>Email:</strong> <?php echo sanitize($order['email']); ?></p>
                        </div>
                    </div>
                    
                    <div class="info-card">
                        <h3><i class="ri-map-pin-line"></i> Pickup Information</h3>
                        <div class="info-content">
                            <p><strong>Campus:</strong> <?php echo ucfirst($order['pickup_campus']); ?> Campus</p>
                            <p><strong>Location:</strong> CSC Office, <?php echo ucfirst($order['pickup_campus']); ?> Campus</p>
                            <p><strong>Estimated Ready:</strong> 3-5 business days</p>
                            <p><strong>Status:</strong> 
                                <span class="status-badge status-<?php echo $order['order_status']; ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $order['order_status'])); ?>
                                </span>
                            </p>
                        </div>
                    </div>
                    
                    <div class="info-card">
                        <h3><i class="ri-bank-card-line"></i> Payment Information</h3>
                        <div class="info-content">
                            <p><strong>Method:</strong> <?php echo ucwords(str_replace('_', ' ', $order['payment_method'])); ?></p>
                            <p><strong>Status:</strong> 
                                <span class="status-badge status-<?php echo $order['payment_status']; ?>">
                                    <?php echo ucwords($order['payment_status']); ?>
                                </span>
                            </p>
                            
                            <?php if ($order['payment_method'] == 'gcash'): ?>
                            <div class="payment-instructions">
                                <h4>GCash Payment Instructions:</h4>
                                <ol>
                                    <li>Open GCash app</li>
                                    <li>Go to "Send Money"</li>
                                    <li>Enter mobile number: <strong>0917-123-4567</strong></li>
                                    <li>Amount: <?php echo formatPrice($order['total_amount']); ?></li>
                                    <li>Reference: <?php echo $order['order_number']; ?></li>
                                    <li>Send payment and keep receipt</li>
                                </ol>
                            </div>
                            <?php elseif ($order['payment_method'] == 'bank_transfer'): ?>
                            <div class="payment-instructions">
                                <h4>Bank Transfer Instructions:</h4>
                                <p><strong>Bank:</strong> BDO</p>
                                <p><strong>Account Name:</strong> BU Labels Merchandise</p>
                                <p><strong>Account Number:</strong> 123-456-7890</p>
                                <p><strong>Amount:</strong> <?php echo formatPrice($order['total_amount']); ?></p>
                                <p><strong>Reference:</strong> <?php echo $order['order_number']; ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="next-steps">
            <h3>What Happens Next?</h3>
            <div class="steps-timeline">
                <div class="step <?php echo ($order['order_status'] == 'processing') ? 'active' : ''; ?>">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h4>Order Processing</h4>
                        <p>Your order is being prepared. You'll receive a confirmation email.</p>
                    </div>
                </div>
                <div class="step <?php echo ($order['order_status'] == 'ready_for_pickup') ? 'active' : ''; ?>">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h4>Ready for Pickup</h4>
                        <p>We'll notify you when your order is ready at the campus CSC Office.</p>
                    </div>
                </div>
                <div class="step <?php echo ($order['order_status'] == 'picked_up') ? 'active' : ''; ?>">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h4>Order Picked Up</h4>
                        <p>Show your order confirmation or ID when picking up.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="confirmation-actions">
            <a href="orders.php" class="btn btn-outline">
                <i class="ri-history-line"></i> View Order History
            </a>
            <a href="products.php" class="btn btn-primary">
                <i class="ri-shopping-bag-line"></i> Continue Shopping
            </a>
            <button class="btn btn-secondary" onclick="window.print()">
                <i class="ri-printer-line"></i> Print Receipt
            </button>
        </div>
        
        <div class="support-info">
            <h4><i class="ri-customer-service-line"></i> Need Help?</h4>
            <p>If you have any questions about your order, please contact our support team:</p>
            <ul>
                <li><strong>Email:</strong> support@bulabels.com</li>
                <li><strong>Phone:</strong> (123) 456-7890</li>
                <li><strong>Office Hours:</strong> Monday-Friday, 8:00 AM - 5:00 PM</li>
            </ul>
        </div>
    </div>
</div>

<style>
/* Print styles */
@media print {
    nav, .footer, .confirmation-actions, .support-info {
        display: none !important;
    }
    
    .confirmation-page {
        padding: 0 !important;
    }
    
    .confirmation-header, .confirmation-content {
        margin: 0 !important;
        padding: 20px !important;
    }
    
    .success-icon {
        color: #000 !important;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>