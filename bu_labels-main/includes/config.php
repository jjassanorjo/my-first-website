<?php
// includes/config.php

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'bu_labels');

// Site configuration
define('SITE_URL', 'http://localhost/bu_labels/');
define('SITE_NAME', 'BU Labels');
define('SITE_TAGLINE', 'Official University Merchandise');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
function getDBConnection() {
    static $conn = null;
    
    if ($conn === null) {
        try {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($conn->connect_error) {
                throw new Exception("Connection failed: " . $conn->connect_error);
            }
            
            $conn->set_charset("utf8mb4");
        } catch (Exception $e) {
            die("Database connection error: " . $e->getMessage());
        }
    }
    
    return $conn;
}

// Helper function to prevent SQL injection
function escape($data) {
    $conn = getDBConnection();
    return $conn->real_escape_string(trim($data));
}

// Redirect function
function redirect($url) {
    header("Location: " . SITE_URL . $url);
    exit();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Get current user data
function getCurrentUser() {
    if (isLoggedIn() && isset($_SESSION['user'])) {
        return $_SESSION['user'];
    }
    return null;
}
?>