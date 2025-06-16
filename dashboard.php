<?php
// Include the database connection script
require_once 'config/config.php';
// Include the story cleanup script
require_once 'includes/story_cleanup.php';

require_once 'notification_utils.php';

// Start the session to manage user data
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to the login page if not logged in
    header("Location: sign-in.php");
    exit();
}

// Clean up expired stories (older than 24 hours)
// This runs silently in the background when a user visits the dashboard
cleanupExpiredStories($pdo);

// Handle AJAX requests for post actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];

    // Edit post
    if ($_POST['action'] === 'edit_post' && isset($_POST['edit_post_id'])) {
        $postId = $_POST['edit_post_id'];
        $description = trim($_POST['edit_description'] ?? '');
        $existingImage = $_POST['existing_image'] ?? null;
        $userId = $_SESSION['user_id'];

        try {
            // Check if the post belongs to the current user
            $checkStmt = $pdo->prepare("SELECT USER_ID FROM POSTS WHERE POSTS_ID = ?");
            $checkStmt->execute([$postId]);
            $post = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$post || $post['USER_ID'] != $userId) {
                $response['message'] = 'You do not have permission to edit this post.';
                echo json_encode($response);
                exit();
            }

            // Handle image upload if a new image is provided
            $imageUrl = $existingImage;
            if (isset($_FILES['edit_post_image']) && $_FILES['edit_post_image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/posts/';

                // Create directory if it doesn't exist
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                // Generate unique filename
                $fileExtension = pathinfo($_FILES['edit_post_image']['name'], PATHINFO_EXTENSION);
                $newFileName = uniqid('post_') . '.' . $fileExtension;
                $targetFilePath = $uploadDir . $newFileName;

                // Check if file is an image or video
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'video/mp4', 'video/webm', 'video/ogg'];
                $fileType = $_FILES['edit_post_image']['type'];

                if (in_array($fileType, $allowedTypes)) {
                    // Move uploaded file to target directory
                    if (move_uploaded_file($_FILES['edit_post_image']['tmp_name'], $targetFilePath)) {
                        $imageUrl = $targetFilePath;
                    } else {
                        $response['message'] = "Sorry, there was an error uploading your file.";
                        echo json_encode($response);
                        exit();
                    }
                } else {
                    $response['message'] = "Only JPG, PNG, GIF, WEBP, and MP4 video files are allowed.";
                    echo json_encode($response);
                    exit();
                }
            }

            // Update post in database
            $stmt = $pdo->prepare("UPDATE POSTS SET DESCRIPTION = ?, IMAGE_URL = ? WHERE POSTS_ID = ? AND USER_ID = ?");
            $stmt->execute([$description, $imageUrl, $postId, $userId]);

            $response['success'] = true;
            $response['message'] = 'Post updated successfully!';
        } catch (PDOException $e) {
            $response['message'] = 'Error updating post: ' . $e->getMessage();
        }

        echo json_encode($response);
        exit();
    }

    // Delete post
    if ($_POST['action'] === 'delete_post' && isset($_POST['post_id'])) {
        $postId = $_POST['post_id'];
        $userId = $_SESSION['user_id'];

        try {
            // Check if the post belongs to the current user
            $checkStmt = $pdo->prepare("SELECT USER_ID, IMAGE_URL FROM POSTS WHERE POSTS_ID = ?");
            $checkStmt->execute([$postId]);
            $post = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$post || $post['USER_ID'] != $userId) {
                $response['message'] = 'You do not have permission to delete this post.';
                echo json_encode($response);
                exit();
            }

            // Delete the post from the database
            $stmt = $pdo->prepare("DELETE FROM POSTS WHERE POSTS_ID = ? AND USER_ID = ?");
            $stmt->execute([$postId, $userId]);

            // Delete the image or video file if it exists
            if (!empty($post['IMAGE_URL'])) {
                $filePath = $post['IMAGE_URL'];
                if (file_exists($filePath)) {
                    try {
                        unlink($filePath);
                    } catch (Exception $e) {
                        // Silently handle file deletion error
                    }
                }
            }

            $response['success'] = true;
            $response['message'] = 'Post deleted successfully!';
        } catch (PDOException $e) {
            $response['message'] = 'Error deleting post: ' . $e->getMessage();
        }

        echo json_encode($response);
        exit();
    }
}

// Get user information
$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';
$userEmail = $_SESSION['user_email'] ?? '';
$profileImage = $_SESSION['profile_image'] ?? null;

// If profile image is not in session, try to get it from database
if (!$profileImage) {
    try {
        $stmt = $pdo->prepare("SELECT PROFILE_IMAGE FROM USERS WHERE USER_ID = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && $result['PROFILE_IMAGE']) {
            $profileImage = $result['PROFILE_IMAGE'];
            $_SESSION['profile_image'] = $profileImage;
        }
    } catch (PDOException $e) {
        // Handle error silently
    }
}

