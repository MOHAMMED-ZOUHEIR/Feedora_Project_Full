<?php
require_once '../config/config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in', 'following' => []]);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    // Get all chefs the user is following
    $stmt = $pdo->prepare("SELECT USER_ID FROM FOLLOWERS WHERE FOLLOWER_ID = ?");
    $stmt->execute([$userId]);
    $following = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode(['success' => true, 'following' => $following]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error', 'following' => []]);
}
?>
