<?php
require_once 'config/config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Handle AJAX follow/unfollow requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_follow') {
    header('Content-Type: application/json');
    
    $chefId = (int)$_POST['chef_id'];
    $userId = $_SESSION['user_id'];
    
    try {
        // Create FOLLOWERS table if it doesn't exist
        $createTable = "
            CREATE TABLE IF NOT EXISTS FOLLOWERS (
               ID INTEGER NOT NULL AUTO_INCREMENT,
               USER_ID INTEGER NOT NULL,
               FOLLOWER_ID INTEGER NOT NULL,
               FOLLOWED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
               PRIMARY KEY (ID),
               UNIQUE KEY unique_follow (USER_ID, FOLLOWER_ID)
            )
        ";
        $pdo->exec($createTable);

        // Check if already following
        $checkStmt = $pdo->prepare("SELECT * FROM FOLLOWERS WHERE USER_ID = ? AND FOLLOWER_ID = ?");
        $checkStmt->execute([$chefId, $userId]);
        $isFollowing = $checkStmt->fetch();

        if ($isFollowing) {
            // Unfollow
            $unfollowStmt = $pdo->prepare("DELETE FROM FOLLOWERS WHERE USER_ID = ? AND FOLLOWER_ID = ?");
            $unfollowStmt->execute([$chefId, $userId]);
            
            // Get updated follower count
            $countStmt = $pdo->prepare("SELECT COUNT(*) as follower_count FROM FOLLOWERS WHERE USER_ID = ?");
            $countStmt->execute([$chefId]);
            $newCount = $countStmt->fetch()['follower_count'];
            
            echo json_encode([
                'success' => true, 
                'action' => 'unfollowed', 
                'message' => 'Chef unfollowed successfully!',
                'new_follower_count' => $newCount
            ]);
        } else {
            // Follow
            $followStmt = $pdo->prepare("INSERT INTO FOLLOWERS (USER_ID, FOLLOWER_ID) VALUES (?, ?)");
            $followStmt->execute([$chefId, $userId]);
            
            // Get updated follower count
            $countStmt = $pdo->prepare("SELECT COUNT(*) as follower_count FROM FOLLOWERS WHERE USER_ID = ?");
            $countStmt->execute([$chefId]);
            $newCount = $countStmt->fetch()['follower_count'];
            
            echo json_encode([
                'success' => true, 
                'action' => 'followed', 
                'message' => 'Now following this chef!',
                'new_follower_count' => $newCount
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Fetch unique chefs with their details (excluding current user and ensuring no duplicates)
$stmt = $pdo->prepare("
    SELECT DISTINCT
        u.USER_ID,
        u.NAME,
        u.PROFILE_IMAGE,
        COALESCE(recipe_data.recipe_count, 0) as recipe_count,
        COALESCE(recipe_data.specialties, '') as specialties,
        COALESCE(follower_data.follower_count, 0) as follower_count
    FROM USERS u
    LEFT JOIN (
        SELECT 
            r.USER_ID,
            COUNT(DISTINCT r.RECIPES_ID) as recipe_count,
            GROUP_CONCAT(DISTINCT c.NAME_CATEGORIE SEPARATOR ', ') as specialties
        FROM RECIPES r
        LEFT JOIN CATEGORIE c ON r.ID_CATEGORIE = c.ID_CATEGORIE
        GROUP BY r.USER_ID
    ) recipe_data ON u.USER_ID = recipe_data.USER_ID
    LEFT JOIN (
        SELECT 
            f.USER_ID,
            COUNT(DISTINCT f.FOLLOWER_ID) as follower_count
        FROM FOLLOWERS f
        GROUP BY f.USER_ID
    ) follower_data ON u.USER_ID = follower_data.USER_ID
    WHERE u.USER_ID != ?
    AND u.NAME IS NOT NULL 
    AND u.NAME != ''
    GROUP BY u.USER_ID, u.NAME, u.PROFILE_IMAGE
    ORDER BY follower_data.follower_count DESC, recipe_data.recipe_count DESC
    LIMIT 20
");
$stmt->execute([$_SESSION['user_id']]);
$chefs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check which chefs the current user is following
$followingStmt = $pdo->prepare("
    SELECT USER_ID FROM FOLLOWERS WHERE FOLLOWER_ID = ?
");
$followingStmt->execute([$_SESSION['user_id']]);
$following = $followingStmt->fetchAll(PDO::FETCH_COLUMN);

// Chef expertise data (demo content)
$expertiseData = [
    'Gordon Ramsay' => [
        'title' => 'British Celebrity Chef',
        'expertise' => ['Fine Dining', 'French Cuisine', 'Restaurant Management'],
        'image' => 'images/chefs/gordon-ramsay.jpg'
    ],
    'ChoumiCha Chafai' => [
        'title' => 'The Great Lady Of Moroccan cuisine',
        'expertise' => ['Moroccan Cuisine', 'Traditional Cooking', 'Pastry'],
        'image' => 'images/chefs/choumicha-chafai.jpg'
    ],
    'Massimo Bottura' => [
        'title' => 'Italian Culinary Master',
        'expertise' => ['Modern Italian', 'Culinary Innovation', 'Fine Dining'],
        'image' => 'images/chefs/massimo-bottura.jpg'
    ],
    'Heston Blumenthal' => [
        'title' => 'British Celebrity Chef',
        'expertise' => ['Molecular Gastronomy', 'Food Science', 'Experimental Cuisine'],
        'image' => 'images/chefs/heston-blumenthal.jpg'
    ],
    'René Redzepi' => [
        'title' => 'Nordic Cuisine Pioneer',
        'expertise' => ['Foraging', 'Nordic Cuisine', 'Sustainable Cooking'],
        'image' => 'images/chefs/rene-redzepi.jpg'
    ],
    'Dominique Crenn' => [
        'title' => 'French-American Chef',
        'expertise' => ['Artistic Cuisine', 'French Technique', 'Sustainable Practices'],
        'image' => 'images/chefs/dominique-crenn.jpg'
    ]
];

// Enhance chef data with expertise info and remove any remaining duplicates
$uniqueChefs = [];
$seenUserIds = [];

foreach ($chefs as &$chef) {
    // Skip if we've already processed this user ID
    if (in_array($chef['USER_ID'], $seenUserIds)) {
        continue;
    }
    $seenUserIds[] = $chef['USER_ID'];
    
    $name = $chef['NAME'];
    if (array_key_exists($name, $expertiseData)) {
        $chef['title'] = $expertiseData[$name]['title'];
        $chef['expertise'] = $expertiseData[$name]['expertise'];
        $chef['profile_image'] = $expertiseData[$name]['image'];
    } else {
        $chef['title'] = 'Culinary Enthusiast';
        $specialties = !empty($chef['specialties']) ? explode(',', $chef['specialties']) : ['Home Cooking'];
        $chef['expertise'] = array_filter(array_map('trim', $specialties), function($item) {
            return !empty($item);
        });
        if (empty($chef['expertise'])) {
            $chef['expertise'] = ['Home Cooking'];
        }
        $chef['profile_image'] = $chef['PROFILE_IMAGE'] ?? 'images/default-avatar.png';
    }
    
    // Check if current user is following this chef
    $chef['is_following'] = in_array($chef['USER_ID'], $following);
    
    $uniqueChefs[] = $chef;
}

$chefs = $uniqueChefs;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Discover and follow amazing chefs on Feedora">
    <meta name="theme-color" content="#ED5A2C">
    <title>Discover Chefs - Feedora</title>
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
            --primary-light: #FF6B3D;
            --primary-dark: #D4491F;
            --secondary-color: #4CAF50;
            --secondary-light: #66BB6A;
            --accent-color: #FF9800;
            --text-color: #2C3E50;
            --text-light: #7F8C8D;
            --text-muted: #BDC3C7;
            --background-color: #F8FAFC;
            --background-gradient: linear-gradient(135deg, #F8FAFC 0%, #E3F2FD 100%);
            --card-background: #FFFFFF;
            --border-radius: 16px;
            --border-radius-lg: 24px;
            --transition-speed: 0.3s;
            --transition-bounce: cubic-bezier(0.68, -0.55, 0.265, 1.55);
            --box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
            --box-shadow-hover: 0 16px 48px rgba(0, 0, 0, 0.12);
            --box-shadow-active: 0 4px 16px rgba(0, 0, 0, 0.12);
            --sidebar-width: 250px;
            --header-height: 70px;
            --gradient-primary: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            --gradient-secondary: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-light) 100%);
        }

        .header-title {
            font-family: 'Qurova', sans-serif;
            font-size: 1.25rem;
            color: var(--text-color);
            font-weight: 400;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .search-icon {
            color: var(--text-light);
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: all 0.2s ease;
        }

        .search-icon:hover {
            background-color: rgba(237, 90, 44, 0.1);
            color: var(--primary-color);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Qurova', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: var(--background-gradient);
            color: var(--text-color);
            display: flex;
            min-height: 100vh;
            line-height: 1.6;
        }

        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
            display: flex;
            flex-direction: column;
        }        .content-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 3rem 2rem;
            position: relative;
        }

        .section-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .section-title {
            font-family: 'DM Serif Display', serif;
            font-size: 2.5rem;
            color: var(--text-color);
            margin-bottom: 1rem;
            position: relative;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -0.5rem;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 4px;
            background: var(--gradient-primary);
            border-radius: 2px;
        }        .chef-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 2rem;
            padding: 1rem 0;
        }

        .chef-card {
            background: var(--card-background);
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            box-shadow: var(--box-shadow);
            transition: all var(--transition-speed) var(--transition-bounce);
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .chef-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
            transform: scaleX(0);
            transition: transform var(--transition-speed) ease;
        }

        .chef-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: var(--box-shadow-hover);
        }

        .chef-card:hover::before {
            transform: scaleX(1);
        }

        .chef-image-container {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto 1.5rem;
            position: relative;
            background: var(--gradient-primary);
            padding: 4px;
            transition: all var(--transition-speed) ease;
        }

        .chef-card:hover .chef-image-container {
            transform: scale(1.1);
            box-shadow: 0 8px 32px rgba(237, 90, 44, 0.3);
        }

        .chef-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            transition: all var(--transition-speed) ease;
            background: var(--card-background);
        }

        .chef-title {
            font-family: 'Qurova', sans-serif;
            font-size: 0.875rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .chef-name {
            font-family: 'Qurova', sans-serif;
            font-size: 1.5rem;
            font-weight: 400;
            margin-bottom: 1rem;
            color: var(--text-color);
            line-height: 1.3;
            text-decoration: none;
        }

        .chef-stats {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: rgba(248, 250, 252, 0.8);
            border-radius: var(--border-radius);
            width: 100%;
        }

        .stat-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
        }

        .stat-value {
            font-family: 'Qurova', sans-serif;
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--text-color);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-family: 'Qurova', sans-serif;
            font-size: 0.875rem;
            color: var(--text-light);
            font-weight: 500;
        }

        .expertise-tags {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
            min-height: 2rem;
        }

        .expertise-tag {
            font-family: 'Qurova', sans-serif;
            background: linear-gradient(135deg, rgba(237, 90, 44, 0.1) 0%, rgba(255, 107, 61, 0.1) 100%);
            color: var(--primary-color);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
            border: 1px solid rgba(237, 90, 44, 0.2);
            transition: all var(--transition-speed) ease;
        }

        .expertise-tag:hover {
            background: var(--gradient-primary);
            color: white;
            transform: translateY(-2px);
        }

        .follow-btn {
            font-family: 'Qurova', sans-serif;
            background: var(--gradient-primary);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 50px;
            cursor: pointer;
            transition: all var(--transition-speed) var(--transition-bounce);
            width: 100%;
            font-weight: 600;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(237, 90, 44, 0.3);
        }

        .follow-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .follow-btn:hover::before {
            left: 100%;
        }

        .follow-btn svg {
            width: 18px;
            height: 18px;
            transition: transform var(--transition-speed) ease;
        }

        .follow-btn.following {
            background: var(--gradient-secondary);
            box-shadow: 0 4px 16px rgba(76, 175, 80, 0.3);
        }

        .follow-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(237, 90, 44, 0.4);
        }

        .follow-btn.following:hover {
            box-shadow: 0 8px 24px rgba(76, 175, 80, 0.4);
        }

        .follow-btn:active {
            transform: translateY(0);
            box-shadow: var(--box-shadow-active);
        }

        .load-more-container {
            text-align: center;
            margin-top: 3rem;
            margin-bottom: 2rem;
        }

        .load-more-btn {
            font-family: 'Qurova', sans-serif;
            background: var(--gradient-primary);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 50px;
            cursor: pointer;
            transition: all var(--transition-speed) ease;
            font-weight: 600;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 4px 16px rgba(237, 90, 44, 0.3);
        }

        .load-more-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(237, 90, 44, 0.4);
        }

        .load-more-btn svg {
            width: 18px;
            height: 18px;
            transition: transform var(--transition-speed) ease;
        }

        .load-more-btn:hover svg {
            transform: rotate(180deg);
        }

        /* Follower count animation */
        .stat-value.updating {
            animation: countUpdate 0.5s ease-in-out;
        }

        @keyframes countUpdate {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); color: var(--primary-color); }
            100% { transform: scale(1); }
        }

        /* Sidebar Overlay */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 95;
            display: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .sidebar-overlay.active {
            display: block;
            opacity: 1;
        }        /* Responsive styles */
        @media (max-width: 1200px) {
            .chef-grid {
                grid-template-columns: repeat(3, 1fr); /* 3 columns for tablet landscape */
                gap: 1.5rem;
            }
        }

        @media (max-width: 992px) {
            .chef-grid {
                grid-template-columns: repeat(3, 1fr); /* 3 columns for tablet portrait */
                gap: 1.25rem;
            }
            
            .content-container {
                padding: 2rem 1.5rem;
            }
        }

        @media (max-width: 768px) {
            .chef-grid {
                grid-template-columns: repeat(2, 1fr); /* 2 columns for mobile landscape */
                gap: 1rem;
            }
        }

        @media (max-width: 480px) {
            .content-container {
                padding: 1rem 0.25rem;
            }

            .chef-card {
                margin: 0 0.25rem;
                padding: 0.875rem;
            }

            .chef-image-container {
                width: 90px;
                height: 90px;
                margin-bottom: 1rem;
            }

            .chef-name {
                font-size: 1.25rem;
            }

            .chef-title {
                font-size: 0.75rem;
            }

            .page-title {
                font-size: 1.75rem;
            }

            .section-title {
                font-size: 1.5rem;
            }

            .hero-section {
                padding: 1.5rem 0.25rem 1rem;
            }
        }

        /* Loading animation */
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .loading {
            animation: pulse 1.5s ease-in-out infinite;
        }

        /* Smooth scroll */
        html {
            scroll-behavior: smooth;
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--background-color);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }
    </style>
