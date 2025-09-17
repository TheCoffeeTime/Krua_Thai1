<?php
/**
 * Somdul Table - Uncle Kole's Sauce Story Blog
 * File: blogproduct.php
 * Description: Storytelling blog page featuring Uncle Kole's 4 signature sauces
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

// Include the header (contains navbar, promo banner, fonts, and base styles)
include 'header.php';

// Include cart functions if available
if (file_exists('includes/cart_functions.php')) {
    require_once 'includes/cart_functions.php';
}

// Database connection with fallback
try {
    $database = new Database();
    $pdo = $database->getConnection();
} catch (Exception $e) {
    // Fallback connection for MAMP/XAMPP
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=somdul_table;charset=utf8mb4", "root", "root");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        // Second fallback
        $pdo = new PDO("mysql:host=localhost:8889;dbname=somdul_table;charset=utf8mb4", "root", "root");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
}

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);

// Calculate cart count
$cart_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_count += intval($item['quantity'] ?? 1);
    }
}

// Uncle Kole's 4 Signature Sauces - Using correct database IDs
$signature_sauces = [
    [
        'id' => 'yellow-curry-sauce',
        'name' => 'Yellow Curry Sauce',
        'size' => '6.7 oz (200 mL)',
        'price' => 14.99,
        'image_url' => './assets/image/yellow-curry-sauce.jpg',
        'description' => 'Dive into our Thai stir-fried yellow curry sauce, a symphony of golden flavors. Infused with traditional spices, it radiates the warmth and richness unique to Thai curries. Meticulously crafted, this sauce encapsulates Thailand\'s culinary heritage. Enhance your dishes with its vibrant charm and embark on a delightful taste journey. Truly, a Thai treasure.',
        'ingredients' => ['Turmeric', 'Lemongrass', 'Galangal', 'Coconut milk powder', 'Shallots', 'Garlic', 'Chili', 'Shrimp paste'],
        'recipe_title' => 'Uncle Kole\'s Yellow Curry Chicken',
        'recipe_description' => 'A family recipe passed down for generations. Tender chicken simmered in aromatic yellow curry with potatoes and onions.',
        'cooking_time' => '25 minutes',
        'servings' => '4 people',
        'uses' => ['Stir-fry with chicken and vegetables', 'Curry noodles', 'Seafood curry', 'Vegetable curry'],
        'story' => 'Uncle Kole\'s first creation, inspired by the golden sunsets of his hometown in Chiang Mai. This sauce holds the secret blend of spices his grandmother taught him.',
        'category' => 'sauce'
    ],
    [
        'id' => 'instant-tom-yum-paste',
        'name' => 'Instant Tom Yum Paste',
        'size' => '8 oz (227 mL)',
        'price' => 16.99,
        'image_url' => './assets/image/instant-tom-yum-paste.jpg',
        'description' => 'A convenient and popular Thai seasoning used broth, and it imparts the soup with the signature spicy, sour, and savory flavors of Thai cuisine. It\'s a quick and easy way to enjoy the complex flavors of Tom Yum to make Tom Yum soup, a flavorful and spicy Thai soup.',
        'ingredients' => ['Lemongrass', 'Kaffir lime leaves', 'Galangal', 'Bird\'s eye chili', 'Fish sauce', 'Lime juice', 'Thai chili paste'],
        'recipe_title' => 'Classic Tom Yum Goong',
        'recipe_description' => 'The legendary hot and sour shrimp soup that made Uncle Kole famous in Bangkok\'s street food scene.',
        'cooking_time' => '15 minutes',
        'servings' => '2-3 people',
        'uses' => ['Tom Yum soup', 'Stir-fry seasoning', 'Noodle soup base', 'Marinade for grilled seafood'],
        'story' => 'Born from Uncle Kole\'s quest to capture the perfect balance of hot, sour, salty, and sweet that defines authentic Tom Yum.',
        'category' => 'sauce'
    ],
    [
        'id' => 'sweet-chili-sauce',
        'name' => 'Sweet Chili Sauce',
        'size' => '10.1 oz (300 mL)',
        'price' => 12.99,
        'image_url' => './assets/image/sweet-chili-sauce.jpg',
        'description' => 'Discover the fusion of fiery chilies and luscious sweetness in our Thai sweet chili sauce. Crafted for the perfect balance, it transforms dishes with a vibrant kick. Ideal for dipping, drizzling, or glazing. Experience the authentic essence of Thailand\'s flavors. A culinary masterpiece in every drop.',
        'ingredients' => ['Red chilies', 'Sugar', 'Vinegar', 'Garlic', 'Water', 'Salt', 'Xanthan gum'],
        'recipe_title' => 'Thai Chicken Wings with Sweet Chili Glaze',
        'recipe_description' => 'Crispy wings glazed with Uncle Kole\'s signature sweet chili sauce - a crowd favorite at every family gathering.',
        'cooking_time' => '30 minutes',
        'servings' => '4-6 people',
        'uses' => ['Spring roll dipping sauce', 'Chicken wing glaze', 'Salad dressing base', 'Pizza drizzle'],
        'story' => 'Uncle Kole\'s answer to the Western palate - a gentle introduction to Thai flavors that became unexpectedly popular with locals too.',
        'category' => 'sauce'
    ],
    [
        'id' => 'spicy-garlic-basil-sauce',
        'name' => 'Spicy Garlic Basil Sauce',
        'size' => '6.76 oz (200 mL)',
        'price' => 15.99,
        'image_url' => './assets/image/spicy-garlic-basil-sauce.jpg',
        'description' => 'Embrace our Thai stir-fried holy basil sauce, inspired by "Pad Kra Pow" â€" the iconic Thai street food dish. Infused with the unique aroma of Thai holy basil leaves, its authenticity is craved by locals. Meticulously crafted, this sauce captures the true essence of Thailand\'s culinary legacy. Dive in and experience an unmatched taste sensation.',
        'ingredients' => ['Holy basil', 'Thai chilies', 'Garlic', 'Fish sauce', 'Oyster sauce', 'Sugar', 'Soy sauce'],
        'recipe_title' => 'Pad Kra Pao Gai (Holy Basil Chicken)',
        'recipe_description' => 'The dish that started it all - Uncle Kole\'s street cart signature that drew lines around the block in Bangkok.',
        'cooking_time' => '12 minutes',
        'servings' => '2 people',
        'uses' => ['Stir-fry with meat or seafood', 'Fried rice seasoning', 'Noodle stir-fry', 'Vegetable stir-fry'],
        'story' => 'The crown jewel of Uncle Kole\'s collection, featuring real Thai holy basil that he imports directly from his family farm.',
        'category' => 'sauce'
    ]
];

// Helper function to format price
function formatPrice($price) {
    return '$' . number_format($price, 2);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uncle Kole's Sauce Story - Authentic Thai Heritage | Somdul Table</title>
    <meta name="description" content="Discover the story behind Uncle Kole's signature Thai sauces. Four decades of culinary passion, from Thailand to your kitchen.">
    
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

        /* Hero Section */
        .story-hero {
            background: linear-gradient(135deg, var(--brown) 0%, var(--curry) 100%);
            color: var(--white);
            padding: 6rem 2rem 4rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .story-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" patternUnits="userSpaceOnUse" width="100" height="100"><circle cx="50" cy="50" r="1" fill="%23ffffff" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }
        
        .story-hero-content {
            position: relative;
            z-index: 2;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .story-hero h1 {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            color: var(--white);
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .story-hero .subtitle {
            font-size: 1.3rem;
            opacity: 0.95;
            font-style: italic;
            margin-bottom: 2rem;
        }
        
        .story-hero .hero-quote {
            font-size: 1.1rem;
            line-height: 1.7;
            opacity: 0.9;
            background: rgba(255,255,255,0.1);
            padding: 2rem;
            border-radius: var(--radius-lg);
            border-left: 4px solid var(--white);
        }

        /* Floating Cart */
        .floating-cart {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
            animation: slideInUp 0.3s ease-out, float 3s ease-in-out infinite;
        }
        
        .floating-cart.hidden {
            display: none;
        }
        
        .floating-cart-link {
            text-decoration: none;
            display: block;
        }
        
        .floating-cart-icon {
            position: relative;
            width: 60px;
            height: 60px;
            background: var(--brown);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--white);
            box-shadow: var(--shadow-medium);
            transition: var(--transition);
            border: 3px solid var(--white);
        }
        
        .floating-cart-icon:hover {
            background: var(--curry);
            transform: scale(1.1) translateY(-5px);
            box-shadow: 0 12px 30px rgba(189, 147, 121, 0.4);
            animation: none;
        }
        
        .floating-cart-counter {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--curry);
            color: var(--white);
            border-radius: 50%;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 700;
            border: 2px solid var(--white);
            min-width: 28px;
            animation: pulse 0.6s ease-out;
        }
        
        .floating-cart-counter.hidden {
            display: none;
        }

        /* Main Story Container */
        .story-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 4rem 2rem;
        }

        /* Story Introduction */
        .story-intro {
            text-align: center;
            margin-bottom: 5rem;
            padding: 3rem;
            background: var(--cream);
            border-radius: var(--radius-lg);
            position: relative;
        }

        .story-intro h2 {
            font-size: 2.5rem;
            color: var(--brown);
            margin-bottom: 2rem;
            font-weight: 800;
        }

        .story-intro .story-text {
            font-size: 1.2rem;
            line-height: 1.8;
            color: var(--text-dark);
            max-width: 800px;
            margin: 0 auto 2rem;
        }

        .story-intro .legacy-text {
            font-size: 1.1rem;
            line-height: 1.7;
            color: var(--sage);
            font-style: italic;
            max-width: 700px;
            margin: 0 auto;
            border-top: 2px solid var(--brown);
            padding-top: 2rem;
        }

        /* Sauce Section Styles */
        .sauce-section {
            margin-bottom: 6rem;
            padding: 3rem 0;
            position: relative;
        }

        .sauce-section:not(:last-child)::after {
            content: '';
            position: absolute;
            bottom: -3rem;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--sage), transparent);
        }

        /* Alternating Layouts */
        .sauce-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
            margin-bottom: 3rem;
        }

        .sauce-content.reverse {
            direction: rtl;
        }

        .sauce-content.reverse > * {
            direction: ltr;
        }

        .sauce-image {
            position: relative;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-medium);
            transition: var(--transition);
        }

        .sauce-image:hover {
            transform: scale(1.02);
            box-shadow: 0 12px 40px rgba(189, 147, 121, 0.3);
        }

        .sauce-image img {
            width: 100%;
            height: 500px;
            object-fit: cover;
        }

        .sauce-image .placeholder {
            width: 100%;
            height: 500px;
            background: var(--cream);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            color: var(--brown);
        }

        .sauce-info {
            padding: 2rem;
        }

        .sauce-category {
            font-size: 0.9rem;
            color: var(--curry);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.5rem;
        }

        .sauce-name {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--brown);
            margin-bottom: 1rem;
            line-height: 1.2;
        }

        .sauce-size {
            font-size: 1rem;
            color: var(--text-gray);
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .sauce-description {
            font-size: 1.1rem;
            line-height: 1.7;
            color: var(--text-dark);
            margin-bottom: 2rem;
        }

        .sauce-story {
            background: var(--cream);
            padding: 1.5rem;
            border-radius: var(--radius-md);
            margin-bottom: 2rem;
            border-left: 4px solid var(--sage);
        }

        .sauce-story .story-label {
            font-size: 0.9rem;
            color: var(--sage);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .sauce-story .story-content {
            font-style: italic;
            color: var(--text-dark);
            line-height: 1.6;
        }

        .sauce-price-cart {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
        }

        .sauce-price {
            font-size: 2rem;
            font-weight: 800;
            color: var(--brown);
        }

        .btn-add-cart {
            background: var(--brown);
            color: var(--white);
            border: none;
            padding: 1rem 2rem;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-family: 'BaticaSans', sans-serif;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1rem;
            min-width: 150px;
            justify-content: center;
        }
        
        .btn-add-cart:hover {
            background: #a8855f;
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }
        
        .btn-add-cart:disabled {
            background: var(--text-gray);
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-add-cart.loading {
            background: var(--sage);
            pointer-events: none;
        }

        /* Recipe Card */
        .recipe-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--cream);
            margin-top: 2rem;
        }

        .recipe-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }

        .recipe-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--brown);
            margin-bottom: 0.5rem;
        }

        .recipe-meta {
            display: flex;
            gap: 2rem;
            font-size: 0.9rem;
            color: var(--text-gray);
        }

        .recipe-meta-item {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .recipe-description {
            font-style: italic;
            color: var(--text-gray);
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .ingredients-uses {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .ingredients h4,
        .uses h4 {
            font-size: 1.1rem;
            color: var(--brown);
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .ingredients-text,
        .uses-text {
            color: var(--text-dark);
            line-height: 1.6;
            font-style: italic;
        }

        /* Success notification */
        .toast-notification {
            position: fixed;
            top: 140px;
            right: 2rem;
            background: var(--sage);
            color: var(--white);
            padding: 1rem 1.5rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-medium);
            z-index: 1000;
            max-width: 350px;
            display: none; /* Hidden by default */
        }

        .toast-notification.show {
            display: block;
            animation: slideInFromRight 0.3s ease-out;
        }

        @keyframes slideInFromRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .toast-content {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .toast-icon {
            font-size: 1.2rem;
        }
        
        .toast-text {
            font-weight: 600;
        }

        /* Call to Action */
        .story-cta {
            text-align: center;
            margin-top: 5rem;
            padding: 4rem 2rem;
            background: linear-gradient(135deg, var(--sage) 0%, var(--brown) 100%);
            color: var(--white);
            border-radius: var(--radius-lg);
        }

        .story-cta h3 {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: var(--white);
        }

        .story-cta p {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            opacity: 0.9;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .cta-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-cta {
            background: var(--white);
            color: var(--brown);
            padding: 1rem 2rem;
            border-radius: var(--radius-sm);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            border: 2px solid var(--white);
        }

        .btn-cta:hover {
            background: transparent;
            color: var(--white);
            transform: translateY(-2px);
        }

        /* Animations */
        @keyframes slideInUp {
            from {
                transform: translateY(100px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-10px);
            }
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .story-hero {
                padding: 4rem 1rem 2rem;
            }
            
            .story-hero h1 {
                font-size: 2.5rem;
            }
            
            .story-container {
                padding: 2rem 1rem;
            }
            
            .sauce-content {
                grid-template-columns: 1fr;
                gap: 2rem;
                text-align: center;
            }
            
            .sauce-content.reverse {
                direction: ltr;
            }
            
            .sauce-image {
                order: 1;
            }
            
            .sauce-image img,
            .sauce-image .placeholder {
                height: 350px;
            }
            
            .sauce-info {
                order: 2;
                padding: 1rem 0;
            }
            
            .recipe-card {
                padding: 1.5rem;
            }
            
            .ingredients-uses {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            
            .recipe-header {
                flex-direction: column;
                gap: 1rem;
            }
            
            .recipe-meta {
                flex-wrap: wrap;
                gap: 1rem;
            }
            
            .sauce-price-cart {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
            
            .floating-cart {
                bottom: 20px;
                right: 20px;
            }
            
            .toast-notification {
                right: 1rem;
                left: 1rem;
                max-width: none;
            }
            
            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }
        }

        /* Small Mobile Devices (480px and below) */
        @media (max-width: 480px) {
            .story-hero {
                padding: 3rem 0.75rem 1.5rem;
            }
            
            .story-hero h1 {
                font-size: 2rem;
                line-height: 1.1;
            }
            
            .story-hero .subtitle {
                font-size: 1.1rem;
            }
            
            .story-hero .hero-quote {
                font-size: 1rem;
                padding: 1.5rem;
            }
            
            .story-container {
                padding: 1.5rem 0.75rem;
            }
            
            .story-intro {
                padding: 2rem 1.5rem;
                margin-bottom: 3rem;
            }
            
            .story-intro h2 {
                font-size: 2rem;
            }
            
            .story-intro .story-text {
                font-size: 1.1rem;
            }
            
            .sauce-section {
                margin-bottom: 4rem;
            }
            
            .sauce-content {
                gap: 1.5rem;
            }
            
            .sauce-image img,
            .sauce-image .placeholder {
                height: 280px;
            }
            
            .sauce-name {
                font-size: 1.8rem;
            }
            
            .sauce-description {
                font-size: 1rem;
            }
            
            .sauce-price {
                font-size: 1.6rem;
            }
            
            .recipe-card {
                padding: 1.25rem;
                margin-top: 1.5rem;
            }
            
            .recipe-title {
                font-size: 1.2rem;
            }
            
            .floating-cart-icon {
                width: 50px;
                height: 50px;
                font-size: 1.3rem;
            }
            
            .floating-cart-counter {
                width: 24px;
                height: 24px;
                font-size: 0.75rem;
                top: -6px;
                right: -6px;
            }
            
            .story-cta {
                padding: 3rem 1.5rem;
                margin-top: 3rem;
            }
            
            .story-cta h3 {
                font-size: 1.6rem;
            }
            
            .btn-cta {
                padding: 0.875rem 1.5rem;
                width: 100%;
                max-width: 280px;
            }
        }

        /* Extra Small Mobile Devices (360px and below) */
        @media (max-width: 360px) {
            .story-hero h1 {
                font-size: 1.75rem;
            }
            
            .story-hero {
                padding: 2.5rem 0.5rem 1.25rem;
            }
            
            .story-container {
                padding: 1.25rem 0.5rem;
            }
            
            .story-intro {
                padding: 1.5rem 1rem;
            }
            
            .sauce-image img,
            .sauce-image .placeholder {
                height: 240px;
            }
            
            .sauce-name {
                font-size: 1.6rem;
            }
            
            .sauce-info {
                padding: 0.75rem 0;
            }
            
            .recipe-card {
                padding: 1rem;
            }
            
            .floating-cart {
                bottom: 15px;
                right: 15px;
            }
            
            .floating-cart-icon {
                width: 45px;
                height: 45px;
                font-size: 1.2rem;
            }
        }
    </style>
</head>

<!-- IMPORTANT: Add has-header class for proper spacing -->
<body class="has-header">
    <!-- The header (promo banner + navbar) is already included from header.php -->

    <!-- Floating Cart Icon -->
    <div class="floating-cart" id="floatingCart">
        <a href="cart.php" class="floating-cart-link">
            <div class="floating-cart-icon">
                🛒
                <span class="floating-cart-counter <?= $cart_count == 0 ? 'hidden' : '' ?>" id="floatingCartCounter">
                    <?= $cart_count ?>
                </span>
            </div>
        </a>
    </div>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Story Hero Section -->
        <section class="story-hero">
            <div class="story-hero-content">
                <h1>Uncle Kole's Legacy</h1>
                <p class="subtitle">Four Decades of Thai Culinary Passion</p>
                <div class="hero-quote">
                    "Every sauce tells a story, every flavor carries a memory, and every bottle holds the soul of Thailand."
                    <br><em>— Uncle Kole</em>
                </div>
            </div>
        </section>

        <!-- Main Story Container -->
        <div class="story-container">
            <!-- Story Introduction -->
            <section class="story-intro">
                <h2>Our Story</h2>
                <div class="story-text">
                    For four decades, Uncle Kole embarked on a flavorful journey from the heart of Thailand. 
                    <strong>His dream?</strong> To introduce the world to the wonders of Thai cuisine and ingredients. 
                    With an unwavering belief in Thai soft power, he aimed to redefine global taste buds. 
                    The essence of refreshing, hot and spicy, sour and sweet Thai food became his mission.
                </div>
                <div class="legacy-text">
                    Now, in the hands of the second generation, Uncle Kole's legacy lives on, continuing to deliver 
                    the extraordinary and unforgettable gourmet experiences that he envisioned.
                </div>
            </section>

            <!-- Sauce Stories -->
            <?php if (empty($signature_sauces)): ?>
                <section class="story-intro">
                    <h2>Coming Soon!</h2>
                    <div class="story-text">
                        Uncle Kole's signature sauces are being prepared with love. Please check back soon to discover these amazing flavors!
                    </div>
                </section>
            <?php else: ?>
                <?php foreach ($signature_sauces as $index => $sauce): ?>
                <section class="sauce-section" data-sauce-id="<?= htmlspecialchars($sauce['id']) ?>">
                    <div class="sauce-content <?= $index % 2 == 1 ? 'reverse' : '' ?>">
                        <!-- Sauce Image -->
                        <div class="sauce-image">
                            <?php if (!empty($sauce['image_url'])): ?>
                                <img src="<?= htmlspecialchars($sauce['image_url']) ?>" alt="<?= htmlspecialchars($sauce['name']) ?>">
                            <?php else: ?>
                                <div class="placeholder">🍯</div>
                            <?php endif; ?>
                        </div>

                        <!-- Sauce Information -->
                        <div class="sauce-info">
                            <div class="sauce-category">Uncle Kole's Signature</div>
                            <h3 class="sauce-name"><?= htmlspecialchars($sauce['name']) ?></h3>
                            <div class="sauce-size"><?= htmlspecialchars($sauce['size']) ?></div>
                            
                            <p class="sauce-description">
                                <?= htmlspecialchars($sauce['description']) ?>
                            </p>

                            <div class="sauce-story">
                                <div class="story-label">Uncle Kole's Memory</div>
                                <div class="story-content"><?= htmlspecialchars($sauce['story']) ?></div>
                            </div>

                            <div class="sauce-price-cart">
                                <div class="sauce-price"><?= formatPrice($sauce['price']) ?></div>
                                <button class="btn-add-cart" onclick="addToCart('<?= htmlspecialchars($sauce['id']) ?>')" 
                                        data-product-id="<?= htmlspecialchars($sauce['id']) ?>"
                                        data-product-name="<?= htmlspecialchars($sauce['name']) ?>">
                                    🛒 Add to Cart
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Recipe Card -->
                    <div class="recipe-card">
                        <div class="recipe-header">
                            <div>
                                <h4 class="recipe-title"><?= htmlspecialchars($sauce['recipe_title']) ?></h4>
                                <div class="recipe-meta">
                                    <div class="recipe-meta-item">
                                        <span>⏱️</span>
                                        <span><?= htmlspecialchars($sauce['cooking_time']) ?></span>
                                    </div>
                                    <div class="recipe-meta-item">
                                        <span>👥</span>
                                        <span><?= htmlspecialchars($sauce['servings']) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <p class="recipe-description"><?= htmlspecialchars($sauce['recipe_description']) ?></p>
                        
                        <div class="ingredients-uses">
                            <div class="ingredients">
                                <h4>Key Ingredients</h4>
                                <p class="ingredients-text">
                                    <?= htmlspecialchars(implode(', ', $sauce['ingredients'])) ?>
                                </p>
                            </div>
                            
                            <div class="uses">
                                <h4>Perfect For</h4>
                                <p class="uses-text">
                                    <?= htmlspecialchars(implode(', ', $sauce['uses'])) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </section>
            <?php endforeach; ?>
            <?php endif; ?>

            <!-- Call to Action -->
            <section class="story-cta">
                <h3>Taste Uncle Kole's Legacy</h3>
                <p>Experience four decades of culinary passion with our complete sauce collection. Every bottle tells a story, every flavor carries tradition.</p>
                <div class="cta-buttons">
                    <a href="products.php" class="btn-cta">Shop All Products</a>
                    <a href="menus.php" class="btn-cta">Explore Recipes</a>
                    <a href="contact.php" class="btn-cta">Contact Us</a>
                </div>
            </section>
        </div>
    </main>

    <!-- Toast Notification -->
    <div class="toast-notification" id="toastNotification">
        <div class="toast-content">
            <span class="toast-icon">✅</span>
            <span class="toast-text" id="toastText">Sauce added to cart!</span>
        </div>
    </div>

    <script>
        // Cart management and AJAX functionality
        let cartCount = <?= $cart_count ?>;
        
        // Add to cart function
        async function addToCart(productId) {
            const button = document.querySelector(`[data-product-id="${productId}"]`);
            const productName = button.getAttribute('data-product-name');
            const originalText = button.innerHTML;
            
            // Show loading state
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            button.classList.add('loading');
            button.disabled = true;
            
            try {
                const response = await fetch('ajax/add_product_to_cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        product_id: productId,
                        quantity: 1,
                        type: 'product'
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Update cart count
                    cartCount = data.cart_count;
                    updateCartDisplay();
                    
                    // Add bounce effect to floating cart
                    const floatingCart = document.getElementById('floatingCart');
                    if (floatingCart) {
                        floatingCart.style.animation = 'bounce 0.6s ease-out';
                        setTimeout(() => {
                            floatingCart.style.animation = 'slideInUp 0.3s ease-out, float 3s ease-in-out infinite';
                        }, 600);
                    }
                    
                    // Reset button immediately
                    button.innerHTML = originalText;
                    button.classList.remove('loading');
                    button.disabled = false;
                    
                    // Show toast notification
                    showToast(`${productName} added to cart!`);
                    
                } else {
                    throw new Error(data.message || 'Failed to add to cart');
                }
                
            } catch (error) {
                console.error('Error adding to cart:', error);
                
                // Show error state
                button.innerHTML = '❌ Error';
                button.classList.remove('loading');
                
                // Show error toast
                showToast(error.message || 'Error adding to cart', 'error');
                
                // Reset button
                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.disabled = false;
                }, 2000);
            }
        }
        
        // Update cart display
        function updateCartDisplay() {
            const floatingCartCounter = document.getElementById('floatingCartCounter');
            const floatingCart = document.getElementById('floatingCart');
            
            if (floatingCartCounter) {
                floatingCartCounter.textContent = cartCount;
            }
            
            // Show/hide floating cart
            if (cartCount > 0) {
                floatingCart.classList.remove('hidden');
                if (floatingCartCounter) {
                    floatingCartCounter.classList.remove('hidden');
                    // Add pulse animation
                    floatingCartCounter.style.animation = 'pulse 0.6s ease-out';
                    setTimeout(() => {
                        floatingCartCounter.style.animation = '';
                    }, 600);
                }
            } else {
                if (floatingCartCounter) {
                    floatingCartCounter.classList.add('hidden');
                }
                floatingCart.classList.remove('hidden');
            }
            
            // Update navbar cart counter if exists
            const navCartCounter = document.querySelector('.cart-counter');
            if (navCartCounter) {
                navCartCounter.textContent = cartCount;
                navCartCounter.style.display = cartCount > 0 ? 'inline-block' : 'none';
            }
        }
        
        // Show toast notification
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toastNotification');
            const toastText = document.getElementById('toastText');
            const toastIcon = toast.querySelector('.toast-icon');
            
            toastText.textContent = message;
            
            if (type === 'error') {
                toastIcon.textContent = '❌';
                toast.style.background = 'var(--error-color, #e74c3c)';
            } else {
                toastIcon.textContent = '✅';
                toast.style.background = 'var(--sage)';
            }
            
            toast.classList.add('show');
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // Smooth scrolling for sauce sections
        function scrollToSauce(sauceId) {
            const section = document.querySelector(`[data-sauce-id="${sauceId}"]`);
            if (section) {
                section.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        }

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Uncle Kole\'s Sauce Story page loaded');
            updateCartDisplay();
            
            // Add smooth scroll animation on load
            const sections = document.querySelectorAll('.sauce-section');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, {
                threshold: 0.2,
                rootMargin: '0px 0px -50px 0px'
            });

            sections.forEach(section => {
                section.style.opacity = '0';
                section.style.transform = 'translateY(50px)';
                section.style.transition = 'opacity 0.8s ease, transform 0.8s ease';
                observer.observe(section);
            });
            
            // Close toast notification when clicked
            const toast = document.getElementById('toastNotification');
            toast.addEventListener('click', function() {
                this.classList.remove('show');
            });
        });
        
        // Handle escape key to close toast
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const toast = document.getElementById('toastNotification');
                toast.classList.remove('show');
            }
        });
    </script>
</body>
</html>