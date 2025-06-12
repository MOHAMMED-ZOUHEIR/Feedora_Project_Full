<?php
// Include the database connection script
require_once 'config/config.php';
// Start the session to manage user data
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to the login page if not logged in
    header("Location: sign-in.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';
$profileImage = $_SESSION['profile_image'] ?? null;
$message = '';
$storySuccess = false;

// Handle story submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $imageUrl = null;
    $visibility = $_POST['visibility'] ?? 'public';
    
    // Handle image/video upload if present
    if (isset($_FILES['story_media']) && $_FILES['story_media']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/stories/';
        
        // Create directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Generate unique filename
        $fileExtension = pathinfo($_FILES['story_media']['name'], PATHINFO_EXTENSION);
        $newFileName = 'story_' . $userId . '_' . time() . '.' . $fileExtension;
        $targetFilePath = $uploadDir . $newFileName;
        
        // Check if file is an image or video
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'video/mp4', 'video/quicktime'];
        $fileType = $_FILES['story_media']['type'];
        
        if (in_array($fileType, $allowedTypes)) {
            // Move uploaded file to target directory
            if (move_uploaded_file($_FILES['story_media']['tmp_name'], $targetFilePath)) {
                $imageUrl = $targetFilePath;
            } else {
                $message = "Sorry, there was an error uploading your file.";
            }
        } else {
            $message = "Only JPG, PNG, GIF, WEBP, MP4, and MOV files are allowed.";
        }
    } else {
        $message = "Please select a media file for your story.";
    }
    
    // Insert story into database if media is uploaded successfully
    if ($imageUrl !== null) {
        try {
            $pdo->beginTransaction();
            
            // Insert the story
            $stmt = $pdo->prepare("INSERT INTO STORIES (USER_ID, IMAGE_URL, VISIBILITY, EXPIRES_AT) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))");
            $stmt->execute([$userId, $imageUrl, $visibility]);
            
            // Get the new story ID
            $storyId = $pdo->lastInsertId();
            
            // Get user name for notification content
            $userStmt = $pdo->prepare("SELECT NAME FROM USERS WHERE USER_ID = ?");
            $userStmt->execute([$userId]);
            $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
            $userName = $userData['NAME'] ?? 'Someone';
            
            // Create notification content
            $notificationContent = "<strong>" . htmlspecialchars($userName) . "</strong> added a new story";
            
            // Get all users except the current user to notify them
            $usersStmt = $pdo->prepare("SELECT USER_ID FROM USERS WHERE USER_ID != ?");
            $usersStmt->execute([$userId]);
            $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Create notifications for all users
            $notifyStmt = $pdo->prepare("INSERT INTO NOTIFICATIONS (USER_ID, TARGET_USER_ID, NOTIFICATION_TYPE, CONTENT, RELATED_ID, CREATED_AT) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
            
            foreach ($users as $user) {
                $notifyStmt->execute([
                    $userId,                // User who created the story
                    $user['USER_ID'],      // User who will receive the notification
                    'new_story',           // Notification type
                    $notificationContent,  // Content of notification
                    $storyId               // Related story ID
                ]);
            }
            
            // Commit the transaction
            $pdo->commit();
            
            $storySuccess = true;
            $_SESSION['story_message'] = "Your story has been published successfully!";
            $_SESSION['story_success'] = true;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['story_message'] = 'Error creating story: ' . $e->getMessage();
            $_SESSION['story_success'] = false;
        }
    } else {
        $_SESSION['story_message'] = $message;
        $_SESSION['story_success'] = false;
    }
    
    // Redirect back to dashboard
    header("Location: dashboard.php");
    exit();
}

