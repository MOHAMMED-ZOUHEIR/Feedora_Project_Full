<?php
// Include the database connection script
require_once 'config/config.php';
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    die("Access denied. Please log in to use this tool.");
}

// Function to format file size
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// Function to compress a video file
function compressVideo($source, $destination, $quality = 23) {
    // Check if ffmpeg is available
    exec('ffmpeg -version', $output, $returnCode);
    if ($returnCode !== 0) {
        return false;
    }
    
    // Use ffmpeg to compress the video
    $cmd = "ffmpeg -i \"$source\" -c:v libx264 -crf $quality -preset medium -c:a aac -b:a 128k \"$destination\"";
    exec($cmd, $output, $returnCode);
    
    return $returnCode === 0;
}

// Function to get all files in uploads directory with their database references
function getFilesWithReferences($pdo) {
    $files = [];
    $uploadDirs = ['uploads/posts/', 'uploads/profiles/', 'uploads/stories/'];
    
    // Get all files from the uploads directories
    foreach ($uploadDirs as $dir) {
        if (is_dir($dir)) {
            $dirFiles = scandir($dir);
            foreach ($dirFiles as $file) {
                if ($file != '.' && $file != '..' && is_file($dir . $file)) {
                    $filePath = $dir . $file;
                    $fileSize = filesize($filePath);
                    $files[$filePath] = [
                        'size' => $fileSize,
                        'referenced' => false,
                        'table' => '',
                        'id' => 0
                    ];
                }
            }
        }
    }
    
    // Check for references in POSTS table
    $stmt = $pdo->query("SELECT POSTS_ID, IMAGE_URL FROM POSTS WHERE IMAGE_URL IS NOT NULL");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (isset($files[$row['IMAGE_URL']])) {
            $files[$row['IMAGE_URL']]['referenced'] = true;
            $files[$row['IMAGE_URL']]['table'] = 'POSTS';
            $files[$row['IMAGE_URL']]['id'] = $row['POSTS_ID'];
        }
    }
    
    // Check for references in USERS table (profile images)
    $stmt = $pdo->query("SELECT USER_ID, PROFILE_IMAGE FROM USERS WHERE PROFILE_IMAGE IS NOT NULL");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (isset($files[$row['PROFILE_IMAGE']])) {
            $files[$row['PROFILE_IMAGE']]['referenced'] = true;
            $files[$row['PROFILE_IMAGE']]['table'] = 'USERS';
            $files[$row['PROFILE_IMAGE']]['id'] = $row['USER_ID'];
        }
    }
    
    // Check for references in STORIES table
    $stmt = $pdo->query("SELECT STORIES_ID, IMAGE_URL FROM STORIES WHERE IMAGE_URL IS NOT NULL");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (isset($files[$row['IMAGE_URL']])) {
            $files[$row['IMAGE_URL']]['referenced'] = true;
            $files[$row['IMAGE_URL']]['table'] = 'STORIES';
            $files[$row['IMAGE_URL']]['id'] = $row['STORIES_ID'];
        }
    }
    
    // Check for references in RECIPES table
    $stmt = $pdo->query("SELECT RECIPES_ID, PHOTO_URL FROM RECIPES WHERE PHOTO_URL IS NOT NULL");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (isset($files[$row['PHOTO_URL']])) {
            $files[$row['PHOTO_URL']]['referenced'] = true;
            $files[$row['PHOTO_URL']]['table'] = 'RECIPES';
            $files[$row['PHOTO_URL']]['id'] = $row['RECIPES_ID'];
        }
    }
    
    return $files;
}

// Initialize variables
$action = isset($_GET['action']) ? $_GET['action'] : '';
$message = '';
$totalSize = 0;
$totalFiles = 0;
$unreferencedSize = 0;
$unreferencedFiles = 0;
$largeVideoSize = 0;
$largeVideoFiles = 0;
$videoSizeThreshold = 10 * 1024 * 1024; // 10MB

// Get all files with their database references
$files = getFilesWithReferences($pdo);

// Calculate statistics
foreach ($files as $path => $info) {
    $totalSize += $info['size'];
    $totalFiles++;
    
    if (!$info['referenced']) {
        $unreferencedSize += $info['size'];
        $unreferencedFiles++;
    }
    
    // Check for large video files
    if (pathinfo($path, PATHINFO_EXTENSION) === 'mp4' && $info['size'] > $videoSizeThreshold) {
        $largeVideoSize += $info['size'];
        $largeVideoFiles++;
    }
}

