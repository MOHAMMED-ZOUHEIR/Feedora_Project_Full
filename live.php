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

// Get user information
$userId = $_SESSION['user_id'];
$profileImage = $_SESSION['profile_image'] ?? 'images/default-avatar.png';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Feedora - Live Cooking Sessions Coming Soon">
    <meta name="theme-color" content="#ED5A2C">
    <title>Live Cooking - Feedora</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:wght@400;500&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="fonts.css">
    <link rel="stylesheet" href="Home.css">
    <!-- Favicon -->
    <link rel="icon" href="images/Frame 1171277973.svg" type="image/svg+xml">
    <style>
        :root {
            --primary-color: #ED5A2C;
            --primary-light: #FF6B3D;
            --primary-dark: #D4491F;
            --accent-color: #FFB800;
            --secondary-color: #2C5ED8;
            --success-color: #00C851;
            --sidebar-width: 250px;
            --glass-bg: rgba(255, 255, 255, 0.15);
            --glass-border: rgba(255, 255, 255, 0.3);
            --shadow-primary: 0 25px 50px rgba(237, 90, 44, 0.4);
            --shadow-glass: 0 8px 32px rgba(0, 0, 0, 0.15);
        }

        * {
            margin: 0;
            padding: 0;
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
        }

        
        /* Decorative Elements */
        .decorative-circles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
        }
        
        .circle {
            position: absolute;
            border-radius: 50%;
            opacity: 0.1;
            animation: float 20s infinite ease-in-out;
        }
        
        .circle:nth-child(1) {
            width: 300px;
            height: 300px;
            background: var(--primary-color);
            top: -150px;
            right: -150px;
            animation-delay: 0s;
        }
        
        .circle:nth-child(2) {
            width: 200px;
            height: 200px;
            background: var(--accent-color);
            bottom: -100px;
            left: -100px;
            animation-delay: 5s;
        }
        
        .circle:nth-child(3) {
            width: 150px;
            height: 150px;
            background: var(--secondary-color);
            top: 30%;
            left: 10%;
            animation-delay: 10s;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            33% { transform: translateY(-20px) rotate(120deg); }
            66% { transform: translateY(10px) rotate(240deg); }
        }
        
        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 40px 20px;
            z-index: 2;
        }
        
        /* Mobile Header Styles */
        .mobile-header {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 70px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            z-index: 100;
            padding: 0 20px;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border-bottom: 1px solid rgba(237, 90, 44, 0.1);
        }
        
        .hamburger-menu {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            width: 26px;
            height: 20px;
            cursor: pointer;
            z-index: 101;
            padding: 2px;
        }
        
        .hamburger-menu span {
            display: block;
            height: 3px;
            width: 100%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            border-radius: 3px;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        
        .hamburger-menu.active span:nth-child(1) {
            transform: translateY(8.5px) rotate(45deg);
        }
        
        .hamburger-menu.active span:nth-child(2) {
            opacity: 0;
            transform: scaleX(0);
        }
        
        .hamburger-menu.active span:nth-child(3) {
            transform: translateY(-8.5px) rotate(-45deg);
        }
        
        .header-title {
            font-size: 1.5rem;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 500;
            text-shadow: 0 2px 10px rgba(237, 90, 44, 0.2);
        }
        
        .header-profile {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid transparent;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            padding: 2px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .header-profile:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 25px rgba(237, 90, 44, 0.3);
        }
        
        .header-profile img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            background: white;
        }
        
        /* Enhanced Coming Soon Container */
        .coming-soon-container {
            position: relative;
            z-index: 10;
            max-width: 900px;
            width: 100%;
            text-align: center;
            padding: 40px;
        }
        
        /* Glass Card Effect */
        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 30px;
            padding: 50px 40px;
            box-shadow: var(--shadow-glass);
            position: relative;
            overflow: hidden;
            transition: all 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
        
        .glass-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.8s ease;
        }
        
        .glass-card:hover::before {
            left: 100%;
        }
        
        .glass-card:hover {
            transform: translateY(-15px) scale(1.02);
            box-shadow: 0 35px 80px rgba(237, 90, 44, 0.25);
            border-color: rgba(255, 255, 255, 0.5);
        }
        
        /* Coming Soon Text */
        .coming-soon-text {
            margin-bottom: 30px;
        }
        
        .main-title {
            font-size: clamp(2.5rem, 5vw, 4rem);
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light), var(--accent-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 20px;
            font-weight: 500;
            letter-spacing: -0.02em;
            line-height: 1.1;
            animation: titleGlow 3s ease-in-out infinite alternate;
        }
        
        @keyframes titleGlow {
            0% { filter: drop-shadow(0 0 10px rgba(237, 90, 44, 0.3)); }
            100% { filter: drop-shadow(0 0 20px rgba(237, 90, 44, 0.6)); }
        }
        
        .subtitle {
            font-size: 1.25rem;
            color: rgba(0, 0, 0, 0.7);
            font-weight: 400;
            margin-bottom: 15px;
            letter-spacing: 0.5px;
        }
        
        .description {
            font-size: 1rem;
            color: rgba(0, 0, 0, 0.6);
            line-height: 1.6;
            max-width: 500px;
            margin: 0 auto 40px;
        }
        
        /* Coming Soon Image */
        .coming-soon-image {
            max-width: 100%;
            height: auto;
            border-radius: 25px;
            box-shadow: var(--shadow-primary);
            transition: all 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            position: relative;
            z-index: 2;
            filter: brightness(1.05) contrast(1.1);
        }
        
        .coming-soon-image:hover {
            transform: translateY(-8px) scale(1.03);
            box-shadow: 0 40px 90px rgba(237, 90, 44, 0.4);
            filter: brightness(1.1) contrast(1.15);
        }
        
        /* Feature Pills */
        .feature-pills {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 40px;
        }
        
        .pill {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            padding: 12px 24px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 500;
            box-shadow: 0 8px 25px rgba(237, 90, 44, 0.3);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .pill::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.6s ease;
        }
        
        .pill:hover::before {
            left: 100%;
        }
        
        .pill:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 12px 35px rgba(237, 90, 44, 0.4);
        }
        
        /* Enhanced Animated Background */
        .animated-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 1;
            pointer-events: none;
        }
        
        .gradient-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 120%;
            height: 120%;
            background: linear-gradient(
                135deg,
                rgba(237, 90, 44, 0.1) 0%,
                rgba(255, 107, 61, 0.08) 25%,
                rgba(255, 184, 0, 0.06) 50%,
                rgba(44, 94, 216, 0.05) 75%,
                rgba(237, 90, 44, 0.08) 100%
            );
            background-size: 400% 400%;
            animation: gradientFlow 20s ease infinite;
            transform: rotate(-5deg);
        }
        
        @keyframes gradientFlow {
            0% { background-position: 0% 50%; transform: rotate(-5deg) scale(1); }
            25% { background-position: 100% 25%; transform: rotate(-3deg) scale(1.02); }
            50% { background-position: 50% 100%; transform: rotate(-7deg) scale(0.98); }
            75% { background-position: 25% 0%; transform: rotate(-4deg) scale(1.01); }
            100% { background-position: 0% 50%; transform: rotate(-5deg) scale(1); }
        }
        
        .food-item {
            position: absolute;
            opacity: 0;
            animation: floatFood 18s linear infinite;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.1));
            user-select: none;
        }
        
        @keyframes floatFood {
            0% {
                transform: translateY(100vh) rotate(0deg) scale(0.3);
                opacity: 0;
            }
            5% {
                opacity: 0.8;
            }
            15% {
                opacity: 1;
                transform: translateY(85vh) rotate(45deg) scale(0.6);
            }
            85% {
                opacity: 0.9;
                transform: translateY(15vh) rotate(315deg) scale(0.9);
            }
            95% {
                opacity: 0.3;
            }
            100% {
                transform: translateY(-10vh) rotate(360deg) scale(1.1);
                opacity: 0;
            }
        }
        
        /* Sidebar Overlay */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(5px);
            z-index: 95;
            display: none;
            opacity: 0;
            transition: all 0.4s ease;
        }
        
        .sidebar-overlay.active {
            display: block;
            opacity: 1;
        }
        
        /* Responsive Styles */
        @media (max-width: 768px) {
            .mobile-header {
                display: flex;
                height: 65px;
            }
            
            .main-content {
                margin-left: 0;
                padding-top: 85px;
                padding: 85px 15px 40px;
            }
            
            .glass-card {
                padding: 35px 25px;
                border-radius: 25px;
            }
            
            .coming-soon-container {
                padding: 20px;
            }
            
            .feature-pills {
                margin-top: 30px;
                gap: 10px;
            }
            
            .pill {
                padding: 10px 20px;
                font-size: 0.85rem;
            }
            
            /* Handle sidebar on mobile */
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                z-index: 99;
                transition: transform 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
        }
        
        @media (max-width: 480px) {
            .main-title {
                font-size: 2.2rem;
            }
            
            .subtitle {
                font-size: 1.1rem;
            }
            
            .description {
                font-size: 0.95rem;
            }
            
            .glass-card {
                padding: 25px 20px;
            }
        }
        
        /* Loading Animation */
        @keyframes pulseGlow {
            0% { box-shadow: 0 0 0 0 rgba(237, 90, 44, 0.4); }
            70% { box-shadow: 0 0 0 20px rgba(237, 90, 44, 0); }
            100% { box-shadow: 0 0 0 0 rgba(237, 90, 44, 0); }
        }
        
        .coming-soon-image {
            animation: pulseGlow 3s infinite;
        }
    </style>
