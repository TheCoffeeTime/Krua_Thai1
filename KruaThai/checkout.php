<?php
/**
 * Somdul Table - Complete Checkout with Stripe Integration
 * File: checkout.php
 * Description: EXACT copy of checkoutold.php functionality + Stripe payments + proper redirect
 * FIXED: Database operations in correct order (subscription FIRST, then payment)
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize variables with defaults
$order = null;
$plan = null;
$selected_meals = [];
$meal_details = [];
$errors = [];
$success = false;
$user = null;

// Early session and authentication checks
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Load Stripe configuration
$stripe_config = require_once 'stripe_config.php';
$environment = $stripe_config['environment'];
$stripe_publishable_key = $stripe_config[$environment]['publishable_key'];

// Database connection - make $pdo available for header.php
try {
    require_once 'config/database.php';
    require_once 'ReferralManager.php';
    $database = new Database();
    $pdo = $database->getConnection();
} catch (Exception $e) {
    // Fallback connections for header.php compatibility
    $configs = [
        ["mysql:host=localhost;dbname=somdul_table;charset=utf8mb4", "root", "root"],
        ["mysql:host=localhost:8889;dbname=somdul_table;charset=utf8mb4", "root", "root"]
    ];
    
    $pdo = null;
    foreach ($configs as $config) {
        try {
            $pdo = new PDO($config[0], $config[1], $config[2]);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            break;
        } catch (PDOException $e) {
            continue;
        }
    }
    
    if ($pdo === null) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// Utility Functions (EXACT copy from checkoutold.php)
class CheckoutUtils {
    
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
        return 'TXN-' . date('Ymd-His') . '-' . substr(self::generateUUID(), 0, 6);
    }
    
    public static function getPlanName($plan) {
        return $plan['name'] ?? 'Selected Package';
    }
    
    public static function getMenuName($menu) {
        return $menu['name'] ?? 'Menu Item';
    }
    
    public static function sanitizeInput($input) {
        return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
    }
    
    public static function formatPrice($price) {
        if ($price > 1000) {
            return number_format($price / 100, 2);
        }
        return number_format($price, 2);
    }
    
    public static function getPriceValue($price) {
        if ($price > 1000) {
            return $price / 100;
        }
        return $price;
    }
}

// Database Connection Handler (EXACT copy from checkoutold.php)
class DatabaseConnection {
    private static $connection = null;
    
    public static function getInstance() {
        global $pdo;
        if (self::$connection === null) {
            try {
                require_once 'config/database.php';
                require_once 'NotificationManager.php';
                self::$connection = (new Database())->getConnection();
            } catch (Exception $e) {
                $configs = [
                    ["mysql:host=localhost;dbname=somdul_table;charset=utf8mb4", "root", "root"],
                    ["mysql:host=localhost:8889;dbname=somdul_table;charset=utf8mb4", "root", "root"]
                ];
                
                foreach ($configs as $config) {
                    try {
                        self::$connection = new PDO($config[0], $config[1], $config[2]);
                        self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        break;
                    } catch (PDOException $e) {
                        continue;
                    }
                }
                
                if (self::$connection === null) {
                    throw new Exception("Database connection failed: " . $e->getMessage());
                }
            }
        }
        return self::$connection;
    }
}

// Checkout Data Manager (EXACT copy from checkoutold.php)
class CheckoutDataManager {
  
    public static function validateCheckoutData($order) {
        $errors = [];
        
        if (!$order || empty($order['plan']) || empty($order['selected_meals'])) {
            $errors[] = "Invalid checkout data";
        }
        
        if (!isset($order['plan']['id']) || !isset($order['plan']['final_price'])) {
            $errors[] = "Invalid plan data";
        }
        
        if (empty($order['selected_meals']) || !is_array($order['selected_meals'])) {
            $errors[] = "No meals selected";
        }
        
        return $errors;
    }
    
    public static function populateMealDetails($db, $selected_meals) {
        if (empty($selected_meals) || !is_array($selected_meals)) {
            return [];
        }
        
        try {
            $placeholders = str_repeat('?,', count($selected_meals) - 1) . '?';
            
            $stmt = $db->prepare("
                SELECT id, name, name_thai, base_price, description, main_image_url,
                       mc.name as category_name, mc.name_thai as category_name_thai
                FROM menus m 
                LEFT JOIN menu_categories mc ON m.category_id = mc.id 
                WHERE m.id IN ($placeholders) AND m.is_available = 1
            ");
            
            $stmt->execute($selected_meals);
            $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $meal_details = [];
            foreach ($menus as $menu) {
                $meal_details[$menu['id']] = $menu;
            }
            
            return $meal_details;
            
        } catch (Exception $e) {
            error_log("Error fetching meal details: " . $e->getMessage());
            return [];
        }
    }
}

// Order Processing Engine - MODIFIED FOR STRIPE BUT SAME DATABASE ORDER
class OrderProcessor {
    
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function validateFormInput($postData) {
        $errors = [];
        
        $required_fields = [
            'delivery_address' => 'Delivery address',
            'city' => 'City/State',
            'zip_code' => 'ZIP code'
        ];
        
        foreach ($required_fields as $field => $label) {
            if (empty(trim($postData[$field] ?? ''))) {
                $errors[] = "Please enter {$label}";
            }
        }
        
        // Delivery date validation
        if (empty($postData['delivery_day'])) {
            $errors[] = "Please select a delivery date";
        } else {
            $delivery_date = $postData['delivery_day'];
            $date_obj = DateTime::createFromFormat('Y-m-d', $delivery_date);
            
            if (!$date_obj) {
                $errors[] = "Invalid date format";
            } else {
                $day_of_week = $date_obj->format('N');
                if ($day_of_week != 3 && $day_of_week != 6) {
                    $errors[] = "Please select a Wednesday or Saturday for delivery";
                }
                
                $today = new DateTime();
                if ($date_obj < $today) {
                    $errors[] = "Please select a future date for delivery";
                }
                
                $max_date = new DateTime('+4 weeks');
                if ($date_obj > $max_date) {
                    $errors[] = "Please select a date within the next 4 weeks";
                }
            }
        }
        
        // ZIP code validation
        $zip_code = trim($postData['zip_code'] ?? '');
        if (!preg_match('/^\d{5}$/', $zip_code)) {
            $errors[] = "ZIP code must be 5 digits";
        }
        
        return $errors;
    }
    
    // EXACT copy from checkoutold.php
    public function createSubscription($data) {
        $subscription_id = CheckoutUtils::generateUUID();
        
        $stmt = $this->db->prepare("INSERT INTO subscriptions (
            id, user_id, plan_id, status, start_date, next_billing_date,
            billing_cycle, total_amount, delivery_days, preferred_delivery_time,
            special_instructions, auto_renew, created_at, updated_at
        ) VALUES (?, ?, ?, 'active', ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())");
        
        $result = $stmt->execute([
            $subscription_id,
            $data['user_id'],
            $data['plan_id'],
            $data['start_date'],
            $data['next_billing_date'],
            $data['billing_cycle'],
            $data['total_amount'],
            $data['delivery_days'],
            $data['preferred_time'],
            $data['delivery_instructions']
        ]);
        
        if (!$result) {
            throw new Exception("Failed to create subscription");
        }
        
        return $subscription_id;
    }
    
    // MODIFIED: For Stripe payments (now expects subscription_id to exist FIRST)
    public function createPayment($data) {
        $payment_id = CheckoutUtils::generateUUID();
        $transaction_id = $data['stripe_payment_intent_id'] ?? CheckoutUtils::generateOrderNumber();
        
        // For Stripe payments, always use credit_card
        $db_payment_method = 'credit_card';
        
        $stmt = $this->db->prepare("INSERT INTO payments (
            id, subscription_id, user_id, payment_method, payment_provider, 
            transaction_id, external_payment_id, amount, currency, fee_amount, 
            net_amount, status, payment_date, billing_period_start, billing_period_end, 
            description, created_at, updated_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
        )");
        
        $result = $stmt->execute([
            $payment_id,
            $data['subscription_id'],  // THIS MUST EXIST - created BEFORE payment
            $data['user_id'],
            $db_payment_method,
            'stripe',
            $transaction_id,
            $data['stripe_payment_intent_id'] ?? null,
            $data['amount'],
            'USD',
            0.00,
            $data['amount'],
            'completed',
            date('Y-m-d H:i:s'),
            $data['start_date'],
            $data['next_billing_date'],
            $data['description']
        ]);
        
        if (!$result) {
            throw new Exception("Failed to create payment");
        }
        
        return ['payment_id' => $payment_id, 'transaction_id' => $transaction_id];
    }
    
    // EXACT copy from checkoutold.php
    public function createSubscriptionMenus($subscription_id, $selected_meals, $delivery_date, $selected_meals_quantities = []) {
        $stmt = $this->db->prepare("INSERT INTO subscription_menus
            (id, subscription_id, menu_id, delivery_date, quantity, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 'scheduled', NOW(), NOW())");
        
        if (!empty($selected_meals_quantities)) {
            foreach ($selected_meals_quantities as $meal_id => $quantity) {
                $menu_uuid = CheckoutUtils::generateUUID();
                $result = $stmt->execute([$menu_uuid, $subscription_id, $meal_id, $delivery_date, $quantity]);
                
                if (!$result) {
                    throw new Exception("Failed to create subscription menu");
                }
            }
        } else {
            foreach ($selected_meals as $meal_id) {
                $menu_uuid = CheckoutUtils::generateUUID();
                $result = $stmt->execute([$menu_uuid, $subscription_id, $meal_id, $delivery_date, 1]);
                
                if (!$result) {
                    throw new Exception("Failed to create subscription menu");
                }
            }
        }
    }
    
    // EXACT copy from checkoutold.php
    public function updateUserProfile($user_id, $data) {
        $stmt = $this->db->prepare("UPDATE users 
            SET delivery_address=?, city=?, zip_code=?, delivery_instructions=?, updated_at=NOW() 
            WHERE id=?");
        
        return $stmt->execute([
            $data['delivery_address'],
            $data['city'],
            $data['zip_code'],
            $data['delivery_instructions'],
            $user_id
        ]);
    }
    
    // MODIFIED: Stripe version but EXACT SAME DATABASE ORDER as checkoutold.php
    public function processFullOrder($user_id, $order, $postData, $stripe_payment_intent_id = null) {
        $this->db->beginTransaction();
        
        try {
            $plan = $order['plan'];
            $selected_meals = $order['selected_meals'];
            $selected_meals_quantities = $order['selected_meals_quantities'] ?? [];
            $delivery_date = $postData['delivery_day'];
            
            // Calculate dates (EXACT copy from checkoutold.php)
            $start_date = $delivery_date;
            $billing_cycle = ($plan['plan_type'] ?? 'weekly') === 'monthly' ? 'monthly' : 'weekly';
            $next_billing_date = $billing_cycle === 'monthly'
                ? date('Y-m-d', strtotime('+1 month', strtotime($start_date)))
                : date('Y-m-d', strtotime('+1 week', strtotime($start_date)));
            
            $plan_price_value = CheckoutUtils::getPriceValue($plan['final_price']);
            
            // 1. Create subscription FIRST (EXACT copy from checkoutold.php)
            $subscription_data = [
                'user_id' => $user_id,
                'plan_id' => $plan['id'],
                'start_date' => $start_date,
                'next_billing_date' => $next_billing_date,
                'billing_cycle' => $billing_cycle,
                'total_amount' => $plan_price_value,
                'delivery_days' => json_encode([$delivery_date]),
                'preferred_time' => $postData['preferred_time'] ?? 'afternoon',
                'delivery_instructions' => CheckoutUtils::sanitizeInput($postData['delivery_instructions'] ?? '')
            ];
            
            $subscription_id = $this->createSubscription($subscription_data);
            
            // 2. Create payment SECOND with valid subscription_id
            $payment_data = [
                'subscription_id' => $subscription_id,  // NOW EXISTS!
                'user_id' => $user_id,
                'amount' => $plan_price_value,
                'start_date' => $start_date,
                'next_billing_date' => $next_billing_date,
                'description' => "Subscription " . CheckoutUtils::getPlanName($plan),
                'stripe_payment_intent_id' => $stripe_payment_intent_id
            ];
            
            $payment_result = $this->createPayment($payment_data);
            
            // 3. Create subscription menus (EXACT copy from checkoutold.php)
            $this->createSubscriptionMenus($subscription_id, $selected_meals, $start_date, $selected_meals_quantities);
            
            // 4. Update user profile (EXACT copy from checkoutold.php)
            $user_data = [
                'delivery_address' => CheckoutUtils::sanitizeInput($postData['delivery_address']),
                'city' => CheckoutUtils::sanitizeInput($postData['city']),
                'zip_code' => CheckoutUtils::sanitizeInput($postData['zip_code']),
                'delivery_instructions' => CheckoutUtils::sanitizeInput($postData['delivery_instructions'] ?? '')
            ];
            
            $this->updateUserProfile($user_id, $user_data);
            
            // 5. Process referrals (EXACT copy from checkoutold.php)
            try {
                $this->processReferralRewardWithoutTransaction($user_id, $subscription_id);
            } catch (Exception $e) {
                error_log("Referral processing error (non-fatal): " . $e->getMessage());
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'subscription_id' => $subscription_id,
                'transaction_id' => $payment_result['transaction_id']
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // EXACT copy from checkoutold.php
    private function processReferralRewardWithoutTransaction($userId, $subscriptionId) {
        try {
            $stmt = $this->db->prepare("SELECT email FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return false;
            }
            
            $stmt = $this->db->prepare("
                SELECT r.*, u.name as referrer_name, u.email as referrer_email
                FROM referrals r
                JOIN users u ON r.referrer_id = u.id
                WHERE r.referred_email = ? 
                AND r.status = 'pending'
                AND r.expires_at > NOW()
                LIMIT 1
            ");
            $stmt->execute([$user['email']]);
            $referral = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$referral) {
                return false;
            }
            
            $stmt = $this->db->prepare("
                UPDATE referrals 
                SET status = 'completed', 
                    referred_user_id = ?,
                    completed_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$userId, $referral['id']]);
            
            $stmt = $this->db->prepare("
                SELECT referral_credits, total_referrals, total_referral_earnings 
                FROM users 
                WHERE id = ?
            ");
            $stmt->execute([$referral['referrer_id']]);
            $referrer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $current_credits = $referrer['referral_credits'] ?? 0;
            $reward_amount = $referral['reward_amount'] ?? 10.00;
            $new_credits = $current_credits + $reward_amount;
            $new_total_referrals = ($referrer['total_referrals'] ?? 0) + 1;
            $new_total_earnings = ($referrer['total_referral_earnings'] ?? 0) + $reward_amount;
            
            $stmt = $this->db->prepare("
                UPDATE users 
                SET referral_credits = ?,
                    total_referrals = ?,
                    total_referral_earnings = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $new_credits,
                $new_total_referrals,
                $new_total_earnings,
                $referral['referrer_id']
            ]);
            
            error_log("Referral reward processed: User {$user['email']} referred by {$referral['referrer_name']}, reward: $" . $reward_amount);
            
            return [
                'success' => true,
                'referral_id' => $referral['id'],
                'referrer_id' => $referral['referrer_id'],
                'referrer_name' => $referral['referrer_name'],
                'reward_amount' => $reward_amount,
                'new_credits' => $new_credits
            ];
            
        } catch (Exception $e) {
            error_log("Referral processing error: " . $e->getMessage());
            throw $e;
        }
    }
}

// Main execution (EXACT copy from checkoutold.php but with Stripe)
try {
    $db = DatabaseConnection::getInstance();
    
    $user_id = $_SESSION['user_id'];
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $order = $_SESSION['checkout_data'] ?? null;

    $validation_errors = CheckoutDataManager::validateCheckoutData($order);
    if (!empty($validation_errors)) {
        error_log("Invalid checkout data for user: " . ($_SESSION['user_id'] ?? 'unknown'));
        $_SESSION['flash_message'] = "Please start your order from the beginning.";
        $_SESSION['flash_type'] = 'error';
        header("Location: subscribe.php");
        exit;
    }
    
    if (isset($order['selected_meals']) && !empty($order['selected_meals']) && 
        (!isset($order['meal_details']) || empty($order['meal_details']))) {
        
        $meal_details = CheckoutDataManager::populateMealDetails($db, $order['selected_meals']);
        $order['meal_details'] = $meal_details;
        $_SESSION['checkout_data'] = $order;
    }
    
    $plan = $order['plan'];
    $selected_meals = $order['selected_meals'] ?? [];
    $meal_details = $order['meal_details'] ?? [];
    $total_price = CheckoutUtils::getPriceValue($plan['final_price']);
    
    // Process form submission with Stripe - MODIFIED FOR STRIPE
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order'])) {
        
        $processor = new OrderProcessor($db);
        $errors = $processor->validateFormInput($_POST);
        
        if (empty($errors)) {
            try {
                $stripe_payment_intent_id = $_POST['stripe_payment_intent_id'] ?? null;
                
                if (!$stripe_payment_intent_id) {
                    throw new Exception("Payment processing failed. Please try again.");
                }
                
                $result = $processor->processFullOrder($user_id, $order, $_POST, $stripe_payment_intent_id);
                
                if ($result['success']) {
                    // CREATE ORDER NOTIFICATION (EXACT copy from checkoutold.php)
                    try {
                        require_once 'NotificationManager.php';
                        $notificationManager = new NotificationManager($db);
                        
                        $orderDetails = [
                            'plan_name' => CheckoutUtils::getPlanName($plan),
                            'total_amount' => CheckoutUtils::formatPrice($plan['final_price']),
                            'delivery_date' => $_POST['delivery_day'],
                            'transaction_id' => $result['transaction_id']
                        ];
                        
                        $notificationManager->createOrderNotification(
                            $user_id,
                            $result['subscription_id'],
                            'confirmed',
                            $orderDetails
                        );
                        
                        error_log("Order notification created for user: $user_id, subscription: {$result['subscription_id']}");
                        
                    } catch (Exception $e) {
                        error_log("Failed to create order notification: " . $e->getMessage());
                    }
                    
                    unset($_SESSION['checkout_data']);
                    $_SESSION['flash_message'] = "Order placed successfully! Thank you for choosing Somdul Table";
                    $_SESSION['flash_type'] = 'success';
                    $_SESSION['last_order_id'] = $result['subscription_id'];
                    $_SESSION['prevent_double_submit'] = time();
                    
                    // REDIRECT (EXACT copy from checkoutold.php)
                    header("Location: subscription-status.php?order=" . $result['subscription_id']);
                    exit;
                }
                
            } catch (Exception $e) {
                $errors[] = "An error occurred: " . $e->getMessage();
            }
        }
    }
    
} catch (Exception $e) {
    die("Application Error: " . $e->getMessage());
}

// Early exit if successful
if ($success) {
    exit;
}

// Include header
include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Somdul Table</title>
    <script src="https://js.stripe.com/v3/"></script>
    
    <style>
    /* All the same CSS from checkoutold.php */
    .container {
        max-width: 900px;
        margin: 0 auto;
        padding: 0 20px;
    }

    .main-content {
        padding-top: 2rem;
        min-height: calc(100vh - 200px);
    }

    .progress-container {
        background: var(--white);
        border-radius: var(--radius-lg);
        padding: 2rem;
        margin-bottom: 3rem;
        box-shadow: var(--shadow-soft);
        border: 1px solid var(--border-light);
    }

    .progress-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        max-width: 800px;
        margin: 0 auto;
        flex-wrap: wrap;
    }

    .progress-step {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.8rem 1.5rem;
        border-radius: 25px;
        font-weight: 600;
        font-size: 0.95rem;
        font-family: 'BaticaSans', sans-serif;
        background: var(--white);
        color: var(--text-gray);
        border: 2px solid var(--cream);
        transition: var(--transition);
        white-space: nowrap;
        flex: 1;
        justify-content: center;
        min-width: 140px;
        touch-action: manipulation;
    }

    .progress-step.active {
        background: var(--brown);
        color: var(--white);
        border-color: var(--brown);
        box-shadow: 0 4px 12px rgba(189, 147, 121, 0.3);
        transform: scale(1.02);
    }

    .progress-step.completed {
        background: var(--sage);
        color: var(--white);
        border-color: var(--sage);
    }

    .progress-arrow {
        color: var(--sage);
        font-size: 1.2rem;
        font-weight: 600;
        flex-shrink: 0;
        transition: var(--transition);
    }

    .title {
        font-size: 2.2rem;
        font-weight: 700;
        margin-bottom: 2rem;
        text-align: center;
        color: var(--brown);
        position: relative;
        font-family: 'BaticaSans', sans-serif;
    }

    .title i {
        color: var(--curry);
        margin-right: 0.5rem;
    }

    .section {
        background: var(--white);
        border-radius: var(--radius-lg);
        margin-bottom: 2rem;
        box-shadow: var(--shadow-soft);
        padding: 2rem;
        border: 1px solid var(--border-light);
        position: relative;
        overflow: hidden;
        touch-action: manipulation;
    }

    .section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--curry), var(--brown), var(--sage));
    }

    .label {
        font-weight: 700;
        color: var(--brown);
        margin-bottom: 1rem;
        font-size: 1.1rem;
        font-family: 'BaticaSans', sans-serif;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .plan-title {
        font-size: 1.2rem;
        color: var(--brown);
        font-weight: 600;
        margin-bottom: 0.5rem;
        font-family: 'BaticaSans', sans-serif;
    }

    .plan-price {
        color: var(--curry);
        font-size: 1.4rem;
        font-weight: 700;
        font-family: 'BaticaSans', sans-serif;
    }

    .meal-list {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .meal-list li {
        border-bottom: 1px solid var(--border-light);
        padding: 1rem 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: var(--transition);
        touch-action: manipulation;
    }

    .meal-list li:hover {
        background: var(--cream);
        margin: 0 -1rem;
        padding: 1rem;
        border-radius: var(--radius-md);
    }

    .meal-list li:last-child {
        border-bottom: none;
    }

    .meal-name {
        flex: 1;
        font-weight: 600;
        color: var(--brown);
        line-height: 1.4;
        font-family: 'BaticaSans', sans-serif;
    }

    .meal-name .quantity-indicator {
        color: var(--curry);
        font-weight: 700;
        margin-left: 0.5rem;
        font-size: 0.95rem;
    }

    .meal-price {
        color: var(--curry);
        font-weight: 600;
        margin-left: 1rem;
        font-family: 'BaticaSans', sans-serif;
    }

    .no-meals-message {
        text-align: center;
        padding: 2rem;
        color: var(--text-gray);
        font-style: italic;
        line-height: 1.6;
        font-family: 'BaticaSans', sans-serif;
    }

    .total {
        font-size: 1.5rem;
        color: var(--curry);
        font-weight: 700;
        margin: 2rem 0;
        text-align: center;
        padding: 1.5rem;
        background: linear-gradient(135deg, var(--cream), #f5f3f0);
        border-radius: var(--radius-lg);
        border: 2px solid rgba(189, 147, 121, 0.1);
        font-family: 'BaticaSans', sans-serif;
    }

    .address-input, .input {
        width: 100%;
        padding: 1rem 1.2rem;
        border-radius: var(--radius-lg);
        border: 2px solid var(--border-light);
        margin-bottom: 1.2rem;
        font-size: 1rem;
        font-family: 'BaticaSans', sans-serif;
        transition: var(--transition);
        background: var(--white);
        color: var(--text-dark);
        touch-action: manipulation;
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
    }

    .input:focus, .address-input:focus {
        border-color: var(--brown);
        outline: none;
        box-shadow: 0 0 15px rgba(189, 147, 121, 0.2);
        transform: translateY(-1px);
    }

    input[type="text"],
    input[type="email"],
    input[type="tel"],
    input[type="number"],
    textarea,
    select {
        font-size: 16px !important;
    }

    #card-element {
        padding: 15px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        background: var(--white);
        margin-bottom: 15px;
    }

    #card-element.StripeElement--focus {
        border-color: var(--brown);
    }

    #card-errors {
        color: #e74c3c;
        font-size: 14px;
        margin-top: 10px;
        display: none;
    }

    .btn {
        width: 100%;
        padding: 1.2rem 2rem;
        border-radius: 25px;
        background: var(--brown);
        color: var(--white);
        font-size: 1.1rem;
        font-weight: 700;
        border: none;
        cursor: pointer;
        transition: var(--transition);
        font-family: 'BaticaSans', sans-serif;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.8rem;
        box-shadow: var(--shadow-soft);
        touch-action: manipulation;
        min-height: 56px;
        position: relative;
        overflow: hidden;
    }

    .btn:hover {
        background: #a8855f;
        transform: translateY(-2px);
        box-shadow: var(--shadow-medium);
    }

    .btn:active {
        transform: translateY(0);
        box-shadow: 0 2px 8px rgba(189, 147, 121, 0.3);
    }

    .btn:disabled {
        opacity: 0.7;
        cursor: not-allowed;
        transform: none;
        background: var(--text-gray);
    }

    .custom-calendar {
        background: var(--white);
        border: 2px solid var(--border-light);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        margin-bottom: 1rem;
        box-shadow: var(--shadow-soft);
        max-width: 400px;
        margin-left: auto;
        margin-right: auto;
        touch-action: manipulation;
    }

    .calendar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid var(--cream);
    }

    .calendar-nav {
        background: var(--cream);
        border: none;
        border-radius: 50%;
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: var(--transition);
        color: var(--brown);
        font-size: 0.85rem;
        touch-action: manipulation;
        flex-shrink: 0;
    }

    .calendar-nav:hover {
        background: var(--brown);
        color: var(--white);
        transform: scale(1.05);
    }

    .calendar-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--brown);
        text-align: center;
        flex: 1;
        margin: 0 0.8rem;
        font-family: 'BaticaSans', sans-serif;
    }

    .calendar-weekdays {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 0.3rem;
        margin-bottom: 0.5rem;
    }

    .weekday {
        text-align: center;
        font-weight: 600;
        color: var(--text-gray);
        padding: 0.3rem;
        font-size: 0.8rem;
        font-family: 'BaticaSans', sans-serif;
    }

    .weekday.highlight {
        color: var(--brown);
        font-weight: 700;
    }

    .calendar-days {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 0.3rem;
    }

    .calendar-day {
        aspect-ratio: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: var(--radius-md);
        cursor: pointer;
        transition: var(--transition);
        font-weight: 600;
        position: relative;
        background: var(--white);
        border: 1px solid transparent;
        min-height: 32px;
        user-select: none;
        touch-action: manipulation;
        font-size: 0.8rem;
        font-family: 'BaticaSans', sans-serif;
    }

    .calendar-day.other-month {
        color: var(--text-gray);
        opacity: 0.3;
        cursor: not-allowed;
        pointer-events: none;
    }

    .calendar-day.disabled {
        color: var(--text-gray);
        opacity: 0.4;
        cursor: not-allowed;
        background: #f8f8f8;
        pointer-events: none;
    }

    .calendar-day.available {
        color: var(--brown);
        background: var(--cream);
        border-color: var(--brown);
        font-weight: 700;
    }

    .calendar-day.available:hover {
        background: var(--brown);
        color: var(--white);
        transform: scale(1.05);
        box-shadow: 0 4px 12px rgba(189, 147, 121, 0.3);
    }

    .calendar-day.selected {
        background: var(--brown);
        color: var(--white);
        transform: scale(1.02);
        box-shadow: 0 4px 16px rgba(189, 147, 121, 0.4);
        border-color: var(--sage);
    }

    .error {
        background: linear-gradient(135deg, #ffebee, #fce4ec);
        color: #d32f2f;
        border: 2px solid #ffcdd2;
        padding: 1.5rem;
        border-radius: var(--radius-lg);
        margin-bottom: 2rem;
        box-shadow: 0 2px 8px rgba(231, 76, 60, 0.1);
        font-family: 'BaticaSans', sans-serif;
    }

    .weekend-info {
        background: linear-gradient(135deg, #e8f5e8, #f0f8f0);
        border: 2px solid var(--sage);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        margin-bottom: 2rem;
        color: var(--text-dark);
        font-size: 0.95rem;
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        line-height: 1.5;
        font-family: 'BaticaSans', sans-serif;
    }

    .weekend-info i {
        color: var(--sage);
        font-size: 1.5rem;
        flex-shrink: 0;
        margin-top: 0.1rem;
    }

    .weekend-info-content {
        flex: 1;
    }

    .weekend-info strong {
        color: var(--brown);
    }

    .date-error-message, .date-success-message {
        margin-top: 1rem;
        padding: 1rem;
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 600;
        transition: var(--transition);
        line-height: 1.4;
        font-family: 'BaticaSans', sans-serif;
    }

    .date-success-message {
        background: linear-gradient(135deg, #e8f5e8, #f0f8f0);
        color: #2e7d32;
        border: 2px solid var(--sage);
    }

    @media (max-width: 768px) {
        .progress-container {
            padding: 1.5rem 1rem;
        }
        
        .progress-bar {
            flex-direction: column;
        }
        
        .section {
            padding: 1.5rem 1rem;
        }
    }
    </style>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>

<body class="has-header">
    <main class="main-content">
        <div class="container">
            <!-- Progress Bar -->
            <div class="progress-container">
                <div class="progress-bar">
                    <div class="progress-step completed">
                        <i class="fas fa-check-circle"></i>
                        <span>Choose Package</span>
                    </div>
                    <span class="progress-arrow">→</span>
                    <div class="progress-step completed">
                        <i class="fas fa-check-circle"></i>
                        <span>Select Menu</span>
                    </div>
                    <span class="progress-arrow">→</span>
                    <div class="progress-step active">
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

            <div class="title"><i class="fas fa-wallet"></i> Review and Pay</div>

            <?php if (!empty($errors)): ?>
                <div class="error">
                    <ul>
                        <?php foreach ($errors as $err): ?>
                            <li><?php echo htmlspecialchars($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Plan Summary -->
            <div class="section plan-summary">
                <div class="label"><i class="fas fa-box"></i> Selected Package</div>
                <div class="plan-title"><?php echo htmlspecialchars(CheckoutUtils::getPlanName($plan)); ?> (<?php echo $plan['meals_per_week']; ?> meals)</div>
                <div class="plan-price">$<?php echo CheckoutUtils::formatPrice($plan['final_price']); ?></div>
            </div>

            <!-- Meals Summary -->
            <div class="section meals-summary">
                <div class="label"><i class="fas fa-utensils"></i> Selected Meals</div>
                <?php if (!empty($selected_meals) && !empty($meal_details)): ?>
                    <ul class="meal-list">
                        <?php 
                        $selected_quantities = $order['selected_meals_quantities'] ?? [];
                        $meal_counts = [];
                        if (!empty($selected_quantities)) {
                            $meal_counts = $selected_quantities;
                        } else {
                            foreach ($selected_meals as $meal_id) {
                                $meal_counts[$meal_id] = ($meal_counts[$meal_id] ?? 0) + 1;
                            }
                        }
                        
                        foreach ($meal_counts as $meal_id => $quantity): ?>
                            <?php $meal = $meal_details[$meal_id] ?? null; if (!$meal) continue; ?>
                            <li>
                                <div class="meal-name">
                                    <?php echo htmlspecialchars(CheckoutUtils::getMenuName($meal)); ?>
                                    <?php if ($quantity > 1): ?>
                                        <span class="quantity-indicator">× <?php echo $quantity; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="meal-price">Included</div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="no-meals-message">
                        <i class="fas fa-exclamation-triangle" style="color: var(--warning); margin-right: 0.5rem;"></i>
                        No meals selected or meal details unavailable.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Order Form -->
            <form method="POST" action="" id="checkout-form">
                <div class="section">
                    <div class="label"><i class="fas fa-map-marker-alt"></i> Delivery Address</div>
                    <input type="text" class="address-input" name="delivery_address" required
                           value="<?php echo htmlspecialchars($user['delivery_address'] ?? ''); ?>" placeholder="Enter your address">
                    <input type="text" class="address-input" name="city" required
                           value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>" placeholder="City, State">
                    <input type="text" class="address-input" name="zip_code" required maxlength="5" pattern="[0-9]{5}"
                           value="<?php echo htmlspecialchars($user['zip_code'] ?? ''); ?>" placeholder="ZIP Code">
                    <textarea name="delivery_instructions" class="address-input" rows="2" placeholder="Special delivery instructions (optional)"><?php echo htmlspecialchars($user['delivery_instructions'] ?? ''); ?></textarea>
                </div>

                <div class="section">
                    <div class="label"><i class="fas fa-calendar-weekend"></i> Select Your Delivery Day</div>
                    
                    <div class="weekend-info">
                        <i class="fas fa-info-circle"></i>
                        <div class="weekend-info-content">
                            <strong>Delivery Schedule:</strong> We deliver fresh Thai meals on <strong>Wednesdays and Saturdays only</strong>. Please select your preferred delivery date below.
                        </div>
                    </div>
                    
                    <div class="date-selection-container">
                        <div class="custom-calendar">
                            <div class="calendar-header">
                                <button type="button" class="calendar-nav" id="prev-month">
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <div class="calendar-title" id="calendar-title"></div>
                                <button type="button" class="calendar-nav" id="next-month">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>
                            
                            <div class="calendar-weekdays">
                                <div class="weekday">Sun</div>
                                <div class="weekday">Mon</div>
                                <div class="weekday">Tue</div>
                                <div class="weekday highlight">Wed</div>
                                <div class="weekday">Thu</div>
                                <div class="weekday">Fri</div>
                                <div class="weekday highlight">Sat</div>
                            </div>
                            
                            <div class="calendar-days" id="calendar-days"></div>
                        </div>
                        
                        <input type="hidden" name="delivery_day" id="delivery_date" required>
                        
                        <div id="date-success" class="date-success-message" style="display: none;">
                            <i class="fas fa-check-circle"></i>
                            <span id="selected-day-name"></span> delivery selected!
                        </div>
                    </div>
                    
                    <div class="label" style="margin-top:2rem;"><i class="fas fa-clock"></i> Preferred Delivery Time</div>
                    <select name="preferred_time" class="address-input" required>
                        <option value="09:00-12:00">Morning (9:00 AM - 12:00 PM)</option>
                        <option value="12:00-15:00">Lunch (12:00 PM - 3:00 PM)</option>
                        <option value="15:00-18:00" selected>Afternoon (3:00 PM - 6:00 PM)</option>
                        <option value="18:00-21:00">Evening (6:00 PM - 9:00 PM)</option>
                    </select>
                </div>

                <!-- Payment with Stripe -->
                <div class="section">
                    <div class="label"><i class="fas fa-credit-card"></i> Payment Information</div>
                    
                    <div id="card-element"></div>
                    <div id="card-errors" role="alert"></div>
                    
                    <div class="total">Total: $<?php echo CheckoutUtils::formatPrice($plan['final_price']); ?></div>
                    
                    <input type="hidden" name="submit_order" value="1">
                    <input type="hidden" name="stripe_payment_intent_id" id="stripe_payment_intent_id">
                    <input type="hidden" name="submit_timestamp" value="<?php echo time(); ?>">
                    
                    <button type="submit" id="submit-btn" class="btn">
                        <span id="submit-text"><i class="fas fa-lock"></i> Complete Order & Pay $<?php echo CheckoutUtils::formatPrice($plan['final_price']); ?></span>
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script>
        const stripe = Stripe('<?php echo $stripe_publishable_key; ?>');
        const elements = stripe.elements();
        
        const style = {
            base: {
                color: '#2c3e50',
                fontFamily: 'BaticaSans, -apple-system, BlinkMacSystemFont, sans-serif',
                fontSmoothing: 'antialiased',
                fontSize: '16px',
                '::placeholder': {
                    color: '#7f8c8d'
                }
            },
            invalid: {
                color: '#e74c3c',
                iconColor: '#e74c3c'
            }
        };

        const cardElement = elements.create('card', {style: style});
        cardElement.mount('#card-element');

        cardElement.on('change', ({error}) => {
            const displayError = document.getElementById('card-errors');
            if (error) {
                displayError.textContent = error.message;
                displayError.style.display = 'block';
            } else {
                displayError.textContent = '';
                displayError.style.display = 'none';
            }
        });

        // Calendar (simplified version)
        class DeliveryCalendar {
            constructor() {
                this.currentDate = new Date();
                this.selectedDate = null;
                this.calendarTitle = document.getElementById('calendar-title');
                this.calendarDays = document.getElementById('calendar-days');
                this.deliveryDateInput = document.getElementById('delivery_date');
                this.dateSuccess = document.getElementById('date-success');
                this.selectedDayName = document.getElementById('selected-day-name');
                
                this.init();
            }
            
            init() {
                this.renderCalendar();
                this.attachEventListeners();
            }
            
            attachEventListeners() {
                document.getElementById('prev-month')?.addEventListener('click', () => {
                    this.currentDate.setMonth(this.currentDate.getMonth() - 1);
                    this.renderCalendar();
                });
                
                document.getElementById('next-month')?.addEventListener('click', () => {
                    this.currentDate.setMonth(this.currentDate.getMonth() + 1);
                    this.renderCalendar();
                });
            }
            
            renderCalendar() {
                const year = this.currentDate.getFullYear();
                const month = this.currentDate.getMonth();
                
                const monthNames = [
                    'January', 'February', 'March', 'April', 'May', 'June',
                    'July', 'August', 'September', 'October', 'November', 'December'
                ];
                this.calendarTitle.textContent = `${monthNames[month]} ${year}`;
                
                this.calendarDays.innerHTML = '';
                
                const firstDay = new Date(year, month, 1);
                const lastDay = new Date(year, month + 1, 0);
                const firstDayOfWeek = firstDay.getDay();
                const daysInMonth = lastDay.getDate();
                
                const today = new Date();
                const maxDate = new Date();
                maxDate.setDate(maxDate.getDate() + 28);
                
                // Previous month days
                const prevMonth = new Date(year, month - 1, 0);
                const daysInPrevMonth = prevMonth.getDate();
                
                for (let i = firstDayOfWeek - 1; i >= 0; i--) {
                    const dayNum = daysInPrevMonth - i;
                    const dayElement = this.createDayElement(dayNum, 'other-month');
                    this.calendarDays.appendChild(dayElement);
                }
                
                // Current month days
                for (let day = 1; day <= daysInMonth; day++) {
                    const currentDayDate = new Date(year, month, day);
                    const dayOfWeek = currentDayDate.getDay();
                    
                    let dayClass = '';
                    let isClickable = false;
                    
                    if (dayOfWeek === 3 || dayOfWeek === 6) { // Wed or Sat
                        if (currentDayDate >= today && currentDayDate <= maxDate) {
                            dayClass = 'available';
                            isClickable = true;
                        } else {
                            dayClass = 'disabled';
                        }
                    } else {
                        dayClass = 'disabled';
                    }
                    
                    if (this.selectedDate && this.isSameDate(currentDayDate, this.selectedDate)) {
                        dayClass += ' selected';
                    }
                    
                    const dayElement = this.createDayElement(day, dayClass, isClickable, currentDayDate);
                    this.calendarDays.appendChild(dayElement);
                }
                
                // Next month days
                const totalCells = this.calendarDays.children.length;
                const remainingCells = 42 - totalCells;
                
                for (let day = 1; day <= remainingCells && day <= 14; day++) {
                    const dayElement = this.createDayElement(day, 'other-month');
                    this.calendarDays.appendChild(dayElement);
                }
            }
            
            createDayElement(dayNum, className = '', isClickable = false, date = null) {
                const dayElement = document.createElement('div');
                dayElement.className = `calendar-day ${className}`;
                dayElement.textContent = dayNum;
                
                if (isClickable && date) {
                    dayElement.addEventListener('click', () => {
                        this.selectDate(date, dayElement);
                    });
                }
                
                return dayElement;
            }
            
            selectDate(date, element) {
                const previousSelected = this.calendarDays.querySelector('.calendar-day.selected');
                if (previousSelected) {
                    previousSelected.classList.remove('selected');
                }
                
                element.classList.add('selected');
                this.selectedDate = new Date(date);
                
                const formattedDate = this.formatDateForInput(date);
                this.deliveryDateInput.value = formattedDate;
                
                const dayOfWeek = date.getDay();
                const dayName = dayOfWeek === 3 ? 'Wednesday' : 'Saturday';
                const formattedDisplay = this.formatDateForDisplay(date);
                
                this.selectedDayName.textContent = `${dayName}, ${formattedDisplay}`;
                this.dateSuccess.style.display = 'flex';
            }
            
            formatDateForInput(date) {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            }
            
            formatDateForDisplay(date) {
                const monthNames = [
                    'January', 'February', 'March', 'April', 'May', 'June',
                    'July', 'August', 'September', 'October', 'November', 'December'
                ];
                return `${monthNames[date.getMonth()]} ${date.getDate()}, ${date.getFullYear()}`;
            }
            
            isSameDate(date1, date2) {
                return date1.getFullYear() === date2.getFullYear() &&
                       date1.getMonth() === date2.getMonth() &&
                       date1.getDate() === date2.getDate();
            }
        }

        // Initialize calendar
        const calendar = new DeliveryCalendar();

        // Form submission
        const form = document.getElementById('checkout-form');
        const submitBtn = document.getElementById('submit-btn');
        const submitText = document.getElementById('submit-text');

        async function handleFormSubmission(event) {
            event.preventDefault();
            
            const deliveryDate = document.getElementById('delivery_date').value;
            if (!deliveryDate) {
                alert('Please select a delivery date');
                return;
            }

            submitBtn.disabled = true;
            submitText.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing Payment...';

            try {
                const totalAmount = <?php echo $total_price; ?>;

                // Create payment intent
                const response = await fetch('ajax/create_payment_intent.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        amount: totalAmount,
                        currency: 'usd'
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
                            address: {
                                line1: document.querySelector('input[name="delivery_address"]').value,
                                city: document.querySelector('input[name="city"]').value,
                                postal_code: document.querySelector('input[name="zip_code"]').value,
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
                        submitText.innerHTML = '<i class="fas fa-check"></i> Payment Successful - Completing Order...';
                        
                        // Submit form normally for PHP redirect
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
                submitText.innerHTML = '<i class="fas fa-lock"></i> Complete Order & Pay $<?php echo CheckoutUtils::formatPrice($plan['final_price']); ?>';
            }
        }

        form.addEventListener('submit', handleFormSubmission);
    </script>
</body>
</html>