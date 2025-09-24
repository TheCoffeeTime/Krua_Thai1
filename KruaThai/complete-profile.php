<?php
/**
 * Somdul Table - Complete Profile Page
 * File: complete-profile.php
 * Description: Profile completion form for Facebook/Google users and incomplete registrations
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'classes/User.php';

// ADD THIS SESSION VALIDATION FUNCTION HERE:
/**
 * Validate and refresh user session data
 * Ensures session is consistent with database and user still exists
 */
function validateAndRefreshSession($db, $user_id) {
    try {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = :user_id AND status = 'active'");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            // User doesn't exist or is inactive - destroy session
            error_log("Session validation failed - user not found or inactive: " . $user_id);
            session_destroy();
            header('Location: login.php?error=session_expired');
            exit();
        }
        
        // Refresh session data with current database values
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
        $_SESSION['user_role'] = $user['role'] ?? 'customer';
        $_SESSION['profile_complete'] = (bool)$user['profile_complete'];
        
        error_log("Session validated and refreshed for user: " . $user['id']);
        return $user;
        
    } catch (Exception $e) {
        error_log("Session validation error: " . $e->getMessage());
        session_destroy();
        header('Location: login.php?error=technical_error');
        exit();
    }
}

$database = new Database();
$db = $database->getConnection();

$errors = [];
$success_message = '';
$form_data = [];
$current_step = $_GET['step'] ?? 'email';
$is_facebook_user = isset($_GET['facebook']) && $_GET['facebook'] == '1';
$is_google_user = isset($_GET['google']) && $_GET['google'] == '1';
$is_returning_user = isset($_GET['returning']) && $_GET['returning'] == '1';

// Get current user data
$stmt = $db->prepare("SELECT * FROM users WHERE id = :user_id");
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_data) {
    header('Location: login.php');
    exit();
}

// Determine OAuth provider from database if not specified in URL
if (!$is_facebook_user && !$is_google_user) {
    if (!empty($user_data['facebook_id'])) {
        $is_facebook_user = true;
    } elseif (!empty($user_data['google_id'])) {
        $is_google_user = true;
    }
}

// Check if profile is already complete
$is_profile_complete = !empty($user_data['phone']) && 
                      !empty($user_data['delivery_address']) && 
                      !empty($user_data['zip_code']) &&
                      !empty($user_data['email']) &&
                      !str_contains($user_data['email'], '@temp.somdultable.com');

if ($is_profile_complete && !$is_returning_user) {
    header('Location: dashboard.php');
    exit();
}

// Determine steps needed based on missing data
$steps_needed = [];

// Check if email is missing or is a temp email
if (empty($user_data['email']) || str_contains($user_data['email'], '@temp.somdultable.com')) {
    $steps_needed[] = 'email';
}

// Check if phone is missing
if (empty($user_data['phone'])) {
    $steps_needed[] = 'contact';
}

// Check if address information is missing
if (empty($user_data['delivery_address']) || empty($user_data['zip_code'])) {
    $steps_needed[] = 'address';
}

// Check if preferences need to be set
// Always include preferences for new social media users or if not properly set
if (empty($user_data['spice_level']) || 
    $user_data['spice_level'] === 'medium' || 
    (!empty($user_data['facebook_id']) && empty($user_data['dietary_preferences'])) ||
    (!empty($user_data['google_id']) && empty($user_data['dietary_preferences']))) {
    $steps_needed[] = 'preferences';
}

// If no steps needed, mark profile as complete and redirect
if (empty($steps_needed)) {
    $stmt = $db->prepare("UPDATE users SET profile_complete = 1, updated_at = NOW() WHERE id = :user_id");
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    
    header('Location: dashboard.php?welcome=1');
    exit();
}

// Set current step if not provided or invalid
if (!in_array($current_step, $steps_needed)) {
    $current_step = $steps_needed[0];
}

