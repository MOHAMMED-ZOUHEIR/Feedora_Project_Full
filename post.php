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
    
    // Insert post into database if description or image is provided
    if (!empty($description) || $imageUrl !== null) {
        try {
            // Begin transaction to ensure post and notifications are created together
            $pdo->beginTransaction();
            
            // Insert the post
            $stmt = $pdo->prepare("INSERT INTO POSTS (USER_ID, IMAGE_URL, DESCRIPTION) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $imageUrl, $description]);
            
            // Get the new post ID
            $postId = $pdo->lastInsertId();
            
            // Get user name for notification content
            $userStmt = $pdo->prepare("SELECT NAME FROM USERS WHERE USER_ID = ?");
            $userStmt->execute([$userId]);
            $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
            $userName = $userData['NAME'] ?? 'Someone';
            
            // Create notification content with proper formatting
            $notificationContent = "<strong>" . htmlspecialchars($userName) . "</strong> shared a new post";
            
            // Get all users except the current user to notify them
            $usersStmt = $pdo->prepare("SELECT USER_ID FROM USERS WHERE USER_ID != ?");
            $usersStmt->execute([$userId]);
            $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Create notifications for all users
            $notifyStmt = $pdo->prepare("INSERT INTO NOTIFICATIONS (USER_ID, TARGET_USER_ID, NOTIFICATION_TYPE, CONTENT, RELATED_ID, CREATED_AT) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
            
            foreach ($users as $user) {
                $notifyStmt->execute([
                    $userId,                // User who created the post
                    $user['USER_ID'],      // User who will receive the notification
                    'new_post',            // Notification type
                    $notificationContent,  // Content of notification
                    $postId                // Related post ID
                ]);
            }
            
            // Commit the transaction
            $pdo->commit();
            
            $postSuccess = true;
            $_SESSION['post_message'] = "Your post has been published successfully!";
            $_SESSION['post_success'] = true;
        } catch (PDOException $e) {
            $_SESSION['post_message'] = 'Error creating post: ' . $e->getMessage();
            $_SESSION['post_success'] = false;
        }
    } else {
        $_SESSION['post_message'] = "Please add a description or image to your post.";
        $_SESSION['post_success'] = false;
    }
    
    // Redirect back to dashboard
    header("Location: dashboard.php");
    exit();
}
?>