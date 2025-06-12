<?php
// Include the database connection script
require_once 'config/config.php';

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];
    
    // Get online users action
    if ($_POST['action'] === 'get_online_users') {
        try {
            // Get users who were active in the last 5 minutes
            $stmt = $pdo->prepare(
                "SELECT USER_ID, LAST_ACTIVITY 
                FROM USER_SESSIONS 
                WHERE LAST_ACTIVITY > DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
            );
            $stmt->execute();
            $online_users = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Get last seen for offline users
            $stmt = $pdo->prepare(
                "SELECT USER_ID, LAST_ACTIVITY 
                FROM USER_SESSIONS 
                WHERE LAST_ACTIVITY <= DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
            );
            $stmt->execute();
            $offline_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $last_seen = [];
            foreach ($offline_users as $user) {
                $last_activity = strtotime($user['LAST_ACTIVITY']);
                $now = time();
                $diff = $now - $last_activity;
                
                if ($diff < 3600) {
                    $last_seen[$user['USER_ID']] = floor($diff / 60) . ' minutes ago';
                } elseif ($diff < 86400) {
                    $last_seen[$user['USER_ID']] = floor($diff / 3600) . ' hours ago';
                } else {
                    $last_seen[$user['USER_ID']] = date('M j', $last_activity);
                }
            }
            
            $response['success'] = true;
            $response['online_users'] = $online_users;
            $response['last_seen'] = $last_seen;
            echo json_encode($response);
            exit;
        } catch (PDOException $e) {
            $response['message'] = 'Database error: ' . $e->getMessage();
            echo json_encode($response);
            exit;
        }
    }
    
    // Update user activity action
    if ($_POST['action'] === 'update_user_activity') {
        try {
            // First, check if user already has a session
            $checkStmt = $pdo->prepare(
                "SELECT SESSION_ID FROM USER_SESSIONS WHERE USER_ID = ? ORDER BY LAST_ACTIVITY DESC LIMIT 1"
            );
            $checkStmt->execute([$_SESSION['user_id']]);
            $existingSession = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingSession) {
                // Update existing session
                $stmt = $pdo->prepare(
                    "UPDATE USER_SESSIONS SET LAST_ACTIVITY = NOW() WHERE SESSION_ID = ?"
                );
                $stmt->execute([$existingSession['SESSION_ID']]);
            } else {
                // Create new session
                $stmt = $pdo->prepare(
                    "INSERT INTO USER_SESSIONS (USER_ID, LAST_ACTIVITY) VALUES (?, NOW())"
                );
                $stmt->execute([$_SESSION['user_id']]);
                
                // Store the session ID in the user's session for future reference
                $_SESSION['session_id'] = $pdo->lastInsertId();
            }
            
            $response['success'] = true;
            echo json_encode($response);
            exit;
        } catch (PDOException $e) {
            $response['message'] = 'Database error: ' . $e->getMessage();
            echo json_encode($response);
            exit;
        }
    }
    
    // Send message action - FIXED
    if ($_POST['action'] === 'send_message') {
        // Validate input
        if (!isset($_POST['receiver_id'])) {
            $response['message'] = 'Receiver ID is required';
            echo json_encode($response);
            exit;
        }
        
        $sender_id = $_SESSION['user_id'];
        $receiver_id = $_POST['receiver_id'];
        $content = isset($_POST['content']) ? trim($_POST['content']) : null;
        $image_url = null;
        $reactions = null;
        
        // Handle image upload if present - FIXED
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $file_type = $_FILES['image']['type'];
            
            if (!in_array($file_type, $allowed_types)) {
                $response['message'] = 'Invalid file type. Only JPEG, PNG, GIF, and WEBP are allowed.';
                echo json_encode($response);
                exit;
            }
            
            // Check file size (5MB max)
            if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
                $response['message'] = 'File size exceeds the limit of 5MB.';
                echo json_encode($response);
                exit;
            }
            
            // Create upload directory if it doesn't exist
            $upload_dir = 'uploads/messages/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename
            $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $new_filename = uniqid('msg_') . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                $image_url = $upload_path;
            } else {
                $response['message'] = 'Failed to upload image.';
                echo json_encode($response);
                exit;
            }
        }
        
        // Handle initial reaction if provided
        if (isset($_POST['reaction']) && !empty($_POST['reaction'])) {
            $reaction = $_POST['reaction'];
            $reactions = json_encode([$reaction => 1]);
        }
        
        // Ensure at least content or image is provided - FIXED
        if (empty($content) && !$image_url) {
            $response['message'] = 'Message content or image is required';
            echo json_encode($response);
            exit;
        }
        
        try {
            // Insert message into database
            $stmt = $pdo->prepare(
                "INSERT INTO MESSAGES (SENDER_ID, RECEIVER_ID, CONTENT, IMAGE_URL, REACTIONS, SENT_AT) 
                VALUES (?, ?, ?, ?, ?, NOW())"
            );
            
            $stmt->execute([$sender_id, $receiver_id, $content, $image_url, $reactions]);
            $message_id = $pdo->lastInsertId();
            
            $response['success'] = true;
            $response['message'] = 'Message sent successfully';
            $response['message_id'] = $message_id;
            echo json_encode($response);
            exit;
        } catch (PDOException $e) {
            $response['message'] = 'Database error: ' . $e->getMessage();
            echo json_encode($response);
            exit;
        }
    }
    
    // Add reaction action
    if ($_POST['action'] === 'add_reaction') {
        // Validate input
        if (!isset($_POST['message_id']) || !isset($_POST['reaction'])) {
            $response['message'] = 'Message ID and reaction are required';
            echo json_encode($response);
            exit;
        }
        
        $message_id = $_POST['message_id'];
        $reaction = $_POST['reaction'];
        $user_id = $_SESSION['user_id'];
        
        try {
            // Get current reactions
            $stmt = $pdo->prepare("SELECT REACTIONS FROM MESSAGES WHERE MESSAGE_ID = ?");
            $stmt->execute([$message_id]);
            $message = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$message) {
                $response['message'] = 'Message not found';
                echo json_encode($response);
                exit;
            }
            
            // Parse existing reactions or create new array
            $reactions = $message['REACTIONS'] ? json_decode($message['REACTIONS'], true) : [];
            
            // Toggle reaction
            if (isset($reactions[$reaction])) {
                $reactions[$reaction]++;
            } else {
                $reactions[$reaction] = 1;
            }
            
            // Update reactions in database
            $stmt = $pdo->prepare("UPDATE MESSAGES SET REACTIONS = ? WHERE MESSAGE_ID = ?");
            $stmt->execute([json_encode($reactions), $message_id]);
            
            $response['success'] = true;
            $response['message'] = 'Reaction added successfully';
            $response['reactions'] = $reactions;
            echo json_encode($response);
            exit;
        } catch (PDOException $e) {
            $response['message'] = 'Database error: ' . $e->getMessage();
            echo json_encode($response);
            exit;
        }
    }
    
    // Get users action
    if ($_POST['action'] === 'get_users') {
        try {
            $search = isset($_POST['search']) ? '%' . $_POST['search'] . '%' : null;
            
            if ($search) {
                $stmt = $pdo->prepare(
                    "SELECT USER_ID, NAME, PROFILE_IMAGE 
                    FROM USERS 
                    WHERE USER_ID != ? AND NAME LIKE ? 
                    ORDER BY NAME ASC"
                );
                $stmt->execute([$_SESSION['user_id'], $search]);
            } else {
                $stmt = $pdo->prepare(
                    "SELECT USER_ID, NAME, PROFILE_IMAGE 
                    FROM USERS 
                    WHERE USER_ID != ? 
                    ORDER BY NAME ASC"
                );
                $stmt->execute([$_SESSION['user_id']]);
            }
            
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response['success'] = true;
            $response['users'] = $users;
            echo json_encode($response);
            exit;
        } catch (PDOException $e) {
            $response['message'] = 'Database error: ' . $e->getMessage();
            echo json_encode($response);
            exit;
        }
    }
    
    // Get user details action
    if ($_POST['action'] === 'get_user_details') {
        // Validate input
        if (!isset($_POST['user_id'])) {
            $response['message'] = 'User ID is required';
            echo json_encode($response);
            exit;
        }
        
        $user_id = $_POST['user_id'];
        
        try {
            // Get user details
            $stmt = $pdo->prepare(
                "SELECT USER_ID, NAME, PROFILE_IMAGE 
                FROM USERS 
                WHERE USER_ID = ?"
            );
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $response['message'] = 'User not found';
                echo json_encode($response);
                exit;
            }
            
            $response['success'] = true;
            $response['user'] = $user;
            echo json_encode($response);
            exit;
        } catch (PDOException $e) {
            $response['message'] = 'Database error: ' . $e->getMessage();
            echo json_encode($response);
            exit;
        }
    }
    
    // Get messages action
    if ($_POST['action'] === 'get_messages') {
        // Validate input
        if (!isset($_POST['receiver_id'])) {
            $response['message'] = 'Receiver ID is required';
            echo json_encode($response);
            exit;
        }
        
        $sender_id = $_SESSION['user_id'];
        $receiver_id = $_POST['receiver_id'];
        $last_timestamp = isset($_POST['last_timestamp']) && !empty($_POST['last_timestamp']) ? $_POST['last_timestamp'] : 0;
        
        try {
            // Get messages between the two users
            if ($last_timestamp > 0) {
                // Only get messages newer than the last timestamp
                $stmt = $pdo->prepare(
                    "SELECT * FROM MESSAGES 
                    WHERE ((SENDER_ID = ? AND RECEIVER_ID = ?) OR (SENDER_ID = ? AND RECEIVER_ID = ?)) 
                    AND UNIX_TIMESTAMP(SENT_AT) > ? 
                    ORDER BY SENT_AT ASC"
                );
                $stmt->execute([$sender_id, $receiver_id, $receiver_id, $sender_id, $last_timestamp / 1000]);
            } else {
                // Get all messages
                $stmt = $pdo->prepare(
                    "SELECT * FROM MESSAGES 
                    WHERE (SENDER_ID = ? AND RECEIVER_ID = ?) OR (SENDER_ID = ? AND RECEIVER_ID = ?) 
                    ORDER BY SENT_AT ASC"
                );
                $stmt->execute([$sender_id, $receiver_id, $receiver_id, $sender_id]);
            }
            
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response['success'] = true;
            $response['messages'] = $messages;
            echo json_encode($response);
            exit;
        } catch (PDOException $e) {
            $response['message'] = 'Database error: ' . $e->getMessage();
            echo json_encode($response);
            exit;
        }
    }
    
    // If we get here, the action was not recognized
    $response['message'] = 'Invalid action';
    echo json_encode($response);
    exit;
}