// Get current step index for progress tracking
$current_step_index = array_search($current_step, $steps_needed);
$total_steps = count($steps_needed);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    switch ($current_step) {
        case 'email':
            $email = sanitizeInput($_POST['email'] ?? '');
            
            if (empty($email)) {
                $errors[] = "Email address is required";
            } elseif (!validateEmail($email)) {
                $errors[] = "Please enter a valid email address";
            } elseif (!isEmailDomainAllowed($email)) {
                $errors[] = "Email domain is not allowed. Please use a different email provider";
            } else {
                // Check if email already exists (excluding current user)
                $stmt = $db->prepare("SELECT id FROM users WHERE email = :email AND id != :user_id");
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->execute();
                
                if ($stmt->fetch()) {
                    $errors[] = "An account with this email address already exists";
                }
            }
            
            if (empty($errors)) {
                // Update email
                $stmt = $db->prepare("UPDATE users SET email = :email, updated_at = NOW() WHERE id = :user_id");
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    $_SESSION['user_email'] = $email;
                    
                    // Send verification email
                    $verification_token = bin2hex(random_bytes(32));
                    $stmt = $db->prepare("UPDATE users SET email_verification_token = :token WHERE id = :user_id");
                    $stmt->bindParam(':token', $verification_token);
                    $stmt->bindParam(':user_id', $_SESSION['user_id']);
                    $stmt->execute();
                    
                    sendVerificationEmail($email, $user_data['first_name'], $verification_token);
                    
                    // Move to next step
                    $next_step_index = $current_step_index + 1;
                    if ($next_step_index < $total_steps) {
                        $next_step_url = 'complete-profile.php?step=' . $steps_needed[$next_step_index];
                        if ($is_facebook_user) $next_step_url .= '&facebook=1';
                        if ($is_google_user) $next_step_url .= '&google=1';
                        header('Location: ' . $next_step_url);
                        exit();
                    } else {
                        header('Location: complete-profile.php?step=complete');
                        exit();
                    }
                } else {
                    $errors[] = "Failed to update email. Please try again.";
                }
            }
            break;
            
        case 'contact':
            $phone = sanitizeInput($_POST['phone'] ?? '');
            
            if (empty($phone)) {
                $errors[] = "Phone number is required";
            } elseif (!validatePhone($phone)) {
                $errors[] = "Please enter a valid phone number (e.g., +1234567890)";
            } else {
                // Check if phone already exists (excluding current user)
                $clean_phone = cleanPhoneNumber($phone);
                $stmt = $db->prepare("SELECT id FROM users WHERE phone = :phone AND id != :user_id");
                $stmt->bindParam(':phone', $clean_phone);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->execute();
                
                if ($stmt->fetch()) {
                    $errors[] = "An account with this phone number already exists";
                }
            }
            
            if (empty($errors)) {
                // Update phone
                $stmt = $db->prepare("UPDATE users SET phone = :phone, updated_at = NOW() WHERE id = :user_id");
                $stmt->bindParam(':phone', cleanPhoneNumber($phone));
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    // Move to next step
                    $next_step_index = $current_step_index + 1;
                    if ($next_step_index < $total_steps) {
                        $next_step_url = 'complete-profile.php?step=' . $steps_needed[$next_step_index];
                        if ($is_facebook_user) $next_step_url .= '&facebook=1';
                        if ($is_google_user) $next_step_url .= '&google=1';
                        header('Location: ' . $next_step_url);
                        exit();
                    } else {
                        header('Location: complete-profile.php?step=complete');
                        exit();
                    }
                } else {
                    $errors[] = "Failed to update phone number. Please try again.";
                }
            }
            break;
            
        case 'address':
            $delivery_address = sanitizeInput($_POST['delivery_address'] ?? '');
            $address_line_2 = sanitizeInput($_POST['address_line_2'] ?? '');
            $city = sanitizeInput($_POST['city'] ?? '');
            $state = sanitizeInput($_POST['state'] ?? '');
            $zip_code = sanitizeInput($_POST['zip_code'] ?? '');
            $delivery_instructions = sanitizeInput($_POST['delivery_instructions'] ?? '');
            
            if (empty($delivery_address)) {
                $errors[] = "Delivery address is required";
            } elseif (strlen($delivery_address) < 10) {
                $errors[] = "Please enter a complete delivery address";
            }
            
            if (empty($zip_code)) {
                $errors[] = "ZIP code is required";
            } elseif (!validateZipCode($zip_code)) {
                $errors[] = "Please enter a valid 5-digit ZIP code";
            }
            
            if (empty($errors)) {
                // Update address information
                $stmt = $db->prepare("
                    UPDATE users SET 
                        delivery_address = :delivery_address,
                        address_line_2 = :address_line_2,
                        city = :city,
                        state = :state,
                        zip_code = :zip_code,
                        delivery_instructions = :delivery_instructions,
                        updated_at = NOW()
                    WHERE id = :user_id
                ");
                
                $stmt->bindParam(':delivery_address', $delivery_address);
                $stmt->bindParam(':address_line_2', $address_line_2);
                $stmt->bindParam(':city', $city);
                $stmt->bindParam(':state', $state);
                $stmt->bindParam(':zip_code', $zip_code);
                $stmt->bindParam(':delivery_instructions', $delivery_instructions);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    // Move to next step
                    $next_step_index = $current_step_index + 1;
                    if ($next_step_index < $total_steps) {
                        $next_step_url = 'complete-profile.php?step=' . $steps_needed[$next_step_index];
                        if ($is_facebook_user) $next_step_url .= '&facebook=1';
                        if ($is_google_user) $next_step_url .= '&google=1';
                        header('Location: ' . $next_step_url);
                        exit();
                    } else {
                        header('Location: complete-profile.php?step=complete');
                        exit();
                    }
                } else {
                    $errors[] = "Failed to update address information. Please try again.";
                }
            }
            break;
            
        case 'preferences':
            $spice_level = sanitizeInput($_POST['spice_level'] ?? 'medium');
            $dietary_preferences = $_POST['dietary_preferences'] ?? [];
            $allergies = sanitizeInput($_POST['allergies'] ?? '');
            
            // Process dietary preferences
            $dietary_json = null;
            if (!empty($dietary_preferences) && is_array($dietary_preferences)) {
                $dietary_json = json_encode($dietary_preferences);
            }
            
            // Process allergies
            $allergies_json = null;
            if (!empty($allergies)) {
                $allergies_json = json_encode(array_map('trim', explode(',', $allergies)));
            }
            
            // Update preferences and mark profile as complete
            $stmt = $db->prepare("
                UPDATE users SET 
                    spice_level = :spice_level,
                    dietary_preferences = :dietary_preferences,
                    allergies = :allergies,
                    profile_complete = 1,
                    updated_at = NOW()
                WHERE id = :user_id
            ");
            
            $stmt->bindParam(':spice_level', $spice_level);
            $stmt->bindParam(':dietary_preferences', $dietary_json);
            $stmt->bindParam(':allergies', $allergies_json);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                // Profile is now complete
                header('Location: complete-profile.php?step=complete');
                exit();
            } else {
                $errors[] = "Failed to update preferences. Please try again.";
            }
            break;
    }
}

