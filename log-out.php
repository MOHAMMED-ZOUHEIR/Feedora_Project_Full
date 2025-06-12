<?php
// Start the session
session_start();

// Update last logout timestamp in database if user is logged in
if (isset($_SESSION['user_id'])) {
    // Connect to the database
    require_once 'config/config.php';
    
    $user_id = $_SESSION['user_id'];
    $query = "UPDATE USERS SET LAST_LOGOUT_AT = CURRENT_TIMESTAMP WHERE USER_ID = ?";
    
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user_id]);
    } catch (PDOException $e) {
        // Log error but continue with logout process
        error_log("Logout database update failed: " . $e->getMessage());
    }
}

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to sign-in page
header("Location: sign-in.php");
exit();
?>