</head>

<body>
    <?php include('sidebar.php'); ?>
    
    <!-- Decorative Background Elements -->
    <div class="decorative-circles">
        <div class="circle"></div>
        <div class="circle"></div>
        <div class="circle"></div>
    </div>
    
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebar-overlay"></div>
    
    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="hamburger-menu" id="hamburger-menu">
            <span></span>
            <span></span>
            <span></span>
        </div>
        <div class="header-title">Live Cooking</div>
        <div class="header-profile">
            <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Profile">
        </div>
    </div>

    <!-- Enhanced Animated Background -->
    <div class="animated-bg">
        <div class="gradient-bg"></div>
        <!-- Food items will be added here via JavaScript -->
    </div>

    <!-- Main Content -->
    <main class="main-content">
        <div class="coming-soon-container">
            <div class="glass-card">
                <div class="coming-soon-text">
                    <h1 class="main-title">Live Cooking</h1>
                    <h2 class="subtitle">Coming Soon</h2>
                    <p class="description">
                        Get ready for an immersive culinary experience! Join live cooking sessions with professional chefs, 
                        learn new recipes, and cook along in real-time.
                    </p>
                </div>
                
                <img src="images/Coming-Soon.png" alt="Live Cooking Coming Soon" class="coming-soon-image" 
                     onerror="this.src='images/coming-soon-placeholder.png'">
                
                <div class="feature-pills">
                    <div class="pill">🔴 Live Sessions</div>
                    <div class="pill">👨‍🍳 Expert Chefs</div>
                    <div class="pill">📚 Step-by-Step</div>
                    <div class="pill">🎯 Interactive</div>
                </div>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile hamburger menu functionality
            const hamburgerMenu = document.getElementById('hamburger-menu');
            const sidebar = document.querySelector('.sidebar');
            const sidebarOverlay = document.getElementById('sidebar-overlay');
            
            if (hamburgerMenu && sidebar && sidebarOverlay) {
                // Toggle sidebar on hamburger click
                hamburgerMenu.addEventListener('click', function() {
                    hamburgerMenu.classList.toggle('active');
                    sidebar.classList.toggle('active');
                    sidebarOverlay.classList.toggle('active');
                    
                    // Prevent body scrolling when sidebar is open
                    if (sidebar.classList.contains('active')) {
                        document.body.style.overflow = 'hidden';
                    } else {
                        document.body.style.overflow = '';
                    }
                });
                
                // Close sidebar when clicking on overlay
                sidebarOverlay.addEventListener('click', function() {
                    hamburgerMenu.classList.remove('active');
                    sidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                    document.body.style.overflow = '';
                });
                
                // Close sidebar on window resize if mobile view is exited
                window.addEventListener('resize', function() {
                    if (window.innerWidth > 768) {
                        hamburgerMenu.classList.remove('active');
                        sidebar.classList.remove('active');
                        sidebarOverlay.classList.remove('active');
                        document.body.style.overflow = '';
                    }
                });
            }
            
            // Enhanced animated food items
            const animatedBg = document.querySelector('.animated-bg');
            const foodItems = [
                '🍕', '🍔', '🍟', '🌮', '🍣', '🍜', '🍱', '🥗', '🍲', 
                '🍝', '🍛', '🍤', '🍗', '🥩', '🍖', '🧁', '🍰', '🍪',
                '🥘', '🍳', '🥞', '🧇', '🥯', '🍞', '🥖', '🥨', '🧀',
                '🥑', '🍅', '🥕', '🌶️', '🫒', '🥬', '🥒', '🌽'
            ];
            
            // Create initial food items
            for (let i = 0; i < 30; i++) {
                setTimeout(() => createFoodItem(), i * 500);
            }
            
            // Continue creating food items at intervals
            setInterval(createFoodItem, 1500);
            
            function createFoodItem() {
                const food = document.createElement('div');
                food.className = 'food-item';
                
                // Random food emoji
                const randomFood = foodItems[Math.floor(Math.random() * foodItems.length)];
                food.textContent = randomFood;
                
                // Random properties for more variety
                const size = Math.floor(Math.random() * 35) + 25; // 25-60px
                const left = Math.floor(Math.random() * 100); // 0-100%
                const animationDuration = Math.floor(Math.random() * 12) + 12; // 12-24s
                const delay = Math.floor(Math.random() * 8); // 0-8s
                const opacity = (Math.random() * 0.4) + 0.6; // 0.6-1.0
                
                food.style.cssText = `
                    left: ${left}%;
                    font-size: ${size}px;
                    animation-duration: ${animationDuration}s;
                    animation-delay: ${delay}s;
                    --max-opacity: ${opacity};
                `;
                
                animatedBg.appendChild(food);
                
                // Remove food item after animation completes
                setTimeout(() => {
                    if (food.parentNode === animatedBg) {
                        animatedBg.removeChild(food);
                    }
                }, (animationDuration + delay) * 1000);
            }
            
            // Add parallax effect to glass card
            const glassCard = document.querySelector('.glass-card');
            
            document.addEventListener('mousemove', function(e) {
                if (window.innerWidth > 768) {
                    const { clientX, clientY } = e;
                    const { innerWidth, innerHeight } = window;
                    
                    const xPercent = (clientX / innerWidth - 0.5) * 2;
                    const yPercent = (clientY / innerHeight - 0.5) * 2;
                    
                    glassCard.style.transform = `
                        perspective(1000px) 
                        rotateY(${xPercent * 2}deg) 
                        rotateX(${-yPercent * 2}deg)
                        translateZ(0)
                    `;
                }
            });
            
            // Reset transform on mouse leave
            document.addEventListener('mouseleave', function() {
                glassCard.style.transform = 'perspective(1000px) rotateY(0deg) rotateX(0deg) translateZ(0)';
            });
        });
    </script>
</body>

</html>