// Set default profile image if none exists
if (!$profileImage) {
    $profileImage = 'images/default-profile.png';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Feedora - Your Cooking Dashboard">
    <meta name="theme-color" content="#ED5A2C">
    <title>Dashboard - Feedora</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="fonts.css">
    <link rel="stylesheet" href="Home.css">
    <!-- Favicon -->
    <link rel="icon" href="images/Frame 1171277973.svg" type="image/svg+xml">
    <style>
        :root {
            --primary-color: #ED5A2C;
            --secondary-color: #4CAF50;
            --text-color: #333;
            --light-text: #666;
            --background-color: #fff;
            --light-background: #f9f9f9;
            --border-color: #eaeaea;
            --border-radius: 12px;
            --transition-speed: 0.3s;
            --box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            --hover-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
            --sidebar-width: 250px;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Qurova', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            background-color: #f5f5f5;
            color: var(--text-color);
            display: flex;
            min-height: 100vh;
        }

        /* Dashboard Content Styles */
        .dashboard-content {
            padding: 20px;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            overflow: visible;
        }

        /* Enhanced Stories Styles */
        .stories-container {
            background-color: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
            padding: 20px;
            width: 100%;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: #333;
            margin-bottom: 18px;
            padding-left: 5px;
            font-family: 'DM Serif Display', serif;
        }

        .stories-list {
            display: flex;
            overflow-x: auto;
            padding: 8px 0;
            gap: 18px;
            scrollbar-width: thin;
            scrollbar-color: #ED5A2C transparent;
            -webkit-overflow-scrolling: touch;
        }

        .stories-list::-webkit-scrollbar {
            height: 6px;
        }

        .stories-list::-webkit-scrollbar-thumb {
            background: linear-gradient(90deg, #ED5A2C, #ff6b3d);
            border-radius: 3px;
        }

        .stories-list::-webkit-scrollbar-track {
            background: transparent;
        }

        .story-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            min-width: 85px;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            cursor: pointer;
            position: relative;
        }

        .story-item:hover {
            transform: translateY(-3px);
        }

        .story-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            position: relative;
            margin-bottom: 10px;
            transition: all 0.3s ease;
            background: linear-gradient(45deg, #ED5A2C, #ff6b3d);
            padding: 3px;
        }

        .story-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            transition: all 0.3s ease;
            border: 2px solid white;
        }

        .story-item:hover .story-avatar img {
            transform: scale(1.05);
            filter: brightness(1.1);
        }

        .story-border-unviewed {
            background: linear-gradient(45deg, #ED5A2C, #ff6b3d, #ED5A2C);
            animation: pulse-border 2s infinite;
            box-shadow: 0 0 0 2px rgba(237, 90, 44, 0.3);
        }

        .story-border-viewed {
            background: #e0e0e0;
            box-shadow: none;
        }

        @keyframes pulse-border {

            0%,
            100% {
                box-shadow: 0 0 0 2px rgba(237, 90, 44, 0.3);
                transform: scale(1);
            }

            50% {
                box-shadow: 0 0 0 4px rgba(237, 90, 44, 0.2);
                transform: scale(1.02);
            }
        }

        /* Story Count Badge */
        .story-count-badge {
            position: absolute;
            top: -2px;
            right: -2px;
            background: linear-gradient(135deg, #ED5A2C, #ff6b3d);
            color: white;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
            border: 2px solid white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            z-index: 2;
        }

        /* Unviewed Indicator Dot */
        .story-unviewed-dot {
            position: absolute;
            bottom: 2px;
            right: 2px;
            width: 14px;
            height: 14px;
            background: #4CAF50;
            border: 2px solid white;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            animation: pulse-dot 2s infinite;
            z-index: 2;
        }

        @keyframes pulse-dot {

            0%,
            100% {
                transform: scale(1);
                opacity: 1;
            }

            50% {
                transform: scale(1.2);
                opacity: 0.8;
            }
        }

        .story-user-info {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            width: 100%;
        }

        .story-username {
            font-size: 13px;
            font-weight: 600;
            color: #333;
            max-width: 80px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            margin-bottom: 2px;
        }



        /* Enhanced Add Story */
        .add-story {
            position: relative;
        }

        .story-media-input {
            position: absolute;
            width: 0.1px;
            height: 0.1px;
            opacity: 0;
            overflow: hidden;
            z-index: -1;
        }

        .add-story-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .add-story-label:hover {
            transform: translateY(-2px);
        }

        .story-add-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
            border: 2px dashed #ED5A2C;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .story-add-icon::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(237, 90, 44, 0.1), transparent);
            transition: left 0.5s ease;
        }

        .add-story-label:hover .story-add-icon {
            background: linear-gradient(135deg, #ED5A2C, #ff6b3d);
            border-color: #ED5A2C;
            transform: scale(1.05);
            box-shadow: 0 8px 25px rgba(237, 90, 44, 0.3);
        }

        .add-story-label:hover .story-add-icon::before {
            left: 100%;
        }

        .add-story-label:hover .story-add-icon svg {
            color: white;
            transform: rotate(90deg);
        }

        .story-add-icon svg {
            color: #ED5A2C;
            transition: all 0.3s ease;
            z-index: 1;
        }

        /* Upload Progress Indicator */
        .story-upload-progress {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: rgba(237, 90, 44, 0.9);
            display: none;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: 600;
            z-index: 10;
        }

        .upload-spinner {
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .stories-container {
                margin: 0 10px 20px 10px;
                padding: 16px;
                border-radius: 12px;
            }

            .section-title {
                font-size: 18px;
                margin-bottom: 15px;
            }

            .stories-list {
                gap: 15px;
                padding: 5px 0;
            }

            .story-item {
                min-width: 75px;
            }

            .story-avatar {
                width: 64px;
                height: 64px;
                margin-bottom: 8px;
            }

            .story-add-icon {
                width: 64px;
                height: 64px;
                margin-bottom: 8px;
            }

            .story-username {
                font-size: 12px;
                max-width: 75px;
            }



            .story-count-badge {
                width: 20px;
                height: 20px;
                font-size: 10px;
            }

            .story-unviewed-dot {
                width: 12px;
                height: 12px;
            }
        }

        /* High contrast mode support */
        @media (prefers-contrast: high) {
            .story-border-unviewed {
                border: 3px solid #ED5A2C;
            }

            .story-border-viewed {
                border: 2px solid #666;
            }
        }

        /* Reduced motion support */
        @media (prefers-reduced-motion: reduce) {

            .story-item,
            .story-avatar img,
            .add-story-label,
            .story-add-icon {
                transition: none;
            }

            .story-border-unviewed,
            .story-unviewed-dot {
                animation: none;
            }

            .story-item:hover {
                transform: none;
            }
        }

        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
            display: flex;
            flex-direction: column;
            overflow: visible;
        }

        /* Dashboard Content */
        .dashboard-content {
            flex: 1;
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 20px;
        }

        .dashboard-title {
            font-size: 1.8rem;
            margin-bottom: 20px;
            color: var(--text-color);
            font-family: 'DM Serif Display', serif;
        }

        /* Post Creation Container */
        .post-creation-container {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            margin-bottom: 30px;
            width: 100%;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }

        .post-tabs {
            display: flex;
            border-bottom: 1px solid var(--border-color);
        }

        .post-tab {
            padding: 15px 20px;
            cursor: pointer;
            font-weight: 500;
            text-align: center;
            flex: 1;
        }

        .post-tab.active {
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
        }

        .post-content {
            padding: 20px;
        }

        .user-info-container {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .user-profile-pic {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .user-profile-pic:hover {
            transform: scale(1.08);
            filter: brightness(1.1);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .post-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .post-textarea {
            width: 100%;
            min-height: 100px;
            padding: 15px;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            resize: vertical;
            font-size: 16px;
        }

        .post-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(237, 90, 44, 0.2);
        }

        .post-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .media-upload {
            display: flex;
            align-items: center;
            gap: 10px;
            background-color: #f1f1f1;
            padding: 10px 15px;
            border-radius: var(--border-radius);
            cursor: pointer;
        }

        .media-upload-label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }

        .media-upload-input {
            display: none;
        }

        .post-button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 20px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition-speed);
        }

        .post-button:hover {
            background-color: #d94e22;
        }

        .post-button:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }

        .image-preview-container {
            margin-top: 15px;
            display: none;
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }


        .image-preview {
            max-width: 100%;
            max-height: 300px;
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .remove-image {
            background-color: rgba(244, 67, 54, 0.9);
            color: white;
            border: none;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: absolute;
            top: 10px;
            right: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            backdrop-filter: blur(5px);
            z-index: 10;
        }

        .remove-image:hover {
            background-color: rgba(244, 67, 54, 1);
            transform: scale(1.1);
            box-shadow: 0 4px 15px rgba(244, 67, 54, 0.4);
        }

        /* Message Styles */
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: var(--border-radius);
            text-align: center;
        }

        .success-message {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Posts Feed Styles */
        .posts-feed {
            margin-top: 30px;
            width: 100%;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
            overflow: visible;
        }

        /* FIXED: Post containers - Allow reactions to show by removing overflow hidden */
        .posts-feed .post-card,
        .post-card,
        [data-post-id] {
            position: relative !important;
            /* Removed overflow: hidden to allow reactions to show */
        }

        .posts-feed .post-card,
        .post-card,
        [data-post-id] {
            position: relative !important;
            overflow: visible !important;
            /* Critical for reactions to show */
        }


        .post-card {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
            overflow: visible !important;
            /* Allow reactions to show */
            width: 100%;
            border: 1px solid #eaeaea;
            position: relative !important;
        }

        .post-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 16px 20px;
            border-bottom: none;
        }

        .post-user-info {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            width: 100%;
        }

        .post-user-pic {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid #f0f0f0;
            transition: all 0.3s ease;
            cursor: pointer;
        }



        .post-user-details {
            display: flex;
            flex-direction: column;
            width: 100%;
        }

        .post-user-name {
            font-weight: 700;
            color: #333;
            font-size: 16px;
            margin-bottom: 5px;
        }

        .post-meta-row {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            color: #777;
            font-size: 14px;
            line-height: 1.3;
        }

        .post-shared {
            color: #777;
        }

        .post-meta {
            color: #777;
        }

        .post-date {
            color: #777;
            position: relative;
            padding-left: 15px;
        }

        .post-date:before {
            content: '•';
            position: absolute;
            left: 5px;
        }

        .post-actions {
            position: relative;
            color: #666;
        }

        .post-actions-menu {
            cursor: pointer;
            padding: 5px;
        }

        .post-menu-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            width: 150px;
            z-index: 10;
            display: none;
        }

        .post-menu-item {
            padding: 12px 15px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.2s;
        }

        .post-menu-item:hover {
            background-color: #f5f5f5;
        }

        .post-menu-item:first-child {
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }

        .post-menu-item:last-child {
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
        }

        .edit-post {
            color: #4CAF50;
        }

        .delete-post {
            color: #f44336;
        }

        /* FIXED: Post Image Styles - Only apply overflow hidden to image containers */
        .posts-feed .post-image,
        .post-card .post-image,
        .post-image {
            width: 100% !important;
            max-height: 500px !important;
            overflow: hidden !important;
            /* Keep overflow hidden only on image containers */
            display: flex !important;
            justify-content: center !important;
            align-items: center !important;
            background-color: #fff !important;
            position: relative !important;
            cursor: pointer !important;
            border-radius: 0 !important;
            isolation: isolate !important;
        }

        .posts-feed .post-image-content,
        .post-card .post-image-content,
        .post-image-content {
            width: 100% !important;
            height: auto !important;
            object-fit: cover !important;
            display: block !important;
            cursor: pointer !important;
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94) !important;
            position: relative !important;
            z-index: 1 !important;
            max-width: 100% !important;
            max-height: 100% !important;
        }


        .post-image::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg,
                    rgba(237, 90, 44, 0.1) 0%,
                    rgba(237, 90, 44, 0.05) 50%,
                    transparent 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 2;
            pointer-events: none;
        }

        .post-image:hover::before {
            opacity: 1;
        }

        .post-image::after {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0);
            font-size: 24px;
            background-color: rgba(0, 0, 0, 0.7);
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: all 0.3s ease;
            z-index: 3;
            pointer-events: none;
        }



        .post-image video {
            aspect-ratio: 4/5;
            height: auto;
            object-fit: cover;
            cursor: pointer;
            transition: all 0.3s ease;
            border-radius: 0;
        }

        .post-image:hover video {
            transform: none;
            filter: brightness(1.05) contrast(1.05);
        }

        .post-description {
            padding: 16px 20px 20px;
            color: #333;
            line-height: 1.5;
            font-size: 16px;
        }

        .post-description p {
            margin: 0;
            font-weight: 400;
            color: #333;
        }

        /* FIXED: Post Stats - Ensure reactions can show above */
        .post-stats {
            display: flex !important;
            justify-content: flex-start !important;
            padding: 12px 20px !important;
            border-top: 1px solid #eaeaea !important;
            gap: 40px !important;
            overflow: visible !important;
            position: relative !important;
            z-index: 10 !important;
        }

        .post-likes,
        .post-comments {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #555;
            font-size: 15px;
            font-weight: 500;
        }

        .post-likes svg,
        .post-comments svg {
            min-width: 20px;
        }

        .no-posts-message {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 30px;
            text-align: center;
            color: #666;
        }

        /* FIXED: Enhanced Food Reactions Styles */
        .post-likes-container {
            position: relative !important;
            z-index: 100 !important;
            display: inline-block !important;
        }

        .post-likes {
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            cursor: pointer;
            color: #555;
            font-size: 15px;
            font-weight: 500;
        }

        .like-icon {
            transition: transform 0.3s ease;
            position: relative;
            z-index: 10;
            min-width: 20px;
        }


        .post-likes-container:hover .like-icon {
            transform: scale(1.1);
        }

        /* FIXED: Food reactions positioning - Ensure they appear above the like button */
        .food-reactions {
            position: absolute !important;
            bottom: 45px !important;
            left: 50% !important;
            transform: translateX(-50%) !important;
            display: flex !important;
            background-color: white !important;
            border-radius: 30px !important;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15) !important;
            padding: 8px 12px !important;
            opacity: 0 !important;
            visibility: hidden !important;
            transition: all 0.3s ease !important;
            z-index: 999 !important;
            gap: 2px !important;
            min-width: 280px !important;
            white-space: nowrap !important;
            border: 1px solid rgba(0, 0, 0, 0.05) !important;
        }

        .post-likes-container:hover .food-reactions,
        .post-likes-container.active .food-reactions {
            opacity: 1 !important;
            visibility: visible !important;
            transform: translateX(-50%) translateY(-5px) !important;
        }

        .reaction-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            line-height: 1;
            font-size: 20px;
            cursor: pointer;
            transition: all 0.2s ease;
            padding: 8px;
            border-radius: 50%;
            position: relative;
            min-width: 40px;
            height: 40px;
            flex-shrink: 0;
        }

        .reaction-icon:hover {
            transform: scale(1.3);
            background-color: rgba(237, 90, 44, 0.1);
            z-index: 1000;
        }


        .reaction-icon span {
            font-size: 18px;
            display: block;
        }


        .post-likes.active {
            color: var(--primary-color);
            font-weight: 600;
        }


        .reaction-animation {
            animation: pop 0.4s ease-out;
        }

        @keyframes pop {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.4);
            }

            100% {
                transform: scale(1);
            }
        }

        @keyframes floatUp {
            0% {
                transform: translateY(0) scale(1);
                opacity: 1;
            }

            50% {
                transform: translateY(-20px) scale(1.2);
                opacity: 0.8;
            }

            100% {
                transform: translateY(-40px) scale(0.8);
                opacity: 0;
            }
        }

        /* Enhanced hover and active states */
        .post-likes-container.active .food-reactions {
            animation: slideUp 0.3s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateX(-50%) translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateX(-50%) translateY(-5px);
            }
        }

        /* Reaction Users Modal */
        .reaction-users-list {
            max-height: 400px;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #ddd transparent;
        }

        .reaction-users-list::-webkit-scrollbar {
            width: 6px;
        }

        .reaction-users-list::-webkit-scrollbar-thumb {
            background-color: #ddd;
            border-radius: 3px;
        }

        .reaction-user-item {
            display: flex;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s ease;
        }

        .reaction-user-item:hover {
            background-color: #f9f9f9;
        }

        .reaction-user-item:last-child {
            border-bottom: none;
        }

        .reaction-user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            margin-right: 12px;
            object-fit: cover;
            border: 2px solid #f0f0f0;
        }

        .reaction-user-info {
            flex: 1;
        }

        .reaction-user-name {
            font-weight: 600;
            margin-bottom: 4px;
            color: #333;
        }

        .reaction-user-time {
            font-size: 12px;
            color: #666;
        }

        .reaction-user-emoji {
            font-size: 24px;
            margin-left: 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .like-count {
            cursor: pointer;
            transition: color 0.2s;
        }

        .like-count:hover {
            color: var(--primary-color);
        }


        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.7);
        }

        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            width: 90%;
            max-width: 600px;
            position: relative;
        }

        .modal-content h2 {
            color: var(--text-color);
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 24px;
            text-align: center;
        }

        .modal-content .form-group {
            margin-bottom: 20px;
        }

        .modal-content textarea {
            width: 100%;
            min-height: 120px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            resize: vertical;
            transition: border-color 0.3s;
        }

        .modal-content textarea:focus {
            border-color: var(--primary-color);
            outline: none;
        }

        #edit-image-preview-container {
            margin-bottom: 15px;
            text-align: center;
        }

        #edit-image-preview {
            max-width: 100%;
            max-height: 300px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        #edit-image-preview:hover {
            transform: scale(1.02);
            filter: brightness(1.05);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .modal-content .file-upload-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 12px 20px;
            background-color: #f5f5f5;
            border: 1px dashed #ccc;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .modal-content .file-upload-label:hover {
            background-color: #eee;
            border-color: #aaa;
        }

        .modal-content .upload-icon svg {
            color: var(--primary-color);
        }

        .modal-content .form-actions {
            margin-top: 25px;
            text-align: center;
        }

        .close-modal {
            position: absolute;
            right: 20px;
            top: 15px;
            font-size: 28px;
            font-weight: bold;
            color: #aaa;
            cursor: pointer;
        }

        /* Enhanced Image Modal */
        .image-modal {
            display: none;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(10px);
            background-color: rgba(0, 0, 0, 0.9);
        }

        .image-modal-content {
            position: relative;
            background-color: transparent;
            margin: auto;
            padding: 0;
            max-width: 95%;
            max-height: 90vh;
            box-shadow: none;
            animation: modalZoomIn 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            border-radius: 12px;
            overflow: hidden;
        }

        @keyframes modalZoomIn {
            from {
                opacity: 0;
                transform: scale(0.8);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .image-container {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            height: 100%;
            position: relative;
        }

        #modalImage {
            max-width: 100%;
            max-height: 90vh;
            object-fit: contain;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            transition: transform 0.3s ease;
        }

 
        .image-close-btn {
            position: absolute;
            top: -50px;
            right: 0;
            color: white;
            font-size: 40px;
            font-weight: bold;
            z-index: 1010;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.8);
            cursor: pointer;
            transition: all 0.3s ease;
            background-color: rgba(0, 0, 0, 0.5);
            border-radius: 50%;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .image-close-btn:hover {
            background-color: rgba(244, 67, 54, 0.8);
            transform: scale(1.1);
            color: white;
        }

        /* Mobile Responsive - FIXED */
        @media (max-width: 768px) {
            .food-reactions {
                position: fixed !important;
                bottom: 80px !important;
                left: 50% !important;
                transform: translateX(-50%) !important;
                background-color: white !important;
                border-radius: 35px !important;
                box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2) !important;
                padding: 12px 16px !important;
                z-index: 1000 !important;
                min-width: 320px !important;
                border: 2px solid rgba(237, 90, 44, 0.1) !important;
            }

            .reaction-icon {
                font-size: 24px;
                padding: 12px;
                margin: 0 4px;
                min-width: 48px;
                height: 48px;
            }

            .reaction-icon span {
                font-size: 22px;
            }

            .reaction-icon:hover {
                transform: scale(1.2);
            }

            .main-content {
                margin-left: 0;
                width: 100%;
            }

            .dashboard-content {
                padding: 10px;
            }

            .stories-container,
            .post-creation-container,
            .posts-feed {
                max-width: 100%;
                margin-bottom: 15px;
            }

            .post-stats {
                display: flex !important;
                justify-content: flex-start !important;
                padding: 12px 20px !important;
                border-top: 1px solid #eaeaea !important;
                gap: 40px !important;
                overflow: visible !important;
                position: relative !important;
                z-index: 10 !important;
            }



            .post-image::after {
                font-size: 20px;
                width: 40px;
                height: 40px;
            }

            .image-close-btn {
                top: -40px;
                font-size: 35px;
                width: 40px;
                height: 40px;
            }

            .post-image-content:active {
                transform: scale(0.98);
            }

            .post-card {
                margin: 0 5px 25px 5px;
                width: calc(100% - 10px);
                overflow: visible !important;
                /* Allow reactions to show on mobile */
                position: relative !important;
            }

            /* Backdrop for mobile reactions */
            .post-likes-container.active::before {
                content: '';
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(0, 0, 0, 0.1);
                z-index: 998;
                backdrop-filter: blur(1px);
            }

            .food-reactions {
                z-index: 999 !important;
            }
        }

        /* Tablet Responsive Styles */
        @media (max-width: 1024px) and (min-width: 769px) {
            .food-reactions {
                min-width: 260px;
                padding: 10px 14px;
                gap: 4px;
            }

            .reaction-icon {
                min-width: 42px;
                height: 42px;
                font-size: 18px;
                padding: 10px;
            }

            .reaction-icon span {
                font-size: 16px;
            }
        }

        /* Small Mobile Devices */
        @media (max-width: 480px) {
            .food-reactions {
                min-width: 300px !important;
                padding: 10px 12px !important;
                bottom: 70px !important;
                border-radius: 30px !important;
            }

            .reaction-icon {
                min-width: 44px;
                height: 44px;
                margin: 0 2px;
                padding: 10px;
            }

            .reaction-icon span {
                font-size: 20px;
            }
        }

        /* Very Small Screens */
        @media (max-width: 360px) {
            .food-reactions {
                min-width: 280px !important;
                padding: 8px 10px !important;
                gap: 1px !important;
            }

            .reaction-icon {
                min-width: 40px;
                height: 40px;
                margin: 0 1px;
                padding: 8px;
            }

            .reaction-icon span {
                font-size: 18px;
            }
        }

        /* High contrast mode support */
        @media (prefers-contrast: high) {
            .post-image::before {
                background: rgba(0, 0, 0, 0.2);
            }

            .post-image::after {
                background-color: rgba(0, 0, 0, 0.9);
                border: 2px solid white;
            }
        }

        /* Reduced motion support */
        @media (prefers-reduced-motion: reduce) {

            .post-image-content,
            .image-preview,
            #modalImage,
            .user-profile-pic,
            .post-user-pic {
                transition: none;
            }


        }

        /* Ensure parent containers don't interfere with reactions */
        .posts-feed,
        .dashboard-content {
            overflow: visible !important;
        }

        /* Override any Home.css conflicting styles for dashboard posts */
        .dashboard-content .post-card .post-image {
            overflow: hidden !important;
            border-radius: 0 !important;
            max-height: 500px !important;
        }

        .dashboard-content .post-card .post-image-content {
            max-width: 50% !important;
        }



        /* ADD this improved CSS to your dashboard.php <style> section */

        /* Enhanced Comments Modal Styles */
        /* Comments Modal Base Styles */
        .comments-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(3px);
            z-index: 1000;
            animation: fadeIn 0.3s ease;
            overflow-y: auto;
        }

        .comments-modal-content {
            background-color: white;
            margin: 3% auto;
            border-radius: 16px;
            width: 90%;
            max-width: 650px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            animation: slideUp 0.3s ease;
            position: relative;
        }

        /* Comments Header */
        .comments-header {
            padding: 20px 25px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #ED5A2C 0%, #ff6b3d 100%);
            color: white;
        }

        .comments-header h3 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
        }

        .close-comments {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .close-comments:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        /* Comments Body */
        .comments-body {
            max-height: 450px;
            overflow-y: auto;
            padding: 20px 25px;
            scroll-behavior: smooth;
            background-color: white;
        }

        .comments-body::-webkit-scrollbar {
            width: 6px;
        }

        .comments-body::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .comments-body::-webkit-scrollbar-thumb {
            background: #ED5A2C;
            border-radius: 3px;
        }

        .comments-body::-webkit-scrollbar-thumb:hover {
            background: #d94e22;
        }

        /* Comment Items */
        .comment-item {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f5f5f5;
            position: relative;
            animation: commentAppear 0.3s ease;
        }

        .comment-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .comment-item.has-reaction {
            background: rgba(237, 90, 44, 0.02);
            border-radius: 12px;
            padding: 15px;
            margin: 10px 0;
            border: 1px solid rgba(237, 90, 44, 0.1);
        }

        .comment-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #f0f0f0;
            flex-shrink: 0;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .comment-avatar:hover {
            transform: scale(1.05);
            border-color: #ED5A2C;
            box-shadow: 0 2px 8px rgba(237, 90, 44, 0.2);
        }

        .comment-content {
            flex: 1;
            min-width: 0;
        }

        .comment-author {
            font-weight: 600;
            color: #333;
            margin-bottom: 6px;
            font-size: 15px;
        }

        .comment-text {
            color: #555;
            line-height: 1.5;
            margin-bottom: 10px;
            word-wrap: break-word;
            font-size: 15px;
        }

        .comment-time {
            font-size: 12px;
            color: #888;
            margin-bottom: 8px;
        }

        .comment-actions {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-top: 10px;
        }

        /* ENHANCED Comment Reaction System */
        .comment-like-container {
            position: relative;
            display: inline-block;
            z-index: 50;
        }

        .comment-like-btn {
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 5px 8px;
            border-radius: 15px;
            transition: all 0.2s ease;
            font-weight: 500;
        }

        .comment-like-btn:hover {
            background-color: rgba(237, 90, 44, 0.1);
            color: var(--primary-color);
            transform: translateY(-1px);
        }

        .comment-like-btn.active {
            background-color: rgba(237, 90, 44, 0.15);
            color: var(--primary-color);
            font-weight: 600;
        }

        .comment-like-btn svg {
            transition: all 0.2s ease;
        }

        .comment-like-btn:hover svg {
            transform: scale(1.1);
        }

        /* ENHANCED Comment Food Reactions Panel */
        .comment-food-reactions {
            position: absolute;
            bottom: 40px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            background-color: white;
            border-radius: 30px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            padding: 8px 12px;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            z-index: 100;
            gap: 2px;
            min-width: 280px;
            white-space: nowrap;
            border: 1px solid rgba(0, 0, 0, 0.05);
            backdrop-filter: blur(10px);
        }

        .comment-like-container:hover .comment-food-reactions,
        .comment-like-container.active .comment-food-reactions {
            opacity: 1;
            visibility: visible;
            transform: translateX(-50%) translateY(-5px);
            animation: reactionPanelBounce 0.3s ease;
        }

        .comment-reaction-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            line-height: 1;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            padding: 8px;
            border-radius: 50%;
            position: relative;
            min-width: 36px;
            height: 36px;
            flex-shrink: 0;
            background: transparent;
            user-select: none;
        }

        .comment-reaction-icon:hover {
            transform: scale(1.4);
            background-color: rgba(237, 90, 44, 0.1);
            z-index: 101;
            box-shadow: 0 4px 15px rgba(237, 90, 44, 0.3);
        }

        .comment-reaction-icon:active {
            transform: scale(1.6);
            transition: transform 0.1s ease;
        }

        .comment-reaction-icon span {
            font-size: 16px;
            display: block;
            pointer-events: none;
        }

        /* Comment Reaction Animations */
        .comment-reaction-animation {
            animation: commentReactionPop 0.4s ease-out;
        }

        @keyframes commentReactionPop {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.5);
            }

            100% {
                transform: scale(1);
            }
        }

        @keyframes reactionPanelBounce {
            0% {
                opacity: 0;
                transform: translateX(-50%) translateY(10px) scale(0.8);
            }

            50% {
                opacity: 0.8;
                transform: translateX(-50%) translateY(-2px) scale(1.05);
            }

            100% {
                opacity: 1;
                transform: translateX(-50%) translateY(-5px) scale(1);
            }
        }

        /* Comment Reaction Display */
        .comment-reaction-display {
            margin: 8px 0;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 14px;
            color: var(--primary-color);
            font-weight: 500;
            padding: 6px 10px;
            background: rgba(237, 90, 44, 0.1);
            border-radius: 15px;
            border: 1px solid rgba(237, 90, 44, 0.2);
            animation: reactionDisplaySlide 0.3s ease;
        }

        @keyframes reactionDisplaySlide {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .comment-reaction-display .reaction-emoji {
            font-size: 16px;
        }

        /* Comment Reaction Count */
        .comment-reaction-count {
            font-size: 12px;
            color: #666;
            cursor: pointer;
            margin: 8px 0;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 8px;
            border-radius: 12px;
            background-color: #f0f0f0;
            transition: all 0.2s ease;
        }

        .comment-reaction-count:hover {
            background-color: rgba(237, 90, 44, 0.1);
            color: var(--primary-color);
            transform: translateY(-1px);
        }

        /* Comment Delete Button */
        .comment-delete-btn {
            background: none;
            border: none;
            color: #f44336;
            cursor: pointer;
            font-size: 12px;
            padding: 5px 8px;
            border-radius: 15px;
            transition: all 0.2s ease;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .comment-delete-btn:hover {
            background-color: rgba(244, 67, 54, 0.1);
            color: #d32f2f;
            transform: translateY(-1px);
        }

        /* Comment Form */
        .comment-form {
            padding: 20px 25px;
            border-top: 1px solid #eee;
            background-color: #fafafa;
        }

        .comment-input-container {
            display: flex;
            gap: 15px;
            align-items: flex-end;
        }

        .comment-input {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            resize: none;
            min-height: 44px;
            max-height: 120px;
            outline: none;
            font-family: inherit;
            font-size: 14px;
            line-height: 1.4;
            transition: all 0.2s ease;
            background-color: white;
        }

        .comment-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(237, 90, 44, 0.1);
        }

        .comment-input::placeholder {
            color: #aaa;
        }

        /* Enhanced Comment Submit Button */
        .comment-submit {
            background: linear-gradient(135deg, #ED5A2C 0%, #ff6b3d 100%);
            color: white;
            border: none;
            border-radius: 50%;
            width: 44px;
            height: 44px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(237, 90, 44, 0.3);
        }

        .comment-submit:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(237, 90, 44, 0.4);
        }

        .comment-submit:active:not(:disabled) {
            transform: translateY(0);
        }

        .comment-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            background: #ccc;
            box-shadow: none;
        }

        /* Loading Spinner */
        .loading-spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #ED5A2C;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        .loading-spinner.small {
            width: 16px;
            height: 16px;
            border-width: 2px;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* No Comments State */
        .no-comments {
            text-align: center;
            color: #666;
            padding: 50px 20px;
            font-size: 16px;
        }

        /* Reactions Section */
        .reactions-section {
            padding: 20px;
            border-top: 1px solid #eee;
            margin-top: 20px;
            background-color: #f9f9f9;
        }

        .reactions-title {
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
        }

        .reactions-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .reaction-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease;
        }

        .reaction-item:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
        }

        /* Global Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes commentAppear {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* MOBILE RESPONSIVE STYLES */
        @media (max-width: 768px) {
            .comments-modal-content {
                width: 95%;
                margin: 2% auto;
                max-height: 90vh;
            }

            .comments-header {
                padding: 15px 20px;
            }

            .comments-header h3 {
                font-size: 18px;
            }

            .comments-body {
                padding: 15px 20px;
                max-height: 350px;
            }

            .comment-form {
                padding: 15px 20px;
            }

            .comment-input-container {
                gap: 12px;
            }

            .comment-submit {
                width: 40px;
                height: 40px;
            }

            .comment-avatar {
                width: 36px;
                height: 36px;
            }

            /* Mobile Comment Reactions */
            .comment-food-reactions {
                position: fixed !important;
                bottom: 80px !important;
                left: 50% !important;
                transform: translateX(-50%) !important;
                background-color: white !important;
                border-radius: 35px !important;
                box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2) !important;
                padding: 12px 16px !important;
                z-index: 1000 !important;
                min-width: 320px !important;
                border: 2px solid rgba(237, 90, 44, 0.1) !important;
            }

            .comment-like-container:hover .comment-food-reactions,
            .comment-like-container.active .comment-food-reactions {
                transform: translateX(-50%) !important;
            }

            .comment-reaction-icon {
                font-size: 22px;
                padding: 12px;
                margin: 0 4px;
                min-width: 48px;
                height: 48px;
            }

            .comment-reaction-icon span {
                font-size: 20px;
            }

            .comment-reaction-icon:hover {
                transform: scale(1.2);
            }

            /* Mobile backdrop for comment reactions */
            .comment-reaction-backdrop {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(0, 0, 0, 0.05);
                z-index: 999;
                backdrop-filter: blur(1px);
            }
        }

        /* Small Mobile Devices */
        @media (max-width: 480px) {
            .comment-food-reactions {
                min-width: 300px !important;
                padding: 10px 12px !important;
                bottom: 70px !important;
                border-radius: 30px !important;
            }

            .comment-reaction-icon {
                min-width: 44px;
                height: 44px;
                margin: 0 2px;
                padding: 10px;
            }

            .comment-reaction-icon span {
                font-size: 18px;
            }
        }

        /* Very Small Screens */
        @media (max-width: 360px) {
            .comment-food-reactions {
                min-width: 280px !important;
                padding: 8px 10px !important;
                gap: 1px !important;
            }

            .comment-reaction-icon {
                min-width: 40px;
                height: 40px;
                margin: 0 1px;
                padding: 8px;
            }

            .comment-reaction-icon span {
                font-size: 16px;
            }
        }

        /* Tablet Responsive */
        @media (max-width: 1024px) and (min-width: 769px) {
            .comment-food-reactions {
                min-width: 260px;
                padding: 10px 14px;
                gap: 4px;
            }

            .comment-reaction-icon {
                min-width: 42px;
                height: 42px;
                font-size: 16px;
                padding: 10px;
            }

            .comment-reaction-icon span {
                font-size: 14px;
            }
        }

        /* High Contrast Mode Support */
        @media (prefers-contrast: high) {
            .comment-food-reactions {
                border: 2px solid #000;
                background-color: #fff;
            }

            .comment-reaction-icon:hover {
                background-color: #000;
                color: #fff;
            }
        }

        /* Reduced Motion Support */
        @media (prefers-reduced-motion: reduce) {

            .comment-reaction-icon,
            .comment-like-btn,
            .comment-submit,
            .comment-avatar {
                transition: none;
            }

            .comment-reaction-icon:hover,
            .comment-like-btn:hover,
            .comment-avatar:hover {
                transform: none;
            }

            .comment-food-reactions {
                animation: none;
            }
        }

        /* Focus Styles for Accessibility */
        .comment-like-btn:focus,
        .comment-reaction-icon:focus,
        .comment-submit:focus,
        .comment-input:focus {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }

        /* Dark Mode Support (if needed) */
        @media (prefers-color-scheme: dark) {
            .comments-modal-content {
                background-color: #1a1a1a;
                color: #fff;
            }

            .comments-body::-webkit-scrollbar-track {
                background: #333;
            }

            .comment-input {
                background-color: white;
                border-color: #444;
                color: black;
            }

            .comment-food-reactions {
                background-color: #2a2a2a;
                border-color: #444;
            }
        }

        /* Print Styles */
        @media print {

            .comments-modal,
            .comment-food-reactions,
            .comment-submit {
                display: none;
            }
        }
    </style>
