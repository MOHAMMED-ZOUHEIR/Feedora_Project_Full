<?php
/**
 * Story Repository - Handles all database operations for stories
 * FIXED: Proper timestamp handling and individual story view tracking
 */
class StoryRepository {
    private $pdo;
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * FIXED: Create a new story with individual timestamp
     * Each story gets its own unique timestamp using NOW()
     */
    public function createStory($userId, $imageUrl, $visibility = 'public') {
        try {
            // CRITICAL FIX: Use NOW() in SQL to ensure each story gets its own timestamp
            // This prevents timestamp overwriting when multiple stories are uploaded
            $stmt = $this->pdo->prepare("
                INSERT INTO STORIES (USER_ID, IMAGE_URL, VISIBILITY, CREATED_AT, EXPIRES_AT) 
                VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 24 HOUR))
            ");
            
            $result = $stmt->execute([$userId, $imageUrl, $visibility]);
            
            if ($result) {
                $storyId = $this->pdo->lastInsertId();
                return $this->getStoryById($storyId);
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Story creation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get story by ID with user information
     */
    public function getStoryById($storyId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT s.*, u.NAME, u.PROFILE_IMAGE 
                FROM STORIES s 
                JOIN USERS u ON s.USER_ID = u.USER_ID 
                WHERE s.STORIES_ID = ? AND s.EXPIRES_AT > NOW()
            ");
            $stmt->execute([$storyId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get story by ID error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * FIXED: Get all active stories for a specific user ordered by individual timestamps
     */
    public function getUserStories($userId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT s.*, u.NAME, u.PROFILE_IMAGE 
                FROM STORIES s 
                JOIN USERS u ON s.USER_ID = u.USER_ID 
                WHERE s.USER_ID = ? AND s.EXPIRES_AT > NOW() 
                ORDER BY s.CREATED_AT ASC
            ");
            $stmt->execute([$userId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get user stories error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * FIXED: Get all active stories grouped by user with accurate view tracking
     */
    public function getAllActiveStoriesByUser($currentUserId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    s.USER_ID,
                    u.NAME, 
                    u.PROFILE_IMAGE,
                    COUNT(s.STORIES_ID) as story_count,
                    MAX(s.CREATED_AT) as latest_story_time,
                    MIN(s.STORIES_ID) as first_story_id,
                    COUNT(DISTINCT CASE WHEN sv.VIEWER_ID = ? THEN s.STORIES_ID END) as viewed_stories_count,
                    COUNT(s.STORIES_ID) as total_stories_count
                FROM STORIES s 
                JOIN USERS u ON s.USER_ID = u.USER_ID 
                LEFT JOIN STORY_VIEWS sv ON s.STORIES_ID = sv.STORY_ID
                WHERE s.EXPIRES_AT > NOW() 
                GROUP BY s.USER_ID, u.NAME, u.PROFILE_IMAGE
                ORDER BY latest_story_time DESC
            ");
            $stmt->execute([$currentUserId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get all active stories error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * CRITICAL FIX: Record view for SPECIFIC story only
     * This ensures views are tracked per individual story, not globally
     */
    public function recordStoryView($storyId, $viewerId) {
        try {
            // First verify the story exists and is not expired
            $verifyStmt = $this->pdo->prepare("
                SELECT STORIES_ID, USER_ID 
                FROM STORIES 
                WHERE STORIES_ID = ? AND EXPIRES_AT > NOW()
            ");
            $verifyStmt->execute([$storyId]);
            $story = $verifyStmt->fetch();
            
            if (!$story) {
                return ['success' => false, 'error' => 'Story not found or expired'];
            }
            
            // Don't record views for own stories
            if ($story['USER_ID'] == $viewerId) {
                return ['success' => true, 'message' => 'Own story view not recorded'];
            }
            
            // CRITICAL: Record view for THIS SPECIFIC STORY ONLY
            $stmt = $this->pdo->prepare("
                INSERT INTO STORY_VIEWS (STORY_ID, VIEWER_ID, VIEWED_AT) 
                VALUES (?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE VIEWED_AT = NOW()
            ");
            
            $result = $stmt->execute([$storyId, $viewerId]);
            
            return [
                'success' => $result,
                'message' => $result ? 'View recorded successfully' : 'Failed to record view'
            ];
            
        } catch (PDOException $e) {
            error_log("Record story view error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * FIXED: Get viewers for a SPECIFIC story only
     */
    public function getStoryViewers($storyId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT u.USER_ID, u.NAME, u.PROFILE_IMAGE, sv.VIEWED_AT 
                FROM STORY_VIEWS sv 
                JOIN USERS u ON sv.VIEWER_ID = u.USER_ID 
                WHERE sv.STORY_ID = ? 
                ORDER BY sv.VIEWED_AT DESC
            ");
            $stmt->execute([$storyId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get story viewers error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get view count for a SPECIFIC story
     */
    public function getStoryViewCount($storyId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as view_count 
                FROM STORY_VIEWS 
                WHERE STORY_ID = ?
            ");
            $stmt->execute([$storyId]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? (int)$result['view_count'] : 0;
        } catch (PDOException $e) {
            error_log("Get story view count error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Check if a user has viewed a SPECIFIC story
     */
    public function hasUserViewedStory($storyId, $userId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 1 
                FROM STORY_VIEWS 
                WHERE STORY_ID = ? AND VIEWER_ID = ?
            ");
            $stmt->execute([$storyId, $userId]);
            
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            error_log("Check user viewed story error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get expired stories for cleanup
     */
    public function getExpiredStories() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT STORIES_ID, IMAGE_URL, USER_ID, CREATED_AT, EXPIRES_AT 
                FROM STORIES 
                WHERE EXPIRES_AT <= NOW()
            ");
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get expired stories error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Delete story and its views
     */
    public function deleteStory($storyId) {
        try {
            $this->pdo->beginTransaction();
            
            // Delete views first (foreign key constraint)
            $viewStmt = $this->pdo->prepare("DELETE FROM STORY_VIEWS WHERE STORY_ID = ?");
            $viewStmt->execute([$storyId]);
            
            // Delete the story
            $storyStmt = $this->pdo->prepare("DELETE FROM STORIES WHERE STORIES_ID = ?");
            $storyStmt->execute([$storyId]);
            
            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollback();
            error_log("Delete story error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get story statistics
     */
    public function getStoryStatistics() {
        try {
            $stats = [];
            
            // Active stories
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM STORIES WHERE EXPIRES_AT > NOW()");
            $stmt->execute();
            $stats['active_stories'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Expired stories
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM STORIES WHERE EXPIRES_AT <= NOW()");
            $stmt->execute();
            $stats['expired_stories'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            return $stats;
        } catch (PDOException $e) {
            error_log("Get story statistics error: " . $e->getMessage());
            return [];
        }
    }
}
