<?php
/**
 * Story Factory - Creates and manages story-related dependencies
 * Implements Factory Pattern for clean dependency injection
 */

require_once 'StoryManager.php';
require_once 'StoryService.php';
require_once 'StoryRepository.php';
require_once 'StoryFileHandler.php';

class StoryFactory {
    
    /**
     * Create StoryManager with all dependencies
     */
    public static function createStoryManager(PDO $pdo) {
        $repository = self::createStoryRepository($pdo);
        $fileHandler = self::createStoryFileHandler();
        $service = new StoryService($repository, $fileHandler);
        
        return new StoryManager($service, $repository, $fileHandler);
    }
    
    /**
     * Create StoryService with dependencies
     */
    public static function createStoryService(PDO $pdo) {
        $repository = self::createStoryRepository($pdo);
        $fileHandler = self::createStoryFileHandler();
        
        return new StoryService($repository, $fileHandler);
    }
    
    /**
     * Create StoryRepository
     */
    public static function createStoryRepository(PDO $pdo) {
        return new StoryRepository($pdo);
    }
    
    /**
     * Create StoryFileHandler
     */
    public static function createStoryFileHandler($uploadDir = 'uploads/stories/', $maxFileSize = 52428800) {
        return new StoryFileHandler($uploadDir, $maxFileSize);
    }
}
