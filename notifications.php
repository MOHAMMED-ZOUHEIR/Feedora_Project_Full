<?php
/**
 * COMPLETELY FIXED notifications.php - Professional Solution
 * 
 * Fixes all issues:
 * 1. Simplified filters (All & Unread only)
 * 2. Correct display logic
 * 3. Proper mark as read functionality
 * 4. Fixed stability after clicking notifications
 * 5. Corrected timezone and timestamp display
 * 6. Professional OOP design with error handling
 */

// Set timezone first (adjust to your server timezone)
date_default_timezone_set('UTC'); // Change this to your timezone, e.g., 'America/New_York'

require_once 'notification_helpers.php';
require_once 'config/config.php';
require_once 'notification_utils.php';
session_start();

/**
 * NotificationManager Class - Professional OOP Approach
 */
class NotificationManager {
    private $pdo;
    private $userId;
    private $timezone;
    
    public function __construct($pdo, $userId, $timezone = 'UTC') {
        $this->pdo = $pdo;
        $this->userId = $userId;
        $this->timezone = $timezone;
        
        // Set timezone for this session
        date_default_timezone_set($this->timezone);
        
        // Set MySQL timezone to match PHP
        $this->pdo->exec("SET time_zone = '+00:00'"); // Adjust based on your needs
    }
    
