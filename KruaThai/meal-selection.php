<?php
/**
 * Somdul Table - Meal Selection Page
 * File: meal-selection.php
 * Description: Select meals according to package amount (Step 2)
 * Updated to properly store meal details in session for checkout
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Database connection with fallback
try {
    require_once 'config/database.php';
    $database = new Database();
    $pdo = $database->getConnection();
} catch (Exception $e) {
    try {
        // Fallback connection for MAMP/XAMPP
        $pdo = new PDO("mysql:host=localhost;dbname=somdul_table;charset=utf8mb4", "root", "root");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        try {
            $pdo = new PDO("mysql:host=localhost:8889;dbname=somdul_table;charset=utf8mb4", "root", "root");
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $plan = $_GET['plan'] ?? '';
    $redirect_url = 'meal-selection.php' . ($plan ? '?plan=' . urlencode($plan) : '');
    header('Location: login.php?redirect=' . urlencode($redirect_url));
    exit;
}

// Get plan information
$plan_id = $_GET['plan'] ?? '';
$highlight_menu_id = $_GET['menu'] ?? '';

if (empty($plan_id)) {
    header('Location: subscribe.php');
    exit;
}

// Helper function to get plan name with fallback
function getPlanName($plan) {
    if (isset($plan['name_english']) && !empty($plan['name_english'])) {
        return $plan['name_english'];
    } elseif (isset($plan['name_thai']) && !empty($plan['name_thai'])) {
        return $plan['name_thai'];
    } else {
        return 'Package ' . ($plan['meals_per_week'] ?? 'Unknown');
    }
}

// Helper function to get menu name with fallback
function getMenuName($menu) {
    if (isset($menu['name']) && !empty($menu['name'])) {
        return $menu['name'];
    } elseif (isset($menu['name_thai']) && !empty($menu['name_thai'])) {
        return $menu['name_thai'];
    } else {
        return 'Menu Item';
    }
}

// Helper function to get category name with fallback
function getCategoryName($category) {
    if (isset($category['name']) && !empty($category['name'])) {
        return $category['name'];
    } elseif (isset($category['name_thai']) && !empty($category['name_thai'])) {
        return $category['name_thai'];
    } else {
        return 'Category';
    }
}

try {
    // Get plan details from database
    $stmt = $pdo->prepare("SELECT * FROM subscription_plans WHERE id = ? AND is_active = 1");
    $stmt->execute([$plan_id]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$plan) {
        // Fallback plans matching database structure
        $plans = [
            '74c0ed20-5afc-11f0-8b7f-3f129bd34f14' => ['id' => '74c0ed20-5afc-11f0-8b7f-3f129bd34f14', 'name_thai' => 'แพ็กเกจ 4 มื้อ', 'name_english' => '4 Meal Package', 'meals_per_week' => 4, 'final_price' => 2500],
            'd6e98c56-5afb-11f0-8b7f-3f129bd34f14' => ['id' => 'd6e98c56-5afb-11f0-8b7f-3f129bd34f14', 'name_thai' => 'แพ็กเกจ 8 มื้อ', 'name_english' => '8 Meal Package', 'meals_per_week' => 8, 'final_price' => 4500],
            'd6e98d96-5afb-11f0-8b7f-3f129bd34f14' => ['id' => 'd6e98d96-5afb-11f0-8b7f-3f129bd34f14', 'name_thai' => 'แพ็กเกจ 12 มื้อ', 'name_english' => '12 Meal Package', 'meals_per_week' => 12, 'final_price' => 6200],
            'd6e98e7c-5afb-11f0-8b7f-3f129bd34f14' => ['id' => 'd6e98e7c-5afb-11f0-8b7f-3f129bd34f14', 'name_thai' => 'แพ็กเกจ 15 มื้อ', 'name_english' => '15 Meal Package', 'meals_per_week' => 15, 'final_price' => 7500],
            // Support old IDs
            '4' => ['id' => '4', 'name_thai' => 'แพ็กเกจ 4 มื้อ', 'name_english' => '4 Meal Package', 'meals_per_week' => 4, 'final_price' => 2500],
            '8' => ['id' => '8', 'name_thai' => 'แพ็กเกจ 8 มื้อ', 'name_english' => '8 Meal Package', 'meals_per_week' => 8, 'final_price' => 4500],
            '12' => ['id' => '12', 'name_thai' => 'แพ็กเกจ 12 มื้อ', 'name_english' => '12 Meal Package', 'meals_per_week' => 12, 'final_price' => 6200],
            '15' => ['id' => '15', 'name_thai' => 'แพ็กเกจ 15 มื้อ', 'name_english' => '15 Meal Package', 'meals_per_week' => 15, 'final_price' => 7500]
        ];
        $plan = $plans[$plan_id] ?? $plans['8']; // Default to 8 meals
    }
    
    // Get available menus
    $stmt = $pdo->prepare("
        SELECT m.*, mc.name as category_name, mc.name_thai as category_name_thai
        FROM menus m 
        LEFT JOIN menu_categories mc ON m.category_id = mc.id 
        WHERE m.is_available = 1 
        ORDER BY mc.sort_order ASC, m.name ASC
    ");
    $stmt->execute();
    $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get menu categories for filters
    $stmt = $pdo->prepare("
        SELECT DISTINCT mc.* 
        FROM menu_categories mc
        INNER JOIN menus m ON mc.id = m.category_id
        WHERE mc.is_active = 1 AND m.is_available = 1
        ORDER BY mc.sort_order ASC
    ");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    // Fallback data if database fails
    $plan = ['id' => $plan_id, 'name_thai' => 'Selected Package', 'name_english' => 'Selected Package', 'meals_per_week' => 8, 'final_price' => 4500];
    $menus = [
        ['id' => '74184fca-57ba-11f0-a23d-e6c5297ffe7c', 'name' => 'Pad Thai (Shrimp)', 'name_thai' => 'ผัดไทยกุ้ง', 'base_price' => 320, 'category_name' => 'Rice Bowls', 'category_name_thai' => 'ข้าวกล่อง', 'description' => 'Classic Pad Thai with shrimp, tofu, and peanuts.'],
        ['id' => '741955aa-57ba-11f0-a23d-e6c5297ffe7c', 'name' => 'Pad Thai (Vegan)', 'name_thai' => 'ผัดไทยเจ', 'base_price' => 290, 'category_name' => 'Rice Bowls', 'category_name_thai' => 'ข้าวกล่อง', 'description' => 'Vegan Pad Thai with tofu, vegetables, and peanuts.'],
        ['id' => '74195a82-57ba-11f0-a23d-e6c5297ffe7c', 'name' => 'Thai Basil (Chicken) + Rice', 'name_thai' => 'ผัดกะเพราไก่', 'base_price' => 305, 'category_name' => 'Rice Bowls', 'category_name_thai' => 'ข้าวกล่อง', 'description' => 'Spicy chicken stir-fried with basil, served with rice.'],
        ['id' => '74195c1c-57ba-11f0-a23d-e6c5297ffe7c', 'name' => 'Green Curry (Chicken) + Rice', 'name_thai' => 'แกงเขียวหวานไก่', 'base_price' => 330, 'category_name' => 'Thai Curries', 'category_name_thai' => 'แกงไทย', 'description' => 'Mildly spicy green curry with chicken and rice.'],
        ['id' => '74195d7a-57ba-11f0-a23d-e6c5297ffe7c', 'name' => 'Vegan Larb (Tofu)', 'name_thai' => 'ลาบเต้าหู้', 'base_price' => 265, 'category_name' => 'Rice Bowls', 'category_name_thai' => 'ข้าวกล่อง', 'description' => 'Northeastern style spicy tofu salad.'],
        ['id' => '74195ed8-57ba-11f0-a23d-e6c5297ffe7c', 'name' => 'Cashew Chicken + Rice', 'name_thai' => 'ไก่ผัดเม็ดมะม่วง', 'base_price' => 320, 'category_name' => 'Rice Bowls', 'category_name_thai' => 'ข้าวกล่อง', 'description' => 'Chicken stir-fried with cashews and vegetables, served with rice.'],
        ['id' => '74196068-57ba-11f0-a23d-e6c5297ffe7c', 'name' => 'Tom Kha (Chicken) + Rice', 'name_thai' => 'ต้มข่าไก่', 'base_price' => 305, 'category_name' => 'Thai Curries', 'category_name_thai' => 'แกงไทย', 'description' => 'Coconut milk soup with chicken and herbs, served with rice.'],
        ['id' => '74196cac-57ba-11f0-a23d-e6c5297ffe7c', 'name' => 'Beef Crying Tiger + Sticky Rice', 'name_thai' => 'เนื้อเสือร้องไห้', 'base_price' => 385, 'category_name' => 'Rice Bowls', 'category_name_thai' => 'ข้าวกล่อง', 'description' => 'Grilled beef with spicy dipping sauce, served with sticky rice.'],
        ['id' => '7419d55c-57ba-11f0-a23d-e6c5297ffe7c', 'name' => 'Tom Yum (Shrimp) soup', 'name_thai' => 'ต้มยำกุ้ง', 'base_price' => 345, 'category_name' => 'Thai Curries', 'category_name_thai' => 'แกงไทย', 'description' => 'Spicy and sour shrimp soup with Thai herbs.'],
        ['id' => '7419d89a-57ba-11f0-a23d-e6c5297ffe7c', 'name' => 'Chicken Satay', 'name_thai' => 'สะเต๊ะไก่', 'base_price' => 280, 'category_name' => 'Rice Bowls', 'category_name_thai' => 'ข้าวกล่อง', 'description' => 'Grilled chicken skewers served with peanut sauce.']
    ];
    $categories = [
        ['id' => '550e8400-e29b-41d4-a716-446655440005', 'name' => 'Rice Bowls', 'name_thai' => 'ข้าวกล่อง'],
        ['id' => '550e8400-e29b-41d4-a716-446655440006', 'name' => 'Thai Curries', 'name_thai' => 'แกงไทย']
    ];
}

// Handle meal selection submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'proceed_to_checkout') {
    header('Content-Type: application/json');
    
    $selected_meals = json_decode($_POST['selected_meals'] ?? '[]', true);
    
    if (!is_array($selected_meals)) {
        echo json_encode(['success' => false, 'message' => 'Invalid menu data. Please try again.']);
        exit;
    }
    
    if (count($selected_meals) !== (int)$plan['meals_per_week']) {
        echo json_encode(['success' => false, 'message' => 'Please select exactly ' . $plan['meals_per_week'] . ' meals (currently selected: ' . count($selected_meals) . ' meals)']);
        exit;
    }
    
    // **FIX: Fetch meal details from database to store in session**
    $meal_details = [];
    if (!empty($selected_meals)) {
        try {
            // Create placeholders for the IN clause
            $placeholders = str_repeat('?,', count($selected_meals) - 1) . '?';
            
            $stmt = $pdo->prepare("
                SELECT m.id, m.name, m.name_thai, m.base_price, m.description, m.main_image_url,
                       mc.name as category_name, mc.name_thai as category_name_thai
                FROM menus m 
                LEFT JOIN menu_categories mc ON m.category_id = mc.id 
                WHERE m.id IN ($placeholders) AND m.is_available = 1
            ");
            
            $stmt->execute($selected_meals);
            $meal_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Create associative array with meal ID as key
            foreach ($meal_results as $meal) {
                $meal_details[$meal['id']] = $meal;
            }
            
            // Log for debugging
            error_log("Fetched meal details for checkout: " . json_encode(array_keys($meal_details)));
            
        } catch (Exception $e) {
            error_log("Error fetching meal details: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error retrieving meal information. Please try again.']);
            exit;
        }
    }
    
    // Verify all selected meals were found
    if (count($meal_details) !== count($selected_meals)) {
        echo json_encode(['success' => false, 'message' => 'Some selected meals are no longer available. Please refresh and try again.']);
        exit;
    }
    
    // Store data in session for checkout (UPDATED with meal_details)
    $_SESSION['checkout_data'] = [
        'plan' => $plan,
        'selected_meals' => $selected_meals,
        'meal_details' => $meal_details, // **NEW: Include meal details**
        'total_meals' => count($selected_meals),
        'user_id' => $_SESSION['user_id'],
        'created_at' => time(),
        'source' => 'meal-selection'
    ];
    
    // Log for debugging
    error_log("Stored checkout data with " . count($meal_details) . " meal details");
    
    echo json_encode(['success' => true, 'redirect' => 'checkout.php', 'message' => 'Redirecting to checkout...']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Your Meals | Somdul Table</title>
    <meta name="description" content="Select your perfect meals from authentic Thai cuisine - Somdul Table delivers fresh, healthy meals to your door">
    
    <!-- BaticaSans Font Import -->
    <link rel="preconnect" href="https://ydpschool.com">
    <style>
        @font-face {
            font-family: 'BaticaSans';
            src: url('https://ydpschool.com/fonts/BaticaSans-Regular.woff2') format('woff2'),
                 url('https://ydpschool.com/fonts/BaticaSans-Regular.woff') format('woff'),
                 url('https://ydpschool.com/fonts/BaticaSans-Regular.ttf') format('truetype');
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }
        
        @font-face {
            font-family: 'BaticaSans';
            src: url('https://ydpschool.com/fonts/BaticaSans-Bold.woff2') format('woff2'),
                 url('https://ydpschool.com/fonts/BaticaSans-Bold.woff') format('woff'),
                 url('https://ydpschool.com/fonts/BaticaSans-Bold.ttf') format('truetype');
            font-weight: 700;
            font-style: normal;
            font-display: swap;
        }
        
        @font-face {
            font-family: 'BaticaSans';
            src: url('https://ydpschool.com/fonts/BaticaSans-Medium.woff2') format('woff2'),
                 url('https://ydpschool.com/fonts/BaticaSans-Medium.woff') format('woff'),
                 url('https://ydpschool.com/fonts/BaticaSans-Medium.ttf') format('truetype');
            font-weight: 500;
            font-style: normal;
            font-display: swap;
        }
    </style>
    
    <style>
        /* CSS Custom Properties for Somdul Table Design System */
        :root {
            --brown: #bd9379;
            --cream: #ece8e1;
            --sage: #adb89d;
            --curry: #cf723a;
            --white: #ffffff;
            --text-dark: #2c3e50;
            --text-gray: #7f8c8d;
            --border-light: #e8e8e8;
            --shadow-soft: 0 4px 12px rgba(189, 147, 121, 0.15);
            --shadow-medium: 0 8px 24px rgba(189, 147, 121, 0.25);
            --shadow-large: 0 16px 48px rgba(189, 147, 121, 0.3);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 24px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --success: #27ae60;
            --danger: #e74c3c;
            --warning: #f39c12;
            --info: #3498db;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'BaticaSans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, var(--cream) 0%, #f8f9fa 100%);
            color: var(--text-dark);
            line-height: 1.6;
            min-height: 100vh;
            font-weight: 400;
        }

        /* Typography using BaticaSans */
        h1, h2, h3, h4, h5, h6 {
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
            line-height: 1.2;
            color: var(--text-dark);
        }

        /* Promotional Banner */
        .promo-banner {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, var(--curry) 0%, #e67e22 100%);
            color: var(--white);
            text-align: center;
            padding: 8px 20px;
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
            font-size: 14px;
            z-index: 1001;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            animation: glow 2s ease-in-out infinite alternate;
        }

        .promo-banner-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .promo-icon {
            font-size: 16px;
            animation: bounce 1.5s ease-in-out infinite;
        }

        .promo-text {
            letter-spacing: 0.5px;
        }

        .promo-close {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--white);
            font-size: 18px;
            cursor: pointer;
            opacity: 0.8;
            transition: opacity 0.3s ease;
        }

        .promo-close:hover {
            opacity: 1;
        }

        @keyframes glow {
            from {
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            }
            to {
                box-shadow: 0 2px 20px rgba(207, 114, 58, 0.3);
            }
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-3px);
            }
            60% {
                transform: translateY(-2px);
            }
        }

        /* Navigation */
        .navbar {
            position: fixed;
            top: 38px;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            z-index: 1000;
            transition: var(--transition);
            box-shadow: var(--shadow-soft);
        }

        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            width: 100%;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            text-decoration: none;
            color: var(--text-dark);
        }

        .logo-text {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--curry);
            font-family: 'BaticaSans', sans-serif;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text-gray);
            font-weight: 500;
            font-family: 'BaticaSans', sans-serif;
            transition: var(--transition);
        }

        .nav-links a:hover {
            color: var(--curry);
        }

        .nav-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            font-family: 'BaticaSans', sans-serif;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--curry), var(--brown));
            color: var(--white);
            box-shadow: var(--shadow-soft);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .btn-secondary {
            background: transparent;
            color: var(--curry);
            border: 2px solid var(--curry);
        }

        .btn-secondary:hover {
            background: var(--curry);
            color: var(--white);
        }

        /* Main Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 120px 2rem 4rem;
        }

        /* Progress Bar */
        .progress-container {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 2rem;
            margin-bottom: 3rem;
            box-shadow: var(--shadow-soft);
        }

        .progress-bar {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .progress-step {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.8rem 1.5rem;
            border-radius: var(--radius-xl);
            font-weight: 600;
            font-size: 0.95rem;
            font-family: 'BaticaSans', sans-serif;
            background: var(--cream);
            color: var(--text-gray);
            border: 2px solid var(--cream);
            transition: var(--transition);
            white-space: nowrap;
        }

        .progress-step.active {
            background: var(--curry);
            color: var(--white);
            border-color: var(--curry);
            box-shadow: 0 4px 12px rgba(207, 114, 58, 0.3);
        }

        .progress-step.completed {
            background: var(--success);
            color: var(--white);
            border-color: var(--success);
        }

        .progress-arrow {
            color: var(--sage);
            font-size: 1.2rem;
            font-weight: 600;
        }

        /* Selection Counter */
        .selection-counter {
            background: var(--white);
            border: 2px solid var(--border-light);
            border-radius: var(--radius-lg);
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 120px;
            z-index: 50;
            box-shadow: var(--shadow-soft);
        }

        .counter-text {
            font-size: 1.1rem;
            font-weight: 600;
            font-family: 'BaticaSans', sans-serif;
        }

        .counter-number {
            color: var(--curry);
            font-size: 1.3rem;
            font-weight: 800;
        }

        .counter-complete {
            color: var(--success);
        }

        /* Filters */
        .filters-section {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
            border: 1px solid var(--border-light);
            box-shadow: var(--shadow-soft);
        }

        .filters-header {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--text-dark);
            font-family: 'BaticaSans', sans-serif;
        }

        .filters-row {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-box {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 0.9rem 1rem 0.9rem 2.8rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-xl);
            font-size: 1rem;
            font-family: 'BaticaSans', sans-serif;
            transition: var(--transition);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--curry);
            box-shadow: 0 0 15px rgba(207, 114, 58, 0.2);
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-gray);
            font-size: 1rem;
        }

        .filter-categories {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .category-filter {
            padding: 0.6rem 1.2rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-xl);
            background: var(--white);
            color: var(--text-gray);
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.9rem;
            font-weight: 600;
            white-space: nowrap;
            font-family: 'BaticaSans', sans-serif;
        }

        .category-filter:hover,
        .category-filter.active {
            border-color: var(--curry);
            background: var(--curry);
            color: var(--white);
            transform: translateY(-1px);
        }

        /* Menu Grid - 5 columns on desktop */
        .meals-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .meal-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-light);
            overflow: hidden;
            transition: var(--transition);
            position: relative;
            cursor: pointer;
        }

        .meal-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-medium);
            border-color: var(--sage);
        }

        .meal-card.selected {
            border-color: var(--curry);
            box-shadow: 0 8px 32px rgba(207, 114, 58, 0.25);
            transform: translateY(-2px);
        }

        .meal-image {
            position: relative;
            height: 160px;
            overflow: hidden;
            background: var(--cream);
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: var(--text-gray);
        }

        .meal-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: var(--transition);
        }

        .meal-card:hover .meal-image img {
            transform: scale(1.05);
        }

        .meal-badge {
            position: absolute;
            top: 0.8rem;
            left: 0.8rem;
            background: rgba(255, 255, 255, 0.95);
            color: var(--curry);
            padding: 0.3rem 0.6rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            backdrop-filter: blur(10px);
            font-family: 'BaticaSans', sans-serif;
        }

        .selection-badge {
            position: absolute;
            top: 0.8rem;
            right: 0.8rem;
            background: var(--curry);
            color: var(--white);
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            opacity: 0;
            transform: scale(0.5);
            transition: var(--transition);
        }

        .meal-card.selected .selection-badge {
            opacity: 1;
            transform: scale(1);
        }

        .meal-content {
            padding: 1.2rem;
        }

        .meal-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.6rem;
            line-height: 1.3;
            font-family: 'BaticaSans', sans-serif;
        }

        .meal-description {
            color: var(--text-gray);
            font-size: 0.8rem;
            line-height: 1.4;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            font-family: 'BaticaSans', sans-serif;
        }

        .meal-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 0.8rem;
            border-top: 1px solid var(--border-light);
        }

        .meal-price {
            font-size: 1rem;
            font-weight: 800;
            color: var(--curry);
            font-family: 'BaticaSans', sans-serif;
        }

        .add-meal-btn {
            background: var(--curry);
            color: var(--white);
            border: none;
            padding: 0.5rem 0.8rem;
            border-radius: var(--radius-xl);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .add-meal-btn:hover {
            background: var(--brown);
            transform: translateY(-2px);
        }

        .add-meal-btn:disabled {
            background: var(--text-gray);
            cursor: not-allowed;
            transform: none;
        }

        .add-meal-btn.selected {
            background: var(--success);
        }

        /* Continue Button */
        .continue-section {
            position: sticky;
            bottom: 0;
            background: var(--white);
            border-top: 2px solid var(--border-light);
            padding: 2rem 0;
            margin-top: 3rem;
            box-shadow: 0 -4px 12px rgba(0,0,0,0.05);
        }

        .continue-btn {
            width: 100%;
            background: linear-gradient(135deg, var(--curry), var(--brown));
            color: var(--white);
            border: none;
            padding: 1.2rem 2rem;
            border-radius: var(--radius-xl);
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            font-family: 'BaticaSans', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.8rem;
            box-shadow: 0 4px 12px rgba(207, 114, 58, 0.3);
        }

        .continue-btn:hover:not(:disabled) {
            background: linear-gradient(135deg, var(--brown), var(--sage));
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(207, 114, 58, 0.4);
        }

        .continue-btn:disabled {
            background: var(--text-gray);
            cursor: not-allowed;
            transform: none;
            opacity: 0.6;
            box-shadow: none;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-gray);
            grid-column: 1 / -1;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        .empty-state h3 {
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
            font-family: 'BaticaSans', sans-serif;
        }

        /* Loading States */
        .loading {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Toast Notifications */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--white);
            border-left: 4px solid;
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow-medium);
            padding: 1rem 1.5rem;
            min-width: 300px;
            transform: translateX(100%);
            transition: transform 0.3s ease;
            z-index: 2000;
            font-family: 'BaticaSans', sans-serif;
        }

        .toast.show {
            transform: translateX(0);
        }

        .toast.success {
            border-left-color: var(--success);
        }

        .toast.error {
            border-left-color: var(--danger);
        }

        .toast.warning {
            border-left-color: var(--warning);
        }

        /* Animation for meal selection */
        @keyframes selectPulse {
            0% { transform: scale(1) translateY(-2px); }
            50% { transform: scale(1.02) translateY(-4px); }
            100% { transform: scale(1) translateY(-2px); }
        }

        .meal-card.just-selected {
            animation: selectPulse 0.4s ease-out;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .meals-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        @media (max-width: 968px) {
            .meals-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {
            .promo-banner {
                font-size: 12px;
                padding: 6px 15px;
            }
            
            .navbar {
                top: 32px;
            }
            
            .container {
                padding: 100px 1rem 3rem;
            }
            
            .promo-banner-content {
                flex-direction: column;
                gap: 5px;
            }
            
            .promo-close {
                right: 10px;
            }

            .filters-row {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box {
                min-width: 100%;
            }

            .meals-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1.5rem;
            }

            .progress-bar {
                gap: 0.5rem;
            }

            .progress-step {
                font-size: 0.8rem;
                padding: 0.6rem 1rem;
            }

            .progress-arrow {
                font-size: 1rem;
            }

            .selection-counter {
                position: static;
                flex-direction: column;
                gap: 0.5rem;
                text-align: center;
            }

            .nav-links {
                display: none;
            }
        }

        @media (max-width: 480px) {
            .nav-actions {
                gap: 0.5rem;
            }

            .logo-text {
                font-size: 1.5rem;
            }

            .progress-step {
                font-size: 0.7rem;
                padding: 0.5rem 0.8rem;
            }

            .meals-grid {
                grid-template-columns: 1fr;
            }

            .meal-content {
                padding: 1rem;
            }

            .meal-footer {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }

            .add-meal-btn {
                justify-content: center;
                padding: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <!-- Promotional Banner -->
    <div class="promo-banner" id="promoBanner">
        <div class="promo-banner-content">
            <span class="promo-icon">🍪</span>
            <span class="promo-text">50% OFF First Week + Free Cookies for Life</span>
            <span class="promo-icon">🎉</span>
        </div>
        <button class="promo-close" onclick="closePromoBanner()" title="Close">×</button>
    </div>

    <!-- Navigation -->
    <nav class="navbar">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 2rem; max-width: 1200px; margin: 0 auto; width: 100%;">
            <a href="home2.php" class="logo">
                <img src="./assets/image/LOGO_BG.png" alt="Somdul Table" style="height: 50px; width: auto;">
            </a>
            <a href="home2.php" class="logo">
                <span class="logo-text">Somdul Table</span>
            </a>
            
            <ul class="nav-links">
                <li><a href="./menus.php">Menu</a></li>
                <li><a href="home2.php#how-it-works">How It Works</a></li>
                <li><a href="#about">About</a></li>
                <li><a href="#contact">Contact</a></li>
            </ul>
            
            <div class="nav-actions">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
                    <a href="logout.php" class="btn btn-primary">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-secondary">Sign In</a>
                    <a href="register.php" class="btn btn-primary">Get Started</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Progress Bar -->
        <div class="progress-container">
            <div class="progress-bar">
                <div class="progress-step completed">
                    <i class="fas fa-check-circle"></i>
                    <span>Choose Package</span>
                </div>
                <span class="progress-arrow">→</span>
                <div class="progress-step active">
                    <i class="fas fa-utensils"></i>
                    <span>Select Menu</span>
                </div>
                <span class="progress-arrow">→</span>
                <div class="progress-step">
                    <i class="fas fa-credit-card"></i>
                    <span>Payment</span>
                </div>
                <span class="progress-arrow">→</span>
                <div class="progress-step">
                    <i class="fas fa-check-double"></i>
                    <span>Complete</span>
                </div>
            </div>
        </div>

        <!-- Selection Counter -->
        <div class="selection-counter">
            <div class="counter-text">
                Selected: <span class="counter-number" id="selectedCount">0</span>/<span class="counter-number"><?php echo $plan['meals_per_week']; ?></span> meals
            </div>
            <div id="selectionStatus" class="counter-text" style="color: var(--text-gray);">
                Please select the required number of meals
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-section">
            <div class="filters-header">
                <i class="fas fa-filter"></i> Search and Filter Menu
            </div>
            <div class="filters-row">
                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="searchInput" class="search-input" placeholder="Search for meals you want...">
                </div>
                
                <div class="filter-categories">
                    <button class="category-filter active" data-category="all">
                        All
                    </button>
                    <?php foreach ($categories as $category): ?>
                        <button class="category-filter" data-category="<?php echo $category['id']; ?>">
                            <?php echo htmlspecialchars(getCategoryName($category)); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Meals Grid -->
        <div class="meals-grid" id="mealsGrid">
            <?php if (!empty($menus)): ?>
                <?php foreach ($menus as $menu): ?>
                    <div class="meal-card" 
                         data-menu-id="<?php echo $menu['id']; ?>"
                         data-category="<?php echo $menu['category_id'] ?? ''; ?>"
                         data-name="<?php echo strtolower(getMenuName($menu) . ' ' . ($menu['name_thai'] ?? '')); ?>"
                         data-price="<?php echo $menu['base_price']; ?>"
                         onclick="toggleMealSelection('<?php echo $menu['id']; ?>')">
                        
                        <div class="meal-image">
                            <?php if (!empty($menu['main_image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($menu['main_image_url']); ?>" 
                                     alt="<?php echo htmlspecialchars(getMenuName($menu)); ?>" 
                                     loading="lazy">
                            <?php else: ?>
                                <div style="text-align: center;">
                                    <i class="fas fa-utensils" style="font-size: 1.5rem; margin-bottom: 0.5rem; opacity: 0.5;"></i>
                                    <br><span style="font-size: 0.8rem;"><?php echo htmlspecialchars(getMenuName($menu)); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($menu['category_name']) || !empty($menu['category_name_thai'])): ?>
                                <div class="meal-badge">
                                    <?php echo htmlspecialchars($menu['category_name'] ?: $menu['category_name_thai']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="selection-badge">
                                <i class="fas fa-check"></i>
                            </div>
                        </div>
                        
                        <div class="meal-content">
                            <h3 class="meal-title">
                                <?php echo htmlspecialchars(getMenuName($menu)); ?>
                            </h3>
                            
                            <?php if (!empty($menu['description'])): ?>
                                <p class="meal-description">
                                    <?php echo htmlspecialchars($menu['description']); ?>
                                </p>
                            <?php endif; ?>
                            
                            <div class="meal-footer">
                                <div class="meal-price">
                                    $<?php echo number_format($menu['base_price']/100, 2); ?>
                                </div>
                                <button class="add-meal-btn" onclick="event.stopPropagation(); toggleMealSelection('<?php echo $menu['id']; ?>')">
                                    <i class="fas fa-plus"></i>
                                    <span>Add</span>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-utensils"></i>
                    <h3>No Meals Available</h3>
                    <p>Sorry, there are currently no meals available to select</p>
                    <a href="subscribe.php" style="margin-top: 1rem; display: inline-block; padding: 0.8rem 1.5rem; background: var(--curry); color: var(--white); text-decoration: none; border-radius: var(--radius-md);">Back to Packages</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Continue Section -->
        <div class="continue-section">
            <button class="continue-btn" id="continueBtn" onclick="proceedToCheckout()" disabled>
                <i class="fas fa-shopping-cart"></i>
                Continue - Proceed to Payment
            </button>
        </div>
    </div>

    <!-- Font Awesome for icons -->
    <script src="https://kit.fontawesome.com/your-fontawesome-kit.js" crossorigin="anonymous"></script>

    <script>
        function closePromoBanner() {
            const promoBanner = document.getElementById('promoBanner');
            const navbar = document.querySelector('.navbar');
            const container = document.querySelector('.container');
            
            promoBanner.style.transform = 'translateY(-100%)';
            promoBanner.style.opacity = '0';
            
            setTimeout(() => {
                promoBanner.style.display = 'none';
                navbar.style.top = '0';
                container.style.paddingTop = '100px';
            }, 300);
        }

        // Navbar background on scroll
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 100) {
                navbar.style.background = 'rgba(255, 255, 255, 0.98)';
            } else {
                navbar.style.background = 'rgba(255, 255, 255, 0.95)';
            }
        });

        // Global variables
        const maxMeals = <?php echo $plan['meals_per_week']; ?>;
        let selectedMeals = [];
        let allMeals = [];

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            console.log('✅ Updated meal selection page loaded');
            
            // Store all meal data
            document.querySelectorAll('.meal-card').forEach(card => {
                allMeals.push({
                    id: card.dataset.menuId,
                    category: card.dataset.category,
                    name: card.dataset.name,
                    price: parseFloat(card.dataset.price),
                    element: card
                });
            });

            // Set up event listeners
            setupEventListeners();
            updateUI();
            loadSelectionState();
            
            // Highlight menu if specified
            <?php if ($highlight_menu_id): ?>
            const highlightCard = document.querySelector('[data-menu-id="<?php echo $highlight_menu_id; ?>"]');
            if (highlightCard) {
                highlightCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                highlightCard.style.transform = 'scale(1.02)';
                setTimeout(() => {
                    highlightCard.style.transform = '';
                }, 1000);
            }
            <?php endif; ?>
        });

        function setupEventListeners() {
            // Search functionality
            const searchInput = document.getElementById('searchInput');
            searchInput.addEventListener('input', debounce(filterMeals, 300));

            // Category filters
            document.querySelectorAll('.category-filter').forEach(filter => {
                filter.addEventListener('click', function() {
                    // Update active filter
                    document.querySelectorAll('.category-filter').forEach(f => f.classList.remove('active'));
                    this.classList.add('active');
                    filterMeals();
                });
            });
        }

        function toggleMealSelection(mealId) {
            const mealCard = document.querySelector(`[data-menu-id="${mealId}"]`);
            const button = mealCard.querySelector('.add-meal-btn');
            
            if (selectedMeals.includes(mealId)) {
                // Remove meal
                selectedMeals = selectedMeals.filter(id => id !== mealId);
                mealCard.classList.remove('selected');
                button.innerHTML = '<i class="fas fa-plus"></i><span>Add</span>';
                button.classList.remove('selected');
                
                // Enable all disabled buttons if we're under the limit
                if (selectedMeals.length < maxMeals) {
                    document.querySelectorAll('.add-meal-btn:disabled').forEach(btn => {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-plus"></i><span>Add</span>';
                    });
                }
            } else {
                // Check if we can add more meals
                if (selectedMeals.length >= maxMeals) {
                    showToast('warning', `You have already selected the maximum number of meals (${maxMeals} meals)`);
                    return;
                }
                
                // Add meal
                selectedMeals.push(mealId);
                mealCard.classList.add('selected', 'just-selected');
                button.innerHTML = '<i class="fas fa-check"></i><span>Selected</span>';
                button.classList.add('selected');
                
                // Remove animation class after animation completes
                setTimeout(() => {
                    mealCard.classList.remove('just-selected');
                }, 400);
                
                // Disable all other buttons if we've reached the limit
                if (selectedMeals.length >= maxMeals) {
                    document.querySelectorAll('.meal-card:not(.selected) .add-meal-btn').forEach(btn => {
                        btn.disabled = true;
                        btn.innerHTML = '<i class="fas fa-lock"></i><span>Limit Reached</span>';
                    });
                }
            }
            
            updateUI();
            saveSelectionState();
        }

        function updateUI() {
            const selectedCount = selectedMeals.length;
            const countElement = document.getElementById('selectedCount');
            const statusElement = document.getElementById('selectionStatus');
            const continueBtn = document.getElementById('continueBtn');
            
            // Update counter
            countElement.textContent = selectedCount;
            countElement.className = selectedCount === maxMeals ? 'counter-number counter-complete' : 'counter-number';
            
            // Update status message
            if (selectedCount === 0) {
                statusElement.textContent = 'Please select the required number of meals';
                statusElement.style.color = 'var(--text-gray)';
            } else if (selectedCount < maxMeals) {
                const remaining = maxMeals - selectedCount;
                statusElement.textContent = `Select ${remaining} more meal${remaining === 1 ? '' : 's'}`;
                statusElement.style.color = 'var(--warning)';
            } else {
                statusElement.textContent = '✅ Selection complete! Ready to proceed';
                statusElement.style.color = 'var(--success)';
            }
            
            // Update continue button
            continueBtn.disabled = selectedCount !== maxMeals;
            
            // Update page title
            document.title = `Select Meals (${selectedCount}/${maxMeals}) - ${<?php echo json_encode(getPlanName($plan)); ?>} | Somdul Table`;
        }

        function filterMeals() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const activeCategory = document.querySelector('.category-filter.active').dataset.category;
            
            let visibleCount = 0;
            
            allMeals.forEach(meal => {
                let visible = true;
                
                // Category filter
                if (activeCategory !== 'all' && meal.category !== activeCategory) {
                    visible = false;
                }
                
                // Search filter
                if (searchTerm && !meal.name.includes(searchTerm)) {
                    visible = false;
                }
                
                // Apply visibility
                meal.element.style.display = visible ? 'block' : 'none';
                if (visible) visibleCount++;
            });
            
            // Show empty state if no results
            const existingEmptyState = document.querySelector('.temp-empty');
            if (existingEmptyState) existingEmptyState.remove();
            
            if (visibleCount === 0 && searchTerm) {
                const mealsGrid = document.getElementById('mealsGrid');
                const emptyDiv = document.createElement('div');
                emptyDiv.className = 'empty-state temp-empty';
                emptyDiv.innerHTML = `
                    <i class="fas fa-search"></i>
                    <h3>No meals found</h3>
                    <p>Try using different search terms or select a different category</p>
                `;
                mealsGrid.appendChild(emptyDiv);
            }
        }

        function proceedToCheckout() {
            if (selectedMeals.length !== maxMeals) {
                showToast('error', 'Please select the exact number of meals required');
                return;
            }
            
            console.log('🚀 Proceeding to checkout with meals:', selectedMeals);
            
            // Show loading state
            const continueBtn = document.getElementById('continueBtn');
            const originalText = continueBtn.innerHTML;
            continueBtn.innerHTML = '<div class="loading"></div> Processing...';
            continueBtn.disabled = true;
            
            // Send selected meals to server
            const formData = new FormData();
            formData.append('action', 'proceed_to_checkout');
            formData.append('selected_meals', JSON.stringify(selectedMeals));
            
            fetch('meal-selection.php?plan=<?php echo urlencode($plan_id); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('✅ Server response:', data);
                if (data.success) {
                    localStorage.removeItem('mealSelection');
                    showToast('success', 'Meal selection saved! Redirecting to checkout...');
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1000);
                } else {
                    showToast('error', data.message);
                    continueBtn.innerHTML = originalText;
                    continueBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('❌ Error:', error);
                showToast('error', 'An error occurred. Please try again.');
                continueBtn.innerHTML = originalText;
                continueBtn.disabled = false;
            });
        }

        // Utility functions
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        function showToast(type, message) {
            const toast = document.createElement('div');
            toast.className = `toast ${type} show`;
            toast.innerHTML = `
                <div style="display: flex; align-items: center; gap: 0.8rem;">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'exclamation-circle'}" style="font-size: 1.2rem;"></i>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => {
                    if (document.body.contains(toast)) {
                        document.body.removeChild(toast);
                    }
                }, 300);
            }, 4000);
        }

        // Save/Load selection state
        function saveSelectionState() {
            localStorage.setItem('mealSelection', JSON.stringify({
                planId: '<?php echo $plan_id; ?>',
                selectedMeals: selectedMeals,
                timestamp: Date.now()
            }));
        }

        function loadSelectionState() {
            const saved = localStorage.getItem('mealSelection');
            if (saved) {
                const data = JSON.parse(saved);
                // Only restore if it's the same plan and less than 1 hour old
                if (data.planId === '<?php echo $plan_id; ?>' && Date.now() - data.timestamp < 3600000) {
                    data.selectedMeals.forEach(mealId => {
                        if (document.querySelector(`[data-menu-id="${mealId}"]`)) {
                            toggleMealSelection(mealId);
                        }
                    });
                }
            }
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && selectedMeals.length === maxMeals) {
                proceedToCheckout();
            }
            
            if (e.key === 'Escape') {
                document.getElementById('searchInput').value = '';
                filterMeals();
            }
        });
    </script>
</body>
</html>