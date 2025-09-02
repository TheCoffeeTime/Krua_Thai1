<?php
/**
 * Somdul Table - Public Menus Page
 * File: menus.php
 * Description: Browse, filter, and search all available menus
 * UPDATED: Removed modal, links to menu-details.php page
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once 'config/database.php';
require_once 'includes/functions.php';

// Include the header (contains navbar, promo banner, fonts, and base styles)
include 'header.php';

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);

try {
    // Fetch categories
    $categories = [];
    $stmt = $pdo->prepare("
        SELECT id, name, name_thai 
        FROM menu_categories 
        WHERE is_active = 1 
        ORDER BY sort_order ASC
    ");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Filter/Search logic
    $category_id = $_GET['category_id'] ?? '';
    $search = $_GET['search'] ?? '';
    $diet = $_GET['diet'] ?? '';
    $spice = $_GET['spice'] ?? '';
    $max_price = $_GET['max_price'] ?? '';

    // Build WHERE clause
    $where_conditions = ["m.is_available = 1"];
    $params = [];

    if ($category_id) {
        $where_conditions[] = "m.category_id = ?";
        $params[] = $category_id;
    }

    if ($search) {
        $where_conditions[] = "(m.name LIKE ? OR m.name_thai LIKE ? OR m.description LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }

    if ($diet) {
        $where_conditions[] = "JSON_CONTAINS(m.dietary_tags, ?)";
        $params[] = '"' . $diet . '"';
    }

    if ($spice) {
        $where_conditions[] = "m.spice_level = ?";
        $params[] = $spice;
    }

    if ($max_price) {
        $where_conditions[] = "m.base_price <= ?";
        $params[] = $max_price;
    }

    $where_clause = "WHERE " . implode(" AND ", $where_conditions);

    // Get menus with additional images
    $sql = "
        SELECT m.*, c.name AS category_name, c.name_thai AS category_name_thai,
               m.main_image_url, m.additional_images
        FROM menus m 
        LEFT JOIN menu_categories c ON m.category_id = c.id 
        $where_clause 
        ORDER BY m.is_featured DESC, m.updated_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all available dietary tags for filter
    $stmt = $pdo->prepare("
        SELECT DISTINCT JSON_UNQUOTE(JSON_EXTRACT(dietary_tags, CONCAT('$[', idx, ']'))) as tag
        FROM menus m
        JOIN (
            SELECT 0 as idx UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
        ) as indexes
        WHERE JSON_EXTRACT(dietary_tags, CONCAT('$[', idx, ']')) IS NOT NULL
        AND m.is_available = 1
        ORDER BY tag
    ");
    $stmt->execute();
    $available_diets = $stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (Exception $e) {
    error_log("Menus page error: " . $e->getMessage());
    $menus = [];
    $categories = [];
    $available_diets = [];
}

// Get price range for filter
$max_menu_price = 500; // Default fallback
try {
    $stmt = $pdo->prepare("SELECT MAX(base_price) FROM menus WHERE is_available = 1");
    $stmt->execute();
    $max_menu_price = $stmt->fetchColumn() ?: 500;
} catch (Exception $e) {
    // Use fallback
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu - Somdul Table | Authentic Thai Restaurant Management</title>
    <meta name="description" content="Browse our healthy Thai food menu from Somdul Table with complete nutritional information and pricing">
    
    <style>
    /* PAGE-SPECIFIC STYLES ONLY - header styles come from header.php */
    
    /* Container */
    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
    }

    .main-content {
        padding-top: 2rem;
        min-height: calc(100vh - 200px);
    }

    /* Menu Navigation */
    .menu-nav-container {
        margin: 2rem 0;
        padding: 20px 0;
        background: var(--cream);
        border-top: 1px solid rgba(189, 147, 121, 0.1);
        border-bottom: 1px solid rgba(189, 147, 121, 0.1);
        position: relative;
        display: flex;
        align-items: center;
    }

    .menu-nav-scroll-btn {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 40px;
        height: 40px;
        border: none;
        background: rgba(255, 255, 255, 0.95);
        color: var(--brown);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 10;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        opacity: 0;
        visibility: hidden;
        backdrop-filter: blur(10px);
    }

    .menu-nav-scroll-btn:hover {
        background: var(--brown);
        color: var(--white);
        transform: translateY(-50%) scale(1.1);
    }

    .menu-nav-scroll-btn:disabled {
        opacity: 0.3;
        cursor: not-allowed;
        pointer-events: none;
    }

    .menu-nav-scroll-btn.visible {
        opacity: 1;
        visibility: visible;
    }

    .menu-nav-scroll-left {
        left: 10px;
    }

    .menu-nav-scroll-right {
        right: 10px;
    }

    .menu-nav-wrapper {
        overflow-x: auto;
        scrollbar-width: none;
        -ms-overflow-style: none;
        padding: 0 60px; /* Add padding to account for scroll buttons */
        max-width: 1200px;
        margin: 0 auto;
        width: 100%;
        scroll-behavior: smooth;
    }

    .menu-nav-wrapper::-webkit-scrollbar { display: none; }

    .menu-nav-list {
        display: flex;
        gap: 0;
        min-width: max-content;
        align-items: center;
        justify-content: center;
    }

    .menu-nav-item {
        display: flex;
        align-items: center;
        gap: 8px;
        height: 54px;
        padding: 0 16px;
        border-bottom: 2px solid transparent;
        background: transparent;
        cursor: pointer;
        font-family: 'BaticaSans', Arial, sans-serif;
        font-size: 14px;
        font-weight: 600;
        color: #707070;
        transition: all 0.3s ease;
        white-space: nowrap;
        text-decoration: none;
        border-radius: 8px;
        outline: none !important;
        -webkit-tap-highlight-color: transparent;
    }

    .menu-nav-item:focus {
        outline: none;
        box-shadow: 0 0 0 2px rgba(189, 147, 121, 0.3);
    }

    .menu-nav-item:hover {
        color: var(--brown);
        background: rgba(189, 147, 121, 0.1);
        border-bottom-color: var(--brown);
    }

    .menu-nav-item.active {
        color: var(--brown);
        background: var(--white);
        border-bottom-color: var(--brown);
        box-shadow: var(--shadow-soft);
    }

    .menu-nav-icon {
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .menu-nav-icon svg {
        width: 100%;
        height: 100%;
        fill: #707070;
        transition: fill 0.3s ease;
    }

    .menu-nav-item:hover .menu-nav-icon svg { fill: var(--brown); }
    .menu-nav-item.active .menu-nav-icon svg { fill: var(--brown); }

    .menu-nav-text {
        font-size: 14px;
        font-weight: 600;
    }

    /* Results */
    .results-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        padding: 1rem 0;
        border-bottom: 2px solid var(--border-light);
    }

    .results-count {
        font-size: 1.1rem;
        color: var(--text-gray);
        font-family: 'BaticaSans', sans-serif;
    }

    /* Menu Grid */
    .menus-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 2rem;
        margin-bottom: 3rem;
    }

    .menu-card {
        background: var(--white);
        border-radius: 16px;
        overflow: hidden;
        box-shadow: var(--shadow-soft);
        border: 1px solid var(--border-light);
        transition: var(--transition);
        position: relative;
    }

    .menu-card:hover {
        transform: translateY(-8px);
        box-shadow: var(--shadow-medium);
    }

    .menu-image {
        position: relative;
        height: 200px;
        background: linear-gradient(135deg, var(--cream), #e8dcc0);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-gray);
        font-size: 1rem;
        font-weight: 600;
        font-family: 'BaticaSans', sans-serif;
        cursor: pointer;
        transition: var(--transition);
    }

    .menu-image:hover { transform: scale(1.02); }
    .menu-image img { width: 100%; height: 100%; object-fit: cover; }

    .menu-badge {
        position: absolute;
        top: 1rem;
        right: 1rem;
        background: rgba(255, 255, 255, 0.95);
        color: var(--brown);
        padding: 0.4rem 0.8rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        backdrop-filter: blur(10px);
        font-family: 'BaticaSans', sans-serif;
    }

    .featured-badge {
        position: absolute;
        top: 1rem;
        left: 1rem;
        background: var(--brown);
        color: var(--white);
        padding: 0.4rem 0.8rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        font-family: 'BaticaSans', sans-serif;
    }

    .menu-content { padding: 1.5rem; }

    .menu-category {
        font-size: 0.8rem;
        color: var(--brown);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.5rem;
        font-family: 'BaticaSans', sans-serif;
    }

    .menu-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: var(--brown);
        margin-bottom: 0.8rem;
        line-height: 1.3;
        font-family: 'BaticaSans', sans-serif;
    }

    .menu-title-en {
        font-size: 0.9rem;
        color: var(--text-gray);
        font-weight: 500;
        margin-bottom: 0.8rem;
        font-family: 'BaticaSans', sans-serif;
    }

    .menu-description {
        color: var(--text-gray);
        font-size: 0.95rem;
        line-height: 1.5;
        margin-bottom: 1.2rem;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
        font-family: 'BaticaSans', sans-serif;
    }

    .menu-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
        margin-bottom: 1.2rem;
    }

    .tag {
        background: var(--cream);
        color: var(--brown);
        padding: 0.3rem 0.6rem;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        font-family: 'BaticaSans', sans-serif;
    }

    .spice-tag {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.3rem 0.6rem;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        font-family: 'BaticaSans', sans-serif;
    }

    .spice-mild { background: #e8f5e8; color: #2e7d32; }
    .spice-medium { background: #fff8e1; color: #f57f17; }
    .spice-hot { background: #ffebee; color: #d32f2f; }
    .spice-extra_hot { background: #ffebee; color: #b71c1c; }

    .menu-nutrition {
        display: flex;
        gap: 1rem;
        margin-bottom: 1.2rem;
        flex-wrap: wrap;
    }

    .nutrition-item {
        text-align: center;
        flex: 1;
        min-width: 60px;
    }

    .nutrition-value {
        font-weight: 700;
        color: var(--brown);
        font-size: 0.9rem;
        font-family: 'BaticaSans', sans-serif;
    }

    .nutrition-label {
        font-size: 0.8rem;
        color: var(--text-gray);
        margin-top: 0.2rem;
        font-family: 'BaticaSans', sans-serif;
    }

    .menu-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 1rem;
        border-top: 1px solid var(--border-light);
    }

    .menu-price {
        font-size: 1.3rem;
        font-weight: 800;
        color: var(--brown);
        font-family: 'BaticaSans', sans-serif;
    }

    .menu-actions {
        display: flex;
        gap: 0.5rem;
    }

    /* Button styles for menu actions (extend header.php button styles) */
    .btn-sm {
        padding: 0.6rem 1rem;
        font-size: 0.9rem;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        color: var(--text-gray);
        grid-column: 1 / -1;
    }

    .empty-state h3 {
        font-size: 1.5rem;
        margin-bottom: 0.5rem;
        color: var(--brown);
        font-family: 'BaticaSans', sans-serif;
    }

    /* CTA Section */
    .cta-section {
        text-align: center;
        padding: 3rem 2rem;
        background: var(--cream);
        border-radius: 16px;
        margin-top: 3rem;
    }

    .cta-section h2 {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--brown);
        margin-bottom: 1rem;
        font-family: 'BaticaSans', sans-serif;
    }

    .cta-section p {
        font-size: 1.1rem;
        color: var(--text-gray);
        margin-bottom: 2rem;
        max-width: 600px;
        margin-left: auto;
        margin-right: auto;
        font-family: 'BaticaSans', sans-serif;
    }

    /* Footer */
    footer {
        background: var(--text-dark);
        color: var(--white);
        padding: 2rem 0;
        text-align: center;
        margin-top: 4rem;
    }

    /* Loading animation */
    .loading {
        text-align: center;
        padding: 2rem;
        color: var(--text-gray);
    }

    .loading i {
        font-size: 2rem;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* Mobile responsiveness */
    @media (max-width: 768px) {
        .container { padding: 0 15px; }
        .menus-grid { grid-template-columns: 1fr; }
        .results-header { flex-direction: column; gap: 1rem; align-items: flex-start; }
        
        .menu-nav-wrapper {
            padding: 0 50px; /* Slightly less padding on mobile */
        }
        
        .menu-nav-scroll-btn {
            width: 36px;
            height: 36px;
        }
        
        .menu-nav-scroll-left {
            left: 8px;
        }

        .menu-nav-scroll-right {
            right: 8px;
        }
        
        .menu-nav-item { padding: 0 12px; font-size: 13px; }
        .menu-nav-icon { width: 20px; height: 20px; }
        .menu-actions { flex-direction: column; gap: 0.5rem; }
        .menu-footer { flex-direction: column; gap: 1rem; align-items: flex-start; }
        .cta-section { padding: 2rem 1rem; }
    }
    </style>
</head>

<!-- IMPORTANT: Add has-header class for proper spacing -->
<body class="has-header">
    <!-- The header (promo banner + navbar) is already included from header.php -->
    
    <!-- Main Content -->
    <main class="main-content">
        <div class="container">

            <!-- Menu Navigation Container -->
            <div class="menu-nav-container">
                <button class="menu-nav-scroll-btn menu-nav-scroll-left" id="menuScrollLeft" aria-label="Scroll left">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
                
                <div class="menu-nav-wrapper" id="menuNavWrapper">
                    <div class="menu-nav-list">
                        <?php if (empty($categories)): ?>
                            <a href="menus.php" class="menu-nav-item <?php echo empty($category_id) ? 'active' : ''; ?>">
                                <span class="menu-nav-icon">
                                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>
                                    </svg>
                                </span>
                                <span class="menu-nav-text">All Items</span>
                            </a>
                        <?php else: ?>
                            <a href="menus.php" class="menu-nav-item <?php echo empty($category_id) ? 'active' : ''; ?>">
                                <span class="menu-nav-icon">
                                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>
                                    </svg>
                                </span>
                                <span class="menu-nav-text">All Items</span>
                            </a>
                            
                            <?php 
                            $category_icons = [
                                'Rice Bowls' => '<path d="M12 2c-1.1 0-2 .9-2 2v2H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-2V4c0-1.1-.9-2-2-2zm0 2v2h-2V4h2zm-4 4h8v2h-8V8zm0 4h8v6H8v-6z"/>',
                                'Thai Curries' => '<path d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zM6 18.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm13.5-9l1.96 2.5H17V9.5h2.5zm-1.5 9c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/>',
                                'Noodle Dishes' => '<path d="M22 2v20H2V2h20zm-2 2H4v16h16V4zM6 8h12v2H6V8zm0 4h12v2H6v-2zm0 4h8v2H6v-2z"/>',
                                'Stir Fry' => '<path d="M12.5 3.5c0 .83-.67 1.5-1.5 1.5s-1.5-.67-1.5-1.5S10.17 2 11 2s1.5.67 1.5 1.5zM20 8H4c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2v-8c0-1.1-.9-2-2-2zm0 10H4v-8h16v8zm-8-6c1.38 0 2.5 1.12 2.5 2.5S13.38 17 12 17s-2.5-1.12-2.5-2.5S10.62 12 12 12z"/>',
                                'Rice Dishes' => '<path d="M18 3H6c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H6V5h12v14zM8 7h8v2H8V7zm0 4h8v2H8v-2zm0 4h6v2H8v-2z"/>',
                                'Soups' => '<path d="M4 18h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2zm0-10h16v8H4V8zm8-4c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2z"/>',
                                'Salads' => '<path d="M7 10c0-1.1.9-2 2-2s2 .9 2 2-.9 2-2 2-2-.9-2-2zm8 0c0-1.1.9-2 2-2s2 .9 2 2-.9 2-2 2-2-.9-2-2zM12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8 0-1.12.23-2.18.65-3.15C6.53 8.51 8 8 9.64 8c.93 0 1.83.22 2.64.61.81-.39 1.71-.61 2.64-.61 1.64 0 3.11.51 4.35.85.42.97.65 2.03.65 3.15 0 4.41-3.59 8-8 8z"/>',
                                'Desserts' => '<path d="M12 3L8 6.5h8L12 3zm0 18c4.97 0 9-4.03 9-9H3c0 4.97 4.03 9 9 9zm0-16L8.5 8h7L12 5z"/>',
                                'Beverages' => '<path d="M5 4v3h5.5v12h3V7H19V4H5z"/>'
                            ];
                            
                            $default_icon = '<path d="M12 2c-1.1 0-2 .9-2 2v2H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-2V4c0-1.1-.9-2-2-2zm0 2v2h-2V4h2zm-4 4h8v2h-8V8zm0 4h8v6H8v-6z"/>';
                            
                            foreach ($categories as $category): 
                                $category_name = $category['name'] ?: $category['name_thai'];
                                $icon_path = $category_icons[$category_name] ?? $default_icon;
                                $is_active = ($category_id == $category['id']) ? 'active' : '';
                            ?>
                                <a href="menus.php?category_id=<?php echo $category['id']; ?>" 
                                   class="menu-nav-item <?php echo $is_active; ?>">
                                    <span class="menu-nav-icon">
                                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <?php echo $icon_path; ?>
                                        </svg>
                                    </span>
                                    <span class="menu-nav-text">
                                        <?php echo htmlspecialchars($category_name); ?>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <button class="menu-nav-scroll-btn menu-nav-scroll-right" id="menuScrollRight" aria-label="Scroll right">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M9 18L15 12L9 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>

            <!-- Results Header -->
            <div class="results-header">
                <div class="results-count">
                    Found <strong><?php echo count($menus); ?></strong> dishes
                    <?php if ($search || $category_id || $diet || $spice || $max_price): ?>
                        from your search
                    <?php endif; ?>
                </div>
            </div>

            <!-- Menus Grid -->
            <div class="menus-grid">
                <?php if (empty($menus)): ?>
                    <div class="empty-state">
                        <i style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.3;">🍽️</i>
                        <h3>No dishes found</h3>
                        <p>Try changing your search terms or filter criteria</p>
                        <a href="menus.php" class="btn btn-primary" style="margin-top: 1rem;">
                            View All Menu
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($menus as $menu): ?>
                        <?php
                        $dietary_tags = json_decode($menu['dietary_tags'] ?? '[]', true);
                        if (!is_array($dietary_tags)) $dietary_tags = [];
                        
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
                        <div class="menu-card">
                            <?php if ($menu['is_featured']): ?>
                                <div class="featured-badge">Featured</div>
                            <?php endif; ?>
                            
                            <div class="menu-image" onclick="window.location.href='menu-details.php?id=<?php echo $menu['id']; ?>'" title="Click to view details">
                                <?php if ($menu['main_image_url']): ?>
                                    <img src="<?php echo htmlspecialchars($menu['main_image_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($menu['name']); ?>" 
                                         loading="lazy">
                                <?php else: ?>
                                    <div style="text-align: center;">
                                        <div style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.5;">🍽️</div>
                                        <?php echo htmlspecialchars($menu['name'] ?: $menu['name_thai']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($menu['category_name']): ?>
                                    <div class="menu-badge">
                                        <?php echo htmlspecialchars($menu['category_name'] ?: $menu['category_name_thai']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="menu-content">
                                <?php if ($menu['category_name']): ?>
                                    <div class="menu-category">
                                        <?php echo htmlspecialchars($menu['category_name'] ?: $menu['category_name_thai']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <h3 class="menu-title">
                                    <?php echo htmlspecialchars($menu['name'] ?: $menu['name_thai']); ?>
                                </h3>
                                
                                <?php if ($menu['name_thai'] && $menu['name']): ?>
                                    <div class="menu-title-en">
                                        <?php echo htmlspecialchars($menu['name_thai']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <p class="menu-description">
                                    <?php echo htmlspecialchars($menu['description'] ?: 'Healthy Thai cuisine'); ?>
                                </p>
                                
                                <!-- Tags -->
                                <div class="menu-tags">
                                    <!-- Dietary Tags -->
                                    <?php foreach (array_slice($dietary_tags, 0, 2) as $tag): ?>
                                        <span class="tag"><?php echo htmlspecialchars($tag); ?></span>
                                    <?php endforeach; ?>
                                    
                                    <!-- Spice Level -->
                                    <?php if ($menu['spice_level']): ?>
                                        <span class="spice-tag spice-<?php echo $menu['spice_level']; ?>">
                                            <?php echo $spice_icons[$menu['spice_level']] ?? '🌶️'; ?>
                                            <?php echo $spice_labels[$menu['spice_level']] ?? 'Medium'; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Nutrition Info -->
                                <div class="menu-nutrition">
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
                                </div>
                                
                                <!-- Footer -->
                                <div class="menu-footer">
                                    <div class="menu-price">
                                        $<?php echo number_format($menu['base_price'], 2); ?>
                                    </div>
                                    
                                    <div class="menu-actions">
                                        <a href="menu-details.php?id=<?php echo $menu['id']; ?>" 
                                           class="btn btn-secondary btn-sm">
                                            Details
                                        </a>
                                        
                                        <?php if ($is_logged_in): ?>
                                            <a href="subscribe.php?menu=<?php echo $menu['id']; ?>" 
                                            class="btn btn-primary btn-sm">
                                                🛒 Order Now
                                            </a>
                                        <?php else: ?>
                                            <a href="register.php?menu=<?php echo $menu['id']; ?>" 
                                            class="btn btn-primary btn-sm">
                                                Sign Up to Order
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Call-to-Action Section -->
            <?php if (!empty($menus)): ?>
                <div class="cta-section">
                    <h2>Ready to start your healthy eating journey?</h2>
                    <p>
                        Choose the meal plan that's right for you and start taking care of your health with authentic Thai cuisine
                    </p>
                    <a href="index.php#plans" class="btn btn-primary" style="font-size: 1.1rem; padding: 1rem 2.5rem;">
                        🌿 View All Plans
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div style="display: flex; justify-content: center; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                <img src="./assets/image/LOGO_BG.png" alt="Somdul Table" style="height: 45px; width: auto;">
                <span style="font-size: 1.5rem; font-weight: 700;">Somdul Table</span>
            </div>
            <p style="color: var(--text-gray); margin-bottom: 0.5rem;">
                Healthy Thai food delivered to your door
            </p>
            <p style="color: var(--text-gray); font-size: 0.9rem;">
                © 2025 Somdul Table. All rights reserved.
            </p>
        </div>
    </footer>

    <script>
    // Page-specific JavaScript for menus.php
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🍽️ Menus page loaded');
        
        // Menu navigation scroll functionality
        const menuNavWrapper = document.getElementById('menuNavWrapper');
        const menuScrollLeftBtn = document.getElementById('menuScrollLeft');
        const menuScrollRightBtn = document.getElementById('menuScrollRight');
        
        function updateMenuScrollButtons() {
            if (!menuNavWrapper || !menuScrollLeftBtn || !menuScrollRightBtn) return;
            
            const canScrollLeft = menuNavWrapper.scrollLeft > 0;
            const canScrollRight = menuNavWrapper.scrollLeft < (menuNavWrapper.scrollWidth - menuNavWrapper.clientWidth);
            const hasOverflow = menuNavWrapper.scrollWidth > menuNavWrapper.clientWidth;
            
            if (hasOverflow) {
                menuScrollLeftBtn.classList.add('visible');
                menuScrollRightBtn.classList.add('visible');
                
                menuScrollLeftBtn.disabled = !canScrollLeft;
                menuScrollRightBtn.disabled = !canScrollRight;
            } else {
                menuScrollLeftBtn.classList.remove('visible');
                menuScrollRightBtn.classList.remove('visible');
            }
        }
        
        // Menu scroll button event listeners
        if (menuScrollLeftBtn && menuScrollRightBtn && menuNavWrapper) {
            menuScrollLeftBtn.addEventListener('click', () => {
                menuNavWrapper.scrollBy({ left: -200, behavior: 'smooth' });
            });
            
            menuScrollRightBtn.addEventListener('click', () => {
                menuNavWrapper.scrollBy({ left: 200, behavior: 'smooth' });
            });
            
            // Update scroll buttons when scrolling
            menuNavWrapper.addEventListener('scroll', updateMenuScrollButtons);
            
            // Update scroll buttons on window resize
            window.addEventListener('resize', updateMenuScrollButtons);
            
            // Initial update
            setTimeout(updateMenuScrollButtons, 100);
        }
    });
    </script>
</body>
</html>