<?php
require_once '../config/config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Get request data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['chef_id']) || !isset($data['action'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$chefId = $data['chef_id'];
$action = $data['action'];
$userId = $_SESSION['user_id'];

// Prevent following yourself
if ($chefId == $userId) {
    echo json_encode(['success' => false, 'message' => 'You cannot follow yourself']);
    exit;
}

try {
    // Check if chef exists
    $checkChef = $pdo->prepare("SELECT USER_ID FROM USERS WHERE USER_ID = ?");
    $checkChef->execute([$chefId]);
    if (!$checkChef->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Chef not found']);
        exit;
    }

    if ($action === 'follow') {
        // Check if already following
        $checkFollowing = $pdo->prepare("SELECT * FROM FOLLOWERS WHERE USER_ID = ? AND FOLLOWER_ID = ?");
        $checkFollowing->execute([$chefId, $userId]);
        
        if (!$checkFollowing->fetch()) {
            // Add follow relationship
            $follow = $pdo->prepare("INSERT INTO FOLLOWERS (USER_ID, FOLLOWER_ID, FOLLOWED_AT) VALUES (?, ?, NOW())");
            $follow->execute([$chefId, $userId]);
            
            // Create notification for the chef
            $notification = $pdo->prepare("
                INSERT INTO NOTIFICATIONS (USER_ID, TARGET_USER_ID, NOTIFICATION_TYPE, CONTENT, RELATED_ID) 
                VALUES (?, ?, 'follow', ?, ?)
            ");
            $notification->execute([
                $userId, 
                $chefId, 
                'started following you', 
                $userId
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Successfully followed chef']);
        } else {
            echo json_encode(['success' => true, 'message' => 'Already following this chef']);
        }
    } else if ($action === 'unfollow') {
        // Remove follow relationship
        $unfollow = $pdo->prepare("DELETE FROM FOLLOWERS WHERE USER_ID = ? AND FOLLOWER_ID = ?");
        $unfollow->execute([$chefId, $userId]);
        
        echo json_encode(['success' => true, 'message' => 'Successfully unfollowed chef']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
