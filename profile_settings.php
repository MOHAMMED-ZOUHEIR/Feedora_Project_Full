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
    }    // Get user's posts
    $postsStmt = $pdo->prepare(
        "SELECT p.*, u.NAME, u.PROFILE_IMAGE, u.USER_ID as AUTHOR_ID,
        CASE 
            WHEN p.IMAGE_URL LIKE '%.mp4' OR p.IMAGE_URL LIKE '%.webm' OR p.IMAGE_URL LIKE '%.ogg' THEN 'video'
            ELSE 'image'
        END as MEDIA_TYPE
        FROM POSTS p 
        JOIN USERS u ON p.USER_ID = u.USER_ID 
        WHERE p.USER_ID = ? 
        ORDER BY p.CREATED_AT DESC 
        LIMIT 6"
    );
    $postsStmt->execute([$profileUserId]);
    $posts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);    // Get user's recipes
    $recipesStmt = $pdo->prepare(
        "SELECT r.*, c.NAME_CATEGORIE, d.DIFFICULTY_NAME, u.NAME as AUTHOR_NAME 
        FROM RECIPES r 
        LEFT JOIN CATEGORIE c ON r.ID_CATEGORIE = c.ID_CATEGORIE 
        LEFT JOIN DIFFICULTY_RECIPES dr ON r.RECIPES_ID = dr.RECIPES_ID 
        LEFT JOIN DIFFICULTY d ON dr.DIFFICULTY_ID = d.DIFFICULTY_ID 
        LEFT JOIN USERS u ON r.USER_ID = u.USER_ID 
        WHERE r.USER_ID = ? 
        ORDER BY r.RECIPES_ID DESC 
        LIMIT 3"
    );
    $recipesStmt->execute([$profileUserId]);
    $recipes = $recipesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get user's favorite recipes
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
        LIMIT 3"
    );
    $favoritesStmt->execute([$profileUserId]);
    $favoriteRecipes = $favoritesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Set default images
$bannerImage = !empty($profileUser['BANNER_IMAGE']) ? $profileUser['BANNER_IMAGE'] : 'images/default-banner.jpg';
$profileUser['PROFILE_IMAGE'] = getProfileImage($profileUser['PROFILE_IMAGE']);

