<?php
/**
 * Somdul Table - Create Product Payment Intent for Stripe
 * File: ajax/create_product_payment_intent.php
 * Description: Handle Stripe payment intent creation for product orders
 */

header('Content-Type: application/json');
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Load Stripe PHP library
    require_once '../vendor/autoload.php';
    
    // Load database configuration
    require_once '../config/database.php';
    
    // Load Stripe configuration
    $stripe_config = require_once '../stripe_config.php';
    $environment = $stripe_config['environment'];
    $stripe_secret_key = $stripe_config[$environment]['secret_key'];
    
    // Initialize Stripe
    \Stripe\Stripe::setApiKey($stripe_secret_key);
    
    // Get database connection
    try {
        $database = new Database();
        $pdo = $database->getConnection();
    } catch (Exception $e) {
        // Fallback connections
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
            throw new Exception("Database connection failed");
        }
    }
    
    // Generate UUID function
    function generateUUID() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    // Get POST data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Invalid request data');
    }
    
    // Validate required fields
    $amount = floatval($data['amount'] ?? 0);
    $currency = strtolower($data['currency'] ?? 'usd');
    $description = $data['description'] ?? 'Product Order';
    $user_id = $_SESSION['user_id'] ?? 'guest';
    
    if ($amount <= 0) {
        throw new Exception('Invalid amount');
    }
    
    // Convert to cents for Stripe
    $stripe_amount = (int)round($amount * 100);
    
    // Validate currency
    $allowed_currencies = ['usd', 'eur', 'gbp'];
    if (!in_array($currency, $allowed_currencies)) {
        throw new Exception('Unsupported currency. Allowed: ' . implode(', ', $allowed_currencies));
    }
    
    // Generate payment ID
    $payment_id = generateUUID();
    $transaction_id = 'PRD-' . date('Ymd-His') . '-' . substr($payment_id, 0, 6);
    
    error_log("Creating Product Payment Intent: Amount={$stripe_amount}, Currency={$currency}");
    
    // Create Stripe Payment Intent
    $payment_intent = \Stripe\PaymentIntent::create([
        'amount' => $stripe_amount,
        'currency' => $currency,
        'description' => $description,
        'metadata' => [
            'payment_id' => $payment_id,
            'transaction_id' => $transaction_id,
            'user_id' => $user_id,
            'order_type' => 'product'
        ],
        'automatic_payment_methods' => [
            'enabled' => true,
        ],
    ]);
    
    error_log("Stripe Payment Intent created: " . $payment_intent->id);
    
    // Return success response
    $response = [
        'success' => true,
        'payment_intent' => [
            'id' => $payment_intent->id,
            'client_secret' => $payment_intent->client_secret,
            'amount' => $payment_intent->amount,
            'currency' => $payment_intent->currency,
            'status' => $payment_intent->status
        ],
        'message' => 'Payment intent created successfully'
    ];
    
    echo json_encode($response);
    
} catch (\Stripe\Exception\CardException $e) {
    error_log("Stripe Card Exception: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'card_declined',
        'message' => $e->getError()->message
    ]);
    
} catch (\Stripe\Exception\RateLimitException $e) {
    error_log("Stripe Rate Limit Exception: " . $e->getMessage());
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'rate_limit',
        'message' => 'Too many requests. Please try again later.'
    ]);
    
} catch (\Stripe\Exception\InvalidRequestException $e) {
    error_log("Stripe Invalid Request Exception: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'invalid_request',
        'message' => $e->getMessage()
    ]);
    
} catch (\Stripe\Exception\AuthenticationException $e) {
    error_log("Stripe Authentication Exception: " . $e->getMessage());
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'authentication_failed',
        'message' => 'Stripe authentication failed'
    ]);
    
} catch (\Stripe\Exception\ApiConnectionException $e) {
    error_log("Stripe API Connection Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'network_error',
        'message' => 'Network error. Please try again.'
    ]);
    
} catch (\Stripe\Exception\ApiErrorException $e) {
    error_log("Stripe API Error Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'api_error',
        'message' => 'Payment processing error. Please try again.'
    ]);
    
} catch (Exception $e) {
    error_log("General Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'general_error',
        'message' => $e->getMessage()
    ]);
}
?>