// Handle actions
if ($action === 'delete_unreferenced') {
    $deleted = 0;
    $deletedSize = 0;
    
    foreach ($files as $path => $info) {
        if (!$info['referenced'] && file_exists($path)) {
            $deletedSize += $info['size'];
            if (unlink($path)) {
                $deleted++;
            }
        }
    }
    
    $message = "Deleted $deleted unreferenced files (" . formatFileSize($deletedSize) . ")";
    
    // Refresh file list
    $files = getFilesWithReferences($pdo);
    
    // Recalculate statistics
    $totalSize = 0;
    $totalFiles = 0;
    $unreferencedSize = 0;
    $unreferencedFiles = 0;
    $largeVideoSize = 0;
    $largeVideoFiles = 0;
    
    foreach ($files as $path => $info) {
        $totalSize += $info['size'];
        $totalFiles++;
        
        if (!$info['referenced']) {
            $unreferencedSize += $info['size'];
            $unreferencedFiles++;
        }
        
        if (pathinfo($path, PATHINFO_EXTENSION) === 'mp4' && $info['size'] > $videoSizeThreshold) {
            $largeVideoSize += $info['size'];
            $largeVideoFiles++;
        }
    }
} elseif ($action === 'compress_videos') {
    $compressed = 0;
    $originalSize = 0;
    $newSize = 0;
    
    foreach ($files as $path => $info) {
        if (pathinfo($path, PATHINFO_EXTENSION) === 'mp4' && $info['size'] > $videoSizeThreshold) {
            $originalSize += $info['size'];
            
            // Create temporary filename for compressed video
            $tempFile = $path . '.compressed.mp4';
            
            // Compress the video
            if (compressVideo($path, $tempFile)) {
                // Check if compression was successful and reduced size
                if (file_exists($tempFile) && filesize($tempFile) < $info['size']) {
                    // Backup original file
                    rename($path, $path . '.backup');
                    
                    // Replace with compressed file
                    rename($tempFile, $path);
                    
                    // Update database reference if needed
                    // (not needed here since the filename stays the same)
                    
                    $newSize += filesize($path);
                    $compressed++;
                } else {
                    // Compression didn't reduce size or failed
                    if (file_exists($tempFile)) {
                        unlink($tempFile);
                    }
                }
            }
        }
    }
    
    $message = "Compressed $compressed videos. Original size: " . formatFileSize($originalSize) . 
               ", New size: " . formatFileSize($newSize) . 
               ", Saved: " . formatFileSize($originalSize - $newSize);
    
    // Refresh file list
    $files = getFilesWithReferences($pdo);
    
    // Recalculate statistics
    $totalSize = 0;
    $totalFiles = 0;
    $unreferencedSize = 0;
    $unreferencedFiles = 0;
    $largeVideoSize = 0;
    $largeVideoFiles = 0;
    
    foreach ($files as $path => $info) {
        $totalSize += $info['size'];
        $totalFiles++;
        
        if (!$info['referenced']) {
            $unreferencedSize += $info['size'];
            $unreferencedFiles++;
        }
        
        if (pathinfo($path, PATHINFO_EXTENSION) === 'mp4' && $info['size'] > $videoSizeThreshold) {
            $largeVideoSize += $info['size'];
            $largeVideoFiles++;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedora - Project Cleanup</title>
    <link rel="stylesheet" href="Home.css">
    <style>
        .cleanup-container {
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .stats-box {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 20px;
        }
        .stat-card {
            flex: 1;
            min-width: 200px;
            padding: 15px;
            background-color: #f5f5f5;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .file-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .file-table th, .file-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .file-table th {
            background-color: #f2f2f2;
        }
        .file-table tr:hover {
            background-color: #f9f9f9;
        }
        .action-buttons {
            margin: 20px 0;
        }
        .action-button {
            padding: 10px 15px;
            margin-right: 10px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .action-button:hover {
            background-color: #45a049;
        }
        .warning {
            color: #ff0000;
        }
        .message {
            padding: 10px;
            margin-bottom: 20px;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
            color: #155724;
        }
    </style>
</head>
<body>
    <div class="cleanup-container">
        <h1>Feedora Project Cleanup</h1>
        
        <?php if (!empty($message)): ?>
        <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="stats-box">
            <div class="stat-card">
                <h3>Total Files</h3>
                <p><?php echo $totalFiles; ?> files</p>
                <p><?php echo formatFileSize($totalSize); ?></p>
            </div>
            
            <div class="stat-card">
                <h3>Unreferenced Files</h3>
                <p><?php echo $unreferencedFiles; ?> files</p>
                <p><?php echo formatFileSize($unreferencedSize); ?></p>
            </div>
            
            <div class="stat-card">
                <h3>Large Videos (>10MB)</h3>
                <p><?php echo $largeVideoFiles; ?> files</p>
                <p><?php echo formatFileSize($largeVideoSize); ?></p>
            </div>
        </div>
        
        <div class="action-buttons">
            <a href="?action=delete_unreferenced" class="action-button" onclick="return confirm('Are you sure you want to delete all unreferenced files?')">Delete Unreferenced Files</a>
            <a href="?action=compress_videos" class="action-button" onclick="return confirm('Are you sure you want to compress all large video files? This may take some time.')">Compress Large Videos</a>
        </div>
        
        <h2>File Details</h2>
        <table class="file-table">
            <thead>
                <tr>
                    <th>File Path</th>
                    <th>Size</th>
                    <th>Referenced</th>
                    <th>Table</th>
                    <th>ID</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($files as $path => $info): ?>
                <tr>
                    <td><?php echo htmlspecialchars($path); ?></td>
                    <td><?php echo formatFileSize($info['size']); ?></td>
                    <td><?php echo $info['referenced'] ? 'Yes' : '<span class="warning">No</span>'; ?></td>
                    <td><?php echo htmlspecialchars($info['table']); ?></td>
                    <td><?php echo $info['id']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
