<?php
/**
 * Somdul Table - Minimal Referral Page
 * File: referral_minimal.php
 * Ultra simple referral form - just the essentials
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Database connection
try {
    require_once 'config/database.php';
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
        die("Database connection failed");
    }
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['friend_email'])) {
    
    $friend_email = trim($_POST['friend_email']);
    
    // Basic validation
    if (empty($friend_email)) {
        $message = 'Please enter an email address.';
        $message_type = 'error';
    } elseif (!filter_var($friend_email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $message_type = 'error';
    } else {
        
        try {
            // Get user email to check self-referral
            $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $message = 'User not found.';
                $message_type = 'error';
            } elseif (strtolower($user['email']) === strtolower($friend_email)) {
                $message = 'You cannot refer yourself.';
                $message_type = 'error';
            } else {
                // Check if already referred
                $stmt = $pdo->prepare("SELECT id FROM referrals WHERE referrer_id = ? AND referred_email = ?");
                $stmt->execute([$user_id, $friend_email]);
                
                if ($stmt->fetch()) {
                    $message = 'You have already referred this email address.';
                    $message_type = 'error';
                } else {
                    // Check if email is already a user
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                    $stmt->execute([$friend_email]);
                    
                    if ($stmt->fetch()) {
                        $message = 'This person is already a member.';
                        $message_type = 'error';
                    } else {
                        // Create referral
                        $referral_id = uniqid('ref_', true);
                        $referral_code = 'REF' . strtoupper(substr(uniqid(), -6));
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO referrals 
                            (id, referrer_id, referred_email, referral_code, status, reward_amount) 
                            VALUES (?, ?, ?, ?, 'pending', 10.00)
                        ");
                        
                        if ($stmt->execute([$referral_id, $user_id, $friend_email, $referral_code])) {
                            $message = 'Referral sent successfully!';
                            $message_type = 'success';
                        } else {
                            $message = 'Failed to create referral.';
                            $message_type = 'error';
                        }
                    }
                }
            }
            
        } catch (Exception $e) {
            error_log('Referral error: ' . $e->getMessage());
            $message = 'Database error occurred.';
            $message_type = 'error';
        }
    }
}

// Include header for consistent styling
include 'header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refer a Friend | Somdul Table</title>
    
    <style>
    .container {
        max-width: 600px;
        margin: 0 auto;
        padding: 2rem 1rem;
    }

    .referral-form-section {
        background: var(--white);
        border-radius: 16px;
        padding: 3rem;
        box-shadow: 0 4px 12px rgba(189, 147, 121, 0.15);
        border: 2px solid var(--cream);
        text-align: center;
    }

    .form-title {
        font-size: 2rem;
        font-weight: 700;
        color: var(--brown);
        margin-bottom: 1rem;
        font-family: 'BaticaSans', sans-serif;
    }

    .form-subtitle {
        color: var(--text-gray);
        margin-bottom: 2rem;
        line-height: 1.6;
        font-family: 'BaticaSans', sans-serif;
    }

    .form-input {
        width: 100%;
        padding: 1rem;
        border-radius: 12px;
        border: 2px solid var(--border-light);
        font-size: 1rem;
        font-family: 'BaticaSans', sans-serif;
        margin-bottom: 1.5rem;
        box-sizing: border-box;
    }

    .form-input:focus {
        border-color: var(--brown);
        outline: none;
        box-shadow: 0 0 15px rgba(189, 147, 121, 0.2);
    }

    .submit-btn {
        width: 100%;
        background: var(--brown);
        color: var(--white);
        border: none;
        padding: 1rem 2rem;
        border-radius: 50px;
        font-size: 1.1rem;
        font-weight: 600;
        font-family: 'BaticaSans', sans-serif;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .submit-btn:hover {
        background: #a8855f;
        transform: translateY(-2px);
    }

    .message {
        padding: 1rem;
        border-radius: 10px;
        margin-bottom: 1.5rem;
        font-family: 'BaticaSans', sans-serif;
        font-weight: 500;
    }

    .message.success {
        background: #e8f5e8;
        color: #2e7d32;
        border: 2px solid #4caf50;
    }

    .message.error {
        background: #ffebee;
        color: #d32f2f;
        border: 2px solid #f44336;
    }

    /* Mobile responsive */
    @media (max-width: 768px) {
        .container {
            padding: 1rem 0.5rem;
        }
        
        .referral-form-section {
            padding: 2rem 1.5rem;
        }
        
        .form-title {
            font-size: 1.5rem;
        }
    }
    </style>
</head>

<body class="has-header">
    <div class="container">
        <div class="referral-form-section">
            <h1 class="form-title">Refer a Friend</h1>
            <p class="form-subtitle">
                Enter your friend's email address to send them an invitation to try Somdul Table.
            </p>
            
            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="email" 
                       name="friend_email" 
                       class="form-input" 
                       placeholder="Enter your friend's email address" 
                       required>
                
                <button type="submit" class="submit-btn">
                    Send Invitation
                </button>
            </form>
        </div>
    </div>
</body>
</html>