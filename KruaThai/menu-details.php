<?php
/**
 * Somdul Table - Menu Details Page
 * File: menu-details.php
 * Description: Full page view for individual menu items with image gallery
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once 'config/database.php';
require_once 'includes/functions.php';

// Include the header
include 'header.php';

// Get menu ID from URL
$menu_id = $_GET['id'] ?? '';

if (empty($menu_id)) {
    header('Location: menus.php');
    exit;
}

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);

try {
    // Fetch menu details with category info
    $stmt = $pdo->prepare("
        SELECT m.*, c.name AS category_name, c.name_thai AS category_name_thai
        FROM menus m 
        LEFT JOIN menu_categories c ON m.category_id = c.id 
        WHERE m.id = ? AND m.is_available = 1
    ");
    $stmt->execute([$menu_id]);
    $menu = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$menu) {
        header('Location: menus.php');
        exit;
    }

} catch (Exception $e) {
    error_log("Menu details error: " . $e->getMessage());
    header('Location: menus.php');
    exit;
}

// Parse JSON fields
$dietary_tags = json_decode($menu['dietary_tags'] ?? '[]', true);
if (!is_array($dietary_tags)) $dietary_tags = [];

$health_benefits = json_decode($menu['health_benefits'] ?? '[]', true);
if (!is_array($health_benefits)) $health_benefits = [];

$additional_images = json_decode($menu['additional_images'] ?? '[]', true);
if (!is_array($additional_images)) $additional_images = [];

// Create image array with main image first, then additional images
$all_images = [];
if ($menu['main_image_url']) {
    $all_images[] = $menu['main_image_url'];
}

// Add additional images
if (!empty($additional_images)) {
    $all_images = array_merge($all_images, $additional_images);
}

// Fill with placeholder images for demo (remove in production)
$demo_images = [
    'assets/image/padthai2.png',
    'assets/image/image1.jpg',
    'assets/image/image2.jpg',
    'assets/image/image3.jpg',
    'assets/image/image4.jpg'
];

while (count($all_images) < 4) {
    $demo_index = count($all_images) % count($demo_images);
    $all_images[] = $demo_images[$demo_index];
}

// Limit to 4 images total
$all_images = array_slice($all_images, 0, 4);

$spice_labels = [
    'mild' => 'Mild',
    'medium' => 'Medium',
    'hot' => 'Hot',
    'extra_hot' => 'Extra Hot'
];

$spice_icons = [
    'mild' => '🌶️',
    'medium' => '🌶️🌶️',
    'hot' => '🌶️🌶️🌶️',
    'extra_hot' => '🌶️🌶️🌶️🌶️'
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($menu['name'] ?: $menu['name_thai']); ?> - Somdul Table</title>
    <meta name="description" content="<?php echo htmlspecialchars($menu['description'] ?: 'Authentic Thai dish from Somdul Table'); ?>">
    
    <style>
    /* PAGE-SPECIFIC STYLES ONLY - header styles come from header.php */
    
    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
    }

    .main-content {
        padding-top: 2rem;
        min-height: calc(100vh - 200px);
    }

    /* Back Button */
    .back-button-container {
        margin-bottom: 2rem;
    }

    .back-button {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--brown);
        text-decoration: none;
        font-weight: 600;
        font-family: 'BaticaSans', sans-serif;
        transition: var(--transition);
        padding: 0.8rem 1.5rem;
        border: 2px solid var(--brown);
        border-radius: 50px;
        background: transparent;
    }

    .back-button:hover {
        background: var(--brown);
        color: var(--white);
        transform: translateY(-2px);
        box-shadow: var(--shadow-soft);
    }

    /* Menu Details Layout */
    .menu-details-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 3rem;
        margin-bottom: 3rem;
    }

    /* Image Gallery */
    .image-gallery {
        position: sticky;
        top: 150px; /* Account for header */
    }

    .main-image-container {
        position: relative;
        width: 100%;
        aspect-ratio: 1.5 / 1; /* Desktop: 1.5:1 ratio */
        margin-bottom: 1rem;
        border-radius: 16px;
        overflow: hidden;
        background: linear-gradient(135deg, var(--cream), #e8dcc0);
        cursor: pointer;
        transition: var(--transition);
    }

    .main-image-container:hover {
        transform: scale(1.02);
    }

    .main-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: var(--transition);
    }

    .main-image-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        color: var(--text-gray);
        font-size: 1.5rem;
        font-weight: 600;
        font-family: 'BaticaSans', sans-serif;
        gap: 1rem;
    }

    .thumbnail-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1rem;
    }

    .thumbnail {
        position: relative;
        aspect-ratio: 1 / 1; /* Desktop: 1:1 ratio */
        border-radius: 12px;
        overflow: hidden;
        background: linear-gradient(135deg, #f8f4f0, #e8dcc0);
        cursor: pointer;
        transition: var(--transition);
        border: 3px solid transparent;
    }

    .thumbnail:hover {
        transform: scale(1.05);
        box-shadow: var(--shadow-soft);
    }

    .thumbnail.active {
        border-color: var(--brown);
        transform: scale(1.05);
    }

    .thumbnail img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .thumbnail-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-gray);
        font-size: 1.2rem;
        opacity: 0.5;
    }

    /* Menu Information */
    .menu-info {
        padding-top: 1rem;
    }

    .menu-header {
        margin-bottom: 2rem;
        padding-bottom: 1.5rem;
        border-bottom: 2px solid var(--border-light);
    }

    .menu-category {
        font-size: 0.9rem;
        color: var(--brown);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.5rem;
        font-family: 'BaticaSans', sans-serif;
    }

    .menu-title {
        font-size: 2.5rem;
        font-weight: 800;
        color: var(--brown);
        margin-bottom: 0.5rem;
        line-height: 1.2;
        font-family: 'BaticaSans', sans-serif;
    }

    .menu-title-thai {
        font-size: 1.2rem;
        color: var(--text-gray);
        font-weight: 500;
        margin-bottom: 1rem;
        font-family: 'BaticaSans', sans-serif;
    }

    .menu-price {
        font-size: 2rem;
        font-weight: 800;
        color: var(--curry);
        font-family: 'BaticaSans', sans-serif;
    }

    .menu-section {
        margin-bottom: 2.5rem;
    }

    .section-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: var(--brown);
        margin-bottom: 1rem;
        font-family: 'BaticaSans', sans-serif;
    }

    .menu-description {
        color: var(--text-gray);
        font-size: 1.1rem;
        line-height: 1.7;
        font-family: 'BaticaSans', sans-serif;
    }

    .ingredients-text {
        color: var(--text-gray);
        font-size: 1rem;
        line-height: 1.6;
        font-family: 'BaticaSans', sans-serif;
    }

    /* Nutrition Grid */
    .nutrition-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 1.5rem;
        background: var(--cream);
        padding: 2rem;
        border-radius: 16px;
    }

    .nutrition-item {
        text-align: center;
    }

    .nutrition-value {
        font-size: 1.8rem;
        font-weight: 800;
        color: var(--brown);
        font-family: 'BaticaSans', sans-serif;
        margin-bottom: 0.3rem;
    }

    .nutrition-label {
        font-size: 0.9rem;
        color: var(--text-gray);
        font-weight: 600;
        font-family: 'BaticaSans', sans-serif;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Tags */
    .tags-container {
        display: flex;
        flex-wrap: wrap;
        gap: 0.8rem;
    }

    .tag {
        background: var(--cream);
        color: var(--brown);
        padding: 0.6rem 1.2rem;
        border-radius: 25px;
        font-size: 0.9rem;
        font-weight: 600;
        font-family: 'BaticaSans', sans-serif;
        border: 2px solid var(--border-light);
    }

    .spice-tag {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.6rem 1.2rem;
        border-radius: 25px;
        font-size: 0.9rem;
        font-weight: 600;
        font-family: 'BaticaSans', sans-serif;
    }

    .spice-mild { background: #e8f5e8; color: #2e7d32; border: 2px solid #c8e6c9; }
    .spice-medium { background: #fff8e1; color: #f57f17; border: 2px solid #ffecb3; }
    .spice-hot { background: #ffebee; color: #d32f2f; border: 2px solid #ffcdd2; }
    .spice-extra_hot { background: #ffebee; color: #b71c1c; border: 2px solid #ef9a9a; }

    /* Health Benefits */
    .benefits-list {
        color: var(--text-gray);
        line-height: 1.8;
        padding-left: 2rem;
        font-family: 'BaticaSans', sans-serif;
    }

    .benefits-list li {
        margin-bottom: 0.5rem;
        font-size: 1rem;
    }

    /* Order Section */
    .order-section {
        background: var(--cream);
        padding: 2.5rem;
        border-radius: 20px;
        text-align: center;
        margin-top: 2rem;
        border: 2px solid var(--border-light);
    }

    .order-section h3 {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--brown);
        margin-bottom: 1rem;
        font-family: 'BaticaSans', sans-serif;
    }

    .order-section p {
        color: var(--text-gray);
        margin-bottom: 2rem;
        font-size: 1.1rem;
        font-family: 'BaticaSans', sans-serif;
    }

    .order-buttons {
        display: flex;
        gap: 1rem;
        justify-content: center;
        flex-wrap: wrap;
    }

    /* Featured Badge */
    .featured-badge {
        position: absolute;
        top: 1rem;
        left: 1rem;
        background: var(--brown);
        color: var(--white);
        padding: 0.5rem 1rem;
        border-radius: 25px;
        font-size: 0.8rem;
        font-weight: 600;
        font-family: 'BaticaSans', sans-serif;
        z-index: 10;
    }

    /* Mobile Responsive */
    @media (max-width: 768px) {
        .container {
            padding: 0 15px;
        }

        .menu-details-container {
            grid-template-columns: 1fr;
            gap: 2rem;
        }

        .image-gallery {
            position: static;
            top: auto;
        }

        /* Mobile Image Layout: main image (1:1.5) with 3 thumbnails on the right */
        .mobile-image-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1rem;
            height: 400px;
        }

        .main-image-container {
            aspect-ratio: 1 / 1.5; /* Mobile: 1:1.5 ratio */
            margin-bottom: 0;
            height: 100%;
        }

        .thumbnail-grid {
            grid-template-columns: 1fr;
            grid-template-rows: repeat(3, 1fr);
            height: 100%;
        }

        .thumbnail {
            aspect-ratio: 1 / 1;
        }

        .menu-title {
            font-size: 2rem;
        }

        .menu-price {
            font-size: 1.5rem;
        }

        .nutrition-grid {
            padding: 1.5rem;
            gap: 1rem;
        }

        .nutrition-value {
            font-size: 1.4rem;
        }

        .order-section {
            padding: 2rem 1.5rem;
        }

        .order-buttons {
            flex-direction: column;
            align-items: center;
        }

        .order-buttons .btn {
            width: 100%;
            max-width: 300px;
        }
    }

    @media (max-width: 480px) {
        .mobile-image-layout {
            height: 320px;
        }

        .menu-title {
            font-size: 1.7rem;
        }

        .nutrition-grid {
            grid-template-columns: repeat(2, 1fr);
            padding: 1rem;
        }
    }
    </style>
</head>

<body class="has-header">
    <main class="main-content">
        <div class="container">
            <!-- Back Button -->
            <div class="back-button-container">
                <a href="menus.php" class="back-button">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Back to Menu
                </a>
            </div>

            <!-- Menu Details -->
            <div class="menu-details-container">
                <!-- Image Gallery -->
                <div class="image-gallery">
                    <!-- Desktop Layout -->
                    <div class="desktop-layout" style="display: block;">
                        <div class="main-image-container">
                            <?php if ($menu['is_featured']): ?>
                                <div class="featured-badge">Featured</div>
                            <?php endif; ?>
                            
                            <?php if (!empty($all_images[0])): ?>
                                <img src="<?php echo htmlspecialchars($all_images[0]); ?>" 
                                     alt="<?php echo htmlspecialchars($menu['name'] ?: $menu['name_thai']); ?>" 
                                     class="main-image" 
                                     id="mainImage">
                            <?php else: ?>
                                <div class="main-image-placeholder">
                                    <div style="font-size: 3rem;">🍽️</div>
                                    <div><?php echo htmlspecialchars($menu['name'] ?: $menu['name_thai']); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="thumbnail-grid">
                            <?php for ($i = 1; $i < 4; $i++): ?>
                                <div class="thumbnail <?php echo $i === 1 ? 'active' : ''; ?>" 
                                     onclick="changeMainImage('<?php echo htmlspecialchars($all_images[$i] ?? ''); ?>', this)">
                                    <?php if (!empty($all_images[$i])): ?>
                                        <img src="<?php echo htmlspecialchars($all_images[$i]); ?>" 
                                             alt="<?php echo htmlspecialchars($menu['name'] ?: $menu['name_thai']); ?> - Image <?php echo $i + 1; ?>">
                                    <?php else: ?>
                                        <div class="thumbnail-placeholder">📷</div>
                                    <?php endif; ?>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- Mobile Layout -->
                    <div class="mobile-layout" style="display: none;">
                        <div class="mobile-image-layout">
                            <div class="main-image-container">
                                <?php if ($menu['is_featured']): ?>
                                    <div class="featured-badge">Featured</div>
                                <?php endif; ?>
                                
                                <?php if (!empty($all_images[0])): ?>
                                    <img src="<?php echo htmlspecialchars($all_images[0]); ?>" 
                                         alt="<?php echo htmlspecialchars($menu['name'] ?: $menu['name_thai']); ?>" 
                                         class="main-image" 
                                         id="mainImageMobile">
                                <?php else: ?>
                                    <div class="main-image-placeholder">
                                        <div style="font-size: 2rem;">🍽️</div>
                                        <div style="font-size: 1rem;"><?php echo htmlspecialchars($menu['name'] ?: $menu['name_thai']); ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="thumbnail-grid">
                                <?php for ($i = 1; $i < 4; $i++): ?>
                                    <div class="thumbnail <?php echo $i === 1 ? 'active' : ''; ?>" 
                                         onclick="changeMainImageMobile('<?php echo htmlspecialchars($all_images[$i] ?? ''); ?>', this)">
                                        <?php if (!empty($all_images[$i])): ?>
                                            <img src="<?php echo htmlspecialchars($all_images[$i]); ?>" 
                                                 alt="<?php echo htmlspecialchars($menu['name'] ?: $menu['name_thai']); ?> - Image <?php echo $i + 1; ?>">
                                        <?php else: ?>
                                            <div class="thumbnail-placeholder">📷</div>
                                        <?php endif; ?>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Menu Information -->
                <div class="menu-info">
                    <!-- Header -->
                    <div class="menu-header">
                        <?php if ($menu['category_name']): ?>
                            <div class="menu-category">
                                <?php echo htmlspecialchars($menu['category_name'] ?: $menu['category_name_thai']); ?>
                            </div>
                        <?php endif; ?>

                        <h1 class="menu-title">
                            <?php echo htmlspecialchars($menu['name'] ?: $menu['name_thai']); ?>
                        </h1>

                        <?php if ($menu['name'] && $menu['name_thai']): ?>
                            <div class="menu-title-thai">
                                <?php echo htmlspecialchars($menu['name_thai']); ?>
                            </div>
                        <?php endif; ?>

                        <div class="menu-price">
                            $<?php echo number_format($menu['base_price'], 2); ?>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="menu-section">
                        <h3 class="section-title">Description</h3>
                        <p class="menu-description">
                            <?php echo htmlspecialchars($menu['description'] ?: 'Authentic Thai cuisine prepared with fresh ingredients and traditional recipes, bringing you the authentic taste of Thailand.'); ?>
                        </p>
                    </div>

                    <!-- Ingredients -->
                    <?php if ($menu['ingredients']): ?>
                        <div class="menu-section">
                            <h3 class="section-title">Ingredients</h3>
                            <p class="ingredients-text">
                                <?php echo htmlspecialchars($menu['ingredients']); ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <!-- Nutritional Information -->
                    <div class="menu-section">
                        <h3 class="section-title">Nutritional Information</h3>
                        <div class="nutrition-grid">
                            <?php if ($menu['calories_per_serving']): ?>
                                <div class="nutrition-item">
                                    <div class="nutrition-value"><?php echo number_format($menu['calories_per_serving']); ?></div>
                                    <div class="nutrition-label">Calories</div>
                                </div>
                            <?php endif; ?>

                            <?php if ($menu['protein_g']): ?>
                                <div class="nutrition-item">
                                    <div class="nutrition-value"><?php echo number_format($menu['protein_g'], 1); ?>g</div>
                                    <div class="nutrition-label">Protein</div>
                                </div>
                            <?php endif; ?>

                            <?php if ($menu['carbs_g']): ?>
                                <div class="nutrition-item">
                                    <div class="nutrition-value"><?php echo number_format($menu['carbs_g'], 1); ?>g</div>
                                    <div class="nutrition-label">Carbs</div>
                                </div>
                            <?php endif; ?>

                            <?php if ($menu['fat_g']): ?>
                                <div class="nutrition-item">
                                    <div class="nutrition-value"><?php echo number_format($menu['fat_g'], 1); ?>g</div>
                                    <div class="nutrition-label">Fat</div>
                                </div>
                            <?php endif; ?>

                            <?php if ($menu['fiber_g']): ?>
                                <div class="nutrition-item">
                                    <div class="nutrition-value"><?php echo number_format($menu['fiber_g'], 1); ?>g</div>
                                    <div class="nutrition-label">Fiber</div>
                                </div>
                            <?php endif; ?>

                            <?php if ($menu['sodium_mg']): ?>
                                <div class="nutrition-item">
                                    <div class="nutrition-value"><?php echo number_format($menu['sodium_mg']); ?>mg</div>
                                    <div class="nutrition-label">Sodium</div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Diet Tags & Spice Level -->
                    <?php if (!empty($dietary_tags) || $menu['spice_level']): ?>
                        <div class="menu-section">
                            <h3 class="section-title">Diet & Spice Information</h3>
                            <div class="tags-container">
                                <!-- Dietary Tags -->
                                <?php foreach ($dietary_tags as $tag): ?>
                                    <span class="tag"><?php echo htmlspecialchars($tag); ?></span>
                                <?php endforeach; ?>

                                <!-- Spice Level -->
                                <?php if ($menu['spice_level']): ?>
                                    <span class="spice-tag spice-<?php echo $menu['spice_level']; ?>">
                                        <span><?php echo $spice_icons[$menu['spice_level']] ?? '🌶️'; ?></span>
                                        <span><?php echo $spice_labels[$menu['spice_level']] ?? 'Medium'; ?></span>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Health Benefits -->
                    <?php if (!empty($health_benefits)): ?>
                        <div class="menu-section">
                            <h3 class="section-title">Health Benefits</h3>
                            <ul class="benefits-list">
                                <?php foreach ($health_benefits as $benefit): ?>
                                    <li><?php echo htmlspecialchars($benefit); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <!-- Order Section -->
                    <div class="order-section">
                        <h3>Ready to order this delicious dish?</h3>
                        <p>Join thousands of satisfied customers and start your healthy eating journey today</p>
                        
                        <div class="order-buttons">
                            <?php if ($is_logged_in): ?>
                                <a href="subscribe.php?menu=<?php echo $menu['id']; ?>" class="btn btn-primary">
                                    🛒 Order Now
                                </a>
                                <a href="menus.php" class="btn btn-secondary">
                                    Browse More Dishes
                                </a>
                            <?php else: ?>
                                <a href="register.php?menu=<?php echo $menu['id']; ?>" class="btn btn-primary">
                                    👤 Sign Up to Order
                                </a>
                                <a href="login.php?redirect=menu-details.php?id=<?php echo $menu['id']; ?>" class="btn btn-secondary">
                                    Already have an account?
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
    // Image gallery functionality
    function changeMainImage(imageUrl, clickedThumbnail) {
        if (!imageUrl) return;
        
        const mainImage = document.getElementById('mainImage');
        
        // Remove active class from all desktop thumbnails
        document.querySelectorAll('.desktop-layout .thumbnail').forEach(thumb => {
            thumb.classList.remove('active');
        });
        
        // Add active class to clicked thumbnail
        clickedThumbnail.classList.add('active');
        
        // Change main image
        if (mainImage.tagName === 'IMG') {
            mainImage.src = imageUrl;
        }
    }

    function changeMainImageMobile(imageUrl, clickedThumbnail) {
        if (!imageUrl) return;
        
        const mainImage = document.getElementById('mainImageMobile');
        
        // Remove active class from all mobile thumbnails
        document.querySelectorAll('.mobile-layout .thumbnail').forEach(thumb => {
            thumb.classList.remove('active');
        });
        
        // Add active class to clicked thumbnail
        clickedThumbnail.classList.add('active');
        
        // Change main image
        if (mainImage.tagName === 'IMG') {
            mainImage.src = imageUrl;
        }
    }

    // Responsive layout switching
    function updateImageLayout() {
        const desktopLayout = document.querySelector('.desktop-layout');
        const mobileLayout = document.querySelector('.mobile-layout');
        
        if (window.innerWidth <= 768) {
            desktopLayout.style.display = 'none';
            mobileLayout.style.display = 'block';
        } else {
            desktopLayout.style.display = 'block';
            mobileLayout.style.display = 'none';
        }
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        updateImageLayout();
        
        // Update layout on window resize
        let resizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(updateImageLayout, 250);
        });
    });
    </script>
</body>
</html>