<?php
// Include the database connection script
require_once 'config/config.php';
// Start the session to manage user data
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    // Return error response
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

$userId = $_SESSION['user_id'];
$response = ['success' => false, 'message' => ''];

// Handle AJAX requests for notification actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Mark notifications as read
    if ($_POST['action'] === 'mark_notifications_read' && isset($_POST['notification_ids'])) {
        try {
            // Get notification IDs from the request
            $notificationIds = json_decode($_POST['notification_ids'], true);
            
            if (!empty($notificationIds) && is_array($notificationIds)) {
                // Create placeholders for the SQL query
                $placeholders = implode(',', array_fill(0, count($notificationIds), '?'));
                
                // Update notifications to mark them as read
                $stmt = $pdo->prepare("UPDATE NOTIFICATIONS SET IS_READ = 1 WHERE NOTIFICATION_ID IN ($placeholders) AND TARGET_USER_ID = ?");
                
                // Combine notification IDs and user ID for the query
                $params = array_merge($notificationIds, [$userId]);
                $stmt->execute($params);
                
                // Get updated unread count
                $countStmt = $pdo->prepare("SELECT COUNT(*) as count FROM NOTIFICATIONS WHERE TARGET_USER_ID = ? AND IS_READ = 0");
                $countStmt->execute([$userId]);
                $result = $countStmt->fetch(PDO::FETCH_ASSOC);
                $unreadCount = $result['count'];
                
                $response['success'] = true;
                $response['message'] = 'Notifications marked as read';
                $response['unread_count'] = $unreadCount;
            } else {
                $response['message'] = 'Invalid notification IDs';
            }
        } catch (PDOException $e) {
            $response['message'] = 'Error updating notifications: ' . $e->getMessage();
        }
    }
    
    // Create a new notification (for testing purposes)
    if ($_POST['action'] === 'create_test_notification') {
        try {
            // Create a test notification
            $stmt = $pdo->prepare("INSERT INTO NOTIFICATIONS (USER_ID, TARGET_USER_ID, NOTIFICATION_TYPE, CONTENT) VALUES (?, ?, 'test', ?)");
            $stmt->execute([$userId, $userId, 'This is a test notification']);
            
            $response['success'] = true;
            $response['message'] = 'Test notification created';
        } catch (PDOException $e) {
            $response['message'] = 'Error creating test notification: ' . $e->getMessage();
        }
    }
    
    // FIXED: Get all notifications with correct column aliases
    if ($_POST['action'] === 'get_all_notifications') {
        try {
            $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
            $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;
            $offset = ($page - 1) * $limit;
            
            // FIXED: Use correct aliases that match header.php expectations
            $stmt = $pdo->prepare(
                "SELECT 
                    n.NOTIFICATION_ID,
                    n.USER_ID,
                    n.TARGET_USER_ID,
                    n.NOTIFICATION_TYPE,
                    n.CONTENT,
                    n.RELATED_ID,
                    n.IS_READ,
                    n.CREATED_AT,
                    u.NAME as SENDER_NAME,
                    u.PROFILE_IMAGE as SENDER_IMAGE,
                    u.USER_ID as SENDER_USER_ID
                FROM NOTIFICATIONS n 
                LEFT JOIN USERS u ON n.USER_ID = u.USER_ID 
                WHERE n.TARGET_USER_ID = ? 
                ORDER BY n.CREATED_AT DESC 
                LIMIT ? OFFSET ?"
            );
            $stmt->execute([$userId, $limit, $offset]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get total count for pagination
            $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM NOTIFICATIONS WHERE TARGET_USER_ID = ?");
            $countStmt->execute([$userId]);
            $totalResult = $countStmt->fetch(PDO::FETCH_ASSOC);
            
            $response['success'] = true;
            $response['notifications'] = $notifications;
            $response['pagination'] = [
                'total' => $totalResult['total'],
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($totalResult['total'] / $limit)
            ];
        } catch (PDOException $e) {
            $response['message'] = 'Error fetching notifications: ' . $e->getMessage();
        }
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit();
?>