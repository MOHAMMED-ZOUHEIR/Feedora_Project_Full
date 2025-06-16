<?php
// Include the database connection script
require_once 'config/config.php';
// Include FIXED notification utilities
require_once 'notification_utils.php';
// Start the session to manage user data
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to the login page if not logged in
    header("Location: sign-in.php");
    exit();
}

// FIXED: Ensure user session data is current and accurate
$userId = $_SESSION['user_id'];

// FIXED: Refresh user session data from database to prevent wrong user display
try {
    $userStmt = $pdo->prepare("SELECT USER_ID, NAME, EMAIL, PROFILE_IMAGE FROM USERS WHERE USER_ID = ?");
    $userStmt->execute([$userId]);
    $currentUser = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$currentUser) {
        // User doesn't exist anymore, logout
        session_destroy();
        header("Location: sign-in.php");
        exit();
    }
    
    // FIXED: Update session with current user data to prevent wrong user display
    $_SESSION['user_id'] = $currentUser['USER_ID'];
    $_SESSION['user_name'] = $currentUser['NAME'];
    $_SESSION['user_email'] = $currentUser['EMAIL'];
    if ($currentUser['PROFILE_IMAGE']) {
        $_SESSION['profile_image'] = $currentUser['PROFILE_IMAGE'];
    }
    
    error_log("✅ Session refreshed for user: " . $currentUser['NAME'] . " (ID: " . $currentUser['USER_ID'] . ")");
    
} catch (PDOException $e) {
    error_log("❌ Error refreshing user session: " . $e->getMessage());
    // Don't redirect, just log the error
}

$message = '';
$postSuccess = false;

