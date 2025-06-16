<?php
/**
 * Shared Notification Helper Functions
 * 
 * This file contains common functions used by both header.php and notifications.php
 * to avoid function redeclaration conflicts.
 */

// ENHANCED: Get notification icons for professional display
if (!function_exists('getNotificationIcon')) {
    function getNotificationIcon($type)
    {
        $icons = [
            'new_post' => '📝',
            'new_reaction' => '❤️',
            'new_comment' => '💬',
            'new_story' => '📸',
            'new_recipe' => '🍳',
            'new_follower' => '👥',
            'new_message' => '💌',
            'test' => '🔔',
            'default' => '🔔'
        ];

        return $icons[$type] ?? $icons['default'];
    }
}

// ENHANCED: Default notification messages
if (!function_exists('getDefaultNotificationMessage')) {
    function getDefaultNotificationMessage($type)
    {
        $messages = [
            'new_post' => 'shared a new post',
            'new_reaction' => 'reacted to your post',
            'new_comment' => 'commented on your post',
            'new_story' => 'added a new story',
            'new_recipe' => 'shared a new recipe',
            'new_follower' => 'started following you',
            'new_message' => 'sent you a message',
            'test' => 'sent you a test notification',
            'default' => 'sent you a notification'
        ];

        return $messages[$type] ?? $messages['default'];
    }
}

// ENHANCED: Enhanced notification action formatting
if (!function_exists('getNotificationAction')) {
    function getNotificationAction($type)
    {
        $actions = [
            'new_post' => 'shared a new post',
            'new_reaction' => 'reacted to your post', 
            'new_comment' => 'commented on your post',
            'new_story' => 'added a new story',
            'new_recipe' => 'shared a new recipe',
            'new_follower' => 'started following you',
            'new_message' => 'sent you a message',
            'test' => 'sent you a test notification'
        ];
        
        return $actions[$type] ?? 'sent you a notification';
    }
}

// ENHANCED: Professional time formatting with better readability
if (!function_exists('formatNotificationTime')) {
    function formatNotificationTime($datetime, $notificationType = '')
    {
        if (!$datetime) {
            return 'Unknown time';
        }

        try {
            $notificationTime = new DateTime($datetime);
            $now = new DateTime();
            $diff = $now->getTimestamp() - $notificationTime->getTimestamp();

            // For very recent notifications (less than 2 minutes)
            if ($diff < 120) {
                return 'Just now';
            }

            // For notifications less than 1 hour
            if ($diff < 3600) {
                $minutes = floor($diff / 60);
                return $minutes . ' minute' . ($minutes !== 1 ? 's' : '') . ' ago';
            }

            // For notifications less than 24 hours
            if ($diff < 86400) {
                $hours = floor($diff / 3600);
                return $hours . ' hour' . ($hours !== 1 ? 's' : '') . ' ago';
            }

            // For notifications from today
            if ($notificationTime->format('Y-m-d') === $now->format('Y-m-d')) {
                return 'Today at ' . $notificationTime->format('g:i A');
            }

            // For notifications from yesterday
            if ($notificationTime->format('Y-m-d') === $now->modify('-1 day')->format('Y-m-d')) {
                return 'Yesterday at ' . $notificationTime->format('g:i A');
            }

            // For notifications within the last week
            if ($diff < 604800) {
                return $notificationTime->format('l') . ' at ' . $notificationTime->format('g:i A');
            }

            // For notifications within this year
            if ($notificationTime->format('Y') === date('Y')) {
                return $notificationTime->format('M j') . ' at ' . $notificationTime->format('g:i A');
            }

            // For older notifications
            return $notificationTime->format('M j, Y') . ' at ' . $notificationTime->format('g:i A');
        } catch (Exception $e) {
            error_log("Error formatting notification time: " . $e->getMessage());
            return 'Unknown time';
        }
    }
}

// ENHANCED: Helper function to format time (alias for backward compatibility)
if (!function_exists('timeAgo')) {
    function timeAgo($datetime) {
        return formatNotificationTime($datetime);
    }
}

// ENHANCED: Professional content formatting with emoji support
if (!function_exists('formatNotificationContent')) {
    function formatNotificationContent($content, $notificationType = '')
    {
        if (!$content) {
            return getDefaultNotificationMessage($notificationType);
        }

        try {
            // Define allowed HTML tags for rich formatting
            $allowedTags = '<strong><b><em><i><span>';
            // Strip unwanted HTML but keep formatting
            $safeContent = strip_tags($content, $allowedTags);

            // Add professional emoji icons based on notification type
            $icon = getNotificationIcon($notificationType);

            return $icon . ' ' . $safeContent;
        } catch (Exception $e) {
            error_log("Error formatting notification content: " . $e->getMessage());
            return getDefaultNotificationMessage($notificationType);
        }
    }
}

?>