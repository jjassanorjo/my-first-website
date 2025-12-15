<?php
// api/orders.php

header('Content-Type: application/json');
require_once '../includes/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $order_id = (int)($_GET['order_id'] ?? 0);
    
    switch ($action) {
        case 'payment_instructions':
            // Get order details
            $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $order_id, $user_id);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();
            
            if ($order) {
                $html = '<div class="payment-instructions">';
                if ($order['payment_method'] == 'gcash') {
                    $html .= '<h4>GCash Payment Instructions:</h4>';
                    $html .= '<ol>';
                    $html .= '<li>Open GCash app</li>';
                    $html .= '<li>Go to "Send Money"</li>';
                    $html .= '<li>Enter mobile number: <strong>0917-123-4567</strong></li>';
                    $html .= '<li>Amount: ' . formatPrice($order['total_amount']) . '</li>';
                    $html .= '<li>Reference: ' . $order['order_number'] . '</li>';
                    $html .= '<li>Send payment and keep receipt</li>';
                    $html .= '</ol>';
                    $html .= '<p class="note"><strong>Note:</strong> Please send the payment within 24 hours.</p>';
                } elseif ($order['payment_method'] == 'bank_transfer') {
                    $html .= '<h4>Bank Transfer Instructions:</h4>';
                    $html .= '<p><strong>Bank:</strong> BDO</p>';
                    $html .= '<p><strong>Account Name:</strong> BU Labels Merchandise</p>';
                    $html .= '<p><strong>Account Number:</strong> 123-456-7890</p>';
                    $html .= '<p><strong>Amount:</strong> ' . formatPrice($order['total_amount']) . '</p>';
                    $html .= '<p><strong>Reference:</strong> ' . $order['order_number'] . '</p>';
                } else {
                    $html .= '<p>No payment instructions needed for this payment method.</p>';
                }
                $html .= '</div>';
                
                echo json_encode(['success' => true, 'html' => $html]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Order not found']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $order_id = (int)($_POST['order_id'] ?? 0);
    
    switch ($action) {
        case 'cancel':
            // Check if order can be cancelled (only if still processing)
            $check_stmt = $conn->prepare("SELECT order_status FROM orders WHERE id = ? AND user_id = ?");
            $check_stmt->bind_param("ii", $order_id, $user_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $order = $check_result->fetch_assoc();
                if ($order['order_status'] == 'processing') {
                    $update_stmt = $conn->prepare("UPDATE orders SET order_status = 'cancelled' WHERE id = ?");
                    $update_stmt->bind_param("i", $order_id);
                    $update_stmt->execute();
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Order cannot be cancelled at this stage']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Order not found']);
            }
            break;
            
        case 'picked_up':
            // Update order status to picked up
            $update_stmt = $conn->prepare("UPDATE orders SET order_status = 'picked_up' WHERE id = ? AND user_id = ?");
            $update_stmt->bind_param("ii", $order_id, $user_id);
            $update_stmt->execute();
            echo json_encode(['success' => true]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>