</head>

<body>
    <?php include('sidebar.php'); ?>

    <!-- Main Content -->
    <main class="main-content">
        <?php include('header.php'); ?>

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Enhanced Stories Section -->
            <div class="stories-container">
                <h3 class="section-title">Stories</h3>
                <div class="stories-list">
                    <!-- Enhanced Add Story Button with Multiple Upload -->
                    <div class="story-item add-story">
                        <form action="story.php" method="POST" enctype="multipart/form-data" id="storyForm">
                            <input type="file" name="story_media[]" id="storyMedia" class="story-media-input"
                                accept="image/*,video/mp4,video/quicktime,video/webm"
                                multiple
                                onchange="handleStoryUpload(this)">
                            <input type="hidden" name="visibility" value="public">
                            <label for="storyMedia" class="add-story-label">
                                <div class="story-add-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="12" y1="5" x2="12" y2="19"></line>
                                        <line x1="5" y1="12" x2="19" y2="12"></line>
                                    </svg>
                                </div>
                                <span class="story-username">Add Story</span>
                            </label>
                        </form>
                    </div>

                    <?php
                    // Get active stories from the database (less than 24 hours old)
                    // Enhanced Stories Query - Get all active stories grouped by user with counts
                    try {
                        $storyStmt = $pdo->prepare(
                            "SELECT 
                    s.USER_ID,
                    u.NAME, 
                    u.PROFILE_IMAGE,
                    COUNT(s.STORIES_ID) as story_count,
                    MAX(s.CREATED_AT) as latest_story_time,
                    (SELECT s2.STORIES_ID FROM STORIES s2 WHERE s2.USER_ID = s.USER_ID AND s2.EXPIRES_AT > NOW() ORDER BY s2.CREATED_AT ASC LIMIT 1) as first_story_id,
                    (SELECT COUNT(*) FROM STORY_VIEWS sv 
                     WHERE sv.STORY_ID IN (SELECT s3.STORIES_ID FROM STORIES s3 WHERE s3.USER_ID = s.USER_ID AND s3.EXPIRES_AT > NOW()) 
                     AND sv.VIEWER_ID = ?) as total_viewed_count,
                    (SELECT COUNT(*) FROM STORIES s4 WHERE s4.USER_ID = s.USER_ID AND s4.EXPIRES_AT > NOW()) as total_stories_count
                FROM STORIES s 
                JOIN USERS u ON s.USER_ID = u.USER_ID 
                WHERE s.EXPIRES_AT > NOW() 
                GROUP BY s.USER_ID, u.NAME, u.PROFILE_IMAGE
                ORDER BY latest_story_time DESC"
                        );
                        $storyStmt->execute([$userId]);
                        $userStories = $storyStmt->fetchAll(PDO::FETCH_ASSOC);

                        // Display each user's story with enhanced UI
                        foreach ($userStories as $userStory) {
                            $hasUnviewed = ($userStory['total_viewed_count'] < $userStory['total_stories_count']);
                            $storyBorderClass = $hasUnviewed ? 'story-border-unviewed' : 'story-border-viewed';
                            $userImage = !empty($userStory['PROFILE_IMAGE']) ? $userStory['PROFILE_IMAGE'] : 'images/default-profile.png';

                            // Calculate time ago for latest story
                            $latestTime = new DateTime($userStory['latest_story_time']);
                            $now = new DateTime();
                            $diff = $now->diff($latestTime);

                            if ($diff->days > 0) {
                                $timeAgo = $diff->days . 'd';
                            } elseif ($diff->h > 0) {
                                $timeAgo = $diff->h . 'h';
                            } elseif ($diff->i > 0) {
                                $timeAgo = $diff->i . 'm';
                            } else {
                                $timeAgo = 'now';
                            }

                            echo '<a href="story.php?user_id=' . $userStory['USER_ID'] . '" class="story-item" data-user-id="' . $userStory['USER_ID'] . '">';
                            echo '<div class="story-avatar ' . $storyBorderClass . '">';
                            echo '<img src="' . htmlspecialchars($userImage) . '" alt="' . htmlspecialchars($userStory['NAME']) . '">';

                            // Story count indicator for multiple stories
                            if ($userStory['story_count'] > 1) {
                                echo '<div class="story-count-badge">' . $userStory['story_count'] . '</div>';
                            }

                            // Unviewed indicator
                            if ($hasUnviewed) {
                                echo '<div class="story-unviewed-dot"></div>';
                            }

                            echo '</div>';
                            echo '<div class="story-user-info">';
                            echo '<span class="story-username">' . htmlspecialchars($userStory['NAME']) . '</span>';
                            echo '</div>';
                            echo '</a>';
                        }
                    } catch (PDOException $e) {
                        // Silently handle error
                        error_log("Stories query error: " . $e->getMessage());
                    }
                    ?>
                </div>
            </div>

            <!-- Post Creation Section -->
            <div class="post-creation-container">
                <div class="post-tabs">
                    <div class="post-tab active">Make Post</div>
                    <div class="post-tab">Make Recipe</div>
                </div>

                <div class="post-content">
                    <?php if (isset($_SESSION['post_message'])): ?>
                        <div class="message <?php echo isset($_SESSION['post_success']) && $_SESSION['post_success'] ? 'success-message' : 'error-message'; ?>">
                            <?php echo htmlspecialchars($_SESSION['post_message']); ?>
                        </div>
                        <?php
                        // Clear the message after displaying it
                        unset($_SESSION['post_message']);
                        unset($_SESSION['post_success']);
                        ?>
                    <?php endif; ?>

                    <div class="user-info-container">
                        <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Profile" class="user-profile-pic">
                    </div>

                    <form action="post.php" method="POST" enctype="multipart/form-data" class="post-form" id="postForm">
                        <textarea name="description" placeholder="What's recipe are you cooking today?" class="post-textarea" id="postDescription"></textarea>

                        <div class="post-options">
                            <div class="media-upload">
                                <label for="postImage" class="media-upload-label">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                        <polyline points="21 15 16 10 5 21"></polyline>
                                    </svg>
                                    Photo or Video
                                </label>
                                <input type="file" name="post_image" id="postImage" class="media-upload-input" accept="image/*,video/mp4">
                            </div>

                            <button type="submit" class="post-button" id="postButton">
                                Post Now
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="22" y1="2" x2="11" y2="13"></line>
                                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                                </svg>
                            </button>
                        </div>

                        <div class="image-preview-container" id="imagePreviewContainer">
                            <div style="position: relative; display: inline-block;">
                                <img src="/placeholder.svg" alt="Preview" class="image-preview" id="imagePreview">
                                <button type="button" class="remove-image" id="removeImage">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="18" y1="6" x2="6" y2="18"></line>
                                        <line x1="6" y1="6" x2="18" y2="18"></line>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Posts Feed Section with Enhanced Reactions -->
            <?php
            // REPLACE the Posts Feed Section in your dashboard.php with this optimized version:

            // Posts Feed Section with Enhanced Reactions and Comments
            echo '<div class="posts-feed">';

            try {
                // Get posts with reaction counts AND comment counts from database
                $stmt = $pdo->prepare(
                    "SELECT p.*, u.NAME, u.PROFILE_IMAGE, 
                COALESCE(prc.total_reactions, 0) as total_reactions,
                COALESCE(prc.yummy_count, 0) as yummy_count,
                COALESCE(prc.delicious_count, 0) as delicious_count,
                COALESCE(prc.tasty_count, 0) as tasty_count,
                COALESCE(prc.love_count, 0) as love_count,
                COALESCE(prc.amazing_count, 0) as amazing_count,
                pr.REACTION_TYPE as user_reaction,
                COALESCE(cc.comment_count, 0) as comment_count
        FROM POSTS p 
        JOIN USERS u ON p.USER_ID = u.USER_ID 
        LEFT JOIN POST_REACTION_COUNTS prc ON p.POSTS_ID = prc.POSTS_ID
        LEFT JOIN POST_REACTIONS pr ON p.POSTS_ID = pr.POST_ID AND pr.USER_ID = ?
        LEFT JOIN (
            SELECT POST_ID, COUNT(*) as comment_count 
            FROM COMMENTS 
            WHERE COMMENT_TEXT IS NOT NULL 
            GROUP BY POST_ID
        ) cc ON p.POSTS_ID = cc.POST_ID
        ORDER BY p.CREATED_AT DESC 
        LIMIT 10"
                );
                $stmt->execute([$userId]);
                $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (count($posts) > 0) {
                    foreach ($posts as $post) {
                        // Format the post creation date
                        $postDate = new DateTime($post['CREATED_AT']);
                        $formattedDate = $postDate->format('M d, Y \a\t g:i A');
                        $userProfileImage = !empty($post['PROFILE_IMAGE']) ? $post['PROFILE_IMAGE'] : 'images/default-profile.png';

                        echo '<div class="post-card" data-post-id="' . $post['POSTS_ID'] . '">';

                        // Post header
                        echo '<div class="post-header">';
                        echo '<div class="post-user-info">';
                        echo '<img src="' . htmlspecialchars($userProfileImage) . '" alt="Profile" class="post-user-pic">';
                        echo '<div class="post-user-details">';
                        echo '<span class="post-user-name">' . htmlspecialchars($post['NAME']) . '</span>';
                        echo '<div class="post-meta-row">';
                        echo '<span class="post-shared">shared a</span>';

                        $fileType = 'post';
                        if (!empty($post['IMAGE_URL'])) {
                            $fileExtension = strtolower(pathinfo($post['IMAGE_URL'], PATHINFO_EXTENSION));
                            $fileType = in_array($fileExtension, ['mp4', 'webm', 'ogg']) ? 'video' : 'image';
                        }
                        echo '<span class="post-meta">' . $fileType . '</span>';
                        echo '<span class="post-date">' . $formattedDate . '</span>';
                        echo '</div>';
                        echo '</div>';
                        echo '</div>';

                        // Post actions menu (edit/delete) - only for post owner
                        if ($post['USER_ID'] == $_SESSION['user_id']) {
                            echo '<div class="post-actions">';
                            echo '<div class="post-actions-menu" onclick="togglePostMenu(' . $post['POSTS_ID'] . ')">';
                            echo '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">';
                            echo '<circle cx="12" cy="12" r="1"></circle>';
                            echo '<circle cx="19" cy="12" r="1"></circle>';
                            echo '<circle cx="5" cy="12" r="1"></circle>';
                            echo '</svg>';
                            echo '</div>';

                            echo '<div class="post-menu-dropdown" id="post-menu-' . $post['POSTS_ID'] . '">';
                            echo '<div class="post-menu-item edit-post" onclick="editPost(' . $post['POSTS_ID'] . ', `' . htmlspecialchars(addslashes($post['DESCRIPTION'] ?? ''), ENT_QUOTES) . '`, `' . htmlspecialchars($post['IMAGE_URL'] ?? '', ENT_QUOTES) . '`)">Edit Post</div>';
                            echo '<div class="post-menu-item delete-post" onclick="deletePost(' . $post['POSTS_ID'] . ')">Delete Post</div>';
                            echo '</div>';
                            echo '</div>';
                        }
                        echo '</div>';

                        // Post media
                        if (!empty($post['IMAGE_URL'])) {
                            echo '<div class="post-image">';
                            $fileExtension = strtolower(pathinfo($post['IMAGE_URL'], PATHINFO_EXTENSION));
                            $isVideo = in_array($fileExtension, ['mp4', 'webm', 'ogg']);

                            if ($isVideo) {
                                echo '<video controls width="100%">';
                                echo '<source src="' . htmlspecialchars($post['IMAGE_URL']) . '" type="video/' . $fileExtension . '">';
                                echo 'Your browser does not support the video tag.';
                                echo '</video>';
                            } else {
                                echo '<img src="' . htmlspecialchars($post['IMAGE_URL']) . '" alt="Post Image" class="post-image-content" onclick="openImageModal(\'' . htmlspecialchars($post['IMAGE_URL']) . '\')">';
                            }
                            echo '</div>';
                        }

                        // Post description
                        if (!empty($post['DESCRIPTION'])) {
                            echo '<div class="post-description">';
                            echo '<p>' . nl2br(htmlspecialchars($post['DESCRIPTION'])) . '</p>';
                            echo '</div>';
                        }

                        // Post engagement stats with enhanced database-driven reactions
                        $totalReactions = intval($post['total_reactions']);
                        $userReaction = $post['user_reaction'];
                        $commentCount = intval($post['comment_count']);
                        $postId = $post['POSTS_ID'];

                        echo '<div class="post-stats">';
                        echo '<div class="post-likes-container" data-post-id="' . $postId . '">';

                        // Main like button with current reaction state
                        $likeButtonClass = $userReaction ? 'post-likes active' : 'post-likes';
                        echo '<div class="' . $likeButtonClass . '" data-user-reaction="' . ($userReaction ?: '') . '">';

                        // Like icon with proper fill state
                        $iconFill = $userReaction ? 'var(--primary-color)' : 'none';
                        $iconStroke = $userReaction ? 'var(--primary-color)' : 'currentColor';

                        echo '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="' . $iconFill . '" stroke="' . $iconStroke . '" class="like-icon" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">';
                        echo '<path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>';
                        echo '</svg>';

                        // Show reaction count with click handler to show users
                        if ($totalReactions > 0) {
                            echo '<span class="like-count" onclick="showReactionUsers(' . $postId . ')">';
                            echo $totalReactions . ($totalReactions === 1 ? ' reaction' : ' reactions');
                            echo '</span>';
                        } else {
                            echo '<span class="like-count">0 reactions</span>';
                        }
                        echo '</div>';

                        // Food reaction icons with enhanced interaction
                        echo '<div class="food-reactions" id="food-reactions-' . $postId . '">';

                        // Reaction icons with proper data attributes and click handlers
                        $reactions = [
                            'yummy' => ['emoji' => '🍔', 'title' => 'Yummy!'],
                            'delicious' => ['emoji' => '🍕', 'title' => 'Delicious!'],
                            'tasty' => ['emoji' => '🍰', 'title' => 'Tasty!'],
                            'love' => ['emoji' => '🍲', 'title' => 'Love it!'],
                            'amazing' => ['emoji' => '🍗', 'title' => 'Amazing!']
                        ];

                        foreach ($reactions as $type => $data) {
                            $isUserReaction = ($userReaction === $type) ? ' style="background-color: rgba(237, 90, 44, 0.2); transform: scale(1.1);"' : '';
                            echo '<div class="reaction-icon" data-reaction="' . $type . '" data-post-id="' . $postId . '" title="' . $data['title'] . '" onclick="handleReaction(' . $postId . ', \'' . $type . '\')"' . $isUserReaction . '>';
                            echo '<span>' . $data['emoji'] . '</span>';
                            echo '</div>';
                        }

                        echo '</div>';
                        echo '</div>';

                        // UPDATED Comments section with real count and click handler
                        echo '<div class="post-comments" style="cursor: pointer;" onclick="openCommentsModal(' . $postId . ')">';
                        echo '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">';
                        echo '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>';
                        echo '</svg>';

                        // Show actual comment count from database
                        if ($commentCount > 0) {
                            echo '<span id="comment-count-' . $postId . '">' . $commentCount . ($commentCount === 1 ? ' comment' : ' comments') . '</span>';
                        } else {
                            echo '<span id="comment-count-' . $postId . '">0 comments</span>';
                        }
                        echo '</div>';
                        echo '</div>';

                        echo '</div>'; // End post-card
                    }
                } else {
                    echo '<div class="no-posts-message">';
                    echo '<p>No posts yet. Be the first to share something!</p>';
                    echo '</div>';
                }
            } catch (PDOException $e) {
                echo '<div class="error-message">Error loading posts: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }

            echo '</div>'; // End posts-feed
            ?>
        </div>
        </div>
    </main>

    <!-- Image Modal -->
    <div id="imageModal" class="modal image-modal">
        <div class="image-modal-content">
            <span class="close-modal image-close-btn">&times;</span>
            <div class="image-container">
                <img id="modalImage" src="/placeholder.svg" alt="Full Size Image">
            </div>
        </div>
    </div>

    <!-- Edit Post Modal -->
    <div id="editPostModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h2>Edit Post</h2>
            <form id="editPostForm" method="post" enctype="multipart/form-data">
                <input type="hidden" id="edit_post_id" name="edit_post_id">
                <div class="form-group">
                    <textarea id="edit_description" name="edit_description" placeholder="What's recipe are you cooking today?"></textarea>
                </div>
                <div class="form-group">
                    <div class="image-preview-container" id="edit-image-preview-container">
                        <img id="edit-image-preview" src="/placeholder.svg" alt="Image Preview" style="display: none;">
                    </div>
                    <label for="edit_post_image" class="file-upload-label">
                        <span class="upload-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                <polyline points="21 15 16 10 5 21"></polyline>
                            </svg>
                        </span>
                        <span>Upload Image or Video</span>
                    </label>
                    <input type="file" id="edit_post_image" name="edit_post_image" accept="image/*,video/mp4" style="display: none;">
                    <input type="hidden" id="existing_image" name="existing_image">
                </div>
                <div class="form-actions">
                    <button type="submit" class="post-button">Update Post</button>
                </div>
            </form>
        </div>
    </div>



    <!-- Comments Modal -->
    <div id="commentsModal" class="comments-modal">
        <div class="comments-modal-content">
            <div class="comments-header">
                <h3>Comments</h3>
                <button class="close-comments" type="button">&times;</button>
            </div>

            <div class="comments-body" id="commentsBody">
                <!-- Comments will be loaded here dynamically -->
            </div>

            <div class="comment-form">
                <div class="comment-input-container">
                    <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Your avatar" class="comment-avatar">
                    <textarea
                        id="commentInput"
                        class="comment-input"
                        placeholder="Write a comment..."
                        rows="1"
                        maxlength="500"></textarea>
                    <button
                        id="commentSubmit"
                        class="comment-submit"
                        type="button"
                        disabled>
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="22" y1="2" x2="11" y2="13"></line>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Reaction Users Modal -->
    <!-- POST Reaction Users Modal -->
    <div id="postReactionUsersModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 500px; max-height: 70vh; overflow: hidden;">
            <span class="close-modal" style="cursor: pointer;">&times;</span>
            <h2 style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee;">People who reacted</h2>
            <div id="postReactionUsersList" class="reaction-users-list" style="max-height: 400px; overflow-y: auto;">
                <!-- Post reaction users will be loaded here dynamically -->
            </div>
        </div>
    </div>

    <!-- COMMENT Reaction Users Modal -->
    <div id="commentReactionUsersModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 500px; max-height: 70vh; overflow: hidden;">
            <span class="close-modal" style="cursor: pointer;">&times;</span>
            <h2 style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee;">People who reacted to this comment</h2>
            <div id="commentReactionUsersList" class="reaction-users-list" style="max-height: 400px; overflow-y: auto;">
                <!-- Comment reaction users will be loaded here dynamically -->
            </div>
        </div>
    </div>

    <script>
        // Enhanced JavaScript for dashboard functionality with fixed reactions
        document.addEventListener('DOMContentLoaded', function() {
            // Enhanced reaction handling function with better feedback
            window.handleReaction = function(postId, reactionType) {
                // Add loading state to the clicked reaction
                const clickedReactionIcon = document.querySelector(`[data-post-id="${postId}"] [data-reaction="${reactionType}"]`);
                if (clickedReactionIcon) {
                    clickedReactionIcon.style.opacity = '0.6';
                    clickedReactionIcon.style.pointerEvents = 'none';
                }

                const formData = new FormData();
                formData.append('action', 'add_reaction');
                formData.append('post_id', postId);
                formData.append('reaction_type', reactionType);

                fetch('post_reaction.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update the UI with new reaction data
                            updateReactionUI(postId, data.reaction_data, reactionType, data.action_type);

                            // Hide the reaction panel
                            const container = document.querySelector(`[data-post-id="${postId}"].post-likes-container`);
                            if (container) {
                                container.classList.remove('active');
                            }

                            // Show appropriate message
                            let message = '';
                            switch (data.action_type) {
                                case 'added':
                                    message = `You reacted with ${reactionType}! ✨`;
                                    break;
                                case 'updated':
                                    message = `Changed reaction to ${reactionType}! 🔄`;
                                    break;
                                case 'removed':
                                    message = `Reaction removed! 👋`;
                                    break;
                                default:
                                    message = 'Reaction updated!';
                            }
                            showToast(message, 'success');
                        } else {
                            showToast(data.message || 'Error adding reaction', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error handling reaction:', error);
                        showToast('An error occurred while adding the reaction.', 'error');
                    })
                    .finally(() => {
                        // Remove loading state
                        if (clickedReactionIcon) {
                            clickedReactionIcon.style.opacity = '1';
                            clickedReactionIcon.style.pointerEvents = 'auto';
                        }
                    });
            };

            // Enhanced UI update function with better visual feedback
            function updateReactionUI(postId, reactionData, newReaction, actionType) {
                const container = document.querySelector(`[data-post-id="${postId}"].post-likes-container`);
                if (!container) return;

                const likeButton = container.querySelector('.post-likes');
                const likeCount = container.querySelector('.like-count');
                const likeIcon = container.querySelector('.like-icon');

                // Update like button state based on user reaction
                if (reactionData.user_reaction) {
                    likeButton.classList.add('active');
                    likeButton.setAttribute('data-user-reaction', reactionData.user_reaction);
                    if (likeIcon) {
                        likeIcon.setAttribute('fill', 'var(--primary-color)');
                        likeIcon.setAttribute('stroke', 'var(--primary-color)');
                    }
                } else {
                    likeButton.classList.remove('active');
                    likeButton.setAttribute('data-user-reaction', '');
                    if (likeIcon) {
                        likeIcon.setAttribute('fill', 'none');
                        likeIcon.setAttribute('stroke', 'currentColor');
                    }
                }

                // Update reaction count with animation
                const totalReactions = reactionData.total_reactions;
                if (likeCount) {
                    // Add a subtle animation to the count change
                    likeCount.style.transform = 'scale(1.1)';
                    likeCount.style.transition = 'transform 0.2s ease';

                    setTimeout(() => {
                        if (totalReactions > 0) {
                            likeCount.textContent = totalReactions + (totalReactions === 1 ? ' reaction' : ' reactions');
                        } else {
                            likeCount.textContent = '0 reactions';
                        }

                        // Reset animation
                        likeCount.style.transform = 'scale(1)';
                    }, 100);
                }

                // Add animation to the clicked reaction icon
                const clickedReactionIcon = container.querySelector(`[data-reaction="${newReaction}"]`);
                if (clickedReactionIcon && actionType !== 'removed') {
                    clickedReactionIcon.classList.add('reaction-animation');

                    // Create a floating emoji effect
                    createFloatingEmoji(clickedReactionIcon, newReaction);

                    setTimeout(() => {
                        clickedReactionIcon.classList.remove('reaction-animation');
                    }, 400);
                }

                // Update visual state of reaction icons
                const allReactionIcons = container.querySelectorAll('.reaction-icon');
                allReactionIcons.forEach(icon => {
                    const iconReaction = icon.getAttribute('data-reaction');
                    if (iconReaction === reactionData.user_reaction) {
                        icon.style.backgroundColor = 'rgba(237, 90, 44, 0.2)';
                        icon.style.transform = 'scale(1.1)';
                    } else {
                        icon.style.backgroundColor = '';
                        icon.style.transform = '';
                    }
                });
            }

            // Create floating emoji animation effect
            function createFloatingEmoji(element, reactionType) {
                const emojiMap = {
                    'yummy': '🍔',
                    'delicious': '🍕',
                    'tasty': '🍰',
                    'love': '🍲',
                    'amazing': '🍗'
                };

                const emoji = emojiMap[reactionType];
                if (!emoji) return;

                const floatingEmoji = document.createElement('div');
                floatingEmoji.textContent = emoji;
                floatingEmoji.style.position = 'absolute';
                floatingEmoji.style.fontSize = '20px';
                floatingEmoji.style.pointerEvents = 'none';
                floatingEmoji.style.zIndex = '9999';
                floatingEmoji.style.animation = 'floatUp 2s ease-out forwards';

                // Position relative to the clicked element
                const rect = element.getBoundingClientRect();
                floatingEmoji.style.left = (rect.left + rect.width / 2) + 'px';
                floatingEmoji.style.top = (rect.top + window.scrollY) + 'px';

                document.body.appendChild(floatingEmoji);

                // Remove after animation
                setTimeout(() => {
                    if (floatingEmoji.parentNode) {
                        floatingEmoji.parentNode.removeChild(floatingEmoji);
                    }
                }, 2000);
            }

            // Test function to debug modal
            window.testModal = function() {
                console.log('Testing modal...');
                const modal = document.getElementById('reactionUsersModal');
                const usersList = document.getElementById('reactionUsersList');

                console.log('Modal element:', modal);
                console.log('UsersList element:', usersList);

                if (modal && usersList) {
                    usersList.innerHTML = '<p style="text-align: center; padding: 20px;">Test data loaded successfully!</p>';
                    modal.style.display = 'block';
                    console.log('Modal should be visible now');
                } else {
                    console.error('Modal elements not found');
                }
            };

            // FIXED: Enhanced function to show POST reaction users with correct modal
            window.showReactionUsers = function(postId) {
                console.log('🎯 showReactionUsers called with postId:', postId);

                const formData = new FormData();
                formData.append('action', 'get_reaction_users');
                formData.append('post_id', postId);

                // FIXED: Use the correct modal ID for POST reactions  
                const modal = document.getElementById('postReactionUsersModal');
                const usersList = document.getElementById('postReactionUsersList');

                console.log('Modal found:', !!modal, 'UsersList found:', !!usersList);

                if (!modal) {
                    console.error('❌ postReactionUsersModal not found - Check your HTML structure');
                    // Fallback: try to create the modal dynamically
                    createPostReactionModal();
                    return showReactionUsers(postId); // Retry after creating modal
                }

                if (!usersList) {
                    console.error('❌ postReactionUsersList not found');
                    return;
                }

                function createPostReactionModal() {
                    console.log('🔧 Creating missing post reaction modal...');

                    const existingModal = document.getElementById('postReactionUsersModal');
                    if (existingModal) return;

                    const modalHTML = `
        <div id="postReactionUsersModal" class="modal" style="display: none;">
            <div class="modal-content" style="max-width: 500px; max-height: 70vh; overflow: hidden;">
                <span class="close-modal" style="cursor: pointer;">&times;</span>
                <h2 style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee;">People who reacted</h2>
                <div id="postReactionUsersList" class="reaction-users-list" style="max-height: 400px; overflow-y: auto;">
                    <!-- Post reaction users will be loaded here -->
                </div>
            </div>
        </div>
    `;

                    document.body.insertAdjacentHTML('beforeend', modalHTML);

                    // Add event listeners to the new modal
                    const modal = document.getElementById('postReactionUsersModal');
                    const closeBtn = modal.querySelector('.close-modal');

                    closeBtn.addEventListener('click', function() {
                        modal.style.display = 'none';
                    });

                    modal.addEventListener('click', function(e) {
                        if (e.target === modal) {
                            modal.style.display = 'none';
                        }
                    });

                    console.log('✅ Post reaction modal created successfully');
                }

                // ENSURE this runs when DOM is loaded
                document.addEventListener('DOMContentLoaded', function() {
                    // FIXED: Initialize post reaction modal handlers
                    const postReactionModal = document.getElementById('postReactionUsersModal');
                    const postReactionCloseBtn = postReactionModal?.querySelector('.close-modal');

                    if (postReactionCloseBtn) {
                        postReactionCloseBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            console.log('✅ Post reaction modal close button clicked');
                            postReactionModal.style.display = 'none';
                        });
                    }

                    if (postReactionModal) {
                        postReactionModal.addEventListener('click', function(event) {
                            if (event.target === postReactionModal) {
                                console.log('✅ Post reaction modal backdrop clicked');
                                postReactionModal.style.display = 'none';
                            }
                        });
                    }

                    // Check if modal exists, if not create it
                    if (!postReactionModal) {
                        console.log('⚠️ Post reaction modal not found, creating it...');
                        createPostReactionModal();
                    }

                    console.log('✅ Post reaction system initialized');
                });
                // Show loading state
                usersList.innerHTML = `
        <div style="text-align: center; padding: 30px;">
            <div class="loading-spinner" style="margin: 0 auto 15px;"></div>
            <p style="color: #666;">Loading reactions...</p>
        </div>
    `;
                modal.style.display = 'block';

                // Fetch reactions
                fetch('post_reaction.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        console.log('Response status:', response.status);
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('✅ Response data:', data);
                        if (data.success && data.users) {
                            displayPostReactionUsers(data.users);
                        } else {
                            throw new Error(data.message || 'No reaction data found');
                        }
                    })
                    .catch(error => {
                        console.error('❌ Error fetching reactions:', error);
                        usersList.innerHTML = `
            <div style="text-align: center; padding: 30px; color: #f44336;">
                <p style="margin-bottom: 15px;">Failed to load reactions</p>
                <button onclick="showReactionUsers(${postId})" 
                        style="background: #ED5A2C; color: white; border: none; padding: 8px 16px; border-radius: 15px; cursor: pointer;">
                    Try Again
                </button>
            </div>
        `;
                    });
            };
            // FIXED: Display function for POST reactions only
            function displayPostReactionUsers(users) {
                console.log('🎨 displayPostReactionUsers called with', users?.length, 'users');

                const usersList = document.getElementById('postReactionUsersList');
                if (!usersList) {
                    console.error('❌ postReactionUsersList element not found');
                    return;
                }

                if (!users || users.length === 0) {
                    usersList.innerHTML = `
            <div style="text-align: center; padding: 40px; color: #666;">
                <div style="font-size: 48px; margin-bottom: 15px;">😔</div>
                <p style="font-size: 16px;">No reactions yet</p>
                <p style="font-size: 14px; opacity: 0.8;">Be the first to react!</p>
            </div>
        `;
                    return;
                }

                let html = '';
                users.forEach((user, index) => {
                    const userImage = user.PROFILE_IMAGE || 'images/default-profile.png';
                    const reactionTime = formatReactionTime(user.CREATED_AT);
                    const reactionType = user.REACTION_TYPE?.charAt(0).toUpperCase() + user.REACTION_TYPE?.slice(1) || 'Like';
                    const reactionEmoji = user.REACTION_EMOJI || '👍';

                    html += `
            <div class="reaction-user-item" style="display: flex; align-items: center; padding: 15px; border-bottom: 1px solid #eee; transition: all 0.2s ease; cursor: pointer;">
                <img src="${userImage}" 
                     alt="${user.NAME || 'User'}" 
                     class="reaction-user-avatar" 
                     style="width: 50px; height: 50px; border-radius: 50%; margin-right: 15px; object-fit: cover; border: 2px solid #f0f0f0; transition: all 0.2s ease;"
                     onerror="this.src='images/default-profile.png'">
                <div class="reaction-user-info" style="flex: 1;">
                    <div class="reaction-user-name" style="font-weight: 600; margin-bottom: 5px; color: #333; font-size: 16px;">
                        ${user.NAME || 'Unknown User'}
                    </div>
                    <div class="reaction-user-time" style="font-size: 13px; color: #666;">
                        Reacted ${reactionTime}
                    </div>
                </div>
                <div class="reaction-user-emoji" style="font-size: 28px; margin-left: 15px; display: flex; flex-direction: column; align-items: center;">
                    <span style="margin-bottom: 2px;">${reactionEmoji}</span>
                    <small style="font-size: 11px; color: #888; text-transform: capitalize; font-weight: 500;">
                        ${reactionType}
                    </small>
                </div>
            </div>
        `;
                });

                usersList.innerHTML = html;

                

                console.log('✅ Post reaction users displayed successfully');
            }

            // Helper function to format reaction time
            function formatReactionTime(dateString) {
                const now = new Date();
                const reactionDate = new Date(dateString);
                const diffInSeconds = Math.floor((now - reactionDate) / 1000);

                if (diffInSeconds < 60) {
                    return 'just now';
                } else if (diffInSeconds < 3600) {
                    const minutes = Math.floor(diffInSeconds / 60);
                    return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
                } else if (diffInSeconds < 86400) {
                    const hours = Math.floor(diffInSeconds / 3600);
                    return `${hours} hour${hours > 1 ? 's' : ''} ago`;
                } else if (diffInSeconds < 604800) {
                    const days = Math.floor(diffInSeconds / 86400);
                    return `${days} day${days > 1 ? 's' : ''} ago`;
                } else {
                    return reactionDate.toLocaleDateString();
                }
            }

            // Enhanced mobile touch support for reactions
            const likesContainers = document.querySelectorAll('.post-likes-container');

            likesContainers.forEach(container => {
                let touchStartTime = 0;

                // Handle touch start
                container.addEventListener('touchstart', function(e) {
                    touchStartTime = Date.now();
                });

                // Handle touch end with duration check
                container.addEventListener('touchend', function(e) {
                    const touchDuration = Date.now() - touchStartTime;

                    // Short tap - toggle reactions panel
                    if (touchDuration < 200) {
                        e.preventDefault();
                        if (this.classList.contains('active')) {
                            this.classList.remove('active');
                        } else {
                            // Remove active class from all containers
                            likesContainers.forEach(c => c.classList.remove('active'));
                            // Add active class to the current container
                            this.classList.add('active');
                        }
                    }
                });

                // Handle long press for direct like
                let longPressTimer;
                container.addEventListener('touchstart', function(e) {
                    longPressTimer = setTimeout(() => {
                        // Long press detected - directly like with default reaction
                        const postId = this.getAttribute('data-post-id');
                        if (postId) {
                            handleReaction(postId, 'yummy');
                            this.classList.remove('active');

                            // Provide haptic feedback if available
                            if ('vibrate' in navigator) {
                                navigator.vibrate(50);
                            }
                        }
                    }, 500);
                });

                container.addEventListener('touchend', function() {
                    clearTimeout(longPressTimer);
                });

                container.addEventListener('touchmove', function() {
                    clearTimeout(longPressTimer);
                });
            });

            // Close reactions when tapping elsewhere
            document.addEventListener('touchstart', function(e) {
                const activeContainers = document.querySelectorAll('.post-likes-container.active');
                activeContainers.forEach(container => {
                    if (!container.contains(e.target)) {
                        container.classList.remove('active');
                    }
                });
            });

            // Close reaction users modal functionality
            const reactionModal = document.getElementById('reactionUsersModal');
            const reactionCloseBtn = reactionModal?.querySelector('.close-modal');

            if (reactionCloseBtn) {
                reactionCloseBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log('Close button clicked'); // Debug log
                    reactionModal.style.display = 'none';
                });
            }

            if (reactionModal) {
                window.addEventListener('click', function(event) {
                    if (event.target === reactionModal) {
                        console.log('Modal backdrop clicked'); // Debug log
                        reactionModal.style.display = 'none';
                    }
                });
            }

            // Add a global click handler for debugging
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('like-count')) {
                    console.log('Like count clicked:', e.target);
                    e.stopPropagation();
                }
            });

            // Image modal functionality
            const imageModal = document.getElementById('imageModal');
            const modalImage = document.getElementById('modalImage');
            const imageCloseBtn = document.querySelector('.image-close-btn');

            // Function to open image modal
            window.openImageModal = function(imageSrc) {
                modalImage.src = imageSrc;
                imageModal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            };

            // Close image modal when clicking the close button
            if (imageCloseBtn) {
                imageCloseBtn.addEventListener('click', function() {
                    imageModal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                });
            }

            // Close image modal when clicking outside the image
            window.addEventListener('click', function(event) {
                if (event.target === imageModal) {
                    imageModal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            });

            // Post menu toggle functionality
            window.togglePostMenu = function(postId) {
                const menu = document.getElementById('post-menu-' + postId);
                if (menu) {
                    // Close all other open menus first
                    document.querySelectorAll('.post-menu-dropdown').forEach(function(dropdown) {
                        if (dropdown.id !== 'post-menu-' + postId && dropdown.style.display === 'block') {
                            dropdown.style.display = 'none';
                        }
                    });

                    // Toggle the clicked menu
                    menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
                }
            };

            // Close menus when clicking outside
            document.addEventListener('click', function(event) {
                if (!event.target.closest('.post-actions')) {
                    document.querySelectorAll('.post-menu-dropdown').forEach(function(dropdown) {
                        dropdown.style.display = 'none';
                    });
                }
            });

            // Edit post functionality
            const editModal = document.getElementById('editPostModal');
            const editForm = document.getElementById('editPostForm');

            window.editPost = function(postId, description, mediaUrl) {
                document.getElementById('edit_post_id').value = postId;
                document.getElementById('edit_description').value = description || '';
                document.getElementById('existing_image').value = mediaUrl || '';

                // Show media preview if exists
                const previewContainer = document.getElementById('edit-image-preview-container');

                if (mediaUrl) {
                    // Check if it's a video or an image based on file extension
                    const isVideo = mediaUrl.toLowerCase().endsWith('.mp4');

                    if (isVideo) {
                        // Create a video element for preview
                        previewContainer.innerHTML = `
                            <video controls id="edit-video-preview" style="max-width: 100%; max-height: 300px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); display: block;">
                                <source src="${mediaUrl}" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>
                        `;
                    } else {
                        // Display image
                        previewContainer.innerHTML = `
                            <img id="edit-image-preview" src="${mediaUrl}" alt="Image Preview" style="display: block; max-width: 100%; max-height: 300px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);">
                        `;
                    }
                } else {
                    // Reset the container to show the default empty image element
                    previewContainer.innerHTML = `
                        <img id="edit-image-preview" src="/placeholder.svg" alt="Image Preview" style="display: none; max-width: 100%; max-height: 300px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);">
                    `;
                }

                // Show the modal
                editModal.style.display = 'block';

                // Close any open menus
                document.querySelectorAll('.post-menu-dropdown').forEach(function(dropdown) {
                    dropdown.style.display = 'none';
                });
            };

            // Close modal when clicking X
            const closeModalBtns = document.querySelectorAll('.close-modal');
            closeModalBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const modal = this.closest('.modal');
                    if (modal) {
                        modal.style.display = 'none';
                    }
                });
            });

            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                const modals = document.querySelectorAll('.modal');
                modals.forEach(modal => {
                    if (event.target === modal) {
                        modal.style.display = 'none';
                    }
                });
            });

            // Handle edit post media preview (image or video)
            const editImageInput = document.getElementById('edit_post_image');
            const editImagePreviewContainer = document.getElementById('edit-image-preview-container');

            if (editImageInput && editImagePreviewContainer) {
                editImageInput.addEventListener('change', function() {
                    const file = this.files[0];
                    if (file) {
                        // Check if the file is a video
                        if (file.type.startsWith('video/')) {
                            // Create a video element for preview
                            editImagePreviewContainer.innerHTML = `
                                <video controls id="edit-video-preview" style="max-width: 100%; max-height: 300px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); display: block;">
                                    <source src="${URL.createObjectURL(file)}" type="${file.type}">
                                    Your browser does not support the video tag.
                                </video>
                            `;
                        } else {
                            // Handle image files
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                // Reset the container with the image
                                editImagePreviewContainer.innerHTML = `
                                    <img id="edit-image-preview" src="${e.target.result}" alt="Image Preview" style="display: block; max-width: 100%; max-height: 300px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);">
                                `;
                            };
                            reader.readAsDataURL(file);
                        }
                    }
                });
            }

            // Handle edit form submission
            if (editForm) {
                editForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    formData.append('action', 'edit_post');

                    // Show loading toast
                    showToast('Updating post...', 'success');

                    fetch('dashboard.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Show success toast before reloading
                                showToast('Post updated successfully!', 'success');
                                // Give the toast a moment to be seen before reload
                                setTimeout(() => {
                                    window.location.reload();
                                }, 1000);
                            } else {
                                // Show error toast
                                showToast(data.message || 'Error updating post', 'error');
                            }
                        })
                        .catch(error => {
                            // Show error toast instead of console error and alert
                            showToast('An error occurred while updating the post.', 'error');
                        });
                });
            }

            // Create a toast notification function
            function showToast(message, type = 'success') {
                // Create toast container if it doesn't exist
                let toastContainer = document.getElementById('toast-container');
                if (!toastContainer) {
                    toastContainer = document.createElement('div');
                    toastContainer.id = 'toast-container';
                    toastContainer.style.position = 'fixed';
                    toastContainer.style.bottom = '20px';
                    toastContainer.style.right = '20px';
                    toastContainer.style.zIndex = '9999';
                    document.body.appendChild(toastContainer);
                }

                // Create toast element
                const toast = document.createElement('div');
                toast.className = `toast ${type}`;
                toast.style.minWidth = '250px';
                toast.style.margin = '10px';
                toast.style.padding = '15px 20px';
                toast.style.borderRadius = '4px';
                toast.style.boxShadow = '0 2px 10px rgba(0,0,0,0.2)';
                toast.style.backgroundColor = type === 'success' ? '#4CAF50' : '#ED5A2C';
                toast.style.color = 'white';
                toast.style.display = 'flex';
                toast.style.alignItems = 'center';
                toast.style.justifyContent = 'space-between';
                toast.style.animation = 'fadeIn 0.5s, fadeOut 0.5s 2.5s';
                toast.style.animationFillMode = 'forwards';

                // Add styles for animations
                const style = document.createElement('style');
                style.textContent = `
                    @keyframes fadeIn {
                        from { opacity: 0; transform: translateY(20px); }
                        to { opacity: 1; transform: translateY(0); }
                    }
                    @keyframes fadeOut {
                        from { opacity: 1; transform: translateY(0); }
                        to { opacity: 0; transform: translateY(20px); }
                    }
                `;
                document.head.appendChild(style);

                // Add content
                const icon = document.createElement('span');
                icon.innerHTML = type === 'success' ?
                    '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>' :
                    '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>';
                icon.style.marginRight = '10px';

                const messageSpan = document.createElement('span');
                messageSpan.textContent = message;
                messageSpan.style.flex = '1';

                const closeBtn = document.createElement('span');
                closeBtn.innerHTML = '&times;';
                closeBtn.style.marginLeft = '10px';
                closeBtn.style.cursor = 'pointer';
                closeBtn.style.fontSize = '20px';
                closeBtn.onclick = function() {
                    toast.remove();
                };

                toast.appendChild(icon);
                toast.appendChild(messageSpan);
                toast.appendChild(closeBtn);

                // Add to container
                toastContainer.appendChild(toast);

                // Auto remove after 3 seconds
                setTimeout(() => {
                    toast.remove();
                }, 3000);
            }

            // Delete post functionality
            window.deletePost = function(postId) {
                if (confirm('Are you sure you want to delete this post? This action cannot be undone.')) {
                    const formData = new FormData();
                    formData.append('action', 'delete_post');
                    formData.append('post_id', postId);

                    fetch('dashboard.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Remove the post from the DOM
                                const postElement = document.querySelector(`[data-post-id="${postId}"]`);
                                if (postElement) {
                                    postElement.remove();
                                    // Show success notification
                                    showToast('Post deleted successfully!', 'success');
                                } else {
                                    // Reload the page if we can't find the element
                                    window.location.reload();
                                }
                            } else {
                                // Show error notification instead of alert
                                showToast(data.message || 'Error deleting post', 'error');
                            }
                        })
                        .catch(error => {
                            // Show error notification instead of console error and alert
                            showToast('An error occurred while deleting the post.', 'error');
                        });
                }

                // Close any open menus
                document.querySelectorAll('.post-menu-dropdown').forEach(function(dropdown) {
                    dropdown.style.display = 'none';
                });
            };

            // Handle media preview for new posts (image or video)
            const postImage = document.getElementById('postImage');
            const imagePreview = document.getElementById('imagePreview');
            const imagePreviewContainer = document.getElementById('imagePreviewContainer');
            const removeImage = document.getElementById('removeImage');
            const postDescription = document.getElementById('postDescription');
            const postButton = document.getElementById('postButton');

            if (postImage && imagePreviewContainer) {
                // Initially hide the preview container
                imagePreviewContainer.style.display = 'none';

                postImage.addEventListener('change', function() {
                    const file = this.files[0];
                    if (file) {
                        // Check if the file is a video
                        if (file.type.startsWith('video/')) {
                            // Create a video element instead of using the image
                            imagePreviewContainer.innerHTML = `
                                <div style="position: relative; display: inline-block;">
                                    <video controls style="max-width: 100%; max-height: 300px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);">
                                        <source src="${URL.createObjectURL(file)}" type="${file.type}">
                                        Your browser does not support the video tag.
                                    </video>
                                    <button type="button" class="remove-image" id="removeVideoBtn">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <line x1="18" y1="6" x2="6" y2="18"></line>
                                            <line x1="6" y1="6" x2="18" y2="18"></line>
                                        </svg>
                                    </button>
                                </div>
                            `;

                            // Show the preview container
                            imagePreviewContainer.style.display = 'block';

                            // Add event listener to the new remove button
                            document.getElementById('removeVideoBtn').addEventListener('click', function() {
                                imagePreviewContainer.innerHTML = `
                                    <div style="position: relative; display: inline-block;">
                                        <img src="/placeholder.svg" alt="Preview" class="image-preview" id="imagePreview">
                                        <button type="button" class="remove-image" id="removeImage">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                                <line x1="6" y1="6" x2="18" y2="18"></line>
                                            </svg>
                                        </button>
                                    </div>
                                `;
                                imagePreviewContainer.style.display = 'none';
                                postImage.value = '';
                                updatePostButtonState();
                            });
                        } else {
                            // Handle image files as before
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                // Reset the container to its original state with an image
                                imagePreviewContainer.innerHTML = `
                                    <div style="position: relative; display: inline-block;">
                                        <img src="${e.target.result}" alt="Preview" class="image-preview" id="imagePreview">
                                        <button type="button" class="remove-image" id="removeImage">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                                <line x1="6" y1="6" x2="18" y2="18"></line>
                                            </svg>
                                        </button>
                                    </div>
                                `;
                                imagePreviewContainer.style.display = 'block';

                                // Add event listener to the new remove button
                                document.getElementById('removeImage').addEventListener('click', function() {
                                    imagePreviewContainer.style.display = 'none';
                                    postImage.value = '';
                                    updatePostButtonState();
                                });
                            };
                            reader.readAsDataURL(file);
                        }
                        updatePostButtonState();
                    }
                });
            }

            // Update post button state
            function updatePostButtonState() {
                if (postDescription && postButton && postImage) {
                    const hasDescription = postDescription.value.trim() !== '';
                    const hasImage = postImage.files.length > 0;
                    postButton.disabled = !hasDescription && !hasImage;
                }
            }

            // Update button state when description changes
            if (postDescription) {
                postDescription.addEventListener('input', updatePostButtonState);
                // Initial button state
                updatePostButtonState();
            }

            // Handle tab switching
            const tabs = document.querySelectorAll('.post-tab');
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // If Make Recipe tab is clicked, redirect to recipes.php
                    if (this.textContent === 'Make Recipe') {
                        window.location.href = 'recipes.php?openRecipeForm=1';
                    }
                });
            });
        });

        // REPLACE the comments JavaScript in your dashboard.php with this optimized version:

        // ========================================
        // ENHANCED COMMENTS SYSTEM - COMPLETE JAVASCRIPT
        // ========================================

        // Global variables for comment system
        let currentPostId = null;
        let commentsLoading = false;
        let currentOffset = 0;
        let hasMoreComments = false;
        let totalCommentsCount = 0;
        const commentsPerLoad = 10;

        // FIXED: Enhanced function to show POST reaction users with separate modal
        // REPLACE your existing showReactionUsers function with this FIXED version:

        // FIXED: Enhanced function to show POST reaction users with separate modal
        window.showReactionUsers = function(postId) {
            console.log('showReactionUsers called with postId:', postId);

            const formData = new FormData();
            formData.append('action', 'get_reaction_users');
            formData.append('post_id', postId);

            // FIXED: Use the correct modal ID for POST reactions
            const modal = document.getElementById('postReactionUsersModal');
            const usersList = document.getElementById('postReactionUsersList');
            const modalTitle = modal?.querySelector('h2');

            if (!modal) {
                console.error('❌ postReactionUsersModal not found - Check your HTML');
                return;
            }

            if (!usersList) {
                console.error('❌ postReactionUsersList not found - Check your HTML');
                return;
            }

            // Set the correct title for POST reactions
            if (modalTitle) {
                modalTitle.textContent = 'People who reacted';
            }

            usersList.innerHTML = '<p style="text-align: center; padding: 20px; color: #666;">Loading reactions...</p>';
            modal.style.display = 'block';

            fetch('post_reaction.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Response data:', data);
                    if (data.success) {
                        displayPostReactionUsers(data.users);
                    } else {
                        usersList.innerHTML = '<p style="text-align: center; padding: 20px; color: #f44336;">Error: ' + (data.message || 'Failed to load reactions') + '</p>';
                        console.error('API Error:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Fetch Error:', error);
                    usersList.innerHTML = '<p style="text-align: center; padding: 20px; color: #f44336;">Network error occurred</p>';
                });
        };

        // FIXED: Separate display function for POST reactions
        function displayPostReactionUsers(users) {
            console.log('displayPostReactionUsers called with users:', users);

            const modal = document.getElementById('postReactionUsersModal');
            const usersList = document.getElementById('postReactionUsersList');

            if (!usersList) {
                console.error('postReactionUsersList element not found');
                return;
            }

            if (!users || users.length === 0) {
                usersList.innerHTML = '<p style="text-align: center; padding: 30px; color: #666; font-size: 16px;">No reactions yet</p>';
            } else {
                console.log('Displaying', users.length, 'users');
                let usersHTML = '';
                users.forEach((user, index) => {
                    console.log('Processing user', index, ':', user);
                    const userImage = user.PROFILE_IMAGE || 'images/default-profile.png';
                    const reactionTime = formatReactionTime(user.CREATED_AT);
                    const reactionTypeCapitalized = user.REACTION_TYPE ? user.REACTION_TYPE.charAt(0).toUpperCase() + user.REACTION_TYPE.slice(1) : 'Unknown';
                    const reactionEmoji = user.REACTION_EMOJI || '👍';

                    usersHTML += `
                <div class="reaction-user-item" style="display: flex; align-items: center; padding: 15px; border-bottom: 1px solid #eee; transition: background-color 0.2s; cursor: pointer;">
                    <img src="${userImage}" alt="${user.NAME || 'User'}" class="reaction-user-avatar" 
                         style="width: 50px; height: 50px; border-radius: 50%; margin-right: 15px; object-fit: cover; border: 2px solid #f0f0f0;"
                         onerror="this.src='images/default-profile.png'">
                    <div class="reaction-user-info" style="flex: 1;">
                        <div class="reaction-user-name" style="font-weight: 600; margin-bottom: 5px; color: #333; font-size: 16px;">${user.NAME || 'Unknown User'}</div>
                        <div class="reaction-user-time" style="font-size: 13px; color: #666;">Reacted ${reactionTime}</div>
                    </div>
                    <div class="reaction-user-emoji" style="font-size: 28px; margin-left: 15px; display: flex; flex-direction: column; align-items: center;">
                        <span>${reactionEmoji}</span>
                        <small style="font-size: 11px; color: #888; margin-top: 3px; text-transform: capitalize;">${reactionTypeCapitalized}</small>
                    </div>
                </div>
            `;
                });
                usersList.innerHTML = usersHTML;

   
            }

            if (modal) {
                modal.style.display = 'block';
                // Add backdrop click to close
                modal.onclick = function(e) {
                    if (e.target === modal) {
                        modal.style.display = 'none';
                    }
                };
            }
        }

        // FIXED: Separate display function for POST reactions
        function displayPostReactionUsers(users) {
            console.log('displayPostReactionUsers called with users:', users);

            const modal = document.getElementById('postReactionUsersModal');
            const usersList = document.getElementById('postReactionUsersList');

            if (!usersList) {
                console.error('postReactionUsersList element not found');
                return;
            }

            if (!users || users.length === 0) {
                usersList.innerHTML = '<p style="text-align: center; padding: 30px; color: #666; font-size: 16px;">No reactions yet</p>';
            } else {
                console.log('Displaying', users.length, 'users');
                let usersHTML = '';
                users.forEach((user, index) => {
                    console.log('Processing user', index, ':', user);
                    const userImage = user.PROFILE_IMAGE || 'images/default-profile.png';
                    const reactionTime = formatReactionTime(user.CREATED_AT);
                    const reactionTypeCapitalized = user.REACTION_TYPE ? user.REACTION_TYPE.charAt(0).toUpperCase() + user.REACTION_TYPE.slice(1) : 'Unknown';
                    const reactionEmoji = user.REACTION_EMOJI || '👍';

                    usersHTML += `
                <div class="reaction-user-item" style="display: flex; align-items: center; padding: 15px; border-bottom: 1px solid #eee; transition: background-color 0.2s; cursor: pointer;">
                    <img src="${userImage}" alt="${user.NAME || 'User'}" class="reaction-user-avatar" 
                         style="width: 50px; height: 50px; border-radius: 50%; margin-right: 15px; object-fit: cover; border: 2px solid #f0f0f0;"
                         onerror="this.src='images/default-profile.png'">
                    <div class="reaction-user-info" style="flex: 1;">
                        <div class="reaction-user-name" style="font-weight: 600; margin-bottom: 5px; color: #333; font-size: 16px;">${user.NAME || 'Unknown User'}</div>
                        <div class="reaction-user-time" style="font-size: 13px; color: #666;">Reacted ${reactionTime}</div>
                    </div>
                    <div class="reaction-user-emoji" style="font-size: 28px; margin-left: 15px; display: flex; flex-direction: column; align-items: center;">
                        <span>${reactionEmoji}</span>
                        <small style="font-size: 11px; color: #888; margin-top: 3px; text-transform: capitalize;">${reactionTypeCapitalized}</small>
                    </div>
                </div>
            `;
                });
                usersList.innerHTML = usersHTML;

                // Add hover effects
                usersList.querySelectorAll('.reaction-user-item').forEach(item => {
                    item.addEventListener('mouseenter', function() {
                        this.style.backgroundColor = '#f8f9fa';
                    });
                    item.addEventListener('mouseleave', function() {
                        this.style.backgroundColor = 'transparent';
                    });
                });
            }

            if (modal) {
                modal.style.display = 'block';
                // Add backdrop click to close
                modal.onclick = function(e) {
                    if (e.target === modal) {
                        modal.style.display = 'none';
                    }
                };
            }
        }

        // Helper function to format reaction time
        function formatReactionTime(dateString) {
            const now = new Date();
            const reactionDate = new Date(dateString);
            const diffInSeconds = Math.floor((now - reactionDate) / 1000);

            if (diffInSeconds < 60) {
                return 'just now';
            } else if (diffInSeconds < 3600) {
                const minutes = Math.floor(diffInSeconds / 60);
                return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
            } else if (diffInSeconds < 86400) {
                const hours = Math.floor(diffInSeconds / 3600);
                return `${hours} hour${hours > 1 ? 's' : ''} ago`;
            } else if (diffInSeconds < 604800) {
                const days = Math.floor(diffInSeconds / 86400);
                return `${days} day${days > 1 ? 's' : ''} ago`;
            } else {
                return reactionDate.toLocaleDateString();
            }
        }

        // Initialize comments system when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            initializeCommentsSystem();
        });

        // Main initialization function
        function initializeCommentsSystem() {
            console.log('🚀 Initializing Enhanced Comments System...');

            // Initialize modal elements
            const commentsModal = document.getElementById('commentsModal');
            const closeButton = document.querySelector('.close-comments');
            const commentInput = document.getElementById('commentInput');
            const commentSubmit = document.getElementById('commentSubmit');

            // Close button event listener
            if (closeButton) {
                closeButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    closeCommentsModal();
                });
            }

            // Modal backdrop click to close
            if (commentsModal) {
                commentsModal.addEventListener('click', function(e) {
                    if (e.target === commentsModal) {
                        closeCommentsModal();
                    }
                });

                // Prevent modal content clicks from closing modal
                const modalContent = commentsModal.querySelector('.comments-modal-content');
                if (modalContent) {
                    modalContent.addEventListener('click', function(e) {
                        e.stopPropagation();
                    });
                }
            }

            // Comment input event listeners
            if (commentInput) {
                // Auto-resize textarea
                commentInput.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = (this.scrollHeight) + 'px';
                    updateCommentSubmitButton();
                });

                // Enter key to submit (Shift+Enter for new line)
                commentInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        if (this.value.trim() && !commentSubmit.disabled) {
                            addComment();
                        }
                    }
                });

                // Focus event
                commentInput.addEventListener('focus', function() {
                    this.parentElement.style.borderColor = 'var(--primary-color)';
                });

                // Blur event
                commentInput.addEventListener('blur', function() {
                    this.parentElement.style.borderColor = '#e0e0e0';
                });
            }

            // Comment submit button event listener
            if (commentSubmit) {
                commentSubmit.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (!this.disabled && commentInput && commentInput.value.trim()) {
                        addComment();
                    }
                });
            }

            // Initialize keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Escape key to close modal
                if (e.key === 'Escape' && commentsModal && commentsModal.style.display === 'block') {
                    closeCommentsModal();
                }
            });

            // Initialize comment reaction handlers
            initializeCommentReactionHandlers();

            // FIXED: Close POST reaction users modal functionality
            const postReactionModal = document.getElementById('postReactionUsersModal');
            const postReactionCloseBtn = postReactionModal?.querySelector('.close-modal');

            if (postReactionCloseBtn) {
                postReactionCloseBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log('Post reaction modal close button clicked');
                    postReactionModal.style.display = 'none';
                });
            }

            if (postReactionModal) {
                window.addEventListener('click', function(event) {
                    if (event.target === postReactionModal) {
                        console.log('Post reaction modal backdrop clicked');
                        postReactionModal.style.display = 'none';
                    }
                });
            }

            // FIXED: Close COMMENT reaction users modal functionality
            const commentReactionModal = document.getElementById('commentReactionUsersModal');
            const commentReactionCloseBtn = commentReactionModal?.querySelector('.close-modal');

            if (commentReactionCloseBtn) {
                commentReactionCloseBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log('Comment reaction modal close button clicked');
                    commentReactionModal.style.display = 'none';
                });
            }

            if (commentReactionModal) {
                window.addEventListener('click', function(event) {
                    if (event.target === commentReactionModal) {
                        console.log('Comment reaction modal backdrop clicked');
                        commentReactionModal.style.display = 'none';
                    }
                });
            }

            console.log('✅ Comments System Initialized Successfully!');
        }

        // Function to open comments modal
        function openCommentsModal(postId) {
            console.log('📖 Opening comments modal for post:', postId);

            currentPostId = postId;
            currentOffset = 0;
            hasMoreComments = false;
            totalCommentsCount = 0;

            const modal = document.getElementById('commentsModal');
            const commentsBody = document.getElementById('commentsBody');
            const commentInput = document.getElementById('commentInput');

            if (!modal || !commentsBody) {
                console.error('❌ Comments modal elements not found');
                return;
            }

            // Show modal immediately
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';

            // Clear previous content and show loading
            commentsBody.innerHTML = `
        <div style="text-align: center; padding: 40px; color: #666;">
            <div class="loading-spinner" style="margin: 0 auto 15px;"></div>
            <p>Loading comments...</p>
        </div>
    `;

            // Reset comment input
            if (commentInput) {
                commentInput.value = '';
                commentInput.style.height = 'auto';
            }

            updateCommentSubmitButton();

            // Load initial comments
            loadComments(postId, true);

            // Focus on comment input after a short delay
            setTimeout(() => {
                if (commentInput) {
                    commentInput.focus();
                }
            }, 500);
        }

        // Function to close comments modal
        function closeCommentsModal() {
            console.log('🔒 Closing comments modal');

            const modal = document.getElementById('commentsModal');
            if (modal) {
                modal.style.display = 'none';
            }

            document.body.style.overflow = 'auto';
            currentPostId = null;
            currentOffset = 0;
            hasMoreComments = false;

            // Close any open reaction panels
            closeAllCommentReactionPanels();
        }

        // Enhanced function to load comments with pagination
        function loadComments(postId, isFirstLoad = false) {
            if (commentsLoading) {
                console.log('⏳ Comments already loading, skipping...');
                return;
            }

            console.log(`📥 Loading comments for post ${postId}, first load: ${isFirstLoad}`);
            commentsLoading = true;

            const formData = new FormData();
            formData.append('action', 'get_comments');
            formData.append('post_id', postId);
            formData.append('offset', isFirstLoad ? 0 : currentOffset);
            formData.append('limit', commentsPerLoad);

            // Update load more button if not first load
            if (!isFirstLoad) {
                const loadMoreBtn = document.getElementById('loadMoreCommentsBtn');
                if (loadMoreBtn) {
                    loadMoreBtn.disabled = true;
                    loadMoreBtn.innerHTML = '<div class="loading-spinner small"></div>';
                }
            }

            fetch('comments.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    // Check if response is actually JSON
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        throw new Error('Response is not JSON');
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('📨 Comments response:', data);

                    if (data.success) {
                        // Update pagination variables
                        currentOffset = data.next_offset || 0;
                        hasMoreComments = data.has_more || false;
                        totalCommentsCount = data.total_comments || 0;

                        if (isFirstLoad) {
                            displayComments(data.comments, data.reactions, true);
                        } else {
                            appendComments(data.comments);
                        }
                    } else {
                        throw new Error(data.message || 'Failed to load comments');
                    }
                })
                .catch(error => {
                    console.error('❌ Error loading comments:', error);
                    if (isFirstLoad) {
                        showCommentsError('Failed to load comments. Please try again.');
                    } else {
                        showToast('Failed to load more comments. Please try again.', 'error');
                    }
                })
                .finally(() => {
                    commentsLoading = false;

                    // Reset load more button
                    if (!isFirstLoad) {
                        const loadMoreBtn = document.getElementById('loadMoreCommentsBtn');
                        if (loadMoreBtn) {
                            loadMoreBtn.disabled = false;
                            loadMoreBtn.innerHTML = 'Load More Comments';

                            if (!hasMoreComments) {
                                loadMoreBtn.style.display = 'none';
                            }
                        }
                    }
                });
        }

        // Function to load more comments
        function loadMoreComments() {
            if (!currentPostId || !hasMoreComments || commentsLoading) {
                console.log('⚠️ Cannot load more comments:', {
                    currentPostId,
                    hasMoreComments,
                    commentsLoading
                });
                return;
            }
            loadComments(currentPostId, false);
        }

        // Enhanced function to display comments (first load)
        function displayComments(comments, reactions, isFirstLoad = true) {
            console.log(`🎨 Displaying ${comments?.length || 0} comments, first load: ${isFirstLoad}`);

            const commentsBody = document.getElementById('commentsBody');
            if (!commentsBody) return;

            let html = '';

            // Display comments
            if (comments && comments.length > 0) {
                comments.forEach(comment => {
                    html += generateCommentHTML(comment);
                });
            }

            // Display post reactions section (only on first load)
            if (isFirstLoad && reactions && reactions.length > 0) {
                html += `
            <div class="reactions-section" style="padding: 20px; border-top: 1px solid #eee; margin-top: 20px; background-color: #f9f9f9;">
                <div class="reactions-title" style="font-weight: 600; margin-bottom: 15px; color: #333;">Post Reactions:</div>
                <div class="reactions-list" style="display: flex; flex-wrap: wrap; gap: 10px;">
        `;

                reactions.forEach(reaction => {
                    const userImage = reaction.PROFILE_IMAGE || 'images/default-profile.png';
                    html += `
                <div class="reaction-item" style="display: flex; align-items: center; gap: 8px; padding: 8px 12px; background: white; border-radius: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <img src="${userImage}" alt="${reaction.NAME}" style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover;" onerror="this.src='images/default-profile.png'">
                    <span style="font-size: 18px;">${reaction.REACTION_EMOJI}</span>
                    <span style="font-size: 12px; color: #666;">${reaction.NAME}</span>
                </div>
            `;
                });

                html += '</div></div>';
            }

            // Add Load More button if there are more comments
            if (hasMoreComments) {
                html += `
            <div style="text-align: center; padding: 20px;">
                <button id="loadMoreCommentsBtn" onclick="loadMoreComments()" 
                        style="background: linear-gradient(135deg, #ED5A2C 0%, #ff6b3d 100%); 
                               color: white; border: none; padding: 12px 24px; border-radius: 25px; 
                               font-weight: 500; cursor: pointer; transition: all 0.2s ease;
                               box-shadow: 0 2px 8px rgba(237, 90, 44, 0.3);">
                    Load More Comments
                </button>
            </div>
        `;
            }

            // Show empty state if no content
            if (!html && isFirstLoad) {
                html = `
            <div class="no-comments" style="text-align: center; padding: 50px 20px; color: #666;">
                <div style="font-size: 48px; margin-bottom: 15px;">💬</div>
                <p style="font-size: 16px; margin-bottom: 10px;">No comments yet</p>
                <p style="font-size: 14px; opacity: 0.8;">Be the first to share your thoughts!</p>
            </div>
        `;
            }

            commentsBody.innerHTML = html;

            // Initialize comment interactions after content is loaded
            setTimeout(() => {
                initializeCommentInteractions();
            }, 100);
        }

        // Function to append new comments (for load more)
        function appendComments(comments) {
            if (!comments || comments.length === 0) return;

            console.log(`➕ Appending ${comments.length} more comments`);

            const commentsBody = document.getElementById('commentsBody');
            const loadMoreBtn = document.getElementById('loadMoreCommentsBtn');

            if (!commentsBody) return;

            // Generate HTML for new comments
            let newCommentsHTML = '';
            comments.forEach(comment => {
                newCommentsHTML += generateCommentHTML(comment);
            });

            // Create temporary container
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = newCommentsHTML;

            // Insert new comments before the load more button
            if (loadMoreBtn && loadMoreBtn.parentElement) {
                const loadMoreContainer = loadMoreBtn.parentElement;
                while (tempDiv.firstChild) {
                    commentsBody.insertBefore(tempDiv.firstChild, loadMoreContainer);
                }

                // Update or hide load more button
                if (!hasMoreComments) {
                    loadMoreContainer.style.display = 'none';
                }
            } else {
                // No load more button, just append
                while (tempDiv.firstChild) {
                    commentsBody.appendChild(tempDiv.firstChild);
                }
            }

            // Initialize interactions for new comments
            setTimeout(() => {
                initializeCommentInteractions();
            }, 100);
        }

        // Helper function to generate comment HTML
        function generateCommentHTML(comment) {
            const timeAgo = getTimeAgo(comment.CREATED_AT);
            const userImage = comment.PROFILE_IMAGE || 'images/default-profile.png';
            const currentUserId = <?php echo json_encode($_SESSION['user_id']); ?>;
            const isOwner = comment.USER_ID == currentUserId;

            // Handle comment reactions
            const hasUserReaction = comment.has_user_reaction === 1 || comment.has_user_reaction === '1';
            const userReactionType = comment.user_reaction_type;
            const userReactionEmoji = comment.user_reaction_emoji;
            const totalReactions = parseInt(comment.total_reactions) || 0;

            return `
        <div class="comment-item ${hasUserReaction ? 'has-reaction' : ''}" data-comment-id="${comment.COMMENT_ID}">
            <img src="${userImage}" alt="${comment.NAME}" class="comment-avatar" onerror="this.src='images/default-profile.png'">
            <div class="comment-content">
                <div class="comment-author">${comment.NAME}</div>
                <div class="comment-text">${comment.COMMENT_TEXT}</div>
                
                ${hasUserReaction ? `
                    <div class="comment-reaction-display" style="margin: 8px 0; display: flex; align-items: center; gap: 6px; font-size: 14px; color: var(--primary-color); font-weight: 500;">
                        <span class="reaction-emoji">${userReactionEmoji}</span>
                        <span style="text-transform: capitalize;">You reacted with ${userReactionType}</span>
                    </div>
                ` : ''}
                
                ${totalReactions > 0 ? `
                    <div class="comment-reaction-count" onclick="showCommentReactionUsers(${comment.COMMENT_ID})" 
                         style="font-size: 12px; color: #666; cursor: pointer; margin: 8px 0; display: inline-flex; align-items: center; gap: 5px; padding: 4px 8px; border-radius: 12px; background-color: #f0f0f0; transition: all 0.2s ease;">
                        <span>👥</span>
                        <span>${totalReactions} reaction${totalReactions !== 1 ? 's' : ''}</span>
                    </div>
                ` : ''}
                
                <div class="comment-time" style="font-size: 12px; color: #888; margin: 8px 0;">${timeAgo}</div>
                
                <div class="comment-actions" style="display: flex; align-items: center; gap: 15px; margin-top: 10px;">
                    <div class="comment-like-container" data-comment-id="${comment.COMMENT_ID}">
                        <button class="comment-like-btn ${hasUserReaction ? 'active' : ''}" 
                                onclick="toggleCommentReactions(${comment.COMMENT_ID})" 
                                data-user-reaction="${userReactionType || ''}"
                                style="background: none; border: none; color: ${hasUserReaction ? 'var(--primary-color)' : '#666'}; cursor: pointer; font-size: 12px; display: flex; align-items: center; gap: 5px; padding: 5px 8px; border-radius: 15px; transition: all 0.2s ease; font-weight: ${hasUserReaction ? '600' : '500'};">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" 
                                 fill="${hasUserReaction ? 'var(--primary-color)' : 'none'}" 
                                 stroke="${hasUserReaction ? 'var(--primary-color)' : 'currentColor'}" 
                                 stroke-width="2">
                                <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
                            </svg>
                            ${hasUserReaction ? userReactionType.charAt(0).toUpperCase() + userReactionType.slice(1) : 'React'}
                        </button>
                        
                        <!-- Comment Food Reactions Panel -->
                        <div class="comment-food-reactions" id="comment-reactions-${comment.COMMENT_ID}">
                            <div class="comment-reaction-icon" data-reaction="yummy" data-comment-id="${comment.COMMENT_ID}" title="Yummy!" onclick="handleCommentReaction(${comment.COMMENT_ID}, 'yummy')">
                                <span>🍔</span>
                            </div>
                            <div class="comment-reaction-icon" data-reaction="delicious" data-comment-id="${comment.COMMENT_ID}" title="Delicious!" onclick="handleCommentReaction(${comment.COMMENT_ID}, 'delicious')">
                                <span>🍕</span>
                            </div>
                            <div class="comment-reaction-icon" data-reaction="tasty" data-comment-id="${comment.COMMENT_ID}" title="Tasty!" onclick="handleCommentReaction(${comment.COMMENT_ID}, 'tasty')">
                                <span>🍰</span>
                            </div>
                            <div class="comment-reaction-icon" data-reaction="love" data-comment-id="${comment.COMMENT_ID}" title="Love it!" onclick="handleCommentReaction(${comment.COMMENT_ID}, 'love')">
                                <span>🍲</span>
                            </div>
                            <div class="comment-reaction-icon" data-reaction="amazing" data-comment-id="${comment.COMMENT_ID}" title="Amazing!" onclick="handleCommentReaction(${comment.COMMENT_ID}, 'amazing')">
                                <span>🍗</span>
                            </div>
                            <div class="comment-reaction-icon" data-reaction="like" data-comment-id="${comment.COMMENT_ID}" title="Like!" onclick="handleCommentReaction(${comment.COMMENT_ID}, 'like')">
                                <span>👍</span>
                            </div>
                        </div>
                    </div>
                    
                    ${isOwner ? `
                        <button class="comment-delete-btn" onclick="deleteComment(${comment.COMMENT_ID})" 
                                style="background: none; border: none; color: #f44336; cursor: pointer; font-size: 12px; padding: 5px 8px; border-radius: 15px; transition: all 0.2s ease; font-weight: 500;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="m19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                            Delete
                        </button>
                    ` : ''}
                </div>
            </div>
        </div>
    `;
        }

        // Function to initialize comment interactions
        function initializeCommentInteractions() {
            console.log('🔧 Initializing comment interactions...');

            // Remove any existing event listeners to prevent duplicates
            document.removeEventListener('click', handleDocumentClick);

            // Add document click listener for closing reaction panels
            document.addEventListener('click', handleDocumentClick);

            // Initialize mobile touch support
            initializeMobileCommentSupport();
        }

        // Document click handler for closing reaction panels
        function handleDocumentClick(e) {
            // Don't close if clicking inside a reaction panel or its trigger
            if (!e.target.closest('.comment-like-container') && !e.target.closest('.comment-food-reactions')) {
                closeAllCommentReactionPanels();
            }
        }

        // Function to close all comment reaction panels
        function closeAllCommentReactionPanels() {
            document.querySelectorAll('.comment-like-container.active').forEach(container => {
                container.classList.remove('active');
            });

            // Remove mobile backdrops
            document.querySelectorAll('.comment-reaction-backdrop').forEach(backdrop => {
                backdrop.remove();
            });
        }

        // Function to toggle comment reaction panel
        function toggleCommentReactions(commentId) {
            console.log('🎭 Toggling comment reactions for:', commentId);

            // Close all other reaction panels first
            document.querySelectorAll('.comment-like-container.active').forEach(container => {
                if (container.getAttribute('data-comment-id') != commentId) {
                    container.classList.remove('active');
                }
            });

            // Remove existing backdrops
            document.querySelectorAll('.comment-reaction-backdrop').forEach(backdrop => backdrop.remove());

            // Toggle current panel
            const container = document.querySelector(`[data-comment-id="${commentId}"].comment-like-container`);
            if (!container) {
                console.error('❌ Comment reaction container not found for:', commentId);
                return;
            }

            const isActive = container.classList.contains('active');

            if (isActive) {
                container.classList.remove('active');
            } else {
                container.classList.add('active');

                // Add mobile backdrop if needed
                if (window.innerWidth <= 768) {
                    const backdrop = document.createElement('div');
                    backdrop.className = 'comment-reaction-backdrop';
                    backdrop.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(0, 0, 0, 0.05);
                z-index: 999;
                backdrop-filter: blur(1px);
            `;
                    backdrop.addEventListener('click', () => {
                        container.classList.remove('active');
                        backdrop.remove();
                    });
                    document.body.appendChild(backdrop);
                }
            }
        }

        // Enhanced function to handle comment reactions
        function handleCommentReaction(commentId, reactionType) {
            console.log(`👍 Handling comment reaction: ${reactionType} for comment:`, commentId);

            // Add loading state
            const clickedIcon = document.querySelector(`[data-comment-id="${commentId}"] [data-reaction="${reactionType}"]`);
            if (clickedIcon) {
                clickedIcon.style.opacity = '0.6';
                clickedIcon.style.transform = 'scale(1.1)';
                clickedIcon.style.pointerEvents = 'none';
            }

            const formData = new FormData();
            formData.append('action', 'add_comment_reaction');
            formData.append('comment_id', commentId);
            formData.append('reaction_type', reactionType);

            fetch('comments.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('📨 Comment reaction response:', data);

                    if (data.success) {
                        // Update UI
                        updateCommentReactionUI(commentId, data, reactionType);

                        // Close reaction panel
                        const container = document.querySelector(`[data-comment-id="${commentId}"].comment-like-container`);
                        if (container) {
                            container.classList.remove('active');
                        }

                        // Remove backdrop
                        document.querySelectorAll('.comment-reaction-backdrop').forEach(backdrop => backdrop.remove());

                        // Show success message
                        const messages = {
                            'added': `You reacted with ${reactionType}! ✨`,
                            'updated': `Changed reaction to ${reactionType}! 🔄`,
                            'removed': `Reaction removed! 👋`
                        };
                        showToast(messages[data.action_type] || 'Reaction updated!', 'success');

                    } else {
                        throw new Error(data.message || 'Failed to update reaction');
                    }
                })
                .catch(error => {
                    console.error('❌ Error handling comment reaction:', error);
                    showToast('Failed to update reaction. Please try again.', 'error');
                })
                .finally(() => {
                    // Remove loading state
                    if (clickedIcon) {
                        clickedIcon.style.opacity = '1';
                        clickedIcon.style.transform = 'scale(1)';
                        clickedIcon.style.pointerEvents = 'auto';
                    }
                });
        }

        // Function to update comment reaction UI
        function updateCommentReactionUI(commentId, responseData, newReaction) {
            console.log('🎨 Updating comment reaction UI for:', commentId);

            const commentItem = document.querySelector(`[data-comment-id="${commentId}"]`);
            const container = document.querySelector(`[data-comment-id="${commentId}"].comment-like-container`);
            const likeButton = container?.querySelector('.comment-like-btn');

            if (!commentItem || !container || !likeButton) {
                console.error('❌ Comment UI elements not found');
                return;
            }

            if (responseData.has_reaction) {
                // User has a reaction
                likeButton.classList.add('active');
                likeButton.setAttribute('data-user-reaction', responseData.reaction_type);
                likeButton.style.color = 'var(--primary-color)';
                likeButton.style.fontWeight = '600';

                // Update button content
                const reactionText = responseData.reaction_type.charAt(0).toUpperCase() + responseData.reaction_type.slice(1);
                likeButton.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" 
                 fill="var(--primary-color)" stroke="var(--primary-color)" stroke-width="2">
                <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
            </svg>
            ${reactionText}
        `;

                commentItem.classList.add('has-reaction');

                // Update or add reaction display
                let reactionDisplay = commentItem.querySelector('.comment-reaction-display');
                if (reactionDisplay) {
                    reactionDisplay.innerHTML = `
                <span class="reaction-emoji">${responseData.reaction_emoji}</span>
                <span style="text-transform: capitalize;">You reacted with ${responseData.reaction_type}</span>
            `;
                } else {
                    const commentText = commentItem.querySelector('.comment-text');
                    if (commentText) {
                        commentText.insertAdjacentHTML('afterend', `
                    <div class="comment-reaction-display" style="margin: 8px 0; display: flex; align-items: center; gap: 6px; font-size: 14px; color: var(--primary-color); font-weight: 500;">
                        <span class="reaction-emoji">${responseData.reaction_emoji}</span>
                        <span style="text-transform: capitalize;">You reacted with ${responseData.reaction_type}</span>
                    </div>
                `);
                    }
                }

            } else {
                // User removed reaction
                likeButton.classList.remove('active');
                likeButton.setAttribute('data-user-reaction', '');
                likeButton.style.color = '#666';
                likeButton.style.fontWeight = '500';

                // Reset button content
                likeButton.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" 
                 fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
            </svg>
            React
        `;

                commentItem.classList.remove('has-reaction');

                // Remove reaction display
                const reactionDisplay = commentItem.querySelector('.comment-reaction-display');
                if (reactionDisplay) {
                    reactionDisplay.remove();
                }
            }

            // Add animation to clicked reaction icon
            const clickedIcon = container.querySelector(`[data-reaction="${newReaction}"]`);
            if (clickedIcon && responseData.has_reaction) {
                clickedIcon.style.transform = 'scale(1.3)';
                setTimeout(() => {
                    clickedIcon.style.transform = 'scale(1)';
                }, 200);
            }
        }

        // Enhanced function to add comment
        function addComment() {
            console.log('💬 Adding new comment...');

            const commentInput = document.getElementById('commentInput');
            const submitBtn = document.getElementById('commentSubmit');

            if (!commentInput || !submitBtn) {
                console.error('❌ Comment input elements not found');
                return;
            }

            const commentText = commentInput.value.trim();

            if (!commentText) {
                showToast('Please write something!', 'error');
                commentInput.focus();
                return;
            }

            if (!currentPostId) {
                showToast('Error: No post selected', 'error');
                return;
            }

            if (commentText.length > 500) {
                showToast('Comment is too long (max 500 characters)', 'error');
                return;
            }

            // Show loading state
            submitBtn.disabled = true;
            const originalContent = submitBtn.innerHTML;
            submitBtn.innerHTML = '<div class="loading-spinner small"></div>';

            const formData = new FormData();
            formData.append('action', 'add_comment');
            formData.append('post_id', currentPostId);
            formData.append('comment_text', commentText);

            fetch('comments.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('📨 Add comment response:', data);

                    if (data.success) {
                        // Clear input
                        commentInput.value = '';
                        commentInput.style.height = 'auto';

                        // Show success
                        showToast('Comment added! 🎉', 'success');

                        // Reload comments to show the new one
                        currentOffset = 0;
                        loadComments(currentPostId, true);

                        // Update comment count in post
                        updatePostCommentCount(currentPostId);

                    } else {
                        throw new Error(data.message || 'Failed to add comment');
                    }
                })
                .catch(error => {
                    console.error('❌ Error adding comment:', error);
                    showToast('Failed to add comment. Please try again.', 'error');
                })
                .finally(() => {
                    // Reset submit button
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalContent;
                    updateCommentSubmitButton();
                });
        }

        // Function to delete comment
        function deleteComment(commentId) {
            if (!confirm('Are you sure you want to delete this comment?')) {
                return;
            }

            console.log('🗑️ Deleting comment:', commentId);

            const formData = new FormData();
            formData.append('action', 'delete_comment');
            formData.append('comment_id', commentId);

            fetch('comments.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Comment deleted! 🗑️', 'success');

                        // Animate and remove comment
                        const commentElement = document.querySelector(`[data-comment-id="${commentId}"]`);
                        if (commentElement) {
                            commentElement.style.transition = 'all 0.3s ease';
                            commentElement.style.opacity = '0';
                            commentElement.style.transform = 'translateX(-20px)';

                            setTimeout(() => {
                                commentElement.remove();
                                updatePostCommentCount(currentPostId);
                                totalCommentsCount = Math.max(0, totalCommentsCount - 1);
                            }, 300);
                        }
                    } else {
                        throw new Error(data.message || 'Failed to delete comment');
                    }
                })
                .catch(error => {
                    console.error('❌ Error deleting comment:', error);
                    showToast('Failed to delete comment. Please try again.', 'error');
                });
        }

        // FIXED: Function to show COMMENT reaction users with separate modal
        function showCommentReactionUsers(commentId) {
            console.log('👥 Showing comment reaction users for:', commentId);

            const formData = new FormData();
            formData.append('action', 'get_comment_reaction_users');
            formData.append('comment_id', commentId);

            // Use SEPARATE modal for comment reactions
            const modal = document.getElementById('commentReactionUsersModal');
            const usersList = document.getElementById('commentReactionUsersList');
            const modalTitle = modal?.querySelector('h2');

            if (!modal || !usersList) {
                console.error('❌ Comment reaction users modal not found');
                return;
            }

            // Set the correct title for COMMENT reactions
            if (modalTitle) {
                modalTitle.textContent = 'People who reacted to this comment';
            }

            usersList.innerHTML = '<div class="loading-spinner" style="margin: 20px auto;"></div><p style="text-align: center; color: #666;">Loading reactions...</p>';
            modal.style.display = 'block';

            fetch('comments.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayCommentReactionUsers(data.users);
                    } else {
                        throw new Error(data.message || 'Failed to load reactions');
                    }
                })
                .catch(error => {
                    console.error('❌ Error loading comment reaction users:', error);
                    usersList.innerHTML = '<p style="text-align: center; padding: 20px; color: #f44336;">Failed to load reactions</p>';
                });
        }

        // FIXED: Separate display function for COMMENT reactions
        function displayCommentReactionUsers(users) {
            const usersList = document.getElementById('commentReactionUsersList');
            if (!usersList) return;

            if (!users || users.length === 0) {
                usersList.innerHTML = '<p style="text-align: center; padding: 30px; color: #666; font-size: 16px;">No reactions yet</p>';
                return;
            }

            let html = '';
            users.forEach(user => {
                const userImage = user.PROFILE_IMAGE || 'images/default-profile.png';
                const reactionTime = formatReactionTime(user.CREATED_AT);
                const reactionType = user.REACTION_TYPE?.charAt(0).toUpperCase() + user.REACTION_TYPE?.slice(1) || 'Unknown';
                const reactionEmoji = user.REACTION_EMOJI || '👍';

                html += `
            <div class="reaction-user-item" style="display: flex; align-items: center; padding: 15px; border-bottom: 1px solid #eee; transition: background-color 0.2s; cursor: pointer;">
                <img src="${userImage}" alt="${user.NAME || 'User'}" class="reaction-user-avatar" 
                     style="width: 50px; height: 50px; border-radius: 50%; margin-right: 15px; object-fit: cover; border: 2px solid #f0f0f0;"
                     onerror="this.src='images/default-profile.png'">
                <div class="reaction-user-info" style="flex: 1;">
                    <div class="reaction-user-name" style="font-weight: 600; margin-bottom: 5px; color: #333; font-size: 16px;">${user.NAME || 'Unknown User'}</div>
                    <div class="reaction-user-time" style="font-size: 13px; color: #666;">Reacted ${reactionTime}</div>
                </div>
                <div class="reaction-user-emoji" style="font-size: 28px; margin-left: 15px; display: flex; flex-direction: column; align-items: center;">
                    <span>${reactionEmoji}</span>
                    <small style="font-size: 11px; color: #888; margin-top: 3px; text-transform: capitalize;">${reactionType}</small>
                </div>
            </div>
        `;
            });

            usersList.innerHTML = html;

            // Add hover effects
            usersList.querySelectorAll('.reaction-user-item').forEach(item => {
                item.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f8f9fa';
                });
                item.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = 'transparent';
                });
            });
        }

        // Function to initialize mobile comment support
        function initializeMobileCommentSupport() {
            const containers = document.querySelectorAll('.comment-like-container');

            containers.forEach(container => {
                let touchStartTime = 0;

                container.addEventListener('touchstart', function(e) {
                    touchStartTime = Date.now();
                }, {
                    passive: true
                });

                container.addEventListener('touchend', function(e) {
                    const touchDuration = Date.now() - touchStartTime;

                    if (touchDuration < 300) { // Quick tap
                        e.preventDefault();
                        const commentId = this.getAttribute('data-comment-id');
                        if (commentId) {
                            toggleCommentReactions(commentId);
                        }
                    }
                });
            });
        }

        // Function to initialize comment reaction handlers
        function initializeCommentReactionHandlers() {
            console.log('🎭 Initializing comment reaction handlers...');

            // This function is called during initialization
            // Most handlers are attached dynamically when comments are loaded
        }

        // Function to update comment submit button state  
        function updateCommentSubmitButton() {
            const commentInput = document.getElementById('commentInput');
            const submitBtn = document.getElementById('commentSubmit');

            if (!commentInput || !submitBtn) return;

            const hasText = commentInput.value.trim().length > 0;
            const isNotTooLong = commentInput.value.length <= 500;

            submitBtn.disabled = !hasText || !isNotTooLong || commentsLoading;

            // Update visual state
            if (hasText && isNotTooLong) {
                submitBtn.style.opacity = '1';
                submitBtn.style.transform = 'scale(1)';
            } else {
                submitBtn.style.opacity = '0.6';
                submitBtn.style.transform = 'scale(0.95)';
            }
        }

        // Function to update post comment count
        function updatePostCommentCount(postId) {
            const formData = new FormData();
            formData.append('action', 'get_comment_count');
            formData.append('post_id', postId);

            fetch('comments.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const countElement = document.getElementById(`comment-count-${postId}`);
                        if (countElement) {
                            const count = data.count;
                            const text = count === 0 ? '0 comments' : count === 1 ? '1 comment' : `${count} comments`;
                            countElement.textContent = text;

                            // Animate the update
                            countElement.style.transform = 'scale(1.1)';
                            setTimeout(() => {
                                countElement.style.transform = 'scale(1)';
                            }, 200);
                        }
                    }
                })
                .catch(error => {
                    console.error('Error updating comment count:', error);
                });
        }

        // Function to show comments error
        function showCommentsError(message) {
            const commentsBody = document.getElementById('commentsBody');
            if (!commentsBody) return;

            commentsBody.innerHTML = `
        <div style="text-align: center; padding: 40px; color: #f44336;">
            <div style="font-size: 48px; margin-bottom: 15px;">⚠️</div>
            <p style="margin-bottom: 20px;">${message}</p>
            <button onclick="loadComments(currentPostId, true)" 
                    style="background: #ED5A2C; color: white; border: none; padding: 10px 20px; border-radius: 20px; cursor: pointer;">
                Try Again
            </button>
        </div>
    `;
        }

        // Utility function to format time
        function getTimeAgo(dateString) {
            const now = new Date();
            const date = new Date(dateString);
            const diff = Math.floor((now - date) / 1000);

            if (diff < 30) return 'Just now';
            if (diff < 60) return `${diff}s ago`;
            if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
            if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
            if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;

            return date.toLocaleDateString();
        }

        // Enhanced toast notification function
        function showToast(message, type = 'success') {
            // Remove existing toasts
            document.querySelectorAll('.toast').forEach(toast => toast.remove());

            const toast = document.createElement('div');
            toast.className = 'toast';
            toast.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: ${type === 'success' ? '#4CAF50' : '#f44336'};
        color: white;
        padding: 12px 20px;
        border-radius: 25px;
        font-size: 14px;
        font-weight: 500;
        z-index: 10000;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        animation: slideInUp 0.3s ease, slideOutDown 0.3s ease 2.7s forwards;
        display: flex;
        align-items: center;
        gap: 8px;
        max-width: 300px;
    `;

            const icon = type === 'success' ? '✅' : '❌';
            toast.innerHTML = `<span>${icon}</span><span>${message}</span>`;

            // Add animation styles if not already present
            if (!document.getElementById('toast-animations')) {
                const style = document.createElement('style');
                style.id = 'toast-animations';
                style.textContent = `
            @keyframes slideInUp {
                from { opacity: 0; transform: translateY(100px); }
                to { opacity: 1; transform: translateY(0); }
            }
            @keyframes slideOutDown {
                from { opacity: 1; transform: translateY(0); }
                to { opacity: 0; transform: translateY(100px); }
            }
        `;
                document.head.appendChild(style);
            }

            document.body.appendChild(toast);

            setTimeout(() => {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 3000);
        }

        // Global functions for backward compatibility
        window.openCommentsModal = openCommentsModal;
        window.closeCommentsModal = closeCommentsModal;
        window.loadMoreComments = loadMoreComments;
        window.toggleCommentReactions = toggleCommentReactions;
        window.handleCommentReaction = handleCommentReaction;
        window.addComment = addComment;
        window.deleteComment = deleteComment;
        window.showCommentReactionUsers = showCommentReactionUsers;

        console.log('✅ Enhanced Comments System JavaScript Loaded Successfully!');


        // ========================================
        // ENHANCED STORY UPLOAD SYSTEM - COMPLETE JAVASCRIPT
        // Add this to the <script> section of dashboard.php
        // ========================================

        // Enhanced Story Upload Handler
        function handleStoryUpload(input) {
            console.log('📸 Story upload initiated');

            if (!input.files || input.files.length === 0) {
                console.log('❌ No files selected');
                return;
            }

            const files = Array.from(input.files);
            const maxFiles = 10; // Maximum files per upload
            const maxSize = 50 * 1024 * 1024; // 50MB max per file
            const allowedTypes = [
                'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
                'video/mp4', 'video/quicktime', 'video/webm', 'video/mov'
            ];

            // Validate file count
            if (files.length > maxFiles) {
                showToast(`Maximum ${maxFiles} files allowed at once`, 'error');
                input.value = '';
                return;
            }

            // Validate files
            const invalidFiles = [];
            const oversizedFiles = [];

            files.forEach((file, index) => {
                console.log(`📋 Validating file ${index + 1}: ${file.name} (${file.type}, ${(file.size / 1024 / 1024).toFixed(2)}MB)`);

                // Check file type
                if (!allowedTypes.includes(file.type)) {
                    invalidFiles.push(file.name);
                }

                // Check file size
                if (file.size > maxSize) {
                    oversizedFiles.push(file.name);
                }
            });

            // Show validation errors
            if (invalidFiles.length > 0) {
                showToast(`Invalid file types: ${invalidFiles.join(', ')}. Only images and videos allowed.`, 'error');
                input.value = '';
                return;
            }

            if (oversizedFiles.length > 0) {
                showToast(`Files too large: ${oversizedFiles.join(', ')}. Maximum 50MB per file.`, 'error');
                input.value = '';
                return;
            }

            // Show upload confirmation and preview
            showStoryUploadPreview(files, input);
        }

        // Enhanced Story Upload Preview Modal
        function showStoryUploadPreview(files, inputElement) {
            console.log('🖼️ Showing story upload preview for', files.length, 'files');

            // Create preview modal
            const modal = document.createElement('div');
            modal.id = 'storyUploadModal';
            modal.className = 'story-upload-modal';
            modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.8);
        backdrop-filter: blur(5px);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
        animation: fadeIn 0.3s ease;
    `;

            const modalContent = document.createElement('div');
            modalContent.className = 'story-upload-content';
            modalContent.style.cssText = `
        background: white;
        border-radius: 20px;
        max-width: 90vw;
        max-height: 85vh;
        overflow: hidden;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        animation: slideUp 0.3s ease;
        display: flex;
        flex-direction: column;
    `;

            // Modal header
            const header = document.createElement('div');
            header.style.cssText = `
        padding: 20px 25px;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: linear-gradient(135deg, #ED5A2C 0%, #ff6b3d 100%);
        color: white;
    `;

            header.innerHTML = `
        <div>
            <h3 style="margin: 0; font-size: 20px; font-weight: 600;">Upload Stories</h3>
            <p style="margin: 5px 0 0 0; font-size: 14px; opacity: 0.9;">${files.length} file${files.length !== 1 ? 's' : ''} selected</p>
        </div>
        <button onclick="closeStoryUploadModal()" style="background: rgba(255,255,255,0.2); border: none; color: white; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 24px; transition: all 0.2s;">
            ×
        </button>
    `;

            // Modal body
            const body = document.createElement('div');
            body.style.cssText = `
        padding: 20px 25px;
        max-height: 60vh;
        overflow-y: auto;
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: 15px;
    `;

            // Generate previews
            files.forEach((file, index) => {
                const preview = document.createElement('div');
                preview.className = 'story-preview-item';
                preview.style.cssText = `
            position: relative;
            aspect-ratio: 9 / 16;
            background: #f5f5f5;
            border-radius: 12px;
            overflow: hidden;
            border: 2px solid #eee;
            transition: all 0.2s ease;
            cursor: pointer;
        `;

                preview.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.05)';
                    this.style.borderColor = '#ED5A2C';
                });

                preview.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                    this.style.borderColor = '#eee';
                });

                const reader = new FileReader();
                reader.onload = function(e) {
                    if (file.type.startsWith('video/')) {
                        preview.innerHTML = `
                    <video style="width: 100%; height: 100%; object-fit: cover;" muted>
                        <source src="${e.target.result}" type="${file.type}">
                    </video>
                    <div style="position: absolute; top: 8px; right: 8px; background: rgba(0,0,0,0.7); color: white; padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: 500;">
                        VIDEO
                    </div>
                    <div style="position: absolute; bottom: 8px; left: 8px; right: 8px; background: rgba(0,0,0,0.7); color: white; padding: 4px 8px; border-radius: 8px; font-size: 10px; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        ${file.name}
                    </div>
                `;
                    } else {
                        preview.innerHTML = `
                    <img src="${e.target.result}" style="width: 100%; height: 100%; object-fit: cover;" alt="Preview">
                    <div style="position: absolute; top: 8px; right: 8px; background: rgba(0,0,0,0.7); color: white; padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: 500;">
                        IMAGE
                    </div>
                    <div style="position: absolute; bottom: 8px; left: 8px; right: 8px; background: rgba(0,0,0,0.7); color: white; padding: 4px 8px; border-radius: 8px; font-size: 10px; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        ${file.name}
                    </div>
                `;
                    }
                };
                reader.readAsDataURL(file);

                body.appendChild(preview);
            });

            // Modal footer
            const footer = document.createElement('div');
            footer.style.cssText = `
        padding: 20px 25px;
        border-top: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #fafafa;
    `;

            footer.innerHTML = `
        <div style="display: flex; align-items: center; gap: 15px;">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 14px; color: #666;">
                <input type="radio" name="storyVisibility" value="public" checked style="margin: 0;">
                <span>Public</span>
            </label>
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 14px; color: #666;">
                <input type="radio" name="storyVisibility" value="friends" style="margin: 0;">
                <span>Friends Only</span>
            </label>
        </div>
        <div style="display: flex; gap: 12px;">
            <button onclick="closeStoryUploadModal()" style="background: #f5f5f5; color: #666; border: none; padding: 12px 20px; border-radius: 25px; font-weight: 500; cursor: pointer; transition: all 0.2s;">
                Cancel
            </button>
            <button onclick="uploadStories()" id="uploadStoriesBtn" style="background: linear-gradient(135deg, #ED5A2C 0%, #ff6b3d 100%); color: white; border: none; padding: 12px 24px; border-radius: 25px; font-weight: 500; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(237, 90, 44, 0.3); display: flex; align-items: center; gap: 8px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10l5-5 5 5"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                Upload ${files.length} Stor${files.length !== 1 ? 'ies' : 'y'}
            </button>
        </div>
    `;

            modalContent.appendChild(header);
            modalContent.appendChild(body);
            modalContent.appendChild(footer);
            modal.appendChild(modalContent);

            // Store files reference for upload
            modal.storyFiles = files;
            modal.inputElement = inputElement;

            document.body.appendChild(modal);

            // Close on backdrop click
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeStoryUploadModal();
                }
            });

            // Add required styles if not present
            if (!document.getElementById('storyUploadStyles')) {
                const styles = document.createElement('style');
                styles.id = 'storyUploadStyles';
                styles.textContent = `
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            @keyframes slideUp {
                from { opacity: 0; transform: translateY(30px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .story-upload-content::-webkit-scrollbar {
                width: 6px;
            }
            .story-upload-content::-webkit-scrollbar-track {
                background: #f1f1f1;
                border-radius: 3px;
            }
            .story-upload-content::-webkit-scrollbar-thumb {
                background: #ED5A2C;
                border-radius: 3px;
            }
            .story-upload-content::-webkit-scrollbar-thumb:hover {
                background: #d94e22;
            }
            @media (max-width: 768px) {
                .story-upload-content {
                    max-width: 95vw !important;
                    max-height: 90vh !important;
                }
                .story-upload-content > div:nth-child(2) {
                    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)) !important;
                    gap: 10px !important;
                    padding: 15px 20px !important;
                }
                .story-upload-content > div:first-child {
                    padding: 15px 20px !important;
                }
                .story-upload-content > div:last-child {
                    padding: 15px 20px !important;
                    flex-direction: column !important;
                    gap: 15px !important;
                }
            }
        `;
                document.head.appendChild(styles);
            }

            console.log('✅ Story upload preview modal created');
        }

        // Close Story Upload Modal
        function closeStoryUploadModal() {
            const modal = document.getElementById('storyUploadModal');
            if (modal) {
                modal.style.animation = 'fadeOut 0.3s ease forwards';
                setTimeout(() => {
                    if (modal.parentNode) {
                        modal.parentNode.removeChild(modal);
                    }
                    // Reset file input
                    if (modal.inputElement) {
                        modal.inputElement.value = '';
                    }
                }, 300);
            }
        }

        // Enhanced Upload Stories Function
        async function uploadStories() {
            const modal = document.getElementById('storyUploadModal');
            const uploadBtn = document.getElementById('uploadStoriesBtn');

            if (!modal || !modal.storyFiles) {
                console.error('❌ No files to upload');
                return;
            }

            const files = modal.storyFiles;
            const visibility = modal.querySelector('input[name="storyVisibility"]:checked').value;

            console.log('📤 Starting upload of', files.length, 'stories with visibility:', visibility);

            // Show loading state
            uploadBtn.disabled = true;
            uploadBtn.innerHTML = `
        <div style="width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top: 2px solid white; border-radius: 50%; animation: spin 1s linear infinite;"></div>
        Uploading...
    `;

            try {
                const formData = new FormData();

                // Add files to form data
                files.forEach((file, index) => {
                    formData.append('story_media[]', file);
                });

                formData.append('visibility', visibility);

                // Upload with progress tracking
                const response = await fetch('story.php', {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                // Check if response is HTML (redirect) or JSON
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    const result = await response.json();
                    if (result.success) {
                        showToast('✨ Stories uploaded successfully!', 'success');
                    } else {
                        throw new Error(result.message || 'Upload failed');
                    }
                } else {
                    // Assume success if we get HTML (redirect)
                    showToast('✨ Stories uploaded successfully!', 'success');
                }

                // Close modal and refresh page
                closeStoryUploadModal();

                // Refresh the page after a short delay to show new stories
                setTimeout(() => {
                    window.location.reload();
                }, 1500);

            } catch (error) {
                console.error('❌ Upload error:', error);
                showToast('Failed to upload stories. Please try again.', 'error');

                // Reset button
                uploadBtn.disabled = false;
                uploadBtn.innerHTML = `
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="7 10l5-5 5 5"></polyline>
                <line x1="12" y1="15" x2="12" y2="3"></line>
            </svg>
            Upload ${files.length} Stor${files.length !== 1 ? 'ies' : 'y'}
        `;
            }
        }

        // Enhanced Story Upload with Drag & Drop Support
        function initializeStoryUpload() {
            console.log('🚀 Initializing enhanced story upload system');

            const storyForm = document.getElementById('storyForm');
            const storyInput = document.getElementById('storyMedia');
            const addStoryLabel = document.querySelector('.add-story-label');

            if (!storyForm || !storyInput || !addStoryLabel) {
                console.warn('⚠️ Story upload elements not found');
                return;
            }

            // Enhanced drag and drop functionality
            let dragCounter = 0;

            // Drag events for the entire add story area
            addStoryLabel.addEventListener('dragenter', function(e) {
                e.preventDefault();
                e.stopPropagation();
                dragCounter++;
                this.style.transform = 'scale(1.05)';
                this.style.backgroundColor = 'rgba(237, 90, 44, 0.1)';
                this.querySelector('.story-add-icon').style.borderColor = '#ED5A2C';
                this.querySelector('.story-add-icon').style.backgroundColor = 'rgba(237, 90, 44, 0.1)';
            });

            addStoryLabel.addEventListener('dragleave', function(e) {
                e.preventDefault();
                e.stopPropagation();
                dragCounter--;
                if (dragCounter === 0) {
                    this.style.transform = 'scale(1)';
                    this.style.backgroundColor = '';
                    this.querySelector('.story-add-icon').style.borderColor = '';
                    this.querySelector('.story-add-icon').style.backgroundColor = '';
                }
            });

            addStoryLabel.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.stopPropagation();
            });

            addStoryLabel.addEventListener('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                dragCounter = 0;

                // Reset styles
                this.style.transform = 'scale(1)';
                this.style.backgroundColor = '';
                this.querySelector('.story-add-icon').style.borderColor = '';
                this.querySelector('.story-add-icon').style.backgroundColor = '';

                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    // Create a new FileList-like object and assign to input
                    const dt = new DataTransfer();
                    Array.from(files).forEach(file => dt.items.add(file));
                    storyInput.files = dt.files;

                    // Trigger the upload handler
                    handleStoryUpload(storyInput);
                }
            });

            // Prevent default drag behaviors on document
            document.addEventListener('dragover', function(e) {
                e.preventDefault();
            });

            document.addEventListener('drop', function(e) {
                e.preventDefault();
            });

            // Add visual feedback for file input
            storyInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    const addIcon = addStoryLabel.querySelector('.story-add-icon');
                    addIcon.style.animation = 'pulse 0.3s ease';
                    setTimeout(() => {
                        addIcon.style.animation = '';
                    }, 300);
                }
            });

            console.log('✅ Story upload system initialized with drag & drop support');
        }

        // Initialize story upload when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Add a small delay to ensure all elements are rendered
            setTimeout(() => {
                initializeStoryUpload();
            }, 500);
        });

        // Add CSS animations for story upload
        if (!document.getElementById('storyUploadAnimations')) {
            const animationStyles = document.createElement('style');
            animationStyles.id = 'storyUploadAnimations';
            animationStyles.textContent = `
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
        
        /* Enhanced hover effects for story upload */
        .add-story-label:hover .story-add-icon::before {
            left: 100%;
        }
        
        .add-story-label:hover .story-username {
            color: #ED5A2C;
            font-weight: 600;
        }
        
        /* Mobile responsiveness for story upload */
        @media (max-width: 768px) {
            .story-upload-modal {
                padding: 10px;
            }
        }
    `;
            document.head.appendChild(animationStyles);
        }

        // Global functions for story upload
        window.handleStoryUpload = handleStoryUpload;
        window.showStoryUploadPreview = showStoryUploadPreview;
        window.closeStoryUploadModal = closeStoryUploadModal;
        window.uploadStories = uploadStories;
        window.initializeStoryUpload = initializeStoryUpload;

        console.log('✅ Enhanced Story Upload System Loaded Successfully!');
    </script>


</body>

</html>