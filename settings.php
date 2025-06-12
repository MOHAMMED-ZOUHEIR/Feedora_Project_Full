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
$messageType = '';

// Get user information from database
try {
    $stmt = $pdo->prepare("SELECT * FROM USERS WHERE USER_ID = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        header("Location: sign-in.php");
        exit();
    }
} catch (PDOException $e) {
    $message = 'Error retrieving user data: ' . $e->getMessage();
    $messageType = 'error';
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Handle profile update
    if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        if (empty($name) || empty($email)) {
            $message = 'Name and email are required.';
            $messageType = 'error';
        } else {
            try {
                // Check if email is already taken by another user
                $checkStmt = $pdo->prepare("SELECT USER_ID FROM USERS WHERE EMAIL = ? AND USER_ID != ?");
                $checkStmt->execute([$email, $userId]);
                
                if ($checkStmt->fetch()) {
                    $message = 'Email is already taken by another user.';
                    $messageType = 'error';
                } else {
                    // Update user profile
                    $updateStmt = $pdo->prepare("UPDATE USERS SET NAME = ?, EMAIL = ? WHERE USER_ID = ?");
                    $updateStmt->execute([$name, $email, $userId]);
                    
                    // Update session data
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_email'] = $email;
                    
                    // Refresh user data
                    $user['NAME'] = $name;
                    $user['EMAIL'] = $email;
                    
                    $message = 'Profile updated successfully!';
                    $messageType = 'success';
                }
            } catch (PDOException $e) {
                $message = 'Error updating profile: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    
    // Handle password change
    if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $message = 'All password fields are required.';
            $messageType = 'error';
        } elseif ($newPassword !== $confirmPassword) {
            $message = 'New passwords do not match.';
            $messageType = 'error';
        } elseif (strlen($newPassword) < 6) {
            $message = 'New password must be at least 6 characters long.';
            $messageType = 'error';
        } else {
            // Verify current password
            if (password_verify($currentPassword, $user['PASSWORD_HASH'])) {
                try {
                    // Update password
                    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $updateStmt = $pdo->prepare("UPDATE USERS SET PASSWORD_HASH = ? WHERE USER_ID = ?");
                    $updateStmt->execute([$newPasswordHash, $userId]);
                    
                    $message = 'Password changed successfully!';
                    $messageType = 'success';
                } catch (PDOException $e) {
                    $message = 'Error changing password: ' . $e->getMessage();
                    $messageType = 'error';
                }
            } else {
                $message = 'Current password is incorrect.';
                $messageType = 'error';
            }
        }
    }
    
    // Handle profile image upload
    if (isset($_POST['action']) && $_POST['action'] === 'update_profile_image' && isset($_FILES['profile_image'])) {
        $uploadDir = 'uploads/profiles/';
        
        // Create directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $file = $_FILES['profile_image'];
        
        if ($file['error'] === UPLOAD_ERR_OK) {
            // Validate file type
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (in_array($file['type'], $allowedTypes)) {
                // Generate unique filename
                $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $newFileName = 'profile_' . $userId . '_' . time() . '.' . $fileExtension;
                $destination = $uploadDir . $newFileName;
                
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    try {
                        // Delete old profile image if exists
                        if (!empty($user['PROFILE_IMAGE']) && file_exists($user['PROFILE_IMAGE'])) {
                            unlink($user['PROFILE_IMAGE']);
                        }
                        
                        // Update profile image in database
                        $updateStmt = $pdo->prepare("UPDATE USERS SET PROFILE_IMAGE = ? WHERE USER_ID = ?");
                        $updateStmt->execute([$destination, $userId]);
                        
                        // Update session and user data
                        $_SESSION['profile_image'] = $destination;
                        $user['PROFILE_IMAGE'] = $destination;
                        
                        $message = 'Profile image updated successfully!';
                        $messageType = 'success';
                    } catch (PDOException $e) {
                        $message = 'Error updating profile image: ' . $e->getMessage();
                        $messageType = 'error';
                    }
                } else {
                    $message = 'Error uploading file.';
                    $messageType = 'error';
                }
            } else {
                $message = 'Only JPG, PNG, GIF, and WEBP files are allowed.';
                $messageType = 'error';
            }
        } else {
            $message = 'Error uploading file. Error code: ' . $file['error'];
            $messageType = 'error';
        }
    }
    
    // Handle account deletion
    if (isset($_POST['action']) && $_POST['action'] === 'delete_account') {
        $confirmPassword = $_POST['delete_password'] ?? '';
        
        if (empty($confirmPassword)) {
            $message = 'Please enter your password to confirm account deletion.';
            $messageType = 'error';
        } else {
            if (password_verify($confirmPassword, $user['PASSWORD_HASH'])) {
                try {
                    // Delete user account (CASCADE will handle related data)
                    $deleteStmt = $pdo->prepare("DELETE FROM USERS WHERE USER_ID = ?");
                    $deleteStmt->execute([$userId]);
                    
                    // Delete profile image if exists
                    if (!empty($user['PROFILE_IMAGE']) && file_exists($user['PROFILE_IMAGE'])) {
                        unlink($user['PROFILE_IMAGE']);
                    }
                    
                    // Destroy session and redirect
                    session_destroy();
                    header("Location: Home.php?message=Account deleted successfully");
                    exit();
                } catch (PDOException $e) {
                    $message = 'Error deleting account: ' . $e->getMessage();
                    $messageType = 'error';
                }
            } else {
                $message = 'Incorrect password. Account deletion cancelled.';
                $messageType = 'error';
            }
        }
    }
}

