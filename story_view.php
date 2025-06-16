<?php
// FIXED story_view.php - Individual story view tracking endpoint

// Include the database connection script
require_once 'config/config.php';
require_once 'includes/StoryRepository.php';
require_once 'includes/StoryService.php';
require_once 'includes/StoryFileHandler.php';
require_once 'includes/StoryFactory.php';
require_once 'includes/StoryManager.php';

// Start the session
session_start();

// Set JSON response header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

// Get POST data
$storyId = intval($_POST['story_id'] ?? 0);
$userId = $_SESSION['user_id'];

// Validate story ID
if ($storyId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid story ID']);
    exit();
}

try {
    // Initialize story manager
    $storyManager = StoryFactory::createStoryManager($pdo);
    
    // FIXED: Record view for specific story only
    $result = $storyManager->recordIndividualView($storyId, $userId);
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Story view recording error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'Failed to record story view: ' . $e->getMessage()
    ]);
}
?>
