<?php
// comments.php - Enhanced Comment System Handler with Reactions and Pagination
require_once 'config/config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

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
            
            // Check if user already reacted to this comment
            $checkStmt = $pdo->prepare("SELECT COMMENT_ID, HAS_REACTION, REACTION_TYPE FROM COMMENTS WHERE COMMENT_ID = ? AND USER_ID = ?");
            $checkStmt->execute([$commentId, $userId]);
            $existingComment = $checkStmt->fetch();
            
            if (!$existingComment) {
                $response['message'] = 'Comment not found or access denied';
            } else {
                if ($existingComment['HAS_REACTION'] && $existingComment['REACTION_TYPE'] === $reactionType) {
                    // Remove reaction (toggle off)
                    $stmt = $pdo->prepare("UPDATE COMMENTS SET HAS_REACTION = FALSE, REACTION_TYPE = NULL, REACTION_EMOJI = NULL WHERE COMMENT_ID = ? AND USER_ID = ?");
                    $stmt->execute([$commentId, $userId]);
                    
                    $response['success'] = true;
                    $response['message'] = 'Reaction removed';
                    $response['action_type'] = 'removed';
                    $response['has_reaction'] = false;
                    $response['reaction_type'] = null;
                    $response['reaction_emoji'] = null;
                } else {
                    // Add or update reaction
                    $stmt = $pdo->prepare("UPDATE COMMENTS SET HAS_REACTION = TRUE, REACTION_TYPE = ?, REACTION_EMOJI = ? WHERE COMMENT_ID = ? AND USER_ID = ?");
                    $stmt->execute([$reactionType, $reactionEmoji, $commentId, $userId]);
                    
                    $actionType = $existingComment['HAS_REACTION'] ? 'updated' : 'added';
                    
                    $response['success'] = true;
                    $response['message'] = 'Reaction ' . $actionType;
                    $response['action_type'] = $actionType;
                    $response['has_reaction'] = true;
                    $response['reaction_type'] = $reactionType;
                    $response['reaction_emoji'] = $reactionEmoji;
                }
            }
        }
        
        // GET COMMENTS FOR A POST (Legacy - keeping for backward compatibility)
        elseif ($action === 'get_comments') {
            $postId = (int)$_POST['post_id'];
            
            // Get comments with user info and reaction status
            $commentsStmt = $pdo->prepare("
                SELECT c.*, u.NAME, u.PROFILE_IMAGE,
                       c.HAS_REACTION, c.REACTION_TYPE, c.REACTION_EMOJI
                FROM COMMENTS c 
                JOIN USERS u ON c.USER_ID = u.USER_ID 
                WHERE c.POST_ID = ? AND c.COMMENT_TEXT IS NOT NULL 
                ORDER BY c.CREATED_AT DESC
            ");
            $commentsStmt->execute([$postId]);
            $comments = $commentsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get post reactions (existing functionality)
            $reactionsStmt = $pdo->prepare("
                SELECT c.*, u.NAME, u.PROFILE_IMAGE 
                FROM COMMENTS c 
                JOIN USERS u ON c.USER_ID = u.USER_ID 
                WHERE c.POST_ID = ? AND c.HAS_REACTION = TRUE AND c.COMMENT_TEXT IS NULL
                ORDER BY c.CREATED_AT DESC
            ");
            $reactionsStmt->execute([$postId]);
            $reactions = $reactionsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response['success'] = true;
            $response['comments'] = $comments;
            $response['reactions'] = $reactions;
            $response['total_comments'] = count($comments);
            $response['total_reactions'] = count($reactions);
        }
        
        // GET COMMENTS FOR A POST WITH PAGINATION (New enhanced version)
        elseif ($action === 'get_comments_paginated') {
            $postId = (int)$_POST['post_id'];
            $offset = (int)($_POST['offset'] ?? 0);
            $limit = (int)($_POST['limit'] ?? 10);
            
            // Get total comment count
            $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM COMMENTS WHERE POST_ID = ? AND COMMENT_TEXT IS NOT NULL");
            $countStmt->execute([$postId]);
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Get paginated comments with user info and reaction status
            $commentsStmt = $pdo->prepare("
                SELECT c.*, u.NAME, u.PROFILE_IMAGE,
                       c.HAS_REACTION, c.REACTION_TYPE, c.REACTION_EMOJI
                FROM COMMENTS c 
                JOIN USERS u ON c.USER_ID = u.USER_ID 
                WHERE c.POST_ID = ? AND c.COMMENT_TEXT IS NOT NULL 
                ORDER BY c.CREATED_AT DESC
                LIMIT ? OFFSET ?
            ");
            $commentsStmt->execute([$postId, $limit, $offset]);
            $comments = $commentsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get post reactions (only on first load)
            $reactions = [];
            if ($offset === 0) {
                $reactionsStmt = $pdo->prepare("
                    SELECT c.*, u.NAME, u.PROFILE_IMAGE 
                    FROM COMMENTS c 
                    JOIN USERS u ON c.USER_ID = u.USER_ID 
                    WHERE c.POST_ID = ? AND c.HAS_REACTION = TRUE AND c.COMMENT_TEXT IS NULL
                    ORDER BY c.CREATED_AT DESC
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
                $stmt = $pdo->prepare("DELETE FROM COMMENTS WHERE COMMENT_ID = ?");
                $stmt->execute([$commentId]);
                
                $response['success'] = true;
                $response['message'] = 'Comment deleted successfully';
            } else {
                $response['message'] = 'You can only delete your own comments';
            }
        }
        
        // LIKE A COMMENT (Legacy - keeping for backward compatibility)
        elseif ($action === 'like_comment') {
            $commentId = (int)$_POST['comment_id'];
            
            // Redirect to the new reaction system with default "like" reaction
            $_POST['action'] = 'add_comment_reaction';
            $_POST['comment_id'] = $commentId;
            $_POST['reaction_type'] = 'like';
            
            // Re-process with the new action
            $action = 'add_comment_reaction';
            // The code will fall through to the add_comment_reaction case above
            
            // Check if user already reacted to this comment
            $checkStmt = $pdo->prepare("SELECT COMMENT_ID, HAS_REACTION, REACTION_TYPE FROM COMMENTS WHERE COMMENT_ID = ? AND USER_ID = ?");
            $checkStmt->execute([$commentId, $userId]);
            $existingComment = $checkStmt->fetch();
            
            if (!$existingComment) {
                $response['message'] = 'Comment not found or access denied';
            } else {
                if ($existingComment['HAS_REACTION'] && $existingComment['REACTION_TYPE'] === 'like') {
                    // Remove like (toggle off)
                    $stmt = $pdo->prepare("UPDATE COMMENTS SET HAS_REACTION = FALSE, REACTION_TYPE = NULL, REACTION_EMOJI = NULL WHERE COMMENT_ID = ? AND USER_ID = ?");
                    $stmt->execute([$commentId, $userId]);
                    
                    $response['success'] = true;
                    $response['message'] = 'Like removed';
                } else {
                    // Add like
                    $stmt = $pdo->prepare("UPDATE COMMENTS SET HAS_REACTION = TRUE, REACTION_TYPE = 'like', REACTION_EMOJI = '👍' WHERE COMMENT_ID = ? AND USER_ID = ?");
                    $stmt->execute([$commentId, $userId]);
                    
                    $response['success'] = true;
                    $response['message'] = 'Comment liked';
                }
            }
        }
        
    } catch (PDOException $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
        error_log("Comment System Error: " . $e->getMessage());
    }
}

echo json_encode($response);
?>