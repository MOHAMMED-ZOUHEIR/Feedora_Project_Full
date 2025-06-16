<?php
/**
 * Story Manager - High-level controller for story operations
 * Coordinates between service layers and handles complex workflows
 */
class StoryManager {
    private $service;
    private $repository;
    private $fileHandler;
    
    public function __construct(StoryService $service, StoryRepository $repository, StoryFileHandler $fileHandler) {
        $this->service = $service;
        $this->repository = $repository;
        $this->fileHandler = $fileHandler;
    }
    
    /**
     * Handle story upload with proper error handling and validation
     */
    public function handleUpload($userId, $files, $visibility = 'public') {
        try {
            // Validate user ID
            if (!$userId || $userId <= 0) {
                return [
                    'success' => false,
                    'message' => 'Invalid user ID',
                    'uploaded_count' => 0
                ];
            }
            
            // Validate files
            if (empty($files) || !isset($files['name'])) {
                return [
                    'success' => false,
                    'message' => 'No files provided for upload',
                    'uploaded_count' => 0
                ];
            }
            
            // Process upload through service
            $result = $this->service->uploadStories($userId, $files, $visibility);
            
            // Add additional metadata if successful
            if ($result['success'] && !empty($result['uploaded_stories'])) {
                $result['stories'] = $result['uploaded_stories'];
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Story upload error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage(),
                'uploaded_count' => 0
            ];
        }
    }
    
    /**
     * FIXED: Handle story viewing with individual story tracking
     */
    public function handleStoryView($storyId, $viewerId) {
        try {
            // Validate story access
            if (!$this->service->canUserAccessStory($storyId, $viewerId)) {
                return [
                    'success' => false,
                    'message' => 'Story not found or access denied'
                ];
            }
            
            // Get the story
            $story = $this->service->getStoryById($storyId);
            if (!$story) {
                return [
                    'success' => false,
                    'message' => 'Story not found'
                ];
            }
            
            // Get all stories from the same user for navigation
            $userStories = $this->service->getUserStories($story['USER_ID']);
            
            // Find current story index
            $currentIndex = 0;
            foreach ($userStories as $index => $userStory) {
                if ($userStory['STORIES_ID'] == $storyId) {
                    $currentIndex = $index;
                    break;
                }
            }
            
            // CRITICAL FIX: Record view for this specific story only
            $this->recordIndividualView($storyId, $viewerId);
            
            return [
                'success' => true,
                'story' => $story,
                'user_stories' => $userStories,
                'current_index' => $currentIndex
            ];
            
        } catch (Exception $e) {
            error_log("Story view error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error viewing story: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * FIXED: Record view for individual story only
     */
    public function recordIndividualView($storyId, $viewerId) {
        try {
            $result = $this->service->recordStoryView($storyId, $viewerId);
            
            return [
                'success' => $result['success'],
                'message' => $result['message'] ?? ($result['success'] ? 'View recorded' : 'Failed to record view')
            ];
            
        } catch (Exception $e) {
            error_log("Record individual view error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error recording view: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get story viewers (owner only)
     */
    public function getStoryViewers($storyId, $ownerId) {
        try {
            return $this->service->getStoryViewers($storyId, $ownerId);
        } catch (Exception $e) {
            error_log("Get story viewers error: " . $e->getMessage());
            return [
                'success' => false,
                'viewers' => [],
                'view_count' => 0,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get all active stories by user
     */
    public function getAllActiveStoriesByUser($currentUserId) {
        try {
            return $this->service->getAllActiveStoriesByUser($currentUserId);
        } catch (Exception $e) {
            error_log("Get all active stories error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Cleanup expired stories
     */
    public function cleanupExpiredStories() {
        try {
            return $this->service->cleanupExpiredStories();
        } catch (Exception $e) {
            error_log("Cleanup expired stories error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Cleanup failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get story statistics
     */
    public function getStatistics() {
        try {
            return $this->service->getStatistics();
        } catch (Exception $e) {
            error_log("Get statistics error: " . $e->getMessage());
            return [];
        }
    }
}
