<?php
// Include the database connection script
require_once 'config/config.php';
// Include FIXED notification utilities
require_once 'notification_utils.php';
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

// Get all categories from database
$categoriesStmt = $pdo->prepare("SELECT * FROM CATEGORIE ORDER BY NAME_CATEGORIE");
$categoriesStmt->execute();
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

// Get all difficulty levels from database
$difficultyStmt = $pdo->prepare("SELECT * FROM DIFFICULTY ORDER BY DIFFICULTY_ID");
$difficultyStmt->execute();
$difficulties = $difficultyStmt->fetchAll(PDO::FETCH_ASSOC);

// Get all units from database
$unitsStmt = $pdo->prepare("SELECT * FROM UNIT ORDER BY UNIT_NAME");
$unitsStmt->execute();
$units = $unitsStmt->fetchAll(PDO::FETCH_ASSOC);

// Process form submission for new recipe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_recipe'])) {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Handle file upload for recipe photo
        $photoUrl = '';
        if (isset($_FILES['recipe_photo']) && $_FILES['recipe_photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/recipes/';
            
            // Create directory if it doesn't exist
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileName = uniqid() . '_' . basename($_FILES['recipe_photo']['name']);
            $uploadFile = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['recipe_photo']['tmp_name'], $uploadFile)) {
                $photoUrl = $uploadFile;
            }
        }
        
        // Insert recipe
        $recipeStmt = $pdo->prepare("INSERT INTO RECIPES (USER_ID, ID_CATEGORIE, TITLE, PHOTO_URL, INSTRUCTIONS, PREP_TIME_MINUTES, COOK_TIME_MINUTES, SERVINGS) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $recipeStmt->execute([
            $userId,
            $_POST['category'],
            $_POST['recipe_title'],
            $photoUrl,
            $_POST['instructions'],
            $_POST['prep_time'],
            $_POST['cook_time'],
            $_POST['servings']
        ]);
        
        $recipeId = $pdo->lastInsertId();
        
        // Insert difficulty relation if not 'all'
        if ($_POST['difficulty'] !== 'all') {
            $difficultyRecipeStmt = $pdo->prepare("INSERT INTO DIFFICULTY_RECIPES (DIFFICULTY_ID, RECIPES_ID) VALUES (?, ?)");
            $difficultyRecipeStmt->execute([$_POST['difficulty'], $recipeId]);
        }
        
        // Process ingredients
        if (isset($_POST['ingredient_name']) && is_array($_POST['ingredient_name'])) {
            for ($i = 0; $i < count($_POST['ingredient_name']); $i++) {
                if (!empty($_POST['ingredient_name'][$i])) {
                    // Insert ingredient
                    $ingredientStmt = $pdo->prepare("INSERT INTO INGREDIENTS (NAME) VALUES (?)");
                    $ingredientStmt->execute([$_POST['ingredient_name'][$i]]);
                    $ingredientId = $pdo->lastInsertId();
                    
                    // Link ingredient to recipe
                    $recipeIngredientStmt = $pdo->prepare("INSERT INTO RECIPE_INGREDIENTS (RECIPES_ID, INGREDIENTS_ID) VALUES (?, ?)");
                    $recipeIngredientStmt->execute([$recipeId, $ingredientId]);
                    
                    // Add quantity and unit
                    if (!empty($_POST['ingredient_quantity'][$i]) && !empty($_POST['ingredient_unit'][$i])) {
                        $unitStmt = $pdo->prepare("INSERT INTO INGREDIENTS_UNIT (INGREDIENTS_ID, UNIT_ID, QUANTITY) VALUES (?, ?, ?)");
                        $unitStmt->execute([
                            $ingredientId,
                            $_POST['ingredient_unit'][$i],
                            $_POST['ingredient_quantity'][$i]
                        ]);
                    }
                }
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Redirect to avoid form resubmission
        header("Location: recipes.php?success=1");
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        $error = "Error creating recipe: " . $e->getMessage();
    }
}

// Set default filter values
$selectedCategory = isset($_GET['category']) ? $_GET['category'] : 'All Categories';
$selectedDifficulty = isset($_GET['difficulty']) ? $_GET['difficulty'] : 'All Difficulties';