// Set default profile image if none exists
$profileImage = !empty($user['PROFILE_IMAGE']) ? $user['PROFILE_IMAGE'] : 'images/default-profile.png';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Feedora - Account Settings">
    <meta name="theme-color" content="#ED5A2C">
    <title>Settings - Feedora</title>
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
            --border-radius: 12px;
            --transition-speed: 0.3s;
            --box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            --hover-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
            --sidebar-width: 250px;
            --danger-color: #e74c3c;
            --success-color: #27ae60;
            --warning-color: #f39c12;
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

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
            display: flex;
            flex-direction: column;
        }

        /* Settings Container */
        .settings-container {
            max-width: 900px;
            margin: 0 auto;
            width: 100%;
        }

        .settings-header {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 30px;
            margin-bottom: 30px;
            text-align: center;
        }

        .settings-title {
            font-size: 2.5rem;
            margin-bottom: 10px;
            color: var(--text-color);
            font-family: 'DM Serif Display', serif;
        }

        .settings-subtitle {
            color: var(--light-text);
            font-size: 1.1rem;
        }

        /* Settings Tabs */
        .settings-tabs {
            display: flex;
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
            overflow: hidden;
        }

        .settings-tab {
            flex: 1;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all var(--transition-speed);
            font-weight: 600;
            border-bottom: 3px solid transparent;
        }

        .settings-tab.active {
            background-color: var(--light-background);
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }

        .settings-tab:hover:not(.active) {
            background-color: rgba(237, 90, 44, 0.05);
        }

        /* Settings Content */
        .settings-content {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 40px;
            margin-bottom: 30px;
        }

        .settings-section {
            display: none;
            animation: fadeIn 0.5s ease-in-out;
        }

        .settings-section.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .section-title {
            font-size: 1.8rem;
            margin-bottom: 20px;
            color: var(--text-color);
            font-family: 'DM Serif Display', serif;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .section-icon {
            width: 40px;
            height: 40px;
            background-color: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-color);
        }

        .form-input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: all var(--transition-speed);
            background-color: #fafafa;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            background-color: white;
            box-shadow: 0 0 0 3px rgba(237, 90, 44, 0.1);
        }

        .form-textarea {
            min-height: 120px;
            resize: vertical;
        }

        /* Profile Image Section */
        .profile-image-section {
            display: flex;
            align-items: center;
            gap: 30px;
            margin-bottom: 30px;
            padding: 25px;
            background-color: var(--light-background);
            border-radius: var(--border-radius);
        }

        .current-profile-image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary-color);
            box-shadow: var(--box-shadow);
        }

        .profile-image-info {
            flex: 1;
        }

        .profile-image-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text-color);
        }

        .profile-image-desc {
            color: var(--light-text);
            margin-bottom: 20px;
            line-height: 1.5;
        }

        /* File Upload Button */
        .file-upload-btn {
            position: relative;
            display: inline-block;
            overflow: hidden;
            background-color: var(--primary-color);
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            transition: all var(--transition-speed);
            font-weight: 600;
            border: none;
        }

        .file-upload-btn:hover {
            background-color: #d94e20;
            transform: translateY(-2px);
            box-shadow: var(--hover-shadow);
        }

        .file-upload-input {
            position: absolute;
            left: -9999px;
        }

        /* Button Styles */
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            transition: all var(--transition-speed);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            justify-content: center;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: #d94e20;
            transform: translateY(-2px);
            box-shadow: var(--hover-shadow);
        }

        .btn-success {
            background-color: var(--success-color);
            color: white;
        }

        .btn-success:hover {
            background-color: #229954;
            transform: translateY(-2px);
            box-shadow: var(--hover-shadow);
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background-color: #c0392b;
            transform: translateY(-2px);
            box-shadow: var(--hover-shadow);
        }

        .btn-outline {
            background-color: transparent;
            color: var(--text-color);
            border: 2px solid #e0e0e0;
        }

        .btn-outline:hover {
            background-color: var(--light-background);
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        /* Message Styles */
        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }

        .message-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, var(--primary-color), #f39c12);
            color: white;
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            transition: all var(--transition-speed);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--hover-shadow);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: white;
        }

        .stat-label {
            color: white;
            font-size: 1.1rem;
            opacity: 0.9;
        }

        /* Account Activity */
        .activity-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background-color: var(--light-background);
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            background-color: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .activity-info {
            flex: 1;
        }

        .activity-title {
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 4px;
        }

        .activity-time {
            color: var(--light-text);
            font-size: 0.9rem;
        }

        /* Danger Zone */
        .danger-zone {
            background-color: #fff5f5;
            border: 2px solid #fed7d7;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-top: 30px;
        }

        .danger-zone-title {
            color: var(--danger-color);
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .danger-zone-desc {
            color: #742a2a;
            margin-bottom: 20px;
            line-height: 1.5;
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
            background-color: rgba(0, 0, 0, 0.7);
        }

        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            width: 90%;
            max-width: 500px;
            position: relative;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-color);
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--light-text);
        }

        .close-modal:hover {
            color: var(--danger-color);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }

            .settings-tabs {
                flex-direction: column;
            }

            .settings-tab {
                border-bottom: none;
                border-left: 3px solid transparent;
            }

            .settings-tab.active {
                border-left-color: var(--primary-color);
                border-bottom-color: transparent;
            }

            .profile-image-section {
                flex-direction: column;
                text-align: center;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .settings-content {
                padding: 25px;
            }

            .settings-header {
                padding: 20px;
            }

            .settings-title {
                font-size: 2rem;
            }
        }

        @media (max-width: 480px) {
            .settings-content {
                padding: 20px;
            }

            .form-input {
                padding: 12px;
            }

            .btn {
                padding: 12px 20px;
                font-size: 14px;
            }
        }
    </style>
