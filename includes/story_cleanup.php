<?php
/**
 * FIXED Story Cleanup - Clean expired stories with proper timestamp handling
 * This file handles the automatic cleanup of expired stories
 */

require_once 'StoryRepository.php';
require_once 'StoryService.php';
require_once 'StoryFileHandler.php';
require_once 'StoryFactory.php';

/**
 * FIXED: Clean up expired stories using proper timestamp logic
 * Stories expire 24 hours after their individual CREATED_AT timestamp
 */
function cleanupExpiredStories($pdo) {
    try {
        // Initialize story manager
        $storyManager = StoryFactory::createStoryManager($pdo);
        
        // Get cleanup result
        $result = $storyManager->cleanupExpiredStories();
        
        // Log the cleanup result
        if ($result['success']) {
            if ($result['deleted_count'] > 0) {
                error_log("Story cleanup: Successfully cleaned up {$result['deleted_count']} expired stories");
            }
        } else {
            error_log("Story cleanup error: " . ($result['message'] ?? 'Unknown error'));
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Story cleanup exception: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Cleanup failed: ' . $e->getMessage(),
            'deleted_count' => 0
        ];
    }
}

/**
 * Get expired stories count for monitoring
 */
function getExpiredStoriesCount($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as expired_count 
            FROM STORIES 
            WHERE EXPIRES_AT <= NOW()
        ");
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['expired_count'] : 0;
        
    } catch (PDOException $e) {
        error_log("Get expired stories count error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Manual cleanup function for admin use
 */
function forceCleanupExpiredStories($pdo) {
    return cleanupExpiredStories($pdo);
}

/**
 * Get stories that will expire soon (within next hour)
 */
function getStoriesExpiringSoon($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT s.STORIES_ID, s.USER_ID, s.IMAGE_URL, s.CREATED_AT, s.EXPIRES_AT, u.NAME
            FROM STORIES s 
            JOIN USERS u ON s.USER_ID = u.USER_ID 
            WHERE s.EXPIRES_AT > NOW() 
            AND s.EXPIRES_AT <= DATE_ADD(NOW(), INTERVAL 1 HOUR)
            ORDER BY s.EXPIRES_AT ASC
        ");
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Get stories expiring soon error: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if cleanup is needed (more than 10 expired stories)
 */
function isCleanupNeeded($pdo) {
    $expiredCount = getExpiredStoriesCount($pdo);
    return $expiredCount > 10;
}

/**
 * Scheduled cleanup function (can be called from cron)
 */
function scheduledCleanup($pdo) {
    if (isCleanupNeeded($pdo)) {
        return cleanupExpiredStories($pdo);
    }
    
    return [
        'success' => true,
        'message' => 'No cleanup needed',
        'deleted_count' => 0
    ];
}
?>
