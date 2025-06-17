<?php
// Include the shared notification helpers FIRST
require_once 'notification_helpers.php';

// Include the database connection script if not already included
if (!isset($pdo)) {
    require_once 'config/config.php';
}

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to the login page if not logged in
    header("Location: sign-in.php");
    exit();
}

// ENHANCED: Current user information with validation
$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User';
$profileImage = isset($_SESSION['profile_image']) ? $_SESSION['profile_image'] : 'images/default-profile.png';
$notificationCount = 0;
$notifications = [];

try {
    if (!$userId) {
        throw new Exception("User ID not found in session");
    }

    // ENHANCED: Get current user data from database (always fresh data)
    $userStmt = $pdo->prepare("SELECT USER_ID, NAME, EMAIL, PROFILE_IMAGE FROM USERS WHERE USER_ID = ?");
    $userStmt->execute([$userId]);
    $currentUser = $userStmt->fetch(PDO::FETCH_ASSOC);

    if ($currentUser && $currentUser['NAME']) {
        $userName = $currentUser['NAME'];
        $_SESSION['user_name'] = $userName;
    }

    if (!$currentUser) {
        // User doesn't exist anymore, logout
        error_log("❌ User ID {$userId} not found in database, logging out");
        session_destroy();
        header("Location: sign-in.php");
        exit();
    }

    // Update session data with current database values
    $_SESSION['user_id'] = $currentUser['USER_ID'];
    $_SESSION['user_name'] = $currentUser['NAME'];
    $_SESSION['user_email'] = $currentUser['EMAIL'];

    $userName = $currentUser['NAME'];

    if ($currentUser['PROFILE_IMAGE']) {
        $_SESSION['profile_image'] = $currentUser['PROFILE_IMAGE'];
        $profileImage = $currentUser['PROFILE_IMAGE'];
    }

    error_log("✅ Header: Current user is " . $userName . " (ID: " . $userId . ")");

    // ENHANCED: Get unread notification count with professional formatting
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM NOTIFICATIONS n 
        INNER JOIN USERS u ON n.USER_ID = u.USER_ID 
        WHERE n.TARGET_USER_ID = ? 
        AND n.IS_READ = 0 
        AND u.USER_ID IS NOT NULL
        AND n.CREATED_AT >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $countStmt->execute([$userId]);
    $result = $countStmt->fetch(PDO::FETCH_ASSOC);
    $notificationCount = intval($result['count'] ?? 0);

    error_log("📱 User {$userName} has {$notificationCount} unread notifications");

    // ENHANCED: Get recent notifications with professional content and validation
    if ($notificationCount > 0) {
        $notifyStmt = $pdo->prepare("
            SELECT 
                n.NOTIFICATION_ID,
                n.USER_ID,
                n.TARGET_USER_ID,
                n.NOTIFICATION_TYPE,
                n.CONTENT,
                n.RELATED_ID,
                n.IS_READ,
                n.CREATED_AT,
                u.NAME as SENDER_NAME, 
                u.PROFILE_IMAGE as SENDER_IMAGE,
                u.USER_ID as SENDER_USER_ID
            FROM NOTIFICATIONS n 
            INNER JOIN USERS u ON n.USER_ID = u.USER_ID 
            WHERE n.TARGET_USER_ID = ? 
            AND u.USER_ID IS NOT NULL
            AND n.USER_ID != n.TARGET_USER_ID
            AND n.CREATED_AT >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY n.CREATED_AT DESC 
            LIMIT 15
        ");
        $notifyStmt->execute([$userId]);
        $notifications = $notifyStmt->fetchAll(PDO::FETCH_ASSOC);

        error_log("📋 Retrieved " . count($notifications) . " notifications for display");

        // ENHANCED: Clean up orphaned notifications and very old ones
        $cleanupStmt = $pdo->prepare("
            DELETE n FROM NOTIFICATIONS n 
            LEFT JOIN USERS u ON n.USER_ID = u.USER_ID 
            WHERE (n.TARGET_USER_ID = ? AND u.USER_ID IS NULL) 
            OR n.CREATED_AT < DATE_SUB(NOW(), INTERVAL 60 DAY)
        ");
        $cleanupStmt->execute([$userId]);
        $cleanedUp = $cleanupStmt->rowCount();

        if ($cleanedUp > 0) {
            error_log("🧹 Cleaned up {$cleanedUp} orphaned/old notifications");
        }
    }
} catch (PDOException $e) {
    // Log error but don't break the page
    error_log("❌ Header database error: " . $e->getMessage());
    $notificationCount = 0;
    $notifications = [];
    // Try to get user name from session as fallback
    $userName = $_SESSION['user_name'] ?? 'User';
    $profileImage = $_SESSION['profile_image'] ?? 'images/default-profile.png';
}

// ENHANCED: Professional notification link generation with validation
function getNotificationLink($notification)
{
    if (!$notification || !isset($notification['NOTIFICATION_TYPE'])) {
        return 'dashboard.php';
    }

    $link = 'dashboard.php';
    $type = $notification['NOTIFICATION_TYPE'];
    $relatedId = $notification['RELATED_ID'] ?? null;
    $senderId = $notification['USER_ID'] ?? null;

    try {
        switch ($type) {
            case 'new_post':
                if ($relatedId) {
                    $link = 'dashboard.php?highlight=' . intval($relatedId) . '#post-' . intval($relatedId);
                }
                break;

            case 'new_reaction':
            case 'new_comment':
                if ($relatedId) {
                    $link = 'dashboard.php?highlight=' . intval($relatedId) . '#post-' . intval($relatedId);
                }
                break;

            case 'new_story':
                if ($senderId) {
                    $link = 'story.php?user_id=' . intval($senderId);
                }
                break;

            case 'new_recipe':
                if ($relatedId) {
                    $link = 'recipes.php?recipe_id=' . intval($relatedId);
                }
                break;

            case 'new_follower':
                if ($senderId) {
                    $link = 'profile.php?user_id=' . intval($senderId);
                }
                break;

            case 'new_message':
                if ($senderId) {
                    $link = 'messages.php?user_id=' . intval($senderId);
                }
                break;

            default:
                $link = 'dashboard.php';
                break;
        }
    } catch (Exception $e) {
        error_log("Error generating notification link: " . $e->getMessage());
        $link = 'dashboard.php';
    }

    return $link;
}

// ENHANCED: Add debugging information to logs
error_log("🔍 Header loaded for user: {$userName} (ID: {$userId}) with {$notificationCount} notifications");
?>

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
    }

    /* Header Styles */
    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 20px;
        background-color: white;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        position: relative;
        z-index: 90;
        height: 70px;
        width: 100%;
        box-sizing: border-box;
        overflow: visible;
        /* Changed from hidden to prevent clipping */
    }

    .header-logo img {
        height: 36px;
        max-width: 140px;
        object-fit: contain;
    }


    .header-actions {
        display: flex;
        align-items: center;
        gap: 18px;
        flex-wrap: nowrap;
        height: 100%;
        padding: 0 5px;
        min-width: 0;
        /* Allow shrinking */
        flex-shrink: 1;
        /* Allow the container to shrink */
    }

    .header-action-item {
        position: relative;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        height: 40px;
        transition: transform var(--transition-speed);
        flex-shrink: 0;
        /* Prevent individual items from shrinking */
    }

    .header-action-item:hover {
        transform: translateY(-2px);
    }

    /* Search Container */
    .search-container {
        display: flex;
        align-items: center;
        background-color: var(--light-background);
        border: 1px solid var(--border-color);
        border-radius: 24px;
        height: 40px;
        width: 240px;
        transition: all var(--transition-speed) ease;
        position: relative;
        overflow: hidden;
    }

    .search-container:focus-within {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(237, 90, 44, 0.1);
        background-color: white;
    }

    .search-input {
        border: none;
        background-color: transparent;
        padding: 8px 12px;
        width: 100%;
        outline: none;
        font-size: 14px;
        color: var(--text-color);
        min-width: 0;
        /* Allow input to shrink */
    }

    .search-input::placeholder {
        color: var(--light-text);
        opacity: 0.8;
    }

    .search-button {
        background: none;
        border: none;
        cursor: pointer;
        color: var(--light-text);
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: color 0.2s ease;
        flex-shrink: 0;
    }


    .search-button:hover {
        color: var(--primary-color);
    }

    /* FIXED: Enhanced Notification Styles with Error Handling */
    .notification-icon,
    .settings-icon {
        color: var(--light-text);
        padding: 8px;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        cursor: pointer;
    }

    .notification-icon:hover,
    .settings-icon:hover {
        background-color: rgba(237, 90, 44, 0.1);
        color: var(--primary-color);
        box-shadow: 0 3px 6px rgba(0, 0, 0, 0.08);
    }

    .notification-badge {
        position: absolute;
        top: 0;
        right: 0;
        background-color: var(--primary-color);
        color: white;
        font-size: 10px;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 4px rgba(237, 90, 44, 0.3);
        border: 2px solid white;
        font-weight: 600;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.1);
        }

        100% {
            transform: scale(1);
        }
    }

    /* FIXED: Notification Dropdown with Better Error Handling */
    .notification-dropdown {
        position: absolute;
        top: calc(100% + 10px);
        right: -10px;
        width: 320px;
        max-height: 450px;
        background-color: white;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        border-radius: 12px;
        z-index: 1000;
        display: none;
        border: 1px solid rgba(0, 0, 0, 0.08);
        overflow: hidden;
    }

    .notification-dropdown.show {
        display: block;
        animation: slideDown 0.3s ease;
    }

    .notification-dropdown.error {
        background-color: #fff5f5;
        border-color: #fed7d7;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .notification-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 20px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.08);
        background: linear-gradient(135deg, var(--primary-color), #ff6b3d);
        color: white;
    }

    .notification-header h3 {
        margin: 0;
        font-size: 16px;
        font-weight: 600;
    }

    .mark-all-read {
        background: rgba(255, 255, 255, 0.2);
        border: none;
        color: white;
        font-size: 12px;
        cursor: pointer;
        padding: 6px 12px;
        border-radius: 15px;
        transition: all 0.2s ease;
        font-weight: 500;
    }

    .mark-all-read:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: translateY(-1px);
    }

    .notification-list {
        max-height: 320px;
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: var(--primary-color) transparent;
    }

    .notification-list::-webkit-scrollbar {
        width: 6px;
    }

    .notification-list::-webkit-scrollbar-thumb {
        background: var(--primary-color);
        border-radius: 3px;
    }

    .notification-list::-webkit-scrollbar-track {
        background: transparent;
    }

    .notification-link {
        text-decoration: none;
        color: inherit;
        display: block;
    }

    .notification-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 14px 20px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        transition: all 0.2s ease;
        cursor: pointer;
        position: relative;
    }

    .notification-item:hover {
        background-color: rgba(237, 90, 44, 0.05);
        transform: translateX(2px);
    }

    .notification-item.unread {
        background-color: rgba(237, 90, 44, 0.08);
        border-left: 3px solid var(--primary-color);
    }

    .notification-item.unread::before {
        content: '';
        position: absolute;
        left: 8px;
        top: 50%;
        transform: translateY(-50%);
        width: 8px;
        height: 8px;
        background: var(--primary-color);
        border-radius: 50%;
        box-shadow: 0 0 0 2px rgba(237, 90, 44, 0.3);
    }

    .notification-user-image img {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid rgba(237, 90, 44, 0.1);
    }

    .notification-content {
        flex: 1;
        min-width: 0;
    }

    .notification-content p {
        margin: 0 0 6px 0;
        font-size: 14px;
        line-height: 1.4;
        color: var(--text-color);
        font-weight: 500;
    }

    .notification-time {
        font-size: 12px;
        color: var(--light-text);
        font-weight: 400;
    }

    /* FIXED: Error States */
    .notification-error {
        padding: 20px;
        text-align: center;
        color: #e53e3e;
        background-color: #fff5f5;
        border-radius: 8px;
        margin: 10px;
    }

    .notification-loading {
        padding: 20px;
        text-align: center;
        color: var(--light-text);
    }

    .notification-footer {
        text-align: center;
        border-top: 1px solid rgba(0, 0, 0, 0.08);
        background-color: rgba(0, 0, 0, 0.02);
    }

    .view-all-notifications {
        color: var(--primary-color);
        font-size: 14px;
        text-decoration: none;
        font-weight: 500;
        transition: color 0.2s ease;
    }

    .view-all-notifications:hover {
        color: #d94e22;
    }

    .no-notifications {
        padding: 40px 20px;
        text-align: center;
        color: var(--light-text);
    }

    .no-notifications-icon {
        font-size: 48px;
        margin-bottom: 12px;
        opacity: 0.5;
    }

    /* User Profile */
    .user-profile {
        display: flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
        padding: 5px;
        border-radius: 25px;
        transition: all 0.2s ease;
        position: relative;
    }

    .user-profile:hover {
        background-color: rgba(237, 90, 44, 0.05);
    }

    .user-info {
        text-align: right;
    }

    .user-name {
        display: block;
        font-weight: 600;
        font-size: 14px;
    }

    .user-role {
        display: block;
        font-size: 12px;
        color: var(--light-text);
    }

    .user-profile-image img {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid white;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    /* Profile dropdown */
    .profile-dropdown {
        position: absolute;
        top: 100%;
        right: 0;
        background-color: white;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        width: 200px;
        z-index: 1000;
        overflow: hidden;
        display: none;
        flex-direction: column;
        margin-top: 8px;
    }

    .profile-dropdown.active {
        display: flex;
        animation: slideDown 0.3s ease;
    }

    .dropdown-item {
        display: flex;
        align-items: center;
        padding: 12px 16px;
        text-decoration: none;
        color: var(--text-color);
        transition: background-color 0.2s ease;
        gap: 10px;
    }

    .dropdown-item:hover {
        background-color: rgba(237, 90, 44, 0.05);
    }

    .dropdown-item svg {
        color: var(--primary-color);
    }

    /* Hamburger Menu */
    .hamburger-menu {
        display: none;
        flex-direction: column;
        justify-content: space-between;
        width: 32px;
        height: 32px;
        cursor: pointer;
        z-index: 101;
        margin-right: 15px;
        padding: 8px;
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .hamburger-menu span {
        display: block;
        height: 2px;
        width: 100%;
        background-color: black;
        border-radius: 2px;
        transition: all 0.3s ease;
    }

    .hamburger-menu.active span:nth-child(1) {
        transform: translateY(8px) rotate(45deg);
    }

    .hamburger-menu.active span:nth-child(2) {
        opacity: 0;
    }

    .hamburger-menu.active span:nth-child(3) {
        transform: translateY(-8px) rotate(-45deg);
    }

    /* Mobile Responsive */
    @media (max-width: 768px) {
        .dashboard-header {
            padding: 10px 15px;
            height: 60px;
            background-color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: visible;
        }

        .hamburger-menu {
            display: flex;
            margin-right: 10px;
            order: -1;
            /* Ensure hamburger appears first */
        }

        .notification-dropdown {
            width: calc(100vw - 30px) !important;
            right: -120px !important;
            /* max-height: 400px; */
        }

        .header-logo {
            display: none;
        }

        /* FIXED: Better responsive header actions */
        .header-actions {
            gap: 8px;
            /* Reduced gap on mobile */
            flex: 1;
            justify-content: flex-end;
            min-width: 0;
            position: relative;
        }

        .search-container {
            width: 40px;
            overflow: hidden;
            transition: width 0.3s ease;
            position: absolute;
            right: 180px;
            /* Position it to not overlap other items */
            z-index: 10;
        }

        .search-container.expanded {
            width: 200px;
            /* Increased width when expanded */
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            background-color: white;
            border-color: var(--primary-color);
        }

        .search-container .search-input {
            opacity: 0;
            width: 0;
            padding: 8px 0;
            transition: all 0.3s ease;
        }

        .search-container.expanded .search-input {
            opacity: 1;
            width: 100%;
            padding: 8px 12px;
        }

        /* FIXED: Ensure other header items maintain their position */
        .notification-icon,
        .settings-icon,
        .user-profile {
            position: relative;
            z-index: 5;
        }

        .main-content {
            margin-left: 0 !important;
            width: 100% !important;
            transition: all 0.3s ease;
        }

        body.sidebar-active .main-content {
            opacity: 0.8;
            pointer-events: none;
        }

        .user-info {
            display: none;
        }

        .settings-icon {
            display: none;
        }

        .notification-dropdown {
            width: calc(100vw - 30px);
            right: -120px;
            max-height: 400px;
        }

        .notification-item {
            padding: 12px 16px;
        }

        .notification-user-image img {
            width: 40px;
            height: 40px;
        }
    }

    @media (max-width: 576px) {
        .dashboard-header {
            padding: 8px 12px;
            height: 55px;
        }

        /* FIXED: Even better mobile layout for small screens */
        .header-actions {
            gap: 6px;
        }

        .search-container {
            right: 120px;
            /* Adjust position for smaller screens */
        }

        .search-container.expanded {
            width: 180px;
            /* Slightly smaller on very small screens */
        }

        .notification-dropdown {
            width: calc(100vw - 20px);
            right: -150px;
        }

        .profile-dropdown {
            width: 180px;
            right: 0;
        }

        .user-profile-image img {
            width: 32px;
            height: 32px;
        }
    }

    /* FIXED: Additional mobile search improvements */
    @media (max-width: 480px) {
        .search-container {
            right: 100px;
        }

        .search-container.expanded {
            width: 160px;
            right: 80px;
        }
    }

    /* Loading Animation */
    .loading-notification {
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 20px;
        color: var(--light-text);
    }

    .loading-spinner {
        width: 20px;
        height: 20px;
        border: 2px solid #f3f3f3;
        border-top: 2px solid var(--primary-color);
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-right: 10px;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }

    /* FIXED: Ensure search doesn't interfere with other elements */
    .search-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.1);
        z-index: 5;
        display: none;
    }

    .search-overlay.active {
        display: block;
    }

    /* FIXED: Better header overflow handling */
    .dashboard-header::after {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 20px;
        height: 100%;
        background: linear-gradient(to right, transparent, white);
        pointer-events: none;
        z-index: 1;
        display: none;
    }

    @media (max-width: 768px) {
        .dashboard-header::after {
            display: block;
        }
    }

    .search-button svg {
        position: absolute;
        left: 20px;
    }