// View a specific story
if (isset($_GET['id'])) {
    $storyId = $_GET['id'];
    
    try {
        // Get the story details
        $stmt = $pdo->prepare(
            "SELECT s.*, u.NAME, u.PROFILE_IMAGE 
            FROM STORIES s 
            JOIN USERS u ON s.USER_ID = u.USER_ID 
            WHERE s.STORIES_ID = ? AND s.EXPIRES_AT > NOW()"
        );
        $stmt->execute([$storyId]);
        $story = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$story) {
            // Story not found or expired
            header("Location: dashboard.php");
            exit();
        }
        
        // Record that this user has viewed the story
        $viewStmt = $pdo->prepare("INSERT INTO STORY_VIEWS (STORY_ID, VIEWER_ID, VIEWED_AT) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE VIEWED_AT = NOW()");
        $viewStmt->execute([$storyId, $userId]);
        
        // Get view count for this story
        $viewCountStmt = $pdo->prepare("SELECT COUNT(*) as view_count FROM STORY_VIEWS WHERE STORY_ID = ?");
        $viewCountStmt->execute([$storyId]);
        $viewCount = $viewCountStmt->fetch(PDO::FETCH_ASSOC)['view_count'];
        
        // Check if the current user is the story owner
        $isOwner = ($userId == $story['USER_ID']);
        
        // If the user is the owner, get the list of viewers with their details
        if ($isOwner) {
            $viewersStmt = $pdo->prepare(
                "SELECT u.USER_ID, u.NAME, u.PROFILE_IMAGE, sv.VIEWED_AT 
                FROM STORY_VIEWS sv 
                JOIN USERS u ON sv.VIEWER_ID = u.USER_ID 
                WHERE sv.STORY_ID = ? 
                ORDER BY sv.VIEWED_AT DESC"
            );
            $viewersStmt->execute([$storyId]);
            $viewers = $viewersStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
    } catch (PDOException $e) {
        // Handle error
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
  <!-- Favicon -->
  <link rel="icon" href="images/Frame 1171277973.svg" type="image/svg+xml">
  <style>
    /* Story viewer styles */
    body {
      margin: 0;
      padding: 0;
      background-color: #000;
      height: 100vh;
      overflow: hidden;
    }
    
    .story-container {
      position: relative;
      width: 100%;
      height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
    }
    
    .story-content {
      position: relative;
      max-width: 100%;
      max-height: 100vh;
      z-index: 1;
    }
    
    .story-image {
      max-width: 100%;
      max-height: 90vh;
      object-fit: contain;
    }
    
    .story-video {
      max-width: 100%;
      max-height: 90vh;
    }
    
    .story-header {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      display: flex;
      align-items: center;
      padding: 15px;
      background: linear-gradient(to bottom, rgba(0,0,0,0.7) 0%, rgba(0,0,0,0) 100%);
      z-index: 2;
    }
    
    .story-user-info {
      display: flex;
      align-items: center;
    }
    
    .story-user-pic {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid #ED5A2C;
    }
    
    .story-user-name {
      color: white;
      margin-left: 10px;
      font-weight: 600;
    }
    
    .story-time {
      color: rgba(255,255,255,0.8);
      font-size: 14px;
      margin-left: 10px;
    }
    
    .story-close {
      margin-left: auto;
      color: white;
      background: none;
      border: none;
      font-size: 24px;
      cursor: pointer;
    }
    
    .story-progress {
      position: absolute;
      top: 10px;
      left: 0;
      right: 0;
      height: 3px;
      background-color: rgba(255,255,255,0.3);
      z-index: 3;
    }
    
    .story-progress-bar {
      height: 100%;
      background-color: #ED5A2C;
      width: 0%;
      transition: width 0.1s linear;
    }
    
    .story-navigation {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      display: flex;
      z-index: 2;
    }
    
    .story-prev, .story-next {
      flex: 1;
      cursor: pointer;
    }
    
    /* Story views counter */
    .story-views {
      position: absolute;
      bottom: 20px;
      left: 20px;
      display: flex;
      align-items: center;
      gap: 5px;
      color: white;
      background-color: rgba(0,0,0,0.5);
      padding: 5px 10px;
      border-radius: 20px;
      font-size: 14px;
      z-index: 3;
      cursor: pointer;
    }
    
    .story-views svg {
      color: white;
    }
    
    /* Story viewers panel */
    .story-viewers-panel {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      background-color: white;
      border-top-left-radius: 15px;
      border-top-right-radius: 15px;
      max-height: 60vh;
      transform: translateY(100%);
      transition: transform 0.3s ease;
      z-index: 5;
      box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
      overflow: hidden;
    }
    
    .story-viewers-panel.active {
      transform: translateY(0);
    }
    
    .viewers-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 15px 20px;
      border-bottom: 1px solid #eee;
    }
    
    .viewers-header h3 {
      margin: 0;
      font-size: 16px;
      color: #333;
    }
    
    .viewers-count {
      font-size: 14px;
      color: #666;
    }
    
    .viewers-list {
      max-height: calc(60vh - 50px);
      overflow-y: auto;
      padding: 10px 0;
    }
    
    .viewer-item {
      display: flex;
      align-items: center;
      padding: 10px 20px;
      transition: background-color 0.2s;
    }
    
    .viewer-item:hover {
      background-color: #f9f9f9;
    }
    
    .viewer-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      overflow: hidden;
      margin-right: 15px;
      flex-shrink: 0;
    }
    
    .viewer-avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    
    .viewer-info {
      display: flex;
      flex-direction: column;
    }
    
    .viewer-name {
      font-weight: 500;
      color: #333;
      margin-bottom: 3px;
    }
    
    .viewer-time {
      font-size: 12px;
      color: #888;
    }
    
    .no-viewers {
      text-align: center;
      padding: 20px;
      color: #888;
      font-style: italic;
    }
  </style>
