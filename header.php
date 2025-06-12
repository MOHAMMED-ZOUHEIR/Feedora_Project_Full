<?php
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

// Get user information
$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';
$profileImage = $_SESSION['profile_image'] ?? 'images/default-profile.png';

// Get unread notifications count
$notificationCount = 0;
$notifications = [];
try {
    // Get unread notification count
    $countStmt = $pdo->prepare("SELECT COUNT(*) as count FROM NOTIFICATIONS WHERE TARGET_USER_ID = ? AND IS_READ = 0");
    $countStmt->execute([$userId]);
    $result = $countStmt->fetch(PDO::FETCH_ASSOC);
    $notificationCount = $result['count'];
    
    // Get recent notifications (limit to 5)
    $notifyStmt = $pdo->prepare(
        "SELECT n.*, u.NAME, u.PROFILE_IMAGE 
        FROM NOTIFICATIONS n 
        JOIN USERS u ON n.USER_ID = u.USER_ID 
        WHERE n.TARGET_USER_ID = ? 
        ORDER BY n.CREATED_AT DESC LIMIT 5"
    );
    $notifyStmt->execute([$userId]);
    $notifications = $notifyStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Silently handle error
}
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
        height: 70px; /* Explicit height instead of var */
        width: 100%;
        box-sizing: border-box;
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
    }
    
    .header-action-item {
        position: relative;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        height: 40px;
        transition: transform var(--transition-speed);
    }
    
    .header-action-item:hover {
        transform: translateY(-2px);
    }
    
    .search-container {
        display: flex;
        align-items: center;
        background-color: var(--light-background);
        border-radius: 20px;
        width: 220px;
        min-width: 160px;
        max-width: 280px;
        height: 40px;
        border: 1px solid rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
        box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
    }
    
    .search-container:focus-within {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 2px rgba(237, 90, 44, 0.1);
    }
    
    /* Hamburger Menu Styles */
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
        /* Removed background color */
        border-radius: 8px;
        transition: all 0.3s ease;
    }
    
    .hamburger-menu span {
        display: block;
        height: 2px;
        width: 100%;
        background-color: black; /* Changed from white to black */
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
    
    @media (max-width: 768px) {
        .dashboard-header {
            padding: 10px 15px;
            height: 60px;
            background-color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .hamburger-menu {
            display: flex;
            margin-right: 10px;
        }
        
        .header-logo {
            display: none;
        }
        
        .current-category {
            display: flex;
            font-weight: 600;
            font-size: 18px;
            color: var(--text-color);
        }
        
        .search-container {
            width: 40px;
            overflow: hidden;
            transition: width 0.3s ease;
        }
        
        .search-container.expanded {
            width: 160px;
        }
        
        .search-container .search-input {
            opacity: 0;
            width: 0;
            transition: all 0.3s ease;
        }
        
        .search-container.expanded .search-input {
            opacity: 1;
            width: 120px;
        }
        
        .search-icon {
            display: flex;
            cursor: pointer;
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
        
        .header-action-item {
            height: 36px;
        }
        
        /* Improve spacing between header elements */
        .header-actions {
            padding-right: 5px;
        }
    }
    
    @media (max-width: 576px) {
        .dashboard-header {
            padding: 8px 12px;
            height: 55px;
        }
        
        .search-container {
            width: 100px;
            min-width: 100px;
        }
        
        .header-actions {
            gap: 8px;
        }
        
        .header-logo img {
            height: 28px;
            max-width: 100px;
        }
        
        .hamburger-menu {
            width: 28px;
            height: 28px;
            padding: 6px;
            margin-right: 8px;
        }
        
        .header-action-item {
            height: 32px;
        }
        
        .search-input {
            padding: 6px;
            font-size: 14px;
        }
        
        /* Hide less important icons on very small screens */
        .settings-icon {
            display: none;
        }
        
        /* Notification dropdown for small screens */
        .notification-dropdown {
            width: calc(100vw - 20px);
            right: -80px;
            max-height: 350px;
        }
        
        .notification-item {
            padding: 10px;
        }
        
        .notification-user-image img {
            width: 32px;
            height: 32px;
        }
    }
    
    .search-input {
        border: none;
        background-color: transparent;
        padding: 8px;
        width: 100%;
        outline: none;
        font-size: 14px;
        color: var(--text-color);
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
    }
    
    .search-button:hover {
        color: var(--primary-color);
    }
    
    .notification-icon, .settings-icon {
        color: var(--light-text);
        padding: 8px;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }
    
    .notification-icon:hover, .settings-icon:hover {
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
        width: 16px;
        height: 16px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 4px rgba(237, 90, 44, 0.3);
        border: 1px solid white;
    }
    
    .user-profile {
        display: flex;
        align-items: center;
        gap: 10px;
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
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    /* Notification Dropdown */
    .notification-dropdown {
        position: absolute;
        top: calc(100% + 5px);
        right: -10px;
        width: 280px;
        max-height: 400px;
        background-color: white;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        border-radius: 10px;
        z-index: 100;
        display: none;
        border: 1px solid rgba(0, 0, 0, 0.05);
    }
    
    .notification-icon:hover .notification-dropdown,
    .notification-dropdown:hover {
        display: block;
    }
    
    .notification-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px;
        border-bottom: 1px solid var(--border-color);
    }
    
    .notification-header h3 {
        margin: 0;
        font-size: 16px;
    }
    
    .mark-all-read {
        background: none;
        border: none;
        color: var(--primary-color);
        font-size: 12px;
        cursor: pointer;
    }
    
    .notification-list {
        max-height: 300px;
        overflow-y: auto;
    }
    
    .notification-link {
        text-decoration: none;
        color: inherit;
    }
    
    .notification-item {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 12px 15px;
        border-bottom: 1px solid var(--border-color);
        transition: background-color 0.2s;
    }
    
    .notification-item:hover {
        background-color: rgba(0, 0, 0, 0.02);
    }
    
    .notification-item.unread {
        background-color: rgba(237, 90, 44, 0.05);
    }
    
    .notification-user-image img {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
    }
    
    .notification-content p {
        margin: 0 0 5px 0;
        font-size: 14px;
        line-height: 1.4;
    }
    
    .notification-time {
        font-size: 12px;
        color: var(--light-text);
    }
    
    .notification-footer {
        padding: 12px 15px;
        text-align: center;
        border-top: 1px solid var(--border-color);
    }
    
    .view-all-notifications {
        color: var(--primary-color);
        font-size: 14px;
    }
    
    .no-notifications {
        padding: 20px;
        text-align: center;
        color: var(--light-text);
    }
    /* Profile dropdown styles */
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
        margin-top: 5px;
    }
    
    .profile-dropdown.active {
        display: flex;
    }
    
    .dropdown-item {
        display: flex;
        align-items: center;
        padding: 12px 15px;
        text-decoration: none;
        color: var(--text-color);
        transition: background-color 0.2s ease;
        gap: 10px;
    }
    
    .dropdown-item:hover {
        background-color: rgba(0, 0, 0, 0.05);
    }
    
    .dropdown-item svg {
        color: var(--primary-color);
    }
    
    /* Current category styles */
    .current-category {
        display: none;
        font-weight: 600;
        font-size: 18px;
        color: var(--text-color);
        margin-right: auto;
    }
    
    /* Search icon styles */
    .search-icon {
        display: none;
    }
    
    @media (max-width: 576px) {
        .notification-dropdown {
            width: 300px;
            right: -100px;
        }
        
        .profile-dropdown {
            width: 180px;
            right: 0;
        }
        
        /* Hide settings icon in responsive mode */
        .settings-icon {
            display: none;
        }
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
    
    <!-- Current Category (shown on mobile) -->
    <div class="current-category" id="current-category">Dashboard</div>
    
    <!-- Header Actions -->
    <div class="header-actions">
        <!-- Search -->
        <div class="header-action-item search-container" id="search-container">
            <input type="text" placeholder="Search..." class="search-input">
            <button class="search-button" id="search-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
            </button>
        </div>
        
        <!-- Notification -->
        <div class="header-action-item notification-icon" id="notification-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
            </svg>
            <?php if ($notificationCount > 0): ?>
            <span class="notification-badge"><?php echo $notificationCount; ?></span>
            <?php endif; ?>
            
            <!-- Notification Dropdown -->
            <div class="notification-dropdown" id="notification-dropdown">
                <div class="notification-header">
                    <h3>Notifications</h3>
                    <?php if (count($notifications) > 0): ?>
                    <button class="mark-all-read" id="mark-all-read">Mark all as read</button>
                    <?php endif; ?>
                </div>
                
                <div class="notification-list">
                    <?php if (count($notifications) > 0): ?>
                        <?php foreach ($notifications as $notification): ?>
                            <?php 
                            // Format the notification timestamp to always show the exact time from database
                            $notificationTime = new DateTime($notification['CREATED_AT']);
                            $timeAgo = $notificationTime->format('M d, Y \a\t g:i A'); // e.g., May 29, 2025 at 5:30 PM
                            
                            $userImage = !empty($notification['PROFILE_IMAGE']) ? $notification['PROFILE_IMAGE'] : 'images/default-profile.png';
                            $isRead = $notification['IS_READ'] ? 'read' : 'unread';
                            ?>
                            <?php
                            // Determine the link based on notification type
                            $linkUrl = '#';
                            if ($notification['NOTIFICATION_TYPE'] === 'new_post' && !empty($notification['RELATED_ID'])) {
                                $linkUrl = 'dashboard.php?post=' . $notification['RELATED_ID'] . '#post-' . $notification['RELATED_ID'];
                            }
                            ?>
                            <a href="<?php echo $linkUrl; ?>" class="notification-link">
                                <div class="notification-item <?php echo $isRead; ?>" data-id="<?php echo $notification['NOTIFICATION_ID']; ?>">
                                    <div class="notification-user-image">
                                        <img src="<?php echo htmlspecialchars($userImage); ?>" alt="User">
                                    </div>
                                    <div class="notification-content">
                                        <p><?php echo $notification['CONTENT']; ?></p>
                                        <span class="notification-time"><?php echo $timeAgo; ?></span>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-notifications">
                            <p>No notifications yet</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="notification-footer">
                    <a href="#" class="view-all-notifications">View all notifications</a>
                </div>
            </div>
        </div>
        
        <!-- Settings -->
        <div class="header-action-item settings-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="3"></circle>
                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
            </svg>
        </div>
    </div>
    
    <div class="user-profile" id="user-profile">
        <div class="user-info">
            <span class="user-name"><?php echo htmlspecialchars($userName); ?></span>
            <span class="user-role">Member</span>
        </div>
        <div class="user-profile-image">
            <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="User Profile">
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
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
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
</header>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<script>
    // Header and sidebar functionality
    document.addEventListener('DOMContentLoaded', function() {
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
            
            // Map page names to display names
            const pageMap = {
                'dashboard': 'Dashboard',
                'recipes': 'Recipes',
                'messages': 'Messages',
                'chefs': 'Meet Chefs',
                'live': 'Live Sessions',
                'profile': 'Profile',
                'settings': 'Settings'
            };
            
            // Set category text based on current page
            if (pageMap[currentPage]) {
                categoryText = pageMap[currentPage];
            }
            
            currentCategory.textContent = categoryText;
        }
        
        // Toggle sidebar function
        function toggleSidebar(show) {
            if (show) {
                hamburgerMenu.classList.add('active');
                sidebar.classList.add('active');
                sidebarOverlay.classList.add('active');
                document.body.classList.add('sidebar-active');
                document.body.style.overflow = 'hidden';
                
                // Ensure menu texts are visible
                menuTexts.forEach(text => {
                    text.style.display = 'block';
                    text.style.opacity = '1';
                    text.style.visibility = 'visible';
                });
            } else {
                hamburgerMenu.classList.remove('active');
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                document.body.classList.remove('sidebar-active');
                document.body.style.overflow = '';
            }
        }
        
        // Toggle search container expansion
        if (searchIcon && searchContainer) {
            searchIcon.addEventListener('click', function() {
                searchContainer.classList.toggle('expanded');
                if (searchContainer.classList.contains('expanded')) {
                    searchInput.focus();
                }
            });
            
            // Close search on click outside
            document.addEventListener('click', function(event) {
                if (!searchContainer.contains(event.target) && searchContainer.classList.contains('expanded')) {
                    searchContainer.classList.remove('expanded');
                }
            });
        }
        
        // Toggle profile dropdown
        if (userProfile && profileDropdown) {
            userProfile.addEventListener('click', function(e) {
                e.stopPropagation();
                profileDropdown.classList.toggle('active');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(event) {
                if (!userProfile.contains(event.target)) {
                    profileDropdown.classList.remove('active');
                }
            });
        }
        
        // Update category text when menu item is clicked
        menuItems.forEach(item => {
            item.addEventListener('click', function() {
                const menuText = this.querySelector('.menu-item-text');
                if (menuText && currentCategory) {
                    currentCategory.textContent = menuText.textContent;
                }
                toggleSidebar(false);
            });
        });
        
        if (hamburgerMenu && sidebar && sidebarOverlay) {
            // Toggle on hamburger click
            hamburgerMenu.addEventListener('click', function() {
                const isActive = sidebar.classList.contains('active');
                toggleSidebar(!isActive);
            });
            
            // Close sidebar when clicking on overlay
            sidebarOverlay.addEventListener('click', function() {
                toggleSidebar(false);
            });
            
            // Close sidebar when clicking on close button
            if (sidebarCloseBtn) {
                sidebarCloseBtn.addEventListener('click', function() {
                    toggleSidebar(false);
                });
            }
            
            // Ensure menu texts are always visible
            menuTexts.forEach(text => {
                text.style.display = 'block';
                text.style.opacity = '1';
                text.style.visibility = 'visible';
            });
            
            // Fix any inconsistencies on window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    menuTexts.forEach(text => {
                        text.style.display = 'block';
                        text.style.opacity = '1';
                        text.style.visibility = 'visible';
                    });
                    
                    // Reset search container on desktop
                    if (searchContainer) {
                        searchContainer.classList.remove('expanded');
                    }
                }
            });
        }
    });
</script>