</head>
<body>
    <?php include('sidebar.php'); ?>

        <main class="main-content">
            <?php include('header.php'); ?>
        <div class="content-container">
            <div class="section-header">
                <h2 class="section-title">Featured Chefs</h2>
            </div>
            
            <div class="chef-grid">
                <?php if (empty($chefs)): ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: var(--text-light);">
                        <h3>No chefs found</h3>
                        <p>Be the first to create some recipes and become a featured chef!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($chefs as $chef): ?>
                    <div class="chef-card" data-chef-id="<?= htmlspecialchars($chef['USER_ID']) ?>">
                        <a href="profile_details.php?id=<?= htmlspecialchars($chef['USER_ID']) ?>" class="chef-image-container">
                            <img src="<?= htmlspecialchars($chef['profile_image']) ?>" 
                                 alt="<?= htmlspecialchars($chef['NAME']) ?>" 
                                 class="chef-image"
                                 onerror="this.src='images/default-avatar.png'">
                        </a>
                        <div class="chef-details">
                            <a href="profile_details.php?id=<?= htmlspecialchars($chef['USER_ID']) ?>" class="chef-name">
                                <?= htmlspecialchars($chef['NAME']) ?>
                            </a>
                            <div class="chef-stats">
                                <div class="stat-item">
                                    <span class="stat-value follower-count" data-chef-id="<?= $chef['USER_ID'] ?>"><?= $chef['follower_count'] ?></span>
                                    <span class="stat-label">Followers</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-value"><?= $chef['recipe_count'] ?></span>
                                    <span class="stat-label">Recipes</span>
                                </div>
                            </div>
                            <button class="follow-btn <?= $chef['is_following'] ? 'following' : '' ?>" 
                                    onclick="toggleFollow(<?= $chef['USER_ID'] ?>)">
                                <?php if ($chef['is_following']): ?>
                                    Following
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M20 6L9 17l-5-5"></path>
                                    </svg>
                                <?php else: ?>
                                    Follow
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="8.5" cy="7" r="4"></circle>
                                        <line x1="20" y1="8" x2="20" y2="14"></line>
                                        <line x1="23" y1="11" x2="17" y2="11"></line>
                                    </svg>
                                <?php endif; ?>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($chefs)): ?>
            <div class="load-more-container">
                <button class="load-more-btn" id="loadMoreBtn">
                    Load More Chefs
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </button>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebar-overlay"></div>

    <script>
    // Track followed chefs
    const followedChefs = new Set(<?= json_encode($following) ?>);
    
    // Toggle follow status
    function toggleFollow(chefId) {
        const button = document.querySelector(`.chef-card[data-chef-id="${chefId}"] .follow-btn`);
        const followerCountElement = document.querySelector(`.follower-count[data-chef-id="${chefId}"]`);
        const isFollowing = button.classList.contains('following');
        
        // Show loading state
        const originalContent = button.innerHTML;
        button.innerHTML = '<span class="loading">Processing...</span>';
        button.disabled = true;

        // Create form data
        const formData = new FormData();
        formData.append('action', 'toggle_follow');
        formData.append('chef_id', chefId);

        // Send request to the same page
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update follower count with animation
                if (followerCountElement && data.new_follower_count !== undefined) {
                    followerCountElement.classList.add('updating');
                    setTimeout(() => {
                        followerCountElement.textContent = data.new_follower_count;
                        followerCountElement.classList.remove('updating');
                    }, 250);
                }
                
                if (data.action === 'followed') {
                    button.classList.add('following');
                    button.innerHTML = `Following <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"></path></svg>`;
                    followedChefs.add(chefId);
                } else {
                    button.classList.remove('following');
                    button.innerHTML = `Follow <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>`;
                    followedChefs.delete(chefId);
                }
                
                // Add success animation
                button.style.transform = 'scale(1.05)';
                setTimeout(() => {
                    button.style.transform = '';
                }, 200);
                
                showToast(data.message, 'success');
            } else {
                // Restore original content on error
                button.innerHTML = originalContent;
                showToast(data.message || 'An error occurred. Please try again.', 'error');
            }
            
            button.disabled = false;
        })
        .catch(error => {
            console.error('Error:', error);
            button.innerHTML = originalContent;
            button.disabled = false;
            showToast('Network error. Please check your connection.', 'error');
        });
    }

    // Load more functionality
    document.getElementById('loadMoreBtn')?.addEventListener('click', function() {
        // Show loading state
        this.innerHTML = `<span class="loading">Loading...</span>`;
        this.disabled = true;
        
        // Simulate loading more chefs
        setTimeout(() => {
            this.innerHTML = `Load More Chefs <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>`;
            this.disabled = false;
        
            showToast('All available chefs have been loaded!', 'info');
        }, 1500);
    });

    // Enhanced toast notification system
    function showToast(message, type = 'success') {
        // Create toast container if it doesn't exist
        let toastContainer = document.getElementById('toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toast-container';
            toastContainer.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                z-index: 10000;
                display: flex;
                flex-direction: column;
                gap: 10px;
            `;
            document.body.appendChild(toastContainer);
        }

        // Create toast element
        const toast = document.createElement('div');
        toast.style.cssText = `
            min-width: 300px;
            padding: 16px 20px;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-weight: 500;
            font-family: 'Qurova', sans-serif;
            transform: translateX(100%);
            transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            backdrop-filter: blur(10px);
        `;

        // Set color based on type
        const colors = {
            success: { bg: '#4CAF50', color: 'white' },
            error: { bg: '#F44336', color: 'white' },
            info: { bg: '#2196F3', color: 'white' },
            warning: { bg: '#FF9800', color: 'white' }
        };
        
        const colorScheme = colors[type] || colors.success;
        toast.style.backgroundColor = colorScheme.bg;
        toast.style.color = colorScheme.color;

        // Add content
        const messageSpan = document.createElement('span');
        messageSpan.textContent = message;
        
        const closeBtn = document.createElement('button');
        closeBtn.innerHTML = '&times;';
        closeBtn.style.cssText = `
            background: none;
            border: none;
            color: inherit;
            font-size: 20px;
            cursor: pointer;
            margin-left: 10px;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background-color 0.2s ease;
        `;
        
        closeBtn.onmouseover = () => closeBtn.style.backgroundColor = 'rgba(255, 255, 255, 0.2)';
        closeBtn.onmouseout = () => closeBtn.style.backgroundColor = 'transparent';
        closeBtn.onclick = () => removeToast(toast);

        toast.appendChild(messageSpan);
        toast.appendChild(closeBtn);

        // Add to container
        toastContainer.appendChild(toast);

        // Animate in
        setTimeout(() => {
            toast.style.transform = 'translateX(0)';
        }, 10);

        // Auto remove after 4 seconds
        setTimeout(() => {
            removeToast(toast);
        }, 4000);
    }

    function removeToast(toast) {
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }

    // Add smooth scroll behavior for better UX
    document.addEventListener('DOMContentLoaded', function() {
        // Add intersection observer for animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe chef cards for staggered animation
        document.querySelectorAll('.chef-card').forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
            observer.observe(card);
        });
    });

  
    </script>
</body>
</html>
