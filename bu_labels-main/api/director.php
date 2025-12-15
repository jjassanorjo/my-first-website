<?php
// api/director.php

header('Content-Type: application/json');
require_once '../includes/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || $_SESSION['user_role'] != 'director') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$campus = $_SESSION['user']['campus'];
$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'pending_count':
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE pickup_campus = ? AND order_status IN ('processing', 'ready_for_pickup')");
            $stmt->bind_param("s", $campus);
            $stmt->execute();
            $result = $stmt->get_result();
            $count = $result->fetch_assoc()['count'];
            echo json_encode(['success' => true, 'count' => $count]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $order_id = (int)($_POST['order_id'] ?? 0);
    $product_id = (int)($_POST['product_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);
    
    switch ($action) {
        case 'mark_picked_up':
            // Check if order belongs to director's campus
            $check_stmt = $conn->prepare("SELECT id FROM orders WHERE id = ? AND pickup_campus = ?");
            $check_stmt->bind_param("is", $order_id, $campus);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows > 0) {
                $update_stmt = $conn->prepare("UPDATE orders SET order_status = 'picked_up' WHERE id = ?");
                $update_stmt->bind_param("i", $order_id);
                $update_stmt->execute();
                
                // Log the action
                $log_stmt = $conn->prepare("INSERT INTO order_status_logs (order_id, status, notes, updated_by) VALUES (?, 'picked_up', 'Marked as picked up by campus director', ?)");
                $log_stmt->bind_param("is", $order_id, $_SESSION['user']['name']);
                $log_stmt->execute();
                
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Order not found in your campus']);
            }
            break;
            
        case 'request_stock':
            if ($product_id > 0 && $quantity > 0) {
                // Get product details
                $product_stmt = $conn->prepare("SELECT name FROM products WHERE id = ?");
                $product_stmt->bind_param("i", $product_id);
                $product_stmt->execute();
                $product = $product_stmt->get_result()->fetch_assoc();
                
                if ($product) {
                    // Create stock request
                    $insert_stmt = $conn->prepare("INSERT INTO stock_requests (product_id, campus, quantity, requested_by, status) VALUES (?, ?, ?, ?, 'pending')");
                    $insert_stmt->bind_param("isis", $product_id, $campus, $quantity, $_SESSION['user']['name']);
                    $insert_stmt->execute();
                    
                    // Send notification to admin (in real app, this would be email/notification)
                    $notification = "Stock request from " . ucfirst($campus) . " Campus: " . $product['name'] . " x" . $quantity;
                    
                    echo json_encode(['success' => true, 'message' => 'Stock request submitted']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Product not found']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid request']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>