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
$userProfileImage = $_SESSION['profile_image'] ?? 'images/default-profile.png';

// Check if recipe ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: recipes.php");
    exit();
}

$recipeId = $_GET['id'];

// Get recipe details
$recipeStmt = $pdo->prepare("
    SELECT r.*, c.NAME_CATEGORIE, d.DIFFICULTY_NAME, u.NAME as AUTHOR_NAME, u.PROFILE_IMAGE as AUTHOR_IMAGE 
    FROM RECIPES r 
    LEFT JOIN CATEGORIE c ON r.ID_CATEGORIE = c.ID_CATEGORIE 
    LEFT JOIN DIFFICULTY_RECIPES dr ON r.RECIPES_ID = dr.RECIPES_ID 
    LEFT JOIN DIFFICULTY d ON dr.DIFFICULTY_ID = d.DIFFICULTY_ID 
    LEFT JOIN USERS u ON r.USER_ID = u.USER_ID 
    WHERE r.RECIPES_ID = ?
");
$recipeStmt->execute([$recipeId]);
$recipe = $recipeStmt->fetch(PDO::FETCH_ASSOC);

// If recipe doesn't exist, redirect
if (!$recipe) {
    header("Location: recipes.php");
    exit();
}

// Get recipe ingredients
$ingredientsStmt = $pdo->prepare("
    SELECT i.INGREDIENTS_ID, i.NAME, iu.QUANTITY, u.UNIT_NAME 
    FROM RECIPE_INGREDIENTS ri 
    JOIN INGREDIENTS i ON ri.INGREDIENTS_ID = i.INGREDIENTS_ID 
    LEFT JOIN INGREDIENTS_UNIT iu ON i.INGREDIENTS_ID = iu.INGREDIENTS_ID 
    LEFT JOIN UNIT u ON iu.UNIT_ID = u.UNIT_ID 
    WHERE ri.RECIPES_ID = ?
");
$ingredientsStmt->execute([$recipeId]);
$ingredients = $ingredientsStmt->fetchAll(PDO::FETCH_ASSOC);

// Check if the current user has collected/saved this recipe
$isCollectedStmt = $pdo->prepare("SELECT * FROM COLLECT WHERE USER_ID = ? AND RECIPES_ID = ?");
$isCollectedStmt->execute([$userId, $recipeId]);
$isCollected = $isCollectedStmt->rowCount() > 0;

// Process save/unsave recipe action
if (isset($_POST['toggle_collect'])) {
    try {
        if ($isCollected) {
            // Remove from collection
            $removeStmt = $pdo->prepare("DELETE FROM COLLECT WHERE USER_ID = ? AND RECIPES_ID = ?");
            $success = $removeStmt->execute([$userId, $recipeId]);
            $isCollected = false;
        } else {
            // Add to collection
            $addStmt = $pdo->prepare("INSERT INTO COLLECT (USER_ID, RECIPES_ID, DATE_COLLECT) VALUES (?, ?, CURRENT_TIMESTAMP)");
            $success = $addStmt->execute([$userId, $recipeId]);
            $isCollected = true;
        }
        
        if ($success) {
            // Redirect to avoid form resubmission
            header("Location: view-recipe.php?id=$recipeId");
            exit();
        } else {
            throw new PDOException("Failed to update collection");
        }
    } catch (PDOException $e) {
        error_log("Collection update error: " . $e->getMessage());
        // You might want to show an error message to the user here
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Feedora - Your Recipe Collection">
    <meta name="theme-color" content="#ED5A2C">
    <title><?php echo htmlspecialchars($recipe['TITLE']); ?> - Feedora</title>
    <link rel="stylesheet" href="Home.css">
    <link rel="stylesheet" href="fonts.css">
    <style>
        :root {
            --primary-color: #ED5A2C;
            --secondary-color: #4CAF50;
            --text-color: #333;
            --light-text: #666;
            --background-color: #f5f5f5;
            --card-background: #fff;
            --border-color: #e0e0e0;
            --shadow-color: rgba(0, 0, 0, 0.1);
            --header-height: 70px;
            --sidebar-width: 240px;
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

        a {
            text-decoration: none;
            color: inherit;
        }

        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
            display: flex;
            flex-direction: column;
        }

        .recipe-container {
            flex: 1;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            padding: 0;
            overflow: hidden;
        }

        .recipe-header {
            position: relative;
            height: 350px;
            overflow: hidden;
        }

        .recipe-header-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .recipe-header-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0.4) 50%, rgba(0,0,0,0) 100%);
            padding: 30px;
            color: white;
        }

        .recipe-title {
            font-size: 32px;
            margin-bottom: 10px;
            font-weight: 700;
            color: white;
        }

        .recipe-meta {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .recipe-meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
        }

        .recipe-meta-icon {
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .recipe-author {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .recipe-author-image {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
        }

        .recipe-author-name {
            font-size: 14px;
            font-weight: 500;
        }

        .recipe-content {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 30px;
            padding: 30px;
        }

        .recipe-instructions {
            padding-right: 20px;
        }

        .recipe-section-title {
            font-size: 22px;
            margin-bottom: 20px;
            color: var(--text-color);
            font-weight: 600;
            position: relative;
            padding-bottom: 10px;
        }

        .recipe-section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 40px;
            height: 3px;
            background-color: var(--primary-color);
            border-radius: 3px;
        }

        .recipe-instructions-text {
            white-space: pre-line;
            line-height: 1.6;
            color: var(--text-color);
        }

        .recipe-sidebar {
            background-color: var(--background-color);
            border-radius: 12px;
            padding: 25px;
        }

        .recipe-ingredients-list {
            list-style: none;
            margin-bottom: 30px;
        }

        .recipe-ingredient-item {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .ingredient-checkbox {
            margin-right: 15px;
            appearance: none;
            width: 20px;
            height: 20px;
            border: 2px solid var(--primary-color);
            border-radius: 4px;
            position: relative;
            cursor: pointer;
            transition: all 0.2s;
        }

        .ingredient-checkbox:checked {
            background-color: var(--primary-color);
        }

        .ingredient-checkbox:checked::after {
            content: '✓';
            position: absolute;
            color: white;
            font-size: 14px;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .ingredient-checkbox:checked + .ingredient-text {
            text-decoration: line-through;
            color: var(--light-text);
        }

        .ingredient-text {
            flex: 1;
            transition: all 0.2s;
        }

        .recipe-details {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }

        .recipe-detail-item {
            background-color: white;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .detail-value {
            font-size: 24px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 5px;
        }

        .detail-label {
            font-size: 12px;
            color: var(--light-text);
        }

        .recipe-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .recipe-action-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
        }

        .save-recipe-btn {
            background-color: <?php echo $isCollected ? '#4CAF50' : 'var(--primary-color)'; ?>;
            color: white;
        }

        .save-recipe-btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .print-recipe-btn {
            background-color: var(--background-color);
            color: var(--text-color);
            border: 1px solid var(--border-color);
        }

        .print-recipe-btn:hover {
            background-color: var(--border-color);
        }

        .back-to-recipes {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            color: var(--light-text);
            font-weight: 500;
            transition: color 0.2s;
        }

        .back-to-recipes:hover {
            color: var(--primary-color);
        }

        @media (max-width: 992px) {
            .recipe-content {
                grid-template-columns: 1fr;
            }
        }

        @media print {
            @page {
                margin: 1cm;
                size: portrait;
            }
            
            body {
                margin: 0;
                padding: 0;
                background: white;
                font-size: 12pt;
                width: 100%;
                display: block;
            }
            
            /* Force main content to take full width with no sidebar margin */
            .main-content {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                margin-left: 0 !important; /* Override the sidebar margin */
                display: block !important;
            }
            
            /* Aggressively hide all navigation elements */
            body > nav,
            body > .sidebar,
            body > #sidebar,
            nav.sidebar,
            .sidebar,
            #sidebar,
            header, 
            .back-to-recipes, 
            .recipe-actions {
                display: none !important;
                width: 0 !important;
                height: 0 !important;
                position: absolute !important;
                overflow: hidden !important;
                visibility: hidden !important;
                opacity: 0 !important;
                clip: rect(0, 0, 0, 0) !important;
            }
            
            /* Show recipe content in a single column layout */
            .recipe-content {
                display: block !important;
                width: 100% !important;
                grid-template-columns: 1fr !important;
            }
            
            /* Keep the recipe sidebar visible for ingredients */
            .recipe-sidebar {
                display: block !important;
                width: 100% !important;
                margin-top: 20px !important;
                padding: 0 !important;
                background: none !important;
                box-shadow: none !important;
                float: none !important;
                page-break-before: auto;
                page-break-after: auto;
                page-break-inside: avoid;
            }
            
            /* Style recipe container */
            .recipe-container {
                box-shadow: none !important;
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
                display: block !important;
            }
            
            /* Style recipe instructions */
            .recipe-instructions {
                width: 100% !important;
                padding: 0 !important;
                margin-bottom: 20px !important;
                display: block !important;
                page-break-before: auto;
                page-break-after: auto;
                page-break-inside: avoid;
            }
            
            /* Make ingredients list fully visible */
            .recipe-ingredients-list {
                display: block !important;
                width: 100% !important;
                padding-left: 20px !important;
                margin-bottom: 20px !important;
                page-break-inside: avoid;
            }
            
            .recipe-ingredients-list li {
                display: block !important;
                width: 100% !important;
                margin-bottom: 5px !important;
            }
            
            /* Hide checkboxes but keep ingredient text */
            .ingredient-checkbox {
                display: none !important;
            }
            
            .ingredient-text {
                display: inline !important;
            }
            
            /* Add a separator between sections */
            .recipe-section-title {
                border-bottom: 1px solid #ccc;
                padding-bottom: 5px;
                margin-top: 15px;
                margin-bottom: 10px;
                page-break-after: avoid;
                display: block !important;
            }
            
            /* Format recipe details nicely */
            .recipe-details {
                display: flex !important;
                flex-wrap: wrap !important;
                margin-bottom: 15px !important;
                border: 1px solid #eee;
                padding: 10px;
                page-break-inside: avoid;
            }
            
            .recipe-detail-item {
                margin-right: 20px !important;
                box-shadow: none !important;
                background: none !important;
                flex: 1 !important;
                min-width: 80px !important;
            }
            
            .detail-value {
                display: block !important;
                font-weight: bold !important;
            }
            
            .detail-label {
                display: block !important;
            }
            
            /* Ensure proper page breaks */
            h1, h2, h3 {
                page-break-after: avoid;
            }
            
            .recipe-instructions-text {
                page-break-inside: avoid;
                display: block !important;
                width: 100% !important;
            }
            
            /* Ensure recipe header and image are visible */
            .recipe-header {
                height: auto !important;
                max-height: 300px !important;
                overflow: visible !important;
                display: block !important;
                position: relative !important;
                margin-bottom: 20px !important;
                page-break-inside: avoid;
            }
            
            .recipe-header-image {
                display: block !important;
                width: 100% !important;
                height: auto !important;
                max-height: 300px !important;
                object-fit: contain !important;
                page-break-inside: avoid;
            }
            
            .recipe-header-overlay {
                position: relative !important;
                background: none !important;
                padding: 10px 0 !important;
                color: black !important;
                display: block !important;
            }
            
            .recipe-title {
                color: black !important;
                font-size: 24pt !important;
                margin-top: 10px !important;
                display: block !important;
            }
            
            .recipe-meta {
                display: block !important;
                margin-bottom: 10px !important;
            }
            
            .recipe-meta-item {
                display: inline-block !important;
                margin-right: 15px !important;
            }
            
            .recipe-author {
                display: block !important;
                margin-top: 10px !important;
            }
            
            img {
                max-width: 100% !important;
                page-break-inside: avoid;
                display: block !important;
            }
            
            /* Add recipe URL at the bottom */
            .recipe-container::after {
                content: "Printed from Feedora - " attr(data-recipe-id);
                display: block;
                text-align: center;
                font-size: 9pt;
                color: #999;
                margin-top: 30px;
            }
            
            /* Add page numbers */
            @page {
                @bottom-right {
                    content: "Page " counter(page) " of " counter(pages);
                }
            }
        }
    </style>
</head>

<body>
    <?php include('sidebar.php'); ?>

    <main class="main-content">
        <?php include('header.php'); ?>

        <a href="recipes.php" class="back-to-recipes">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
            Back to Recipes
        </a>

        <div class="recipe-container" data-recipe-id="<?php echo htmlspecialchars($recipe['TITLE']); ?> | Feedora">
            <div class="recipe-header">
                <?php if (!empty($recipe['PHOTO_URL'])): ?>
                    <img src="<?php echo htmlspecialchars($recipe['PHOTO_URL']); ?>" alt="<?php echo htmlspecialchars($recipe['TITLE']); ?>" class="recipe-header-image">
                <?php else: ?>
                    <img src="images/default-recipe.jpg" alt="Default Recipe Image" class="recipe-header-image">
                <?php endif; ?>
                
                <div class="recipe-header-overlay">
                    <h1 class="recipe-title"><?php echo htmlspecialchars($recipe['TITLE']); ?></h1>
                    
                    <div class="recipe-meta">
                        <span class="recipe-meta-item">
                            <span class="recipe-meta-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 2v7c0 1.1.9 2 2 2h14a2 2 0 0 0 2-2V2"></path>
                                    <path d="M7 2v20"></path>
                                    <path d="M17 2v20"></path>
                                    <path d="M9 2v4"></path>
                                    <path d="M15 2v4"></path>
                                    <path d="M12 14v3"></path>
                                    <path d="M10 17h4"></path>
                                </svg>
                            </span>
                            <?php echo htmlspecialchars($recipe['NAME_CATEGORIE'] ?? 'Uncategorized'); ?>
                        </span>
                        
                        <span class="recipe-meta-item">
                            <span class="recipe-meta-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 20v-6M6 20V10M18 20V4"></path>
                                </svg>
                            </span>
                            <?php echo htmlspecialchars($recipe['DIFFICULTY_NAME'] ?? 'Not specified'); ?>
                        </span>
                    </div>
                    
                    <div class="recipe-author">
                        <?php if (!empty($recipe['AUTHOR_IMAGE'])): ?>
                            <img src="<?php echo htmlspecialchars($recipe['AUTHOR_IMAGE']); ?>" alt="<?php echo htmlspecialchars($recipe['AUTHOR_NAME']); ?>" class="recipe-author-image">
                        <?php else: ?>
                            <img src="images/default-profile.png" alt="Default Profile" class="recipe-author-image">
                        <?php endif; ?>
                        <span class="recipe-author-name">By <?php echo htmlspecialchars($recipe['AUTHOR_NAME']); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="recipe-content">
                <div class="recipe-instructions">
                    <h2 class="recipe-section-title">Instructions</h2>
                    <div class="recipe-instructions-text">
                        <?php echo nl2br(htmlspecialchars($recipe['INSTRUCTIONS'])); ?>
                    </div>
                </div>
                
                <div class="recipe-sidebar">
                    <div class="recipe-details">
                        <div class="recipe-detail-item">
                            <div class="detail-value"><?php echo htmlspecialchars($recipe['PREP_TIME_MINUTES'] ?? '0'); ?></div>
                            <div class="detail-label">Prep Time (min)</div>
                        </div>
                        
                        <div class="recipe-detail-item">
                            <div class="detail-value"><?php echo htmlspecialchars($recipe['COOK_TIME_MINUTES'] ?? '0'); ?></div>
                            <div class="detail-label">Cook Time (min)</div>
                        </div>
                        
                        <div class="recipe-detail-item">
                            <div class="detail-value"><?php echo htmlspecialchars($recipe['SERVINGS'] ?? '1'); ?></div>
                            <div class="detail-label">Servings</div>
                        </div>
                    </div>
                    
                    <h2 class="recipe-section-title">Ingredients</h2>
                    <?php if (count($ingredients) > 0): ?>
                        <ul class="recipe-ingredients-list">
                            <?php foreach ($ingredients as $ingredient): ?>
                                <li class="recipe-ingredient-item">
                                    <input type="checkbox" class="ingredient-checkbox" id="ingredient-<?php echo $ingredient['INGREDIENTS_ID']; ?>">
                                    <label class="ingredient-text" for="ingredient-<?php echo $ingredient['INGREDIENTS_ID']; ?>">
                                        <?php 
                                            $quantity = !empty($ingredient['QUANTITY']) ? $ingredient['QUANTITY'] : '';
                                            $unit = !empty($ingredient['UNIT_NAME']) ? $ingredient['UNIT_NAME'] : '';
                                            if (!empty($quantity) && !empty($unit)) {
                                                echo htmlspecialchars("$quantity $unit of {$ingredient['NAME']}");
                                            } else {
                                                echo htmlspecialchars($ingredient['NAME']);
                                            }
                                        ?>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>No ingredients listed for this recipe.</p>
                    <?php endif; ?>
                    
                    <div class="recipe-actions">
                        <form method="post" action="">
                            <input type="hidden" name="recipe_id" value="<?php echo $recipeId; ?>">
                            <button type="submit" name="toggle_collect" class="recipe-action-btn save-recipe-btn" id="saveBtn">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="<?php echo $isCollected ? 'white' : 'none'; ?>" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                                </svg>
                                <?php echo $isCollected ? 'Saved to Collection' : 'Save to Collection'; ?>
                            </button>
                        </form>
                        
                        <button class="recipe-action-btn print-recipe-btn" onclick="window.print()">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6 9 6 2 18 2 18 9"></polyline>
                                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                                <rect x="6" y="14" width="12" height="8"></rect>
                            </svg>
                            Print Recipe
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Save ingredient checked state to localStorage
            const checkboxes = document.querySelectorAll('.ingredient-checkbox');
            const recipeId = '<?php echo $recipeId; ?>';
            
            // Load saved state
            checkboxes.forEach(checkbox => {
                const id = checkbox.id;
                const savedState = localStorage.getItem(`recipe_${recipeId}_${id}`);
                if (savedState === 'true') {
                    checkbox.checked = true;
                }
                
                // Save state on change
                checkbox.addEventListener('change', function() {
                    localStorage.setItem(`recipe_${recipeId}_${id}`, this.checked);
                });
            });
        });
    </script>
</body>

</html>
