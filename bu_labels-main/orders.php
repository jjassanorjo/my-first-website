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
// orders.php

require_once 'includes/config.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('account.php?login=1');
}

$page_title = "My Orders";
$body_class = "orders-page";

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get orders with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get total count
$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM orders WHERE user_id = ?");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// Get orders
$orders_query = "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?";
$orders_stmt = $conn->prepare($orders_query);
$orders_stmt->bind_param("iii", $user_id, $limit, $offset);
$orders_stmt->execute();
$orders = $orders_stmt->get_result();

// Get status counts
$status_counts = [
    'all' => $total_rows,
    'processing' => 0,
    'ready_for_pickup' => 0,
    'picked_up' => 0,
    'cancelled' => 0
];

$status_stmt = $conn->prepare("SELECT order_status, COUNT(*) as count FROM orders WHERE user_id = ? GROUP BY order_status");
$status_stmt->bind_param("i", $user_id);
$status_stmt->execute();
$status_result = $status_stmt->get_result();
while ($row = $status_result->fetch_assoc()) {
    $status_counts[$row['order_status']] = $row['count'];
}

// Get filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

require_once 'includes/header.php';
?>

<div class="small container orders-page">
    <div class="page-header">
        <h1>My Orders</h1>
        <p>Track and manage your orders</p>
    </div>
    
    <div class="orders-content">
        <!-- Order Stats -->
        <div class="order-stats">
            <div class="stat-card">
                <div class="stat-icon all">
                    <i class="ri-shopping-bag-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $status_counts['all']; ?></h3>
                    <p>Total Orders</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon processing">
                    <i class="ri-refresh-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $status_counts['processing']; ?></h3>
                    <p>Processing</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon ready_for_pickup">
                    <i class="ri-checkbox-circle-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $status_counts['ready_for_pickup']; ?></h3>
                    <p>Ready for Pickup</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon picked_up">
                    <i class="ri-truck-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $status_counts['picked_up']; ?></h3>
                    <p>Completed</p>
                </div>
            </div>
        </div>
        
        <!-- Order Filters -->
        <div class="order-filters">
            <div class="filter-tabs">
                <a href="?filter=all" class="filter-tab <?php echo ($filter == 'all') ? 'active' : ''; ?>">
                    All Orders <span class="count"><?php echo $status_counts['all']; ?></span>
                </a>
                <a href="?filter=processing" class="filter-tab <?php echo ($filter == 'processing') ? 'active' : ''; ?>">
                    Processing <span class="count"><?php echo $status_counts['processing']; ?></span>
                </a>
                <a href="?filter=ready_for_pickup" class="filter-tab <?php echo ($filter == 'ready_for_pickup') ? 'active' : ''; ?>">
                    Ready for Pickup <span class="count"><?php echo $status_counts['ready_for_pickup']; ?></span>
                </a>
                <a href="?filter=picked_up" class="filter-tab <?php echo ($filter == 'picked_up') ? 'active' : ''; ?>">
                    Completed <span class="count"><?php echo $status_counts['picked_up']; ?></span>
                </a>
                <a href="?filter=cancelled" class="filter-tab <?php echo ($filter == 'cancelled') ? 'active' : ''; ?>">
                    Cancelled <span class="count"><?php echo $status_counts['cancelled']; ?></span>
                </a>
            </div>
            
            <div class="search-box">
                <input type="text" placeholder="Search by order number..." id="order-search">
                <button type="button" id="search-btn"><i class="ri-search-line"></i></button>
            </div>
        </div>
        
        <!-- Orders List -->
        <div class="orders-list">
            <?php if ($orders->num_rows > 0): ?>
                <?php while ($order = $orders->fetch_assoc()): 
                    // Skip if filtered and doesn't match
                    if ($filter != 'all' && $order['order_status'] != $filter) {
                        continue;
                    }
                    
                    // Get order items count
                    $items_stmt = $conn->prepare("SELECT COUNT(*) as count FROM order_items WHERE order_id = ?");
                    $items_stmt->bind_param("i", $order['id']);
                    $items_stmt->execute();
                    $items_result = $items_stmt->get_result();
                    $items_count = $items_result->fetch_assoc()['count'];
                ?>
                <div class="order-card" data-order-number="<?php echo $order['order_number']; ?>">
                    <div class="order-header">
                        <div class="order-info">
                            <h3>Order #<?php echo $order['order_number']; ?></h3>
                            <div class="order-meta">
                                <span class="date"><?php echo date('F j, Y', strtotime($order['created_at'])); ?></span>
                                <span class="items"><?php echo $items_count; ?> item<?php echo ($items_count != 1) ? 's' : ''; ?></span>
                                <span class="payment"><?php echo ucwords(str_replace('_', ' ', $order['payment_method'])); ?></span>
                            </div>
                        </div>
                        
                        <div class="order-status">
                            <span class="status-badge status-<?php echo $order['order_status']; ?>">
                                <?php echo ucwords(str_replace('_', ' ', $order['order_status'])); ?>
                            </span>
                            <span class="total"><?php echo formatPrice($order['total_amount']); ?></span>
                        </div>
                    </div>
                    
                    <div class="order-body">
                        <?php
                        // Get order items for preview
                        $preview_stmt = $conn->prepare("SELECT oi.*, p.name, p.image_main 
                                                       FROM order_items oi 
                                                       JOIN products p ON oi.product_id = p.id 
                                                       WHERE oi.order_id = ? LIMIT 3");
                        $preview_stmt->bind_param("i", $order['id']);
                        $preview_stmt->execute();
                        $preview_items = $preview_stmt->get_result();
                        ?>
                        
                        <div class="order-items-preview">
                            <?php while ($item = $preview_items->fetch_assoc()): ?>
                            <div class="preview-item">
                                <img src="<?php echo SITE_URL; ?>assets/images/<?php echo $item['image_main']; ?>" 
                                     alt="<?php echo sanitize($item['name']); ?>">
                                <div class="preview-info">
                                    <h4><?php echo sanitize($item['name']); ?></h4>
                                    <div class="item-meta">
                                        <?php if (!empty($item['size'])): ?>
                                        <span class="size">Size: <?php echo $item['size']; ?></span>
                                        <?php endif; ?>
                                        <span class="quantity">Qty: <?php echo $item['quantity']; ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                            
                            <?php if ($items_count > 3): ?>
                            <div class="more-items">
                                +<?php echo $items_count - 3; ?> more items
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="order-pickup">
                            <div class="pickup-info">
                                <i class="ri-map-pin-line"></i>
                                <div>
                                    <strong>Pickup Location:</strong>
                                    <span>CSC Office, <?php echo ucfirst($order['pickup_campus']); ?> Campus</span>
                                </div>
                            </div>
                            
                            <?php if ($order['order_status'] == 'ready_for_pickup'): ?>
                            <div class="pickup-notice">
                                <i class="ri-information-line"></i>
                                <p>Your order is ready for pickup. Please bring your order confirmation or valid ID.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="order-actions">
                        <a href="order-confirmation.php?order_id=<?php echo $order['id']; ?>" class="btn btn-small">
                            <i class="ri-eye-line"></i> View Details
                        </a>
                        
                        <?php if ($order['order_status'] == 'processing'): ?>
                        <button class="btn btn-small btn-outline cancel-order" data-order-id="<?php echo $order['id']; ?>">
                            <i class="ri-close-line"></i> Cancel Order
                        </button>
                        <?php endif; ?>
                        
                        <?php if ($order['order_status'] == 'ready_for_pickup'): ?>
                        <button class="btn btn-small btn-primary mark-picked-up" data-order-id="<?php echo $order['id']; ?>">
                            <i class="ri-check-line"></i> Mark as Picked Up
                        </button>
                        <?php endif; ?>
                        
                        <a href="order-confirmation.php?order_id=<?php echo $order['id']; ?>&print=1" class="btn btn-small btn-outline" target="_blank">
                            <i class="ri-printer-line"></i> Print Receipt
                        </a>
                        
                        <?php if ($order['order_status'] == 'processing' && $order['payment_method'] == 'gcash'): ?>
                        <a href="#" class="btn btn-small btn-primary" onclick="showPaymentInstructions(<?php echo $order['id']; ?>)">
                            <i class="ri-bank-card-line"></i> Payment Instructions
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
                
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
                
            <?php else: ?>
                <div class="empty-orders">
                    <div class="empty-icon">
                        <i class="ri-shopping-bag-line"></i>
                    </div>
                    <h3>No orders found</h3>
                    <p>You haven't placed any orders yet.</p>
                    <a href="products.php" class="btn btn-primary">Start Shopping</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Payment Instructions Modal -->
<div class="modal" id="paymentModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Payment Instructions</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body" id="paymentInstructions">
            <!-- Content will be loaded via AJAX -->
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline modal-close">Close</button>
        </div>
    </div>
</div>

<script>
// Order search
document.getElementById('search-btn').addEventListener('click', function() {
    const searchTerm = document.getElementById('order-search').value.trim();
    if (searchTerm) {
        const orderCards = document.querySelectorAll('.order-card');
        orderCards.forEach(card => {
            const orderNumber = card.dataset.orderNumber;
            if (orderNumber.includes(searchTerm)) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    } else {
        // Show all cards
        document.querySelectorAll('.order-card').forEach(card => {
            card.style.display = 'block';
        });
    }
});

// Cancel order
document.querySelectorAll('.cancel-order').forEach(btn => {
    btn.addEventListener('click', function() {
        const orderId = this.dataset.orderId;
        if (confirm('Are you sure you want to cancel this order?')) {
            fetch('api/orders.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=cancel&order_id=${orderId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }
    });
});

// Mark as picked up
document.querySelectorAll('.mark-picked-up').forEach(btn => {
    btn.addEventListener('click', function() {
        const orderId = this.dataset.orderId;
        if (confirm('Have you picked up your order?')) {
            fetch('api/orders.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=picked_up&order_id=${orderId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }
    });
});

// Payment instructions modal
function showPaymentInstructions(orderId) {
    fetch(`api/orders.php?action=payment_instructions&order_id=${orderId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('paymentInstructions').innerHTML = data.html;
                document.getElementById('paymentModal').style.display = 'block';
            }
        });
}

// Modal close handlers
document.querySelectorAll('.modal-close').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('paymentModal').style.display = 'none';
    });
});

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('paymentModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>