// Build the query based on filters
$query = "SELECT r.*, c.NAME_CATEGORIE, d.DIFFICULTY_NAME, u.NAME as AUTHOR_NAME 
         FROM RECIPES r 
         LEFT JOIN CATEGORIE c ON r.ID_CATEGORIE = c.ID_CATEGORIE 
         LEFT JOIN DIFFICULTY_RECIPES dr ON r.RECIPES_ID = dr.RECIPES_ID 
         LEFT JOIN DIFFICULTY d ON dr.DIFFICULTY_ID = d.DIFFICULTY_ID 
         LEFT JOIN USERS u ON r.USER_ID = u.USER_ID 
         WHERE 1=1";
$params = [];

// Apply category filter if not 'All Categories'
if ($selectedCategory !== 'All Categories') {
    $query .= " AND c.NAME_CATEGORIE = ?";
    $params[] = $selectedCategory;
}

// Apply difficulty filter if specified
if ($selectedDifficulty !== 'All Difficulties') {
    $query .= " AND d.DIFFICULTY_NAME = ?";
    $params[] = $selectedDifficulty;
}

// Order by newest first
$query .= " ORDER BY r.RECIPES_ID DESC";

// Execute the query
$recipesStmt = $pdo->prepare($query);
$recipesStmt->execute($params);
$recipes = $recipesStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Feedora - Your Recipe Collection">
    <meta name="theme-color" content="#ED5A2C">
    <title>Feedora - Recipes</title>
    <link rel="stylesheet" href="Home.css">
    <link rel="stylesheet" href="fonts.css">
        <!-- Favicon -->
        <link rel="icon" href="images/Frame 1171277973.svg" type="image/svg+xml">
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

        .recipes-container {
            flex: 1;
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 20px;
        }

        .recipes-content {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 30px;
            margin-top: 20px;
        }

        .recipes-main {
            background-color: var(--card-background);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            padding: 25px;
            transition: all 0.3s ease;
        }

        .recipes-sidebar {
            background-color: var(--card-background);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            padding: 25px;
            height: fit-content;
            position: sticky;
            top: 20px;
        }

        .filter-section {
            margin-bottom: 30px;
            position: relative;
        }

        .filter-section h3 {
            margin-bottom: 18px;
            font-size: 18px;
            color: var(--text-color);
            font-weight: 600;
            position: relative;
            padding-bottom: 10px;
        }

        .filter-section h3::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 40px;
            height: 3px;
            background-color: var(--primary-color);
            border-radius: 3px;
        }

        .filter-options {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .filter-option {
            background-color: var(--background-color);
            padding: 10px 18px;
            border-radius: 25px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            border: 1px solid transparent;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .filter-option:hover {
            background-color: rgba(237, 90, 44, 0.1);
            border-color: rgba(237, 90, 44, 0.2);
            transform: translateY(-2px);
        }

        .filter-option.active {
            background-color: var(--primary-color);
            color: white;
            box-shadow: 0 4px 8px rgba(237, 90, 44, 0.3);
        }

        .recipe-card {
            background-color: var(--card-background);
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.06);
            margin-bottom: 25px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.03);
            display: flex;
            flex-direction: column;
        }

        .recipe-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            border-color: rgba(237, 90, 44, 0.2);
        }

        .recipe-image {
            width: 100%;
            height: 220px;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .recipe-card:hover .recipe-image {
            transform: scale(1.05);
        }

        .recipe-content {
            padding: 25px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .recipe-title {
            font-size: 20px;
            margin-bottom: 12px;
            color: var(--text-color);
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .recipe-card:hover .recipe-title {
            color: var(--primary-color);
        }

        .recipe-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 15px;
            font-size: 14px;
            color: var(--light-text);
        }

        .recipe-meta span {
            background-color: rgba(237, 90, 44, 0.1);
            padding: 5px 10px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
        }

        .recipe-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }

        .recipe-author {
            font-size: 14px;
            color: var(--light-text);
            font-weight: 500;
        }

        .view-recipe-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
            font-weight: 500;
            box-shadow: 0 2px 6px rgba(237, 90, 44, 0.3);
        }

        .view-recipe-btn:hover {
            background-color: #d64a1e;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(237, 90, 44, 0.4);
        }

        .make-recipe-btn {
            display: block;
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 14px 20px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            font-family: 'Qurova', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            box-shadow: 0 4px 10px rgba(237, 90, 44, 0.3);
            text-decoration: none;
        }

        .make-recipe-btn:hover {
            background-color: #d64a1e;
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(237, 90, 44, 0.4);
        }

        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 18px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .filter-form-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-dropdown {
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background-color: var(--card-background);
            cursor: pointer;
            margin-right: 12px;
            font-size: 14px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            outline: none;
        }

        .filter-dropdown:hover,
        .filter-dropdown:focus {
            border-color: var(--primary-color);
            box-shadow: 0 3px 8px rgba(237, 90, 44, 0.15);
        }

        .no-recipes {
            text-align: center;
            padding: 40px 0;
            color: var(--light-text);
            background-color: var(--card-background);
            border-radius: 10px;
            box-shadow: 0 2px 5px var(--shadow-color);
        }
    </style>