</head>
<body>
  <?php if (isset($story)): ?>
  <div class="story-container">
    <div class="story-progress">
      <div class="story-progress-bar" id="progressBar"></div>
    </div>
    
    <div class="story-header">
      <div class="story-user-info">
        <img src="<?php echo htmlspecialchars($story['PROFILE_IMAGE'] ?? 'images/default-profile.png'); ?>" alt="User" class="story-user-pic">
        <span class="story-user-name"><?php echo htmlspecialchars($story['NAME']); ?></span>
        <?php 
        // Format the story timestamp to show the exact time from database
        $storyTime = new DateTime($story['CREATED_AT']);
        $formattedTime = $storyTime->format('M d, Y \a\t g:i A'); // e.g., May 29, 2025 at 5:30 PM
        ?>
        <span class="story-time"><?php echo $formattedTime; ?></span>
      </div>
      <a href="dashboard.php" class="story-close">×</a>
    </div>
    
    <div class="story-content">
      <?php 
      $fileExtension = strtolower(pathinfo($story['IMAGE_URL'], PATHINFO_EXTENSION));
      $isVideo = in_array($fileExtension, ['mp4', 'mov', 'webm', 'ogg']);
      
      if ($isVideo): 
      ?>
      <video class="story-video" controls autoplay>
        <source src="<?php echo htmlspecialchars($story['IMAGE_URL']); ?>" type="video/<?php echo $fileExtension; ?>">
        Your browser does not support the video tag.
      </video>
      <?php else: ?>
      <img src="<?php echo htmlspecialchars($story['IMAGE_URL']); ?>" alt="Story" class="story-image">
      <?php endif; ?>
    </div>
    
    <div class="story-navigation">
      <div class="story-prev" id="prevStory"></div>
      <div class="story-next" id="nextStory"></div>
    </div>
    
    <!-- Story views counter (only visible to story owner) -->
    <?php if (isset($isOwner) && $isOwner): ?>
    <div class="story-views">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
        <circle cx="12" cy="12" r="3"></circle>
      </svg>
      <span><?php echo $viewCount; ?></span>
    </div>
    <?php endif; ?>
    
    <?php if (isset($isOwner) && $isOwner): ?>
    <!-- Story viewers panel (only visible to story owner) -->
    <div class="story-viewers-panel">
      <div class="viewers-header">
        <h3>Viewers</h3>
        <span class="viewers-count"><?php echo $viewCount; ?> <?php echo $viewCount == 1 ? 'person' : 'people'; ?></span>
      </div>
      <div class="viewers-list">
        <?php if (isset($viewers) && count($viewers) > 0): ?>
          <?php foreach ($viewers as $viewer): ?>
            <div class="viewer-item">
              <div class="viewer-avatar">
                <img src="<?php echo !empty($viewer['PROFILE_IMAGE']) ? htmlspecialchars($viewer['PROFILE_IMAGE']) : 'images/default-profile.png'; ?>" alt="<?php echo htmlspecialchars($viewer['NAME']); ?>">
              </div>
              <div class="viewer-info">
                <span class="viewer-name"><?php echo htmlspecialchars($viewer['NAME']); ?></span>
                <?php 
                $viewTime = new DateTime($viewer['VIEWED_AT']);
                $formattedViewTime = $viewTime->format('M d, Y \a\t g:i A'); // e.g., May 29, 2025 at 5:30 PM
                ?>
                <span class="viewer-time"><?php echo $formattedViewTime; ?></span>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="no-viewers">No one has viewed your story yet</div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
  
  <script>
    // Auto-progress the story
    const progressBar = document.getElementById('progressBar');
    const storyDuration = <?php echo $isVideo ? '0' : '5000'; ?>; // 5 seconds for images, videos control their own timing
    let progress = 0;
    let progressInterval;
    
    if (!<?php echo $isVideo ? 'true' : 'false'; ?>) {
      // Only auto-progress for images
      progressInterval = setInterval(() => {
        progress += 0.1;
        progressBar.style.width = `${progress}%`;
        
        if (progress >= 100) {
          clearInterval(progressInterval);
          window.location.href = 'dashboard.php';
        }
      }, storyDuration / 1000);
    }
    
    // Navigation
    document.getElementById('nextStory').addEventListener('click', function() {
      clearInterval(progressInterval);
      window.location.href = 'dashboard.php';
    });
    
    document.getElementById('prevStory').addEventListener('click', function() {
      clearInterval(progressInterval);
      window.location.href = 'dashboard.php';
    });
    
    // Story viewers panel toggle (only for story owner)
    <?php if (isset($isOwner) && $isOwner): ?>
    const storyViews = document.querySelector('.story-views');
    const viewersPanel = document.querySelector('.story-viewers-panel');
    
    if (storyViews && viewersPanel) {
      // Toggle viewers panel when clicking on view count
      storyViews.addEventListener('click', function(e) {
        e.stopPropagation(); // Prevent click from reaching navigation
        viewersPanel.classList.toggle('active');
      });
      
      // Close panel when clicking elsewhere
      document.addEventListener('click', function(e) {
        if (viewersPanel.classList.contains('active') && 
            !viewersPanel.contains(e.target) && 
            e.target !== storyViews) {
          viewersPanel.classList.remove('active');
        }
      });
      
      // Prevent story navigation when clicking on viewers panel
      viewersPanel.addEventListener('click', function(e) {
        e.stopPropagation();
      });
    }
    <?php endif; ?>
  </script>
  <?php endif; ?>
</body>
</html>