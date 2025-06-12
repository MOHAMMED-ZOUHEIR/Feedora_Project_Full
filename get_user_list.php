<?php
// Include the database connection script
require_once 'config/config.php';
// Start the session to manage user data
session_start();

// Helper function to get profile image
function getProfileImage($userProfileImage) {
    return !empty($userProfileImage) ? htmlspecialchars($userProfileImage) : 'images/default-profile.png';
}

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$response = ['success' => false, 'users' => [], 'message' => ''];

// Get parameters
$type = isset($_GET['type']) ? $_GET['type'] : '';
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if (!$type || !$userId) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

try {    if ($type === 'followers') {
        // Get users who follow this user (people who are following the specified user)
        $stmt = $pdo->prepare(
            "SELECT u.USER_ID, u.NAME, u.EMAIL, u.PROFILE_IMAGE 
            FROM FOLLOWERS f 
            JOIN USERS u ON f.FOLLOWER_ID = u.USER_ID 
            WHERE f.USER_ID = ? 
            ORDER BY f.FOLLOWED_AT DESC"
        );
        $stmt->execute([$userId]);
        
    } else if ($type === 'following') {
        // Get users that this user follows (people who the specified user is following)
        $stmt = $pdo->prepare(
            "SELECT u.USER_ID, u.NAME, u.EMAIL, u.PROFILE_IMAGE 
            FROM FOLLOWERS f 
            JOIN USERS u ON f.USER_ID = u.USER_ID 
            WHERE f.FOLLOWER_ID = ? 
            ORDER BY f.FOLLOWED_AT DESC"
        );
        $stmt->execute([$userId]);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid type']);
        exit();
    }
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Set default profile images for users without one
    foreach ($users as &$user) {
        $user['PROFILE_IMAGE'] = getProfileImage($user['PROFILE_IMAGE']);
    }
    
    $response['success'] = true;
    $response['users'] = $users;
    $response['message'] = count($users) . ' ' . $type . ' found';
    
} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>