</head>

<body>
    <?php include('sidebar.php'); ?>

    <main class="main-content">
        <?php include('header.php'); ?>

        <!-- Recipes Content -->
        <div class="recipes-container">
            <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
                <div class="success-message">
                    <p>Recipe created successfully!</p>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="error-message">
                    <p><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php endif; ?>

            <div class="recipes-content">
                <div class="recipes-main">
                    <div class="filter-header">
                        <div class="filter-form-container">
                            <form action="" method="get" id="filterForm" style="display: flex; align-items: center; gap: 10px;">
                                <select name="category" class="filter-dropdown" onchange="this.form.submit()">
                                    <option value="All Categories" <?php echo $selectedCategory === 'All Categories' ? 'selected' : ''; ?>>All Categories</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo htmlspecialchars($category['NAME_CATEGORIE']); ?>" <?php echo $selectedCategory === $category['NAME_CATEGORIE'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['NAME_CATEGORIE']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <select name="difficulty" class="filter-dropdown" onchange="this.form.submit()">
                                    <option value="All Difficulties" <?php echo $selectedDifficulty === 'All Difficulties' ? 'selected' : ''; ?>>All Difficulties</option>
                                    <?php foreach ($difficulties as $difficulty): ?>
                                        <option value="<?php echo htmlspecialchars($difficulty['DIFFICULTY_NAME']); ?>" <?php echo $selectedDifficulty === $difficulty['DIFFICULTY_NAME'] ? 'selected' : ''; ?>>
                                            Difficulty: <?php echo htmlspecialchars($difficulty['DIFFICULTY_NAME']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                            <button id="openRecipeFormBtn" class="make-recipe-btn" style="width: auto; margin-top: 0;">Make Recipe</button>
                        </div>
                    </div>

                    <?php if (count($recipes) > 0): ?>
                        <?php foreach ($recipes as $recipe): ?>
                            <div class="recipe-card">
                                <?php if (!empty($recipe['PHOTO_URL'])): ?>
                                    <img src="<?php echo htmlspecialchars($recipe['PHOTO_URL']); ?>" alt="<?php echo htmlspecialchars($recipe['TITLE']); ?>" class="recipe-image">
                                <?php else: ?>
                                    <img src="images/default-recipe.jpg" alt="Default Recipe Image" class="recipe-image">
                                <?php endif; ?>

                                <div class="recipe-content">
                                    <h3 class="recipe-title"><?php echo htmlspecialchars($recipe['TITLE']); ?></h3>

                                    <div class="recipe-meta">
                                        <span><?php echo htmlspecialchars($recipe['NAME_CATEGORIE'] ?? 'Uncategorized'); ?></span>
                                        <span>Difficulty: <?php echo htmlspecialchars($recipe['DIFFICULTY_NAME'] ?? 'Not specified'); ?></span>
                                    </div>

                                    <div class="recipe-meta">
                                        <span>Prep: <?php echo htmlspecialchars($recipe['PREP_TIME_MINUTES'] ?? '0'); ?> min</span>
                                        <span>Cook: <?php echo htmlspecialchars($recipe['COOK_TIME_MINUTES'] ?? '0'); ?> min</span>
                                        <span>Servings: <?php echo htmlspecialchars($recipe['SERVINGS'] ?? '1'); ?></span>
                                    </div>

                                    <div class="recipe-footer">
                                        <span class="recipe-author">By <?php echo htmlspecialchars($recipe['AUTHOR_NAME']); ?></span>
                                        <a href="view-recipe.php?id=<?php echo $recipe['RECIPES_ID']; ?>" class="view-recipe-btn">View Recipe</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-recipes">
                            <h3>No recipes found matching your criteria</h3>
                            <p>Try adjusting your filters or create a new recipe!</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="recipes-sidebar">
                    <h2>Filter Recipes</h2>

                    <div class="filter-section">
                        <h3>Categories</h3>
                        <div class="filter-options">
                            <a href="?category=All Categories&difficulty=<?php echo urlencode($selectedDifficulty); ?>" class="filter-option <?php echo $selectedCategory === 'All Categories' ? 'active' : ''; ?>">All</a>

                            <?php foreach ($categories as $category): ?>
                                <a href="?category=<?php echo urlencode($category['NAME_CATEGORIE']); ?>&difficulty=<?php echo urlencode($selectedDifficulty); ?>"
                                    class="filter-option <?php echo $selectedCategory === $category['NAME_CATEGORIE'] ? 'active' : ''; ?>">
                                    <?php echo htmlspecialchars($category['NAME_CATEGORIE']); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="filter-section">
                        <h3>Difficulty</h3>
                        <div class="filter-options">
                            <a href="?category=<?php echo urlencode($selectedCategory); ?>&difficulty=All Difficulties"
                                class="filter-option <?php echo $selectedDifficulty === 'All Difficulties' ? 'active' : ''; ?>">All</a>

                            <?php foreach ($difficulties as $difficulty): ?>
                                <a href="?category=<?php echo urlencode($selectedCategory); ?>&difficulty=<?php echo urlencode($difficulty['DIFFICULTY_NAME']); ?>"
                                    class="filter-option <?php echo $selectedDifficulty === $difficulty['DIFFICULTY_NAME'] ? 'active' : ''; ?>">
                                    <?php echo htmlspecialchars($difficulty['DIFFICULTY_NAME']); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <button id="openRecipeFormBtn2" class="make-recipe-btn">Make Recipe</button>
                </div>
            </div>
        </div>
    </main>

    <!-- Recipe Form Modal -->
    <div id="recipeFormModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            
            <div class="recipe-form-container">
                <h2>Share Your Recipe</h2>
                <p class="form-subtitle">Guide fellow cooks through each step</p>
                
                <form action="" method="post" enctype="multipart/form-data" id="recipeForm">
                    <div class="form-group">
                        <label for="recipe_title">Recipe Title</label>
                        <input type="text" id="recipe_title" name="recipe_title" placeholder="Enter your recipe name..." required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group half">
                            <label for="category"><i class="icon-category"></i> Category</label>
                            <select id="category" name="category" required>
                                <option value="" disabled selected>Select category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['ID_CATEGORIE']; ?>">
                                        <?php echo htmlspecialchars($category['NAME_CATEGORIE']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group half">
                            <label for="difficulty"><i class="icon-difficulty"></i> Difficulty</label>
                            <select id="difficulty" name="difficulty" required>
                                <option value="" disabled>Select difficulty</option>
                                <option value="all" selected>All Difficulties</option>
                                <?php foreach ($difficulties as $difficulty): ?>
                                    <option value="<?php echo $difficulty['DIFFICULTY_ID']; ?>">
                                        <?php echo htmlspecialchars($difficulty['DIFFICULTY_NAME']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="recipe_photo">Recipe Photo</label>
                        <div class="photo-upload-container" id="photoUploadContainer">
                            <div class="upload-placeholder" id="uploadPlaceholder">
                                <i class="upload-icon">⬆️</i>
                                <p>Drag & drop your image here</p>
                                <button type="button" class="browse-btn" id="browseTrigger">Browse Files</button>
                            </div>
                            <div class="photo-preview" id="photoPreview" style="display: none;">
                                <img id="previewImage" src="" alt="Recipe preview">
                                <button type="button" class="remove-photo" id="removePhoto">Remove</button>
                            </div>
                            <input type="file" name="recipe_photo" id="recipePhotoInput" accept="image/*" style="display: none;">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Ingredients</label>
                        <div id="ingredientsContainer">
                            <div class="ingredient-row">
                                <input type="text" name="ingredient_name[]" placeholder="Ingredient name" required>
                                <input type="number" name="ingredient_quantity[]" placeholder="Qty" min="0" step="0.01" required>
                                <select name="ingredient_unit[]" required>
                                    <option value="" disabled selected>Unit</option>
                                    <?php foreach ($units as $unit): ?>
                                        <option value="<?php echo $unit['UNIT_ID']; ?>">
                                            <?php echo htmlspecialchars($unit['UNIT_NAME']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="remove-ingredient">✕</button>
                            </div>
                        </div>
                        <button type="button" id="addIngredient" class="add-ingredient-btn">+ Add Ingredient</button>
                    </div>
                    
                    <div class="form-group">
                        <label for="instructions">Instructions</label>
                        <textarea id="instructions" name="instructions" placeholder="Describe each step in detail..." rows="6" required></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group third">
                            <label for="prep_time"><i class="icon-time"></i> Prep Time</label>
                            <div class="time-input">
                                <input type="number" id="prep_time" name="prep_time" min="0" value="30" required>
                                <span class="time-unit">min</span>
                            </div>
                        </div>
                        
                        <div class="form-group third">
                            <label for="cook_time"><i class="icon-time"></i> Cook Time</label>
                            <div class="time-input">
                                <input type="number" id="cook_time" name="cook_time" min="0" value="45" required>
                                <span class="time-unit">min</span>
                            </div>
                        </div>
                        
                        <div class="form-group third">
                            <label for="servings"><i class="icon-servings"></i> Servings</label>
                            <input type="number" id="servings" name="servings" min="1" value="4" required>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="submit_recipe" class="submit-recipe-btn">Make Recipe</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // JavaScript to enhance the filter functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Check if we need to open the recipe form modal automatically
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('openRecipeForm') === '1') {
                const modal = document.getElementById('recipeFormModal');
                if (modal) {
                    modal.style.display = 'block';
                    document.body.classList.add('modal-open');
                }
            }
            
            // Make filter options clickable
            document.querySelectorAll('.filter-option').forEach(function(option) {
                option.addEventListener('click', function() {
                    window.location.href = this.getAttribute('href');
                });
            });
            // Modal functionality
            const modal = document.getElementById('recipeFormModal');
            const openBtns = [document.getElementById('openRecipeFormBtn'), document.getElementById('openRecipeFormBtn2')];
            const closeBtn = document.querySelector('.close-modal');
            
            openBtns.forEach(btn => {
                if (btn) {
                    btn.addEventListener('click', function() {
                        modal.style.display = 'block';
                        document.body.classList.add('modal-open');
                    });
                }
            });
            
            closeBtn.addEventListener('click', function() {
                modal.style.display = 'none';
                document.body.classList.remove('modal-open');
            });
            
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                    document.body.classList.remove('modal-open');
                }
            });
            
            // Photo upload preview
            const photoInput = document.getElementById('recipePhotoInput');
            const browseTrigger = document.getElementById('browseTrigger');
            const uploadPlaceholder = document.getElementById('uploadPlaceholder');
            const photoPreview = document.getElementById('photoPreview');
            const previewImage = document.getElementById('previewImage');
            const removePhotoBtn = document.getElementById('removePhoto');
            const photoContainer = document.getElementById('photoUploadContainer');
            
            browseTrigger.addEventListener('click', function() {
                photoInput.click();
            });
            
            photoInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        previewImage.src = e.target.result;
                        uploadPlaceholder.style.display = 'none';
                        photoPreview.style.display = 'block';
                    };
                    
                    reader.readAsDataURL(this.files[0]);
                }
            });
            
            removePhotoBtn.addEventListener('click', function() {
                photoInput.value = '';
                previewImage.src = '';
                photoPreview.style.display = 'none';
                uploadPlaceholder.style.display = 'flex';
            });
            
            // Drag and drop functionality
            photoContainer.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('dragover');
            });
            
            photoContainer.addEventListener('dragleave', function() {
                this.classList.remove('dragover');
            });
            
            photoContainer.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
                
                if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                    photoInput.files = e.dataTransfer.files;
                    
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewImage.src = e.target.result;
                        uploadPlaceholder.style.display = 'none';
                        photoPreview.style.display = 'block';
                    };
                    
                    reader.readAsDataURL(e.dataTransfer.files[0]);
                }
            });
            
            // Add/remove ingredients
            const ingredientsContainer = document.getElementById('ingredientsContainer');
            const addIngredientBtn = document.getElementById('addIngredient');
            
            addIngredientBtn.addEventListener('click', function() {
                const newRow = document.createElement('div');
                newRow.className = 'ingredient-row';
                newRow.innerHTML = `
                    <input type="text" name="ingredient_name[]" placeholder="Ingredient name" required>
                    <input type="number" name="ingredient_quantity[]" placeholder="Qty" min="0" step="0.01" required>
                    <select name="ingredient_unit[]" required>
                        <option value="" disabled selected>Unit</option>
                        <?php foreach ($units as $unit): ?>
                            <option value="<?php echo $unit['UNIT_ID']; ?>">
                                <?php echo htmlspecialchars($unit['UNIT_NAME']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="remove-ingredient">✕</button>
                `;
                
                ingredientsContainer.appendChild(newRow);
                
                // Add event listener to the new remove button
                const removeBtn = newRow.querySelector('.remove-ingredient');
                removeBtn.addEventListener('click', function() {
                    ingredientsContainer.removeChild(newRow);
                });
            });
            
            // Add event listener to the initial remove button
            document.querySelector('.remove-ingredient').addEventListener('click', function() {
                if (ingredientsContainer.children.length > 1) {
                    this.parentElement.remove();
                }
            });
        });
    </script>
    
    <style>
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 2% auto;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }
        
        .close-modal {
            position: absolute;
            right: 20px;
            top: 15px;
            font-size: 28px;
            font-weight: bold;
            color: #aaa;
            cursor: pointer;
        }
        
        .close-modal:hover {
            color: var(--primary-color);
        }
        
        body.modal-open {
            overflow: hidden;
        }
        
        /* Recipe Form Styles */
        .recipe-form-container {
            padding: 10px 0;
        }
        
        .recipe-form-container h2 {
            font-size: 24px;
            margin-bottom: 5px;
            color: var(--text-color);
        }
        
        .form-subtitle {
            color: var(--light-text);
            margin-bottom: 25px;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .half {
            width: 50%;
        }
        
        .third {
            width: calc(33.333% - 14px);
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-color);
        }
        
        input[type="text"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            color: var(--text-color);
            background-color: white;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        
        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(237, 90, 44, 0.1);
        }
        
        .photo-upload-container {
            border: 2px dashed #ddd;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            min-height: 200px;
            position: relative;
            transition: all 0.3s;
        }
        
        .photo-upload-container.dragover {
            border-color: var(--primary-color);
            background-color: rgba(237, 90, 44, 0.05);
        }
        
        .upload-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--light-text);
        }
        
        .upload-icon {
            font-size: 32px;
            margin-bottom: 10px;
            color: #ccc;
        }
        
        .browse-btn {
            background-color: var(--light-background);
            border: 1px solid #ddd;
            padding: 8px 15px;
            border-radius: 20px;
            margin-top: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .browse-btn:hover {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .photo-preview {
            position: relative;
        }
        
        .photo-preview img {
            max-width: 100%;
            max-height: 300px;
            border-radius: 8px;
        }
        
        .remove-photo {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: rgba(0, 0, 0, 0.6);
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .remove-photo:hover {
            background-color: rgba(237, 90, 44, 0.8);
        }
        
        .ingredient-row {
            display: grid;
            grid-template-columns: 3fr 1fr 1fr auto;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }
        
        .remove-ingredient {
            background-color: #f0f0f0;
            border: none;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #666;
            font-size: 12px;
            transition: all 0.2s;
        }
        
        .remove-ingredient:hover {
            background-color: #ff6b6b;
            color: white;
        }
        
        .add-ingredient-btn {
            background: none;
            border: none;
            color: var(--primary-color);
            padding: 8px 0;
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            transition: opacity 0.2s;
        }
        
        .add-ingredient-btn:hover {
            opacity: 0.8;
        }
        
        .time-input {
            position: relative;
        }
        
        .time-unit {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--light-text);
            pointer-events: none;
        }
        
        .submit-recipe-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 30px;
            padding: 12px 30px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: block;
            margin-left: auto;
        }
        
        .submit-recipe-btn:hover {
            background-color: #d94e20;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(237, 90, 44, 0.3);
        }
        
        .success-message {
            background-color: #e8f5e9;
            border-left: 4px solid #4caf50;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .error-message {
            background-color: #ffebee;
            border-left: 4px solid #f44336;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        /* Responsive Styles for Recipes Content */
        @media (max-width: 1200px) {
            .recipes-container {
                padding: 15px;
                max-width: 100%;
            }
            
            .recipes-content {
                gap: 20px;
            }
            
            .recipes-main {
                padding: 20px;
            }
            
            .recipes-sidebar {
                padding: 20px;
            }
        }
        
        @media (max-width: 992px) {
            .recipes-container {
                padding: 15px 10px;
            }
            
            .recipes-content {
                grid-template-columns: 1fr 280px;
                gap: 15px;
            }
            
            .filter-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .make-recipe-btn {
                width: 100%;
                margin-top: 10px;
            }
            
            .recipe-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .recipes-content {
                grid-template-columns: 1fr;
            }
            
            .recipes-sidebar {
                display: none; /* Hide the sidebar filter on mobile */
            }
            
            .filter-form-container {
                flex-wrap: wrap;
                width: 100%;
            }
            
            .filter-dropdown {
                flex: 1;
                min-width: 120px;
            }
            
            .recipe-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
            
            .recipe-card {
                padding: 15px;
            }
            
            .recipe-title {
                font-size: 18px;
            }
            
            .recipe-meta {
                gap: 8px;
            }
        }
        
        @media (max-width: 576px) {
            .main-content {
                padding: 10px;
            }
            
            .recipes-container {
                padding: 10px;
                border-radius: 10px;
            }
            
            .recipes-main,
            .recipes-sidebar {
                padding: 15px;
                border-radius: 10px;
            }
            
            .recipe-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .filter-dropdown {
                width: 100%;
                margin-right: 0;
                margin-bottom: 10px;
            }
            
            .filter-form-container form {
                display: flex;
                flex-direction: column;
                width: 100%;
                gap: 10px;
            }
            
            /* Make the top filter more prominent since sidebar is hidden */
            .filter-header {
                padding-bottom: 15px;
                margin-bottom: 20px;
                border-bottom: 2px solid var(--border-color);
            }
            
            .filter-options {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .filter-option {
                width: 100%;
                padding: 10px;
            }
            
            /* Modal responsive styles */
            .modal-content {
                width: 95%;
                max-height: 90vh;
                padding: 15px;
                overflow-y: auto;
            }
            
            .form-row {
                flex-direction: column;
            }
            
            .form-group.half,
            .form-group.third {
                width: 100%;
            }
            
            .ingredient-row {
                grid-template-columns: 1fr 80px auto;
                gap: 8px;
            }
            
            .ingredient-row input[name="ingredient_quantity[]"] {
                grid-column: 1;
                grid-row: 2;
            }
            
            .ingredient-row select[name="ingredient_unit[]"] {
                grid-column: 2/4;
                grid-row: 2;
            }
        }
        
        @media (max-width: 480px) {
            .recipes-container {
                padding: 5px;
            }
            
            .recipes-main,
            .recipes-sidebar {
                padding: 12px;
                border-radius: 8px;
            }
            
            .recipe-card {
                padding: 12px;
            }
            
            .recipe-image {
                height: 160px;
            }
            
            .recipe-title {
                font-size: 16px;
                margin-bottom: 8px;
            }
            
            .recipe-meta {
                font-size: 12px;
            }
            
            .recipe-meta-item {
                padding: 4px 8px;
            }
            
            .filter-section h3 {
                font-size: 16px;
                margin-bottom: 12px;
            }
            
            /* Fix for ingredient form on small screens */
            .ingredient-row {
                display: flex;
                flex-direction: column;
                gap: 8px;
                padding-bottom: 15px;
                margin-bottom: 15px;
                border-bottom: 1px solid var(--border-color);
            }
            
            .ingredient-row input,
            .ingredient-row select {
                width: 100%;
            }
            
            .remove-ingredient {
                align-self: flex-end;
                margin-top: -36px;
            }
        }
    </style>
</body>

</html>