<?php
// FIXED story.php - Proper story notification system

// Include the database connection script
require_once 'config/config.php';
require_once 'includes/StoryRepository.php';
require_once 'includes/StoryService.php';
require_once 'includes/StoryFileHandler.php';
require_once 'includes/StoryFactory.php';
require_once 'includes/StoryManager.php';
require_once 'notification_utils.php'; // FIXED: Include at the top

// Start the session to manage user data
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
  header("Location: sign-in.php");
  exit();
}

// Initialize story manager with clean architecture
$storyManager = StoryFactory::createStoryManager($pdo);
$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';
$profileImage = $_SESSION['profile_image'] ?? null;

// ENHANCED: Handle AJAX request for individual story view recording
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_individual_view') {
  header('Content-Type: application/json');

  $storyId = intval($_POST['story_id'] ?? 0);

  if ($storyId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid story ID']);
    exit();
  }

  $result = $storyManager->recordIndividualView($storyId, $userId);
  echo json_encode($result);
  exit();
}

// FIXED: Enhanced story submission with proper notification system
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
  $visibility = $_POST['visibility'] ?? 'public';

  // Check if files were uploaded
  if (!isset($_FILES['story_media']) || empty($_FILES['story_media']['name'])) {
    $_SESSION['story_message'] = "Please select at least one file to upload.";
    $_SESSION['story_success'] = false;
    header("Location: dashboard.php");
    exit();
  }

  // Handle upload using story manager
  $uploadResult = $storyManager->handleUpload($userId, $_FILES['story_media'], $visibility);

  // Set session messages
  $_SESSION['story_message'] = $uploadResult['message'];
  $_SESSION['story_success'] = $uploadResult['success'];

  // FIXED: Create notifications for successful story uploads
  if ($uploadResult['success'] && $uploadResult['uploaded_count'] > 0) {
    try {
      error_log("📸 FIXED: Starting story notification process for user ID: $userId");
      
      // Get user info for logging
      $userStmt = $pdo->prepare("SELECT NAME FROM USERS WHERE USER_ID = ?");
      $userStmt->execute([$userId]);
      $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
      $notificationUserName = $userData['NAME'] ?? 'Someone';
      
      error_log("📸 User $notificationUserName uploaded {$uploadResult['uploaded_count']} stories");

      // FIXED: Get the actual story IDs from the database (most recent stories)
      $storyStmt = $pdo->prepare("SELECT STORIES_ID FROM STORIES WHERE USER_ID = ? ORDER BY CREATED_AT DESC LIMIT ?");
      $storyStmt->execute([$userId, $uploadResult['uploaded_count']]);
      $recentStories = $storyStmt->fetchAll(PDO::FETCH_COLUMN);

      if (!empty($recentStories)) {
        error_log("📸 Found " . count($recentStories) . " recent story IDs: " . implode(', ', $recentStories));
        
        // FIXED: Notify followers for each uploaded story using the existing function
        foreach ($recentStories as $storyId) {
          $notificationResult = notifyFollowersNewStory($pdo, $storyId, $userId);
          
          if ($notificationResult) {
            error_log("✅ FIXED: Story notification sent successfully for story ID: $storyId");
          } else {
            error_log("❌ FIXED: Failed to send story notification for story ID: $storyId");
          }
        }
        
        error_log("✅ FIXED: Story notification process completed for user: $notificationUserName");
        
      } else {
        error_log("❌ FIXED: No recent stories found in database for user ID: $userId");
      }
      
    } catch (Exception $e) {
      error_log("❌ FIXED: Story notification error: " . $e->getMessage());
      error_log("   User ID: $userId, Upload count: " . ($uploadResult['uploaded_count'] ?? 0));
    }
  }

  header("Location: dashboard.php");
  exit();
}

// Handle story viewing with proper individual story loading
$currentStory = null;
$userStories = [];
$currentIndex = 0;
$viewCount = 0;
$viewers = [];
$isOwner = false;

