<?php
// comments.php - Enhanced Comment System Handler with Comment Reactions and Pagination
require_once 'config/config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ensure we return JSON
header('Content-Type: application/json');


$userId = $_SESSION['user_id'];
$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        // ADD NEW COMMENT
        if ($action === 'add_comment') {
            $postId = (int)$_POST['post_id'];
            $commentText = trim($_POST['comment_text']);
            
            if (empty($commentText)) {
                $response['message'] = 'Comment cannot be empty';
            } else {
                $stmt = $pdo->prepare("INSERT INTO COMMENTS (POST_ID, USER_ID, COMMENT_TEXT) VALUES (?, ?, ?)");
                $stmt->execute([$postId, $userId, $commentText]);
                
                $response['success'] = true;
                $response['message'] = 'Comment added successfully';
                $response['comment_id'] = $pdo->lastInsertId();
            }
        }
        
        // ADD OR UPDATE REACTION TO COMMENT
        elseif ($action === 'add_comment_reaction') {
            $commentId = (int)$_POST['comment_id'];
            $reactionType = $_POST['reaction_type'] ?? 'like';
            
            // Define reaction emojis
            $reactionEmojis = [
                'yummy' => '🍔',
                'delicious' => '🍕', 
                'tasty' => '🍰',
                'love' => '🍲',
                'amazing' => '🍗',
                'like' => '👍'
            ];
            $reactionEmoji = $reactionEmojis[$reactionType] ?? '👍';
            
            // Check if comment exists and user can react to it
            $checkStmt = $pdo->prepare("SELECT USER_ID FROM COMMENTS WHERE COMMENT_ID = ?");
            $checkStmt->execute([$commentId]);
            $comment = $checkStmt->fetch();
            
            if (!$comment) {
                $response['message'] = 'Comment not found';
            } else {
                // Check if user already reacted to this comment
                $existingReactionStmt = $pdo->prepare("SELECT REACTION_TYPE FROM COMMENT_REACTIONS WHERE COMMENT_ID = ? AND USER_ID = ?");
                $existingReactionStmt->execute([$commentId, $userId]);
                $existingReaction = $existingReactionStmt->fetch();
                
                if ($existingReaction && $existingReaction['REACTION_TYPE'] === $reactionType) {
                    // Remove reaction (toggle off)
                    $stmt = $pdo->prepare("DELETE FROM COMMENT_REACTIONS WHERE COMMENT_ID = ? AND USER_ID = ?");
                    $stmt->execute([$commentId, $userId]);
                    
                    $response['success'] = true;
                    $response['message'] = 'Reaction removed';
                    $response['action_type'] = 'removed';
                    $response['has_reaction'] = false;
                    $response['reaction_type'] = null;
                    $response['reaction_emoji'] = null;
                } else {
                    // Add or update reaction
                    if ($existingReaction) {
                        // Update existing reaction
                        $stmt = $pdo->prepare("UPDATE COMMENT_REACTIONS SET REACTION_TYPE = ?, REACTION_EMOJI = ?, UPDATED_AT = CURRENT_TIMESTAMP WHERE COMMENT_ID = ? AND USER_ID = ?");
                        $stmt->execute([$reactionType, $reactionEmoji, $commentId, $userId]);
                        $actionType = 'updated';
                    } else {
                        // Insert new reaction
                        $stmt = $pdo->prepare("INSERT INTO COMMENT_REACTIONS (COMMENT_ID, USER_ID, REACTION_TYPE, REACTION_EMOJI) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$commentId, $userId, $reactionType, $reactionEmoji]);
                        $actionType = 'added';
                    }
                    
                    $response['success'] = true;
                    $response['message'] = 'Reaction ' . $actionType;
                    $response['action_type'] = $actionType;
                    $response['has_reaction'] = true;
                    $response['reaction_type'] = $reactionType;
                    $response['reaction_emoji'] = $reactionEmoji;
                }
            }
        }
        
        // GET COMMENT REACTION USERS
        elseif ($action === 'get_comment_reaction_users') {
            $commentId = (int)$_POST['comment_id'];
            
            $stmt = $pdo->prepare("
                SELECT cr.*, u.NAME, u.PROFILE_IMAGE 
                FROM COMMENT_REACTIONS cr 
                JOIN USERS u ON cr.USER_ID = u.USER_ID 
                WHERE cr.COMMENT_ID = ? 
                ORDER BY cr.CREATED_AT DESC
            ");
            $stmt->execute([$commentId]);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response['success'] = true;
            $response['users'] = $users;
        }
        
        // GET COMMENTS FOR A POST WITH PAGINATION
        elseif ($action === 'get_comments') {
            $postId = (int)$_POST['post_id'];
            $offset = (int)($_POST['offset'] ?? 0);
            $limit = (int)($_POST['limit'] ?? 10);
            
            // Get total comment count
            $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM COMMENTS WHERE POST_ID = ? AND COMMENT_TEXT IS NOT NULL");
            $countStmt->execute([$postId]);
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Get paginated comments with user info and user's reaction status
            $commentsStmt = $pdo->prepare("
                SELECT c.*, u.NAME, u.PROFILE_IMAGE,
                       cr.REACTION_TYPE as user_reaction_type,
                       cr.REACTION_EMOJI as user_reaction_emoji,
                       crc.total_reactions,
                       (CASE WHEN cr.REACTION_ID IS NOT NULL THEN 1 ELSE 0 END) as has_user_reaction
                FROM COMMENTS c 
                JOIN USERS u ON c.USER_ID = u.USER_ID 
                LEFT JOIN COMMENT_REACTIONS cr ON c.COMMENT_ID = cr.COMMENT_ID AND cr.USER_ID = ?
                LEFT JOIN COMMENT_REACTION_COUNTS crc ON c.COMMENT_ID = crc.COMMENT_ID
                WHERE c.POST_ID = ? AND c.COMMENT_TEXT IS NOT NULL 
                ORDER BY c.CREATED_AT DESC
                LIMIT ? OFFSET ?
            ");
            $commentsStmt->execute([$userId, $postId, $limit, $offset]);
            $comments = $commentsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get post reactions (only on first load)
            $reactions = [];
            if ($offset === 0) {
                $reactionsStmt = $pdo->prepare("
                    SELECT pr.*, u.NAME, u.PROFILE_IMAGE 
                    FROM POST_REACTIONS pr 
                    JOIN USERS u ON pr.USER_ID = u.USER_ID 
                    WHERE pr.POST_ID = ?
                    ORDER BY pr.CREATED_AT DESC
                ");
                $reactionsStmt->execute([$postId]);
                $reactions = $reactionsStmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            $hasMore = ($offset + $limit) < $totalCount;
            $nextOffset = $offset + $limit;
            
            $response['success'] = true;
            $response['comments'] = $comments;
            $response['reactions'] = $reactions; // Only filled on first load
            $response['total_comments'] = $totalCount;
            $response['has_more'] = $hasMore;
            $response['next_offset'] = $nextOffset;
            $response['current_offset'] = $offset;
            $response['limit'] = $limit;
        }
        
        // GET COMMENT COUNT FOR A POST
        elseif ($action === 'get_comment_count') {
            $postId = (int)$_POST['post_id'];
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM COMMENTS WHERE POST_ID = ? AND COMMENT_TEXT IS NOT NULL");
            $stmt->execute([$postId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $response['success'] = true;
            $response['count'] = $result['count'];
        }
        
        // DELETE COMMENT
        elseif ($action === 'delete_comment') {
            $commentId = (int)$_POST['comment_id'];
            
            // Check if comment belongs to current user
            $checkStmt = $pdo->prepare("SELECT USER_ID FROM COMMENTS WHERE COMMENT_ID = ?");
            $checkStmt->execute([$commentId]);
            $comment = $checkStmt->fetch();
            
            if ($comment && $comment['USER_ID'] == $userId) {
                // Delete all reactions for this comment first (cascade should handle this, but being explicit)
                $deleteReactionsStmt = $pdo->prepare("DELETE FROM COMMENT_REACTIONS WHERE COMMENT_ID = ?");
                $deleteReactionsStmt->execute([$commentId]);
                
                // Delete the comment
                $stmt = $pdo->prepare("DELETE FROM COMMENTS WHERE COMMENT_ID = ?");
                $stmt->execute([$commentId]);
                
                $response['success'] = true;
                $response['message'] = 'Comment deleted successfully';
                $response['comment_id'] = $commentId;
            } else {
                $response['message'] = 'You can only delete your own comments';
            }
        }
        
        // LIKE A COMMENT (Legacy - now redirects to reaction system)
        elseif ($action === 'like_comment') {
            $commentId = (int)$_POST['comment_id'];
            
            // Redirect to the new reaction system with default "like" reaction
            $_POST['action'] = 'add_comment_reaction';
            $_POST['comment_id'] = $commentId;
            $_POST['reaction_type'] = 'like';
            
            // Re-process with the new action (recursive call to the reaction handler above)
            $reactionType = 'like';
            $reactionEmoji = '👍';
            
            // Check if user already reacted to this comment
            $existingReactionStmt = $pdo->prepare("SELECT REACTION_TYPE FROM COMMENT_REACTIONS WHERE COMMENT_ID = ? AND USER_ID = ?");
            $existingReactionStmt->execute([$commentId, $userId]);
            $existingReaction = $existingReactionStmt->fetch();
            
            if ($existingReaction && $existingReaction['REACTION_TYPE'] === 'like') {
                // Remove like (toggle off)
                $stmt = $pdo->prepare("DELETE FROM COMMENT_REACTIONS WHERE COMMENT_ID = ? AND USER_ID = ?");
                $stmt->execute([$commentId, $userId]);
                
                $response['success'] = true;
                $response['message'] = 'Like removed';
                $response['action_type'] = 'removed';
                $response['has_reaction'] = false;
            } else {
                // Add like or update existing reaction to like
                if ($existingReaction) {
                    $stmt = $pdo->prepare("UPDATE COMMENT_REACTIONS SET REACTION_TYPE = 'like', REACTION_EMOJI = '👍', UPDATED_AT = CURRENT_TIMESTAMP WHERE COMMENT_ID = ? AND USER_ID = ?");
                    $stmt->execute([$commentId, $userId]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO COMMENT_REACTIONS (COMMENT_ID, USER_ID, REACTION_TYPE, REACTION_EMOJI) VALUES (?, ?, 'like', '👍')");
                    $stmt->execute([$commentId, $userId]);
                }
                
                $response['success'] = true;
                $response['message'] = 'Comment liked';
                $response['action_type'] = $existingReaction ? 'updated' : 'added';
                $response['has_reaction'] = true;
                $response['reaction_type'] = 'like';
                $response['reaction_emoji'] = '👍';
            }
        }
        
    } catch (PDOException $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
        error_log("Comment System Error: " . $e->getMessage());
    } catch (Exception $e) {
        $response['message'] = 'System error: ' . $e->getMessage();
        error_log("Comment System Error: " . $e->getMessage());
    }
}

echo json_encode($response);
?>