    /**
     * Get notifications with proper filtering
     */
    public function getNotifications($filter = 'all', $page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        
        // Base query - always get notifications for current user
        $baseQuery = "
            SELECT 
                n.NOTIFICATION_ID,
                n.USER_ID,
                n.TARGET_USER_ID,
                n.NOTIFICATION_TYPE,
                n.CONTENT,
                n.RELATED_ID,
                n.IS_READ,
                n.CREATED_AT,
                COALESCE(u.NAME, 'System') as SENDER_NAME,
                COALESCE(u.PROFILE_IMAGE, 'images/default-profile.png') as SENDER_IMAGE,
                u.USER_ID as SENDER_USER_ID
            FROM NOTIFICATIONS n 
            LEFT JOIN USERS u ON n.USER_ID = u.USER_ID 
            WHERE n.TARGET_USER_ID = ?
        ";
        
        $countQuery = "
            SELECT COUNT(*) as total 
            FROM NOTIFICATIONS n 
            LEFT JOIN USERS u ON n.USER_ID = u.USER_ID 
            WHERE n.TARGET_USER_ID = ?
        ";
        
        $queryParams = [$this->userId];
        
        // Apply filter conditions
        switch ($filter) {
            case 'unread':
                $baseQuery .= " AND n.IS_READ = 0";
                $countQuery .= " AND n.IS_READ = 0";
                break;
            case 'all':
            default:
                // No additional conditions - show all notifications
                break;
        }
        
        // Add ordering and pagination
        $baseQuery .= " ORDER BY n.IS_READ ASC, n.CREATED_AT DESC LIMIT ? OFFSET ?";
        $finalParams = array_merge($queryParams, [$limit, $offset]);
        
        try {
            // Get total count
            $countStmt = $this->pdo->prepare($countQuery);
            $countStmt->execute($queryParams);
            $totalResult = $countStmt->fetch(PDO::FETCH_ASSOC);
            $total = intval($totalResult['total']);
            
            // Get notifications
            $stmt = $this->pdo->prepare($baseQuery);
            $stmt->execute($finalParams);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get unread count (always needed for badge)
            $unreadStmt = $this->pdo->prepare("SELECT COUNT(*) as unread FROM NOTIFICATIONS WHERE TARGET_USER_ID = ? AND IS_READ = 0");
            $unreadStmt->execute([$this->userId]);
            $unreadResult = $unreadStmt->fetch(PDO::FETCH_ASSOC);
            $unreadCount = intval($unreadResult['unread']);
            
            return [
                'notifications' => $notifications,
                'total' => $total,
                'unread_count' => $unreadCount,
                'current_page' => $page,
                'total_pages' => ceil($total / $limit),
                'per_page' => $limit
            ];
            
        } catch (PDOException $e) {
            error_log("❌ NotificationManager::getNotifications error: " . $e->getMessage());
            return [
                'notifications' => [],
                'total' => 0,
                'unread_count' => 0,
                'current_page' => 1,
                'total_pages' => 0,
                'per_page' => $limit,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Mark single notification as read
     */
    public function markAsRead($notificationId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE NOTIFICATIONS 
                SET IS_READ = 1 
                WHERE NOTIFICATION_ID = ? AND TARGET_USER_ID = ? AND IS_READ = 0
            ");
            $result = $stmt->execute([$notificationId, $this->userId]);
            
            if ($result && $stmt->rowCount() > 0) {
                // Get updated unread count
                $countStmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM NOTIFICATIONS WHERE TARGET_USER_ID = ? AND IS_READ = 0");
                $countStmt->execute([$this->userId]);
                $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
                
                return [
                    'success' => true,
                    'message' => 'Notification marked as read',
                    'unread_count' => intval($countResult['count'])
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Notification not found or already read'
            ];
            
        } catch (PDOException $e) {
            error_log("❌ NotificationManager::markAsRead error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Database error occurred'
            ];
        }
    }
    
    /**
     * Mark all notifications as read
     */
    public function markAllAsRead() {
        try {
            $stmt = $this->pdo->prepare("UPDATE NOTIFICATIONS SET IS_READ = 1 WHERE TARGET_USER_ID = ? AND IS_READ = 0");
            $result = $stmt->execute([$this->userId]);
            
            if ($result) {
                $affectedRows = $stmt->rowCount();
                return [
                    'success' => true,
                    'message' => 'All notifications marked as read',
                    'count' => $affectedRows,
                    'unread_count' => 0
                ];
            }
            
            return [
                'success' => false,
                'message' => 'No unread notifications found'
            ];
            
        } catch (PDOException $e) {
            error_log("❌ NotificationManager::markAllAsRead error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Database error occurred'
            ];
        }
    }
    
    /**
     * Delete notification
     */
    public function deleteNotification($notificationId) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM NOTIFICATIONS WHERE NOTIFICATION_ID = ? AND TARGET_USER_ID = ?");
            $result = $stmt->execute([$notificationId, $this->userId]);
            
            if ($result && $stmt->rowCount() > 0) {
                // Get updated unread count
                $countStmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM NOTIFICATIONS WHERE TARGET_USER_ID = ? AND IS_READ = 0");
                $countStmt->execute([$this->userId]);
                $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
                
                return [
                    'success' => true,
                    'message' => 'Notification deleted successfully',
                    'unread_count' => intval($countResult['count'])
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Notification not found'
            ];
            
        } catch (PDOException $e) {
            error_log("❌ NotificationManager::deleteNotification error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Database error occurred'
            ];
        }
    }
    
    /**
     * Format notification time with proper timezone handling
     */
    public function formatTime($dateString) {
        try {
            // Create DateTime object with UTC timezone (assuming database stores UTC)
            $notificationTime = new DateTime($dateString, new DateTimeZone('UTC'));
            
            // Convert to user's timezone if needed
            $userTimezone = new DateTimeZone($this->timezone);
            $notificationTime->setTimezone($userTimezone);
            
            $now = new DateTime('now', $userTimezone);
            $diff = $now->getTimestamp() - $notificationTime->getTimestamp();
            
            // Format based on time difference
            if ($diff < 60) {
                return 'Just now';
            } elseif ($diff < 3600) {
                $minutes = floor($diff / 60);
                return $minutes . ' minute' . ($minutes !== 1 ? 's' : '') . ' ago';
            } elseif ($diff < 86400) {
                $hours = floor($diff / 3600);
                return $hours . ' hour' . ($hours !== 1 ? 's' : '') . ' ago';
            } elseif ($diff < 604800) {
                $days = floor($diff / 86400);
                return $days . ' day' . ($days !== 1 ? 's' : '') . ' ago';
            } elseif ($diff < 2592000) {
                $weeks = floor($diff / 604800);
                return $weeks . ' week' . ($weeks !== 1 ? 's' : '') . ' ago';
            } else {
                // For older notifications, show the actual date
                return $notificationTime->format('M j, Y \a\t g:i A');
            }
            
        } catch (Exception $e) {
            error_log("❌ NotificationManager::formatTime error: " . $e->getMessage());
            return 'Unknown time';
        }
    }
    
    /**
     * Generate notification link
     */
    public function getNotificationLink($notification) {
        if (!$notification || !isset($notification['NOTIFICATION_TYPE'])) {
            return 'dashboard.php';
        }

        $type = $notification['NOTIFICATION_TYPE'];
        $relatedId = $notification['RELATED_ID'] ?? null;
        $senderId = $notification['USER_ID'] ?? null;

        switch ($type) {
            case 'new_post':
            case 'new_reaction':
            case 'new_comment':
                if ($relatedId) {
                    return 'dashboard.php?highlight=' . intval($relatedId) . '#post-' . intval($relatedId);
                }
                break;

            case 'new_story':
                if ($senderId) {
                    return 'story.php?user_id=' . intval($senderId);
                }
                break;

            case 'new_recipe':
                if ($relatedId) {
                    return 'recipes.php?recipe_id=' . intval($relatedId);
                }
                break;

            case 'new_follower':
                if ($senderId) {
                    return 'profile.php?user_id=' . intval($senderId);
                }
                break;

            case 'new_message':
                if ($senderId) {
                    return 'messages.php?user_id=' . intval($senderId);
                }
                break;
        }

        return 'dashboard.php';
    }
}

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: sign-in.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';
$userEmail = $_SESSION['user_email'] ?? '';
$profileImage = $_SESSION['profile_image'] ?? 'images/default-profile.png';

// Initialize NotificationManager
$notificationManager = new NotificationManager($pdo, $userId, 'UTC'); // Change timezone as needed

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'mark_as_read':
            $notificationId = intval($_POST['notification_id'] ?? 0);
            if ($notificationId > 0) {
                echo json_encode($notificationManager->markAsRead($notificationId));
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
            }
            break;
            
        case 'mark_all_read':
            echo json_encode($notificationManager->markAllAsRead());
            break;
            
        case 'delete_notification':
            $notificationId = intval($_POST['notification_id'] ?? 0);
            if ($notificationId > 0) {
                echo json_encode($notificationManager->deleteNotification($notificationId));
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    exit();
}

// Handle filter selection - ONLY 'all' and 'unread'
$allowedFilters = ['all', 'unread'];
$currentFilter = 'all'; // Default to 'all'

if (isset($_GET['filter']) && in_array($_GET['filter'], $allowedFilters)) {
    $currentFilter = $_GET['filter'];
}

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;

// Get notifications
$result = $notificationManager->getNotifications($currentFilter, $page, $limit);
$notifications = $result['notifications'];
$totalNotifications = $result['total'];
$unreadCount = $result['unread_count'];
$totalPages = $result['total_pages'];
$error = $result['error'] ?? null;

// Check for action messages
$actionMessage = '';
if (isset($_SESSION['notification_message'])) {
    $actionMessage = $_SESSION['notification_message'];
    unset($_SESSION['notification_message']);
}

error_log("📋 FIXED Notifications: Filter={$currentFilter}, Found=" . count($notifications) . ", Total={$totalNotifications}, Unread={$unreadCount}");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Feedora</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="fonts.css">
    <link rel="stylesheet" href="Home.css">
    <link rel="icon" href="images/Frame 1171277973.svg" type="image/svg+xml">
    
    <style>
        :root {
            --primary-color: #ED5A2C;
            --secondary-color: #4CAF50;
            --text-color: #333;
            --light-text: #666;
            --background-color: #fff;
            --light-background: #f9f9f9;
            --border-color: #eaeaea;
            --border-radius: 12px;
            --transition-speed: 0.3s;
            --box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            --hover-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
            --sidebar-width: 250px;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Qurova', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background-color: #f5f5f5 !important;
            color: var(--text-color);
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
            display: flex;
            flex-direction: column;
        }

        .notifications-container {
            background-color: white !important;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            max-width: 900px;
            margin: 0 auto;
            width: 100%;
        }

        .notifications-header {
            padding: 25px 30px;
            border-bottom: 1px solid var(--border-color);
            background: linear-gradient(135deg, #ED5A2C 0%, #ff6b3d 100%);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .notifications-title {
            font-family: 'DM Serif Display', serif;
            font-size: 28px;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .notifications-count {
            background: rgba(255, 255, 255, 0.2);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }

        .notifications-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .action-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .action-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-1px);
        }

        .action-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        /* SIMPLIFIED: Only 2 filters now */
        .notification-filters {
            padding: 20px 30px;
            border-bottom: 1px solid var(--border-color);
            background-color: #fafafa;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-label {
            font-weight: 600;
            color: var(--text-color);
            margin-right: 10px;
        }

        .filter-btn {
            background: white;
            color: var(--light-text);
            border: 1px solid var(--border-color);
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }

        .filter-btn:hover {
            background-color: rgba(237, 90, 44, 0.1);
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .filter-btn.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            box-shadow: 0 2px 8px rgba(237, 90, 44, 0.3);
        }

        .filter-counts {
            margin-left: auto;
            display: flex;
            gap: 15px;
            align-items: center;
            font-size: 14px;
            color: var(--light-text);
        }

        .filter-count-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .filter-count-badge {
            background: var(--primary-color);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .notifications-list {
            max-height: 70vh;
            overflow-y: auto;
        }

        .notification-item {
            display: flex;
            align-items: flex-start;
            padding: 20px 30px;
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.2s ease;
            cursor: pointer;
            position: relative;
        }

        .notification-item:hover {
            background-color: #f8f9fa;
            transform: translateX(5px);
        }

        .notification-item.unread {
            background-color: rgba(237, 90, 44, 0.02);
            border-left: 4px solid var(--primary-color);
        }

        .notification-item.unread::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            width: 8px;
            height: 8px;
            background: var(--primary-color);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        .notification-item.read {
            opacity: 0.8;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: translateY(-50%) scale(1); }
            50% { opacity: 0.6; transform: translateY(-50%) scale(1.2); }
        }

        .notification-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;
            border: 2px solid #f0f0f0;
            transition: all 0.2s ease;
        }

