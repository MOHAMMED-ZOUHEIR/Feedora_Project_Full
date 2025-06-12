<?php
// Get the current page file name to highlight the active menu item
$current_page = basename($_SERVER['PHP_SELF']);
?>

<style>
    /* Sidebar Styles */
    :root {
        --primary-color: #ED5A2C;
        --secondary-color: #4CAF50;
        --text-color: #333;
        --light-text: #666;
        --background-color: #f5f5f5;
        --card-background: #fff;
        --border-color: #e0e0e0;
        --shadow-color: rgba(0, 0, 0, 0.1);
        --header-height: 70px;
        --sidebar-width: 240px;
    }

    /* Sidebar Styles */
    .sidebar {
        width: 250px;
        height: 100vh;
        background-color: #fff;
        box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
        padding: 20px 0;
        position: fixed;
        top: 0;
        left: 0;
        z-index: 100;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        transition: all 0.3s ease;
    }
    
    /* Sidebar close button */
    .sidebar-close-btn {
        position: absolute;
        top: 20px;
        right: 20px;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background-color: var(--primary-color);
        display: none;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        z-index: 101;
        transition: all 0.2s ease;
    }
    
    .sidebar-close-btn svg {
        color: white;
        stroke-width: 2.5;
    }
    
    .sidebar-close-btn:hover {
        transform: rotate(90deg);
        background-color: #d14a1f;
    }
    
    /* Responsive styles for sidebar */
    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
            width: 100%;
            max-width: 300px;
            padding-top: 70px; /* Make room for header */
            background-color: rgba(255, 255, 255, 0.98);
        }
        
        .sidebar-close-btn {
            display: flex;
        }
        
        .sidebar-logo {
            display: flex;
            justify-content: center;
            padding: 15px 0;
            margin-bottom: 10px;
        }
        
        .sidebar.active {
            transform: translateX(0);
        }
        
        /* Make menu items larger and more touch-friendly on mobile */
        .sidebar .menu-item {
            padding: 15px 20px;
            margin: 8px 15px;
            background-color: var(--background-color);
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            border-left: 3px solid var(--primary-color);
            display: flex;
            align-items: center;
        }
        
        .sidebar .menu-item-icon {
            width: 32px;
            height: 32px;
            min-width: 32px;
            margin-right: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .sidebar .menu-item-text {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-color);
            display: block !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
        
        .sidebar .menu-item:hover, .sidebar .menu-item.active {
            background-color: rgba(237, 90, 44, 0.1);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        /* Ensure all menu items are visible */
        .sidebar-menu {
            margin-top: 30px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
            width: 100%;
        }
        
        /* Overlay when sidebar is active */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 99;
        }
        
        .sidebar-overlay.active {
            display: block;
        }
    }

    .sidebar-logo {
        padding: 20px;
        display: flex;
        justify-content: center;
        border-bottom: 1px solid #eee;
    }

    .sidebar-logo img {
        max-width: 150px;
        height: auto;
    }

    .sidebar-menu {
        padding: 20px 0;
        flex-grow: 1;
    }

    .menu-item {
        display: flex;
        align-items: center;
        padding: 12px 20px;
        color: var(--text-color);
        text-decoration: none;
        transition: all 0.2s ease;
        margin: 6px 10px;
        border-radius: 8px;
        position: relative;
        background-color: var(--background-color);
        border-left: 3px solid transparent;
    }

    .menu-item.active {
        background-color: rgba(237, 90, 44, 0.1);
        border-left: 3px solid var(--primary-color);
        color: var(--primary-color);
    }

    .menu-item:hover {
        background-color: rgba(237, 90, 44, 0.05);
    }

    .logout-item {
        margin-top: auto;
        margin-bottom: 20px;
        border-top: 1px solid rgba(0, 0, 0, 0.1);
        padding-top: 15px;
    }

    .sidebar-footer {
        margin-top: auto;
        padding-top: 15px;
        border-top: 1px solid rgba(0, 0, 0, 0.1);
    }

    .menu-item-icon {
        width: 28px;
        height: 28px;
        min-width: 28px;
        margin-right: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }

    .menu-item-icon img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .menu-item-text {
        font-weight: 600;
        font-size: 15px;
        white-space: nowrap;
        display: inline-block;
        transition: all 0.2s ease;
        color: var(--text-color);
    }
</style>
<!-- Sidebar Navigation -->
<aside class="sidebar">
    <!-- Close button for mobile view -->
    <div class="sidebar-close-btn" id="sidebar-close-btn">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
    </div>
    
    <div class="sidebar-logo">
        <img src="images/Feedora.svg" alt="Feedora Logo">
    </div>
    <nav class="sidebar-menu">
        <!-- Main Navigation Items -->
        <a href="dashboard.php" class="menu-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <div class="menu-item-icon">
                <img src="images/Asset 51.svg" alt="Dashboard Icon">
            </div>
            <span class="menu-item-text">Dashboard</span>
        </a>
        <a href="recipes.php" class="menu-item <?php echo ($current_page == 'recipes.php') ? 'active' : ''; ?>">
            <div class="menu-item-icon">
                <img src="images/Asset 45.svg" alt="Recipes Icon">
            </div>
            <span class="menu-item-text">Recipes</span>
        </a>
        <a href="chat.php" class="menu-item <?php echo ($current_page == 'chat.php') ? 'active' : ''; ?>">
            <div class="menu-item-icon">
                <img src="images/Asset 27.svg" alt="Messages Icon">
            </div>
            <span class="menu-item-text">Messages</span>
        </a>
        <a href="chefs.php" class="menu-item <?php echo ($current_page == 'chefs.php') ? 'active' : ''; ?>">
            <div class="menu-item-icon">
                <img src="images/Asset 60.svg" alt="Meet Chefs Icon">
            </div>
            <span class="menu-item-text">Meet Chefs</span>
        </a>
        <a href="live.php" class="menu-item <?php echo ($current_page == 'live.php') ? 'active' : ''; ?>">
            <div class="menu-item-icon">
                <img src="images/Asset 29.svg" alt="Live Sessions Icon">
            </div>
            <span class="menu-item-text">Live Sessions</span>
        </a>


        <a href="profile_settings.php" class="menu-item <?php echo ($current_page == 'profile_settings.php') ? 'active' : ''; ?>">
            <div class="menu-item-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
            </div>
            <span class="menu-item-text">Profile</span>
        </a>
        <a href="settings.php" class="menu-item <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>">
            <div class="menu-item-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                </svg>
            </div>
            <span class="menu-item-text">Settings</span>
        </a>

    </nav>

    <!-- Logout Section at Bottom -->
    <div class="sidebar-footer">
        <a href="log-out.php" class="menu-item logout-item">
            <div class="menu-item-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
            </div>
            <span class="menu-item-text">Logout</span>
        </a>
    </div>
</aside>

<!-- Online Status Update Script -->
<script>
    // Function to update user online status
    function updateOnlineStatus() {
        const formData = new FormData();
        formData.append('action', 'update_status');
        
        fetch('chat.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Status updated successfully
            }
        })
        .catch(error => {
            console.error('Error updating online status:', error);
        });
    }

    // Update online status immediately when page loads
    updateOnlineStatus();

    // Update online status every 30 seconds
    setInterval(updateOnlineStatus, 30000);
</script>