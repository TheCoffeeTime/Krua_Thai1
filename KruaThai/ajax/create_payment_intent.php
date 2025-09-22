<?php
/**
 * Somdul Table - Create Payment Intent for Stripe (FIXED VERSION)
 * File: ajax/create_payment_intent.php  
 * Description: Handle Stripe payment intent creation and database integration
 */

header('Content-Type: application/json');
session_start();

// Error reporting for testing
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Load Stripe PHP library - FIXED PATH
    require_once '../vendor/autoload.php';
    
    // Load database configuration - FIXED PATH
    require_once '../config/database.php';
    
    // Load Stripe configuration
    $stripe_config = require_once '../stripe_config.php';
    $environment = $stripe_config['environment'];
    $stripe_secret_key = $stripe_config[$environment]['secret_key'];
    
    // Initialize Stripe
    \Stripe\Stripe::setApiKey($stripe_secret_key);
    
    // Get database connection - FIXED CONNECTION METHOD
    try {
        $database = new Database();
        $pdo = $database->getConnection();
    } catch (Exception $e) {
        // Fallback connections like in checkoutold.php
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
    
    // Log payment status changes
    function logPaymentStatusChange($pdo, $payment_id, $old_status, $new_status, $user_id, $notes = '') {
        try {
            // Check if payment_status_log table exists first
            $stmt = $pdo->prepare("SHOW TABLES LIKE 'payment_status_log'");
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO payment_status_log (payment_id, old_status, new_status, changed_by, notes, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                return $stmt->execute([$payment_id, $old_status, $new_status, $user_id, $notes]);
            }
            return true; // Skip if table doesn't exist
        } catch (Exception $e) {
            error_log("Failed to log payment status change: " . $e->getMessage());
            return false;
        }
    }
    
    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // DEBUG: Log the input data
    error_log("Payment Intent Input: " . json_encode($input));
    
    // Validate required fields
    $required_fields = ['amount', 'currency'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            throw new Exception("Missing required field: {$field}");
        }
    }
    
    // Extract and validate input data
    $amount = floatval($input['amount']);
    $currency = strtolower(trim($input['currency']));
    $description = $input['description'] ?? 'Somdul Table Order Payment';
    $subscription_id = $input['subscription_id'] ?? null;
    $user_id = $_SESSION['user_id'] ?? $input['user_id'] ?? null;
    
    // Convert amount to cents for Stripe (Stripe uses cents)
    $stripe_amount = intval($amount * 100);
    
    // Validate amount
    if ($amount <= 0) {
        throw new Exception('Amount must be greater than 0');
    }
    
    // Validate currency
    $allowed_currencies = ['usd', 'thb', 'eur', 'gbp'];
    if (!in_array($currency, $allowed_currencies)) {
        throw new Exception('Invalid currency. Allowed: ' . implode(', ', $allowed_currencies));
    }
    
    // Generate payment ID
    $payment_id = generateUUID();
    $transaction_id = 'TXN-' . date('Ymd-His') . '-' . substr($payment_id, 0, 6);
    
    // DEBUG: Log before Stripe API call
    error_log("Creating Stripe Payment Intent: Amount={$stripe_amount}, Currency={$currency}");
    
    // Create Stripe Payment Intent
    $payment_intent = \Stripe\PaymentIntent::create([
        'amount' => $stripe_amount,
        'currency' => $currency,
        'description' => $description,
        'metadata' => [
            'payment_id' => $payment_id,
            'transaction_id' => $transaction_id,
            'user_id' => $user_id,
            'subscription_id' => $subscription_id
        ],
        'automatic_payment_methods' => [
            'enabled' => true,
        ],
    ]);
    
    // DEBUG: Log Stripe success
    error_log("Stripe Payment Intent created: " . $payment_intent->id);
    
    // Return success response - NO DATABASE PAYMENT RECORD CREATED
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
    
    // DEBUG: Log final response
    error_log("Returning success response: " . json_encode($response));
    
    echo json_encode($response);
    
} catch (\Stripe\Exception\CardException $e) {
    // Card was declined
    error_log("Stripe Card Exception: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'card_declined',
        'message' => $e->getError()->message
    ]);
    
} catch (\Stripe\Exception\RateLimitException $e) {
    // Too many requests made to the API too quickly
    error_log("Stripe Rate Limit Exception: " . $e->getMessage());
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'rate_limit',
        'message' => 'Too many requests. Please try again later.'
    ]);
    
} catch (\Stripe\Exception\InvalidRequestException $e) {
    // Invalid parameters were supplied to Stripe's API
    error_log("Stripe Invalid Request Exception: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'invalid_request',
        'message' => $e->getMessage()
    ]);
    
} catch (\Stripe\Exception\AuthenticationException $e) {
    // Authentication with Stripe's API failed
    error_log("Stripe Authentication Exception: " . $e->getMessage());
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'authentication_failed',
        'message' => 'Stripe authentication failed'
    ]);
    
} catch (\Stripe\Exception\ApiConnectionException $e) {
    // Network communication with Stripe failed
    error_log("Stripe API Connection Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'network_error',
        'message' => 'Network error. Please try again.'
    ]);
    
} catch (\Stripe\Exception\ApiErrorException $e) {
    // Display a very generic error to the user
    error_log("Stripe API Error Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'api_error',
        'message' => 'Payment processing error. Please try again.'
    ]);
    
} catch (Exception $e) {
    // General error
    error_log("General Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'general_error',
        'message' => $e->getMessage()
    ]);
}
?>