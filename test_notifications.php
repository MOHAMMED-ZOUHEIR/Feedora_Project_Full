<?php
// TESTING SCRIPT: test_notifications.php
// Use this to test the story notification system

require_once 'config/config.php';
require_once 'notification_utils.php';

// ONLY RUN THIS ON DEVELOPMENT/TESTING ENVIRONMENT
if ($_SERVER['HTTP_HOST'] === 'localhost' || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
    
    echo "<h2>🧪 Story Notification System Test</h2>";
    echo "<p>Testing the notification system for story uploads...</p>";

    try {
        // Test parameters
        $testUserId = 1; // User who posts the story
        $testStoryId = 1; // Story ID (use an existing one)
        
        echo "<div style='background: #f0f8ff; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
        echo "<h3>📋 Test Configuration:</h3>";
        echo "<ul>";
        echo "<li><strong>Story Poster User ID:</strong> $testUserId</li>";
        echo "<li><strong>Test Story ID:</strong> $testStoryId</li>";
        echo "</ul>";
        echo "</div>";

        // Step 1: Check if user exists
        $userStmt = $pdo->prepare("SELECT USER_ID, NAME, EMAIL FROM USERS WHERE USER_ID = ?");
        $userStmt->execute([$testUserId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo "<div style='background: #ffebee; color: #c62828; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
            echo "❌ <strong>Error:</strong> User ID $testUserId not found in database!";
            echo "</div>";
            exit();
        }

        echo "<div style='background: #e8f5e8; color: #2e7d32; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
        echo "✅ <strong>User Found:</strong> " . htmlspecialchars($user['NAME']) . " (" . htmlspecialchars($user['EMAIL']) . ")";
        echo "</div>";

        // Step 2: Check followers
        $followersStmt = $pdo->prepare("
            SELECT f.FOLLOWER_ID, u.NAME, u.EMAIL 
            FROM FOLLOWERS f 
            JOIN USERS u ON f.FOLLOWER_ID = u.USER_ID 
            WHERE f.USER_ID = ?
        ");
        $followersStmt->execute([$testUserId]);
        $followers = $followersStmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<div style='background: #fff3e0; color: #ef6c00; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
        echo "<h3>👥 Followers Check:</h3>";
        if (empty($followers)) {
            echo "<p>⚠️ User ID $testUserId has no followers. Creating notifications won't have visible effect.</p>";
            echo "<p><strong>To test properly:</strong></p>";
            echo "<ol>";
            echo "<li>Create a test follower relationship in the database:</li>";
            echo "<li><code>INSERT INTO FOLLOWERS (USER_ID, FOLLOWER_ID) VALUES ($testUserId, 2);</code></li>";
            echo "<li>Replace '2' with an existing user ID who should receive notifications</li>";
            echo "</ol>";
        } else {
            echo "<p>✅ Found " . count($followers) . " followers:</p>";
            echo "<ul>";
            foreach ($followers as $follower) {
                echo "<li>" . htmlspecialchars($follower['NAME']) . " (ID: " . $follower['FOLLOWER_ID'] . ")</li>";
            }
            echo "</ul>";
        }
        echo "</div>";

        // Step 3: Test story notification function
        echo "<div style='background: #f3e5f5; color: #7b1fa2; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
        echo "<h3>🚀 Testing Story Notification Function:</h3>";
        
        if (!empty($followers)) {
            $result = notifyFollowersNewStory($pdo, $testStoryId, $testUserId);
            
            if ($result) {
                echo "<p>✅ <strong>Success!</strong> Story notifications created successfully.</p>";
                
                // Check created notifications
                $notifStmt = $pdo->prepare("
                    SELECT n.*, u.NAME as SENDER_NAME, t.NAME as TARGET_NAME
                    FROM NOTIFICATIONS n
                    LEFT JOIN USERS u ON n.USER_ID = u.USER_ID
                    LEFT JOIN USERS t ON n.TARGET_USER_ID = t.USER_ID
                    WHERE n.USER_ID = ? AND n.NOTIFICATION_TYPE = 'new_story' AND n.RELATED_ID = ?
                    ORDER BY n.CREATED_AT DESC
                    LIMIT 10
                ");
                $notifStmt->execute([$testUserId, $testStoryId]);
                $notifications = $notifStmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($notifications)) {
                    echo "<h4>📋 Created Notifications:</h4>";
                    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
                    echo "<tr style='background: #f5f5f5;'>";
                    echo "<th style='padding: 8px;'>ID</th>";
                    echo "<th style='padding: 8px;'>From</th>";
                    echo "<th style='padding: 8px;'>To</th>";
                    echo "<th style='padding: 8px;'>Content</th>";
                    echo "<th style='padding: 8px;'>Created</th>";
                    echo "</tr>";
                    
                    foreach ($notifications as $notif) {
                        echo "<tr>";
                        echo "<td style='padding: 8px;'>" . $notif['NOTIFICATION_ID'] . "</td>";
                        echo "<td style='padding: 8px;'>" . htmlspecialchars($notif['SENDER_NAME']) . "</td>";
                        echo "<td style='padding: 8px;'>" . htmlspecialchars($notif['TARGET_NAME']) . "</td>";
                        echo "<td style='padding: 8px;'>" . htmlspecialchars($notif['CONTENT']) . "</td>";
                        echo "<td style='padding: 8px;'>" . $notif['CREATED_AT'] . "</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                } else {
                    echo "<p>⚠️ Function returned success but no notifications found in database.</p>";
                }
                
            } else {
                echo "<p>❌ <strong>Failed!</strong> Story notification function returned false.</p>";
            }
        } else {
            echo "<p>⏭️ <strong>Skipped:</strong> No followers to notify.</p>";
        }
        echo "</div>";

        // Step 4: Check notification system integration
        echo "<div style='background: #e3f2fd; color: #1565c0; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
        echo "<h3>🔍 System Integration Check:</h3>";
        
        // Check if notification_utils.php is properly included
        if (function_exists('notifyFollowersNewStory')) {
            echo "<p>✅ <strong>notifyFollowersNewStory</strong> function is available</p>";
        } else {
            echo "<p>❌ <strong>notifyFollowersNewStory</strong> function is NOT available</p>";
        }
        
        // Check if other notification functions exist
        $functions = ['createNotification', 'getUserFollowers', 'getUserName'];
        foreach ($functions as $func) {
            if (function_exists($func)) {
                echo "<p>✅ <strong>$func</strong> function is available</p>";
            } else {
                echo "<p>❌ <strong>$func</strong> function is NOT available</p>";
            }
        }
        echo "</div>";

        // Step 5: Manual test links
        echo "<div style='background: #fff8e1; color: #f57f17; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
        echo "<h3>🔗 Manual Test Links:</h3>";
        echo "<p>After running this test, check these pages to verify notifications appear:</p>";
        echo "<ul>";
        
        if (!empty($followers)) {
            foreach ($followers as $follower) {
                echo "<li><a href='header.php?user=" . $follower['FOLLOWER_ID'] . "' target='_blank'>Check " . htmlspecialchars($follower['NAME']) . "'s header notifications</a></li>";
                echo "<li><a href='notifications.php?user=" . $follower['FOLLOWER_ID'] . "' target='_blank'>Check " . htmlspecialchars($follower['NAME']) . "'s notifications page</a></li>";
            }
        }
        
        echo "<li><a href='notifications.php' target='_blank'>View Notifications Page</a></li>";
        echo "<li><a href='dashboard.php' target='_blank'>View Dashboard (check header)</a></li>";
        echo "</ul>";
        echo "</div>";

        // Clean up option
        echo "<div style='background: #ffebee; color: #c62828; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
        echo "<h3>🧹 Clean Up Test Data:</h3>";
        echo "<p>If you want to remove the test notifications created by this script:</p>";
        echo "<form method='post' style='margin: 10px 0;'>";
        echo "<input type='hidden' name='cleanup' value='1'>";
        echo "<input type='hidden' name='test_user_id' value='$testUserId'>";
        echo "<input type='hidden' name='test_story_id' value='$testStoryId'>";
        echo "<button type='submit' style='background: #f44336; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;'>🗑️ Delete Test Notifications</button>";
        echo "</form>";
        echo "</div>";

    } catch (Exception $e) {
        echo "<div style='background: #ffebee; color: #c62828; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
        echo "<h3>❌ Error During Testing:</h3>";
        echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p><strong>File:</strong> " . $e->getFile() . "</p>";
        echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
        echo "</div>";
    }

    // Handle cleanup
    if (isset($_POST['cleanup']) && $_POST['cleanup'] == '1') {
        try {
            $cleanupUserId = intval($_POST['test_user_id']);
            $cleanupStoryId = intval($_POST['test_story_id']);
            
            $cleanupStmt = $pdo->prepare("
                DELETE FROM NOTIFICATIONS 
                WHERE USER_ID = ? AND NOTIFICATION_TYPE = 'new_story' AND RELATED_ID = ?
            ");
            $cleanupStmt->execute([$cleanupUserId, $cleanupStoryId]);
            $deletedCount = $cleanupStmt->rowCount();
            
            echo "<div style='background: #e8f5e8; color: #2e7d32; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
            echo "<h3>✅ Cleanup Complete:</h3>";
            echo "<p>Deleted $deletedCount test notifications.</p>";
            echo "</div>";
            
        } catch (Exception $e) {
            echo "<div style='background: #ffebee; color: #c62828; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
            echo "<h3>❌ Cleanup Error:</h3>";
            echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
            echo "</div>";
        }
    }

} else {
    echo "<h2>⚠️ Security Notice</h2>";
    echo "<p>This test script only runs on localhost/development environments for security reasons.</p>";
    echo "<p>Current host: " . htmlspecialchars($_SERVER['HTTP_HOST']) . "</p>";
}
?>

<style>
body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    line-height: 1.6;
    color: #333;
    max-width: 1000px;
    margin: 0 auto;
    padding: 20px;
    background: #f5f5f5;
}

h2, h3 {
    color: #333;
}

code {
    background: #f5f5f5;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
}

table {
    font-size: 14px;
}

th {
    font-weight: 600;
}

a {
    color: #1976d2;
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}

button:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}
</style>