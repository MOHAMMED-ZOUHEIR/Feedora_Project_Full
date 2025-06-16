<?php
/**
 * Story Service - Business logic layer for story operations
 * FIXED: Proper individual timestamp handling and view tracking
 */
class StoryService {
    private $repository;
    private $fileHandler;
    
    public function __construct(StoryRepository $repository, StoryFileHandler $fileHandler) {
        $this->repository = $repository;
        $this->fileHandler = $fileHandler;
    }
    
    /**
     * FIXED: Upload multiple stories with individual timestamps
     * Each story gets its own unique timestamp when created
     */
    public function uploadStories($userId, $files, $visibility = 'public') {
        $results = [
            'uploaded_count' => 0,
            'error_count' => 0,
            'errors' => [],
            'uploaded_stories' => [],
            'success' => false,
            'message' => ''
        ];
        
        try {
            // Handle multiple files
            if (is_array($files['name'])) {
                $fileCount = count($files['name']);
                
                for ($i = 0; $i < $fileCount; $i++) {
                    // Skip empty uploads
                    if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }
                    
                    $singleFile = [
                        'name' => $files['name'][$i],
                        'type' => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i],
                        'size' => $files['size'][$i]
                    ];
                    
                    $uploadResult = $this->uploadSingleStory($userId, $singleFile, $visibility);
                    
                    if ($uploadResult['success']) {
                        $results['uploaded_count']++;
                        $results['uploaded_stories'][] = $uploadResult['story'];
                    } else {
                        $results['error_count']++;
                        $results['errors'][] = $uploadResult['error'];
                    }
                }
            } else {
                // Handle single file upload
                $uploadResult = $this->uploadSingleStory($userId, $files, $visibility);
                
                if ($uploadResult['success']) {
                    $results['uploaded_count'] = 1;
                    $results['uploaded_stories'][] = $uploadResult['story'];
                } else {
                    $results['error_count'] = 1;
                    $results['errors'][] = $uploadResult['error'];
                }
            }
            
            // Set overall success and message
            if ($results['uploaded_count'] > 0) {
                $results['success'] = true;
                $results['message'] = "Successfully uploaded {$results['uploaded_count']} story" . 
                                    ($results['uploaded_count'] > 1 ? 'ies' : '');
                
                if ($results['error_count'] > 0) {
                    $results['message'] .= " ({$results['error_count']} failed)";
                }
            } else {
                $results['message'] = 'No stories were uploaded successfully';
                if (!empty($results['errors'])) {
                    $results['message'] .= ': ' . implode(', ', $results['errors']);
                }
            }
            
        } catch (Exception $e) {
            error_log("Upload stories error: " . $e->getMessage());
            $results['message'] = 'Upload failed: ' . $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * FIXED: Upload single story with proper individual timestamp handling
     */
    private function uploadSingleStory($userId, $file, $visibility) {
        try {
            // Validate file
            $validation = $this->fileHandler->validateFile($file);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error' => $validation['error']
                ];
            }
            
            // Upload file
            $uploadResult = $this->fileHandler->uploadFile($file, $userId);
            if (!$uploadResult['success']) {
                return [
                    'success' => false,
                    'error' => $uploadResult['error']
                ];
            }
            
            // CRITICAL FIX: Create story in database with individual NOW() timestamp
            // The repository ensures each story gets its own unique timestamp
            $story = $this->repository->createStory($userId, $uploadResult['file_path'], $visibility);
            
            if (!$story) {
                // Cleanup uploaded file if database insert fails
                $this->fileHandler->deleteFile($uploadResult['file_path']);
                return [
                    'success' => false,
                    'error' => 'Failed to save story to database'
                ];
            }
            
            return [
                'success' => true,
                'story' => $story
            ];
            
        } catch (Exception $e) {
            error_log("Upload single story error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Upload failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get all stories for a user
     */
    public function getUserStories($userId) {
        return $this->repository->getUserStories($userId);
    }
    
    /**
     * Get all active stories grouped by user
     */
    public function getAllActiveStoriesByUser($currentUserId) {
        return $this->repository->getAllActiveStoriesByUser($currentUserId);
    }
    
    /**
     * Get story by ID
     */
    public function getStoryById($storyId) {
        return $this->repository->getStoryById($storyId);
    }
    
    /**
     * FIXED: Record view for SPECIFIC story only
     */
    public function recordStoryView($storyId, $viewerId) {
        return $this->repository->recordStoryView($storyId, $viewerId);
    }
    
    /**
     * FIXED: Get viewers for SPECIFIC story only
     */
    public function getStoryViewers($storyId, $ownerId) {
        // Only allow story owner to see viewers
        $story = $this->repository->getStoryById($storyId);
        if (!$story || $story['USER_ID'] != $ownerId) {
            return [
                'success' => false,
                'viewers' => [],
                'view_count' => 0,
                'error' => 'Unauthorized access'
            ];
        }
        
        $viewers = $this->repository->getStoryViewers($storyId);
        $viewCount = $this->repository->getStoryViewCount($storyId);
        
        return [
            'success' => true,
            'viewers' => $viewers,
            'view_count' => $viewCount
        ];
    }
    
    /**
     * Get view count for specific story
     */
    public function getStoryViewCount($storyId) {
        return $this->repository->getStoryViewCount($storyId);
    }
    
    /**
     * Check if user has viewed specific story
     */
    public function hasUserViewedStory($storyId, $userId) {
        return $this->repository->hasUserViewedStory($storyId, $userId);
    }
    
    /**
     * Cleanup expired stories
     */
    public function cleanupExpiredStories() {
        try {
            $expiredStories = $this->repository->getExpiredStories();
            
            if (empty($expiredStories)) {
                return [
                    'success' => true,
                    'deleted_count' => 0,
                    'message' => 'No expired stories found'
                ];
            }
            
            $deletedCount = 0;
            $deletedFiles = 0;
            
            foreach ($expiredStories as $story) {
                // Delete file first
                if (!empty($story['IMAGE_URL'])) {
                    if ($this->fileHandler->deleteFile($story['IMAGE_URL'])) {
                        $deletedFiles++;
                    }
                }
                
                // Delete from database
                if ($this->repository->deleteStory($story['STORIES_ID'])) {
                    $deletedCount++;
                }
            }
            
            return [
                'success' => true,
                'deleted_count' => $deletedCount,
                'deleted_files' => $deletedFiles,
                'message' => "Successfully cleaned up {$deletedCount} expired stories"
            ];
            
        } catch (Exception $e) {
            error_log("Cleanup expired stories error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to cleanup expired stories'
            ];
        }
    }
    
    /**
     * Get story statistics
     */
    public function getStatistics() {
        return $this->repository->getStoryStatistics();
    }
    
    /**
     * Validate story access permissions
     */
    public function canUserAccessStory($storyId, $userId) {
        $story = $this->repository->getStoryById($storyId);
        
        if (!$story) {
            return false;
        }
        
        // Owner can always access
        if ($story['USER_ID'] == $userId) {
            return true;
        }
        
        // Check visibility settings
        if ($story['VISIBILITY'] === 'public') {
            return true;
        }
        
        // Add friends-only logic here if needed
        if ($story['VISIBILITY'] === 'friends') {
            // For now, allow all users - implement friend checking logic as needed
            return true;
        }
        
        return false;
    }
}