if (isset($_GET['id']) || isset($_GET['user_id'])) {
  try {
    if (isset($_GET['id'])) {
      // View specific story
      $storyId = intval($_GET['id']);
      $viewResult = $storyManager->handleStoryView($storyId, $userId);

      if (!$viewResult['success']) {
        $_SESSION['story_message'] = $viewResult['message'];
        $_SESSION['story_success'] = false;
        header("Location: dashboard.php");
        exit();
      }

      $currentStory = $viewResult['story'];
      $userStories = $viewResult['user_stories'];
      $currentIndex = $viewResult['current_index'];
      
      // ENHANCED: Get story owner information for profile display
      $ownerStmt = $pdo->prepare("
        SELECT u.NAME, u.PROFILE_IMAGE, s.CREATED_AT, s.USER_ID
        FROM STORIES s 
        JOIN USERS u ON s.USER_ID = u.USER_ID 
        WHERE s.STORIES_ID = ?
      ");
      $ownerStmt->execute([$storyId]);
      $ownerInfo = $ownerStmt->fetch(PDO::FETCH_ASSOC);
      
      if ($ownerInfo) {
        $currentStory['NAME'] = $ownerInfo['NAME'];
        $currentStory['PROFILE_IMAGE'] = $ownerInfo['PROFILE_IMAGE'];
        $currentStory['CREATED_AT'] = $ownerInfo['CREATED_AT'];
      }
      
    } else {
      // View first story of a user
      $storyUserId = intval($_GET['user_id']);
      $storyService = StoryFactory::createStoryService($pdo);
      $userStories = $storyService->getUserStories($storyUserId);

      if (empty($userStories)) {
        header("Location: dashboard.php");
        exit();
      }

      $currentStory = $userStories[0];
      $currentIndex = 0;

      // ENHANCED: Get story owner information for profile display
      $ownerStmt = $pdo->prepare("
        SELECT u.NAME, u.PROFILE_IMAGE 
        FROM USERS u 
        WHERE u.USER_ID = ?
      ");
      $ownerStmt->execute([$storyUserId]);
      $ownerInfo = $ownerStmt->fetch(PDO::FETCH_ASSOC);
      
      if ($ownerInfo) {
        $currentStory['NAME'] = $ownerInfo['NAME'];
        $currentStory['PROFILE_IMAGE'] = $ownerInfo['PROFILE_IMAGE'];
      }

      // Record view for the first story
      $storyManager->recordIndividualView($currentStory['STORIES_ID'], $userId);
    }

    // Get viewers and view count for current story
    $isOwner = ($userId == $currentStory['USER_ID']);

    if ($isOwner) {
      $viewersResult = $storyManager->getStoryViewers($currentStory['STORIES_ID'], $userId);
      $viewers = $viewersResult['viewers'] ?? [];
      $viewCount = $viewersResult['view_count'] ?? 0;
    } else {
      $storyService = StoryFactory::createStoryService($pdo);
      $viewCount = $storyService->getStoryViewCount($currentStory['STORIES_ID']);
    }
  } catch (Exception $e) {
    error_log("Story viewing error: " . $e->getMessage());
    $_SESSION['story_message'] = 'Error viewing story: ' . $e->getMessage();
    $_SESSION['story_success'] = false;
    header("Location: dashboard.php");
    exit();
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Feedora - View Story">
  <meta name="theme-color" content="#ED5A2C">
  <title>Story - Feedora</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="fonts.css">
  <link rel="stylesheet" href="Home.css">
  <link rel="icon" href="images/Frame 1171277973.svg" type="image/svg+xml">
  <style>
    /* ENHANCED Professional Story Viewer Styles */
    body {
      margin: 0;
      padding: 0;
      background: linear-gradient(135deg, #000000, #1a1a1a, #000000);
      height: 100vh;
      overflow: hidden;
      font-family: 'Qurova', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      position: relative;
    }

    body::before {
      content: '';
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: radial-gradient(circle at center, rgba(237, 90, 44, 0.1) 0%, rgba(0, 0, 0, 0.8) 70%);
      z-index: 0;
      pointer-events: none;
    }

    .story-container {
      position: relative;
      width: 100vw;
      height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      background: transparent;
      z-index: 1;
    }

    .story-content {
      position: relative;
      width: 100%;
      height: 100%;
      z-index: 2;
      display: flex;
      justify-content: center;
      align-items: center;
      overflow: hidden;
    }

    .story-image {
      max-width: 100vw;
      max-height: 100vh;
      width: auto;
      height: auto;
      object-fit: contain;
      user-select: none;
      -webkit-user-drag: none;
      display: block;
      margin: 0 auto;
      box-shadow: 0 0 80px rgba(237, 90, 44, 0.3), 0 0 40px rgba(0, 0, 0, 0.8);
      border-radius: 20px;
      transition: all 0.3s ease;
      filter: brightness(1.05) contrast(1.02);
    }

    .story-video {
      max-width: 100vw;
      max-height: 100vh;
      width: auto;
      height: auto;
      object-fit: contain;
      display: block;
      margin: 0 auto;
      box-shadow: 0 0 80px rgba(237, 90, 44, 0.3), 0 0 40px rgba(0, 0, 0, 0.8);
      border-radius: 20px;
      transition: all 0.3s ease;
      filter: brightness(1.05) contrast(1.02);
    }

    /* ENHANCED Progress Bars with Glassmorphism */
    .story-progress-container {
      position: absolute;
      top: 25px;
      left: 25px;
      right: 25px;
      display: flex;
      gap: 8px;
      z-index: 100;
      backdrop-filter: blur(10px);
      background: rgba(255, 255, 255, 0.1);
      padding: 10px 15px;
      border-radius: 25px;
      border: 1px solid rgba(255, 255, 255, 0.2);
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    }

    .story-progress-bar {
      flex: 1;
      height: 4px;
      background-color: rgba(255, 255, 255, 0.3);
      border-radius: 4px;
      overflow: hidden;
      position: relative;
      box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.2);
    }

    .story-progress-fill {
      height: 100%;
      background: linear-gradient(90deg, #ED5A2C, #ff6b3d, #ED5A2C);
      width: 0%;
      transition: width 0.1s linear;
      border-radius: 4px;
      box-shadow: 0 0 10px rgba(237, 90, 44, 0.6);
      position: relative;
    }

    .story-progress-fill::after {
      content: '';
      position: absolute;
      top: 0;
      right: 0;
      width: 3px;
      height: 100%;
      background: rgba(255, 255, 255, 0.8);
      border-radius: 0 4px 4px 0;
      box-shadow: 0 0 5px rgba(255, 255, 255, 0.5);
    }

    .story-progress-bar.viewed .story-progress-fill {
      width: 100%;
      background: linear-gradient(90deg, #4CAF50, #66BB6A, #4CAF50);
    }

    .story-progress-bar.active .story-progress-fill {
      animation: progressGlow 2s infinite alternate;
    }

    @keyframes progressGlow {
      0% {
        box-shadow: 0 0 10px rgba(237, 90, 44, 0.6);
      }

      100% {
        box-shadow: 0 0 20px rgba(237, 90, 44, 1), 0 0 30px rgba(237, 90, 44, 0.8);
      }
    }

    /* NEW: User Profile Info in Top Left */
    .story-user-info {
      position: absolute;
      top: 70px;
      left: 25px;
      display: flex;
      align-items: center;
      gap: 12px;
      z-index: 100;
      backdrop-filter: blur(15px);
      background: rgba(0, 0, 0, 0.4);
      padding: 12px 16px;
      border-radius: 25px;
      border: 1px solid rgba(255, 255, 255, 0.1);
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
      transition: all 0.3s ease;
      max-width: 280px;
    }

    .story-user-info:hover {
      background: rgba(0, 0, 0, 0.6);
      transform: translateY(-2px);
      box-shadow: 0 12px 40px rgba(0, 0, 0, 0.7);
    }

    .story-user-avatar {
      position: relative;
    }

    .story-user-avatar img {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid rgba(237, 90, 44, 0.8);
      transition: all 0.3s ease;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    }

    .story-user-info:hover .story-user-avatar img {
      border-color: #ED5A2C;
      transform: scale(1.05);
      box-shadow: 0 6px 16px rgba(237, 90, 44, 0.4);
    }

    .story-user-details {
      flex: 1;
      min-width: 0;
    }

    .story-user-name {
      color: white;
      font-size: 16px;
      font-weight: 700;
      margin: 0 0 4px 0;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.8);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      letter-spacing: 0.3px;
    }

    .story-user-time {
      color: rgba(255, 255, 255, 0.85);
      font-size: 13px;
      font-weight: 500;
      margin: 0;
      display: flex;
      align-items: center;
      gap: 4px;
      text-shadow: 0 1px 2px rgba(0, 0, 0, 0.6);
    }

    .story-user-time::before {
      content: '🕐';
      font-size: 11px;
      opacity: 0.8;
    }

    /* Close Button in Top Right */
    .story-close {
      position: absolute;
      top: 70px;
      right: 25px;
      background: rgba(244, 67, 54, 0.2);
      border: 1px solid rgba(244, 67, 54, 0.3);
      color: white;
      font-size: 24px;
      cursor: pointer;
      padding: 10px;
      line-height: 1;
      border-radius: 50%;
      width: 45px;
      height: 45px;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.3s ease;
      backdrop-filter: blur(10px);
      z-index: 100;
    }

    .story-close:hover {
      background: rgba(244, 67, 54, 0.4);
      transform: scale(1.1) rotate(90deg);
      box-shadow: 0 5px 15px rgba(244, 67, 54, 0.5);
    }

    /* ENHANCED Navigation with Visual Feedback */
    .story-nav {
      position: absolute;
      top: 0;
      bottom: 0;
      width: 50%;
      z-index: 50;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.3s ease;
      user-select: none;
    }

    .story-nav::before {
      content: '';
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      width: 50px;
      height: 50px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.2);
      transition: all 0.3s ease;
      opacity: 0;
    }

    .story-nav:hover::before {
      opacity: 1;
      transform: translateY(-50%) scale(1.2);
      background: rgba(237, 90, 44, 0.3);
      border-color: rgba(237, 90, 44, 0.5);
    }

    .story-nav::after {
      content: '';
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      width: 0;
      height: 0;
      transition: all 0.3s ease;
      opacity: 0;
    }

    .story-nav-prev {
      left: 0;
    }

    .story-nav-prev::after {
      left: 20px;
      border-right: 15px solid rgba(255, 255, 255, 0.8);
      border-top: 10px solid transparent;
      border-bottom: 10px solid transparent;
    }

    .story-nav-next {
      right: 0;
    }

    .story-nav-next::after {
      right: 20px;
      border-left: 15px solid rgba(255, 255, 255, 0.8);
      border-top: 10px solid transparent;
      border-bottom: 10px solid transparent;
    }

    .story-nav:hover::after {
      opacity: 1;
    }

    /* NEW: Bottom Left Viewers Panel */
    .story-viewers-panel {
      position: absolute;
      bottom: 25px;
      left: 25px;
      backdrop-filter: blur(20px);
      background: rgba(0, 0, 0, 0.4);
      border-radius: 20px;
      border: 1px solid rgba(255, 255, 255, 0.1);
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
      padding: 20px;
      max-width: 350px;
      max-height: 300px;
      overflow: hidden;
      z-index: 100;
      transition: all 0.3s ease;
    }

    .story-viewers-panel:hover {
      background: rgba(0, 0, 0, 0.6);
      transform: translateY(-5px);
      box-shadow: 0 12px 40px rgba(0, 0, 0, 0.7);
    }

    .viewers-header {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 15px;
      padding-bottom: 12px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .viewers-icon {
      font-size: 20px;
      color: #ED5A2C;
    }

    .viewers-title {
      color: white;
      font-size: 16px;
      font-weight: 700;
      margin: 0;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
    }

    .viewers-count {
      color: rgba(255, 255, 255, 0.8);
      font-size: 14px;
      font-weight: 500;
      margin-left: auto;
    }

    .viewers-list {
      max-height: 200px;
      overflow-y: auto;
      scrollbar-width: thin;
      scrollbar-color: #ED5A2C transparent;
    }

    .viewers-list::-webkit-scrollbar {
      width: 4px;
    }

    .viewers-list::-webkit-scrollbar-thumb {
      background: linear-gradient(180deg, #ED5A2C, #ff6b3d);
      border-radius: 2px;
    }

    .viewer-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 8px 0;
      transition: all 0.3s ease;
    }

    .viewer-item:hover {
      background: rgba(237, 90, 44, 0.1);
      border-radius: 8px;
      padding-left: 8px;
      padding-right: 8px;
    }

    .viewer-avatar {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid rgba(237, 90, 44, 0.5);
      transition: all 0.3s ease;
    }

    .viewer-item:hover .viewer-avatar {
      border-color: #ED5A2C;
      transform: scale(1.1);
    }

    .viewer-info {
      flex: 1;
      min-width: 0;
    }

    .viewer-name {
      color: white;
      font-size: 14px;
      font-weight: 600;
      margin: 0 0 2px 0;
      text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .viewer-time {
      color: rgba(255, 255, 255, 0.7);
      font-size: 12px;
      margin: 0;
      font-weight: 500;
    }

    .no-viewers {
      text-align: center;
      padding: 20px;
      color: rgba(255, 255, 255, 0.6);
    }

    .no-viewers-icon {
      font-size: 32px;
      margin-bottom: 10px;
      opacity: 0.5;
    }

    .no-viewers-text {
      font-size: 14px;
      margin: 0 0 5px 0;
    }

    .no-viewers-subtext {
      font-size: 12px;
      margin: 0;
      opacity: 0.8;
    }

    /* ENHANCED Mobile Responsiveness */
    @media (max-width: 768px) {
      .story-progress-container {
        top: 15px;
        left: 15px;
        right: 15px;
        padding: 8px 12px;
      }

      .story-user-info {
        top: 60px;
        left: 15px;
        padding: 10px 14px;
        max-width: calc(100vw - 110px);
      }

      .story-user-avatar img {
        width: 38px;
        height: 38px;
      }

      .story-user-name {
        font-size: 15px;
      }

      .story-user-time {
        font-size: 12px;
      }

      .story-close {
        top: 15px;
        right: 15px;
        width: 40px;
        height: 40px;
        font-size: 20px;
      }

      .story-viewers-panel {
        bottom: 15px;
        left: 15px;
        right: 15px;
        max-width: none;
        max-height: 200px;
      }

      .story-nav::before {
        width: 40px;
        height: 40px;
      }
    }

    /* Loading States */
    .story-loading {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      color: white;
      font-size: 18px;
      text-align: center;
      z-index: 10;
    }

    .loading-spinner {
      width: 40px;
      height: 40px;
      border: 3px solid rgba(255, 255, 255, 0.3);
      border-top: 3px solid #ED5A2C;
      border-radius: 50%;
      animation: spin 1s linear infinite;
      margin: 0 auto 15px;
    }

    @keyframes spin {
      0% {
        transform: rotate(0deg);
      }

      100% {
        transform: rotate(360deg);
      }
    }

    /* Error States */
    .story-error {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      background: rgba(244, 67, 54, 0.9);
      color: white;
      padding: 20px 30px;
      border-radius: 15px;
      text-align: center;
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.2);
    }

    /* NEW: Volume Control Button */
    .volume-control {
      position: absolute;
      bottom: 20px;
      right: 20px;
      background: rgba(0, 0, 0, 0.6);
      border: none;
      color: white;
      padding: 10px;
      border-radius: 50%;
      cursor: pointer;
      z-index: 100;
      width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;
      backdrop-filter: blur(10px);
      transition: all 0.3s ease;
    }

    .volume-control:hover {
      background: rgba(0, 0, 0, 0.8);
      transform: scale(1.1);
    }

    @media (max-width: 768px) {
      .volume-control {
        bottom: 15px;
        right: 15px;
        width: 35px;
        height: 35px;
        font-size: 18px;
      }
    }
  </style>
</head>

<body>

  <?php if ($currentStory): ?>
    <div class="story-container">
      <!-- ENHANCED Progress Bars -->
      <div class="story-progress-container">
        <?php for ($i = 0; $i < count($userStories); $i++): ?>
          <div class="story-progress-bar <?php
                                          echo $i < $currentIndex ? 'viewed' : '';
                                          echo $i === $currentIndex ? ' active' : '';
                                          ?>" data-index="<?php echo $i; ?>">
            <div class="story-progress-fill"></div>
          </div>
        <?php endfor; ?>
      </div>

      <!-- NEW: User Profile Info in Top Left -->
      <div class="story-user-info">
        <div class="story-user-avatar">
          <img src="<?php echo htmlspecialchars($currentStory['PROFILE_IMAGE'] ?? 'images/default-profile.png'); ?>" 
               alt="<?php echo htmlspecialchars($currentStory['NAME'] ?? 'User'); ?>"
               onerror="this.src='images/default-profile.png'">
        </div>
        <div class="story-user-details">
          <div class="story-user-name"><?php echo htmlspecialchars($currentStory['NAME'] ?? 'User'); ?></div>
          <div class="story-user-time"><?php echo date('M j, g:i A', strtotime($currentStory['CREATED_AT'])); ?></div>
        </div>
      </div>

      <!-- Close Button -->
      <div class="story-close" onclick="closeStory()">×</div>

      <!-- Story Content -->
      <div class="story-content" id="mediaContainer">
        <?php
        $fileExtension = strtolower(pathinfo($currentStory['IMAGE_URL'], PATHINFO_EXTENSION));
        $videoExtensions = ['mp4', 'mov', 'webm', 'ogg', 'avi'];

        if (in_array($fileExtension, $videoExtensions)):
        ?>
          <video class="story-video" id="storyVideo" controls>
            <source src="<?php echo htmlspecialchars($currentStory['IMAGE_URL']); ?>" type="video/<?php echo $fileExtension; ?>">
            Your browser does not support the video tag.
          </video>
        <?php else: ?>
          <img src="<?php echo htmlspecialchars($currentStory['IMAGE_URL']); ?>"
            alt="Story" class="story-image" id="storyImage">
        <?php endif; ?>
      </div>

      <!-- ENHANCED Navigation -->
      <?php if ($currentIndex > 0): ?>
        <div class="story-nav story-nav-prev" onclick="previousStory()"></div>
      <?php endif; ?>

      <?php if ($currentIndex < count($userStories) - 1): ?>
        <div class="story-nav story-nav-next" onclick="nextStory()"></div>
      <?php endif; ?>

      <!-- NEW: Bottom Left Viewers Panel -->
      <?php if ($isOwner): ?>
        <div class="story-viewers-panel">
          <div class="viewers-header">
            <div class="viewers-icon">👁️</div>
            <h3 class="viewers-title">Viewers</h3>
            <div class="viewers-count"><?php echo $viewCount; ?></div>
          </div>
          <div class="viewers-list">
            <?php if (empty($viewers)): ?>
              <div class="no-viewers">
                <div class="no-viewers-icon">👁️</div>
                <p class="no-viewers-text">No viewers yet</p>
                <p class="no-viewers-subtext">Share your story to get more views!</p>
              </div>
            <?php else: ?>
              <?php foreach ($viewers as $viewer): ?>
                <div class="viewer-item">
                  <img src="<?php echo htmlspecialchars($viewer['PROFILE_IMAGE'] ?? 'images/default-profile.png'); ?>"
                    alt="Viewer" class="viewer-avatar">
                  <div class="viewer-info">
                    <div class="viewer-name"><?php echo htmlspecialchars($viewer['NAME']); ?></div>
                    <div class="viewer-time"><?php echo date('M j, g:i A', strtotime($viewer['VIEWED_AT'])); ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- Loading State -->
      <div class="story-loading" id="storyLoading" style="display: none;">
        <div class="loading-spinner"></div>
        <p>Loading story...</p>
      </div>
    </div>

    <script>
      // ENHANCED Story JavaScript with Professional Video Sync
      const stories = <?php echo json_encode($userStories); ?>;
      let currentIndex = <?php echo $currentIndex; ?>;
      const userId = <?php echo $userId; ?>;

      // ENHANCED Progress tracking variables
      let progressInterval;
      let isVideoPaused = false;
      let storyStartTime;
      let videoDuration = 0;
      let isVideo = false;

      // Initialize story on load
      document.addEventListener('DOMContentLoaded', function() {
        console.log('🎬 Enhanced story viewer initialized');
        initializeStory();
      });

      // ENHANCED: Initialize current story with proper media handling
      function initializeStory() {
        const video = document.getElementById('storyVideo');
        const image = document.getElementById('storyImage');

        if (video) {
          isVideo = true;
          initializeVideoStory(video);
        } else if (image) {
          isVideo = false;
          initializeImageStory();
        }

        // Record view for current story
        recordStoryView(<?php echo $currentStory['STORIES_ID']; ?>);

        // Set story start time
        storyStartTime = Date.now();
      }

      // ENHANCED: Initialize video story with synchronized progress
      function initializeVideoStory(video) {
        console.log('🎥 Initializing video story with sync');

        // Show loading
        showLoading(true);

        // Add volume control
        video.volume = 0.5; // Set default volume to 50%

        // Add volume control button
        const volumeBtn = document.createElement('button');
        volumeBtn.className = 'volume-control';
        volumeBtn.innerHTML = '🔊';
        volumeBtn.onclick = function(e) {
          e.stopPropagation();
          if (video.muted) {
            video.muted = false;
            this.innerHTML = '🔊';
          } else {
            video.muted = true;
            this.innerHTML = '🔈';
          }
        };
        document.querySelector('.story-content').appendChild(volumeBtn);

        video.addEventListener('loadedmetadata', function() {
          videoDuration = video.duration * 1000; // Convert to milliseconds
          console.log(`📹 Video duration: ${videoDuration}ms`);
          showLoading(false);

          // Auto-play video
          video.play().then(() => {
            startVideoProgress();
          }).catch(error => {
            console.error('Video autoplay failed:', error);
            // Fallback to manual play
            showPlayButton();
          });
        });

        video.addEventListener('ended', function() {
          console.log('🎬 Video ended, moving to next story');
          setTimeout(() => {
            nextStory();
          }, 500);
        });

        video.addEventListener('error', function(e) {
          console.error('Video error:', e);
          showError('Error loading video');
        });

        // Handle video pause/play
        video.addEventListener('pause', function() {
          isVideoPaused = true;
          if (progressInterval) {
            clearInterval(progressInterval);
          }
        });

        video.addEventListener('play', function() {
          isVideoPaused = false;
          startVideoProgress();
        });
      }

      // ENHANCED: Initialize image story with better timing
      function initializeImageStory() {
        console.log('🖼️ Initializing image story');

        const image = document.getElementById('storyImage');

        if (image.complete) {
          showLoading(false);
          startImageProgress();
        } else {
          image.addEventListener('load', function() {
            showLoading(false);
            startImageProgress();
          });

          image.addEventListener('error', function() {
            showError('Error loading image');
          });
        }
      }

      // ENHANCED: Video progress synchronized with actual video time
      function startVideoProgress() {
        if (progressInterval) {
          clearInterval(progressInterval);
        }

        const video = document.getElementById('storyVideo');
        const currentProgressBar = document.querySelector(`.story-progress-bar[data-index="${currentIndex}"] .story-progress-fill`);

        if (!video || !currentProgressBar) return;

        progressInterval = setInterval(() => {
          if (isVideoPaused) return;

          const currentTime = video.currentTime * 1000; // Convert to milliseconds
          const progress = (currentTime / videoDuration) * 100;

          if (progress >= 100) {
            clearInterval(progressInterval);
            currentProgressBar.style.width = '100%';
            setTimeout(() => {
              nextStory();
            }, 200);
          } else {
            currentProgressBar.style.width = progress + '%';
          }
        }, 50); // Update every 50ms for smooth animation
      }

      // ENHANCED: Image progress with better timing
      function startImageProgress() {
        if (progressInterval) {
          clearInterval(progressInterval);
        }

        let progress = 0;
        const duration = 5000; // 5 seconds for images
        const interval = 50; // Update every 50ms
        const increment = (interval / duration) * 100;

        progressInterval = setInterval(() => {
          progress += increment;

          if (progress >= 100) {
            clearInterval(progressInterval);
            const currentProgressBar = document.querySelector(`.story-progress-bar[data-index="${currentIndex}"] .story-progress-fill`);
            if (currentProgressBar) {
              currentProgressBar.style.width = '100%';
            }
            setTimeout(() => {
              nextStory();
            }, 200);
          } else {
            const currentProgressBar = document.querySelector(`.story-progress-bar[data-index="${currentIndex}"] .story-progress-fill`);
            if (currentProgressBar) {
              currentProgressBar.style.width = progress + '%';
            }
          }
        }, interval);
      }

      // ENHANCED: Navigation functions with better transitions
      function nextStory() {
        if (currentIndex < stories.length - 1) {
          console.log('➡️ Moving to next story');
          showLoading(true);
          currentIndex++;
          navigateToStory(stories[currentIndex].STORIES_ID);
        } else {
          console.log('🏁 Reached end of stories');
          closeStory();
        }
      }

      function previousStory() {
        if (currentIndex > 0) {
          console.log('⬅️ Moving to previous story');
          showLoading(true);
          currentIndex--;
          navigateToStory(stories[currentIndex].STORIES_ID);
        }
      }

      function navigateToStory(storyId) {
        // Clear current progress
        if (progressInterval) {
          clearInterval(progressInterval);
        }

        // Smooth transition
        document.querySelector('.story-content').style.opacity = '0.5';

        setTimeout(() => {
          window.location.href = `story.php?id=${storyId}`;
        }, 150);
      }

      function closeStory() {
        console.log('❌ Closing story viewer');

        // Clear progress
        if (progressInterval) {
          clearInterval(progressInterval);
        }

        // Fade out animation
        document.querySelector('.story-container').style.animation = 'fadeOut 0.3s ease forwards';

        setTimeout(() => {
          window.location.href = 'dashboard.php';
        }, 300);
      }

      // ENHANCED: Record story view with better error handling
      function recordStoryView(storyId) {
        if (!storyId) return;

        fetch('story.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=record_individual_view&story_id=${storyId}`
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              console.log('✅ Story view recorded');
            } else {
              console.warn('⚠️ Failed to record view:', data.error);
            }
          })
          .catch(error => {
            console.error('❌ Error recording view:', error);
          });
      }

      // ENHANCED: Loading and error states
      function showLoading(show) {
        const loading = document.getElementById('storyLoading');
        if (loading) {
          loading.style.display = show ? 'block' : 'none';
        }
      }

      function showError(message) {
        const container = document.querySelector('.story-container');
        container.innerHTML = `
        <div class="story-error">
            <h3>⚠️ ${message}</h3>
            <p>Please try again or go back to dashboard</p>
            <button onclick="closeStory()" style="background: white; color: #f44336; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin-top: 15px;">
                Back to Dashboard
            </button>
        </div>
    `;
      }

      function showPlayButton() {
        const container = document.querySelector('.story-content');
        const playBtn = document.createElement('div');
        playBtn.innerHTML = '▶️';
        playBtn.style.cssText = `
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-size: 64px;
        cursor: pointer;
        background: rgba(0,0,0,0.7);
        border-radius: 50%;
        width: 100px;
        height: 100px;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 100;
    `;

        playBtn.onclick = function() {
          const video = document.getElementById('storyVideo');
          if (video) {
            video.play();
            this.remove();
          }
        };

        container.appendChild(playBtn);
      }

      // ENHANCED: Keyboard navigation
      document.addEventListener('keydown', function(e) {
        switch (e.key) {
          case 'ArrowRight':
          case ' ':
            e.preventDefault();
            nextStory();
            break;
          case 'ArrowLeft':
            e.preventDefault();
            previousStory();
            break;
          case 'Escape':
            e.preventDefault();
            closeStory();
            break;
        }
      });

      // ENHANCED: Touch/swipe navigation for mobile
      let startX = 0;
      let endX = 0;
      let startY = 0;
      let endY = 0;

      document.addEventListener('touchstart', function(e) {
        startX = e.changedTouches[0].screenX;
        startY = e.changedTouches[0].screenY;
      });

      document.addEventListener('touchend', function(e) {
        endX = e.changedTouches[0].screenX;
        endY = e.changedTouches[0].screenY;
        handleSwipe();
      });

      function handleSwipe() {
        const threshold = 50;
        const diffX = startX - endX;
        const diffY = startY - endY;

        // Only handle horizontal swipes (ignore vertical scrolling)
        if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > threshold) {
          if (diffX > 0) {
            // Swipe left - next story
            nextStory();
          } else {
            // Swipe right - previous story
            previousStory();
          }
        }
      }

      // Add CSS animations
      const style = document.createElement('style');
      style.textContent = `
    @keyframes fadeOut {
        from { opacity: 1; }
        to { opacity: 0; }
    }
`;
      document.head.appendChild(style);

      console.log('✅ FIXED: Enhanced Story Viewer with Proper Notifications System Loaded!');
    </script>

  <?php else: ?>
    <div style="display: flex; justify-content: center; align-items: center; height: 100vh; background: linear-gradient(135deg, #000, #1a1a1a); color: white;">
      <div style="text-align: center; backdrop-filter: blur(10px); background: rgba(255,255,255,0.1); padding: 40px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.2);">
        <div style="font-size: 64px; margin-bottom: 20px;">📱</div>
        <h2 style="margin-bottom: 15px;">Story not found</h2>
        <p style="margin-bottom: 25px; opacity: 0.8;">The story you're looking for doesn't exist or has expired.</p>
        <a href="dashboard.php" style="color: #ED5A2C; text-decoration: none; background: rgba(237,90,44,0.2); padding: 12px 24px; border-radius: 25px; border: 1px solid rgba(237,90,44,0.3); transition: all 0.3s ease;" onmouseover="this.style.background='rgba(237,90,44,0.3)'" onmouseout="this.style.background='rgba(237,90,44,0.2)'">
          ← Back to Dashboard
        </a>
      </div>
    </div>
  <?php endif; ?>

</body>

</html>