<?php
// includes/functions.php

require_once 'config.php';

// Generate random string
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

// Generate order number
function generateOrderNumber() {
    return 'BU' . date('Ymd') . strtoupper(generateRandomString(6));
}

// Format price
function formatPrice($price) {
    return '₱' . number_format($price, 2);
}

// Get cart count
function getCartCount() {
    if (!isLoggedIn()) {
        return isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
    }
    
    $conn = getDBConnection();
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT SUM(quantity) as count FROM cart WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['count'] ?? 0;
}

// Get categories for navigation
function getCategories() {
    $conn = getDBConnection();
    $result = $conn->query("SELECT * FROM categories ORDER BY name");
    $categories = [];
    
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    
    return $categories;
}

// Sanitize output
function sanitize($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}
?>