</style>

<!-- Header -->
<header class="dashboard-header">
    <!-- Hamburger Menu for Mobile -->
    <div class="hamburger-menu" id="hamburger-menu">
        <span></span>
        <span></span>
        <span></span>
    </div>

    <div class="header-logo">
        <img src="images/Muslim Woman Cooking Illustration_Muslim Woman Cooking Illustration-01.svg" alt="Feedora Icon">
    </div>


    <!-- Header Actions -->
    <div class="header-actions">
        <!-- Search -->
        <div class="header-action-item search-container" id="search-container">
            <input type="text" placeholder="Search..." class="search-input">
            <button class="search-button" id="search-icon">
                <svg class=".search-button svg" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
            </button>
        </div>

        <!-- FIXED: Enhanced Notification with Error Handling -->
        <div class="header-action-item notification-icon" id="notification-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
            </svg>
            <?php if ($notificationCount > 0): ?>
                <span class="notification-badge" id="notification-badge"><?php echo $notificationCount; ?></span>
            <?php endif; ?>

            <!-- FIXED: Enhanced Notification Dropdown with Error Handling -->
            <div class="notification-dropdown" id="notification-dropdown">
                <div class="notification-header">
                    <h3>Notifications</h3>
                    <?php if (count($notifications) > 0): ?>
                        <button class="mark-all-read" id="mark-all-read">Mark all read</button>
                    <?php endif; ?>
                </div>

                <div class="notification-list" id="notification-list">
                    <?php if (count($notifications) > 0): ?>
                        <?php foreach ($notifications as $notification): ?>
                            <?php
                            // Extra validation for each notification
                            if (!$notification || !isset($notification['NOTIFICATION_ID'])) {
                                continue;
                            }

                           $userImage = !empty($notification['SENDER_IMAGE']) ? $notification['SENDER_IMAGE'] : 'images/default-profile.png';
