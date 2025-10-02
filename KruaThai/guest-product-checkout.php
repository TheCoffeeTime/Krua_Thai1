<?php
/**
 * Somdul Table - Guest Product Checkout
 * File: guest-product-checkout.php
 * Description: Checkout page for guest users purchasing individual products
 * UPDATED: Now redirects to guest-order-status.php after successful order
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

// Load Stripe configuration
$stripe_config = require_once 'stripe_config.php';
$environment = $stripe_config['environment'];
$stripe_publishable_key = $stripe_config[$environment]['publishable_key'];

// Utility Functions
class GuestProductCheckoutUtils {
    
    public static function generateUUID() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    public static function generateOrderNumber() {
        return 'GUEST-PRD-' . date('Ymd-His') . '-' . substr(self::generateUUID(), 0, 6);
    }
    
    public static function sanitizeInput($input) {
        return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
    }
    
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    public static function validatePhone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        return strlen($phone) === 10 || strlen($phone) === 11;
    }
    
    public static function formatPrice($price) {
        return '$' . number_format($price, 2);
    }
    
    public static function calculateShipping($subtotal) {
        return $subtotal >= 50 ? 0.00 : 7.99;
    }
    
    public static function calculateTax($subtotal, $state = 'CA') {
        $tax_rates = [
            'CA' => 0.0875, // 8.75%
            'NY' => 0.08,   // 8%
            'TX' => 0.0625, // 6.25%
            'FL' => 0.06,   // 6%
        ];
        
        $rate = $tax_rates[$state] ?? 0.05;
        return $subtotal * $rate;
    }
}

// Initialize variables
$selected_product = null;
$errors = [];
$success = false;

// Database connection with fallback
try {
    $database = new Database();
    $pdo = $database->getConnection();
} catch (Exception $e) {
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=somdul_table;charset=utf8mb4", "root", "root");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        $pdo = new PDO("mysql:host=localhost:8889;dbname=somdul_table;charset=utf8mb4", "root", "root");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
}

// Handle different entry points - FIXED VERSION
$source = trim(strtolower($_GET['source'] ?? ''));
$product_id = trim($_GET['product'] ?? '');

// Check if this is a cart checkout
$is_cart_checkout = false;

// More robust cart detection
if ($source === 'cart' || (isset($_SESSION['cart']) && !empty($_SESSION['cart']) && empty($product_id))) {
    // Initialize cart if not exists
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // Check if cart has items - redirect to cart page, not product page
    if (empty($_SESSION['cart'])) {
        $_SESSION['flash_message'] = 'Your cart is empty. Please add items before checkout.';
        $_SESSION['flash_type'] = 'error';
        header('Location: cart.php');
        exit;
    }
    
    $is_cart_checkout = true;
    
} elseif (!empty($product_id)) {
    // Direct product purchase
    $is_cart_checkout = false;
    
} else {
    // Neither cart nor direct product - unclear intent
    // Check if there's anything in the cart first
    if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
        // Has cart items, probably meant to checkout cart
        $_SESSION['flash_message'] = 'Redirected to cart checkout.';
        $_SESSION['flash_type'] = 'info';
        header('Location: guest-product-checkout.php?source=cart');
        exit;
    } else {
        // No cart items and no product specified - go to products page
        $_SESSION['flash_message'] = 'Please select a product to checkout.';
        $_SESSION['flash_type'] = 'info';
        header('Location: product.php');
        exit;
    }
}

// Debug logging (remove in production)
error_log("Guest Checkout Debug - Source: '$source', Product ID: '$product_id', Is Cart: " . ($is_cart_checkout ? 'Yes' : 'No'));

// Fetch selected product
try {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
    $stmt->execute([$product_id]);
    $selected_product = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Fallback products
    $fallback_products = [
        'pad-thai-kit-pro' => [
            'id' => 'pad-thai-kit-pro',
            'name' => 'Premium Pad Thai Kit',
            'description' => 'Complete authentic Pad Thai kit with premium ingredients.',
            'price' => 24.99,
            'category' => 'meal-kit',
            'stock_quantity' => 50
        ],
        'tom-yum-paste-authentic' => [
            'id' => 'tom-yum-paste-authentic',
            'name' => 'Authentic Tom Yum Paste',
            'description' => 'Traditional Tom Yum paste made with fresh ingredients.',
            'price' => 12.99,
            'category' => 'sauce',
            'stock_quantity' => 100
        ],
        'thai-curry-kit-trio' => [
            'id' => 'thai-curry-kit-trio',
            'name' => 'Thai Curry Kit Trio',
            'description' => 'Three authentic curry pastes: Red, Green, and Yellow.',
            'price' => 34.99,
            'category' => 'meal-kit',
            'stock_quantity' => 30
        ],
        'fish-sauce-premium' => [
            'id' => 'fish-sauce-premium',
            'name' => 'Premium Fish Sauce',
            'description' => 'Artisanal fish sauce aged for 2 years.',
            'price' => 18.99,
            'category' => 'sauce',
            'stock_quantity' => 75
        ],
        'thai-chili-oil-spicy' => [
            'id' => 'thai-chili-oil-spicy',
            'name' => 'Spicy Thai Chili Oil',
            'description' => 'Handcrafted chili oil with Thai chilies.',
            'price' => 15.99,
            'category' => 'sauce',
            'stock_quantity' => 60
        ],
        'som-tam-kit-fresh' => [
            'id' => 'som-tam-kit-fresh',
            'name' => 'Fresh Som Tam Kit',
            'description' => 'Everything needed for authentic papaya salad.',
            'price' => 19.99,
            'category' => 'meal-kit',
            'stock_quantity' => 40
        ]
    ];
    
    $selected_product = $fallback_products[$product_id] ?? null;
}

if (!$selected_product && !$is_cart_checkout) {
    header('Location: product.php');
    exit;
}

// Handle form submission BEFORE any output to avoid header errors
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    
    // Get form data
    $quantity = max(1, intval($_POST['quantity'] ?? 1));
    $first_name = GuestProductCheckoutUtils::sanitizeInput($_POST['first_name'] ?? '');
    $last_name = GuestProductCheckoutUtils::sanitizeInput($_POST['last_name'] ?? '');
    $email = GuestProductCheckoutUtils::sanitizeInput($_POST['email'] ?? '');
    $phone = GuestProductCheckoutUtils::sanitizeInput($_POST['phone'] ?? '');
    $shipping_address = GuestProductCheckoutUtils::sanitizeInput($_POST['shipping_address'] ?? '');
    $shipping_city = GuestProductCheckoutUtils::sanitizeInput($_POST['shipping_city'] ?? '');
    $shipping_state = GuestProductCheckoutUtils::sanitizeInput($_POST['shipping_state'] ?? '');
    $shipping_zip = GuestProductCheckoutUtils::sanitizeInput($_POST['shipping_zip'] ?? '');
    $payment_method = GuestProductCheckoutUtils::sanitizeInput($_POST['payment_method'] ?? '');
    
    // Validation
    if (empty($first_name)) $errors[] = "First name is required";
    if (empty($last_name)) $errors[] = "Last name is required";
    if (!GuestProductCheckoutUtils::validateEmail($email)) $errors[] = "Valid email address is required";
    if (!GuestProductCheckoutUtils::validatePhone($phone)) $errors[] = "Valid phone number is required";
    if (empty($shipping_address)) $errors[] = "Shipping address is required";
    if (empty($shipping_city)) $errors[] = "City is required";
    if (empty($shipping_state)) $errors[] = "State is required";
    if (empty($shipping_zip)) $errors[] = "ZIP code is required";
    if (empty($payment_method)) $errors[] = "Payment method is required";
    
    // Validate Stripe payment
    $stripe_payment_intent_id = $_POST['stripe_payment_intent_id'] ?? null;

    if (!$stripe_payment_intent_id && empty($errors)) {
        $errors[] = "Payment processing failed. Please try again.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Calculate totals
            if ($is_cart_checkout) {
                $subtotal = 0;
                foreach ($_SESSION['cart'] as $item) {
                    $quantity_item = intval($item['quantity']);
                    $price = floatval($item['base_price']);
                    $item_total = $price * $quantity_item;
                    
                    // Add customization costs
                    if (isset($item['customizations'])) {
                        if (isset($item['customizations']['extra_protein']) && $item['customizations']['extra_protein']) {
                            $item_total += 2.99 * $quantity_item;
                        }
                        if (isset($item['customizations']['extra_vegetables']) && $item['customizations']['extra_vegetables']) {
                            $item_total += 1.99 * $quantity_item;
                        }
                    }
                    
                    $subtotal += $item_total;
                }
            } else {
                $subtotal = $selected_product['price'] * $quantity;
            }
            
            $shipping_cost = GuestProductCheckoutUtils::calculateShipping($subtotal);
            $tax_amount = GuestProductCheckoutUtils::calculateTax($subtotal, $shipping_state);
            $total_amount = $subtotal + $shipping_cost + $tax_amount;
            
            // Create guest user if email doesn't exist
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing_user) {
                $user_id = $existing_user['id'];
            } else {
                $user_id = GuestProductCheckoutUtils::generateUUID();
                $default_password_hash = password_hash('guest_' . time(), PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("
                    INSERT INTO users (
                        id, first_name, last_name, email, phone, 
                        password_hash, delivery_address, city, state, zip_code,
                        role, status, email_verified, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'customer', 'active', 0, NOW(), NOW())
                ");
                $stmt->execute([
                    $user_id, $first_name, $last_name, $email, $phone,
                    $default_password_hash, $shipping_address, $shipping_city, 
                    $shipping_state, $shipping_zip
                ]);
            }
            
            // Create product order
            $order_id = GuestProductCheckoutUtils::generateUUID();
            $order_number = GuestProductCheckoutUtils::generateOrderNumber();
            
            $stmt = $pdo->prepare("
                INSERT INTO product_orders (
                    id, order_number, user_id, customer_email, customer_name, customer_phone,
                    shipping_address_line1, shipping_city, shipping_state, shipping_zip,
                    subtotal, shipping_cost, tax_amount, total_amount,
                    status, payment_status, payment_method, stripe_payment_intent_id, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', 'paid', 'credit_card', ?, NOW())
            ");
            
            $full_name = trim($first_name . ' ' . $last_name);
            
            $stmt->execute([
                $order_id, $order_number, $user_id, $email, $full_name, $phone,
                $shipping_address, $shipping_city, $shipping_state, $shipping_zip,
                $subtotal, $shipping_cost, $tax_amount, $total_amount, $stripe_payment_intent_id
            ]);
            
            // Create order items
            if ($is_cart_checkout) {
                foreach ($_SESSION['cart'] as $item) {
                    $item_id = GuestProductCheckoutUtils::generateUUID();
                    $quantity_item = intval($item['quantity']);
                    $price = floatval($item['base_price']);
                    $item_subtotal = $price * $quantity_item;
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO product_order_items (
                            id, order_id, product_id, product_name, quantity, unit_price, total_price
                        ) VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $stmt->execute([
                        $item_id, $order_id, $item['id'] ?? 'unknown', $item['name'], 
                        $quantity_item, $price, $item_subtotal
                    ]);
                }
                
                // Clear cart after successful order
                $_SESSION['cart'] = [];
            } else {
                // Single product
                $item_id = GuestProductCheckoutUtils::generateUUID();
                $stmt = $pdo->prepare("
                    INSERT INTO product_order_items (
                        id, order_id, product_id, product_name, quantity, unit_price, total_price
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $item_id, $order_id, $product_id, $selected_product['name'], 
                    $quantity, $selected_product['price'], $subtotal
                ]);
            }
            
            $pdo->commit();
            
            // UPDATED: Redirect to guest-order-status.php instead of showing success inline
            header("Location: guest-order-status.php?order_number=" . urlencode($order_number) . "&email=" . urlencode($email));
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Failed to place order: " . $e->getMessage();
        }
    }
}

// Calculate default totals
if ($is_cart_checkout) {
    $default_subtotal = 0;
    foreach ($_SESSION['cart'] as $item) {
        $quantity_item = intval($item['quantity']);
        $price = floatval($item['base_price']);
        $item_total = $price * $quantity_item;
        
        // Add customization costs
        if (isset($item['customizations'])) {
            if (isset($item['customizations']['extra_protein']) && $item['customizations']['extra_protein']) {
                $item_total += 2.99 * $quantity_item;
            }
            if (isset($item['customizations']['extra_vegetables']) && $item['customizations']['extra_vegetables']) {
                $item_total += 1.99 * $quantity_item;
            }
        }
        
        $default_subtotal += $item_total;
    }
} else {
    $default_quantity = 1;
    $default_subtotal = $selected_product['price'] * $default_quantity;
}

$default_shipping = GuestProductCheckoutUtils::calculateShipping($default_subtotal);
$default_tax = GuestProductCheckoutUtils::calculateTax($default_subtotal);
$default_total = $default_subtotal + $default_shipping + $default_tax;

// Include header AFTER all form processing to avoid header errors
include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout<?= $is_cart_checkout ? ' - Your Cart' : ' - ' . htmlspecialchars($selected_product['name']) ?> | Somdul Table</title>
    <script src="https://js.stripe.com/v3/"></script>
    <meta name="description" content="Complete your purchase<?= $is_cart_checkout ? ' from your cart' : ' of ' . htmlspecialchars($selected_product['name']) ?>">
    
    <style>
        /* PAGE-SPECIFIC STYLES ONLY - header styles come from header.php */
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .back-link {
            color: var(--curry);
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 2rem;
            display: inline-block;
        }

        .checkout-content {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 3rem;
        }

        .checkout-form {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-soft);
        }

        .order-summary {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-soft);
            height: fit-content;
            position: sticky;
            top: 140px;
        }

        .section-title {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: var(--text-dark);
            font-weight: 700;
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .form-section-title {
            font-size: 1.2rem;
            margin-bottom: 1rem;
            color: var(--text-dark);
            font-weight: 600;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--cream);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-dark);
        }

        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-sm);
            font-size: 1rem;
            font-family: 'BaticaSans', sans-serif;
            transition: var(--transition);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--curry);
            box-shadow: 0 0 0 3px rgba(207, 114, 58, 0.1);
        }

        .form-select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-sm);
            font-size: 1rem;
            font-family: 'BaticaSans', sans-serif;
            background: var(--white);
            transition: var(--transition);
        }

        .form-select:focus {
            outline: none;
            border-color: var(--curry);
            box-shadow: 0 0 0 3px rgba(207, 114, 58, 0.1);
        }

        .product-summary {
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-light);
        }

        .product-item {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--cream);
        }

        .product-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .product-image {
            width: 80px;
            height: 80px;
            background: linear-gradient(45deg, var(--curry), var(--brown));
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 1.5rem;
        }

        .product-details h3 {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }

        .product-price {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--curry);
        }

        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .quantity-btn {
            width: 30px;
            height: 30px;
            border: 1px solid var(--brown);
            background: var(--white);
            color: var(--brown);
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .quantity-btn:hover {
            background: var(--brown);
            color: var(--white);
        }

        .quantity-input {
            width: 50px;
            text-align: center;
            border: 1px solid var(--border-light);
            border-radius: var(--radius-sm);
            padding: 0.25rem;
        }

        .price-breakdown {
            margin-bottom: 1.5rem;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .price-row.total {
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--brown);
            border-top: 1px solid var(--border-light);
            padding-top: 0.5rem;
            margin-top: 1rem;
        }

        .checkout-buttons {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .checkout-buttons .btn {
            width: 100%;
        }

        .error-message {
            background: rgba(231, 76, 60, 0.1);
            color: var(--error-color, #e74c3c);
            padding: 1rem;
            border-radius: var(--radius-sm);
            margin-bottom: 1rem;
            border: 1px solid var(--error-color, #e74c3c);
        }

        @media (max-width: 768px) {
            .checkout-content {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .order-summary {
                position: static;
                order: -1;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .container {
                padding: 0 1rem;
            }
        }
    </style>
</head>

<!-- IMPORTANT: Add has-header class for proper spacing -->
<body class="has-header">
    <div class="container">
        <!-- Back Link -->
        <a href="<?= $is_cart_checkout ? 'cart.php' : 'product.php' ?>" class="back-link">← Back to <?= $is_cart_checkout ? 'Cart' : 'Products' ?></a>

        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
            <div class="error-message">
                <strong>Please fix the following errors:</strong>
                <ul style="margin: 0.5rem 0 0 1.5rem;">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="checkout-content">
            <!-- Checkout Form -->
            <div class="checkout-form">
                <h1 class="section-title">Complete Your Order</h1>
                
                <form method="POST" id="checkoutForm">
                    <!-- Contact Information -->
                    <div class="form-section">
                        <h2 class="form-section-title">Contact Information</h2>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name" class="form-label">First Name *</label>
                                <input type="text" id="first_name" name="first_name" class="form-input" 
                                       value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="last_name" class="form-label">Last Name *</label>
                                <input type="text" id="last_name" name="last_name" class="form-input" 
                                       value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" id="email" name="email" class="form-input" 
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="phone" class="form-label">Phone *</label>
                                <input type="tel" id="phone" name="phone" class="form-input" 
                                       value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
                            </div>
                        </div>
                    </div>

                    <!-- Shipping Information -->
                    <div class="form-section">
                        <h2 class="form-section-title">Shipping Information</h2>
                        <div class="form-group">
                            <label for="shipping_address" class="form-label">Address *</label>
                            <input type="text" id="shipping_address" name="shipping_address" class="form-input" 
                                   value="<?= htmlspecialchars($_POST['shipping_address'] ?? '') ?>" required>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="shipping_city" class="form-label">City *</label>
                                <input type="text" id="shipping_city" name="shipping_city" class="form-input" 
                                       value="<?= htmlspecialchars($_POST['shipping_city'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="shipping_state" class="form-label">State *</label>
                                <select id="shipping_state" name="shipping_state" class="form-select" required>
                                    <option value="">Select State</option>
                                    <option value="CA">California</option>
                                    <option value="NY">New York</option>
                                    <option value="TX">Texas</option>
                                    <option value="FL">Florida</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="shipping_zip" class="form-label">ZIP Code *</label>
                            <input type="text" id="shipping_zip" name="shipping_zip" class="form-input" 
                                   value="<?= htmlspecialchars($_POST['shipping_zip'] ?? '') ?>" required>
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <!-- Payment with Stripe -->
                    <div class="form-section">
                        <h2 class="form-section-title">💳 Payment Information</h2>
                        
                        <div id="card-element" style="padding: 0.75rem; border: 2px solid var(--border-light); border-radius: var(--radius-sm); background: white; min-height: 40px;"></div>
                        <div id="card-errors" role="alert" style="color: #e74c3c; margin-top: 0.5rem; font-size: 0.9rem; display: none;"></div>
                        
                        <input type="hidden" name="stripe_payment_intent_id" id="stripe_payment_intent_id">
                        <input type="hidden" name="payment_method" value="credit_card">
                    </div>

                    <div class="checkout-buttons">
                        <button type="submit" name="place_order" class="btn btn-primary">
                            💳 Place Order - <?= GuestProductCheckoutUtils::formatPrice($default_total) ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Order Summary -->
            <div class="order-summary">
                <h3 class="section-title">Order Summary</h3>
                
                <div class="product-summary">
                    <?php if ($is_cart_checkout): ?>
                        <!-- Display all cart items -->
                        <?php foreach ($_SESSION['cart'] as $item): ?>
                            <div class="product-item">
                                <div class="product-image">🍜</div>
                                <div class="product-details">
                                    <h3><?= htmlspecialchars($item['name']) ?></h3>
                                    <div class="product-price"><?= GuestProductCheckoutUtils::formatPrice($item['base_price']) ?> each</div>
                                    <div style="font-size: 0.9rem; color: var(--text-gray);">Qty: <?= intval($item['quantity']) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Single product -->
                        <div class="product-item">
                            <div class="product-image">🍜</div>
                            <div class="product-details">
                                <h3><?= htmlspecialchars($selected_product['name']) ?></h3>
                                <div class="product-price"><?= GuestProductCheckoutUtils::formatPrice($selected_product['price']) ?> each</div>
                                
                                <div class="quantity-selector">
                                    <button type="button" class="quantity-btn" onclick="updateQuantity(-1)">-</button>
                                    <input type="number" class="quantity-input" id="quantity" name="quantity" value="1" min="1" max="10">
                                    <button type="button" class="quantity-btn" onclick="updateQuantity(1)">+</button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="price-breakdown">
                    <div class="price-row">
                        <span>Subtotal:</span>
                        <span id="subtotal"><?= GuestProductCheckoutUtils::formatPrice($default_subtotal) ?></span>
                    </div>
                    <div class="price-row">
                        <span>Shipping:</span>
                        <span id="shipping"><?= GuestProductCheckoutUtils::formatPrice($default_shipping) ?></span>
                    </div>
                    <div class="price-row">
                        <span>Tax:</span>
                        <span id="tax"><?= GuestProductCheckoutUtils::formatPrice($default_tax) ?></span>
                    </div>
                    <div class="price-row total">
                        <span>Total:</span>
                        <span id="total"><?= GuestProductCheckoutUtils::formatPrice($default_total) ?></span>
                    </div>
                </div>
                
                <div style="font-size: 0.9rem; color: var(--text-gray); text-align: center;">
                    🚚 Free shipping on orders over $50<br>
                    📞 Questions? <a href="contact.php" style="color: var(--curry);">Contact us</a>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Stripe Integration
<script>
    // Wait for DOM to be ready
    document.addEventListener('DOMContentLoaded', function() {
        // Stripe Integration
        const stripe = Stripe('<?php echo $stripe_publishable_key; ?>');
        const elements = stripe.elements();

        const style = {
            base: {
                color: '#2c3e50',
                fontFamily: 'BaticaSans, -apple-system, BlinkMacSystemFont, sans-serif',
                fontSmoothing: 'antialiased',
                fontSize: '16px',
                '::placeholder': {
                    color: '#adb89d'
                }
            },
            invalid: {
                color: '#e74c3c',
                iconColor: '#e74c3c'
            }
        };

        const cardElement = elements.create('card', {style: style});
        cardElement.mount('#card-element');

    // Handle real-time validation errors
    cardElement.on('change', function(event) {
        const displayError = document.getElementById('card-errors');
        if (event.error) {
            displayError.textContent = event.error.message;
            displayError.style.display = 'block';
        } else {
            displayError.textContent = '';
            displayError.style.display = 'none';
        }
    });

    // Original variables
    const isCartCheckout = <?= $is_cart_checkout ? 'true' : 'false' ?>;
    const productPrice = <?= $is_cart_checkout ? $default_subtotal : ($selected_product['price'] ?? 0) ?>;
    
    // Handle form submission with Stripe
    const form = document.getElementById('checkoutForm');
    const submitBtn = form.querySelector('[name="place_order"]');
    const submitBtnOriginalText = submitBtn.innerHTML;

    async function handleFormSubmission(event) {
        event.preventDefault();
        
        // Basic validation
        const requiredFields = ['first_name', 'last_name', 'email', 'phone', 'shipping_address', 'shipping_city', 'shipping_state', 'shipping_zip'];
        let hasErrors = false;
        
        requiredFields.forEach(field => {
            const input = document.querySelector(`[name="${field}"]`);
            if (input && !input.value.trim()) {
                input.style.borderColor = '#e74c3c';
                hasErrors = true;
            } else if (input) {
                input.style.borderColor = '#d4c4b8';
            }
        });
        
        if (hasErrors) {
            alert('Please fill in all required fields.');
            return;
        }
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing Payment...';
        
        try {
            const totalAmount = <?= $default_total ?>;
            
            // Create payment intent
            const response = await fetch('ajax/create_product_payment_intent.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    amount: totalAmount,
                    currency: 'usd',
                    description: '<?= $is_cart_checkout ? "Cart Checkout" : ($selected_product["name"] ?? "Product Order") ?>'
                })
            });
            
            const paymentData = await response.json();
            
            if (!paymentData.success) {
                throw new Error(paymentData.message || 'Failed to create payment');
            }
            
            // Confirm payment
            const result = await stripe.confirmCardPayment(paymentData.payment_intent.client_secret, {
                payment_method: {
                    card: cardElement,
                    billing_details: {
                        name: document.querySelector('[name="first_name"]').value + ' ' + document.querySelector('[name="last_name"]').value,
                        email: document.querySelector('[name="email"]').value,
                        phone: document.querySelector('[name="phone"]').value,
                        address: {
                            line1: document.querySelector('[name="shipping_address"]').value,
                            city: document.querySelector('[name="shipping_city"]').value,
                            state: document.querySelector('[name="shipping_state"]').value,
                            postal_code: document.querySelector('[name="shipping_zip"]').value,
                        }
                    }
                }
            });
            
            if (result.error) {
                throw new Error(result.error.message);
            } else {
                const successStatuses = ['succeeded', 'processing', 'requires_capture'];
                
                if (successStatuses.includes(result.paymentIntent.status)) {
                    console.log('Payment successful:', result.paymentIntent.id);
                    
                    document.getElementById('stripe_payment_intent_id').value = result.paymentIntent.id;
                    submitBtn.innerHTML = '<i class="fas fa-check"></i> Payment Successful - Completing Order...';
                    
                    // Submit form
                    form.removeEventListener('submit', handleFormSubmission);
                    form.submit();
                    
                } else {
                    throw new Error(`Payment status: ${result.paymentIntent.status}`);
                }
            }
            
        } catch (error) {
            console.error('Payment error:', error);
            
            const cardErrors = document.getElementById('card-errors');
            cardErrors.textContent = error.message || 'Payment failed. Please try again.';
            cardErrors.style.display = 'block';
            
            submitBtn.disabled = false;
            submitBtn.innerHTML = submitBtnOriginalText;
        }
    }

    form.addEventListener('submit', handleFormSubmission);
    
    // Keep original quantity functions for single product checkout
    function updateQuantity(change) {
        if (isCartCheckout) return;
        
        const quantityInput = document.getElementById('quantity');
        let newQuantity = parseInt(quantityInput.value) + change;
        
        if (newQuantity < 1) newQuantity = 1;
        if (newQuantity > 10) newQuantity = 10;
        
        quantityInput.value = newQuantity;
        updateTotals();
    }
    
    function updateTotals() {
        if (isCartCheckout) return;
        
        const quantity = parseInt(document.getElementById('quantity').value);
        const state = document.getElementById('shipping_state').value || 'CA';
        
        const subtotal = productPrice * quantity;
        const shipping = subtotal >= 50 ? 0 : 7.99;
        
        const taxRates = { 'CA': 0.0875, 'NY': 0.08, 'TX': 0.0625, 'FL': 0.06 };
        const taxRate = taxRates[state] || 0.05;
        const tax = subtotal * taxRate;
        
        const total = subtotal + shipping + tax;
        
        document.getElementById('subtotal').textContent = '$' + subtotal.toFixed(2);
        document.getElementById('shipping').textContent = '$' + shipping.toFixed(2);
        document.getElementById('tax').textContent = '$' + tax.toFixed(2);
        document.getElementById('total').textContent = '$' + total.toFixed(2);
        
        const submitBtn = document.querySelector('.btn-primary');
        submitBtn.innerHTML = `💳 Place Order - $${total.toFixed(2)}`;
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        if (!isCartCheckout) {
            document.getElementById('quantity').addEventListener('change', updateTotals);
        }
        document.getElementById('shipping_state').addEventListener('change', updateTotals);
    });
</script>
</body>
</html>