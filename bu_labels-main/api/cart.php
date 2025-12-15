<?php
// api/cart.php

header('Content-Type: application/json');
require_once '../includes/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $product_id = (int)($_POST['product_id'] ?? 0);
    $cart_id = (int)($_POST['cart_id'] ?? 0);
    $size = $_POST['size'] ?? '';
    $quantity = (int)($_POST['quantity'] ?? 1);
    
    switch ($action) {
        case 'add':
            // Check if item exists
            $check_stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ? AND size = ?");
            $check_stmt->bind_param("iis", $user_id, $product_id, $size);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $new_quantity = $row['quantity'] + $quantity;
                $update_stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
                $update_stmt->bind_param("ii", $new_quantity, $row['id']);
                $update_stmt->execute();
            } else {
                $insert_stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, size, quantity) VALUES (?, ?, ?, ?)");
                $insert_stmt->bind_param("iisi", $user_id, $product_id, $size, $quantity);
                $insert_stmt->execute();
            }
            echo json_encode(['success' => true]);
            break;
            
        case 'update':
            $update_stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
            $update_stmt->bind_param("iii", $quantity, $cart_id, $user_id);
            $update_stmt->execute();
            echo json_encode(['success' => true]);
            break;
            
        case 'remove':
            $remove_stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
            $remove_stmt->bind_param("ii", $cart_id, $user_id);
            $remove_stmt->execute();
            echo json_encode(['success' => true]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>