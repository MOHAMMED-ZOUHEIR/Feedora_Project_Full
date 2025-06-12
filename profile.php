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
$userName = $_SESSION['user_name'] ?? 'User';
$userEmail = $_SESSION['user_email'] ?? '';
$profileImage = null;
$message = '';

// Get current profile image if exists
try {
    $stmt = $pdo->prepare("SELECT PROFILE_IMAGE FROM USERS WHERE USER_ID = ?");
    $stmt->execute([$userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result && $result['PROFILE_IMAGE']) {
        $profileImage = $result['PROFILE_IMAGE'];
        
        // If user already has a profile image, redirect to dashboard
        // Only show this page to users who haven't set a profile image yet
        header("Location: dashboard.php");
        exit();
    }
} catch (PDOException $e) {
    $message = 'Error retrieving profile: ' . $e->getMessage();
}

// Handle profile image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_image'])) {
    $uploadDir = 'uploads/profiles/';
    
    // Create directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $file = $_FILES['profile_image'];
    $fileName = $file['name'];
    $fileTmpName = $file['tmp_name'];
    $fileError = $file['error'];
    
    // Check for errors
    if ($fileError === 0) {
        // Generate unique filename
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
        $newFileName = 'profile_' . $userId . '_' . time() . '.' . $fileExtension;
        $destination = $uploadDir . $newFileName;
        
        // Move uploaded file to destination
        if (move_uploaded_file($fileTmpName, $destination)) {
            // Update profile image in database
            try {
                $stmt = $pdo->prepare("UPDATE USERS SET PROFILE_IMAGE = ? WHERE USER_ID = ?");
                $stmt->execute([$destination, $userId]);
                $profileImage = $destination;
                $message = 'Profile image updated successfully!';
                
                // Update session with new profile image
                $_SESSION['profile_image'] = $destination;
                
                // Redirect to dashboard.php after successful profile update
                header("Location: dashboard.php");
                exit();
            } catch (PDOException $e) {
                $message = 'Error updating profile: ' . $e->getMessage();
            }
        } else {
            $message = 'Error moving uploaded file.';
        }
    } else {
        $message = 'Error uploading file. Error code: ' . $fileError;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Feedora - Set up your profile">
  <meta name="theme-color" content="#ED5A2C">
  <title>Profile Setup - Feedora</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="fonts.css">
  <link rel="stylesheet" href="Home.css">
  <!-- Favicon -->
  <link rel="icon" href="images/Frame 1171277973.svg" type="image/svg+xml">
  <style>
    /* Additional styles specific to the profile page */
    .profile-container {
      display: flex;
      flex-direction: column;
      align-items: center;
      background-color: white;
      border-radius: var(--border-radius);
      box-shadow: var(--box-shadow);
      padding: 40px;
      margin: 40px auto;
      max-width: 600px;
    }
    
    .profile-title {
      font-size: 2.5rem;
      margin-bottom: 10px;
      color: #333;
      font-family: 'DM Serif Display', serif;
      text-align: center;
    }
    
    .profile-subtitle {
      color: #666;
      margin-bottom: 30px;
      text-align: center;
    }
    
    .profile-image-container {
      width: 150px;
      height: 150px;
      border-radius: 50%;
      overflow: hidden;
      margin-bottom: 20px;
      border: 3px solid var(--primary-color);
      position: relative;
    }
    
    .profile-image {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    
    .profile-placeholder {
      width: 100%;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      background-color: #f5f5f5;
      color: #999;
      font-size: 3rem;
    }
    
    .profile-form {
      width: 100%;
      max-width: 400px;
    }
    
    .file-input-container {
      position: relative;
      margin-bottom: 20px;
      width: 100%;
    }
    
    .file-input {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      opacity: 0;
      cursor: pointer;
      z-index: 2;
    }
    
    .file-input-label {
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 12px 24px;
      background-color: #f5f5f5;
      border: 2px dashed #ddd;
      border-radius: 8px;
      color: #666;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.3s ease;
    }
    
    .file-input-label:hover {
      border-color: var(--primary-color);
      color: var(--primary-color);
    }
    
    .file-name {
      margin-top: 8px;
      font-size: 14px;
      color: #666;
      text-align: center;
    }
    
    .submit-button {
      background-color: var(--primary-color);
      color: white;
      border: none;
      border-radius: 8px;
      padding: 12px 24px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: background-color 0.3s;
      width: 100%;
      margin-top: 20px;
    }
    
    .submit-button:hover {
      background-color: #d94e20;
    }
    
    .skip-link {
      margin-top: 20px;
      color: #666;
      text-decoration: none;
      font-weight: 500;
      transition: color 0.3s;
    }
    
    .skip-link:hover {
      color: var(--primary-color);
      text-decoration: underline;
    }
    
    .message {
      margin-top: 20px;
      padding: 10px;
      border-radius: 5px;
      width: 100%;
      text-align: center;
    }
    
    .success-message {
      background-color: #e8f8f5;
      color: #2ecc71;
    }
    
    .error-message {
      background-color: #fdecea;
      color: #e74c3c;
    }
  </style>
</head>
<body>
  <!-- Loading Screen -->
  <div id="loading-screen" aria-hidden="true">
    <div class="loader-container">
      <img src="images/Pizza (8).png" alt="Loading" class="loader-image">
    </div>
    <p class="visually-hidden">Loading Feedora</p>
  </div>
  
  <header>
    <div class="logo-container">
      <div class="logo-icon">
        <img src="images/Feedora-logo.svg" alt="Feedora Icon">
      </div>
    </div>

    <div class="center-container">
      <a href="Home.php" class="home-link">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ed5a2c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
        </svg>
      </a>
      <a href="/recipes" class="nav-link">Recipes</a>
      <a href="/live-sessions" class="nav-link">Live Sessions</a>
      <a href="/community" class="nav-link">Community</a>
      
      <div class="search-container">
        <input type="text" placeholder="Search" class="search-input">
        <button class="search-button">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
        </button>
      </div>
    </div>

    <div class="auth-buttons">
      <!-- Show user name if logged in -->
      <span><?php echo htmlspecialchars($userName); ?></span>
    </div>
  </header>

  <main>
    <div class="profile-container">
      <h1 class="profile-title">Set Up Your Profile</h1>
      <p class="profile-subtitle">Add a profile picture to personalize your account</p>
      
      <div class="profile-image-container">
        <?php if ($profileImage): ?>
          <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Profile Image" class="profile-image">
        <?php else: ?>
          <div class="profile-placeholder">
            <?php echo strtoupper(substr($userName, 0, 1)); ?>
          </div>
        <?php endif; ?>
      </div>
      
      <?php if ($message): ?>
        <div class="message <?php echo strpos($message, 'successfully') !== false ? 'success-message' : 'error-message'; ?>">
          <?php echo htmlspecialchars($message); ?>
        </div>
      <?php endif; ?>
      
      <form action="profile.php" method="POST" enctype="multipart/form-data" class="profile-form">
        <div class="file-input-container">
          <input type="file" name="profile_image" id="profile_image" class="file-input" accept="image/*" onchange="updateFileName(this)">
          <label for="profile_image" class="file-input-label">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
              <polyline points="17 8 12 3 7 8"></polyline>
              <line x1="12" y1="3" x2="12" y2="15"></line>
            </svg>
            &nbsp;Choose Profile Image
          </label>
          <div id="file-name" class="file-name">No file chosen</div>
        </div>
        
        <button type="submit" class="submit-button">Upload Profile Image</button>
      </form>
      
      <a href="dashboard.php" class="skip-link">Skip for now</a>
    </div>
  </main>
  
  <!-- Footer Section -->
  <footer class="footer-section">
    <div class="footer-container">
      <div class="footer-logo-section">
        <div class="footer-logo">
          <img src="images/Feedora-logo.svg" alt="Feedora Logo" class="footer-logo-img">
        </div>
        <p class="footer-description">Feedora is a platform for cooking lovers to share, learn, and connect.</p>
        <div class="footer-social">
          <a href="#" class="social-link facebook">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path>
            </svg>
          </a>
          <a href="#" class="social-link instagram">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
              <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
              <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
            </svg>
          </a>
        </div>
      </div>
      
      <div class="footer-links-section">
        <div class="footer-links-column">
          <h3 class="footer-column-title">Quick Links</h3>
          <ul class="footer-links">
            <li><a href="Home.php" class="footer-link">Home</a></li>
            <li><a href="#" class="footer-link">Recipes</a></li>
            <li><a href="#" class="footer-link">Live Sessions</a></li>
            <li><a href="#" class="footer-link">Contact</a></li>
            <li><a href="#" class="footer-link">Terms & Privacy</a></li>
          </ul>
        </div>
        
        <div class="footer-newsletter">
          <h3 class="footer-column-title">Newsletter</h3>
          <div class="newsletter-form">
            <input type="email" placeholder="Enter your email" class="newsletter-input">
            <button class="newsletter-button">Subscribe</button>
          </div>
        </div>
      </div>
    </div>
    
    <div class="footer-bottom">
      <p class="copyright">©2025 Feedora All rights reserved</p>
    </div>
  </footer>
  
  <script src="Home.js"></script>
  <script>
    function updateFileName(input) {
      const fileName = input.files[0] ? input.files[0].name : 'No file chosen';
      document.getElementById('file-name').textContent = fileName;
      
      // Preview image if selected
      if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
          const profileContainer = document.querySelector('.profile-image-container');
          
          // Check if there's already an image or placeholder
          if (profileContainer.querySelector('.profile-image')) {
            profileContainer.querySelector('.profile-image').src = e.target.result;
          } else if (profileContainer.querySelector('.profile-placeholder')) {
            // Remove placeholder and add image
            profileContainer.innerHTML = '<img src="' + e.target.result + '" alt="Profile Image" class="profile-image">';
          }
        };
        reader.readAsDataURL(input.files[0]);
      }
    }
  </script>
</body>
</html>