// Process profile images for posts
foreach ($posts as &$post) {
    $post['PROFILE_IMAGE'] = getProfileImage($post['PROFILE_IMAGE']);
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
    <title><?php echo htmlspecialchars($profileUser['NAME']); ?> - Feedora</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="images/Frame 1171277973.svg" type="image/svg+xml">



    <style>
        :root {
            --primary-color: #ED5A2C;
            --secondary-color: #4CAF50;
            --text-color: #333;
            --light-text: #666;
            --background-color: #fff;
            --light-background: #f9f9f9;
            --border-radius: 12px;
            --transition-speed: 0.3s;
            --box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            --hover-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
            --sidebar-width: 250px;
            --header-height: 70px;
            --border-light: #e2e8f0;
            --shadow-soft: 0 4px 20px rgba(0, 0, 0, 0.06);
            --shadow-medium: 0 8px 30px rgba(0, 0, 0, 0.12);
            --shadow-strong: 0 15px 40px rgba(0, 0, 0, 0.15);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 24px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
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

        /* Profile Header */
        .profile-header {
            background: white;
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow-soft);
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
            border-radius: var(--radius-md);
            font-size: 12px;
            cursor: pointer;
            transition: var(--transition);
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
            box-shadow: var(--shadow-medium);
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
            border-radius: var(--radius-md);
            font-weight: 500;
            font-size: 14px;
            text-decoration: none;
            transition: var(--transition);
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
            border: 1px solid var(--border-light);
        }

        .btn-secondary:hover {
            background: var(--border-light);
        }

        .btn-outline {
            background: transparent;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }

        .btn-outline:hover {
            background: var(--primary-color);
            color: white;
        }

        .btn-outline:hover,
        .btn-outline:visited {
            color: inherit;
            text-decoration: none;
        }

        .btn-outline:hover {
            color: white;
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 12px;
        }

        /* Profile Content Layout */
        .profile-content {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 30px;
            align-items: start;
        }

        .profile-posts {
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-soft);
            overflow: hidden;
        }

        .section-header {
            padding: 25px 30px;
            border-bottom: 1px solid var(--border-light);
        }

        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-color);
            margin: 0;
            font-family: 'Qurova', sans-serif;
        }

        .posts-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            padding: 20px;
            background: white;
        }

        .post-card {
            background: white;
            transition: var(--transition);
            cursor: pointer;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            width: 100%;
            max-width: 680px;
            margin: 0 auto;
        }

        .post-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .post-card-media {
            overflow: hidden;
            border-radius: var(--radius-md) var(--radius-md) 0 0;
            position: relative;
            background: #000;
        }

        .post-card-media img,
        .post-card-media video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: var(--transition);
        }

        .post-card:hover .post-card-media img,
        .post-card:hover .post-card-media video {
            transform: scale(1.05);
        }

        .post-card-media video {
            background: #000;
            cursor: pointer;
        }



        .post-card-description {
            font-size: 16px;
            color: var(--text-color);
            line-height: 1.6;
            margin-bottom: 15px;
            font-family: 'Qurova', sans-serif;
        }

        .post-card-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .post-card-time {
            font-size: 12px;
            color: var(--light-text);
            font-family: 'Qurova', sans-serif;
        }

        .empty-state {
            padding: 60px 30px;
            text-align: center;
            color: var(--light-text);
        }

        .empty-state-icon {
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .empty-state p {
            font-size: 16px;
            font-family: 'Qurova', sans-serif;
        }

        /* Profile Sidebar - Our Recipes Section */
        .profile-sidebar {
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-soft);
            padding: 25px;
            height: fit-content;
            position: sticky;
            top: 90px;
        }

        .sidebar-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--text-color);
            font-family: 'Qurova', sans-serif;
        }

        .recipes-list {
            margin-bottom: 20px;
        }

        .recipe-item {
            display: flex;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-light);
            transition: var(--transition);
        }

        .recipe-item:last-child {
            border-bottom: none;
        }

        .recipe-item:hover {
            background: var(--light-background);
            margin: 0 -15px;
            padding: 12px 15px;
            border-radius: var(--radius-md);
        }

        .recipe-image {
            width: 60px;
            height: 60px;
            border-radius: var(--radius-md);
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
            font-size: 14px;
            font-weight: 600;
            color: var(--text-color);
            text-decoration: none;
            display: block;
            margin-bottom: 4px;
            font-family: 'Qurova', sans-serif;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .recipe-title a:hover {
            color: var(--primary-color);
        }

        .recipe-category {
            font-size: 12px;
            color: var(--light-text);
            margin: 0;
            font-family: 'Qurova', sans-serif;
        }

        .empty-state-small {
            text-align: center;
            padding: 40px 20px;
            color: var(--light-text);
        }

        .empty-state-small p {
            font-size: 14px;
            margin: 0;
            font-family: 'Qurova', sans-serif;
        }

        /* Favorite Recipes Section */
        .sidebar-section-favorite {
            background: white;
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-soft);
            padding: 25px;
            height: fit-content;
            position: sticky;
            top: 90px;
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

            .profile-sidebar {
                position: static;
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

            .posts-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }

            .profile-info {
                margin-top: -40px;
                padding: 15px 20px 25px;
            }

            .section-header {
                padding: 20px;
            }

            .profile-sidebar {
                padding: 20px;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 10px;
            }

            .profile-banner {
                height: 150px;
            }

            .profile-avatar img {
                width: 80px;
                height: 80px;
            }

            .profile-name {
                font-size: 20px;
            }

            .profile-username {
                font-size: 14px;
            }

            .profile-stats {
                gap: 15px;
            }

            .stat-number {
                font-size: 16px;
            }

            .stat-label {
                font-size: 12px;
            }

            .posts-grid {
                grid-template-columns: 1fr;
            }

            .btn {
                padding: 8px 16px;
                font-size: 12px;
            }

            .profile-actions {
                flex-direction: column;
                width: 100%;
            }

            .profile-actions .btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* Image Modal Styles */
        .image-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .image-modal-content {
            position: relative;
            max-width: 90%;
            max-height: 90vh;
            margin: auto;
        }

        .image-container {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        #modalImage {
            max-width: 100%;
            max-height: 90vh;
            object-fit: contain;
        }

        .image-close-btn {
            position: absolute;
            top: -40px;
            right: 0;
            color: #fff;
            font-size: 30px;
            font-weight: bold;
            cursor: pointer;
        }

        .image-close-btn:hover {
            color: #ccc;
        }
    </style>
</head>

<body>
    <!-- Include Sidebar -->
    <?php include 'sidebar.php'; ?>



    <!-- Main Content -->
    <main class="main-content">
        <!-- Include Header -->
        <?php include 'header.php'; ?>
        <!-- Profile Header -->
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
                            <span class="stat-number"><?php echo number_format($profileUser['follower_count']); ?></span>
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

        <!-- Profile Content -->
        <div class="profile-content">
            <!-- Posts Section -->
            <div class="profile-posts">
                <div class="section-header">
                    <h2 class="section-title">Posts</h2>
                </div>

                <?php if (!empty($posts)): ?>
                    <div class="posts-grid"> <?php foreach ($posts as $post): ?>
                            <article class="post-card">
                                <?php if (!empty($post['IMAGE_URL'])): ?>
                                    <div class="post-card-media">
                                        <?php if ($post['MEDIA_TYPE'] === 'video'): ?>
                                            <video controls>
                                                <source src="<?php echo htmlspecialchars($post['IMAGE_URL']); ?>" type="video/mp4">
                                                Your browser does not support the video tag.
                                            </video>
                                        <?php else: ?>
                                            <img src="<?php echo htmlspecialchars($post['IMAGE_URL']); ?>"
                                                alt="Post image" loading="lazy">
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="post-card-content">
                                    <?php if (!empty($post['DESCRIPTION'])): ?>
                                        <p class="post-card-description">
                                            <?php echo htmlspecialchars(substr($post['DESCRIPTION'], 0, 120)); ?>
                                            <?php if (strlen($post['DESCRIPTION']) > 120): ?>...<?php endif; ?>
                                        </p>
                                    <?php endif; ?>

                                    <div class="post-card-meta">
                                        <span class="post-card-time">
                                            <?php echo date('M j, Y', strtotime($post['CREATED_AT'])); ?>
                                        </span>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                <polyline points="21,15 16,10 5,21"></polyline>
                            </svg>
                        </div>
                        <p>No posts yet</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recipes Sidebar -->
            <div class="profile-sidebar">
                <div class="sidebar-section">
                    <h3 class="sidebar-title">Our Recipes</h3>

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

                        <a href="recipes.php?user=<?php echo $profileUserId; ?>" class="btn btn-outline btn-sm">View All Recipes</a> <?php else: ?>
                        <div class="empty-state-small">
                            <p>No recipes shared yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Favorite Recipes Section -->
            <div class="sidebar-section-favorite">
                <h3 class="sidebar-title">Favorite Recipes</h3>

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

                    <a href="recipes.php?favorites=1&user=<?php echo $profileUserId; ?>" class="btn btn-outline btn-sm">View All Favorites</a>
                <?php else: ?>
                    <div class="empty-state-small">
                        <p>No favorite recipes yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Image Modal -->
    <div id="imageModal" class="modal image-modal">
        <div class="image-modal-content">
            <span class="close-modal image-close-btn">×</span>
            <div class="image-container">
                <img id="modalImage" src="" alt="Full Size Image">
            </div>
        </div>
    </div>

    <script>
        function toggleFollow(userId) {
            const followBtn = document.getElementById('follow-btn');
            const isFollowing = followBtn.textContent.trim() === 'Following';
            const action = isFollowing ? 'unfollow' : 'follow';

            const formData = new FormData();
            formData.append('action', action);
            formData.append('user_id', userId);

            fetch('profile_settings.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        followBtn.textContent = data.action === 'followed' ? 'Following' : 'Follow';
                        followBtn.className = data.action === 'followed' ? 'btn btn-secondary' : 'btn btn-primary';

                        // Update follower count if provided
                        if (data.new_follower_count !== undefined) {
                            const followerStat = document.querySelector('.stat-item:nth-child(2) .stat-number');
                            if (followerStat) {
                                followerStat.textContent = new Number(data.new_follower_count).toLocaleString();
                            }
                        }
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
        }

        // Banner upload functionality
        document.addEventListener('DOMContentLoaded', function() {
            const bannerUpload = document.getElementById('banner-upload');
            if (bannerUpload) {
                bannerUpload.addEventListener('change', function(e) {
                    if (e.target.files.length > 0) {
                        const formData = new FormData();
                        formData.append('action', 'upload_banner');
                        formData.append('banner_image', e.target.files[0]);

                        fetch('profile_settings.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    location.reload();
                                } else {
                                    alert(data.message);
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                alert('An error occurred while uploading the banner.');
                            });
                    }
                });
            }
        });

        // Image modal functionality
        document.addEventListener('DOMContentLoaded', function() {
            const imageModal = document.getElementById('imageModal');
            const modalImage = document.getElementById('modalImage');
            const imageCloseBtn = document.querySelector('.image-close-btn');
            document.querySelectorAll('.post-card-media img').forEach(img => {
                img.addEventListener('click', function() {
                    modalImage.src = this.src;
                    imageModal.style.display = 'flex';
                });
            });

            imageCloseBtn.addEventListener('click', function() {
                imageModal.style.display = 'none';
                modalImage.src = '';
            });

            imageModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.style.display = 'none';
                    modalImage.src = '';
                }
            });
        });
    </script>
</body>

</html>