// Get user's recent conversations
try {
    $current_user_id = $_SESSION['user_id'];
    
    // This query gets the most recent message between the current user and each other user
    $stmt = $pdo->prepare(
        "SELECT 
            u.USER_ID, u.NAME, u.PROFILE_IMAGE,
            m.CONTENT, m.SENT_AT,
            (SELECT COUNT(*) FROM MESSAGES WHERE 
                ((SENDER_ID = ? AND RECEIVER_ID = u.USER_ID) OR 
                (SENDER_ID = u.USER_ID AND RECEIVER_ID = ?)) AND 
                SENT_AT > NOW() - INTERVAL 1 DAY
            ) as MESSAGE_COUNT
        FROM USERS u
        LEFT JOIN MESSAGES m ON 
            ((m.SENDER_ID = ? AND m.RECEIVER_ID = u.USER_ID) OR 
            (m.SENDER_ID = u.USER_ID AND m.RECEIVER_ID = ?))
        WHERE u.USER_ID != ?
        GROUP BY u.USER_ID
        ORDER BY m.SENT_AT DESC"
    );
    $stmt->execute([$current_user_id, $current_user_id, $current_user_id, $current_user_id, $current_user_id]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $conversations = [];
    // Silently handle error
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Feedora - Your Cooking Dashboard">
    <meta name="theme-color" content="#ED5A2C">
    <title>Chat - Feedora</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="fonts.css">
    <link rel="stylesheet" href="Home.css">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Favicon -->
    <link rel="icon" href="images/Frame 1171277973.svg" type="image/svg+xml">
    <style>
        /* Enhanced Chat Page Styles with Fixed Responsive Sidebar */
        :root {
            --primary-color: #ED5A2C;
            --primary-color-light: #ff7e50;
            --secondary-color: #4CAF50;
            --text-color: #333;
            --light-text: #666;
            --background-color: #f9f9f9;
            --light-background: #f0f0f0;
            --border-color: #e0e0e0;
            --success-color: #4CAF50;
            --danger-color: #f44336;
            --warning-color: #FFC107;
            --info-color: #2196F3;
            --border-radius: 12px;
            --transition-speed: 0.3s;
            --box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            --hover-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
            --sidebar-width: 250px;
            --message-sent-bg: linear-gradient(135deg, #ED5A2C, #ff7e50);
            --message-received-bg: #f0f0f0;
            --message-sent-text: #fff;
            --message-received-text: #333;
            --chat-bg: #f8f8f8;
        }
        
        * {
            box-sizing: border-box;
        }
        
        body {
            margin: 0;
            padding: 0;
            font-family: 'Qurova', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            background-color: #f5f5f5;
            color: var(--text-color);
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        /* Apply font to all text elements */
        h1, h2, h3, h4, h5, h6, p, span, div, button, input, textarea {
            font-family: 'Qurova', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
        }
        
        /* FIXED: Main Content Styles with Proper Responsive Behavior */
        .main-content {
            flex: 1;
            padding: 0;
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            transition: all 0.3s ease;
  
        }
        
        /* FIXED: Sidebar Responsive Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background-color: white;
            border-right: 1px solid var(--border-color);
            z-index: 1000;
            transition: transform 0.3s ease;
            overflow-y: auto;
        }
        
        /* Sidebar Overlay for Mobile */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        /* FIXED: Responsive Breakpoints */
        
        /* Large Desktop (1200px and up) */
        @media (min-width: 1200px) {
            .main-content {
                margin-left: var(--sidebar-width);
                width: calc(100% - var(--sidebar-width));
            }
            
            .sidebar {
                transform: translateX(0);
                position: fixed;
            }
            
            .sidebar-toggle {
                display: none !important;
            }
            
            .mobile-conversations-toggle {
                display: none !important;
            }
        }
        
        /* Desktop and Tablet (992px to 1199px) */
        @media (max-width: 1199px) and (min-width: 992px) {
            .main-content {
                margin-left: var(--sidebar-width);
                width: calc(100% - var(--sidebar-width));
            }
            
            .sidebar {
                transform: translateX(0);
                position: fixed;
            }
            
            .sidebar-toggle {
                display: none !important;
            }
            
            .mobile-conversations-toggle {
                display: none !important;
            }
        }
        
        /* Tablet (768px to 991px) */
        @media (max-width: 991px) and (min-width: 768px) {
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                z-index: 1000;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .sidebar-overlay.active {
                display: block;
                opacity: 1;
            }
            
            .sidebar-toggle {
                display: flex !important;
            }
            
            .mobile-conversations-toggle {
                display: none !important;
            }
            
            .message-header {
                justify-content: center;
                padding-left: 70px;
                padding-right: 20px;
            }
        }
        
        /* Mobile (767px and below) */
        @media (max-width: 767px) {
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                z-index: 1000;
                width: 280px;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .sidebar-overlay.active {
                display: block;
                opacity: 1;
                padding-right: 20px;
            }
            
            .message-header-title {
                font-size: 18px;
            }
            
            .new-message-btn {
                padding: 8px 15px;
                font-size: 13px;
            }
        }
        
        /* Extra Small Mobile (576px and below) */
        @media (max-width: 576px) {
            .sidebar {
                width: 100%;
                max-width: 320px;
            }
            
            .message-header {
                padding-left: 60px;
                padding-right: 15px;
            }
            
            .message-header-title {
                font-size: 16px;
            }
            
            .new-message-btn {
                padding: 6px 12px;
                font-size: 12px;
            }
            
            .mobile-conversations-toggle {
                width: 55px;
                height: 55px;
                font-size: 20px;
                bottom: 15px;
                right: 15px;
            }
        }
        
        /* Chat Container */
        .chat-container {
            display: flex;
            width: 100%;
            height: 100vh; /* Full height */
            overflow: hidden; /* Prevent scrolling */            background-color: var(--background-color);
            will-change: transform;
            transition: all 0.3s ease;
            -webkit-overflow-scrolling: touch;
            user-select: none;
            margin-top: 0;
            box-shadow: var(--box-shadow);
            border-radius: var(--border-radius);
        }
        
        /* Conversations List */
        .conversations-list {
            width: 320px;
            min-width: 320px;
            background-color: white;
            border-right: 1px solid var(--border-color);
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05);
        }
        
        /* FIXED: Mobile Conversations List */
        @media (max-width: 767px) {
            .conversations-list {
                position: fixed;
                left: -320px;
                top: 0;
                height: 100vh;
                z-index: 1000;
                transition: all 0.3s ease;
                width: 320px;
                min-width: 320px;
                background-color: white;
            }
            
            .conversations-list.mobile-open {
                left: 0;
                box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
            }
            
            .mobile-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 999;
                opacity: 0;
                transition: opacity 0.3s ease;
            }
            
            .mobile-overlay.active {
                display: block;
                opacity: 1;
                z-index: 999;
            }
        }
        
        @media (max-width: 576px) {
            .conversations-list {
                width: 100%;
                max-width: 300px;
                left: -100%;
            }
            
            .conversations-list.mobile-open {
                left: 0;
            }
        }
        
        /* Search Conversation Styling */
        .search-conversation {
            position: sticky;
            top: 0;
            padding: 20px;
            background-color: white;
            z-index: 10;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .search-conversation input {
            width: 100%;
            padding: 12px 20px 12px 45px;
            border: 2px solid var(--border-color);
            border-radius: 25px;
            font-size: 14px;
            outline: none;
            transition: all 0.3s ease;
            color: var(--text-color);
            background-color: var(--light-background);
            font-weight: 500;
        }
        
        .search-conversation input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(237, 90, 44, 0.1);
            background-color: white;
            transform: translateY(-1px);
        }
        
        .search-conversation .search-icon {
            position: absolute;
            left: 35px;
            font-size: 16px;
            color: var(--light-text);
            transition: all 0.3s ease;
            pointer-events: none;
        }
        
        .search-conversation input:focus + .search-icon {
            color: var(--primary-color);
            transform: scale(1.1);
        }
        
        .search-conversation input::placeholder {
            color: var(--light-text);
            opacity: 0.7;
            transition: opacity 0.3s ease;
        }
        
        .search-conversation input:focus::placeholder {
            opacity: 0.5;
        }
        
        /* Message Content Area */
        .message-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            background-color: var(--background-color);
            width: 100%;
            position: relative;
        }
        
        /* FIXED: Enhanced Message Header */
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            border-bottom: 1px solid var(--border-color);
            background: linear-gradient(135deg, #fff 0%, #fafafa 100%);
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
            z-index: 5;
            position: relative;
            min-height: 70px;
        }

        .message-header-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-color);
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0;
        }

        .message-header-title::before {
            content: '';
            width: 8px;
            height: 8px;
            background: var(--primary-color);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        .message-header-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .new-message-btn {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-color-light));
            color: white;
            border: none;
            border-radius: 30px;
            padding: 12px 24px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(237, 90, 44, 0.3);
            text-transform: none;
            letter-spacing: 0.5px;
        }

        .new-message-btn:hover {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-color-light));
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(237, 90, 44, 0.4);
        }

        .new-message-btn:active {
            transform: translateY(0);
            transition: all 0.1s ease;
        }

        .new-message-btn i {
            font-size: 16px;
            font-weight: 600;
        }

        /* FIXED: Sidebar Toggle Button Positioning */
        .sidebar-toggle {
            display: none;
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background-color: rgba(237, 90, 44, 0.1);
            border: none;
            color: var(--primary-color);
            font-size: 18px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;
            align-items: center;
            justify-content: center;
        }

        .sidebar-toggle:hover {
            background-color: rgba(237, 90, 44, 0.2);
            transform: translateY(-50%) scale(1.05);
            color: var(--primary-color-dark);
        }

        .sidebar-toggle:active {
            transform: translateY(-50%) scale(0.95);
        }

        /* FIXED: Responsive Header Adjustments */
        @media (max-width: 991px) {
            .sidebar-toggle {
                display: flex !important;
            }
            
            .message-header {
                padding-left: 80px;
                padding-right: 20px;
            }
            
            .message-header-title {
                font-size: 22px;
            }
        }

        @media (max-width: 767px) {
            .message-header {
                padding: 15px 20px 15px 75px;
                min-height: 60px;
            }
            
            .message-header-title {
                font-size: 20px;
            }
            
            .new-message-btn {
                padding: 10px 18px;
                font-size: 14px;
                border-radius: 25px;
            }
            
            .new-message-btn span {
                display: none;
            }
            
            .new-message-btn i {
                margin-right: 0;
            }
        }

        @media (max-width: 576px) {
            .message-header {
                padding: 12px 15px 12px 65px;
                min-height: 55px;
            }
            
            .message-header-title {
                font-size: 18px;
            }
            
            .new-message-btn {
                padding: 8px 15px;
                font-size: 13px;
            }
            
            .sidebar-toggle {
                width: 40px;
                height: 40px;
                left: 15px;
                font-size: 16px;
            }
        }
        
        /* Active Conversation Header */
        .conversation-header-active {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            background-color: white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            z-index: 5;
        }
        
        .conversation-header-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 15px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.15);
            border: 3px solid white;
        }
        
        .conversation-header-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .conversation-header-info {
            flex: 1;
        }
        
        #active-conversation-name {
            font-weight: 700;
            font-size: 18px;
            color: var(--text-color);
            margin-bottom: 2px;
        }
        
        .conversation-status {
            font-size: 13px;
            color: var(--light-text);
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
        }
        
        .conversation-status.online .status-text {
            color: var(--success-color);
            font-weight: 600;
        }
        
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 5px;
            transition: all 0.3s ease;
        }
        
        .status-online {
            background-color: var(--success-color);
            animation: pulse 2s infinite;
            box-shadow: 0 0 10px rgba(46, 204, 113, 0.4);
        }
        
        .status-offline {
            background-color: var(--light-text);
        }
        
        @keyframes pulse {
            0% { opacity: 0.7; transform: scale(0.95); }
            50% { opacity: 1; transform: scale(1.05); }
            100% { opacity: 0.7; transform: scale(0.95); }
        }
        
        .conversation-header-actions {
            display: flex;
            gap: 10px;
        }
        
        .header-action-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: transparent;
            border: none;
            color: var(--light-text);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .header-action-btn:hover {
            background-color: rgba(237, 90, 44, 0.1);
            color: var(--primary-color);
            transform: scale(1.1);
        }
        
        /* Message Area */
        .message-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background-color: var(--chat-bg);
            width: 100%;
            overflow: hidden;
            position: relative;
        }
        
        /* No message selected styling */
        .no-message-selected {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100%;
            color: var(--light-text);
            text-align: center;
            padding: 40px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
        
        .no-message-selected .icon-container {
            color: var(--primary-color);
            background-color: white;
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(237, 90, 44, 0.2);
            margin-bottom: 25px;
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        .icon-container i {
            font-size: 40px;
            color: var(--primary-color);
        }
        
        .no-message-selected h3 {
            font-size: 24px;
            margin-bottom: 15px;
            color: var(--text-color);
            font-weight: 700;
        }
        
        .no-message-selected p {
            font-size: 16px;
            max-width: 350px;
            line-height: 1.6;
            color: var(--light-text);
            margin-bottom: 25px;
        }
        
        /* Conversation Item */
        .conversation-item {
            display: flex;
            align-items: center;
            padding: 18px 20px;
            border-bottom: 1px solid rgba(224, 224, 224, 0.5);
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            will-change: background-color, transform;
        }
        
        .conversation-item:hover {
            background-color: rgba(237, 90, 44, 0.05);
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .conversation-item.active {
            background-color: rgba(237, 90, 44, 0.1);
            border-left: 4px solid var(--primary-color);
            box-shadow: inset 0 0 20px rgba(237, 90, 44, 0.05);
        }
        
        .conversation-item:hover .conversation-avatar {
            border-color: var(--primary-color);
            transform: scale(1.05);
        }
        
        .conversation-item.active .conversation-avatar {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(237, 90, 44, 0.2);
        }
        
        .conversation-avatar {
            width: 55px;
            height: 55px;
            border-radius: 50%;
            margin-right: 15px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            flex-shrink: 0;
            transition: all 0.3s ease;
            border: 3px solid transparent;
        }
        
        .conversation-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .conversation-info {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .conversation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }
        
        .conversation-name {
            font-weight: 700;
            font-size: 16px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: var(--text-color);
            transition: color 0.2s ease;
        }
        
        .conversation-time {
            font-size: 12px;
            color: var(--light-text);
            white-space: nowrap;
            margin-left: 10px;
            font-weight: 500;
        }
        
        .conversation-last-message {
            font-size: 14px;
            color: var(--light-text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: color 0.2s ease;
        }
        
        .unread-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: var(--primary-color);
            position: absolute;
            top: 18px;
            right: 18px;
            animation: pulse 2s infinite;
        }
        
        /* Enhanced Button Styles */
        .attachment-btn, .emoji-btn, .send-btn, .back-to-messages, .reaction-btn {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background-color: rgba(237, 90, 44, 0.1);
            color: var(--primary-color);
            border: none;
            touch-action: manipulation;
            position: relative;
            overflow: hidden;
        }
        
        .attachment-btn::before, .emoji-btn::before, .reaction-btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background-color: rgba(237, 90, 44, 0.2);
            border-radius: 50%;
            transition: all 0.3s ease;
            transform: translate(-50%, -50%);
        }
        
        .attachment-btn:hover::before, .emoji-btn:hover::before, .reaction-btn:hover::before {
            width: 100%;
            height: 100%;
        }
        
        .attachment-btn:hover, .emoji-btn:hover, .send-btn:hover, .back-to-messages:hover, .reaction-btn:hover {
            background-color: rgba(237, 90, 44, 0.2);
            transform: scale(1.1);
            color: var(--primary-color-dark);
            box-shadow: 0 5px 15px rgba(237, 90, 44, 0.3);
        }
        
        .attachment-btn:active, .emoji-btn:active, .send-btn:active, .back-to-messages:active, .reaction-btn:active {
            transform: scale(0.95);
            transition: all 0.1s ease;
        }
        
        /* Enhanced Send Button */
        .send-btn {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-color-light));
            color: white;
            box-shadow: 0 4px 15px rgba(237, 90, 44, 0.4);
        }
        
        .send-btn:hover {
            background: linear-gradient(135deg, var(--primary-color-dark), var(--primary-color));
            color: white;
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(237, 90, 44, 0.5);
        }
        
        .attachment-btn i, .emoji-btn i, .reaction-btn i, .send-btn i {
            font-size: 18px;
            display: block;
            position: relative;
            z-index: 2;
        }
        
        /* Active Conversation Styles */
        .active-conversation {
            display: flex;
            flex-direction: column;
            height: 100%;
            width: 100%;
            overflow: hidden;
            position: relative;
        }
        
        .messages-container {
            flex-grow: 1;
            padding: 25px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 20px;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
            scrollbar-color: rgba(237, 90, 44, 0.3) transparent;
        }
        
        /* Enhanced Scrollbar */
        .messages-container::-webkit-scrollbar {
            width: 8px;
        }
        
        .messages-container::-webkit-scrollbar-track {
            background: transparent;
        }
        
        .messages-container::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, rgba(237, 90, 44, 0.3), rgba(237, 90, 44, 0.5));
            border-radius: 10px;
        }
        
        .messages-container::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, rgba(237, 90, 44, 0.5), rgba(237, 90, 44, 0.7));
        }
        
        /* Enhanced Message Styles */
        .message {
            display: flex;
            margin-bottom: 20px;
            position: relative;
            max-width: 75%;
            animation: slideIn 0.4s ease;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .message-sent {
            align-self: flex-end;
            flex-direction: row-reverse;
        }
        
        .message-received {
            align-self: flex-start;
        }
        
        .message-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 10px;
            flex-shrink: 0;
            align-self: flex-end;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.15);
            border: 2px solid white;
        }
        
        .message-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .message-content {
            display: flex;
            flex-direction: column;
        }
        
        .message-bubble {
            padding: 15px 20px;
            border-radius: 20px;
            max-width: 100%;
            word-break: break-word;
            position: relative;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            animation: messageAppear 0.4s ease;
            font-size: 15px;
            line-height: 1.4;
        }
        
        @keyframes messageAppear {
            from { opacity: 0; transform: scale(0.95) translateY(10px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        
        .message-bubble .reaction-hover-btn {
            position: absolute;
            right: -40px;
            top: 50%;
            transform: translateY(-50%);
            background-color: white;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            border: none;
            color: var(--primary-color);
            z-index: 5;
            touch-action: manipulation;
        }
        
        .message-bubble .reaction-hover-btn:hover {
            background-color: rgba(237, 90, 44, 0.1);
            transform: translateY(-50%) scale(1.2);
            color: var(--primary-color-dark);
            box-shadow: 0 6px 20px rgba(237, 90, 44, 0.3);
        }
        
        .message-received .message-bubble .reaction-hover-btn {
            right: auto;
            left: -40px;
        }
        
        .message-bubble .reaction-hover-btn:active {
            transform: translateY(-50%) scale(0.9);
            background-color: rgba(237, 90, 44, 0.2);
            transition: all 0.1s ease;
        }
        
        .message:hover .reaction-hover-btn {
            opacity: 1;
        }
        
        .message-bubble:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .message-sent .message-bubble {
            background: var(--message-sent-bg);
            color: var(--message-sent-text);
            border-bottom-right-radius: 6px;
        }
        
        .message-received .message-bubble {
            background-color: var(--message-received-bg);
            color: var(--message-received-text);
            border-bottom-left-radius: 6px;
        }
        
        .message-time {
            font-size: 11px;
            color: rgba(0, 0, 0, 0.5);
            margin-top: 6px;
            margin-left: 10px;
            margin-right: 10px;
            opacity: 0.8;
            transition: opacity 0.2s ease;
            font-weight: 500;
        }
        
        .message-sent .message-time {
            text-align: right;
            color: black;
        }
        
        .message-received .message-time {
            text-align: left;
            color: black;

        }
        
        .message:hover .message-time {
            opacity: 1;
        }
        
        /* Enhanced Image Messages */
        .message-image {
            max-width: 100%;
            max-height: 350px;
            border-radius: 15px;
            margin-top: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            position: relative;
            overflow: hidden;
        }
        
        .message-image::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, transparent 0%, rgba(0, 0, 0, 0.1) 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .message-image:hover::before {
            opacity: 1;
        }
        
        .message-image .reaction-hover-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.25);
            border: none;
            color: var(--primary-color);
            z-index: 5;
        }
        
        .message-received .message-image .reaction-hover-btn {
            right: 15px;
        }
        
        .message-image:hover .reaction-hover-btn {
            opacity: 1;
        }
        
        .message-image .reaction-hover-btn:hover {
            transform: scale(1.2);
            background-color: white;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        }
        
        .message-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 15px;
        }
        
        .message-image:hover {
            transform: scale(1.02);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
        }
        
        /* Enhanced Reactions */
        .message-reactions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
            justify-content: flex-end;
        }
        
        .message-received .message-reactions {
            justify-content: flex-start;
        }
        
        .message-reaction {
            background-color: white;
            border-radius: 15px;
            padding: 6px 12px;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.15);
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 2px;
            border: 2px solid rgba(237, 90, 44, 0.1);
            min-width: 50px;
            justify-content: center;
        }
        
        .message-reaction:hover {
            transform: scale(1.1);
            background-color: rgba(237, 90, 44, 0.1);
            box-shadow: 0 4px 15px rgba(237, 90, 44, 0.3);
            border-color: var(--primary-color);
        }
        
        .message-reaction:active {
            transform: scale(0.95);
            transition: all 0.1s ease;
        }
        
        .message-reaction-count {
            font-size: 13px;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        /* Enhanced Message Input */
        .message-input-container {
            display: flex;
            align-items: center;
            padding: 20px 25px;
            border-top: 1px solid var(--border-color);
            background-color: #fff;
            position: sticky;
            bottom: 0;
            box-shadow: 0 -5px 20px rgba(0, 0, 0, 0.08);
            z-index: 10;
            transition: all 0.3s ease;
            border-radius: 15px 15px 0 0;
        }
        
        .message-input-container:focus-within {
            box-shadow: 0 -8px 30px rgba(0, 0, 0, 0.12);
            padding-top: 25px;
            padding-bottom: 25px;
            background-color: #fafafa;
        }
        
        .message-attachments {
            display: flex;
            align-items: center;
            margin-right: 15px;
            gap: 10px;
        }
        
        .message-input-wrapper {
            flex: 1;
            position: relative;
            background-color: #f8f9fa;
            border-radius: 25px;
            padding: 0 8px;
            transition: all 0.3s ease;
            min-height: 50px;
            display: flex;
            align-items: center;
            border: 2px solid transparent;
        }
        
        .message-input-wrapper:focus-within {
            background-color: white;
            box-shadow: 0 0 0 3px rgba(237, 90, 44, 0.1);
            border-color: rgba(237, 90, 44, 0.3);
        }
        
        #message-input {
            width: 100%;
            padding: 15px 20px;
            border: none;
            border-radius: 25px;
            font-size: 15px;
            resize: none;
            max-height: 120px;
            overflow-y: auto;
            transition: all 0.3s ease;
            background-color: transparent;
            outline: none;
            font-weight: 500;
            line-height: 1.4;
        }
        
        /* Mobile Conversations Toggle */
        .mobile-conversations-toggle {
            display: none;
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-color-light));
            color: white;
            font-size: 24px;
            align-items: center;
            justify-content: center;
            box-shadow: 0 3px 10px rgba(237, 90, 44, 0.3);
            z-index: 1001;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .send-message-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-color-light));
            color: white;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            margin-left: 15px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(237, 90, 44, 0.4);
        }
        
        .send-message-btn i {
            font-size: 20px;
        }
        
        .send-message-btn:hover {
            background: linear-gradient(135deg, var(--primary-color-dark), var(--primary-color));
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(237, 90, 44, 0.6);
        }
        
        .send-message-btn:active {
            transform: translateY(-1px);
            transition: all 0.1s ease;
        }
        
        /* Enhanced Image Preview */
        .image-preview {
            position: relative;
            margin-top: 15px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            animation: fadeIn 0.3s ease;
        }
        
        #preview-image {
            max-width: 250px;
            max-height: 200px;
            display: block;
            border-radius: 12px;
        }
        
        .remove-image-btn {
            position: absolute;
            top: 8px;
            right: 8px;
            background-color: rgba(0, 0, 0, 0.7);
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .remove-image-btn:hover {
            background-color: rgba(0, 0, 0, 0.9);
            transform: scale(1.1);
        }
        
        /* Enhanced Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            background-color: white;
            border-radius: 25px;
            width: 90%;
            max-width: 550px;
            max-height: 85vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.4s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        @keyframes slideUp {
            from { transform: translateY(60px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 25px 30px;
            border-bottom: 1px solid var(--border-color);
            background-color: white;
            position: relative;
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 24px;
            color: var(--text-color);
            font-weight: 700;
        }
        
        .close-modal-btn, .back-to-messages-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--light-text);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .close-modal-btn:hover, .back-to-messages-btn:hover {
            background-color: rgba(237, 90, 44, 0.1);
            color: var(--primary-color);
            transform: scale(1.1);
        }
        
        .modal-body {
            padding: 30px;
            overflow-y: auto;
            max-height: calc(85vh - 90px);
        }
        
        /* Enhanced User List */
        .search-users {
            margin-bottom: 20px;
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .search-users::before {
            content: '\f002';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--light-text);
            z-index: 1;
            font-size: 16px;
        }
        
        #search-users-input {
            width: 100%;
            padding: 15px 25px 15px 50px;
            border: 2px solid #eee;
            border-radius: 25px;
            font-size: 16px;
            outline: none;
            background-color: white;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            color: #333;
            font-weight: 500;
        }
        
        #search-users-input:focus {
            border-color: var(--primary-color);
            background-color: white;
            box-shadow: 0 0 0 4px rgba(237, 90, 44, 0.1);
            transform: translateY(-1px);
        }
        
        .users-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 15px;
        }
        
        .user-item {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            border-radius: 18px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            background-color: #f8f9fa;
        }
        
        .user-item:hover {
            background-color: rgba(237, 90, 44, 0.05);
            border-color: rgba(237, 90, 44, 0.2);
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(237, 90, 44, 0.15);
        }
        
        .user-avatar {
            width: 55px;
            height: 55px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 18px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
            border: 3px solid white;
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .user-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .user-name {
            font-weight: 700;
            color: var(--text-color);
            font-size: 17px;
        }
        
        .user-status {
            font-size: 14px;
            color: var(--light-text);
            font-weight: 500;
        }
        
        .loading-users {
            text-align: center;
            padding: 30px;
            color: var(--light-text);
            font-size: 16px;
        }
        
        /* Enhanced Emoji Picker */
        .emoji-picker {
            position: absolute;
            bottom: 70px;
            left: 15px;
            background-color: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            display: none;
            width: 320px;
            z-index: 1000;
            animation: fadeInUp 0.3s ease;
            max-height: 350px;
            overflow: hidden;
            border: 1px solid rgba(237, 90, 44, 0.1);
        }
        
        .emoji-picker.active {
            display: block;
        }
        
        .emoji-picker-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            background-color: #f8f9fa;
        }
        
        .emoji-picker-header h4 {
            margin: 0;
            color: var(--text-color);
            font-size: 18px;
            font-weight: 700;
        }
        
        .close-emoji-btn {
            background: none;
            border: none;
            color: var(--light-text);
            cursor: pointer;
            font-size: 16px;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .close-emoji-btn:hover {
            background-color: rgba(237, 90, 44, 0.1);
            color: var(--primary-color);
        }
        
        .emoji-categories {
            display: flex;
            justify-content: center;
            gap: 15px;
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            background-color: white;
        }
        
        .emoji-category {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 12px;
            color: #777;
            transition: all 0.3s ease;
        }
        
        .emoji-category.active {
            background-color: var(--primary-color);
            color: white;
            transform: scale(1.1);
        }
        
        .emoji-category:hover {
            background-color: rgba(237, 90, 44, 0.1);
            color: var(--primary-color);
        }
        
        .emoji-container {
            padding: 15px;
            max-height: 220px;
            overflow-y: auto;
        }
        
        .emoji-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
        }
        
        .emoji {
            font-size: 28px;
            cursor: pointer;
            padding: 10px;
            border-radius: 12px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            user-select: none;
        }
        
        .emoji:hover {
            background-color: rgba(237, 90, 44, 0.1);
            transform: scale(1.2);
        }
        
        /* Enhanced Reactions Menu - FIXED */
        .reactions-menu {
            display: none;
            position: fixed;
            background-color: white;
            border-radius: 30px;
            padding: 12px;
            gap: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            z-index: 1001;
            animation: fadeInUp 0.3s ease;
            border: 2px solid rgba(237, 90, 44, 0.1);
            backdrop-filter: blur(10px);
        }
        
        .reactions-menu.active {
            display: flex;
        }
        
        .reaction-item {
            position: relative;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border-radius: 50%;
            transition: all 0.3s ease;
            background-color: white;
            touch-action: manipulation;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            border: 2px solid rgba(237, 90, 44, 0.1);
        }
        
        .reaction-item:hover {
            background-color: rgba(237, 90, 44, 0.1);
            transform: scale(1.3);
            box-shadow: 0 6px 20px rgba(237, 90, 44, 0.3);
            border-color: var(--primary-color);
        }
        
        .reaction-item:active {
            transform: scale(0.9);
            transition: all 0.1s ease;
        }
        
        .reaction-emoji {
            font-size: 26px;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            user-select: none;
            -webkit-user-select: none;
        }
        
        /* Tooltip for reactions */
        .reaction-item::after {
            content: attr(data-reaction);
            position: absolute;
            top: -35px;
            left: 50%;
            transform: translateX(-50%);
            background-color: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 12px;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            white-space: nowrap;
        }
        
        .reaction-item:hover::after {
            opacity: 1;
            visibility: visible;
        }
        
        /* Fullscreen image styles */
        .fullscreen-image {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.95);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 2000;
            cursor: pointer;
            animation: fadeIn 0.3s ease;
            backdrop-filter: blur(10px);
        }
        
        .fullscreen-image img {
            max-width: 95%;
            max-height: 95%;
            object-fit: contain;
            border-radius: 10px;
            box-shadow: 0 10px 50px rgba(0, 0, 0, 0.5);
        }
        
        .no-messages {
            text-align: center;
            padding: 40px;
            color: var(--light-text);
            font-style: italic;
            font-size: 16px;
        }
        
        .loading-messages {
            text-align: center;
            padding: 30px;
            color: var(--light-text);
            font-size: 16px;
        }
        
        /* FIXED: Mobile Responsive Adjustments */
        @media (max-width: 767px) {
            .message {
                max-width: 85%;
            }
            
            .message-bubble {
                max-width: 90%;
                padding: 12px 16px;
                font-size: 14px;
            }
            
            .message-avatar {
                width: 35px;
                height: 35px;
                margin: 0 8px;
            }
            
            .reaction-hover-btn {
                opacity: 0.8 !important;
                width: 32px !important;
                height: 32px !important;
            }
            
            .message-input-container {
                padding: 15px 20px;
            }
            
            .attachment-btn, .emoji-btn, .reaction-btn {
                width: 40px;
                height: 40px;
            }
            
            .send-message-btn {
                width: 45px;
                height: 45px;
            }
            
            .reaction-item {
                width: 42px;
                height: 42px;
            }
            
            .reaction-emoji {
                font-size: 22px;
            }
            
            .emoji-picker {
                width: 280px;
                left: 10px;
                bottom: 80px;
            }
            
            .reactions-menu {
                max-width: 260px;
                padding: 8px;
                gap: 8px;
            }
        }
        
        @media (max-width: 576px) {
            .message {
                max-width: 95%;
            }
            
            .message-bubble {
                max-width: 100%;
                padding: 10px 14px;
                font-size: 14px;
            }
            
            .reactions-menu {
                max-width: 240px;
                padding: 6px;
                gap: 6px;
            }
            
            .reaction-item {
                width: 38px;
                height: 38px;
            }
            
            .reaction-emoji {
                font-size: 20px;
            }
            
            .conversation-avatar {
                width: 45px;
                height: 45px;
                margin-right: 12px;
            }
            
            .conversation-name {
                font-size: 15px;
            }
            
            .conversation-last-message {
                font-size: 13px;
            }
            
            .message-input-container {
                padding: 12px 15px;
            }
            
            .message-input-wrapper {
                min-height: 45px;
            }
            
            #message-input {
                padding: 12px 16px;
                font-size: 14px;
            }
            
            .modal-content {
                width: 95%;
                margin: 20px;
            }
            
            .modal-header {
                padding: 20px 25px;
            }
            
            .modal-body {
                padding: 25px;
            }
        }
        
        /* Loading animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Improved focus states for accessibility */
        .conversation-item:focus,
        .user-item:focus,
        .message-reaction:focus,
        .reaction-item:focus,
        .emoji:focus {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }
        
        /* High contrast mode support */
        @media (prefers-contrast: high) {
            :root {
                --border-color: #000;
                --light-text: #000;
                --message-received-bg: #f0f0f0;
            }
        }
        
        /* Reduced motion support */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body>
    <?php include('sidebar.php'); ?>
    
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebar-overlay"></div>
    
    <!-- Mobile conversations toggle button -->
    <button class="mobile-conversations-toggle" id="mobile-conversations-toggle">
        <i class="fas fa-comments"></i>
    </button>
    
    <!-- Main Content Area -->
    <main class="main-content">
        <!-- Mobile overlay for conversations list -->
        <div class="mobile-overlay" id="mobile-overlay"></div>
        
        <!-- Chat Container -->
        <div class="chat-container">
            <!-- Conversations List -->
            <div class="conversations-list" id="conversations-list">
                <div class="search-conversation">
                    <input type="text" placeholder="Search conversations..." id="search-conversations">
                    <i class="fas fa-search search-icon"></i>
                </div>
                
                <?php if (count($conversations) > 0): ?>
                    <?php foreach ($conversations as $conversation): ?>
                        <div class="conversation-item" data-user-id="<?php echo $conversation['USER_ID']; ?>" data-user-name="<?php echo htmlspecialchars($conversation['NAME']); ?>" data-user-image="<?php echo $conversation['PROFILE_IMAGE']; ?>">
                            <div class="conversation-avatar">
                                <img src="<?php echo !empty($conversation['PROFILE_IMAGE']) ? $conversation['PROFILE_IMAGE'] : 'uploads/profiles/default.png'; ?>" alt="<?php echo htmlspecialchars($conversation['NAME']); ?>">
                            </div>
                            <div class="conversation-info">
                                <div class="conversation-header">
                                    <div class="conversation-name"><?php echo htmlspecialchars($conversation['NAME']); ?></div>
                                    <div class="conversation-time">
                                        <?php 
                                            if (!empty($conversation['SENT_AT'])) {
                                                $sent_time = strtotime($conversation['SENT_AT']);
                                                $now = time();
                                                $diff = $now - $sent_time;
                                                
                                                if ($diff < 60) {
                                                    echo 'Just now';
                                                } elseif ($diff < 3600) {
                                                    echo floor($diff / 60) . 'm';
                                                } elseif ($diff < 86400) {
                                                    echo floor($diff / 3600) . 'h';
                                                } else {
                                                    echo date('M j', $sent_time);
                                                }
                                            }
                                        ?>
                                    </div>
                                </div>
                                <div class="conversation-last-message">
                                    <?php 
                                        if (!empty($conversation['CONTENT'])) {
                                            echo htmlspecialchars(substr($conversation['CONTENT'], 0, 35)) . (strlen($conversation['CONTENT']) > 35 ? '...' : '');
                                        } elseif (!empty($conversation['IMAGE_URL'])) {
                                            echo '<i class="fas fa-image"></i> Photo';
                                        } else {
                                            echo 'Start a conversation';
                                        }
                                    ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="conversation-item">
                        <div class="conversation-info">
                            <div class="conversation-name">No conversations yet</div>
                            <div class="conversation-last-message">Start a new conversation</div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Message Content -->
            <div class="message-content">
                <div class="message-header">
                    <button class="sidebar-toggle" id="sidebar-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 class="message-header-title">
                        Messages
                    </h1>
                    <div class="message-header-actions">
                        <button class="new-message-btn" id="new-message-btn">
                            <i class="fas fa-plus"></i>
                            <span>New Chat</span>
                        </button>
                    </div>
                </div>
                
                <div class="message-area" id="message-area">
                    <div class="no-message-selected" id="no-message-selected">
                        <div class="icon-container">
                            <i class="fas fa-comments"></i>
                        </div>
                        <h3>Your Messages</h3>
                        <p>Select a conversation or start a new one to begin chatting with fellow food enthusiasts</p>
                        <button class="new-message-btn" id="new-message-mobile-btn">
                            <i class="fas fa-plus"></i> Start New Chat
                        </button>
                    </div>
                    
                    <!-- Active conversation will be displayed here -->
                    <div class="active-conversation" id="active-conversation" style="display: none;">
                        <div class="conversation-header-active">
                            <div class="conversation-header-avatar">
                                <img src="uploads/profiles/default.png" alt="User">
                            </div>
                            <div class="conversation-header-info">
                                <div id="active-conversation-name">User Name</div>
                                <div class="conversation-status">
                                    <i class="fas fa-circle status-indicator"></i>
                                    <span class="status-text">Active now</span>
                                </div>
                            </div>
                            <div class="conversation-header-actions">
                                <button class="header-action-btn" title="More options">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="messages-container" id="messages-container"></div>
                        
                        <div class="message-input-container">
                            <div class="message-attachments">
                                <label for="message-image" class="attachment-btn" title="Attach image">
                                    <i class="fas fa-image"></i>
                                </label>
                                <input type="file" id="message-image" accept="image/*" style="display: none;">
                                
                                <button class="emoji-btn" id="show-emoji-btn" title="Add emoji">
                                    <i class="fas fa-smile"></i>
                                </button>
                            </div>
                            
                            <div class="message-input-wrapper">
                                <textarea id="message-input" placeholder="Type your message..." rows="1"></textarea>
                                <div class="image-preview" id="image-preview" style="display: none;">
                                    <img id="preview-image" src="/placeholder.svg" alt="Preview">
                                    <button class="remove-image-btn" id="remove-image-btn" title="Remove image">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <button class="send-message-btn" id="send-message-btn" title="Send message">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- New Message Modal -->
        <div class="modal" id="new-message-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>New Message</h3>
                    <button class="back-to-messages-btn" id="back-to-messages-btn" title="Close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="modal-body">
                    <div class="search-users">
                        <input type="text" id="search-users-input" placeholder="Search users...">
                    </div>
                    
                    <div class="users-list" id="users-list">
                        <div class="loading-users">Loading users...</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Emoji Picker -->
        <div class="emoji-picker" id="emoji-picker">
            <div class="emoji-picker-header">
                <h4>Emojis</h4>
                <button class="close-emoji-btn" id="close-emoji-btn" title="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="emoji-categories">
                <button class="emoji-category active" data-category="food" title="Food">
                    🍕
                </button>
                <button class="emoji-category" data-category="smileys" title="Smileys">
                    😊
                </button>
                <button class="emoji-category" data-category="symbols" title="Symbols">
                    ❤️
                </button>
            </div>
            <div class="emoji-container">
                <div class="emoji-group" id="food-emojis">
                    <span class="emoji" data-emoji="🍕">🍕</span>
                    <span class="emoji" data-emoji="🍔">🍔</span>
                    <span class="emoji" data-emoji="🍗">🍗</span>
                    <span class="emoji" data-emoji="🍜">🍜</span>
                    <span class="emoji" data-emoji="🍰">🍰</span>
                    <span class="emoji" data-emoji="🍎">🍎</span>
                    <span class="emoji" data-emoji="🥑">🥑</span>
                    <span class="emoji" data-emoji="🍷">🍷</span>
                    <span class="emoji" data-emoji="🍞">🍞</span>
                    <span class="emoji" data-emoji="🥐">🥐</span>
                    <span class="emoji" data-emoji="🧀">🧀</span>
                    <span class="emoji" data-emoji="🍳">🍳</span>
                    <span class="emoji" data-emoji="🥗">🥗</span>
                    <span class="emoji" data-emoji="🍝">🍝</span>
                    <span class="emoji" data-emoji="🍦">🍦</span>
                    <span class="emoji" data-emoji="🍩">🍩</span>
                </div>
                <div class="emoji-group" id="smileys-emojis" style="display: none;">
                    <span class="emoji" data-emoji="😊">😊</span>
                    <span class="emoji" data-emoji="😂">😂</span>
                    <span class="emoji" data-emoji="😍">😍</span>
                    <span class="emoji" data-emoji="🤔">🤔</span>
                    <span class="emoji" data-emoji="😎">😎</span>
                    <span class="emoji" data-emoji="😋">😋</span>
                    <span class="emoji" data-emoji="🤗">🤗</span>
                    <span class="emoji" data-emoji="😉">😉</span>
                    <span class="emoji" data-emoji="😴">😴</span>
                    <span class="emoji" data-emoji="🤤">🤤</span>
                    <span class="emoji" data-emoji="😇">😇</span>
                    <span class="emoji" data-emoji="🥰">🥰</span>
                </div>
                <div class="emoji-group" id="symbols-emojis" style="display: none;">
                    <span class="emoji" data-emoji="❤️">❤️</span>
                    <span class="emoji" data-emoji="👍">👍</span>
                    <span class="emoji" data-emoji="👏">👏</span>
                    <span class="emoji" data-emoji="🎉">🎉</span>
                    <span class="emoji" data-emoji="🔥">🔥</span>
                    <span class="emoji" data-emoji="✨">✨</span>
                    <span class="emoji" data-emoji="⭐">⭐</span>
                    <span class="emoji" data-emoji="💯">💯</span>
                    <span class="emoji" data-emoji="💪">💪</span>
                    <span class="emoji" data-emoji="🙌">🙌</span>
                    <span class="emoji" data-emoji="👌">👌</span>
                    <span class="emoji" data-emoji="✌️">✌️</span>
                </div>
            </div>
        </div>
        
        <!-- Reactions Menu -->
        <div class="reactions-menu" id="reactions-menu">
            <div class="reaction-item" data-reaction="🍕">
                <span class="reaction-emoji">🍕</span>
            </div>
            <div class="reaction-item" data-reaction="🍔">
                <span class="reaction-emoji">🍔</span>
            </div>
            <div class="reaction-item" data-reaction="😋">
                <span class="reaction-emoji">😋</span>
            </div>
            <div class="reaction-item" data-reaction="👍">
                <span class="reaction-emoji">👍</span>
            </div>
            <div class="reaction-item" data-reaction="❤️">
                <span class="reaction-emoji">❤️</span>
            </div>
            <div class="reaction-item" data-reaction="😂">
                <span class="reaction-emoji">😂</span>
            </div>
            <div class="reaction-item" data-reaction="🔥">
                <span class="reaction-emoji">🔥</span>
            </div>
            <div class="reaction-item" data-reaction="🎉">
                <span class="reaction-emoji">🎉</span>
            </div>
        </div>
    </main>
    
    <!-- Mobile Conversations Toggle -->
    <div class="mobile-conversations-toggle" id="mobile-conversations-toggle">
        <i class="fas fa-comments"></i>
    </div>
    
    <script>
        // Enhanced JavaScript for improved functionality with Fixed Sidebar Responsiveness
        
        // Global variables
        let currentReceiverId = null;
        let selectedMessageId = null;
        let selectedFile = null;
        let lastMessageTimestamp = 0;
        let messagePollingInterval = null;
        let selectedReaction = null;
        let activeConversationUserId = null;
        let activeConversationUserName = null;
        let activeConversationUserImage = null;
        
        // DOM Elements
        const messagesContainer = document.getElementById('messages-container');
        const messageInput = document.getElementById('message-input');
        const sendMessageBtn = document.getElementById('send-message-btn');
        const searchUsersInput = document.getElementById('search-users-input');
        const usersList = document.getElementById('users-list');
        const newMessageBtn = document.getElementById('new-message-btn');
        const newMessageModal = document.getElementById('new-message-modal');
        const messageImageInput = document.getElementById('message-image');
        const imagePreview = document.getElementById('image-preview');
        const previewImage = document.getElementById('preview-image');
        const removeImageBtn = document.getElementById('remove-image-btn');
        const showEmojiBtn = document.getElementById('show-emoji-btn');
        const closeEmojiBtn = document.getElementById('close-emoji-btn');
        const reactionsMenu = document.getElementById('reactions-menu');
        const messageArea = document.getElementById('message-area');
        const conversationItems = document.querySelectorAll('.conversation-item');
        const noMessageSelected = document.getElementById('no-message-selected');
        const activeConversation = document.getElementById('active-conversation');
        const mobileToggle = document.getElementById('mobile-conversations-toggle');
        const mobileOverlay = document.getElementById('mobile-overlay');
        const conversationsList = document.getElementById('conversations-list');
        
        // Initialize the chat interface
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize sidebar functionality
            initializeSidebar();
            
            // Initialize mobile conversations toggle
            initializeMobileConversationsToggle();
            
            // Initialize online status
            updateUserStatus();
            
            // Initialize emoji picker functionality
            initEmojiPicker();
            
            // Auto-resize textarea
            messageInput.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            });
            
            // Mobile conversations toggle
            if (mobileToggle) {
                mobileToggle.addEventListener('click', function() {
                    conversationsList.classList.add('mobile-open');
                    mobileOverlay.classList.add('active');
                });
            }
            
            if (mobileOverlay) {
                mobileOverlay.addEventListener('click', function() {
                    conversationsList.classList.remove('mobile-open');
                    mobileOverlay.classList.remove('active');
                });
            }
            
            // Set up emoji button click handler
            if (showEmojiBtn) {
                showEmojiBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    toggleEmojiPicker();
                });
            }
            
            // Set up close emoji button click handler
            if (closeEmojiBtn) {
                closeEmojiBtn.addEventListener('click', function() {
                    const emojiPicker = document.getElementById('emoji-picker');
                    if (emojiPicker) {
                        emojiPicker.classList.remove('active');
                    }
                });
            }
            
            // FIXED: Close emoji picker and reactions menu when clicking outside
            document.addEventListener('click', function(e) {
                const emojiPicker = document.getElementById('emoji-picker');
                
                // Close emoji picker if clicking outside
                if (emojiPicker && !emojiPicker.contains(e.target) && 
                    !showEmojiBtn.contains(e.target)) {
                    emojiPicker.classList.remove('active');
                }
                
                // FIXED: Close reactions menu if clicking outside
                if (reactionsMenu && !reactionsMenu.contains(e.target) && 
                    !e.target.classList.contains('reaction-hover-btn') && 
                    !e.target.closest('.reaction-hover-btn')) {
                    reactionsMenu.classList.remove('active');
                    reactionsMenu.style.display = 'none';
                }
            });
            
            // Set up conversation item click handlers
            conversationItems.forEach(item => {
                item.addEventListener('click', function() {
                    // Remove active class from all items
                    conversationItems.forEach(i => i.classList.remove('active'));
                    
                    // Add active class to clicked item
                    this.classList.add('active');
                    
                    // Get user data from data attributes
                    const userId = this.dataset.userId;
                    const userName = this.dataset.userName;
                    const userImage = this.dataset.userImage;
                    
                    if (userId) {
                        // Load active conversation with user data
                        loadActiveConversation(userId, userName, userImage);
                        
                        // Close mobile conversations list
                        if (window.innerWidth <= 767) {
                            conversationsList.classList.remove('mobile-open');
                            mobileOverlay.classList.remove('active');
                        }
                    } else {
                        console.error('No user ID found for this conversation');
                    }
                });
            });
            
            // Set up message input handlers
            messageInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });
            
            // Send message button click handler
            sendMessageBtn.addEventListener('click', sendMessage);
            
            // Image upload handler
            messageImageInput.addEventListener('change', handleImageUpload);
            
            // Remove image button handler
            removeImageBtn.addEventListener('click', removeImage);
            
            // New message button handler
            newMessageBtn.addEventListener('click', openNewMessageModal);
            
            // Back to messages button handler
            const backToMessagesBtn = document.getElementById('back-to-messages-btn');
            if (backToMessagesBtn) {
                backToMessagesBtn.addEventListener('click', closeModal);
            }
            
            // Close modal when clicking outside
            window.addEventListener('click', function(e) {
                if (e.target === newMessageModal) {
                    closeModal();
                }
            });
            
            // Search users input handler
            searchUsersInput.addEventListener('input', debounce(searchUsers, 300));
            
            // Add event listener for mobile new message button
            const mobileBtn = document.getElementById('new-message-mobile-btn');
            if (mobileBtn) {
                mobileBtn.addEventListener('click', function() {
                    openNewMessageModal();
                });
            }
            
            // Emoji category buttons
            document.querySelectorAll('.emoji-category').forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all categories
                    document.querySelectorAll('.emoji-category').forEach(btn => {
                        btn.classList.remove('active');
                    });
                    
                    // Add active class to clicked category
                    this.classList.add('active');
                    
                    // Hide all emoji groups
                    document.querySelectorAll('.emoji-group').forEach(group => {
                        group.style.display = 'none';
                    });
                    
                    // Show selected emoji group
                    const category = this.dataset.category;
                    const emojiGroup = document.getElementById(`${category}-emojis`);
                    if (emojiGroup) {
                        emojiGroup.style.display = 'flex';
                    }
                });
            });
            
            // FIXED: Reaction item click handlers
            document.querySelectorAll('.reaction-item').forEach(item => {
                item.addEventListener('click', function(event) {
                    if (selectedMessageId) {
                        addReaction(selectedMessageId, this.dataset.reaction);
                    } else {
                        // Store reaction to add to the next sent message
                        selectedReaction = this.dataset.reaction;
                    }
                    
                    // FIXED: Hide reactions menu after selection
                    reactionsMenu.classList.remove('active');
                    reactionsMenu.style.display = 'none';
                    event.stopPropagation();
                });
            });
            
            // Handle conversation search functionality
            const searchInput = document.getElementById('search-conversations');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    
                    conversationItems.forEach(item => {
                        const conversationName = item.querySelector('.conversation-name').textContent.toLowerCase();
                        const conversationMessage = item.querySelector('.conversation-last-message').textContent.toLowerCase();
                        
                        if (conversationName.includes(searchTerm) || conversationMessage.includes(searchTerm)) {
                            item.style.display = 'flex';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            }
        });
        
        // FIXED: Initialize Sidebar Functionality
        function initializeSidebar() {
            const sidebarToggle = document.getElementById('sidebar-toggle');
            const sidebar = document.querySelector('.sidebar');
            const sidebarOverlay = document.getElementById('sidebar-overlay');
            
            if (sidebarToggle && sidebar && sidebarOverlay) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                    sidebarOverlay.classList.toggle('active');
                });
                
                sidebarOverlay.addEventListener('click', function() {
                    sidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                });
                
                // Close sidebar when clicking on sidebar links (mobile)
                const sidebarLinks = sidebar.querySelectorAll('a');
                sidebarLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        if (window.innerWidth <= 991) {
                            sidebar.classList.remove('active');
                            sidebarOverlay.classList.remove('active');
                        }
                    });
                });
            }
        }
        
        // Initialize Mobile Conversations Toggle
        function initializeMobileConversationsToggle() {
            const conversationsList = document.getElementById('conversations-list');
            const mobileToggle = document.getElementById('mobile-conversations-toggle');
            const mobileOverlay = document.getElementById('mobile-overlay');
            const backButton = document.querySelector('.sidebar-toggle');
            
            if (mobileToggle && conversationsList && mobileOverlay) {
                // Toggle conversations list on mobile
                mobileToggle.addEventListener('click', function() {
                    conversationsList.classList.add('mobile-open');
                    mobileOverlay.classList.add('active');
                });
                
                // Close conversations list when clicking overlay
                mobileOverlay.addEventListener('click', function() {
                    conversationsList.classList.remove('mobile-open');
                    mobileOverlay.classList.remove('active');
                });
                
                // Close conversations list when clicking back button (at small screen sizes)
                if (backButton) {
                    backButton.addEventListener('click', function() {
                        if (window.innerWidth <= 767) {
                            conversationsList.classList.remove('mobile-open');
                            mobileOverlay.classList.remove('active');
                        }
                    });
                }
                
                // Close conversation list when a conversation is selected
                const conversationItems = document.querySelectorAll('.conversation-item');
                conversationItems.forEach(item => {
                    item.addEventListener('click', function() {
                        if (window.innerWidth <= 767) {
                            setTimeout(() => {
                                conversationsList.classList.remove('mobile-open');
                                mobileOverlay.classList.remove('active');
                            }, 100);
                        }
                    });
                });
            }
            
            // Re-initialize on window resize to handle orientation changes
            window.addEventListener('resize', function() {
                if (window.innerWidth > 767) {
                    conversationsList.classList.remove('mobile-open');
                    mobileOverlay.classList.remove('active');
                }
            });
        }
        
        // Load active conversation
        function loadActiveConversation(userId, userName, userImage) {
            activeConversationUserId = userId;
            activeConversationUserName = userName;
            activeConversationUserImage = userImage || 'uploads/profiles/default.png';
            
            // Update active conversation header
            const headerNameElement = document.getElementById('active-conversation-name');
            headerNameElement.textContent = userName;
            
            // Update header avatar if it exists
            const headerAvatarImg = document.querySelector('.conversation-header-avatar img');
            if (headerAvatarImg) {
                headerAvatarImg.src = activeConversationUserImage;
                headerAvatarImg.alt = userName;
            }
            
            // Show active conversation and hide no message selected
            document.getElementById('no-message-selected').style.display = 'none';
            document.getElementById('active-conversation').style.display = 'flex';
            
            // Ensure message input is visible and focused
            document.querySelector('.message-input-container').style.display = 'flex';
            setTimeout(() => messageInput.focus(), 100);
            
            // Clear messages container
            messagesContainer.innerHTML = '';
            
            // Load messages
            loadMessages();
            
            // Start polling for new messages
            startMessagePolling();
            
            // Update conversation list to highlight active conversation
            const conversationItems = document.querySelectorAll('.conversation-item');
            conversationItems.forEach(item => {
                if (item.dataset.userId == userId) {
                    item.classList.add('active');
                } else {
                    item.classList.remove('active');
                }
            });
        }
        
        // Start message polling
        function startMessagePolling() {
            // Clear any existing polling interval
            if (messagePollingInterval) {
                clearInterval(messagePollingInterval);
            }
            
            // Set up polling for new messages
            messagePollingInterval = setInterval(function() {
                if (lastMessageTimestamp > 0) {
                    loadMessages(lastMessageTimestamp);
                }
            }, 3000); // Poll every 3 seconds
        }
        
        // Load messages for selected conversation
        function loadMessages(lastTimestamp = 0) {
            if (!activeConversationUserId) return;
            
            // Show loading indicator on first load
            if (lastTimestamp === 0) {
                messagesContainer.innerHTML = '<div class="loading-messages"><i class="fas fa-spinner fa-spin"></i> Loading messages...</div>';
                // Reset last message timestamp
                lastMessageTimestamp = 0;
            }
            
            const formData = new FormData();
            formData.append('action', 'get_messages');
            formData.append('receiver_id', activeConversationUserId);
            if (lastTimestamp > 0) {
                formData.append('last_timestamp', lastTimestamp);
            }
            
            fetch('chat.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // If this is the first load, clear the container
                    if (lastTimestamp === 0) {
                        messagesContainer.innerHTML = '';
                    }
                    
                    // Add messages
                    if (data.messages && data.messages.length > 0) {
                        data.messages.forEach(message => {
                            appendMessage(message);
                        });
                        
                        // Scroll to bottom of messages container
                        messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    } else if (lastTimestamp === 0) {
                        // No messages yet
                        messagesContainer.innerHTML = '<div class="no-messages"><i class="fas fa-comments"></i><br>No messages yet. Start the conversation!</div>';
                    }
                } else {
                    console.error('Error loading messages:', data.message);
                }
            })
            .catch(error => {
                console.error('Error loading messages:', error);
            });
        }
        
        // Append a message to the messages container
        function appendMessage(message) {
            const isSent = message.SENDER_ID == <?php echo $_SESSION['user_id']; ?>;
            const messageClass = isSent ? 'message-sent' : 'message-received';
            const messageTime = new Date(message.SENT_AT);
            const formattedTime = messageTime.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            
            // Get user profile image
            let profileImage = isSent ? 
                '<?php echo !empty($_SESSION["profile_image"]) ? $_SESSION["profile_image"] : "uploads/profiles/default.png"; ?>' : 
                activeConversationUserImage;
                
            // Default image if none available
            if (!profileImage || profileImage.trim() === '') {
                profileImage = 'uploads/profiles/default.png';
            }
            
            // Create message element
            const messageElement = document.createElement('div');
            messageElement.className = `message ${messageClass}`;
            messageElement.dataset.messageId = message.MESSAGE_ID;
            
            // Create avatar element
            const avatarElement = document.createElement('div');
            avatarElement.className = 'message-avatar';
            avatarElement.innerHTML = `<img src="${profileImage}" alt="User avatar">`;
            
            // Create message content wrapper
            const contentWrapper = document.createElement('div');
            contentWrapper.className = 'message-content';
            
            // Create message bubble
            const bubbleElement = document.createElement('div');
            bubbleElement.className = 'message-bubble';
            
            // Create message text element if content exists
            if (message.CONTENT) {
                const textElement = document.createElement('div');
                textElement.className = 'message-text';
                textElement.textContent = message.CONTENT;
                bubbleElement.appendChild(textElement);
            }
            
            // Add message image if present
            if (message.IMAGE_URL) {
                const imageContainer = document.createElement('div');
                imageContainer.className = 'message-image';
                imageContainer.innerHTML = `<img src="${message.IMAGE_URL}" alt="Message image" onclick="openFullscreenImage('${message.IMAGE_URL}')">`;
                
                // Add reaction hover button for image messages
                const reactionBtn = document.createElement('button');
                reactionBtn.className = 'reaction-hover-btn';
                reactionBtn.innerHTML = '<i class="fas fa-smile"></i>';
                reactionBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    showReactionsMenu(message.MESSAGE_ID, this);
                });
                
                imageContainer.appendChild(reactionBtn);
                bubbleElement.appendChild(imageContainer);
            }
            
            // Add reaction hover button to bubble (only if there's text content)
            if (message.CONTENT) {
                const reactionBtn = document.createElement('button');
                reactionBtn.className = 'reaction-hover-btn';
                reactionBtn.innerHTML = '<i class="fas fa-smile"></i>';
                reactionBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    showReactionsMenu(message.MESSAGE_ID, this);
                });
                
                bubbleElement.appendChild(reactionBtn);
            }
            
            contentWrapper.appendChild(bubbleElement);
            
            // Add message time
            const timeElement = document.createElement('div');
            timeElement.className = 'message-time';
            timeElement.textContent = formattedTime;
            contentWrapper.appendChild(timeElement);
            
            // Add reactions if present
            if (message.REACTIONS) {
                try {
                    const reactions = typeof message.REACTIONS === 'string' ? 
                        JSON.parse(message.REACTIONS) : message.REACTIONS;
                    
                    if (reactions && Object.keys(reactions).length > 0) {
                        const reactionsContainer = document.createElement('div');
                        reactionsContainer.className = 'message-reactions';
                        
                        for (const [reaction, count] of Object.entries(reactions)) {
                            const reactionElement = document.createElement('span');
                            reactionElement.className = 'message-reaction';
                            reactionElement.dataset.reaction = reaction;
                            reactionElement.innerHTML = `${reaction} <span class="message-reaction-count">${count}</span>`;
                            reactionElement.onclick = () => addReaction(message.MESSAGE_ID, reaction);
                            reactionsContainer.appendChild(reactionElement);
                        }
                        
                        contentWrapper.appendChild(reactionsContainer);
                    }
                } catch (e) {
                    console.error('Error parsing reactions:', e);
                }
            }
            
            // Append avatar and content to message element
            messageElement.appendChild(avatarElement);
            messageElement.appendChild(contentWrapper);
            messagesContainer.appendChild(messageElement);
            
            // Update last message timestamp
            try {
                const timestamp = new Date(message.SENT_AT).getTime();
                if (timestamp > lastMessageTimestamp) {
                    lastMessageTimestamp = timestamp;
                }
            } catch (e) {
                console.error('Error parsing timestamp:', e);
            }
        }
        
        // FIXED: Send a message function
        function sendMessage() {
            if (!activeConversationUserId) return;
            
            const content = messageInput.value.trim();
            if (!content && !selectedFile) return;
            
            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('receiver_id', activeConversationUserId);
            
            if (content) {
                formData.append('content', content);
            }
            
            // FIXED: Properly append the file
            if (selectedFile) {
                formData.append('image', selectedFile);
            }
            
            // Add reaction if one is selected
            if (selectedReaction) {
                formData.append('reaction', selectedReaction);
                selectedReaction = null;
            }
            
            // Disable send button while sending
            sendMessageBtn.disabled = true;
            sendMessageBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
            fetch('chat.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Clear input and image preview
                    messageInput.value = '';
                    messageInput.style.height = 'auto';
                    removeImage();
                    
                    // Fetch latest messages including the one we just sent
                    loadMessages();
                } else {
                    alert('Error sending message: ' + data.message);
                }
                
                // Re-enable send button
                sendMessageBtn.disabled = false;
                sendMessageBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';
            })
            .catch(error => {
                console.error('Error sending message:', error);
                sendMessageBtn.disabled = false;
                sendMessageBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';
                alert('Error sending message. Please try again.');
            });
        }
        
        // Handle image upload with improved preview
        function handleImageUpload(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            // Check file type
            const validImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!validImageTypes.includes(file.type)) {
                alert('Please select a valid image file (JPEG, PNG, GIF, WEBP)');
                return;
            }
            
            // Check file size (5MB max)
            if (file.size > 5 * 1024 * 1024) {
                alert('Image size should be less than 5MB');
                return;
            }
            
            // Store selected file
            selectedFile = file;
            
            // Show image preview with animation
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImage.src = e.target.result;
                imagePreview.style.display = 'block';
                imagePreview.style.opacity = '0';
                setTimeout(() => {
                    imagePreview.style.opacity = '1';
                }, 10);
            };
            reader.readAsDataURL(file);
            
            // Focus back to message input
            messageInput.focus();
        }
        
        // Remove selected image
        function removeImage() {
            selectedFile = null;
            messageImageInput.value = '';
            imagePreview.style.display = 'none';
        }
        
        // Add or toggle a reaction to a message
        function addReaction(messageId, reaction) {
            const formData = new FormData();
            formData.append('action', 'add_reaction');
            formData.append('message_id', messageId);
            formData.append('reaction', reaction);
            
            fetch('chat.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the reactions display
                    const message = document.querySelector(`.message[data-message-id="${messageId}"]`);
                    if (!message) return;
                    
                    let reactionsContainer = message.querySelector('.message-reactions');
                    
                    if (!reactionsContainer) {
                        reactionsContainer = document.createElement('div');
                        reactionsContainer.className = 'message-reactions';
                        message.querySelector('.message-content').appendChild(reactionsContainer);
                    }
                    
                    // Clear existing reactions
                    reactionsContainer.innerHTML = '';
                    
                    // Add updated reactions
                    const reactions = data.reactions;
                    for (const [reaction, count] of Object.entries(reactions)) {
                        const reactionElement = document.createElement('span');
                        reactionElement.className = 'message-reaction';
                        reactionElement.dataset.reaction = reaction;
                        reactionElement.innerHTML = `${reaction} <span class="message-reaction-count">${count}</span>`;
                        
                        // Add click handler to toggle reaction
                        reactionElement.addEventListener('click', function() {
                            addReaction(messageId, reaction);
                        });
                        
                        reactionsContainer.appendChild(reactionElement);
                    }
                    
                    // Hide reactions menu
                    reactionsMenu.classList.remove('active');
                    reactionsMenu.style.display = 'none';
                } else {
                    console.error('Error adding reaction:', data.message);
                }
            })
            .catch(error => {
                console.error('Error adding reaction:', error);
            });
        }
        
        // FIXED: Create and show reactions menu dynamically with enhanced animations
        function showReactionsMenu(messageId, buttonElement) {
            selectedMessageId = messageId;
            
            // Close emoji picker if open
            document.getElementById('emoji-picker').classList.remove('active');
            
            // Position the reactions menu
            const rect = buttonElement.getBoundingClientRect();
            reactionsMenu.style.position = 'fixed';
            reactionsMenu.style.top = `${rect.top - reactionsMenu.offsetHeight - 10}px`;
            reactionsMenu.style.left = `${rect.left - (reactionsMenu.offsetWidth / 2) + (rect.width / 2)}px`;
            
            // Ensure menu stays within viewport
            const menuRect = reactionsMenu.getBoundingClientRect();
            if (menuRect.left < 10) {
                reactionsMenu.style.left = '10px';
            }
            if (menuRect.right > window.innerWidth - 10) {
                reactionsMenu.style.left = `${window.innerWidth - menuRect.width - 10}px`;
            }
            
            // Show reactions menu with animation
            reactionsMenu.style.opacity = '0';
            reactionsMenu.style.transform = 'translateY(10px) scale(0.95)';
            reactionsMenu.style.display = 'flex';
            
            setTimeout(() => {
                reactionsMenu.classList.add('active');
                reactionsMenu.style.opacity = '1';
                reactionsMenu.style.transform = 'translateY(0) scale(1)';
            }, 10);
            
            // Add bounce effect to reaction items
            const reactionItems = reactionsMenu.querySelectorAll('.reaction-item');
            reactionItems.forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'scale(0.8)';
                
                setTimeout(() => {
                    item.style.opacity = '1';
                    item.style.transform = 'scale(1)';
                }, 50 + (index * 30));
            });
        }
        
        function toggleEmojiPicker() {
            const emojiPicker = document.getElementById('emoji-picker');
            if (!emojiPicker) return;
            
            emojiPicker.classList.toggle('active');
            
            // Hide reactions menu if open
            if (reactionsMenu) {
                reactionsMenu.classList.remove('active');
                reactionsMenu.style.display = 'none';
            }
        }
        
        function initEmojiPicker() {
            // Add click handlers for all emojis
            document.querySelectorAll('.emoji').forEach(emoji => {
                emoji.addEventListener('click', function() {
                    const emojiChar = this.dataset.emoji;
                    insertEmojiIntoMessage(emojiChar);
                    const emojiPicker = document.getElementById('emoji-picker');
                    if (emojiPicker) {
                        emojiPicker.classList.remove('active');
                    }
                });
            });
        }
        
        function insertEmojiIntoMessage(emoji) {
            if (!messageInput) return;
            
            // Get current cursor position
            const cursorPos = messageInput.selectionStart;
            const textBefore = messageInput.value.substring(0, cursorPos);
            const textAfter = messageInput.value.substring(cursorPos, messageInput.value.length);
            
            // Insert emoji at cursor position
            messageInput.value = textBefore + emoji + textAfter;
            
            // Move cursor after inserted emoji
            messageInput.selectionStart = cursorPos + emoji.length;
            messageInput.selectionEnd = cursorPos + emoji.length;
            
            // Focus back on the input
            messageInput.focus();
            
            // Trigger input event to resize textarea
            messageInput.dispatchEvent(new Event('input'));
        }
        
        // Open new message modal
        function openNewMessageModal() {
            newMessageModal.style.display = 'flex';
            searchUsersInput.focus();
            loadUsers();
        }
        
        // Close modal
        function closeModal() {
            newMessageModal.style.display = 'none';
            searchUsersInput.value = '';
        }
        
        // Load users for new message
        function loadUsers(searchTerm = '') {
            usersList.innerHTML = '<div class="loading-users"><i class="fas fa-spinner fa-spin"></i> Loading users...</div>';
            
            const formData = new FormData();
            formData.append('action', 'get_users');
            if (searchTerm) {
                formData.append('search', searchTerm);
            }
            
            fetch('chat.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderUsersList(data.users);
                } else {
                    usersList.innerHTML = '<div class="error-loading">Error loading users</div>';
                    console.error('Error loading users:', data.message);
                }
            })
            .catch(error => {
                usersList.innerHTML = '<div class="error-loading">Error loading users</div>';
                console.error('Error loading users:', error);
            });
        }
        
        // Search users
        function searchUsers() {
            const searchTerm = searchUsersInput.value.trim();
            loadUsers(searchTerm);
        }
        
        // Render users list
        function renderUsersList(users) {
            if (!users || users.length === 0) {
                usersList.innerHTML = '<div class="no-users-found">No users found</div>';
                return;
            }
            
            usersList.innerHTML = '';
            
            users.forEach(user => {
                const userId = user.USER_ID || '';
                const userName = user.NAME || 'Unknown User';
                const profileImage = user.PROFILE_IMAGE && user.PROFILE_IMAGE.trim() !== '' ? 
                    user.PROFILE_IMAGE : 'uploads/profiles/default.png';
                
                const userItem = document.createElement('div');
                userItem.className = 'user-item';
                userItem.dataset.userId = userId;
                
                userItem.innerHTML = `
                    <div class="user-avatar">
                        <img src="${profileImage}" alt="${userName}">
                    </div>
                    <div class="user-info">
                        <div class="user-name">${userName}</div>
                        <div class="user-status">Click to start chatting</div>
                    </div>
                `;
                
                userItem.addEventListener('click', function() {
                    if (userId) {
                        startNewConversation(userId);
                    }
                });
                
                usersList.appendChild(userItem);
            });
        }
        
        // Start a new conversation
        function startNewConversation(userId) {
            closeModal();
            
            // Check if conversation already exists
            const existingConversation = document.querySelector(`.conversation-item[data-user-id="${userId}"]`);
            if (existingConversation) {
                existingConversation.click();
                return;
            }
            
            // Load the conversation directly
            loadConversation(userId);
        }
        
        // Load conversation with a user
        function loadConversation(userId) {
            // Fetch user details first
            const formData = new FormData();
            formData.append('action', 'get_user_details');
            formData.append('user_id', userId);
            
            fetch('chat.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.user) {
                    const user = data.user;
                    // Load active conversation with user data
                    loadActiveConversation(user.USER_ID, user.NAME, user.PROFILE_IMAGE || 'uploads/profiles/default.png');
                } else {
                    console.error('Error loading user details:', data.message || 'Unknown error');
                }
            })
            .catch(error => {
                console.error('Error loading conversation:', error);
            });
        }
        
        // Helper function: Debounce function to limit how often a function can be called
        function debounce(func, delay) {
            let timeout;
            return function() {
                const context = this;
                const args = arguments;
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(context, args), delay);
            };
        }
        
        // Open image in fullscreen
        function openFullscreenImage(src) {
            const fullscreenDiv = document.createElement('div');
            fullscreenDiv.className = 'fullscreen-image';
            fullscreenDiv.innerHTML = `<img src="${src}" alt="Fullscreen Image">`;
            fullscreenDiv.addEventListener('click', function() {
                document.body.removeChild(fullscreenDiv);
            });
            document.body.appendChild(fullscreenDiv);
        }
        
        // Function to update user online status
        function updateUserStatus() {
            // Fetch online users
            const formData = new FormData();
            formData.append('action', 'get_online_users');
            
            fetch('chat.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update status for all conversation items
                    document.querySelectorAll('.conversation-item').forEach(item => {
                        const userId = item.dataset.userId;
                        const statusElement = item.querySelector('.conversation-status');
                        
                        if (statusElement) {
                            if (data.online_users && data.online_users.includes(parseInt(userId))) {
                                statusElement.innerHTML = '<i class="fas fa-circle status-indicator status-online"></i> <span class="status-text">Online</span>';
                                statusElement.classList.add('online');
                            } else {
                                const lastSeen = data.last_seen && data.last_seen[userId] ? data.last_seen[userId] : 'a while ago';
                                statusElement.innerHTML = '<i class="fas fa-circle status-indicator status-offline"></i> <span class="status-text">Last seen ' + lastSeen + '</span>';
                                statusElement.classList.remove('online');
                            }
                        }
                    });
                    
                    // Update active conversation status if open
                    if (activeConversationUserId) {
                        const statusElement = document.querySelector('.conversation-header-info .conversation-status');
                        if (statusElement) {
                            if (data.online_users && data.online_users.includes(parseInt(activeConversationUserId))) {
                                statusElement.innerHTML = '<i class="fas fa-circle status-indicator status-online"></i> <span class="status-text">Online</span>';
                                statusElement.classList.add('online');
                            } else {
                                const lastSeen = data.last_seen && data.last_seen[activeConversationUserId] ? data.last_seen[activeConversationUserId] : 'a while ago';
                                statusElement.innerHTML = '<i class="fas fa-circle status-indicator status-offline"></i> <span class="status-text">Last seen ' + lastSeen + '</span>';
                                statusElement.classList.remove('online');
                            }
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error fetching online status:', error);
            });
            
            // Update current user's online status
            updateUserActivity();
        }
        
        // Function to update the current user's activity timestamp
        function updateUserActivity() {
            const formData = new FormData();
            formData.append('action', 'update_user_activity');
            
            fetch('chat.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    console.error('Failed to update user activity:', data.message);
                }
            })
            .catch(error => {
                console.error('Error updating user activity:', error);
            });
        }

        // Update user status every 30 seconds
        setInterval(updateUserStatus, 30000);
        
        // FIXED: Handle window resize for mobile responsiveness
        window.addEventListener('resize', function() {
            // Close mobile conversations list when resizing to desktop
            if (window.innerWidth > 767) {
                conversationsList.classList.remove('mobile-open');
                mobileOverlay.classList.remove('active');
            }
            
            // Close sidebar when resizing to desktop
            if (window.innerWidth > 991) {
                const sidebar = document.querySelector('.sidebar');
                const sidebarOverlay = document.getElementById('sidebar-overlay');
                if (sidebar && sidebarOverlay) {
                    sidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                }
            }
        });
    </script>
</body>
</html>