</head>

<body>
    <?php include('sidebar.php'); ?>

    <!-- Main Content -->
    <main class="main-content">
        <?php include('header.php'); ?>

        <div class="settings-container">
            <!-- Settings Header -->
            <div class="settings-header">
                <h1 class="settings-title">Account Settings</h1>
                <p class="settings-subtitle">Manage your profile, security, and preferences</p>
            </div>

            <!-- Settings Tabs -->
            <div class="settings-tabs">
                <div class="settings-tab active" data-tab="profile">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px;">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    Profile
                </div>
<div class="settings-tab" data-tab="security">
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px;">
        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
    </svg>
    Security
</div>
                <div class="settings-tab" data-tab="account">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px;">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                    </svg>
                    Account
                </div>
            </div>

            <!-- Settings Content -->
            <div class="settings-content">
                <?php if ($message): ?>
                    <div class="message message-<?php echo $messageType; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <?php if ($messageType === 'success'): ?>
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            <?php else: ?>
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            <?php endif; ?>
                        </svg>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <!-- Profile Section -->
                <div class="settings-section active" id="profile-section">
                    <h2 class="section-title">
                        <div class="section-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </div>
                        Profile Information
                    </h2>

                    <!-- Profile Image Section -->
                    <div class="profile-image-section">
                        <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Profile Image" class="current-profile-image">
                        <div class="profile-image-info">
                            <h3 class="profile-image-title">Profile Picture</h3>
                            <p class="profile-image-desc">Upload a new profile picture. Recommended size: 400x400px. Supported formats: JPG, PNG, GIF, WEBP.</p>
                            <form action="settings.php" method="POST" enctype="multipart/form-data" style="display: inline;">
                                <input type="hidden" name="action" value="update_profile_image">
                                <label for="profile_image" class="file-upload-btn">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px;">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                        <polyline points="17 8 12 3 7 8"></polyline>
                                        <line x1="12" y1="3" x2="12" y2="15"></line>
                                    </svg>
                                    Choose New Image
                                </label>
                                <input type="file" id="profile_image" name="profile_image" class="file-upload-input" accept="image/*" onchange="this.form.submit()">
                            </form>
                        </div>
                    </div>

                    <!-- Profile Form -->
                    <form action="settings.php" method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-group">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" id="name" name="name" class="form-input" 
                                   value="<?php echo htmlspecialchars($user['NAME'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" id="email" name="email" class="form-input" 
                                   value="<?php echo htmlspecialchars($user['EMAIL'] ?? ''); ?>" required>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                <polyline points="7 3 7 8 15 8"></polyline>
                            </svg>
                            Save Changes
                        </button>
                    </form>
                </div>

                <!-- Security Section -->
                <div class="settings-section" id="security-section">
                    <h2 class="section-title">
                        <div class="section-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <circle cx="12" cy="16" r="1"></circle>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                        </div>
                        Security Settings
                    </h2>

                    <!-- Password Change Form -->
                    <form action="settings.php" method="POST">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" id="current_password" name="current_password" class="form-input" required>
                        </div>

                        <div class="form-group">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" id="new_password" name="new_password" class="form-input" 
                                   minlength="6" required>
                            <small style="color: var(--light-text); margin-top: 5px; display: block;">
                                Password must be at least 6 characters long.
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-input" 
                                   minlength="6" required>
                        </div>

                        <button type="submit" class="btn btn-success">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <circle cx="12" cy="16" r="1"></circle>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                            Change Password
                        </button>
                    </form>

                    <!-- Account Activity -->
                    <div style="margin-top: 40px;">
                        <h3 style="font-size: 1.3rem; margin-bottom: 20px; color: var(--text-color);">Recent Activity</h3>
                        
                        <div class="activity-item">
                            <div class="activity-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px;">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                            </div>
                            <div class="activity-info">
                                <div class="activity-title">Account Created</div>
                                <div class="activity-time">
                                    <?php 
                                    if ($user['CREATED_AT']) {
                                        echo date('F j, Y \a\t g:i A', strtotime($user['CREATED_AT']));
                                    } else {
                                        echo 'Date not available';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>

                        <?php if ($user['LAST_LOGIN_AT']): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                                    <polyline points="10 17 15 12 10 7"></polyline>
                                    <line x1="15" y1="12" x2="3" y2="12"></line>
                                </svg>
                            </div>
                            <div class="activity-info">
                                <div class="activity-title">Last Login</div>
                                <div class="activity-time">
                                    <?php echo date('F j, Y \a\t g:i A', strtotime($user['LAST_LOGIN_AT'])); ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Account Section -->
                <div class="settings-section" id="account-section">
                    <h2 class="section-title">
                        <div class="section-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                            </svg>
                        </div>
                        Account Overview
                    </h2>

                    <!-- Account Statistics -->
                    <div class="stats-grid">
                        <?php
                        try {
                            // Get user statistics
                            $postsStmt = $pdo->prepare("SELECT COUNT(*) as post_count FROM POSTS WHERE USER_ID = ?");
                            $postsStmt->execute([$userId]);
                            $postCount = $postsStmt->fetch(PDO::FETCH_ASSOC)['post_count'];

                            $recipesStmt = $pdo->prepare("SELECT COUNT(*) as recipe_count FROM RECIPES WHERE USER_ID = ?");
                            $recipesStmt->execute([$userId]);
                            $recipeCount = $recipesStmt->fetch(PDO::FETCH_ASSOC)['recipe_count'];

                            $storiesStmt = $pdo->prepare("SELECT COUNT(*) as story_count FROM STORIES WHERE USER_ID = ?");
                            $storiesStmt->execute([$userId]);
                            $storyCount = $storiesStmt->fetch(PDO::FETCH_ASSOC)['story_count'];
                        } catch (PDOException $e) {
                            $postCount = $recipeCount = $storyCount = 0;
                        }
                        ?>
                        
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $postCount; ?></div>
                            <div class="stat-label">Posts Created</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $recipeCount; ?></div>
                            <div class="stat-label">Recipes Shared</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $storyCount; ?></div>
                            <div class="stat-label">Stories Posted</div>
                        </div>
                    </div>

                    <!-- Danger Zone -->
                    <div class="danger-zone">
                        <h3 class="danger-zone-title">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                                <line x1="12" y1="9" x2="12" y2="13"></line>
                                <line x1="12" y1="17" x2="12.01" y2="17"></line>
                            </svg>
                            Danger Zone
                        </h3>
                        <p class="danger-zone-desc">
                            Once you delete your account, there is no going back. Please be certain. All your posts, recipes, stories, and data will be permanently deleted.
                        </p>
                        <button type="button" class="btn btn-danger" onclick="openDeleteModal()">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                <line x1="10" y1="11" x2="10" y2="17"></line>
                                <line x1="14" y1="11" x2="14" y2="17"></line>
                            </svg>
                            Delete Account
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Delete Account Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Delete Account</h3>
                <button type="button" class="close-modal" onclick="closeDeleteModal()">&times;</button>
            </div>
            <form action="settings.php" method="POST">
                <input type="hidden" name="action" value="delete_account">
                <p style="color: var(--danger-color); margin-bottom: 20px; font-weight: 500;">
                    ⚠️ This action cannot be undone. Your account and all associated data will be permanently deleted.
                </p>
                <div class="form-group">
                    <label for="delete_password" class="form-label">Enter your password to confirm:</label>
                    <input type="password" id="delete_password" name="delete_password" class="form-input" required>
                </div>
                <div style="display: flex; gap: 15px; justify-content: flex-end;">
                    <button type="button" class="btn btn-outline" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete My Account</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Tab switching functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.settings-tab');
            const sections = document.querySelectorAll('.settings-section');

            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const targetTab = this.getAttribute('data-tab');

                    // Remove active class from all tabs and sections
                    tabs.forEach(t => t.classList.remove('active'));
                    sections.forEach(s => s.classList.remove('active'));

                    // Add active class to clicked tab and corresponding section
                    this.classList.add('active');
                    document.getElementById(targetTab + '-section').classList.add('active');
                });
            });

            // Password confirmation validation
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');

            function validatePassword() {
                if (newPassword.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Passwords do not match');
                } else {
                    confirmPassword.setCustomValidity('');
                }
            }

            if (newPassword && confirmPassword) {
                newPassword.addEventListener('change', validatePassword);
                confirmPassword.addEventListener('keyup', validatePassword);
            }
        });

        // Modal functions
        function openDeleteModal() {
            document.getElementById('deleteModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
            document.body.style.overflow = 'auto';
            document.getElementById('delete_password').value = '';
        }

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target === modal) {
                closeDeleteModal();
            }
        });

        // Auto-hide messages after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const messages = document.querySelectorAll('.message');
            messages.forEach(message => {
                setTimeout(() => {
                    message.style.opacity = '0';
                    message.style.transform = 'translateY(-10px)';
                    setTimeout(() => {
                        message.remove();
                    }, 300);
                }, 5000);
            });
        });
    </script>
</body>

</html>