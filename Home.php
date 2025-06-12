<?php
require_once 'config/config.php'; // Include the database connection script
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Feedora - Discover, Cook & Share delicious recipes from around the world">
  <meta name="theme-color" content="#ED5A2C">
  <title>Feedora - Discover, Cook & Share</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="fonts.css">
  <link rel="stylesheet" href="Home.css">
  <!-- Favicon -->
  <link rel="icon" href="images/Frame 1171277973.svg" type="image/svg+xml">
</head>

<body>
  <!-- Loading Screen -->
  <div id="loading-screen" aria-hidden="true">
    <div class="loader-container">
      <img src="images/Pizza (8).png" alt="Loading" class="loader-image">
    </div>
    <p class="visually-hidden">Loading Feedora</p>
  </div>
  <!-- Reaction Icons Container (Moved to body level) -->
  <div id="global-reaction-container" class="global-reaction-container">
    <div class="reaction-icons">
      <button class="reaction-icon" data-reaction="yummy" title="Yummy">
        <img src="images/food-icons/pizza.png" alt="Yummy" onerror="this.src='https://cdn-icons-png.flaticon.com/512/3132/3132693.png'">
      </button>
      <button class="reaction-icon" data-reaction="delicious" title="Delicious">
        <img src="images/food-icons/burger.png" alt="Delicious" onerror="this.src='https://cdn-icons-png.flaticon.com/512/3075/3075977.png'">
      </button>
      <button class="reaction-icon" data-reaction="spicy" title="Spicy">
        <img src="images/food-icons/chili.png" alt="Spicy" onerror="this.src='https://cdn-icons-png.flaticon.com/512/2518/2518224.png'">
      </button>
      <button class="reaction-icon" data-reaction="sweet" title="Sweet">
        <img src="images/food-icons/cake.png" alt="Sweet" onerror="this.src='https://cdn-icons-png.flaticon.com/512/3361/3361431.png'">
      </button>
      <button class="reaction-icon" data-reaction="recipe" title="Save Recipe">
        <img src="images/food-icons/cookbook.png" alt="Save Recipe" onerror="this.src='https://cdn-icons-png.flaticon.com/512/1046/1046857.png'">
      </button>
    </div>
  </div>

  <?php if (isset($connectionError)): ?>
    <div style="background-color: #f44336; color: white; padding: 10px; text-align: center; font-weight: bold; position: fixed; top: 0; left: 0; right: 0; z-index: 9999;">
      Database connection failed: <?php echo htmlspecialchars($connectionError); ?>
    </div>
  <?php endif; ?>
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
      <a href="./sign-in.php" class="signin-button">Sign in</a>
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
          <a href="Home.php" class="mobile-nav-link">Home</a>
          <a href="recipes.php" class="mobile-nav-link">Recipes</a>
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
    <section class="hero-section">
      <div class="hero-container">
        <div class="hero-content">
          <h1 class="hero-title">
            Discover, Cook
            <span class="hero-ampersand">&</span> Share
            <span class="hero-subtitle">Best Recipes</span>
            <img src="images/Frame 1171277973.svg" alt="Decorative Icon" class="title-icon">
          </h1>
          <div class="hero-bottom-content">
            <div class="pizza-container">
              <img src="images/Pizza (8).png" alt="Pizza" class="pizza-image">
            </div>
            <div class="content-box">
              <p class="hero-description">Join our cooking community and master new recipes every day.</p>
              <div class="hero-buttons">
                <a href="live.php" class="hero-button primary-button">Join a Live <span class="arrow-icon">→</span></a>
                <a href="recipes.php" class="hero-button secondary-button">Explore Recipes <span class="recipe-icon">📋</span></a>
              </div>
            </div>
          </div>
        </div>
        <div class="hero-image-container">
          <img src="images/Food Recipe.svg" alt="Food Recipe Illustration" class="hero-image">
        </div>
      </div>
    </section>
    <!-- Recipe of the Week Section -->
    <section class="recipe-of-week-section">
      <div class="recipe-container">
        <div class="recipe-header">
          <div class="recipe-title-container">
            <h2 class="recipe-title">Recipe Of The Week</h2>
            <img src="images/Frame 1171277973.svg" alt="Decorative Icon" class="recipe-accent-icon">
          </div>
          <p class="recipe-description">Our top pick this week—an inspired dish that's simple to make, bursting with flavor, and sure to impress at your next meal.</p>
        </div>

        <div class="recipe-slider-container">
          <div class="recipe-slider">
            <!-- Recipe Card 1 -->
            <div class="recipe-card">
              <div class="recipe-number">1</div>
              <div class="recipe-image-container">
                <img src="images/Rfissa+Express+-6.jpg" alt="Rfissa Express" class="recipe-image">
              </div>
              <h3 class="recipe-card-title">Rfissa Express – Fragrant chicken and lentils with papardelle</h3>
            </div>

            <!-- Recipe Card 2 -->
            <div class="recipe-card">
              <div class="recipe-number">2</div>
              <div class="recipe-image-container">
                <img src="images/zaalouk+dip.jpg" alt="Zaalouk Dip" class="recipe-image">
              </div>
              <h3 class="recipe-card-title">Zaalouk Dip</h3>
            </div>

            <!-- Recipe Card 3 -->
            <div class="recipe-card">
              <div class="recipe-number">3</div>
              <div class="recipe-image-container">
                <img src="images/apple+baghrir_-7.jpg" alt="Baghrir pancakes" class="recipe-image">
              </div>
              <h3 class="recipe-card-title">Baghrir pancakes with brown butter spiced apples</h3>
            </div>

            <!-- Recipe Card 4 -->
            <div class="recipe-card">
              <div class="recipe-number">4</div>
              <div class="recipe-image-container">
                <img src="images/image-asset.jpeg" alt="Chocolate and Almond Ghriba" class="recipe-image">
              </div>
              <h3 class="recipe-card-title">Chocolate and Almond Ghriba</h3>
            </div>
          </div>

          <!-- Slider Navigation -->
          <div class="slider-navigation">
            <button class="slider-prev">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M15 18l-6-6 6-6" />
              </svg>
            </button>
            <div class="slider-dots">
              <span class="dot active" data-index="0"></span>
              <span class="dot" data-index="1"></span>
              <span class="dot" data-index="2"></span>
              <span class="dot" data-index="3"></span>
            </div>
            <button class="slider-next">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 18l6-6-6-6" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- Live Cooking Sessions Section -->
    <section class="live-cooking-section">
      <div class="live-cooking-container">
        <div class="live-cooking-header">
          <div class="live-title-container">
            <h2 class="live-title">Live Cooking Sessions</h2>
            <img src="images/Frame 1171277973.svg" alt="Decorative Icon" class="live-accent-icon">
          </div>
          <p class="live-description">Join our expert chefs in real time—catch the next session's countdown, reserve your spot, and never miss a moment of hands-on culinary action.</p>
        </div>

        <div class="live-tabs">
          <button class="live-tab active" data-tab="live">Live</button>
          <button class="live-tab" data-tab="upcoming">Upcoming</button>
        </div>

        <div class="live-slider-container">
          <!-- Live Sessions Slider -->
          <div class="live-slider" id="live-sessions-slider">
            <!-- Live Session 1 -->
            <div class="live-card">
              <div class="live-image-container">
                <img src="images/Firefly_-imagine https---contais3.s3.amazonaws.com-public-original_images-vZcXwLUkGj0T1r4et6K 275575.jpg" alt="Crispy Fried Chicken" class="live-image">
                <div class="live-badge">
                  <span class="live-indicator"></span>
                  Live
                </div>
                <div class="live-viewers">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                  1.2K
                </div>
              </div>
              <h3 class="live-card-title">How To make easy Crispy Fried Chicken</h3>
              <div class="chef-info">
                <img src="images/Cooking_1.svg" alt="Chef Gordon Ra" class="chef-avatar">
                <div class="chef-details">
                  <span class="chef-name">Gordon Ra</span>
                  <span class="chef-title">Chef</span>
                </div>
              </div>
            </div>

            <!-- Live Session 2 -->
            <div class="live-card">
              <div class="live-image-container">
                <img src="images/Firefly_-imagine https---contais3.s3.amazonaws.com-public-original_images-7EqeUk5bLytzTvFhI0e 747404.jpg" alt="Pineapple Strainer Cocktail" class="live-image">
                <div class="live-badge">
                  <span class="live-indicator"></span>
                  Live
                </div>
                <div class="live-viewers">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                  1.5K
                </div>
              </div>
              <h3 class="live-card-title">How to make Pineapple Strainer Cocktail</h3>
              <div class="chef-info">
                <img src="images/Cooking_5.svg" alt="Chef Quique" class="chef-avatar">
                <div class="chef-details">
                  <span class="chef-name">Quique</span>
                  <span class="chef-title">Chef</span>
                </div>
              </div>
            </div>

            <!-- Live Session 3 -->
            <div class="live-card">
              <div class="live-image-container">
                <img src="images/UaaovMo6khVpYfMleLpa0VBhoiQQAHCMBozIHx7x.jpg" alt="Quick and Easy Chicken Spaghetti" class="live-image">
                <div class="live-badge">
                  <span class="live-indicator"></span>
                  Live
                </div>
                <div class="live-viewers">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                  2.3K
                </div>
              </div>
              <h3 class="live-card-title">How to make easy Quick and Easy Chicken Spaghetti</h3>
              <div class="chef-info">
                <img src="images/Cooking_9.svg" alt="Chef Yannick" class="chef-avatar">
                <div class="chef-details">
                  <span class="chef-name">Yannick</span>
                  <span class="chef-title">Chef</span>
                </div>
              </div>
            </div>

            <!-- Live Session 4 -->
            <div class="live-card">
              <div class="live-image-container">
                <img src="images/AlucLNmdregb2ySpPUfyut4HuPvF2BGTaDPsI7MW.jpg" alt="Georgia Pot Roast" class="live-image">
                <div class="live-badge">
                  <span class="live-indicator"></span>
                  Live
                </div>
                <div class="live-viewers">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                  4.6K
                </div>
              </div>
              <h3 class="live-card-title">How to make Georgia Pot Roast</h3>
              <div class="chef-info">
                <img src="images/Cooking_12.svg" alt="Chef Knuckles" class="chef-avatar">
                <div class="chef-details">
                  <span class="chef-name">Knuckles</span>
                  <span class="chef-title">Chef</span>
                </div>
              </div>
            </div>

            <!-- Live Session 5 -->
            <div class="live-card">
              <div class="live-image-container">
                <img src="images/9xk2WYdTHkOkRaClvqi16bf28I9adxogVmfFjxnY.jpg" alt="Yummy Sweet Potato Casserole" class="live-image">
                <div class="live-badge">
                  <span class="live-indicator"></span>
                  Live
                </div>
                <div class="live-viewers">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                  3.1K
                </div>
              </div>
              <h3 class="live-card-title">How to make Yummy Sweet Potato Casserole</h3>
              <div class="chef-info">
                <img src="images/Cooking_10.svg" alt="Chef Quique" class="chef-avatar">
                <div class="chef-details">
                  <span class="chef-name">Quique</span>
                  <span class="chef-title">Chef</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Upcoming Sessions Slider (initially hidden) -->
          <div class="live-slider" id="upcoming-sessions-slider" style="display: none;">
            <!-- Upcoming Session 1 -->
            <div class="live-card">
              <div class="live-image-container">
                <img src="images/apple+baghrir_-7.jpg" alt="Baghrir Pancakes" class="live-image">
                <div class="upcoming-badge">
                  <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                  </svg>
                  Tomorrow, 2PM
                </div>
              </div>
              <h3 class="live-card-title">Baghrir Pancakes with Brown Butter Spiced Apples</h3>
              <div class="chef-info">
                <img src="images/Cooking_12.svg" alt="Chef Knuckles" class="chef-avatar">
                <div class="chef-details">
                  <span class="chef-name">Knuckles</span>
                  <span class="chef-title">Chef</span>
                </div>
              </div>
            </div>

            <!-- Upcoming Session 2 -->
            <div class="live-card">
              <div class="live-image-container">
                <img src="images/bEeEOjt3fCQGMuYywteM8WNsM7EdOWIeToNisZgy.jpg" alt="Moroccan Tagine" class="live-image">
                <div class="upcoming-badge">
                  <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                  </svg>
                  Friday, 6PM
                </div>
              </div>
              <h3 class="live-card-title">Authentic Moroccan Tagine with Preserved Lemons</h3>
              <div class="chef-info">
                <img src="images/Cooking_11.svg" alt="Chef Maria" class="chef-avatar">
                <div class="chef-details">
                  <span class="chef-name">Maria</span>
                  <span class="chef-title">Chef</span>
                </div>
              </div>
            </div>

            <!-- Upcoming Session 3 -->
            <div class="live-card">
              <div class="live-image-container">
                <img src="images/zaalouk+dip.jpg" alt="Zaalouk Dip" class="live-image">
                <div class="upcoming-badge">
                  <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                  </svg>
                  Saturday, 1PM
                </div>
              </div>
              <h3 class="live-card-title">Zaalouk Dip and Moroccan Mezze Platter</h3>
              <div class="chef-info">
                <img src="images/Cooking_9.svg" alt="Chef Yannick" class="chef-avatar">
                <div class="chef-details">
                  <span class="chef-name">Yannick</span>
                  <span class="chef-title">Chef</span>
                </div>
              </div>
            </div>

            <!-- Upcoming Session 4 -->
            <div class="live-card">
              <div class="live-image-container">
                <img src="images/apple+baghrir_-7.jpg" alt="Moroccan Breakfast" class="live-image">
                <div class="upcoming-badge">
                  <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                  </svg>
                  Sunday, 10AM
                </div>
              </div>
              <h3 class="live-card-title">Traditional Moroccan Breakfast Spread</h3>
              <div class="chef-info">
                <img src="images/Cooking_7.svg" alt="Chef Samira" class="chef-avatar">
                <div class="chef-details">
                  <span class="chef-name">Samira</span>
                  <span class="chef-title">Chef</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Live Slider Navigation -->
          <div class="live-slider-navigation">
            <button class="live-slider-prev">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M15 18l-6-6 6-6" />
              </svg>
            </button>
            <div class="live-slider-dots">
              <span class="live-dot active" data-index="0"></span>
              <span class="live-dot" data-index="1"></span>
              <span class="live-dot" data-index="2"></span>
              <span class="live-dot" data-index="3"></span>
              <span class="live-dot" data-index="4"></span>
            </div>
            <button class="live-slider-next">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 18l6-6-6-6" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- Statistics Section -->
    <section class="stats-section">
      <div class="stats-container">
        <div class="stat-card">
          <h3 class="stat-number">140K+</h3>
          <p class="stat-label">Our registered member</p>
        </div>

        <div class="stat-card">
          <h3 class="stat-number">100+</h3>
          <p class="stat-label">Master chef mentor</p>
        </div>
      </div>
    </section>

    <!-- Meet the Chefs Section -->
    <section class="chefs-section">
      <div class="chefs-container">
        <div class="chefs-header">
          <div class="chefs-title-wrapper">
            <h2 class="chefs-title">Meet the Chefs</h2>
            <img src="images/Frame 1171277973.svg" alt="Decorative Icon" class="chefs-accent-icon">
          </div>
          <p class="chefs-description">Discover the culinary artists behind every dish—browse their specialties, follow your favorites, and uncover each chef's signature creations at a glance.</p>
        </div>

        <div class="chefs-slider-container">
          <div class="chefs-slider" id="chefs-slider">
            <!-- Chef 1 -->
            <div class="chef-card">
              <div class="chef-image-container">
                <img src="images/vf_gordon_ramsay_5265.webp" alt="Gordon Ramsay" class="chef-image">
              </div>
              <div class="chef-info-container">
                <h4 class="chef-category">British Celebrity Chef</h4>
                <h3 class="chef-name">Gordon Ramsay</h3>
                <a href="/chef/gordon-ramsay" class="follow-button">
                  Follow
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                    <polyline points="12 5 19 12 12 19"></polyline>
                  </svg>
                </a>
              </div>
            </div>

            <!-- Chef 2 -->
            <div class="chef-card">
              <div class="chef-image-container">
                <img src="images/Choumicha-Lambassadrice-de-la-cuisine-marocaine-dans-le-monde-jpg.webp" alt="Choumicha Chafai" class="chef-image">
              </div>
              <div class="chef-info-container">
                <h4 class="chef-category">The Great Lady Of Moroccan cuisine</h4>
                <h3 class="chef-name">ChoumiCha Chafai</h3>
                <a href="/chef/choumicha-chafai" class="follow-button">
                  Follow
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                    <polyline points="12 5 19 12 12 19"></polyline>
                  </svg>
                </a>
              </div>
            </div>

            <!-- Chef 3 -->
            <div class="chef-card">
              <div class="chef-image-container">
                <img src="images/images.jpg" alt="Wolfgang Puck" class="chef-image">
              </div>
              <div class="chef-info-container">
                <h4 class="chef-category">Austrian Chef And Restaurateur</h4>
                <h3 class="chef-name">Wolfgang Puck</h3>
                <a href="/chef/wolfgang-puck" class="follow-button">
                  Follow
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                    <polyline points="12 5 19 12 12 19"></polyline>
                  </svg>
                </a>
              </div>
            </div>
          </div>

          <!-- Chefs Slider Navigation -->
          <div class="chefs-slider-navigation">
            <button class="chefs-slider-prev">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M15 18l-6-6 6-6" />
              </svg>
            </button>
            <div class="chefs-slider-dots">
              <span class="chefs-dot active" data-index="0"></span>
              <span class="chefs-dot" data-index="1"></span>
              <span class="chefs-dot" data-index="2"></span>
            </div>
            <button class="chefs-slider-next">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 18l6-6-6-6" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- Community Feed Section -->
    <section class="section-wrapper">
      <div class="community-feed-section">
        <div class="community-container">
          <div class="community-header">
            <div class="community-title-container">
              <h2 class="community-title">Community Feed</h2>
              <img src="images/Frame 1171277973.svg" alt="Decorative Icon" class="community-accent-icon">
            </div>
            <a href="post.php" class="post-now-button">
              Post Now
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 5v14M5 12h14"></path>
              </svg>
            </a>
          </div>

          <div class="community-slider-container">
            <div class="community-slider" id="community-slider">
              <!-- Post Card 1 -->
              <div class="post-card" id="post1">
                <div class="post-header">
                  <div class="post-user">
                    <img src="images/vf_gordon_ramsay_5265.webp" alt="Gordon Ramsay" class="post-user-avatar" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; display: block; border: 2px solid #ED5A2C;">
                    <div class="post-user-info">
                      <div class="post-username">Gordon Ramsay</div>
                      <div class="post-meta">
                        <span class="post-shared">shared a image</span>
                        <span class="post-dot">·</span>
                        <span class="post-time">Yesterday at 1:08 AM</span>
                      </div>
                    </div>
                  </div>
                  <button class="post-menu-button">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <circle cx="12" cy="12" r="1"></circle>
                      <circle cx="12" cy="5" r="1"></circle>
                      <circle cx="12" cy="19" r="1"></circle>
                    </svg>
                  </button>
                </div>
                <div class="post-image-container">
                  <img src="images/Firefly_-imagine https---contais3.s3.amazonaws.com-public-original_images-vZcXwLUkGj0T1r4et6K 275575.jpg" alt="Blackstone Griddle Grilled Nachos" class="post-image">
                </div>
                <div class="post-content">
                  <p class="post-description">Craving something delicious and cheesy? Try these irresistible Blackstone Griddle Grilled Nachos that are perfect for sharing! 🌮🧀</p>
                  <div class="post-stats">
                    <div class="post-likes">
                      <div class="like-button-container" data-post-id="post1">
                        <button class="like-button">
                          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
                          </svg>
                        </button>
                      </div>
                      <span class="likes-count">93.2k likes</span>
                    </div>
                    <div class="post-comments">
                      <button class="comment-button">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                        </svg>
                      </button>
                      <span class="comments-count">Comments (32)</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Post Card 2 -->
              <div class="post-card" id="post2">
                <div class="post-header">
                  <div class="post-user">
                    <img src="images/images.jpg" alt="Wolfgang Puck" class="post-user-avatar" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; display: block;">
                    <div class="post-user-info">
                      <div class="post-username">Wolfgang Puck</div>
                      <div class="post-meta">
                        <span class="post-shared">shared a image</span>
                        <span class="post-dot">·</span>
                        <span class="post-time">Yesterday at 1:28 AM</span>
                      </div>
                    </div>
                  </div>
                  <button class="post-menu-button">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <circle cx="12" cy="12" r="1"></circle>
                      <circle cx="12" cy="5" r="1"></circle>
                      <circle cx="12" cy="19" r="1"></circle>
                    </svg>
                  </button>
                </div>
                <div class="post-image-container">
                  <img src="images/Firefly_-imagine https---contais3.s3.amazonaws.com-public-original_images-7EqeUk5bLytzTvFhI0e 747404.jpg" alt="Sausage Gravy and Biscuits" class="post-image">
                </div>
                <div class="post-content">
                  <p class="post-description">Looking for a comforting breakfast? This Sausage Gravy and Biscuits recipe is your answer—creamy, savory, and oh-so-delicious! 🍳🥓</p>
                  <div class="post-stats">
                    <div class="post-likes">
                      <div class="like-button-container" data-post-id="post1">
                        <button class="like-button">
                          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
                          </svg>
                        </button>
                      </div>
                      <span class="likes-count">93.2k likes</span>
                    </div>
                    <div class="post-comments">
                      <button class="comment-button">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                        </svg>
                      </button>
                      <span class="comments-count">Comments (32)</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Post Card 3 -->
              <div class="post-card" id="post3">
                <div class="post-header">
                  <div class="post-user">
                    <img src="images/Choumicha-Lambassadrice-de-la-cuisine-marocaine-dans-le-monde-jpg.webp" alt="Choumicha Chafai" class="post-user-avatar" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; display: block; border: 2px solid #ED5A2C;">
                    <div class="post-user-info">
                      <div class="post-username">Choumicha Chafai</div>
                      <div class="post-meta">
                        <span class="post-shared">shared a image</span>
                        <span class="post-dot">·</span>
                        <span class="post-time">Yesterday at 2:15 PM</span>
                      </div>
                    </div>
                  </div>
                  <button class="post-menu-button">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <circle cx="12" cy="12" r="1"></circle>
                      <circle cx="12" cy="5" r="1"></circle>
                      <circle cx="12" cy="19" r="1"></circle>
                    </svg>
                  </button>
                </div>
                <div class="post-image-container">
                  <img src="images/zaalouk+dip.jpg" alt="Moroccan Zaalouk Dip" class="post-image">
                </div>
                <div class="post-content">
                  <p class="post-description">Get ready to impress your guests with this authentic Moroccan Zaalouk dip! The smoky eggplant and golden brown spices create a flavor explosion. #MoroccanCuisine</p>
                  <div class="post-stats">
                    <div class="post-likes">
                      <div class="like-button-container" data-post-id="post1">
                        <button class="like-button">
                          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
                          </svg>
                        </button>
                      </div>
                      <span class="likes-count">93.2k likes</span>
                    </div>
                    <div class="post-comments">
                      <button class="comment-button">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                        </svg>
                      </button>
                      <span class="comments-count">Comments (48)</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Community Navigation and Load More Container -->
            <div class="community-controls-container">
              <!-- Community Slider Navigation -->
              <div class="community-slider-navigation">
                <button class="community-slider-prev">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M15 18l-6-6 6-6" />
                  </svg>
                </button>
                <div class="community-slider-dots">
                  <span class="community-dot active" data-index="0"></span>
                  <span class="community-dot" data-index="1"></span>
                  <span class="community-dot" data-index="2"></span>
                </div>
                <button class="community-slider-next">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 18l6-6-6-6" />
                  </svg>
                </button>
              </div>

              <a href="dashboard.php" class="load-more-button">
                Load More
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M7 17l9.2-9.2M17 17V7H7" />
                </svg>
              </a>
            </div>
          </div>
        </div>
      </div>
    </section>



    <!-- How It Works Section -->
    <section class="section-wrapper">
      <div class="how-it-works-section">
        <div class="feature-container">
          <div class="feature-box how-it-works-box">
            <div class="how-it-works-container">
              <!-- Header row with title and description -->
              <div class="hiw-header-row">
                <div class="hiw-title-container">
                  <h2 class="hiw-title">How It Works</h2>
                  <img src="images/Frame 1171277973.svg" alt="How It Works Icon" class="hiw-icon">
                </div>

                <div class="hiw-description-container">
                  <p class="hiw-description">Create your free account to unlock the full experience.</p>
                </div>
              </div>

              <!-- Content row with buttons and image -->
              <div class="hiw-content-row">
                <!-- Buttons container -->
                <div class="hiw-buttons-container">
                  <a href="sign-up.php" class="hiw-signup-button">Sign up</a>
                  <a href="sign-in.php" class="hiw-signin-button">Sign in</a>
                </div>

                <!-- Image container -->
                <div class="hiw-image-container">
                  <img src="images/man is logged in.png" alt="User login illustration" class="hiw-image">
                </div>
              </div>
            </div>
          </div>

          <div class="explore-recipes-section">
            <div class="explore-container">
              <div class="explore-left">
                <img src="images/Cooking partner.svg" alt="Cooking partners" class="explore-image">
              </div>

              <div class="explore-right">
                <div class="explore-title-container">
                  <h2 class="explore-title">Explore & Share Your Favorite Recipes</h2>
                  <img src="images/Frame 1171277973.svg" alt="Icon" class="explore-title-icon">
                </div>

                <div class="explore-buttons">
                  <a href="recipes.php" class="explore-button">
                    <span>Explore Recipes</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M9 22h6c5 0 8-2 8-8V9c0-6-3-8-8-8H9C4 1 1 3 1 9v5c0 6 3 8 8 8z"></path>
                      <path d="M10 7v10l5-5-5-5z"></path>
                    </svg>
                  </a>

                  <a href="post.php" class="explore-button">
                    <span>Post Now</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <circle cx="12" cy="12" r="10"></circle>
                      <path d="M12 8v8"></path>
                      <path d="M8 12h8"></path>
                    </svg>
                  </a>
                </div>
              </div>
            </div>
          </div>

          <div class="live-sessions-section">
            <div class="live-container">
              <div class="live-left">
                <div class="live-title-container">
                  <div class="live-title-with-icon">
                    <h2 class="live-title">Join Live Sessions</h2>
                    <img src="images/Frame 1171277973.svg" alt="Icon" class="live-title-icon">
                  </div>
                </div>

                <div class="live-button-container">
                  <a href="live.php" class="live-button">
                    <span>Join now</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M5 12h14"></path>
                      <path d="M12 5l7 7-7 7"></path>
                    </svg>
                  </a>
                </div>
              </div>

              <div class="live-right">
                <img src="images/VNU_M698_15.jpg" alt="Live cooking session" class="live-image">
              </div>
            </div>
          </div>

          <div class="global-container">
            <div class="community-section">
              <div class="community-image-container">
                <img src="images/men and women working as chefs.jpg" alt="Chef community" class="community-image">
              </div>

              <div class="community-content">
                <div class="community-title-container">
                  <h2 class="community-title">Engage with the Community</h2>
                  <img src="images/Frame 1171277973.svg" alt="Icon" class="community-title-icon">
                </div>
                <p class="community-description">Like, comment, and connect with fellow food lovers.</p>
              </div>
            </div>
          </div>

          <div class="feature-box brand-box">
            <div class="feature-content">
              <img src="images/logo-feedora2.png" alt="Feedora Logo" class="brand-logo">
            </div>
          </div>
        </div>
      </div>
    </section>
    <!-- Testimonials Section -->
    <section class="section-wrapper">
      <div class="testimonials-section">
        <div class="testimonials-container">
          <div class="testimonials-header">
            <h2 class="testimonials-title">What Our Community Says About Us</h2>
            <img src="images/Frame 1171277973.svg" alt="Testimonials Icon" class="testimonials-icon">
          </div>

          <div class="testimonials-slider-container">
            <div class="testimonials-slider">
              <!-- Testimonial 1 -->
              <div class="testimonial-card">
                <div class="testimonial-content">
                  <div class="testimonial-quote">
                    <p>Feedora has transformed my home cooking—live sessions with top chefs taught me techniques I never thought I'd master!</p>
                  </div>
                  <div class="testimonial-author">
                    <p class="author-name">Sarah L.</p>
                    <p class="author-title">Home Cook & Food Blogger</p>
                  </div>
                </div>
              </div>

              <!-- Testimonial 2 -->
              <div class="testimonial-card">
                <div class="testimonial-content">
                  <div class="testimonial-quote">
                    <p>I love browsing recipes and saving my favorites. The community feedback helps me improve every dish.</p>
                  </div>
                  <div class="testimonial-author">
                    <p class="author-name">Marcus Y</p>
                    <p class="author-title">Amateur Chef</p>
                  </div>
                </div>
              </div>

              <!-- Testimonial 3 -->
              <div class="testimonial-card">
                <div class="testimonial-content">
                  <div class="testimonial-quote">
                    <p>Becoming a cook is my dream since I was little, and here I have found a way to make it happen!</p>
                  </div>
                  <div class="testimonial-author">
                    <p class="author-name">Amanda Valmont</p>
                    <p class="author-title">Culinary Arts Student</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Slider Navigation -->
            <div class="testimonials-navigation">
              <div class="testimonials-dots">
                <span class="testimonial-dot active" data-index="0"></span>
                <span class="testimonial-dot" data-index="1"></span>
                <span class="testimonial-dot" data-index="2"></span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
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
            <li><a href="Home.php" class="footer-link">Home</a></li>
            <li><a href="recipes.php" class="footer-link">Recipes</a></li>
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