<?php
// Include the database connection script
require_once 'config/config.php';
// Start the session to manage user data
session_start();
// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize user inputs
    $name = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    // Validate inputs
    if (empty($name) || empty($email) || empty($password)) {
        $_SESSION['error'] = 'All fields are required.';
        header('Location: sign-up.php');
        exit;
    }

    // Hash the password for security
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    try {
        // Prepare an SQL statement to insert the new user into the database
        $stmt = $pdo->prepare("INSERT INTO USERS (NAME, EMAIL, PASSWORD_HASH) VALUES (?, ?, ?)");
        $stmt->execute([$name, $email, $hashedPassword]);

        // Redirect to a success page or login page
        $_SESSION['success'] = 'Registration successful. You can now log in.';
        header('Location: sign-in.php');
        exit;
    } catch (PDOException $e) {
        // Handle any errors during the database operation
        $_SESSION['error'] = 'Registration failed: ' . $e->getMessage();
        header('Location: sign-up.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Feedora - Sign up to discover, cook & share delicious recipes">
  <meta name="theme-color" content="#ED5A2C">
  <title>Sign Up - Feedora</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="fonts.css">
  <link rel="stylesheet" href="Home.css">
  <!-- Favicon -->
  <link rel="icon" href="images/Frame 1171277973.svg" type="image/svg+xml">
  <style>
    /* Additional styles specific to the sign-up page */
    .signup-container {
      display: flex;
      background-color: white;
      border-radius: var(--border-radius);
      box-shadow: var(--box-shadow);
      overflow: hidden;
      margin: 40px auto;
      max-width: 1200px;
    }
    
    .signup-image-container {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      background-color: #f9f9f9;
      border-radius: var(--border-radius) 0 0 var(--border-radius);
    }
    
    .signup-image {
      max-width: 100%;
      height: auto;
      border-radius: var(--border-radius);
    }
    
    .signup-form-container {
      flex: 1;
      padding: 40px;
    }
    
    .signup-title {
      font-size: 2.5rem;
      margin-bottom: 10px;
      color: #333;
      font-family: 'DM Serif Display', serif;
    }
    
    .account-text {
      color: var(--primary-color);
      font-style: italic;
    }
    
    .signup-subtitle {
      color: #666;
      margin-bottom: 30px;
    }
    
    .form-group {
      margin-bottom: 20px;
    }
    
    .form-group label {
      display: block;
      margin-bottom: 5px;
      font-weight: 500;
      color: #333;
    }
    
    .form-group input {
      width: 100%;
      padding: 12px;
      border: 1px solid #ddd;
      border-radius: 8px;
      font-size: 16px;
      transition: border-color 0.3s;
    }
    
    .form-group input:focus {
      border-color: var(--primary-color);
      outline: none;
    }
    
    .remember-me {
      display: flex;
      align-items: center;
      margin-bottom: 20px;
    }
    
    .remember-me input {
      margin-right: 10px;
    }
    
    .login-button {
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
    }
    
    .login-button:hover {
      background-color: #d94e20;
    }
    
    .social-login {
      margin-top: 30px;
      text-align: center;
    }
    
    .social-login-title {
      position: relative;
      margin-bottom: 20px;
      color: #666;
    }
    
    .social-login-title::before,
    .social-login-title::after {
      content: '';
      position: absolute;
      top: 50%;
      width: 30%;
      height: 1px;
      background-color: #ddd;
    }
    
    .social-login-title::before {
      left: 0;
    }
    
    .social-login-title::after {
      right: 0;
    }
    
    .social-icons {
      display: flex;
      justify-content: center;
      gap: 15px;
    }
    
    .social-icon {
      width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
      background-color: #f5f5f5;
      transition: background-color 0.3s;
    }
    
    .social-icon:hover {
      background-color: #e0e0e0;
    }
    
    .signup-link {
      margin-top: 20px;
      text-align: center;
      color: #666;
    }
    
    .signup-link a {
      color: var(--primary-color);
      text-decoration: none;
      font-weight: 600;
    }
    
    .signup-link a:hover {
      text-decoration: underline;
    }
    
    .error-message {
      color: #e74c3c;
      background-color: #fdecea;
      padding: 10px;
      border-radius: 5px;
      margin-bottom: 20px;
    }
    
    .success-message {
      color: #2ecc71;
      background-color: #e8f8f5;
      padding: 10px;
      border-radius: 5px;
      margin-bottom: 20px;
    }
    
    @media (max-width: 768px) {
      .signup-container {
        flex-direction: column;
      }
      
      .signup-image-container {
        display: none;
      }
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
      <a href="recipes.php" class="nav-link">Recipes</a>
      <a href="live.php" class="nav-link">Live Sessions</a>
      <a href="dashboard.php" class="nav-link">Community</a>

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
      <a href="sign-up.php" class="signup-button">Sign up</a>
      <a href="sign-in.php" class="signin-button">Sign in</a>
    </div>

    <button class="mobile-menu-button menu-toggle" aria-label="Open menu" aria-expanded="false" aria-controls="mobileNav">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="3" y1="12" x2="21" y2="12"></line>
        <line x1="3" y1="6" x2="21" y2="6"></line>
        <line x1="3" y1="18" x2="21" y2="18"></line>
      </svg>
    </button>

    <div class="mobile-nav mobile-menu" id="mobileNav" aria-hidden="true">
      <div class="mobile-nav-header">
        <div class="logo-container">
          <img src="images/Feedora-logo.svg" alt="Feedora Icon" class="mobile-logo">
        </div>
        <button class="close-menu-button" aria-label="Close menu">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </button>
      </div>
      <div class="mobile-nav-content">
        <div class="mobile-search">
          <input type="text" placeholder="Search" class="mobile-search-input">
          <button class="mobile-search-button">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="11" cy="11" r="8"></circle>
              <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
          </button>
        </div>
        <nav class="mobile-nav-links">
          <a href="home.php" class="mobile-nav-link">Home</a>
          <a href="recipe.php" class="mobile-nav-link">Recipes</a>
          <a href="live.php" class="mobile-nav-link">Live Sessions</a>
          <a href="dashboard.php" class="mobile-nav-link">Community</a>
        </nav>
        <div class="mobile-auth-buttons">
          <a href="sign-up.php" class="mobile-signup-button">Sign up</a>
          <a href="sign-in.php" class="mobile-signin-button">Sign in</a>
        </div>
      </div>
    </div>
  </header>

  <main>
    <div class="signup-container">
      <div class="signup-image-container">
        <img src="images/Chef 3.png" alt="Chef illustration" class="signup-image">
      </div>
      <div class="signup-form-container">
        <h1 class="signup-title">Sign Up to access your <span class="account-text">account</span></h1>
        <p class="signup-subtitle">Greetings upon your return! Please enter your details at your convenience.</p>
        
        <?php if(isset($_SESSION['error'])): ?>
          <div class="error-message">
            <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
          </div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['success'])): ?>
          <div class="success-message">
            <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
          </div>
        <?php endif; ?>
        
        <form action="sign-up.php" method="POST">
          <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" placeholder="Enter your username" required>
          </div>
          
          <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" placeholder="Enter your email" required>
          </div>
          
          <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="Enter your password" required>
          </div>
          
          <button type="submit" class="login-button">Sign Up</button>
        </form>
        
        <div class="signup-link">
          <p>Already have an account? <a href="sign-in.php">Sign in</a></p>
        </div>
      </div>
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
          <a href="https://web.facebook.com/profile.php?id=61576878007803" class="social-link facebook">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path>
            </svg>
          </a>
          <a href="https://www.instagram.com/feedoracook/" class="social-link instagram">
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
            <li><a href="home.php" class="footer-link">Home</a></li>
            <li><a href="recipe.php" class="footer-link">Recipes</a></li>
            <li><a href="live.php" class="footer-link">Live Sessions</a></li>
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
</body>
</html>