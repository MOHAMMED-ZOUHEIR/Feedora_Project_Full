<?php
// Include the database connection script
require_once 'config/config.php';
// Start the session to manage user data
session_start();

// Helper function to get profile image
function getProfileImage($userProfileImage)
{
    return !empty($userProfileImage) ? htmlspecialchars($userProfileImage) : 'images/default-profile.png';
}

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to the login page if not logged in
    header("Location: sign-in.php");
    exit();
}

$currentUserId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';

// Get the profile user ID from URL parameter
$profileUserId = isset($_GET['id']) ? (int)$_GET['id'] : $currentUserId;

// Handle follow/unfollow actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];

    if ($_POST['action'] === 'follow' || $_POST['action'] === 'unfollow') {
        $targetUserId = (int)$_POST['user_id'];

        try {
            if ($_POST['action'] === 'follow') {
                $stmt = $pdo->prepare("INSERT INTO FOLLOWERS (USER_ID, FOLLOWER_ID, FOLLOWED_AT) VALUES (?, ?, NOW())");
                if ($stmt->execute([$targetUserId, $currentUserId])) {
                    $response['success'] = true;
                    $response['action'] = 'followed';
                    $response['message'] = 'Successfully followed user';
                }
            } else {
                $stmt = $pdo->prepare("DELETE FROM FOLLOWERS WHERE USER_ID = ? AND FOLLOWER_ID = ?");
                if ($stmt->execute([$targetUserId, $currentUserId])) {
                    $response['success'] = true;
                    $response['action'] = 'unfollowed';
                    $response['message'] = 'Successfully unfollowed user';
                }
            }

            // Get updated follower count
            $countStmt = $pdo->prepare("SELECT COUNT(*) as count FROM FOLLOWERS WHERE USER_ID = ?");
            $countStmt->execute([$targetUserId]);
            $response['new_follower_count'] = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
        } catch (PDOException $e) {
            $response['message'] = 'Error: ' . $e->getMessage();
        }

        echo json_encode($response);
        exit();
    }

    // Handle banner image upload
    if ($_POST['action'] === 'upload_banner' && $profileUserId === $currentUserId) {
        try {
            if (!isset($_FILES['banner_image']) || $_FILES['banner_image']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('File upload failed.');
            }

            $file = $_FILES['banner_image'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'video/mp4', 'video/webm', 'video/ogg'];
            $fileType = strtolower($file['type']);

            if (!in_array($fileType, $allowedTypes)) {
                throw new Exception('Invalid file type. Please upload JPG, PNG, GIF, WEBP images, or MP4, WEBM, OGG videos only.');
            }

            if ($file['size'] > 5 * 1024 * 1024) {
                throw new Exception('File size too large. Maximum size is 5MB.');
            }

            $uploadDir = 'uploads/banners/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $fileName = 'banner_' . $currentUserId . '_' . uniqid() . '.' . $fileExtension;
            $uploadPath = $uploadDir . $fileName;

            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                throw new Exception('Failed to save uploaded file.');
            }

            $updateStmt = $pdo->prepare("UPDATE USERS SET BANNER_IMAGE = ? WHERE USER_ID = ?");
            if (!$updateStmt->execute([$uploadPath, $currentUserId])) {
                unlink($uploadPath);
                throw new Exception('Failed to update database.');
            }

            $response['success'] = true;
            $response['message'] = 'Banner updated successfully!';
            $response['banner_url'] = $uploadPath;
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }

        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    // Handle post editing (only for post owner)
    if ($_POST['action'] === 'edit_post' && isset($_POST['edit_post_id'])) {
        $postId = $_POST['edit_post_id'];
        $description = trim($_POST['edit_description'] ?? '');
        $existingImage = $_POST['existing_image'] ?? null;

        try {
            // Check if the post belongs to the current user
            $checkStmt = $pdo->prepare("SELECT USER_ID FROM POSTS WHERE POSTS_ID = ?");
            $checkStmt->execute([$postId]);
            $post = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$post || $post['USER_ID'] != $currentUserId) {
                $response['message'] = 'You do not have permission to edit this post.';
                echo json_encode($response);
                exit();
            }

            // Handle image upload if a new image is provided
            $imageUrl = $existingImage;
            if (isset($_FILES['edit_post_image']) && $_FILES['edit_post_image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/posts/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $fileExtension = pathinfo($_FILES['edit_post_image']['name'], PATHINFO_EXTENSION);
                $newFileName = uniqid('post_') . '.' . $fileExtension;
                $targetFilePath = $uploadDir . $newFileName;

                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'video/mp4', 'video/webm', 'video/ogg'];
                $fileType = $_FILES['edit_post_image']['type'];

                if (in_array($fileType, $allowedTypes)) {
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
            $stmt->execute([$description, $imageUrl, $postId, $currentUserId]);

            $response['success'] = true;
            $response['message'] = 'Post updated successfully!';
        } catch (PDOException $e) {
            $response['message'] = 'Error updating post: ' . $e->getMessage();
        }

        echo json_encode($response);
        exit();
    }

    // Handle post deletion (only for post owner)
    if ($_POST['action'] === 'delete_post' && isset($_POST['post_id'])) {
        $postId = $_POST['post_id'];

        try {
            // Check if the post belongs to the current user
            $checkStmt = $pdo->prepare("SELECT USER_ID, IMAGE_URL FROM POSTS WHERE POSTS_ID = ?");
            $checkStmt->execute([$postId]);
            $post = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$post || $post['USER_ID'] != $currentUserId) {
                $response['message'] = 'You do not have permission to delete this post.';
                echo json_encode($response);
                exit();
            }

            // Delete the post from the database
            $stmt = $pdo->prepare("DELETE FROM POSTS WHERE POSTS_ID = ? AND USER_ID = ?");
            $stmt->execute([$postId, $currentUserId]);

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

try {
    // Get profile user's information with aggregated data
    $userStmt = $pdo->prepare("
        SELECT 
            u.USER_ID,
            u.NAME,
            u.EMAIL,
            u.PROFILE_IMAGE,
            u.BANNER_IMAGE,
            u.CREATED_AT,
            u.LAST_LOGIN_AT,
            COALESCE(recipe_count.total, 0) as recipe_count,
            COALESCE(follower_data.follower_count, 0) as follower_count,
            COALESCE(following_data.following_count, 0) as following_count,
            COALESCE(post_data.post_count, 0) as post_count
        FROM USERS u
        LEFT JOIN (
            SELECT USER_ID, COUNT(*) as total 
            FROM RECIPES 
            GROUP BY USER_ID
        ) recipe_count ON u.USER_ID = recipe_count.USER_ID
        LEFT JOIN (
            SELECT USER_ID, COUNT(*) as follower_count 
            FROM FOLLOWERS 
            GROUP BY USER_ID
        ) follower_data ON u.USER_ID = follower_data.USER_ID
        LEFT JOIN (
            SELECT FOLLOWER_ID, COUNT(*) as following_count 
            FROM FOLLOWERS 
            GROUP BY FOLLOWER_ID
        ) following_data ON u.USER_ID = following_data.FOLLOWER_ID
        LEFT JOIN (
            SELECT USER_ID, COUNT(*) as post_count 
            FROM POSTS 
            GROUP BY USER_ID
        ) post_data ON u.USER_ID = post_data.USER_ID
        WHERE u.USER_ID = ?
    ");
    $userStmt->execute([$profileUserId]);
    $profileUser = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$profileUser) {
        header("Location: dashboard.php");
        exit();
    }

    // Check if current user is following this profile
    $isFollowing = false;
    if ($currentUserId !== $profileUserId) {
        $followCheckStmt = $pdo->prepare("SELECT * FROM FOLLOWERS WHERE USER_ID = ? AND FOLLOWER_ID = ?");
        $followCheckStmt->execute([$profileUserId, $currentUserId]);
        $isFollowing = (bool)$followCheckStmt->fetch();
    }

    // FIXED: Single posts query with debugging
    $postsStmt = $pdo->prepare(
        "SELECT 
            p.POSTS_ID,
            p.USER_ID, 
            p.IMAGE_URL,
            p.DESCRIPTION,
            p.CREATED_AT,
            u.NAME, 
            u.PROFILE_IMAGE,
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
        WHERE p.USER_ID = ?
        ORDER BY p.CREATED_AT DESC 
        LIMIT 50"
    );
    $postsStmt->execute([$currentUserId, $profileUserId]);
    $posts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);

    // DEBUG: Log the actual posts data
    error_log("=== POSTS DEBUG INFO ===");
    error_log("Profile User ID: " . $profileUserId);
    error_log("Current User ID: " . $currentUserId);
    error_log("Total posts fetched: " . count($posts));

    if (!empty($posts)) {
        foreach ($posts as $index => $post) {
            error_log("Post Index {$index}: ID=" . ($post['POSTS_ID'] ?? 'NULL') . ", User=" . ($post['NAME'] ?? 'NULL'));
        }
    }

    // Check for duplicate POSTS_ID values
    $postIds = array_column($posts, 'POSTS_ID');
    $duplicates = array_diff_assoc($postIds, array_unique($postIds));
    if (!empty($duplicates)) {
        error_log("WARNING: Duplicate POSTS_ID found: " . implode(', ', $duplicates));
    }

    // Process profile images for posts
    $processedPosts = [];
    foreach ($posts as $post) {
        // FIXED: Ensure each post has a valid, unique POSTS_ID
        if (!isset($post['POSTS_ID']) || empty($post['POSTS_ID'])) {
            error_log("WARNING: Post without POSTS_ID found, skipping");
            continue;
        }

        // Convert POSTS_ID to integer and validate
        $post['POSTS_ID'] = intval($post['POSTS_ID']);
        if ($post['POSTS_ID'] <= 0) {
            error_log("WARNING: Invalid POSTS_ID found: " . $post['POSTS_ID']);
            continue;
        }

        // Process profile image
        $post['PROFILE_IMAGE'] = getProfileImage($post['PROFILE_IMAGE']);

        $processedPosts[] = $post;
    }

    // Use the processed posts array
    $posts = $processedPosts;

    // Get user's recipes (limit to 6 for better UI)
    $recipesStmt = $pdo->prepare(
        "SELECT r.*, c.NAME_CATEGORIE, d.DIFFICULTY_NAME, u.NAME as AUTHOR_NAME 
        FROM RECIPES r 
        LEFT JOIN CATEGORIE c ON r.ID_CATEGORIE = c.ID_CATEGORIE 
        LEFT JOIN DIFFICULTY_RECIPES dr ON r.RECIPES_ID = dr.RECIPES_ID 
        LEFT JOIN DIFFICULTY d ON dr.DIFFICULTY_ID = d.DIFFICULTY_ID 
        LEFT JOIN USERS u ON r.USER_ID = u.USER_ID 
        WHERE r.USER_ID = ? 
        ORDER BY r.RECIPES_ID DESC 
        LIMIT 6"
    );
    $recipesStmt->execute([$profileUserId]);
    $recipes = $recipesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get user's favorite recipes (limit to 6 for better UI)
    $favoritesStmt = $pdo->prepare(
        "SELECT r.*, c.NAME_CATEGORIE, d.DIFFICULTY_NAME, u.NAME as AUTHOR_NAME, cl.DATE_COLLECT 
        FROM COLLECT cl
        JOIN RECIPES r ON cl.RECIPES_ID = r.RECIPES_ID
        LEFT JOIN CATEGORIE c ON r.ID_CATEGORIE = c.ID_CATEGORIE 
        LEFT JOIN DIFFICULTY_RECIPES dr ON r.RECIPES_ID = dr.RECIPES_ID 
        LEFT JOIN DIFFICULTY d ON dr.DIFFICULTY_ID = d.DIFFICULTY_ID 
        LEFT JOIN USERS u ON r.USER_ID = u.USER_ID 
        WHERE cl.USER_ID = ? 
        ORDER BY cl.DATE_COLLECT DESC 
        LIMIT 6"
    );
    $favoritesStmt->execute([$profileUserId]);
    $favoriteRecipes = $favoritesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error in profile_settings.php: " . $e->getMessage());
    die("Error: " . $e->getMessage());
}

// Set default images
$bannerImage = !empty($profileUser['BANNER_IMAGE']) ? $profileUser['BANNER_IMAGE'] : 'images/default-banner.jpg';
$profileUser['PROFILE_IMAGE'] = getProfileImage($profileUser['PROFILE_IMAGE']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Feedora - Your Cooking Dashboard">
    <meta name="theme-color" content="#ED5A2C">
    <title><?php echo htmlspecialchars($profileUser['NAME']); ?> - Feedora</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="fonts.css">
    <link rel="stylesheet" href="Home.css">
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Qurova', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            background-color: #f5f5f5;
            color: var(--text-color);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Main Content Layout */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
            display: flex;
            flex-direction: column;
        }

        /* Profile Header - UNCHANGED */
        .profile-header {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
            position: relative;
        }

        .profile-banner {
            position: relative;
            height: 250px;
            overflow: hidden;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        .banner-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .banner-upload {
            position: absolute;
            top: 15px;
            right: 15px;
        }

        .banner-upload-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 12px;
            cursor: pointer;
            transition: var(--transition-speed);
            font-family: 'Qurova', sans-serif;
        }

        .banner-upload-btn:hover {
            background: rgba(0, 0, 0, 0.8);
        }

        .profile-info {
            padding: 0 30px 30px;
            display: flex;
            align-items: flex-end;
            margin-top: -50px;
            position: relative;
            z-index: 2;
        }

        .profile-avatar {
            margin-right: 20px;
            flex-shrink: 0;
        }

        .profile-avatar img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid white;
            object-fit: cover;
            box-shadow: var(--box-shadow);
        }

        .profile-details {
            flex: 1;
            padding-top: 50px;
        }

        .profile-name {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--text-color);
            font-family: 'Qurova', sans-serif;
        }

        .profile-username {
            font-size: 16px;
            color: var(--light-text);
            margin-bottom: 20px;
            font-family: 'Qurova', sans-serif;
        }

        .profile-stats {
            display: flex;
            gap: 30px;
            margin-bottom: 20px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            display: block;
            font-size: 20px;
            font-weight: 700;
            color: var(--text-color);
            font-family: 'Qurova', sans-serif;
        }

        .stat-label {
            font-size: 14px;
            color: var(--light-text);
            font-family: 'Qurova', sans-serif;
        }

        .profile-actions {
            display: flex;
            gap: 12px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: var(--border-radius);
            font-weight: 500;
            font-size: 14px;
            text-decoration: none;
            transition: var(--transition-speed);
            border: none;
            cursor: pointer;
            font-family: 'Qurova', sans-serif;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
            box-shadow: 0 4px 12px rgba(237, 90, 44, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(237, 90, 44, 0.4);
        }

        .btn-secondary {
            background: var(--light-background);
            color: var(--text-color);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: var(--border-color);
        }

        .btn-outline {
            background: transparent;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
            text-decoration: none;
        }

        .btn-outline:hover {
            background: var(--primary-color);
            color: white;
            text-decoration: none;
        }

        /* OPTIMIZED Profile Content Layout */
        .profile-content {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 25px;
            align-items: start;
        }

        /* OPTIMIZED Main Posts Section */
        .profile-main {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
        }

        .section-header {
            padding: 20px 25px;
            border-bottom: 1px solid var(--border-color);
            background: white;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-color);
            margin: 0;
            font-family: 'Qurova', sans-serif;
        }

        .posts-feed {
            padding: 0;
            background: white;
        }

        /* OPTIMIZED Sidebar */
        .profile-sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .sidebar-section {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
        }

        .sidebar-section-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
            background: linear-gradient(135deg, var(--primary-color) 0%, #ff6b3d 100%);
            color: white;
        }

        .sidebar-section-header.favorites {
            background: linear-gradient(135deg, var(--secondary-color) 0%, #66bb6a 100%);
        }

        .sidebar-title {
            font-size: 16px;
            font-weight: 600;
            margin: 0;
            font-family: 'Qurova', sans-serif;
        }

        .sidebar-content {
            padding: 16px 20px;
            max-height: 300px;
            overflow-y: auto;
        }

        .sidebar-content::-webkit-scrollbar {
            width: 4px;
        }

        .sidebar-content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 2px;
        }

        .sidebar-content::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 2px;
        }

        /* Recipe Items */
        .recipes-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .recipe-item {
            display: flex;
            gap: 10px;
            padding: 8px;
            border-radius: 8px;
            transition: var(--transition-speed);
            border: 1px solid #f0f0f0;
        }

        .recipe-item:hover {
            background: var(--light-background);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .recipe-image {
            width: 50px;
            height: 50px;
            border-radius: 6px;
            overflow: hidden;
            flex-shrink: 0;
        }

        .recipe-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .recipe-info {
            flex: 1;
            min-width: 0;
        }

        .recipe-title a {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-color);
            text-decoration: none;
            display: block;
            margin-bottom: 2px;
            font-family: 'Qurova', sans-serif;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .recipe-title a:hover {
            color: var(--primary-color);
        }

        .recipe-category {
            font-size: 11px;
            color: var(--light-text);
            margin: 0;
            font-family: 'Qurova', sans-serif;
        }

        .empty-state-small {
            text-align: center;
            padding: 30px 20px;
            color: var(--light-text);
        }

        .empty-state-small p {
            font-size: 13px;
            margin: 0;
            font-family: 'Qurova', sans-serif;
        }

        .sidebar-footer {
            padding: 12px 20px;
            border-top: 1px solid var(--border-color);
            background: var(--light-background);
        }

        /* Post Cards - Same as Dashboard */
        .post-card {
            background: white;
            border-bottom: 1px solid #f0f0f0;
            overflow: visible !important;
            width: 100%;
            position: relative !important;
        }

        .post-card:last-child {
            border-bottom: none;
        }

        .post-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 16px 20px;
            position: relative;
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

        /* Post Image Styles */
        .post-image {
            width: 100% !important;
            max-height: none !important;
            overflow: hidden !important;
            display: flex !important;
            justify-content: center !important;
            align-items: flex-start !important;
            background-color: #fff !important;
            position: relative !important;
            cursor: pointer !important;
            border-radius: 0 !important;
            isolation: isolate !important;
        }

        .post-image-content {
            width: 100% !important;
            height: auto !important;
            max-width: 100% !important;
            max-height: 100% !important;
            object-fit: cover !important;
            display: block !important;
            cursor: pointer !important;
            transition: none !important;
            position: relative !important;
            z-index: 1 !important;
        }

        .post-image video {
            width: 100% !important;
            height: auto !important;
            max-width: 100% !important;
            object-fit: contain !important;
            cursor: pointer !important;
            transition: none !important;
            border-radius: 0 !important;
            display: block !important;
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

        /* Post Stats */
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

        /* Food reactions positioning */
        .food-reactions {
            position: absolute !important;
            bottom: 45px !important;
            left: 100% !important;
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

        .like-count {
            cursor: pointer;
            transition: color 0.2s;
        }

        .like-count:hover {
            color: var(--primary-color);
        }

        .post-comments {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #555;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
        }

        /* No Posts State */
        .no-posts-message {
            text-align: center;
            padding: 60px 30px;
            color: var(--light-text);
        }

        .no-posts-message p {
            font-size: 16px;
            font-family: 'Qurova', sans-serif;
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

        .close-modal {
            position: absolute;
            right: 20px;
            top: 15px;
            font-size: 28px;
            font-weight: bold;
            color: #aaa;
            cursor: pointer;
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

        .post-button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 25px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition-speed);
            margin: 0 auto;
        }

        .post-button:hover {
            background-color: #d94e22;
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

        /* Comments Modal Styles */
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

        .comment-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            background: #ccc;
            box-shadow: none;
        }

        .no-comments {
            text-align: center;
            color: #666;
            padding: 50px 20px;
            font-size: 16px;
        }

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

        /* Animations */
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

        /* Responsive Design */
        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
            }

            .profile-content {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .profile-info {
                flex-direction: column;
                align-items: center;
                text-align: center;
                padding: 20px 30px 30px;
            }

            .profile-avatar {
                margin-right: 0;
                margin-bottom: 15px;
            }

            .profile-stats {
                justify-content: center;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 15px;
            }

            .profile-banner {
                height: 180px;
            }

            .profile-avatar img {
                width: 100px;
                height: 100px;
            }

            .profile-name {
                font-size: 24px;
            }

            .profile-stats {
                gap: 20px;
            }

            .stat-number {
                font-size: 18px;
            }

            .profile-info {
                margin-top: -40px;
                padding: 15px 20px 25px;
            }

            .section-header {
                padding: 15px 20px;
            }

            .sidebar-section {
                max-height: 250px;
            }

            /* Mobile Food Reactions for Posts */
            .food-reactions {
                position: fixed !important;
                bottom: 100px !important;
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
        }

        /* Small Mobile Devices */
        @media (max-width: 480px) {

            .food-reactions,
            .comment-food-reactions {
                min-width: 300px !important;
                padding: 10px 12px !important;
                bottom: 70px !important;
                border-radius: 30px !important;
            }

            .reaction-icon,
            .comment-reaction-icon {
                min-width: 44px;
                height: 44px;
                margin: 0 2px;
                padding: 10px;
            }

            .reaction-icon span,
            .comment-reaction-icon span {
                font-size: 18px;
            }
        }

        /* Very Small Screens */
        @media (max-width: 360px) {

            .food-reactions,
            .comment-food-reactions {
                min-width: 280px !important;
                padding: 8px 10px !important;
                gap: 1px !important;
            }

            .reaction-icon,
            .comment-reaction-icon {
                min-width: 40px;
                height: 40px;
                margin: 0 1px;
                padding: 8px;
            }

            .reaction-icon span,
            .comment-reaction-icon span {
                font-size: 16px;
            }
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <?php include 'header.php'; ?>

        <!-- Profile Header - UNCHANGED -->
        <div class="profile-header">
            <div class="profile-banner">
                <img src="<?php echo htmlspecialchars($bannerImage); ?>" alt="Profile Banner" class="banner-image">
                <?php if ($profileUserId === $currentUserId): ?>
                    <div class="banner-upload">
                        <input type="file" id="banner-upload" accept="image/*" style="display: none;">
                        <button class="banner-upload-btn" onclick="document.getElementById('banner-upload').click()">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"></path>
                                <circle cx="12" cy="13" r="3"></circle>
                            </svg>
                            Change Banner
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <div class="profile-info">
                <div class="profile-avatar">
                    <img src="<?php echo htmlspecialchars($profileUser['PROFILE_IMAGE']); ?>"
                        alt="<?php echo htmlspecialchars($profileUser['NAME']); ?>">
                </div>

                <div class="profile-details">
                    <h1 class="profile-name"><?php echo htmlspecialchars($profileUser['NAME']); ?></h1>
                    <p class="profile-username">@<?php echo strtolower(str_replace(' ', '', $profileUser['NAME'])); ?></p>

                    <div class="profile-stats">
                        <div class="stat-item">
                            <span class="stat-number"><?php echo number_format($profileUser['post_count']); ?></span>
                            <span class="stat-label">Posts</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number" id="follower-count"><?php echo number_format($profileUser['follower_count']); ?></span>
                            <span class="stat-label">Followers</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo number_format($profileUser['following_count']); ?></span>
                            <span class="stat-label">Following</span>
                        </div>
                    </div>

                    <?php if ($profileUserId !== $currentUserId): ?>
                        <div class="profile-actions">
                            <button class="btn <?php echo $isFollowing ? 'btn-secondary' : 'btn-primary'; ?>"
                                onclick="toggleFollow(<?php echo $profileUserId; ?>)"
                                id="follow-btn">
                                <?php echo $isFollowing ? 'Following' : 'Follow'; ?>
                            </button>
                            <a href="chat.php?id=<?php echo $profileUserId; ?>" class="btn btn-outline">Message</a>
                        </div>
                    <?php else: ?>
                        <div class="profile-actions">
                            <a href="settings.php" class="btn btn-secondary">Edit Profile</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- OPTIMIZED Profile Content -->
        <div class="profile-content">
            <!-- Main Posts Section -->
            <div class="profile-main">
                <div class="section-header">
                    <h2 class="section-title">Posts (<?php echo count($posts); ?>)</h2>
                </div>

                <div class="posts-feed">
                    <?php if (count($posts) > 0): ?>
                        <?php foreach ($posts as $arrayIndex => $post): ?>
                            <?php
                            // CRITICAL FIX: Multiple layers of validation
                            $postId = intval($post['POSTS_ID']);
                            $uniqueKey = 'post_' . $postId . '_' . $arrayIndex; // Fallback unique identifier

                            // Additional validation
                            if ($postId <= 0) {
                                error_log("ERROR: Invalid postId in render loop: " . $postId);
                                continue;
                            }

                            // Format date and other data
                            $postDate = new DateTime($post['CREATED_AT']);
                            $formattedDate = $postDate->format('M d, Y \a\t g:i A');

                            $totalReactions = intval($post['total_reactions'] ?? 0);
                            $userReaction = $post['user_reaction'] ?? null;
                            $commentCount = intval($post['comment_count'] ?? 0);

                            // Debug output
                            error_log("Rendering post: ID={$postId}, Index={$arrayIndex}, UniqueKey={$uniqueKey}");
                            ?>

                            <!-- FIXED: Use postId with additional validation -->
                            <div class="post-card" data-post-id="<?php echo $postId; ?>" data-unique-key="<?php echo $uniqueKey; ?>">
                                <!-- Post Header -->
                                <div class="post-header">
                                    <div class="post-user-info">
                                        <img src="<?php echo htmlspecialchars($post['PROFILE_IMAGE']); ?>" alt="Profile" class="post-user-pic">
                                        <div class="post-user-details">
                                            <span class="post-user-name"><?php echo htmlspecialchars($post['NAME']); ?></span>
                                            <div class="post-meta-row">
                                                <span class="post-shared">shared a</span>
                                                <?php
                                                $fileType = 'post';
                                                if (!empty($post['IMAGE_URL'])) {
                                                    $fileExtension = strtolower(pathinfo($post['IMAGE_URL'], PATHINFO_EXTENSION));
                                                    $fileType = in_array($fileExtension, ['mp4', 'webm', 'ogg']) ? 'video' : 'image';
                                                }
                                                ?>
                                                <span class="post-meta"><?php echo $fileType; ?></span>
                                                <span class="post-date"><?php echo $formattedDate; ?></span>
                                                <!-- DEBUG: Show post ID -->
                                                <span class="post-debug" style="color: #999; font-size: 11px;">[ID: <?php echo $postId; ?>]</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Post actions menu (edit/delete) - only for post owner -->
                                    <?php if (intval($post['USER_ID']) === intval($currentUserId)): ?>
                                        <div class="post-actions">
                                            <div class="post-actions-menu" onclick="togglePostMenu(<?php echo $postId; ?>)">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <circle cx="12" cy="12" r="1"></circle>
                                                    <circle cx="19" cy="12" r="1"></circle>
                                                    <circle cx="5" cy="12" r="1"></circle>
                                                </svg>
                                            </div>

                                            <div class="post-menu-dropdown" id="post-menu-<?php echo $postId; ?>">
                                                <div class="post-menu-item edit-post" onclick="editPost(<?php echo $postId; ?>, `<?php echo htmlspecialchars(addslashes($post['DESCRIPTION'] ?? ''), ENT_QUOTES); ?>`, `<?php echo htmlspecialchars($post['IMAGE_URL'] ?? '', ENT_QUOTES); ?>`)">Edit Post</div>
                                                <div class="post-menu-item delete-post" onclick="deletePost(<?php echo $postId; ?>)">Delete Post</div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Post Media -->
                                <?php if (!empty($post['IMAGE_URL'])): ?>
                                    <div class="post-image">
                                        <?php
                                        $fileExtension = strtolower(pathinfo($post['IMAGE_URL'], PATHINFO_EXTENSION));
                                        $isVideo = in_array($fileExtension, ['mp4', 'webm', 'ogg']);
                                        ?>
                                        <?php if ($isVideo): ?>
                                            <video controls width="100%">
                                                <source src="<?php echo htmlspecialchars($post['IMAGE_URL']); ?>" type="video/<?php echo $fileExtension; ?>">
                                                Your browser does not support the video tag.
                                            </video>
                                        <?php else: ?>
                                            <img src="<?php echo htmlspecialchars($post['IMAGE_URL']); ?>" alt="Post Image" class="post-image-content" onclick="openImageModal('<?php echo htmlspecialchars($post['IMAGE_URL']); ?>')">
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Post Description -->
                                <?php if (!empty($post['DESCRIPTION'])): ?>
                                    <div class="post-description">
                                        <p><?php echo nl2br(htmlspecialchars($post['DESCRIPTION'])); ?></p>
                                    </div>
                                <?php endif; ?>

                                <!-- Post Stats -->
                                <div class="post-stats">
                                    <div class="post-likes-container" data-post-id="<?php echo $postId; ?>">
                                        <div class="post-likes <?php echo $userReaction ? 'active' : ''; ?>" data-user-reaction="<?php echo $userReaction ?: ''; ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="<?php echo $userReaction ? 'var(--primary-color)' : 'none'; ?>" stroke="<?php echo $userReaction ? 'var(--primary-color)' : 'currentColor'; ?>" class="like-icon" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
                                            </svg>
                                            <?php if ($totalReactions > 0): ?>
                                                <span class="like-count" onclick="showReactionUsers(<?php echo $postId; ?>)">
                                                    <?php echo $totalReactions . ($totalReactions === 1 ? ' reaction' : ' reactions'); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="like-count">0 reactions</span>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Food Reactions Panel -->
                                        <div class="food-reactions" id="food-reactions-<?php echo $postId; ?>">
                                            <?php
                                            $reactions = [
                                                'yummy' => ['emoji' => '🍔', 'title' => 'Yummy!'],
                                                'delicious' => ['emoji' => '🍕', 'title' => 'Delicious!'],
                                                'tasty' => ['emoji' => '🍰', 'title' => 'Tasty!'],
                                                'love' => ['emoji' => '🍲', 'title' => 'Love it!'],
                                                'amazing' => ['emoji' => '🍗', 'title' => 'Amazing!']
                                            ];
                                            ?>
                                            <?php foreach ($reactions as $type => $data): ?>
                                                <?php $isUserReaction = ($userReaction === $type) ? ' style="background-color: rgba(237, 90, 44, 0.2); transform: scale(1.1);"' : ''; ?>
                                                <div class="reaction-icon" data-reaction="<?php echo $type; ?>" data-post-id="<?php echo $postId; ?>" title="<?php echo $data['title']; ?>" onclick="handleReaction(<?php echo $postId; ?>, '<?php echo $type; ?>')" <?php echo $isUserReaction; ?>>
                                                    <span><?php echo $data['emoji']; ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <!-- Comments Section -->
                                    <div class="post-comments" onclick="openCommentsModal(<?php echo $postId; ?>)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                                        </svg>
                                        <?php if ($commentCount > 0): ?>
                                            <span id="comment-count-<?php echo $postId; ?>"><?php echo $commentCount . ($commentCount === 1 ? ' comment' : ' comments'); ?></span>
                                        <?php else: ?>
                                            <span id="comment-count-<?php echo $postId; ?>">0 comments</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-posts-message">
                            <p><?php echo $profileUserId === $currentUserId ? 'You haven\'t shared any posts yet.' : 'No posts shared yet.'; ?></p>
                        </div>
                    <?php endif; ?>
                </div>


            </div>
            <!-- OPTIMIZED Sidebar -->
            <div class="profile-sidebar">
                <!-- Our Recipes Section -->
                <div class="sidebar-section">
                    <div class="sidebar-section-header">
                        <h3 class="sidebar-title">Our Recipes (<?php echo count($recipes); ?>)</h3>
                    </div>

                    <div class="sidebar-content">
                        <?php if (!empty($recipes)): ?>
                            <div class="recipes-list">
                                <?php foreach ($recipes as $recipe): ?>
                                    <div class="recipe-item">
                                        <div class="recipe-image">
                                            <img src="<?php echo htmlspecialchars($recipe['PHOTO_URL'] ?? 'images/default-recipe.jpg'); ?>"
                                                alt="<?php echo htmlspecialchars($recipe['TITLE']); ?>">
                                        </div>
                                        <div class="recipe-info">
                                            <h4 class="recipe-title">
                                                <a href="view-recipe.php?id=<?php echo $recipe['RECIPES_ID']; ?>">
                                                    <?php echo htmlspecialchars($recipe['TITLE']); ?>
                                                </a>
                                            </h4>
                                            <p class="recipe-category">
                                                <?php echo htmlspecialchars($recipe['NAME_CATEGORIE'] ?? 'Uncategorized'); ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state-small">
                                <p><?php echo $profileUserId === $currentUserId ? 'You haven\'t created any recipes yet' : 'No recipes shared yet'; ?></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($recipes)): ?>
                        <div class="sidebar-footer">
                            <a href="recipes.php?user=<?php echo $profileUserId; ?>" class="btn btn-outline" style="width: 100%; text-align: center; font-size: 12px; padding: 8px 12px;">View All Recipes</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Favorite Recipes Section -->
                <div class="sidebar-section">
                    <div class="sidebar-section-header favorites">
                        <h3 class="sidebar-title">Favorite Recipes (<?php echo count($favoriteRecipes); ?>)</h3>
                    </div>

                    <div class="sidebar-content">
                        <?php if (!empty($favoriteRecipes)): ?>
                            <div class="recipes-list">
                                <?php foreach ($favoriteRecipes as $recipe): ?>
                                    <div class="recipe-item">
                                        <div class="recipe-image">
                                            <img src="<?php echo htmlspecialchars($recipe['PHOTO_URL'] ?? 'images/default-recipe.jpg'); ?>"
                                                alt="<?php echo htmlspecialchars($recipe['TITLE']); ?>">
                                        </div>
                                        <div class="recipe-info">
                                            <h4 class="recipe-title">
                                                <a href="view-recipe.php?id=<?php echo $recipe['RECIPES_ID']; ?>">
                                                    <?php echo htmlspecialchars($recipe['TITLE']); ?>
                                                </a>
                                            </h4>
                                            <p class="recipe-category">
                                                <?php echo htmlspecialchars($recipe['NAME_CATEGORIE'] ?? 'Uncategorized'); ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state-small">
                                <p><?php echo $profileUserId === $currentUserId ? 'You haven\'t favorited any recipes yet' : 'No favorite recipes yet'; ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
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
                    <img src="<?php echo htmlspecialchars($profileUser['PROFILE_IMAGE']); ?>" alt="Your avatar" class="comment-avatar">
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
    </script>

</html>