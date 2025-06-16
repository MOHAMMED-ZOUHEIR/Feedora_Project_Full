<?php
/**
 * OPTIMIZED Notification Utilities - Fixed All Issues
 * 
 * This file contains helper functions to create notifications 
 * throughout your application with proper error handling and optimization.
 */

// Include this at the top of files that need to create notifications
if (!isset($pdo)) {
    require_once 'config/config.php';
}

/**
 * Master function to create any notification with error handling
 */
function createNotification($pdo, $fromUserId, $toUserId, $type, $content, $relatedId = null) {
    // Don't create notifications for self-actions
    if ($fromUserId == $toUserId) {
        return true;
    }
    
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO NOTIFICATIONS (USER_ID, TARGET_USER_ID, NOTIFICATION_TYPE, CONTENT, RELATED_ID, CREATED_AT) 
             VALUES (?, ?, ?, ?, ?, NOW())"
        );
        
        $result = $stmt->execute([$fromUserId, $toUserId, $type, $content, $relatedId]);
        
        if ($result) {
            error_log("✅ Notification created: {$type} from {$fromUserId} to {$toUserId}");
            return $pdo->lastInsertId();
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("❌ Notification creation failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user's followers for mass notifications
 */
function getUserFollowers($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("SELECT FOLLOWER_ID FROM FOLLOWERS WHERE USER_ID = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("Error getting followers: " . $e->getMessage());
        return [];
    }
}

/**
 * Get user name for notifications
 */
function getUserName($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("SELECT NAME FROM USERS WHERE USER_ID = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn() ?: 'Someone';
    } catch (PDOException $e) {
        error_log("Error getting user name: " . $e->getMessage());
        return 'Someone';
    }
}

// ==============================================
// SPECIFIC NOTIFICATION FUNCTIONS - FIXED
// ==============================================

/**
 * FIXED: Create notifications for followers when user posts
 */
function notifyFollowersNewPost($pdo, $postId, $userId) {
    try {
        // Get user name
        $userName = getUserName($pdo, $userId);
        
        // Get all followers - FIXED QUERY
        $followersStmt = $pdo->prepare("SELECT FOLLOWER_ID FROM FOLLOWERS WHERE USER_ID = ?");
        $followersStmt->execute([$userId]);
        $followers = $followersStmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($followers)) {
            $notificationContent = "<strong>" . htmlspecialchars($userName) . "</strong> shared a new post";
            
            // Batch insert for better performance
            $placeholders = str_repeat('(?, ?, ?, ?, ?, NOW()),', count($followers));
            $placeholders = rtrim($placeholders, ',');
            
            $sql = "INSERT INTO NOTIFICATIONS (USER_ID, TARGET_USER_ID, NOTIFICATION_TYPE, CONTENT, RELATED_ID, CREATED_AT) VALUES $placeholders";
            $stmt = $pdo->prepare($sql);
            
            $params = [];
            foreach ($followers as $followerId) {
                $params = array_merge($params, [$userId, $followerId, 'new_post', $notificationContent, $postId]);
            }
            
            $stmt->execute($params);
            error_log("✅ Post notifications sent to " . count($followers) . " followers");
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("❌ Error notifying followers of new post: " . $e->getMessage());
        return false;
    }
}

/**
 * FIXED: Create notifications for followers when user uploads story
 */
function notifyFollowersNewStory($pdo, $storyId, $userId) {
    try {
        // Get user name
        $userName = getUserName($pdo, $userId);
        
        // Get all followers
        $followersStmt = $pdo->prepare("SELECT FOLLOWER_ID FROM FOLLOWERS WHERE USER_ID = ?");
        $followersStmt->execute([$userId]);
        $followers = $followersStmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($followers)) {
            $notificationContent = "<strong>" . htmlspecialchars($userName) . "</strong> added a new story";
            
            // Batch insert for better performance
            $placeholders = str_repeat('(?, ?, ?, ?, ?, NOW()),', count($followers));
            $placeholders = rtrim($placeholders, ',');
            
            $sql = "INSERT INTO NOTIFICATIONS (USER_ID, TARGET_USER_ID, NOTIFICATION_TYPE, CONTENT, RELATED_ID, CREATED_AT) VALUES $placeholders";
            $stmt = $pdo->prepare($sql);
            
            $params = [];
            foreach ($followers as $followerId) {
                $params = array_merge($params, [$userId, $followerId, 'new_story', $notificationContent, $storyId]);
            }
            
            $stmt->execute($params);
            error_log("✅ Story notifications sent to " . count($followers) . " followers");
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("❌ Error notifying followers of new story: " . $e->getMessage());
        return false;
    }
}

/**
 * FIXED: Create notification when someone follows a user
 */
function notifyUserOfNewFollower($pdo, $followerId, $followedUserId) {
    try {
        // Don't notify if user follows themselves
        if ($followerId == $followedUserId) {
            return true;
        }
        
        // Get follower name
        $followerName = getUserName($pdo, $followerId);
        
        $notificationContent = "<strong>" . htmlspecialchars($followerName) . "</strong> started following you";
        
        $stmt = $pdo->prepare(
            "INSERT INTO NOTIFICATIONS (USER_ID, TARGET_USER_ID, NOTIFICATION_TYPE, CONTENT, RELATED_ID, CREATED_AT) 
            VALUES (?, ?, 'new_follower', ?, ?, NOW())"
        );
        
        $result = $stmt->execute([$followerId, $followedUserId, $notificationContent, $followerId]);
        
        if ($result) {
            error_log("✅ Follow notification created: {$followerId} -> {$followedUserId}");
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("❌ Error notifying user of new follower: " . $e->getMessage());
        return false;
    }
}

/**
 * FIXED: Create notification for post reactions
 */
function notifyPostReaction($pdo, $postId, $reactorId, $reactionType) {
    try {
        // Get post owner
        $postStmt = $pdo->prepare("SELECT USER_ID FROM POSTS WHERE POSTS_ID = ?");
        $postStmt->execute([$postId]);
        $postOwnerId = $postStmt->fetchColumn();
        
        if (!$postOwnerId || $postOwnerId == $reactorId) {
            return true; // Don't notify if it's the user's own post
        }
        
        // Get reactor name
        $reactorName = getUserName($pdo, $reactorId);
        
        $notificationContent = "<strong>" . htmlspecialchars($reactorName) . "</strong> reacted " . htmlspecialchars($reactionType) . " to your post";
        
        $stmt = $pdo->prepare(
            "INSERT INTO NOTIFICATIONS (USER_ID, TARGET_USER_ID, NOTIFICATION_TYPE, CONTENT, RELATED_ID, CREATED_AT) 
            VALUES (?, ?, 'new_reaction', ?, ?, NOW())"
        );
        
        return $stmt->execute([$reactorId, $postOwnerId, $notificationContent, $postId]);
    } catch (PDOException $e) {
        error_log("❌ Error notifying post reaction: " . $e->getMessage());
        return false;
    }
}

/**
 * FIXED: Create notification for comments
 */
function notifyPostComment($pdo, $postId, $commenterId, $commentText = '') {
    try {
        // Get post owner
        $postStmt = $pdo->prepare("SELECT USER_ID FROM POSTS WHERE POSTS_ID = ?");
        $postStmt->execute([$postId]);
        $postOwnerId = $postStmt->fetchColumn();
        
        if (!$postOwnerId || $postOwnerId == $commenterId) {
            return true; // Don't notify if it's the user's own post
        }
        
        // Get commenter name
        $commenterName = getUserName($pdo, $commenterId);
        
        $notificationContent = "<strong>" . htmlspecialchars($commenterName) . "</strong> commented on your post";
        
        $stmt = $pdo->prepare(
            "INSERT INTO NOTIFICATIONS (USER_ID, TARGET_USER_ID, NOTIFICATION_TYPE, CONTENT, RELATED_ID, CREATED_AT) 
            VALUES (?, ?, 'new_comment', ?, ?, NOW())"
        );
        
        return $stmt->execute([$commenterId, $postOwnerId, $notificationContent, $postId]);
    } catch (PDOException $e) {
        error_log("❌ Error notifying post comment: " . $e->getMessage());
        return false;
    }
}

/**
 * FIXED: Create notification for recipe shares
 */
function notifyFollowersNewRecipe($pdo, $recipeId, $userId) {
    try {
        // Get user name
        $userName = getUserName($pdo, $userId);
        
        // Get all followers
        $followersStmt = $pdo->prepare("SELECT FOLLOWER_ID FROM FOLLOWERS WHERE USER_ID = ?");
        $followersStmt->execute([$userId]);
        $followers = $followersStmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($followers)) {
            $notificationContent = "<strong>" . htmlspecialchars($userName) . "</strong> shared a new recipe";
            
            // Batch insert for better performance
            $placeholders = str_repeat('(?, ?, ?, ?, ?, NOW()),', count($followers));
            $placeholders = rtrim($placeholders, ',');
            
            $sql = "INSERT INTO NOTIFICATIONS (USER_ID, TARGET_USER_ID, NOTIFICATION_TYPE, CONTENT, RELATED_ID, CREATED_AT) VALUES $placeholders";
            $stmt = $pdo->prepare($sql);
            
            $params = [];
            foreach ($followers as $followerId) {
                $params = array_merge($params, [$userId, $followerId, 'new_recipe', $notificationContent, $recipeId]);
            }
            
            $stmt->execute($params);
            error_log("✅ Recipe notifications sent to " . count($followers) . " followers");
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("❌ Error notifying followers of new recipe: " . $e->getMessage());
        return false;
    }
}

/**
 * OPTIMIZED: Clean up old notifications (older than 30 days)
 */
function cleanupOldNotifications($pdo) {
    try {
        $stmt = $pdo->prepare("DELETE FROM NOTIFICATIONS WHERE CREATED_AT < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute();
        $deletedCount = $stmt->rowCount();
        error_log("✅ Cleaned up {$deletedCount} old notifications");
        return true;
    } catch (PDOException $e) {
        error_log("❌ Error cleaning up old notifications: " . $e->getMessage());
        return false;
    }
}

/**
 * OPTIMIZED: Get notification counts for a user
 */
function getNotificationCounts($pdo, $userId) {
    try {
        $stmt = $pdo->prepare(
            "SELECT 
                COUNT(*) as total_count,
                SUM(CASE WHEN IS_READ = 0 THEN 1 ELSE 0 END) as unread_count
            FROM NOTIFICATIONS 
            WHERE TARGET_USER_ID = ?"
        );
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("❌ Error getting notification counts: " . $e->getMessage());
        return ['total_count' => 0, 'unread_count' => 0];
    }
}

/**
 * OPTIMIZED: Mark notifications as read
 */
function markNotificationsAsRead($pdo, $userId, $notificationIds = []) {
    try {
        if (empty($notificationIds)) {
            // Mark all as read
            $stmt = $pdo->prepare("UPDATE NOTIFICATIONS SET IS_READ = 1 WHERE TARGET_USER_ID = ? AND IS_READ = 0");
            $result = $stmt->execute([$userId]);
        } else {
            // Mark specific notifications as read
            $placeholders = str_repeat('?,', count($notificationIds));
            $placeholders = rtrim($placeholders, ',');
            
            $sql = "UPDATE NOTIFICATIONS SET IS_READ = 1 WHERE NOTIFICATION_ID IN ($placeholders) AND TARGET_USER_ID = ? AND IS_READ = 0";
            $stmt = $pdo->prepare($sql);
            
            $params = array_merge($notificationIds, [$userId]);
            $result = $stmt->execute($params);
        }
        
        return $result ? $stmt->rowCount() : 0;
    } catch (PDOException $e) {
        error_log("❌ Error marking notifications as read: " . $e->getMessage());
        return 0;
    }
}

/**
 * OPTIMIZED: Delete notifications
 */
function deleteNotifications($pdo, $userId, $notificationIds) {
    try {
        if (empty($notificationIds)) {
            return 0;
        }
        
        $placeholders = str_repeat('?,', count($notificationIds));
        $placeholders = rtrim($placeholders, ',');
        
        $sql = "DELETE FROM NOTIFICATIONS WHERE NOTIFICATION_ID IN ($placeholders) AND TARGET_USER_ID = ?";
        $stmt = $pdo->prepare($sql);
        
        $params = array_merge($notificationIds, [$userId]);
        $result = $stmt->execute($params);
        
        return $result ? $stmt->rowCount() : 0;
    } catch (PDOException $e) {
        error_log("❌ Error deleting notifications: " . $e->getMessage());
        return 0;
    }
}

// ==============================================
// BATCH NOTIFICATION FUNCTIONS - OPTIMIZED
// ==============================================

/**
 * OPTIMIZED: Send notifications to multiple users at once
 */
function sendBatchNotifications($pdo, $fromUserId, $targetUserIds, $type, $content, $relatedId = null) {
    try {
        if (empty($targetUserIds)) {
            return true;
        }
        
        // Filter out the sender and remove duplicates
        $targetUserIds = array_unique(array_filter($targetUserIds, function($id) use ($fromUserId) {
            return $id != $fromUserId;
        }));
        
        if (empty($targetUserIds)) {
            return true;
        }
        
        // Batch insert
        $placeholders = str_repeat('(?, ?, ?, ?, ?, NOW()),', count($targetUserIds));
        $placeholders = rtrim($placeholders, ',');
        
        $sql = "INSERT INTO NOTIFICATIONS (USER_ID, TARGET_USER_ID, NOTIFICATION_TYPE, CONTENT, RELATED_ID, CREATED_AT) VALUES $placeholders";
        $stmt = $pdo->prepare($sql);
        
        $params = [];
        foreach ($targetUserIds as $targetUserId) {
            $params = array_merge($params, [$fromUserId, $targetUserId, $type, $content, $relatedId]);
        }
        
        $result = $stmt->execute($params);
        
        if ($result) {
            error_log("✅ Batch notifications sent: {$type} from {$fromUserId} to " . count($targetUserIds) . " users");
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("❌ Error sending batch notifications: " . $e->getMessage());
        return false;
    }
}

/**
 * UTILITY: Check if notification already exists (prevent duplicates)
 */
function notificationExists($pdo, $fromUserId, $toUserId, $type, $relatedId, $timeFrame = 300) {
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM NOTIFICATIONS 
            WHERE USER_ID = ? AND TARGET_USER_ID = ? AND NOTIFICATION_TYPE = ? AND RELATED_ID = ? 
            AND CREATED_AT > DATE_SUB(NOW(), INTERVAL ? SECOND)"
        );
        $stmt->execute([$fromUserId, $toUserId, $type, $relatedId, $timeFrame]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("❌ Error checking notification existence: " . $e->getMessage());
        return false;
    }
}

?>