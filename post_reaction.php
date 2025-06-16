<?php
// Include the database connection script
require_once 'config/config.php';

// Start the session to manage user data
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}



// Set content type to JSON
header('Content-Type: application/json');

$userId = $_SESSION['user_id'];
$response = ['success' => false, 'message' => ''];

// Handle different actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    switch ($_POST['action']) {
        case 'add_reaction':
            addReaction($pdo, $userId, $response);
            break;
            
        case 'remove_reaction':
            removeReaction($pdo, $userId, $response);
            break;
            
        case 'get_reactions':
            getReactions($pdo, $response);
            break;
            
        case 'get_reaction_users':
            getReactionUsers($pdo, $response);
            break;
            
        default:
            $response['message'] = 'Invalid action';
    }
} else {
    $response['message'] = 'Invalid request method';
}

echo json_encode($response);

// Function to add or update a reaction
function addReaction($pdo, $userId, &$response) {
    if (!isset($_POST['post_id']) || !isset($_POST['reaction_type'])) {
        $response['message'] = 'Missing required parameters';
        return;
    }
    
    $postId = intval($_POST['post_id']);
    $reactionType = $_POST['reaction_type'];
    
    // Define valid reaction types and their emojis
    $validReactions = [
        'yummy' => '🍔',
        'delicious' => '🍕',
        'tasty' => '🍰',
        'love' => '🍲',
        'amazing' => '🍗'
    ];
    
    if (!array_key_exists($reactionType, $validReactions)) {
        $response['message'] = 'Invalid reaction type';
        return;
    }
    
    $reactionEmoji = $validReactions[$reactionType];
    
    try {
        // Check if post exists
        $postCheck = $pdo->prepare("SELECT POSTS_ID FROM POSTS WHERE POSTS_ID = ?");
        $postCheck->execute([$postId]);
        if (!$postCheck->fetch()) {
            $response['message'] = 'Post not found';
            return;
        }
        
        // Check if user already reacted to this post
        $existingReaction = $pdo->prepare("SELECT REACTION_ID, REACTION_TYPE FROM POST_REACTIONS WHERE POST_ID = ? AND USER_ID = ?");
        $existingReaction->execute([$postId, $userId]);
        $existing = $existingReaction->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            if ($existing['REACTION_TYPE'] === $reactionType) {
                // Same reaction - remove it (toggle behavior)
                $deleteStmt = $pdo->prepare("DELETE FROM POST_REACTIONS WHERE POST_ID = ? AND USER_ID = ?");
                $deleteStmt->execute([$postId, $userId]);
                $response['message'] = 'Reaction removed';
                $response['action_type'] = 'removed';
            } else {
                // Different reaction - update it
                $updateStmt = $pdo->prepare("UPDATE POST_REACTIONS SET REACTION_TYPE = ?, REACTION_EMOJI = ?, UPDATED_AT = CURRENT_TIMESTAMP WHERE POST_ID = ? AND USER_ID = ?");
                $updateStmt->execute([$reactionType, $reactionEmoji, $postId, $userId]);
                $response['message'] = 'Reaction updated successfully';
                $response['action_type'] = 'updated';
                $response['old_reaction'] = $existing['REACTION_TYPE'];
            }
        } else {
            // Insert new reaction
            $insertStmt = $pdo->prepare("INSERT INTO POST_REACTIONS (POST_ID, USER_ID, REACTION_TYPE, REACTION_EMOJI) VALUES (?, ?, ?, ?)");
            $insertStmt->execute([$postId, $userId, $reactionType, $reactionEmoji]);
            $response['message'] = 'Reaction added successfully';
            $response['action_type'] = 'added';
        }
        
        // Get updated reaction counts
        $response['reaction_data'] = getPostReactionData($pdo, $postId, $userId);
        $response['success'] = true;
        
    } catch (PDOException $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
}