// Handle post submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $description = trim($_POST['description'] ?? '');
    $imageUrl = null;
    
    // Handle image upload if present
    if (isset($_FILES['post_image']) && $_FILES['post_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/posts/';
        
        // Create directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Generate unique filename
        $fileExtension = pathinfo($_FILES['post_image']['name'], PATHINFO_EXTENSION);
        $newFileName = uniqid('post_') . '.' . $fileExtension;
        $targetFilePath = $uploadDir . $newFileName;
        
        // Check if file is an image or video
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'video/mp4'];
        $fileType = $_FILES['post_image']['type'];
        $fileSize = $_FILES['post_image']['size'];
        
        // Set size limits based on file type
        $maxImageSize = 5 * 1024 * 1024; // 5MB for images
        $maxVideoSize = 10 * 1024 * 1024; // 10MB for videos
        
        if (in_array($fileType, $allowedTypes)) {
            // Check file size based on type
            $isVideo = ($fileType === 'video/mp4');
            $maxSize = $isVideo ? $maxVideoSize : $maxImageSize;
            
            if ($fileSize > $maxSize) {
                $sizeInMB = $maxSize / (1024 * 1024);
                $message = $isVideo ? "Video files must be less than {$sizeInMB}MB." : "Image files must be less than {$sizeInMB}MB.";
            } else {
                // For videos, check if we can compress them
                if ($isVideo) {
                    // First check if ffmpeg is available
                    exec('where ffmpeg', $ffmpegOutput, $ffmpegReturnCode);
                    $ffmpegAvailable = ($ffmpegReturnCode === 0);
                    
                    if ($ffmpegAvailable) {
                        // Temporary path for compressed video
                        $tempPath = $uploadDir . 'temp_' . $newFileName;
                        
                        // Try to compress the video
                        if (move_uploaded_file($_FILES['post_image']['tmp_name'], $tempPath)) {
                            // Command to compress video (adjust quality as needed)
                            $cmd = "ffmpeg -i \"$tempPath\" -c:v libx264 -crf 28 -preset faster -c:a aac -b:a 128k \"$targetFilePath\"";
                            exec($cmd, $output, $returnCode);
                            
                            if ($returnCode === 0 && file_exists($targetFilePath)) {
                                // Compression successful
                                unlink($tempPath); // Remove temporary file
                                $imageUrl = $targetFilePath;
                            } else {
                                // If compression fails, just use the original file
                                rename($tempPath, $targetFilePath);
                                $imageUrl = $targetFilePath;
                            }
                        } else {
                            $message = "Sorry, there was an error uploading your file.";
                        }
                    } else { // if ffmpeg is not available
                        // Just move the file without compression
                        if (move_uploaded_file($_FILES['post_image']['tmp_name'], $targetFilePath)) {
                            $imageUrl = $targetFilePath;
                        } else {
                            $message = "Sorry, there was an error uploading your file.";
                        }
                    }
                } else { // if not a video
                    // For images or if ffmpeg is not available
                    if (move_uploaded_file($_FILES['post_image']['tmp_name'], $targetFilePath)) {
                        $imageUrl = $targetFilePath;
                    } else {
                        $message = "Sorry, there was an error uploading your file.";
                    }
                }
            }
        } else {
            $message = "Only JPG, PNG, GIF, WEBP, and MP4 files are allowed.";
        }
    }
    
    // ENHANCED: Insert post into database and send professional notifications
    if (!empty($description) || $imageUrl !== null) {
        try {
            // Begin transaction to ensure post and notifications are created together
            $pdo->beginTransaction();
            
            error_log("🚀 Creating post for user ID: {$userId} with name: " . $_SESSION['user_name']);
            
            // Insert the post
            $stmt = $pdo->prepare("INSERT INTO POSTS (USER_ID, IMAGE_URL, DESCRIPTION, CREATED_AT) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$userId, $imageUrl, $description]);
            
            // Get the new post ID
            $postId = $pdo->lastInsertId();
            
            error_log("✅ Post created with ID: {$postId}");
            
            // ENHANCED: Send professional notifications to followers
            $userName = $_SESSION['user_name'] ?? 'Someone';
            
            // Get all followers
            $followersStmt = $pdo->prepare("SELECT FOLLOWER_ID FROM FOLLOWERS WHERE USER_ID = ?");
            $followersStmt->execute([$userId]);
            $followers = $followersStmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($followers)) {
                // Create professional notification content
                $notificationContent = "<strong>" . htmlspecialchars($userName) . "</strong> shared a new post";
                
                // Add preview of the post content if available
                if (!empty($description)) {
                    $previewText = strlen($description) > 50 ? substr($description, 0, 50) . '...' : $description;
                    $notificationContent .= ": " . htmlspecialchars($previewText);
                }
                
                // Batch insert notifications for better performance
                $placeholders = str_repeat('(?, ?, ?, ?, ?, NOW()),', count($followers));
                $placeholders = rtrim($placeholders, ',');
                
                $sql = "INSERT INTO NOTIFICATIONS (USER_ID, TARGET_USER_ID, NOTIFICATION_TYPE, CONTENT, RELATED_ID, CREATED_AT) 
                        VALUES $placeholders";
                $stmt = $pdo->prepare($sql);
                
                $params = [];
                foreach ($followers as $followerId) {
                    $params = array_merge($params, [
                        $userId,                    // USER_ID (who created the post)
                        $followerId,               // TARGET_USER_ID (who receives the notification)
                        'new_post',               // NOTIFICATION_TYPE
                        $notificationContent,     // CONTENT
                        $postId                   // RELATED_ID (post ID)
                    ]);
                }
                
                $result = $stmt->execute($params);
                
                if ($result) {
                    error_log("✅ Professional notifications sent to " . count($followers) . " followers for post {$postId}");
                    
                    // ENHANCED: Log detailed notification info for debugging
                    error_log("📊 Notification details:");
                    error_log("   - Post ID: {$postId}");
                    error_log("   - Author: {$userName} (ID: {$userId})");
                    error_log("   - Followers notified: " . count($followers));
                    error_log("   - Content preview: " . substr($notificationContent, 0, 100));
                    
                } else {
                    error_log("⚠️ Failed to send notifications for post {$postId}");
                }
            } else {
                error_log("ℹ️ No followers to notify for user {$userName} (ID: {$userId})");
            }
            
            // Commit the transaction
            $pdo->commit();
            
            $postSuccess = true;
            $_SESSION['post_message'] = "Your post has been published successfully and notifications sent to your followers!";
            $_SESSION['post_success'] = true;
            
            error_log("✅ Post creation and notification process completed successfully");
            
        } catch (PDOException $e) {
            // Rollback on error
            $pdo->rollback();
            error_log("❌ Post creation failed: " . $e->getMessage());
            $_SESSION['post_message'] = 'Error creating post: ' . $e->getMessage();
            $_SESSION['post_success'] = false;
        }
    } else {
        $_SESSION['post_message'] = "Please add a description or image to your post.";
        $_SESSION['post_success'] = false;
    }
    
    // FIXED: Add a small delay before redirect to ensure session is properly saved
    sleep(1);
    
    // Redirect back to dashboard
    header("Location: dashboard.php");
    exit();
}
?>