        .notification-item:hover .notification-avatar {
            transform: scale(1.05);
            border-color: var(--primary-color);
        }

        .notification-content {
            flex: 1;
            min-width: 0;
        }

        .notification-text {
            margin: 0 0 8px 0;
            font-size: 15px;
            line-height: 1.4;
            color: var(--text-color);
        }

        .notification-text strong {
            color: var(--primary-color);
            font-weight: 600;
        }

        .notification-time {
            font-size: 12px;
            color: var(--light-text);
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 5px;
        }

        .notification-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: 15px;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .notification-item:hover .notification-actions {
            opacity: 1;
        }

        .notification-action {
            background: none;
            border: none;
            cursor: pointer;
            padding: 6px;
            border-radius: 50%;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .notification-action:hover {
            background-color: #f0f0f0;
            transform: scale(1.1);
        }

        .mark-read-btn {
            color: var(--primary-color);
        }

        .delete-btn {
            color: #f44336;
        }

        .notification-icon {
            font-size: 18px;
            margin-left: 10px;
            opacity: 0.7;
            display: flex;
            align-items: center;
            gap: 5px;
            flex-direction: column;
            text-align: center;
        }

        .empty-state {
            text-align: center;
            padding: 60px 30px;
            color: var(--light-text);
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .empty-state h3 {
            margin: 0 0 10px 0;
            font-size: 20px;
            color: var(--text-color);
        }

        .empty-state p {
            margin: 0;
            font-size: 14px;
        }

        .pagination {
            padding: 20px 30px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            background-color: #fafafa;
        }

        .pagination-btn {
            background: white;
            border: 1px solid var(--border-color);
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            color: var(--text-color);
            font-weight: 500;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .pagination-btn:hover {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            transform: translateY(-1px);
        }

        .pagination-btn.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination-info {
            color: var(--light-text);
            font-size: 14px;
            margin: 0 15px;
        }

        .redirect-actions {
            margin-top: 8px;
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .redirect-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .redirect-btn:hover {
            background: #d94e22;
            transform: translateY(-1px);
        }

        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--primary-color);
            color: white;
            padding: 12px 20px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 500;
            z-index: 10000;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }

        .toast.success {
            background: var(--secondary-color);
        }

        .toast.error {
            background: #f44336;
        }

        .toast.info {
            background: #2196F3;
        }

        /* Mobile responsive */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 10px;
            }
            
            .notifications-header {
                padding: 20px;
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .notifications-title {
                font-size: 24px;
            }

            .notification-filters {
                padding: 15px 20px;
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .filter-counts {
                margin-left: 0;
                align-self: stretch;
                justify-content: space-between;
            }
            
            .notification-item {
                padding: 15px 20px;
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .notification-avatar {
                width: 40px;
                height: 40px;
                margin-right: 10px;
            }
            
            .notification-actions {
                opacity: 1;
                margin-left: 0;
                align-self: flex-end;
            }
            
            .pagination {
                padding: 15px;
                flex-wrap: wrap;
            }
            
            .pagination-info {
                width: 100%;
                text-align: center;
                margin: 10px 0;
            }
        }
    </style>
</head>

<body>
    <?php include('sidebar.php'); ?>

    <main class="main-content">
        <?php include('header.php'); ?>

        <div class="notifications-container">
            <!-- Header -->
            <div class="notifications-header">
                <div>
                    <h1 class="notifications-title">
                        🔔 Notifications
                        <?php if ($unreadCount > 0): ?>
                            <span class="notifications-count"><?php echo $unreadCount; ?> new</span>
                        <?php endif; ?>
                    </h1>
                </div>
                
                <?php if ($totalNotifications > 0): ?>
                <div class="notifications-actions">
                    <?php if ($unreadCount > 0): ?>
                        <button class="action-btn" onclick="markAllAsRead()" id="markAllBtn">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 6L9 17l-5-5"/>
                            </svg>
                            Mark All Read
                        </button>
                    <?php endif; ?>
                    
                    <button class="action-btn" onclick="window.location.reload()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M23 4v6h-6"/>
                            <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                        </svg>
                        Refresh
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <!-- SIMPLIFIED: Only 2 Filters -->
            <div class="notification-filters">
                <span class="filter-label">Filter:</span>
                
                <a href="?filter=all" class="filter-btn <?php echo $currentFilter === 'all' ? 'active' : ''; ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                    All
                </a>
                
                <a href="?filter=unread" class="filter-btn <?php echo $currentFilter === 'unread' ? 'active' : ''; ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M12 6v6l4 2"/>
                    </svg>
                    Unread
                </a>

                <div class="filter-counts">
                    <div class="filter-count-item">
                        <span>Total:</span>
                        <span class="filter-count-badge"><?php echo $totalNotifications; ?></span>
                    </div>
                    <?php if ($unreadCount > 0): ?>
                    <div class="filter-count-item">
                        <span>Unread:</span>
                        <span class="filter-count-badge"><?php echo $unreadCount; ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Notifications List -->
            <div class="notifications-list">
                <?php if (empty($notifications)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <?php echo $currentFilter === 'unread' ? '📭' : '🔕'; ?>
                        </div>
                        <h3>
                            <?php echo $currentFilter === 'unread' ? 'No unread notifications' : 'No notifications yet'; ?>
                        </h3>
                        <p>
                            <?php 
                            echo $currentFilter === 'unread' 
                                ? 'All your notifications have been read! 🎉' 
                                : 'You\'ll see notifications here when people interact with your posts, stories, and recipes!';
                            ?>
                        </p>
                        <?php if ($error): ?>
                            <div style="background: #ffebee; color: #c62828; padding: 15px; border-radius: 8px; margin-top: 20px;">
                                <strong>⚠️ Error:</strong> <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                        <?php
                        if (!$notification || !isset($notification['NOTIFICATION_ID'])) {
                            continue;
                        }
                        
                        $userImage = !empty($notification['SENDER_IMAGE']) ? $notification['SENDER_IMAGE'] : 'images/default-profile.png';
                        $userName = !empty($notification['SENDER_NAME']) ? $notification['SENDER_NAME'] : 'Unknown User';
                        $isRead = $notification['IS_READ'] ? 'read' : 'unread';
                        $linkUrl = $notificationManager->getNotificationLink($notification);
                        $content = !empty($notification['CONTENT']) ? $notification['CONTENT'] : 'New notification';
                        $formattedTime = $notificationManager->formatTime($notification['CREATED_AT']);
                        ?>
                        
                        <div class="notification-item <?php echo $isRead; ?>" 
                             id="notification-<?php echo $notification['NOTIFICATION_ID']; ?>">
                            
                            <img src="<?php echo htmlspecialchars($userImage); ?>" 
                                 alt="<?php echo htmlspecialchars($userName); ?>" 
                                 class="notification-avatar"
                                 onerror="this.src='images/default-profile.png'">
                            
                            <div class="notification-content">
                                <div class="notification-time">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"/>
                                        <polyline points="12,6 12,12 16,14"/>
                                    </svg>
                                    <?php echo $formattedTime; ?>
                                    <?php if (!$notification['IS_READ']): ?>
                                        <span style="color: var(--primary-color); font-weight: 600; margin-left: 8px;">• NEW</span>
                                    <?php endif; ?>
                                </div>
                                
                                <p class="notification-text">
                                    <?php echo $content; ?>
                                </p>

                                <?php if ($linkUrl !== 'dashboard.php'): ?>
                                <div class="redirect-actions">
                                    <a href="<?php echo htmlspecialchars($linkUrl); ?>" 
                                       class="redirect-btn"
                                       onclick="markNotificationAsReadAndGo(<?php echo $notification['NOTIFICATION_ID']; ?>, '<?php echo htmlspecialchars($linkUrl); ?>', event)">
                                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="7" y1="17" x2="17" y2="7"></line>
                                            <polyline points="7 7 17 7 17 17"></polyline>
                                        </svg>
                                        View Source
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="notification-icon">
                                <span style="font-size: 24px;"><?php echo getNotificationIcon($notification['NOTIFICATION_TYPE']); ?></span>
                                <span style="font-size: 10px; color: #666; text-transform: capitalize; margin-top: 2px;">
                                    <?php echo str_replace('_', ' ', $notification['NOTIFICATION_TYPE']); ?>
                                </span>
                            </div>
                            
                            <div class="notification-actions">
                                <?php if (!$notification['IS_READ']): ?>
                                    <button class="notification-action mark-read-btn" 
                                            onclick="event.stopPropagation(); markAsRead(<?php echo $notification['NOTIFICATION_ID']; ?>)"
                                            title="Mark as read">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M20 6L9 17l-5-5"/>
                                        </svg>
                                    </button>
                                <?php endif; ?>
                                
                                <button class="notification-action delete-btn" 
                                        onclick="event.stopPropagation(); deleteNotification(<?php echo $notification['NOTIFICATION_ID']; ?>)"
                                        title="Delete notification">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="3 6 5 6 21 6"/>
                                        <path d="m19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&filter=<?php echo $currentFilter; ?>" class="pagination-btn">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="15 18 9 12 15 6"/>
                            </svg>
                            Previous
                        </a>
                    <?php endif; ?>
                    
                    <div class="pagination-info">
                        Page <?php echo $page; ?> of <?php echo $totalPages; ?>
                        (<?php echo $totalNotifications; ?> total)
                    </div>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&filter=<?php echo $currentFilter; ?>" class="pagination-btn">
                            Next
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="9 18 15 12 9 6"/>
                            </svg>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <div id="toast"></div>

    <script>
        // COMPLETELY FIXED JavaScript for notifications
        
        // Global variables
        let isProcessing = false;
        
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 FIXED Notifications System Loaded');
            
            <?php if ($actionMessage): ?>
            showToast('<?php echo addslashes($actionMessage); ?>', 'success');
            <?php endif; ?>
            
            console.log(`📊 Stats: ${<?php echo count($notifications); ?>} displayed, ${<?php echo $totalNotifications; ?>} total, ${<?php echo $unreadCount; ?>} unread, filter: <?php echo $currentFilter; ?>`);
        });

        // FIXED: Mark notification as read and redirect
        function markNotificationAsReadAndGo(notificationId, redirectUrl, event) {
            event.preventDefault();
            event.stopPropagation();
            
            if (isProcessing) return;
            
            console.log('📋 Mark as read and redirect:', notificationId, redirectUrl);
            
            showToast('Opening source...', 'info');
            
            // Open redirect URL immediately
            window.open(redirectUrl, '_blank');
            
            // Mark as read in background
            markAsReadRequest(notificationId, function(success, data) {
                if (success) {
                    updateNotificationUI(notificationId, data.unread_count);
                }
            });
        }

        // FIXED: Mark single notification as read
        function markAsRead(notificationId) {
            if (isProcessing) return;
            
            markAsReadRequest(notificationId, function(success, data) {
                if (success) {
                    updateNotificationUI(notificationId, data.unread_count);
                    showToast('✅ Marked as read', 'success');
                } else {
                    showToast('❌ ' + (data.message || 'Error marking as read'), 'error');
                }
            });
        }

        // FIXED: Mark all notifications as read
        function markAllAsRead() {
            const unreadNotifications = document.querySelectorAll('.notification-item.unread');
            if (unreadNotifications.length === 0) {
                showToast('ℹ️ No unread notifications', 'info');
                return;
            }

            if (!confirm(`Mark all ${unreadNotifications.length} notifications as read?`)) {
                return;
            }

            if (isProcessing) return;
            isProcessing = true;

            const markAllBtn = document.getElementById('markAllBtn');
            if (markAllBtn) {
                markAllBtn.disabled = true;
                markAllBtn.innerHTML = '<span>⏳ Processing...</span>';
            }

            fetch('notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=mark_all_read'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI based on current filter
                    const currentFilter = '<?php echo $currentFilter; ?>';
                    
                    if (currentFilter === 'all') {
                        // In 'all' view, just update styling
                        unreadNotifications.forEach(notification => {
                            notification.classList.remove('unread');
                            notification.classList.add('read');
                            
                            const newIndicator = notification.querySelector('.notification-time span');
                            if (newIndicator && newIndicator.textContent.includes('NEW')) {
                                newIndicator.remove();
                            }
                            
                            const markReadBtn = notification.querySelector('.mark-read-btn');
                            if (markReadBtn) {
                                markReadBtn.remove();
                            }
                        });
                    } else if (currentFilter === 'unread') {
                        // In 'unread' view, remove notifications
                        unreadNotifications.forEach(notification => {
                            notification.style.animation = 'slideOut 0.3s ease forwards';
                            setTimeout(() => notification.remove(), 300);
                        });
                        
                        // Show empty state after animation
                        setTimeout(() => {
                            if (document.querySelectorAll('.notification-item').length === 0) {
                                location.reload();
                            }
                        }, 400);
                    }
                    
                    updateUnreadCount(0);
                    showToast(`✅ ${data.count} notifications marked as read`, 'success');
                    
                    if (markAllBtn) {
                        markAllBtn.style.display = 'none';
                    }
                } else {
                    showToast('❌ ' + (data.message || 'Error marking all as read'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('❌ Network error occurred', 'error');
            })
            .finally(() => {
                isProcessing = false;
                if (markAllBtn) {
                    markAllBtn.disabled = false;
                    markAllBtn.innerHTML = `
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 6L9 17l-5-5"/>
                        </svg>
                        Mark All Read
                    `;
                }
            });
        }

        // FIXED: Delete notification
        function deleteNotification(notificationId) {
            if (!confirm('🗑️ Delete this notification permanently?')) {
                return;
            }

            if (isProcessing) return;
            isProcessing = true;

            fetch('notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=delete_notification&notification_id=${notificationId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const notificationElement = document.getElementById(`notification-${notificationId}`);
                    if (notificationElement) {
                        notificationElement.style.animation = 'slideOut 0.3s ease forwards';
                        setTimeout(() => {
                            notificationElement.remove();
                            updateUnreadCount(data.unread_count);
                            
                            if (document.querySelectorAll('.notification-item').length === 0) {
                                location.reload();
                            }
                        }, 300);
                    }
                    showToast('✅ Notification deleted', 'success');
                } else {
                    showToast('❌ ' + (data.message || 'Error deleting notification'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('❌ Network error occurred', 'error');
            })
            .finally(() => {
                isProcessing = false;
            });
        }

        // Helper function for mark as read requests
        function markAsReadRequest(notificationId, callback) {
            if (isProcessing) return;
            isProcessing = true;

            fetch('notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=mark_as_read&notification_id=${notificationId}`
            })
            .then(response => response.json())
            .then(data => {
                callback(data.success, data);
            })
            .catch(error => {
                console.error('Error:', error);
                callback(false, { message: 'Network error occurred' });
            })
            .finally(() => {
                isProcessing = false;
            });
        }

        // Helper function to update notification UI
        function updateNotificationUI(notificationId, unreadCount) {
            const currentFilter = '<?php echo $currentFilter; ?>';
            const notificationElement = document.getElementById(`notification-${notificationId}`);
            
            if (notificationElement) {
                if (currentFilter === 'all') {
                    // In 'all' view, just update styling
                    notificationElement.classList.remove('unread');
                    notificationElement.classList.add('read');
                    
                    const newIndicator = notificationElement.querySelector('.notification-time span');
                    if (newIndicator && newIndicator.textContent.includes('NEW')) {
                        newIndicator.remove();
                    }
                    
                    const markReadBtn = notificationElement.querySelector('.mark-read-btn');
                    if (markReadBtn) {
                        markReadBtn.remove();
                    }
                } else if (currentFilter === 'unread') {
                    // In 'unread' view, remove notification
                    notificationElement.style.animation = 'slideOut 0.3s ease forwards';
                    setTimeout(() => {
                        notificationElement.remove();
                        
                        if (document.querySelectorAll('.notification-item').length === 0) {
                            location.reload();
                        }
                    }, 300);
                }
            }
            
            updateUnreadCount(unreadCount);
        }

        // Helper function to update unread count
        function updateUnreadCount(count) {
            const countBadge = document.querySelector('.notifications-count');
            const markAllBtn = document.getElementById('markAllBtn');
            const filterCountBadges = document.querySelectorAll('.filter-count-badge');
            
            if (count === 0) {
                if (countBadge) {
                    countBadge.remove();
                }
                if (markAllBtn) {
                    markAllBtn.style.display = 'none';
                }
            } else if (countBadge) {
                countBadge.textContent = `${count} new`;
            }
            
            // Update filter count badges
            if (filterCountBadges.length > 1) {
                filterCountBadges[1].textContent = count;
            }
            
            // Update header notification badge
            const headerBadge = document.getElementById('notification-badge');
            if (headerBadge) {
                if (count > 0) {
                    headerBadge.textContent = count;
                    headerBadge.style.display = 'flex';
                } else {
                    headerBadge.style.display = 'none';
                }
            }
        }

        // Toast notification function
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            
            toast.innerHTML = `<span>${message}</span>`;
            toast.className = `toast ${type} show`;
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // Add slide-out animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideOut {
                from {
                    opacity: 1;
                    transform: translateX(0);
                    max-height: 100px;
                    padding: 20px 30px;
                    margin: 0;
                }
                to {
                    opacity: 0;
                    transform: translateX(-100%);
                    max-height: 0;
                    padding: 0 30px;
                    margin: 0;
                }
            }
        `;
        document.head.appendChild(style);

        console.log('✅ COMPLETELY FIXED Notifications System Ready!');
    </script>

</body>
</html>