$userName = $notification['SENDER_NAME'] ?? 'Unknown User';

                            $isRead = ($notification['IS_READ'] ?? 0) ? 'read' : 'unread';
                            $linkUrl = getNotificationLink($notification);
                            $content = !empty($notification['CONTENT']) ? $notification['CONTENT'] : 'New notification';
                            $createdAt = !empty($notification['CREATED_AT']) ? $notification['CREATED_AT'] : date('Y-m-d H:i:s');
                            $notificationType = !empty($notification['NOTIFICATION_TYPE']) ? $notification['NOTIFICATION_TYPE'] : 'general';
                            ?>
                            <a href="<?php echo htmlspecialchars($linkUrl); ?>" class="notification-link" onclick="markNotificationAsRead(<?php echo intval($notification['NOTIFICATION_ID']); ?>)">
                                <div class="notification-item <?php echo $isRead; ?>" data-id="<?php echo intval($notification['NOTIFICATION_ID']); ?>">
                                    <div class="notification-user-image">
                                        <img src="<?php echo htmlspecialchars($userImage); ?>"
                                            alt="<?php echo htmlspecialchars($userName); ?>"
                                            onerror="this.src='images/default-profile.png'">
                                    </div>
                                    <div class="notification-content">
                                        <p><?php echo formatNotificationContent($content); ?></p>
                                        <span class="notification-time">
                                            <?php echo formatNotificationTime($createdAt, $notificationType); ?>
                                        </span>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-notifications">
                            <div class="no-notifications-icon">🔔</div>
                            <p>No notifications yet</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="notification-footer">
                    <a href="notifications.php" class="view-all-notifications">View all notifications</a>
                </div>
            </div>
        </div>

        <!-- Settings -->
        <a href="settings.php">
            <div class="header-action-item settings-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                </svg>
            </div>
        </a>

        <!-- User Profile -->
        <div class="user-profile" id="user-profile">
            <div class="user-info">
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></span>
                <span class="user-role">Member</span>
            </div>
            <div class="user-profile-image">
                <img src="<?php echo htmlspecialchars($_SESSION['profile_image'] ?? 'images/default-profile.png'); ?>"
                    alt="User Profile"
                    onerror="this.src='images/default-profile.png'">
            </div>

            <!-- Profile Dropdown -->
            <div class="profile-dropdown" id="profile-dropdown">
                <a href="profile_Settings.php" class="dropdown-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <span>Profile</span>
                </a>
                <a href="settings.php" class="dropdown-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                    </svg>
                    <span>Settings</span>
                </a>
                <a href="log-out.php" class="dropdown-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </div>