// Prepare form data with current user data
$form_data = [
    'email' => $user_data['email'] ?? '',
    'phone' => $user_data['phone'] ?? '',
    'delivery_address' => $user_data['delivery_address'] ?? '',
    'address_line_2' => $user_data['address_line_2'] ?? '',
    'city' => $user_data['city'] ?? '',
    'state' => $user_data['state'] ?? '',
    'zip_code' => $user_data['zip_code'] ?? '',
    'delivery_instructions' => $user_data['delivery_instructions'] ?? '',
    'spice_level' => $user_data['spice_level'] ?? 'medium',
    'dietary_preferences' => !empty($user_data['dietary_preferences']) ? json_decode($user_data['dietary_preferences'], true) : [],
    'allergies' => !empty($user_data['allergies']) ? implode(', ', json_decode($user_data['allergies'], true)) : ''
];

// Determine the OAuth provider for display purposes
$oauth_provider = '';
$oauth_provider_class = '';
if (!empty($user_data['facebook_id'])) {
    $oauth_provider = 'Facebook';
    $oauth_provider_class = 'facebook';
    $is_facebook_user = true;
} elseif (!empty($user_data['google_id'])) {
    $oauth_provider = 'Google';
    $oauth_provider_class = 'google';
    $is_google_user = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Profile - Somdul Table</title>
    <meta name="description" content="Complete your Somdul Table profile to start ordering authentic Thai meals.">
    
    <!-- BaticaSans Font Import -->
    <link rel="preconnect" href="https://ydpschool.com">
    <style>
        /* BaticaSans Font Family */
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

        /* CSS Custom Properties - Matching Somdul Table Design System */
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
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #17a2b8;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'BaticaSans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
            background: linear-gradient(135deg, var(--cream) 0%, #f8f9fa 100%);
            min-height: 100vh;
            font-size: 16px;
            font-weight: 400;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
            line-height: 1.2;
            color: var(--text-dark);
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 0 20px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .profile-container {
            background: var(--white);
            border-radius: 20px;
            box-shadow: var(--shadow-medium);
            border: 1px solid rgba(255, 255, 255, 0.8);
            width: 100%;
            max-width: 600px;
            margin: 2rem auto;
            overflow: hidden;
            flex: 1;
        }

        .profile-header {
            background: linear-gradient(135deg, var(--curry) 0%, var(--brown) 100%);
            padding: 2.5rem 2rem;
            text-align: center;
            color: var(--white);
            position: relative;
        }

        .profile-header::before {
            content: "";
            position: absolute;
            top: 0;
            right: 0;
            width: 60%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="70" cy="30" r="15" fill="rgba(255,255,255,0.1)"/><circle cx="85" cy="60" r="8" fill="rgba(255,255,255,0.05)"/><circle cx="60" cy="75" r="12" fill="rgba(255,255,255,0.08)"/></svg>');
            background-size: 200px 200px;
            opacity: 0.3;
        }

        .logo {
            display: inline-flex;
            align-items: center;
            gap: 0.8rem;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
            text-decoration: none;
            color: inherit;
        }

        .logo-text {
            font-size: 1.8rem;
            font-weight: 700;
            letter-spacing: -0.5px;
            font-family: 'BaticaSans', sans-serif;
        }

        .profile-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
            font-family: 'BaticaSans', sans-serif;
        }

        .profile-subtitle {
            font-size: 1rem;
            opacity: 0.9;
            font-weight: 400;
            position: relative;
            z-index: 1;
            font-family: 'BaticaSans', sans-serif;
        }

        /* OAuth Provider Badge */
        .oauth-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.2);
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.9rem;
            margin-top: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
            position: relative;
            z-index: 1;
        }

        .oauth-icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 700;
        }

        .oauth-icon.facebook {
            background: #1877f2;
            color: white;
        }

        .oauth-icon.google {
            background: white;
            color: #4285f4;
        }

        /* Progress Bar */
        .progress-container {
            background: var(--white);
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border-light);
        }

        .progress-bar {
            background: #f0f0f0;
            border-radius: 10px;
            height: 8px;
            overflow: hidden;
            margin-bottom: 1rem;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--curry), var(--brown));
            border-radius: 10px;
            transition: width 0.5s ease;
        }

        .progress-text {
            text-align: center;
            font-size: 0.9rem;
            color: var(--text-gray);
            font-family: 'BaticaSans', sans-serif;
        }

        /* Form Container */
        .profile-form {
            padding: 2.5rem 2rem;
        }

        .step-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .step-description {
            color: var(--text-gray);
            margin-bottom: 2rem;
            font-size: 1rem;
            line-height: 1.5;
            font-family: 'BaticaSans', sans-serif;
        }

        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
            font-weight: 500;
            border: 2px solid;
            font-family: 'BaticaSans', sans-serif;
        }

        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            border-color: var(--success);
            color: var(--success);
        }

        .alert-error {
            background-color: rgba(220, 53, 69, 0.1);
            border-color: var(--danger);
            color: var(--danger);
        }

        .alert-info {
            background-color: rgba(23, 162, 184, 0.1);
            border-color: var(--info);
            color: var(--info);
        }

        .alert ul {
            margin: 0;
            padding-left: 1.2rem;
        }

        .alert ul li {
            margin-bottom: 0.3rem;
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.95rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .required {
            color: var(--danger);
        }

        input[type="text"],
        input[type="email"],
        input[type="tel"],
        select,
        textarea {
            width: 100%;
            padding: 1rem 1.2rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-md);
            font-size: 1rem;
            transition: var(--transition);
            background: var(--white);
            color: var(--text-dark);
            font-family: 'BaticaSans', sans-serif;
            -webkit-appearance: none;
            appearance: none;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: var(--curry);
            box-shadow: 0 0 0 3px rgba(207, 114, 58, 0.1);
            transform: translateY(-1px);
        }

        input.error {
            border-color: var(--danger);
            background-color: #fff5f5;
        }

        input.success {
            border-color: var(--success);
            background-color: #f0fff4;
        }

        /* Custom Select */
        select {
            background-image: url("data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTIiIGhlaWdodD0iOCIgdmlld0JveD0iMCAwIDEyIDgiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxwYXRoIGQ9Ik0xIDFMNiA2TDExIDEiIHN0cm9rZT0iIzZjNzU3ZCIgc3Ryb2tlLXdpZHRoPSIyIiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiLz4KPC9zdmc+");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 12px;
            padding-right: 3rem;
        }

        textarea {
            resize: vertical;
            min-height: 80px;
        }

        small {
            color: var(--text-gray);
            font-size: 0.85rem;
            font-family: 'BaticaSans', sans-serif;
        }

        /* Checkbox Groups */
        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 0.8rem;
            margin-top: 0.5rem;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1rem;
            background: rgba(189, 147, 121, 0.05);
            border-radius: 25px;
            border: 2px solid transparent;
            transition: var(--transition);
            cursor: pointer;
            font-size: 0.9rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .checkbox-item:hover {
            background: rgba(189, 147, 121, 0.1);
            transform: translateY(-1px);
        }

        .checkbox-item input[type="checkbox"] {
            width: auto;
            margin: 0;
            transform: scale(1.1);
            accent-color: var(--curry);
        }

        .checkbox-item.checked {
            background: var(--curry);
            color: var(--white);
            border-color: var(--curry);
        }

        /* Buttons */
        .btn-primary {
            background: linear-gradient(135deg, var(--curry), var(--brown));
            color: var(--white);
            padding: 1.2rem 2rem;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-weight: 700;
            font-size: 1.1rem;
            width: 100%;
            transition: var(--transition);
            box-shadow: var(--shadow-soft);
            margin-top: 1rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .btn-primary:hover:not(:disabled) {
            background: linear-gradient(135deg, var(--brown), var(--curry));
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .btn-primary:disabled {
            background: var(--text-gray);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
            opacity: 0.7;
        }

        .btn-secondary {
            background: transparent;
            color: var(--curry);
            padding: 0.8rem 1.5rem;
            border: 2px solid var(--curry);
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: var(--transition);
            font-size: 0.9rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .btn-secondary:hover {
            background: var(--curry);
            color: var(--white);
            transform: translateY(-1px);
            box-shadow: var(--shadow-soft);
        }

        /* Navigation Buttons */
        .form-navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border-light);
        }

        .btn-nav {
            padding: 0.8rem 2rem;
            border-radius: 25px;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
            font-family: 'BaticaSans', sans-serif;
        }

        .btn-back {
            background: transparent;
            color: var(--text-gray);
            border: 2px solid var(--border-light);
        }

        .btn-back:hover {
            background: var(--border-light);
            transform: translateY(-1px);
        }

        .btn-skip {
            background: transparent;
            color: var(--curry);
            border: 2px solid transparent;
            text-decoration: underline;
        }

        .btn-skip:hover {
            color: var(--brown);
        }

        /* Complete Step Styles */
        .complete-container {
            text-align: center;
            padding: 3rem 2rem;
        }

        .complete-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--success), #20c997);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            color: white;
            font-size: 2rem;
        }

        .complete-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 1rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .complete-message {
            font-size: 1.1rem;
            color: var(--text-gray);
            margin-bottom: 2rem;
            line-height: 1.6;
            font-family: 'BaticaSans', sans-serif;
        }

        /* Mobile Responsive */
        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 0 15px;
            }
            
            .profile-container {
                margin: 1rem auto;
            }
            
            .profile-form {
                padding: 2rem 1.5rem;
            }
            
            .profile-header {
                padding: 2rem 1.5rem 1.5rem;
            }
            
            .logo-text {
                font-size: 1.4rem;
            }
            
            .step-title {
                font-size: 1.2rem;
            }
            
            .checkbox-group {
                gap: 0.5rem;
            }
            
            .checkbox-item {
                font-size: 0.85rem;
                padding: 0.5rem 0.8rem;
            }
            
            .form-navigation {
                flex-direction: column;
                gap: 1rem;
            }
            
            .btn-nav {
                width: 100%;
                text-align: center;
            }
        }

        /* Loading State */
        .loading {
            position: relative;
            pointer-events: none;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid transparent;
            border-top: 2px solid var(--white);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Focus indicators for accessibility */
        input:focus-visible,
        select:focus-visible,
        textarea:focus-visible,
        button:focus-visible {
            outline: 2px solid var(--curry);
            outline-offset: 2px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="profile-container">
            <!-- Header -->
            <div class="profile-header">
                <a href="dashboard.php" class="logo">
                    <img src="./assets/image/LOGO_BG.png" alt="Somdul Table" style="height: 50px; width: auto; border-radius: 50%;">
                    <span class="logo-text">Somdul Table</span>
                </a>
                
                <?php if ($current_step === 'complete'): ?>
                    <h1 class="profile-title">Welcome to Somdul Table!</h1>
                    <p class="profile-subtitle">Your profile is now complete</p>
                <?php else: ?>
                    <h1 class="profile-title">Complete Your Profile</h1>
                    <p class="profile-subtitle">
                        Welcome<?php echo $oauth_provider ? ' from ' . $oauth_provider : ''; ?>, <?php echo htmlspecialchars($user_data['first_name'] ?: 'User'); ?>! 
                        Let's set up your account for the best experience.
                    </p>
                    
                    <?php if ($oauth_provider): ?>
                        <div class="oauth-badge">
                            <div class="oauth-icon <?php echo $oauth_provider_class; ?>">
                                <?php if ($oauth_provider === 'Facebook'): ?>
                                    f
                                <?php elseif ($oauth_provider === 'Google'): ?>
                                    G
                                <?php endif; ?>
                            </div>
                            <span>Connected via <?php echo $oauth_provider; ?></span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if ($current_step !== 'complete'): ?>
                <!-- Progress Bar -->
                <div class="progress-container">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo round((($current_step_index + 1) / $total_steps) * 100); ?>%"></div>
                    </div>
                    <div class="progress-text">
                        Step <?php echo $current_step_index + 1; ?> of <?php echo $total_steps; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Form Content -->
            <div class="profile-form">
                <?php if ($current_step === 'complete'): ?>
                    <!-- Completion Step -->
                    <div class="complete-container">
                        <div class="complete-icon">
                            ✓
                        </div>
                        <h2 class="complete-title">Profile Complete!</h2>
                        <p class="complete-message">
                            Great! Your Somdul Table profile is now complete. You can start browsing our authentic Thai meals and place your first order.
                        </p>
                        
                        <?php if (!empty($user_data['email']) && str_contains($user_data['email'], '@temp.somdultable.com')): ?>
                            <div class="alert alert-info">
                                <strong>Email Verification:</strong> We've sent a verification email to your new email address. Please check your inbox and click the verification link to fully activate your account.
                            </div>
                        <?php endif; ?>
                        
                        <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                            <a href="dashboard.php" class="btn-primary" style="width: auto; min-width: 200px;">
                                Start Ordering
                            </a>
                            <a href="profile.php" class="btn-secondary">
                                View Profile
                            </a>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <!-- Step Forms -->
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-error" role="alert" aria-live="polite">
                            <strong>Please correct the following errors:</strong>
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" id="profileForm" novalidate>
                        <?php if ($current_step === 'email'): ?>
                            <!-- Email Step -->
                            <h2 class="step-title">📧 Email Address</h2>
                            <p class="step-description">
                                Please provide your real email address. We'll use this for order confirmations, account updates, and to send you a verification email.
                            </p>
                            
                            <div class="form-group">
                                <label for="email">Email Address <span class="required">*</span></label>
                                <input type="email" 
                                       id="email" 
                                       name="email" 
                                       required 
                                       autocomplete="email"
                                       value="<?php echo str_contains($form_data['email'], '@temp.somdultable.com') ? '' : htmlspecialchars($form_data['email']); ?>"
                                       aria-describedby="email_help">
                                <small id="email_help">We'll send order updates and important notifications to this email</small>
                            </div>

                        <?php elseif ($current_step === 'contact'): ?>
                            <!-- Contact Step -->
                            <h2 class="step-title">📱 Contact Information</h2>
                            <p class="step-description">
                                Your phone number helps our delivery team coordinate with you and provide updates about your orders.
                            </p>
                            
                            <div class="form-group">
                                <label for="phone">Phone Number <span class="required">*</span></label>
                                <input type="tel" 
                                       id="phone" 
                                       name="phone" 
                                       placeholder="+1234567890" 
                                       required 
                                       autocomplete="tel"
                                       value="<?php echo htmlspecialchars($form_data['phone']); ?>"
                                       aria-describedby="phone_help">
                                <small id="phone_help">Used for delivery coordination and order updates</small>
                            </div>

                        <?php elseif ($current_step === 'address'): ?>
                            <!-- Address Step -->
                            <h2 class="step-title">🏠 Delivery Address</h2>
                            <p class="step-description">
                                Where would you like your delicious Thai meals delivered? Please provide complete and accurate address information.
                            </p>
                            
                            <div class="form-group">
                                <label for="delivery_address">Delivery Address <span class="required">*</span></label>
                                <textarea id="delivery_address" 
                                          name="delivery_address" 
                                          rows="3" 
                                          required 
                                          autocomplete="street-address"
                                          placeholder="Enter your complete delivery address including building name, room number, and any landmarks"
                                          aria-describedby="address_help"><?php echo htmlspecialchars($form_data['delivery_address']); ?></textarea>
                                <small id="address_help">Please provide a detailed address for accurate delivery</small>
                            </div>

                            <div class="form-group">
                                <label for="address_line_2">Address Line 2</label>
                                <input type="text" 
                                       id="address_line_2" 
                                       name="address_line_2" 
                                       placeholder="Apartment, suite, unit, building, floor, etc."
                                       autocomplete="address-line2"
                                       value="<?php echo htmlspecialchars($form_data['address_line_2']); ?>">
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="city">City</label>
                                    <input type="text" 
                                           id="city" 
                                           name="city" 
                                           autocomplete="address-level2"
                                           placeholder="e.g., New York"
                                           value="<?php echo htmlspecialchars($form_data['city']); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="state">State/Province</label>
                                    <input type="text" 
                                           id="state" 
                                           name="state" 
                                           autocomplete="address-level1"
                                           placeholder="e.g., NY"
                                           value="<?php echo htmlspecialchars($form_data['state']); ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="zip_code">ZIP Code <span class="required">*</span></label>
                                <input type="text" 
                                       id="zip_code" 
                                       name="zip_code" 
                                       placeholder="12345" 
                                       required 
                                       autocomplete="postal-code"
                                       maxlength="5"
                                       pattern="\d{5}"
                                       value="<?php echo htmlspecialchars($form_data['zip_code']); ?>"
                                       aria-describedby="zip_help">
                                <small id="zip_help">5-digit US postal code</small>
                            </div>

                            <div class="form-group">
                                <label for="delivery_instructions">Delivery Instructions</label>
                                <textarea id="delivery_instructions" 
                                          name="delivery_instructions" 
                                          rows="2" 
                                          placeholder="Special delivery instructions, access codes, or notes for the delivery person"
                                          maxlength="500"><?php echo htmlspecialchars($form_data['delivery_instructions']); ?></textarea>
                            </div>

                        <?php elseif ($current_step === 'preferences'): ?>
                            <!-- Preferences Step -->
                            <h2 class="step-title">🌶️ Food Preferences</h2>
                            <p class="step-description">
                                Help us personalize your experience! These preferences will help us recommend the perfect Thai dishes for you.
                            </p>
                            
                            <div class="form-group">
                                <label for="spice_level">Preferred Spice Level</label>
                                <select id="spice_level" name="spice_level" aria-describedby="spice_help">
                                    <option value="mild" <?php echo ($form_data['spice_level'] == 'mild') ? 'selected' : ''; ?>>Mild 🌶️ - Very gentle heat</option>
                                    <option value="medium" <?php echo ($form_data['spice_level'] == 'medium') ? 'selected' : ''; ?>>Medium 🌶️🌶️ - Moderate spice (recommended)</option>
                                    <option value="hot" <?php echo ($form_data['spice_level'] == 'hot') ? 'selected' : ''; ?>>Hot 🌶️🌶️🌶️ - Authentic Thai heat</option>
                                    <option value="extra_hot" <?php echo ($form_data['spice_level'] == 'extra_hot') ? 'selected' : ''; ?>>Extra Hot 🌶️🌶️🌶️🌶️ - For spice lovers</option>
                                </select>
                                <small id="spice_help">You can always adjust this later in your profile</small>
                            </div>

                            <div class="form-group">
                                <label>Dietary Preferences (Optional)</label>
                                <div class="checkbox-group" role="group" aria-labelledby="dietary_preferences_label">
                                    <?php 
                                    $dietary_options = [
                                        'vegetarian' => 'Vegetarian',
                                        'vegan' => 'Vegan',
                                        'gluten_free' => 'Gluten-Free',
                                        'keto' => 'Keto-Friendly',
                                        'low_sodium' => 'Low Sodium',
                                        'diabetic_friendly' => 'Diabetic-Friendly'
                                    ];
                                    $selected_preferences = $form_data['dietary_preferences'] ?? [];
                                    ?>
                                    <?php foreach ($dietary_options as $value => $label): ?>
                                        <div class="checkbox-item">
                                            <input type="checkbox" 
                                                   id="diet_<?php echo $value; ?>" 
                                                   name="dietary_preferences[]" 
                                                   value="<?php echo $value; ?>"
                                                   <?php echo in_array($value, $selected_preferences) ? 'checked' : ''; ?>>
                                            <label for="diet_<?php echo $value; ?>"><?php echo $label; ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <small>Select all that apply - we'll recommend meals that match your preferences</small>
                            </div>

                            <div class="form-group">
                                <label for="allergies">Food Allergies or Intolerances (Optional)</label>
                                <input type="text" 
                                       id="allergies" 
                                       name="allergies" 
                                       placeholder="e.g., Peanuts, Shellfish, Dairy, Soy"
                                       maxlength="200"
                                       value="<?php echo htmlspecialchars($form_data['allergies']); ?>"
                                       aria-describedby="allergies_help">
                                <small id="allergies_help">Separate multiple allergies with commas. This helps us recommend safe meals for you</small>
                            </div>

                        <?php endif; ?>

                        <!-- Form Navigation -->
                        <div class="form-navigation">
                            <?php if ($current_step_index > 0): ?>
                                <?php 
                                $prev_url = 'complete-profile.php?step=' . $steps_needed[$current_step_index - 1];
                                if ($is_facebook_user) $prev_url .= '&facebook=1';
                                if ($is_google_user) $prev_url .= '&google=1';
                                ?>
                                <a href="<?php echo $prev_url; ?>" class="btn-nav btn-back">
                                    ← Previous
                                </a>
                            <?php else: ?>
                                <div></div>
                            <?php endif; ?>

                            <?php if ($current_step === 'preferences'): ?>
                                <a href="dashboard.php" class="btn-nav btn-skip">
                                    Skip for now →
                                </a>
                            <?php endif; ?>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="btn-primary" id="submitBtn">
                            <span id="submit_text">
                                <?php if ($current_step_index === $total_steps - 1): ?>
                                    Complete Profile
                                <?php else: ?>
                                    Continue
                                <?php endif; ?>
                            </span>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Phone number formatting and validation
        const phoneInput = document.getElementById('phone');
        if (phoneInput) {
            phoneInput.addEventListener('input', function() {
                let value = this.value.replace(/[^\d+\-\(\)\s]/g, '');
                this.value = value;
                
                // Basic phone validation
                if (value && value.length < 10) {
                    this.classList.add('error');
                } else if (value) {
                    this.classList.remove('error');
                    this.classList.add('success');
                } else {
                    this.classList.remove('error', 'success');
                }
            });
        }

        // ZIP code validation
        const zipInput = document.getElementById('zip_code');
        if (zipInput) {
            zipInput.addEventListener('input', function() {
                let value = this.value.replace(/\D/g, '');
                if (value.length > 5) value = value.slice(0, 5);
                this.value = value;
                
                if (value && value.length === 5) {
                    this.classList.remove('error');
                    this.classList.add('success');
                } else if (value) {
                    this.classList.add('error');
                    this.classList.remove('success');
                } else {
                    this.classList.remove('error', 'success');
                }
            });
        }

        // Email validation
        const emailInput = document.getElementById('email');
        if (emailInput) {
            emailInput.addEventListener('blur', function() {
                const email = this.value;
                if (email && !isValidEmail(email)) {
                    this.classList.add('error');
                    this.classList.remove('success');
                } else if (email) {
                    this.classList.remove('error');
                    this.classList.add('success');
                }
            });
        }

        function isValidEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }

        // Checkbox styling
        document.querySelectorAll('.checkbox-item input[type="checkbox"]').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const item = this.closest('.checkbox-item');
                if (this.checked) {
                    item.classList.add('checked');
                } else {
                    item.classList.remove('checked');
                }
            });
            
            // Initialize checked state
            if (checkbox.checked) {
                checkbox.closest('.checkbox-item').classList.add('checked');
            }
        });

        // Form submission handling
        const profileForm = document.getElementById('profileForm');
        if (profileForm) {
            profileForm.addEventListener('submit', function(e) {
                const submitBtn = document.getElementById('submitBtn');
                const submitText = document.getElementById('submit_text');

                // Show loading state
                submitBtn.disabled = true;
                submitBtn.classList.add('loading');
                submitText.textContent = 'Processing...';
                
                // Re-enable after timeout (in case of server errors)
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('loading');
                    submitText.textContent = submitText.textContent.replace('Processing...', 'Continue');
                }, 10000);
            });
        }

        // Auto-focus on first empty required field
        document.addEventListener('DOMContentLoaded', function() {
            const firstEmptyRequired = document.querySelector('input[required]:not([value]), input[required][value=""], textarea[required]:empty');
            if (firstEmptyRequired) {
                firstEmptyRequired.focus();
            }
        });

        // Real-time validation feedback
        document.querySelectorAll('input[required], textarea[required]').forEach(field => {
            field.addEventListener('blur', function() {
                if (this.value.trim() === '') {
                    this.classList.add('error');
                } else {
                    this.classList.remove('error');
                    if (!this.classList.contains('success')) {
                        this.classList.add('success');
                    }
                }
            });
        });

        // Prevent form double submission
        let formSubmitted = false;
        if (profileForm) {
            profileForm.addEventListener('submit', function(e) {
                if (formSubmitted) {
                    e.preventDefault();
                    return false;
                }
                formSubmitted = true;
            });
        }

        // Accessibility improvements
        document.addEventListener('keydown', function(e) {
            // Escape key to clear focus
            if (e.key === 'Escape') {
                document.activeElement.blur();
            }
        });
    </script>
</body>
</html>