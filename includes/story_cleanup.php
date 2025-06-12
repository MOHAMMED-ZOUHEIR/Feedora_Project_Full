<?php
/**
 * Story Cleanup Script
 * 
 * This script handles the automatic deletion of expired stories (older than 24 hours)
 * It can be included in dashboard.php or run as a scheduled task via cron
 */

function cleanupExpiredStories($pdo) {
    try {
        // First, get a list of expired story image URLs to delete the files
        $getExpiredStmt = $pdo->prepare("SELECT IMAGE_URL FROM STORIES WHERE EXPIRES_AT <= NOW() AND IMAGE_URL IS NOT NULL");
        $getExpiredStmt->execute();
        $expiredStories = $getExpiredStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Delete the expired story image files
        foreach ($expiredStories as $story) {
            if (!empty($story['IMAGE_URL']) && file_exists($story['IMAGE_URL'])) {
                @unlink($story['IMAGE_URL']);
            }
        }
        
        // Delete expired stories from the database
        // Note: Story views will be automatically deleted due to ON DELETE CASCADE
        $deleteStmt = $pdo->prepare("DELETE FROM STORIES WHERE EXPIRES_AT <= NOW()");
        $result = $deleteStmt->execute();
        
        $deletedCount = $deleteStmt->rowCount();
        
        return [
            'success' => true,
            'deleted_count' => $deletedCount,
            'message' => "Successfully deleted $deletedCount expired stories."
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => "Error cleaning up expired stories: " . $e->getMessage()
        ];
    }
}

// If this script is called directly, run the cleanup
if (basename($_SERVER['SCRIPT_FILENAME']) == basename(__FILE__)) {
    // Include database connection
    require_once dirname(__DIR__) . '/config/config.php';
    
    // Run the cleanup
    $result = cleanupExpiredStories($pdo);
    
    // Output result
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}