</header>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<script>
    // FIXED: Enhanced notification functions with proper error handling
    let notificationDropdownOpen = false;
    let isProcessingNotification = false;

    // Header and sidebar functionality
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🚀 Initializing enhanced header with robust notification system...');

        // Initialize all components with error handling
        try {
            initializeHeaderComponents();
            initializeNotificationSystem();
            initializeMobileFeatures();
            console.log('✅ Header initialized successfully');
        } catch (error) {
            console.error('❌ Header initialization error:', error);
            // Graceful fallback - at least basic functionality should work
            initializeBasicHeader();
        }
    });

    function initializeHeaderComponents() {
        const hamburgerMenu = document.getElementById('hamburger-menu');
        const sidebar = document.querySelector('.sidebar');
        const sidebarOverlay = document.getElementById('sidebar-overlay');
        const sidebarCloseBtn = document.getElementById('sidebar-close-btn');
        const menuItems = document.querySelectorAll('.menu-item');
        const menuTexts = document.querySelectorAll('.menu-item-text');
        const currentCategory = document.getElementById('current-category');
        const searchContainer = document.getElementById('search-container');
        const searchIcon = document.getElementById('search-icon');
        const searchInput = document.querySelector('.search-input');
        const userProfile = document.getElementById('user-profile');
        const profileDropdown = document.getElementById('profile-dropdown');

        // Get current page name from URL
        const currentPage = window.location.pathname.split('/').pop().split('.')[0];

        // Set current category text based on current page
        if (currentCategory) {
            let categoryText = 'Dashboard';
            const pageMap = {
                'dashboard': 'Dashboard',
                'recipes': 'Recipes',
                'messages': 'Messages',
                'chefs': 'Meet Chefs',
                'live': 'Live Sessions',
                'profile': 'Profile',
                'settings': 'Settings'
            };

            if (pageMap[currentPage]) {
                categoryText = pageMap[currentPage];
            }
            currentCategory.textContent = categoryText;
        }

        // Toggle sidebar function with error handling
        function toggleSidebar(show) {
            try {
                if (show) {
                    hamburgerMenu?.classList.add('active');
                    sidebar?.classList.add('active');
                    sidebarOverlay?.classList.add('active');
                    document.body.classList.add('sidebar-active');
                    document.body.style.overflow = 'hidden';

                    menuTexts.forEach(text => {
                        if (text) {
                            text.style.display = 'block';
                            text.style.opacity = '1';
                            text.style.visibility = 'visible';
                        }
                    });
                } else {
                    hamburgerMenu?.classList.remove('active');
                    sidebar?.classList.remove('active');
                    sidebarOverlay?.classList.remove('active');
                    document.body.classList.remove('sidebar-active');
                    document.body.style.overflow = '';
                }
            } catch (error) {
                console.error('Sidebar toggle error:', error);
            }
        }

        // Search functionality
        if (searchIcon && searchContainer) {
            searchIcon.addEventListener('click', function() {
                try {
                    searchContainer.classList.toggle('expanded');
                    if (searchContainer.classList.contains('expanded')) {
                        searchInput?.focus();
                    }
                } catch (error) {
                    console.error('Search toggle error:', error);
                }
            });

            document.addEventListener('click', function(event) {
                try {
                    if (!searchContainer.contains(event.target) && searchContainer.classList.contains('expanded')) {
                        searchContainer.classList.remove('expanded');
                    }
                } catch (error) {
                    console.error('Search close error:', error);
                }
            });
        }

        // Profile dropdown functionality
        if (userProfile && profileDropdown) {
            userProfile.addEventListener('click', function(e) {
                try {
                    e.stopPropagation();
                    profileDropdown.classList.toggle('active');

                    // Close notification dropdown if open
                    const notificationDropdown = document.getElementById('notification-dropdown');
                    if (notificationDropdown) {
                        notificationDropdown.classList.remove('show');
                        notificationDropdownOpen = false;
                    }
                } catch (error) {
                    console.error('Profile dropdown error:', error);
                }
            });

            document.addEventListener('click', function(event) {
                try {
                    if (!userProfile.contains(event.target)) {
                        profileDropdown.classList.remove('active');
                    }
                } catch (error) {
                    console.error('Profile dropdown close error:', error);
                }
            });
        }

        // Menu items functionality
        menuItems.forEach(item => {
            item.addEventListener('click', function() {
                try {
                    const menuText = this.querySelector('.menu-item-text');
                    if (menuText && currentCategory) {
                        currentCategory.textContent = menuText.textContent;
                    }
                    toggleSidebar(false);
                } catch (error) {
                    console.error('Menu item click error:', error);
                }
            });
        });

        // Hamburger menu functionality
        if (hamburgerMenu && sidebar && sidebarOverlay) {
            hamburgerMenu.addEventListener('click', function() {
                try {
                    const isActive = sidebar.classList.contains('active');
                    toggleSidebar(!isActive);
                } catch (error) {
                    console.error('Hamburger menu error:', error);
                }
            });

            sidebarOverlay.addEventListener('click', function() {
                try {
                    toggleSidebar(false);
                } catch (error) {
                    console.error('Sidebar overlay error:', error);
                }
            });

            if (sidebarCloseBtn) {
                sidebarCloseBtn.addEventListener('click', function() {
                    try {
                        toggleSidebar(false);
                    } catch (error) {
                        console.error('Sidebar close error:', error);
                    }
                });
            }
        }
    }

    // FIXED: Enhanced notification system with comprehensive error handling
    function initializeNotificationSystem() {
        const notificationIcon = document.getElementById('notification-icon');
        const notificationDropdown = document.getElementById('notification-dropdown');
        const markAllReadBtn = document.getElementById('mark-all-read');

        if (!notificationIcon || !notificationDropdown) {
            console.warn('Notification elements not found, skipping initialization');
            return;
        }

        // Notification dropdown functionality with error handling
        notificationIcon.addEventListener('click', function(e) {
            try {
                e.stopPropagation();
                notificationDropdownOpen = !notificationDropdownOpen;

                if (notificationDropdownOpen) {
                    notificationDropdown.classList.add('show');
                    // Close profile dropdown if open
                    const profileDropdown = document.getElementById('profile-dropdown');
                    if (profileDropdown) {
                        profileDropdown.classList.remove('active');
                    }

                    // Refresh notifications on open
                    refreshNotifications();
                } else {
                    notificationDropdown.classList.remove('show');
                }
            } catch (error) {
                console.error('Notification dropdown error:', error);
                showNotificationError('Error opening notifications');
            }
        });

        // Close notification dropdown when clicking outside
        document.addEventListener('click', function(event) {
            try {
                if (!notificationIcon.contains(event.target)) {
                    notificationDropdown.classList.remove('show');
                    notificationDropdownOpen = false;
                }
            } catch (error) {
                console.error('Notification close error:', error);
            }
        });

        // Mark all notifications as read
        if (markAllReadBtn) {
            markAllReadBtn.addEventListener('click', function(e) {
                try {
                    e.preventDefault();
                    e.stopPropagation();

                    if (!isProcessingNotification) {
                        markAllNotificationsAsRead();
                    }
                } catch (error) {
                    console.error('Mark all read error:', error);
                    showToast('Error marking notifications as read', 'error');
                }
            });
        }

        // Initialize periodic refresh (every 30 seconds when page is visible)
        initializeNotificationRefresh();
    }

    // FIXED: Enhanced function to mark a single notification as read
    function markNotificationAsRead(notificationId) {
        if (isProcessingNotification || !notificationId) {
            return;
        }

        isProcessingNotification = true;

        fetch('notification_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=mark_notifications_read&notification_ids=${JSON.stringify([parseInt(notificationId)])}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Update the UI
                    const notificationItem = document.querySelector(`[data-id="${notificationId}"]`);
                    if (notificationItem) {
                        notificationItem.classList.remove('unread');
                        notificationItem.classList.add('read');
                    }

                    // Update notification badge
                    updateNotificationBadge(data.unread_count);
                } else {
                    console.error('Failed to mark notification as read:', data.message);
                }
            })
            .catch(error => {
                console.error('Error marking notification as read:', error);
            })
            .finally(() => {
                isProcessingNotification = false;
            });
    }

    // FIXED: Enhanced function to mark all notifications as read
    function markAllNotificationsAsRead() {
        if (isProcessingNotification) {
            return;
        }

        const unreadNotifications = document.querySelectorAll('.notification-item.unread');
        const notificationIds = Array.from(unreadNotifications)
            .map(item => parseInt(item.getAttribute('data-id')))
            .filter(id => !isNaN(id));

        if (notificationIds.length === 0) {
            showToast('No unread notifications', 'info');
            return;
        }

        isProcessingNotification = true;
        const markAllBtn = document.getElementById('mark-all-read');

        if (markAllBtn) {
            markAllBtn.disabled = true;
            markAllBtn.textContent = 'Processing...';
        }

        fetch('notification_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=mark_notifications_read&notification_ids=${JSON.stringify(notificationIds)}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Update all notification items
                    unreadNotifications.forEach(item => {
                        item.classList.remove('unread');
                        item.classList.add('read');
                    });

                    // Update notification badge
                    updateNotificationBadge(0);

                    // Hide mark all read button
                    if (markAllBtn) {
                        markAllBtn.style.display = 'none';
                    }

                    showToast('All notifications marked as read', 'success');
                } else {
                    throw new Error(data.message || 'Failed to mark notifications as read');
                }
            })
            .catch(error => {
                console.error('Error marking all notifications as read:', error);
                showToast('Error marking notifications as read', 'error');
            })
            .finally(() => {
                isProcessingNotification = false;

                if (markAllBtn) {
                    markAllBtn.disabled = false;
                    markAllBtn.textContent = 'Mark all read';
                }
            });
    }

    // FIXED: Enhanced function to update notification badge
    function updateNotificationBadge(count) {
        try {
            const badge = document.getElementById('notification-badge');
            const countNum = parseInt(count) || 0;

            if (countNum > 0) {
                if (badge) {
                    badge.textContent = countNum;
                    badge.style.display = 'flex';
                } else {
                    // Create badge if it doesn't exist
                    const notificationIcon = document.getElementById('notification-icon');
                    if (notificationIcon) {
                        const newBadge = document.createElement('span');
                        newBadge.className = 'notification-badge';
                        newBadge.id = 'notification-badge';
                        newBadge.textContent = countNum;
                        notificationIcon.appendChild(newBadge);
                    }
                }
            } else {
                if (badge) {
                    badge.style.display = 'none';
                }
            }
        } catch (error) {
            console.error('Error updating notification badge:', error);
        }
    }

    // FIXED: Function to refresh notifications periodically
    function refreshNotifications() {
        if (isProcessingNotification) {
            return;
        }

        // Only refresh if dropdown is open
        if (!notificationDropdownOpen) {
            return;
        }

        fetch('notification_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_all_notifications&limit=10'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.notifications) {
                    updateNotificationList(data.notifications);
                }
            })
            .catch(error => {
                console.error('Error refreshing notifications:', error);
            });
    }

    // Function to update notification list
    function updateNotificationList(notifications) {
        try {
            const notificationList = document.getElementById('notification-list');
            if (!notificationList) return;

            if (notifications.length === 0) {
                notificationList.innerHTML = `
                    <div class="no-notifications">
                        <div class="no-notifications-icon">🔔</div>
                        <p>No notifications yet</p>
                    </div>
                `;
                return;
            }

            let html = '';
            notifications.forEach(notification => {
                if (!notification || !notification.NOTIFICATION_ID) return;

const userImage = notification.SENDER_IMAGE || 'images/default-profile.png';
const userName = notification.SENDER_NAME || 'Unknown User';


                const isRead = notification.IS_READ ? 'read' : 'unread';
                const content = notification.CONTENT || 'New notification';

                html += `
                    <a href="#" class="notification-link" onclick="markNotificationAsRead(${notification.NOTIFICATION_ID})">
                        <div class="notification-item ${isRead}" data-id="${notification.NOTIFICATION_ID}">
                            <div class="notification-user-image">
                                <img src="${userImage}" alt="${userName}" onerror="this.src='images/default-profile.png'">
                            </div>
                            <div class="notification-content">
                                <p>${content}</p>
                                <span class="notification-time">
                                    ${formatTimeAgo(notification.CREATED_AT)}
                                </span>
                            </div>
                        </div>
                    </a>
                `;
            });

            notificationList.innerHTML = html;
        } catch (error) {
            console.error('Error updating notification list:', error);
            showNotificationError('Error loading notifications');
        }
    }

    // Function to show notification error
    function showNotificationError(message) {
        const notificationList = document.getElementById('notification-list');
        if (notificationList) {
            notificationList.innerHTML = `
                <div class="notification-error">
                    <p>⚠️ ${message}</p>
                    <button onclick="location.reload()" style="margin-top: 10px; padding: 5px 10px; background: #ED5A2C; color: white; border: none; border-radius: 5px; cursor: pointer;">
                        Refresh Page
                    </button>
                </div>
            `;
        }
    }

    // Function to initialize notification refresh
    function initializeNotificationRefresh() {
        // Refresh every 30 seconds when page is visible
        setInterval(() => {
            if (!document.hidden && notificationDropdownOpen) {
                refreshNotifications();
            }
        }, 30000);

        // Refresh when page becomes visible
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && notificationDropdownOpen) {
                setTimeout(refreshNotifications, 1000);
            }
        });
    }

    // Helper function to format time
    function formatTimeAgo(dateString) {
        try {
            const now = new Date();
            const date = new Date(dateString);
            const diff = Math.floor((now - date) / 1000);

            if (diff < 60) return 'just now';
            if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
            if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
            if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';

            return date.toLocaleDateString();
        } catch (error) {
            return 'Unknown time';
        }
    }

    // Initialize mobile features
    function initializeMobileFeatures() {
        // Check for highlighted post on page load
        checkForHighlightedPost();
    }

    // Basic header initialization fallback
    function initializeBasicHeader() {
        console.log('Initializing basic header functionality...');

        // Basic hamburger menu
        const hamburger = document.getElementById('hamburger-menu');
        const sidebar = document.querySelector('.sidebar');

        if (hamburger && sidebar) {
            hamburger.addEventListener('click', () => {
                sidebar.classList.toggle('active');
                document.body.classList.toggle('sidebar-active');
            });
        }
    }

    // Function to check for highlighted post
    function checkForHighlightedPost() {
        try {
            const urlParams = new URLSearchParams(window.location.search);
            const highlightPostId = urlParams.get('highlight');

            if (highlightPostId) {
                setTimeout(() => {
                    const postElement = document.getElementById(`post-${highlightPostId}`);
                    if (postElement) {
                        postElement.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });

                        postElement.style.transition = 'all 0.3s ease';
                        postElement.style.backgroundColor = 'rgba(237, 90, 44, 0.1)';
                        postElement.style.border = '2px solid rgba(237, 90, 44, 0.3)';
                        postElement.style.borderRadius = '12px';
                        postElement.style.padding = '10px';

                        setTimeout(() => {
                            postElement.style.backgroundColor = '';
                            postElement.style.border = '';
                            postElement.style.padding = '';
                        }, 3000);
                    }
                }, 1000);
            }
        } catch (error) {
            console.error('Error highlighting post:', error);
        }
    }

    // Enhanced toast notification function
    function showToast(message, type = 'success') {
        try {
            // Remove existing toasts
            document.querySelectorAll('.toast-notification').forEach(toast => toast.remove());

            const toast = document.createElement('div');
            toast.className = 'toast-notification';
            toast.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: ${type === 'success' ? '#4CAF50' : type === 'error' ? '#f44336' : '#2196F3'};
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

            const icon = type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️';
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
        } catch (error) {
            console.error('Error showing toast:', error);
        }
    }

    // Make functions globally available
    window.markNotificationAsRead = markNotificationAsRead;
    window.markAllNotificationsAsRead = markAllNotificationsAsRead;
    window.updateNotificationBadge = updateNotificationBadge;
    window.showToast = showToast;
    window.refreshNotifications = refreshNotifications;

    console.log('✅ Enhanced Header with Robust Notification System Loaded Successfully!');
</script>