// Function to remove a reaction
function removeReaction($pdo, $userId, &$response) {
    if (!isset($_POST['post_id'])) {
        $response['message'] = 'Missing post ID';
        return;
    }
    
    $postId = intval($_POST['post_id']);
    
    try {
        $deleteStmt = $pdo->prepare("DELETE FROM POST_REACTIONS WHERE POST_ID = ? AND USER_ID = ?");
        $deleteStmt->execute([$postId, $userId]);
        
        if ($deleteStmt->rowCount() > 0) {
            $response['success'] = true;
            $response['message'] = 'Reaction removed successfully';
            $response['reaction_data'] = getPostReactionData($pdo, $postId, $userId);
        } else {
            $response['message'] = 'No reaction found to remove';
        }
        
    } catch (PDOException $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
}

// Function to get reactions for a post
function getReactions($pdo, &$response) {
    if (!isset($_POST['post_id'])) {
        $response['message'] = 'Missing post ID';
        return;
    }
    
    $postId = intval($_POST['post_id']);
    $userId = $_SESSION['user_id'];
    
    try {
        $response['reaction_data'] = getPostReactionData($pdo, $postId, $userId);
        $response['success'] = true;
        $response['message'] = 'Reactions retrieved successfully';
        
    } catch (PDOException $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
}

// Function to get users who reacted to a post
function getReactionUsers($pdo, &$response) {
    if (!isset($_POST['post_id'])) {
        $response['message'] = 'Missing post ID';
        return;
    }
    
    $postId = intval($_POST['post_id']);
    $reactionType = $_POST['reaction_type'] ?? null;
    
    try {
        $query = "SELECT pr.REACTION_TYPE, pr.REACTION_EMOJI, pr.CREATED_AT, u.NAME, u.PROFILE_IMAGE 
                  FROM POST_REACTIONS pr 
                  JOIN USERS u ON pr.USER_ID = u.USER_ID 
                  WHERE pr.POST_ID = ?";
        $params = [$postId];
        
        if ($reactionType) {
            $query .= " AND pr.REACTION_TYPE = ?";
            $params[] = $reactionType;
        }
        
        $query .= " ORDER BY pr.CREATED_AT DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $response['success'] = true;
        $response['users'] = $users;
        $response['message'] = 'Users retrieved successfully';
        
    } catch (PDOException $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
}

// Helper function to get post reaction data
function getPostReactionData($pdo, $postId, $userId) {
    // Get reaction counts using the view
    $countStmt = $pdo->prepare("SELECT * FROM POST_REACTION_COUNTS WHERE POSTS_ID = ?");
    $countStmt->execute([$postId]);
    $counts = $countStmt->fetch(PDO::FETCH_ASSOC);
    
    // If no data from view, get direct counts
    if (!$counts) {
        $directCountStmt = $pdo->prepare("
            SELECT 
                COALESCE(COUNT(*), 0) as total_reactions,
                COALESCE(SUM(CASE WHEN REACTION_TYPE = 'yummy' THEN 1 ELSE 0 END), 0) as yummy_count,
                COALESCE(SUM(CASE WHEN REACTION_TYPE = 'delicious' THEN 1 ELSE 0 END), 0) as delicious_count,
                COALESCE(SUM(CASE WHEN REACTION_TYPE = 'tasty' THEN 1 ELSE 0 END), 0) as tasty_count,
                COALESCE(SUM(CASE WHEN REACTION_TYPE = 'love' THEN 1 ELSE 0 END), 0) as love_count,
                COALESCE(SUM(CASE WHEN REACTION_TYPE = 'amazing' THEN 1 ELSE 0 END), 0) as amazing_count
            FROM POST_REACTIONS 
            WHERE POST_ID = ?
        ");
        $directCountStmt->execute([$postId]);
        $counts = $directCountStmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Get current user's reaction if any
    $userReactionStmt = $pdo->prepare("SELECT REACTION_TYPE FROM POST_REACTIONS WHERE POST_ID = ? AND USER_ID = ?");
    $userReactionStmt->execute([$postId, $userId]);
    $userReaction = $userReactionStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent reactors (limit to 5 for display)
    $recentReactorsStmt = $pdo->prepare("
        SELECT u.NAME, u.PROFILE_IMAGE, pr.REACTION_EMOJI, pr.REACTION_TYPE, pr.CREATED_AT 
        FROM POST_REACTIONS pr 
        JOIN USERS u ON pr.USER_ID = u.USER_ID 
        WHERE pr.POST_ID = ? 
        ORDER BY pr.CREATED_AT DESC 
        LIMIT 5
    ");
    $recentReactorsStmt->execute([$postId]);
    $recentReactors = $recentReactorsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'total_reactions' => intval($counts['total_reactions'] ?? 0),
        'yummy_count' => intval($counts['yummy_count'] ?? 0),
        'delicious_count' => intval($counts['delicious_count'] ?? 0),
        'tasty_count' => intval($counts['tasty_count'] ?? 0),
        'love_count' => intval($counts['love_count'] ?? 0),
        'amazing_count' => intval($counts['amazing_count'] ?? 0),
        'user_reaction' => $userReaction['REACTION_TYPE'] ?? null,
        'recent_reactors' => $recentReactors
    ];
}
?>