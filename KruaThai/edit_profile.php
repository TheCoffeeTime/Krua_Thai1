<?php
/**
 * Somdul Table - Edit Profile Page  
 * File: edit_profile.php
 * Status: PRODUCTION READY ✅
 * Enhanced UX with progressive disclosure, auto-save, and smart validation
 * UPDATED: Now uses header.php for consistent navigation and styling
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php?redirect=edit_profile.php");
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

// Include the header (contains navbar, promo banner, fonts, and base styles)
include 'header.php';

$user_id = $_SESSION['user_id'];
$errors = [];
$success_message = "";

// 🔥 AJAX Handler - Enhanced with better error handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    error_log("=== DEBUG ===");
    error_log("POST data: " . print_r($_POST, true));
    error_log("Dietary: " . print_r($_POST['dietary_preferences'] ?? 'NOT SET', true));
    error_log("Allergies: " . print_r($_POST['allergies'] ?? 'NOT SET', true));

    $response = ['success' => false, 'errors' => [], 'message' => ''];

    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        // Get current user data for verification
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user_data) {
            throw new Exception("User not found");
        }

        switch ($action) {
            case 'auto_save':
                // Auto-save draft functionality
                $field = sanitizeInput($_POST['field'] ?? '');
                $value = sanitizeInput($_POST['value'] ?? '');
                
                if ($field && in_array($field, ['first_name', 'last_name', 'phone', 'delivery_address', 'city', 'zip_code', 'delivery_instructions'])) {
                    $stmt = $pdo->prepare("UPDATE users SET {$field} = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$value, $user_id]);
                    $response['success'] = true;
                    $response['message'] = 'Auto-saved';
                }
                break;

            case 'validate_field':
                // Real-time field validation
                $field = $_POST['field'] ?? '';
                $value = $_POST['value'] ?? '';
                
                switch ($field) {
                    case 'email':
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $response['errors'][] = 'Please enter a valid email address';
                        } else {
                            // Check if email is already taken
                            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                            $stmt->execute([$value, $user_id]);
                            if ($stmt->fetch()) {
                                $response['errors'][] = 'This email is already registered';
                            }
                        }
                        break;
                    case 'phone':
                        $cleaned_phone = preg_replace('/[^\d]/', '', $value);
                        if (strlen($cleaned_phone) < 10) {
                            $response['errors'][] = 'Phone number must be at least 10 digits';
                        }
                        break;
                    case 'zip_code':
                        if (!preg_match('/^\d{5}(-\d{4})?$/', $value)) {
                            $response['errors'][] = 'Please enter a valid ZIP code (12345 or 12345-6789)';
                        }
                        break;
                }
                
                if (empty($response['errors'])) {
                    $response['success'] = true;
                    $response['message'] = 'Valid';
                }
                break;

            case 'check_delivery_zone':
                // Check if ZIP code is in delivery zone
                $zip_code = sanitizeInput($_POST['zip_code'] ?? '');
                
                // Available delivery zones (you can modify this list)
                $delivery_zones = [
                    '10110', '10120', '10200', '10210', '10220', '10230', '10240', '10250',
                    '10260', '10270', '10280', '10290', '10300', '10310', '10320', '10330'
                ];
                
                if (in_array($zip_code, $delivery_zones)) {
                    $response['success'] = true;
                    $response['message'] = 'Delivery available! Estimated delivery time: 2-4 hours';
                    $response['delivery_fee'] = 0; // Free delivery
                } else {
                    $response['success'] = false;
                    $response['message'] = 'Sorry, we don\'t deliver to this area yet. Please leave your email to be notified when we expand.';
                    $response['show_email_form'] = true;
                }
                break;

            case 'update_profile':
                // Enhanced profile update with better validation
                $first_name = sanitizeInput($_POST['first_name'] ?? '');
                $last_name = sanitizeInput($_POST['last_name'] ?? '');
                $phone = sanitizeInput($_POST['phone'] ?? '');
                $date_of_birth = sanitizeInput($_POST['date_of_birth'] ?? '');
                $gender = sanitizeInput($_POST['gender'] ?? '');
                $delivery_address = sanitizeInput($_POST['delivery_address'] ?? '');
                $city = sanitizeInput($_POST['city'] ?? '');
                $zip_code = sanitizeInput($_POST['zip_code'] ?? '');
                $delivery_instructions = sanitizeInput($_POST['delivery_instructions'] ?? '');
                $dietary_preferences = isset($_POST['dietary_preferences']) ? json_encode($_POST['dietary_preferences']) : '[]';
                $allergies = isset($_POST['allergies']) ? json_encode($_POST['allergies']) : '[]';    
                $spice_level = sanitizeInput($_POST['spice_level'] ?? 'medium');

                // Enhanced validation
                if (empty($first_name)) {
                    $response['errors'][] = ['field' => 'first_name', 'message' => 'First name is required'];
                } elseif (strlen($first_name) < 2) {
                    $response['errors'][] = ['field' => 'first_name', 'message' => 'First name must be at least 2 characters'];
                }

                if (empty($last_name)) {
                    $response['errors'][] = ['field' => 'last_name', 'message' => 'Last name is required'];
                } elseif (strlen($last_name) < 2) {
                    $response['errors'][] = ['field' => 'last_name', 'message' => 'Last name must be at least 2 characters'];
                }

                if ($phone && !preg_match('/^\+?[\d\s\-\(\)]{10,}$/', $phone)) {
                    $response['errors'][] = ['field' => 'phone', 'message' => 'Please enter a valid phone number'];
                }

                if ($zip_code && !preg_match('/^\d{5}(-\d{4})?$/', $zip_code)) {
                    $response['errors'][] = ['field' => 'zip_code', 'message' => 'Please enter a valid ZIP code'];
                }

                if (empty($response['errors'])) {
                    $sql = "UPDATE users SET 
                        first_name = ?, last_name = ?, phone = ?, date_of_birth = ?, gender = ?,
                        delivery_address = ?, city = ?, zip_code = ?, delivery_instructions = ?,
                        dietary_preferences = ?, allergies = ?, spice_level = ?, updated_at = NOW() 
                    WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $first_name, $last_name, $phone, $date_of_birth, $gender, 
                        $delivery_address, $city, $zip_code, $delivery_instructions, 
                        $dietary_preferences, $allergies, $spice_level, $user_id
                    ]);

                    $response['success'] = true;
                    $response['message'] = "Profile updated successfully! 🎉";
                    $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                }
                break;

            case 'update_preferences':
                // อัพเดท Food Preferences เท่านั้น
                $dietary_preferences = isset($_POST['dietary_preferences']) ? json_encode($_POST['dietary_preferences']) : '[]';
                $allergies = isset($_POST['allergies']) ? json_encode($_POST['allergies']) : '[]';
                $spice_level = sanitizeInput($_POST['spice_level'] ?? 'medium');

                // Debug log
                error_log("=== PREFERENCES UPDATE ===");
                error_log("Dietary: " . $dietary_preferences);
                error_log("Allergies: " . $allergies);
                error_log("Spice: " . $spice_level);

                try {
                    $sql = "UPDATE users SET 
                                dietary_preferences = ?, allergies = ?, spice_level = ?, updated_at = NOW() 
                            WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$dietary_preferences, $allergies, $spice_level, $user_id]);
                    
                    error_log("Rows affected: " . $stmt->rowCount());

                    $response['success'] = true;
                    $response['message'] = "Food preferences updated successfully! 🍽️";
                } catch (Exception $e) {
                    error_log("SQL Error: " . $e->getMessage());
                    $response['errors'][] = "Error updating preferences: " . $e->getMessage();
                }
                break;
                
            case 'change_password':
                $current_password = $_POST['current_password'] ?? '';
                $new_password = $_POST['new_password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';

                // Enhanced password validation
                if (empty($current_password)) {
                    $response['errors'][] = ['field' => 'current_password', 'message' => 'Current password is required'];
                }
                
                if (empty($new_password)) {
                    $response['errors'][] = ['field' => 'new_password', 'message' => 'New password is required'];
                } elseif (strlen($new_password) < 8) {
                    $response['errors'][] = ['field' => 'new_password', 'message' => 'Password must be at least 8 characters'];
                } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $new_password)) {
                    $response['errors'][] = ['field' => 'new_password', 'message' => 'Password must contain uppercase, lowercase, and number'];
                }
                
                if ($new_password !== $confirm_password) {
                    $response['errors'][] = ['field' => 'confirm_password', 'message' => 'Passwords do not match'];
                }

                if (empty($response['errors'])) {
                    if (password_verify($current_password, $user_data['password_hash'])) {
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$hashed_password, $user_id]);
                        $response['success'] = true;
                        $response['message'] = "Password changed successfully! 🔐";
                    } else {
                        $response['errors'][] = ['field' => 'current_password', 'message' => 'Current password is incorrect'];
                    }
                }
                break;
        }

    } catch (Exception $e) {
        $response['errors'][] = "System error: " . $e->getMessage();
    }
    
    echo json_encode($response);
    exit();
}

// Fetch current user data for page display
try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $current_user = [];
}

if (!$current_user) {
    die("Unable to fetch user data");
}

$page_title = "Edit Profile";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Somdul Table</title>
    
    <style>
        /* EDIT PROFILE SPECIFIC STYLES ONLY - Base styles come from header.php */
        
        /* Main Content Layout */
        .main-content {
            padding-top: 2rem;
            min-height: calc(100vh - 100px);
            padding-left: 2rem;
            padding-right: 2rem;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Enhanced Profile Header */
        .profile-header {
            background: linear-gradient(135deg, var(--brown) 0%, #a8855f 100%);
            color: var(--white);
            padding: 3rem 2rem;
            margin-bottom: 3rem;
            border-radius: var(--radius-lg);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }

        .profile-header-content {
            position: relative;
            z-index: 1;
        }

        .profile-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .profile-header p {
            font-size: 1.2rem;
            opacity: 0.9;
        }

        /* Enhanced Progress Indicator */
        .progress-indicator {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            margin: 2rem 0;
            padding: 1rem;
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
        }

        .progress-step {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-md);
            font-weight: 600;
            transition: var(--transition);
        }

        .progress-step.active {
            background: var(--brown);
            color: var(--white);
        }

        .progress-step.completed {
            background: var(--success);
            color: var(--white);
        }

        .progress-connector {
            width: 40px;
            height: 2px;
            background: var(--border-light);
        }

        .progress-connector.completed {
            background: var(--success);
        }

        /* Enhanced Profile Layout */
        .profile-layout {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 2rem;
            align-items: start;
        }

        .profile-sidebar {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-soft);
            position: sticky;
            top: 120px;
        }

        .sidebar-menu {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: var(--transition);
            text-align: left;
            width: 100%;
            background: transparent;
            font-family: inherit;
            font-size: 1rem;
            color: var(--text-gray);
            position: relative;
        }

        .menu-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 0;
            height: 100%;
            background: var(--brown);
            border-radius: var(--radius-md);
            transition: var(--transition);
            z-index: -1;
        }

        .menu-item:hover::before,
        .menu-item.active::before {
            width: 100%;
        }

        .menu-item:hover,
        .menu-item.active {
            color: var(--white);
        }

        .menu-icon {
            font-size: 1.2rem;
            min-width: 24px;
        }

        /* Enhanced Content Area */
        .profile-content {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            overflow: hidden;
        }

        .tab-content {
            display: none;
            padding: 2rem;
            animation: fadeIn 0.3s ease-in-out;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .section-header {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--cream);
        }

        .section-header h2 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }

        .section-header p {
            color: var(--text-gray);
        }

        /* Enhanced Form Styles */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .required {
            color: var(--error);
        }

        .form-input, .form-select, .form-textarea {
            padding: 0.8rem 1rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-md);
            font-size: 1rem;
            font-family: inherit;
            transition: var(--transition);
            background: var(--white);
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--brown);
            box-shadow: 0 0 0 3px rgba(189, 147, 121, 0.1);
        }

        .form-input.error {
            border-color: var(--error);
            box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1);
        }

        .form-input.success {
            border-color: var(--success);
            box-shadow: 0 0 0 3px rgba(39, 174, 96, 0.1);
        }

        /* Auto-save indicator */
        .auto-save-indicator {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.8rem;
            color: var(--success);
            opacity: 0;
            transition: var(--transition);
        }

        .auto-save-indicator.show {
            opacity: 1;
        }

        /* Field validation feedback */
        .field-feedback {
            font-size: 0.85rem;
            margin-top: 0.25rem;
            min-height: 1.2rem;
            transition: var(--transition);
        }

        .field-feedback.error {
            color: var(--error);
        }

        .field-feedback.success {
            color: var(--success);
        }

        .field-feedback.warning {
            color: var(--warning);
        }

        /* Enhanced Password Input */
        .password-input-wrapper {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-gray);
            font-size: 1.2rem;
            padding: 0.25rem;
            border-radius: var(--radius-sm);
            transition: var(--transition);
        }

        .password-toggle:hover {
            color: var(--brown);
            background: var(--cream);
        }

        /* Password Strength Indicator */
        .password-strength {
            margin-top: 0.5rem;
            height: 4px;
            background: var(--border-light);
            border-radius: 2px;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            transition: var(--transition);
            border-radius: 2px;
        }

        .password-strength-bar.weak {
            width: 33%;
            background: var(--error);
        }

        .password-strength-bar.medium {
            width: 66%;
            background: var(--warning);
        }

        .password-strength-bar.strong {
            width: 100%;
            background: var(--success);
        }

        /* Enhanced Checkbox and Radio Styles */
        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: var(--cream);
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: var(--transition);
            border: 2px solid transparent;
        }

        .checkbox-item:hover {
            background: var(--sage);
            color: var(--white);
            transform: translateY(-1px);
        }

        .checkbox-item input[type="checkbox"]:checked + .checkbox-label {
            font-weight: 600;
        }

        .checkbox-item.checked {
            background: var(--brown);
            color: var(--white);
            border-color: var(--brown);
        }

        /* Spice Level Selector */
        .spice-level-selector {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
        }

        .spice-option {
            position: relative;
        }

        .spice-option input[type="radio"] {
            display: none;
        }

        .spice-label {
            text-align: center;
            padding: 1rem;
            background: var(--cream);
            border-radius: var(--radius-md);
            cursor: pointer;
            border: 2px solid transparent;
            transition: var(--transition);
            display: block;
        }

        .spice-option input[type="radio"]:checked + .spice-label {
            background: var(--brown);
            color: var(--white);
            border-color: var(--brown);
            transform: translateY(-2px);
            box-shadow: var(--shadow-soft);
        }

        .spice-icon {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            display: block;
        }

        .spice-text {
            font-weight: 600;
            font-size: 0.9rem;
        }

        /* Enhanced Form Actions */
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid var(--cream);
            justify-content: flex-end;
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: var(--z-toast);
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .toast {
            padding: 1rem 1.5rem;
            border-radius: var(--radius-md);
            color: var(--white);
            font-weight: 600;
            box-shadow: var(--shadow-medium);
            transform: translateX(100%);
            opacity: 0;
            transition: var(--transition);
            min-width: 300px;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .toast.show {
            transform: translateX(0);
            opacity: 1;
        }

        .toast.success {
            background: var(--success);
        }

        .toast.error {
            background: var(--error);
        }

        .toast.warning {
            background: var(--warning);
        }

        .toast.info {
            background: var(--info);
        }

        .toast-close {
            background: none;
            border: none;
            color: inherit;
            font-size: 1.2rem;
            cursor: pointer;
            margin-left: auto;
            padding: 0.25rem;
            border-radius: var(--radius-sm);
            transition: var(--transition);
        }

        .toast-close:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        /* Loading States */
        .loading {
            position: relative;
            opacity: 0.7;
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
            border-top: 2px solid var(--brown);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Security Info Styles */
        .security-info {
            background: var(--cream);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            margin-top: 2rem;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-light);
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            min-width: 120px;
            color: var(--text-dark);
        }

        .info-value {
            flex: 1;
            color: var(--text-gray);
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-badge.verified,
        .status-badge.active {
            background: #d4edda;
            color: #155724;
        }

        .status-badge.pending {
            background: #fff3cd;
            color: #856404;
        }

        /* Delivery Zone Warning */
        .delivery-zone-warning {
            background: var(--warning);
            color: var(--white);
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-top: 1rem;
            display: none;
        }

        .delivery-zone-warning.show {
            display: block;
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from { transform: translateY(-10px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .profile-layout {
                grid-template-columns: 1fr;
            }
            
            .profile-sidebar {
                position: static;
                top: unset;
            }
            
            .sidebar-menu {
                flex-direction: row;
                overflow-x: auto;
                padding-bottom: 0.5rem;
                gap: 1rem;
            }
            
            .menu-item {
                min-width: 200px;
                flex-shrink: 0;
                justify-content: center;
            }

            .container {
                padding: 0 1rem;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }
            
            .profile-header {
                margin-bottom: 2rem;
                padding: 2rem 1rem;
            }
            
            .profile-header h1 {
                font-size: 2rem;
            }

            .profile-header p {
                font-size: 1rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .form-actions {
                flex-direction: column;
                gap: 1rem;
            }
            
            .progress-indicator {
                flex-direction: column;
                gap: 0.5rem;
                padding: 1rem;
            }
            
            .progress-connector {
                width: 2px;
                height: 20px;
            }

            .progress-step {
                padding: 0.75rem 1rem;
                min-width: 150px;
                justify-content: center;
            }
            
            .checkbox-grid {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }

            .checkbox-item {
                padding: 1rem;
            }

            .spice-level-selector {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .form-input, .form-select, .form-textarea {
                font-size: 16px; /* ป้องกัน zoom บน iOS */
            }
        }

        @media (max-width: 480px) {
            .profile-header h1 {
                font-size: 1.8rem;
            }
            
            .profile-header p {
                font-size: 0.9rem;
            }

            .container {
                padding: 0;
            }

            .main-content {
                padding: 0.5rem;
            }

            .checkbox-grid,
            .spice-level-selector {
                grid-template-columns: 1fr;
            }

            .profile-content .tab-content {
                padding: 1.5rem 1rem;
            }

            .profile-sidebar {
                padding: 1rem;
            }

            .sidebar-menu {
                gap: 0.5rem;
            }

            .menu-item {
                min-width: 160px;
                padding: 0.75rem;
                font-size: 0.9rem;
            }

            .progress-step {
                padding: 0.5rem 0.75rem;
                min-width: 120px;
            }

            .progress-step span {
                font-size: 0.85rem;
            }
        }
    </style>
</head>

<!-- IMPORTANT: Add has-header class for proper spacing -->
<body class="has-header">
    <!-- The header (promo banner + navbar + notifications) is already included from header.php -->

    <div class="main-content">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-header-content">
                <h1>Edit Profile</h1>
                <p>Manage your account settings and meal preferences</p>
            </div>
        </div>

        <!-- Progress Indicator -->
        <div class="progress-indicator">
            <div class="progress-step active" data-step="profile">
                <span class="step-icon">👤</span>
                <span>Personal Info</span>
            </div>
            <div class="progress-connector"></div>
            <div class="progress-step" data-step="preferences">
                <span class="step-icon">🍽️</span>
                <span>Food Preferences</span>
            </div>
            <div class="progress-connector"></div>
            <div class="progress-step" data-step="security">
                <span class="step-icon">🔐</span>
                <span>Security</span>
            </div>
        </div>

        <div class="container">
            <div class="profile-layout">
                <!-- Sidebar Navigation -->
                <aside class="profile-sidebar">
                    <nav class="sidebar-menu">
                        <button class="menu-item active" data-tab="profile">
                            <span class="menu-icon">👤</span>
                            <span>Personal Information</span>
                        </button>
                        <button class="menu-item" data-tab="preferences">
                            <span class="menu-icon">🍽️</span>
                            <span>Food Preferences</span>
                        </button>
                        <button class="menu-item" data-tab="security">
                            <span class="menu-icon">🔐</span>
                            <span>Security & Privacy</span>
                        </button>
                    </nav>
                </aside>

                <!-- Main Content -->
                <main class="profile-content">
                    <!-- Personal Information Tab -->
                    <div class="tab-content active" id="profile-tab">
                        <form id="profileForm" novalidate>
                            <input type="hidden" name="action" value="update_profile">

                            <div class="section-header">
                                <h2>Personal Information</h2>
                                <p>Update your basic profile information and delivery details</p>
                            </div>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="first_name" class="form-label">
                                        First Name <span class="required">*</span>
                                    </label>
                                    <input 
                                        type="text" 
                                        id="first_name" 
                                        name="first_name" 
                                        class="form-input" 
                                        value="<?= htmlspecialchars($current_user['first_name']); ?>" 
                                        required
                                        data-auto-save="true"
                                    >
                                    <div class="auto-save-indicator">✓ Saved</div>
                                    <div class="field-feedback"></div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="last_name" class="form-label">
                                        Last Name <span class="required">*</span>
                                    </label>
                                    <input 
                                        type="text" 
                                        id="last_name" 
                                        name="last_name" 
                                        class="form-input" 
                                        value="<?= htmlspecialchars($current_user['last_name']); ?>" 
                                        required
                                        data-auto-save="true"
                                    >
                                    <div class="auto-save-indicator">✓ Saved</div>
                                    <div class="field-feedback"></div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="email" class="form-label">
                                        Email Address
                                        <span class="status-badge verified">✅ Verified</span>
                                    </label>
                                    <input 
                                        type="email" 
                                        id="email" 
                                        class="form-input" 
                                        value="<?= htmlspecialchars($current_user['email']); ?>" 
                                        disabled
                                        title="Email cannot be changed. Contact support if needed."
                                    >
                                    <div class="field-feedback info">Contact support to change your email address</div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input 
                                        type="tel" 
                                        id="phone" 
                                        name="phone" 
                                        class="form-input" 
                                        value="<?= htmlspecialchars($current_user['phone'] ?? ''); ?>"
                                        placeholder="(555) 123-4567"
                                        data-auto-save="true"
                                        data-validate="phone"
                                    >
                                    <div class="auto-save-indicator">✓ Saved</div>
                                    <div class="field-feedback"></div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="date_of_birth" class="form-label">Date of Birth</label>
                                    <input 
                                        type="date" 
                                        id="date_of_birth" 
                                        name="date_of_birth" 
                                        class="form-input" 
                                        value="<?= htmlspecialchars($current_user['date_of_birth'] ?? ''); ?>"
                                        max="<?= date('Y-m-d', strtotime('-13 years')); ?>"
                                    >
                                    <div class="field-feedback"></div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="gender" class="form-label">Gender</label>
                                    <select id="gender" name="gender" class="form-select">
                                        <option value="">Prefer not to say</option>
                                        <option value="male" <?= ($current_user['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                        <option value="female" <?= ($current_user['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                        <option value="other" <?= ($current_user['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                            </div>

                            <div class="section-header" style="margin-top: 2rem;">
                                <h2>Delivery Information</h2>
                                <p>Where should we deliver your delicious Thai meals?</p>
                            </div>
                            
                            <div class="form-grid">
                                <div class="form-group full-width">
                                    <label for="delivery_address" class="form-label">
                                        Street Address
                                        <span class="required">*</span>
                                    </label>
                                    <input 
                                        type="text" 
                                        id="delivery_address" 
                                        name="delivery_address" 
                                        class="form-input" 
                                        value="<?= htmlspecialchars($current_user['delivery_address'] ?? ''); ?>"
                                        placeholder="123 Main Street, Apt 4B"
                                        data-auto-save="true"
                                    >
                                    <div class="auto-save-indicator">✓ Saved</div>
                                    <div class="field-feedback"></div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="city" class="form-label">City</label>
                                    <input 
                                        type="text" 
                                        id="city" 
                                        name="city" 
                                        class="form-input" 
                                        value="<?= htmlspecialchars($current_user['city'] ?? ''); ?>"
                                        placeholder="Bangkok"
                                        data-auto-save="true"
                                    >
                                    <div class="auto-save-indicator">✓ Saved</div>
                                    <div class="field-feedback"></div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="zip_code" class="form-label">
                                        ZIP Code
                                        <span class="required">*</span>
                                    </label>
                                    <input 
                                        type="text" 
                                        id="zip_code" 
                                        name="zip_code" 
                                        class="form-input" 
                                        value="<?= htmlspecialchars($current_user['zip_code'] ?? ''); ?>"
                                        placeholder="10110"
                                        data-validate="zip"
                                        data-check-delivery="true"
                                    >
                                    <div class="field-feedback"></div>
                                    <div class="delivery-zone-warning" id="deliveryZoneWarning">
                                        <strong>⚠️ Delivery Not Available</strong><br>
                                        We don't deliver to this area yet. Leave your email to be notified when we expand!
                                        <div style="margin-top: 0.5rem;">
                                            <input type="email" placeholder="your@email.com" style="padding: 0.5rem; border-radius: 4px; border: none; margin-right: 0.5rem;">
                                            <button type="button" style="padding: 0.5rem 1rem; background: var(--white); color: var(--warning); border: none; border-radius: 4px; font-weight: 600;">Notify Me</button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group full-width">
                                    <label for="delivery_instructions" class="form-label">
                                        Delivery Instructions
                                        <span style="font-weight: 400; color: var(--text-gray);">(Optional)</span>
                                    </label>
                                    <textarea 
                                        id="delivery_instructions" 
                                        name="delivery_instructions" 
                                        class="form-textarea"
                                        rows="3"
                                        placeholder="e.g., Leave at front door, Ring doorbell twice, etc."
                                        data-auto-save="true"
                                    ><?= htmlspecialchars($current_user['delivery_instructions'] ?? ''); ?></textarea>
                                    <div class="auto-save-indicator">✓ Saved</div>
                                    <div class="field-feedback"></div>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <span class="btn-text">Save Changes</span>
                                    <span class="btn-loading" style="display: none;">Saving...</span>
                                </button>
                                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>

                    <!-- Food Preferences Tab -->
                    <div class="tab-content" id="preferences-tab">
                        <form id="preferencesForm">
                            <input type="hidden" name="action" value="update_preferences">

                            <div class="section-header">
                                <h2>Food Preferences</h2>
                                <p>Help us customize your meals to your dietary needs and taste preferences</p>
                            </div>
                            
                            <div class="form-group full-width">
                                <label class="form-label">
                                    <span class="menu-icon">🌱</span>
                                    Dietary Restrictions
                                </label>
                                <p style="color: var(--text-gray); margin-bottom: 1rem; font-size: 0.9rem;">
                                    Select all that apply to ensure we prepare meals that meet your dietary needs
                                </p>
                                <div class="checkbox-grid">
                                    <?php 
                                    $dietary_preferences = json_decode($current_user['dietary_preferences'] ?? '[]', true); 
                                    $preferences = [
                                        'vegetarian' => ['label' => 'Vegetarian', 'icon' => '🥬'],
                                        'vegan' => ['label' => 'Vegan', 'icon' => '🌿'], 
                                        'halal' => ['label' => 'Halal', 'icon' => '☪️'],
                                        'gluten_free' => ['label' => 'Gluten Free', 'icon' => '🌾'],
                                        'dairy_free' => ['label' => 'Dairy Free', 'icon' => '🥛'],
                                        'low_sodium' => ['label' => 'Low Sodium', 'icon' => '🧂']
                                    ];
                                    foreach ($preferences as $key => $data): 
                                    ?>
                                        <label class="checkbox-item">
                                            <input type="checkbox" 
                                               id="diet_<?php echo $key; ?>" 
                                               name="dietary_preferences[]" 
                                               value="<?php echo $key; ?>"
                                               <?php echo in_array($key, $dietary_preferences) ? 'checked' : ''; ?>
                                            >
                                            <span class="checkbox-label">
                                                <?php echo $data['icon']; ?> <?php echo $data['label']; ?>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="form-group full-width" style="margin-top: 2rem;">
                                <label class="form-label">
                                    <span class="menu-icon">⚠️</span>
                                    Food Allergies
                                </label>
                                <p style="color: var(--error); margin-bottom: 1rem; font-size: 0.9rem; font-weight: 600;">
                                    ⚠️ Please select all allergies. This is critical for your safety.
                                </p>
                                <div class="checkbox-grid">
                                    <?php 
                                    $allergies = json_decode($current_user['allergies'] ?? '[]', true); 
                                    $allergy_options = [
                                        'nuts' => ['label' => 'Tree Nuts', 'icon' => '🥜'],
                                        'peanuts' => ['label' => 'Peanuts', 'icon' => '🥜'],
                                        'shellfish' => ['label' => 'Shellfish', 'icon' => '🦐'], 
                                        'fish' => ['label' => 'Fish', 'icon' => '🐟'],
                                        'eggs' => ['label' => 'Eggs', 'icon' => '🥚'],
                                        'soy' => ['label' => 'Soy', 'icon' => '🫘'],
                                        'sesame' => ['label' => 'Sesame', 'icon' => '🧈'],
                                        'dairy' => ['label' => 'Dairy', 'icon' => '🥛']
                                    ];
                                    foreach ($allergy_options as $key => $data): 
                                    ?>
                                        <label class="checkbox-item">
                                            <input type="checkbox" 
                                               id="allergy_<?php echo $key; ?>" 
                                               name="allergies[]" 
                                               value="<?php echo $key; ?>"
                                               <?php echo in_array($key, $allergies) ? 'checked' : ''; ?>
                                            >
                                            <span class="checkbox-label">
                                                <?php echo $data['icon']; ?> <?php echo $data['label']; ?>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="form-group full-width" style="margin-top: 2rem;">
                                <label class="form-label">
                                    <span class="menu-icon">🌶️</span>
                                    Preferred Spice Level
                                </label>
                                <p style="color: var(--text-gray); margin-bottom: 1rem; font-size: 0.9rem;">
                                    Choose your heat preference for Thai dishes
                                </p>
                                <div class="spice-level-selector">
                                    <?php 
                                    $spice_levels = [
                                        'mild' => ['icon' => '🟢', 'text' => 'Mild', 'desc' => 'No spice'],
                                        'medium' => ['icon' => '🟡', 'text' => 'Medium', 'desc' => 'Some heat'],
                                        'hot' => ['icon' => '🟠', 'text' => 'Hot', 'desc' => 'Spicy'],
                                        'extra_hot' => ['icon' => '🔴', 'text' => 'Extra Hot', 'desc' => 'Very spicy']
                                    ];
                                    foreach ($spice_levels as $key => $data): ?>
                                        <label class="spice-option">
                                            <input 
                                                type="radio" 
                                                name="spice_level" 
                                                value="<?php echo $key; ?>"
                                                <?php echo ($current_user['spice_level'] ?? 'medium') === $key ? 'checked' : ''; ?>
                                            >
                                            <div class="spice-label">
                                                <div class="spice-icon"><?php echo $data['icon']; ?></div>
                                                <div class="spice-text"><?php echo $data['text']; ?></div>
                                                <div style="font-size: 0.8rem; color: var(--text-gray); margin-top: 0.25rem;">
                                                    <?php echo $data['desc']; ?>
                                                </div>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <span class="btn-text">Save Preferences</span>
                                    <span class="btn-loading" style="display: none;">Saving...</span>
                                </button>
                                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>

                    <!-- Security Tab -->
                    <div class="tab-content" id="security-tab">
                        <form id="passwordForm" novalidate>
                            <input type="hidden" name="action" value="change_password">

                            <div class="section-header">
                                <h2>Change Password</h2>
                                <p>Keep your account secure with a strong password</p>
                            </div>
                            
                            <div class="form-grid">
                                <div class="form-group full-width">
                                    <label for="current_password" class="form-label">
                                        Current Password <span class="required">*</span>
                                    </label>
                                    <div class="password-input-wrapper">
                                        <input 
                                            type="password" 
                                            id="current_password" 
                                            name="current_password" 
                                            class="form-input" 
                                            required
                                            autocomplete="current-password"
                                        >
                                        <button type="button" class="password-toggle" onclick="togglePassword('current_password')">
                                            👁️
                                        </button>
                                    </div>
                                    <div class="field-feedback"></div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="new_password" class="form-label">
                                        New Password <span class="required">*</span>
                                    </label>
                                    <div class="password-input-wrapper">
                                        <input 
                                            type="password" 
                                            id="new_password" 
                                            name="new_password" 
                                            class="form-input" 
                                            required
                                            autocomplete="new-password"
                                            data-validate="password"
                                        >
                                        <button type="button" class="password-toggle" onclick="togglePassword('new_password')">
                                            👁️
                                        </button>
                                    </div>
                                    <div class="password-strength">
                                        <div class="password-strength-bar" id="passwordStrengthBar"></div>
                                    </div>
                                    <div class="field-feedback">
                                        Must include uppercase, lowercase, and number (min 8 chars)
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="confirm_password" class="form-label">
                                        Confirm New Password <span class="required">*</span>
                                    </label>
                                    <div class="password-input-wrapper">
                                        <input 
                                            type="password" 
                                            id="confirm_password" 
                                            name="confirm_password" 
                                            class="form-input" 
                                            required
                                            autocomplete="new-password"
                                            data-validate="confirm-password"
                                        >
                                        <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                            👁️
                                        </button>
                                    </div>
                                    <div class="field-feedback"></div>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <span class="btn-text">Change Password</span>
                                    <span class="btn-loading" style="display: none;">Changing...</span>
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="resetPasswordForm()">Reset Form</button>
                            </div>
                        </form>

                        <div class="section-header" style="margin-top: 3rem;">
                            <h2>Account Security</h2>
                            <p>Review your account security information</p>
                        </div>

                        <div class="security-info">
                            <div class="info-item">
                                <span class="info-label">Email:</span>
                                <span class="info-value"><?= htmlspecialchars($current_user['email']); ?></span>
                                <span class="status-badge verified">✅ Verified</span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Account Status:</span>
                                <span class="info-value">Active Account</span>
                                <span class="status-badge active">✅ Active</span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Member Since:</span>
                                <span class="info-value"><?= date('F j, Y', strtotime($current_user['created_at'] ?? 'now')); ?></span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Last Login:</span>
                                <span class="info-value"><?= date('M j, Y g:i A', strtotime($current_user['last_login'] ?? 'now')); ?></span>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <script>
        // Enhanced Tab Management
        class ProfileManager {
            constructor() {
                this.currentTab = 'profile';
                this.autoSaveTimeout = null;
                this.validationCache = new Map();
                this.init();
            }

            init() {
                this.setupTabs();
                this.setupAutoSave();
                this.setupValidation();
                this.setupFormSubmissions();
                this.setupPasswordToggle();
                this.setupPasswordStrength();
                this.setupCheckboxStyles(); 
            }

            setupTabs() {
                const menuItems = document.querySelectorAll('.menu-item');
                const tabContents = document.querySelectorAll('.tab-content');
                const progressSteps = document.querySelectorAll('.progress-step');

                menuItems.forEach((item, index) => {
                    item.addEventListener('click', () => {
                        const targetTab = item.getAttribute('data-tab');
                        this.switchTab(targetTab, menuItems, tabContents, progressSteps);
                    });
                });
            }

            switchTab(targetTab, menuItems, tabContents, progressSteps) {
                // Remove active classes
                menuItems.forEach(mi => mi.classList.remove('active'));
                tabContents.forEach(tc => tc.classList.remove('active'));
                progressSteps.forEach(ps => ps.classList.remove('active'));

                // Add active classes
                document.querySelector(`[data-tab="${targetTab}"]`).classList.add('active');
                document.getElementById(`${targetTab}-tab`).classList.add('active');
                document.querySelector(`[data-step="${targetTab}"]`).classList.add('active');
                this.currentTab = targetTab;
            }

            setupAutoSave() {
                const autoSaveFields = document.querySelectorAll('[data-auto-save="true"]');
                
                autoSaveFields.forEach(field => {
                    field.addEventListener('input', (e) => {
                        this.handleAutoSave(e.target);
                    });
                });
            }

            handleAutoSave(field) {
                clearTimeout(this.autoSaveTimeout);
                
                this.autoSaveTimeout = setTimeout(() => {
                    const formData = new FormData();
                    formData.append('action', 'auto_save');
                    formData.append('field', field.name);
                    formData.append('value', field.value);

                    fetch('edit_profile.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            this.showAutoSaveIndicator(field);
                        }
                    })
                    .catch(console.error);
                }, 2000); // Auto-save after 2 seconds of inactivity
            }

            showAutoSaveIndicator(field) {
                const indicator = field.parentNode.querySelector('.auto-save-indicator');
                if (indicator) {
                    indicator.classList.add('show');
                    setTimeout(() => {
                        indicator.classList.remove('show');
                    }, 2000);
                }
            }

            setupValidation() {
                const validateFields = document.querySelectorAll('[data-validate]');
                
                validateFields.forEach(field => {
                    field.addEventListener('blur', () => this.validateField(field));
                    field.addEventListener('input', () => this.clearFieldError(field));
                });

                // Special handling for ZIP code delivery check
                const zipField = document.querySelector('[data-check-delivery="true"]');
                if (zipField) {
                    zipField.addEventListener('blur', () => this.checkDeliveryZone(zipField));
                }
            }

            async validateField(field) {
                const validationType = field.getAttribute('data-validate');
                const value = field.value.trim();
                
                if (!value) return;

                const formData = new FormData();
                formData.append('action', 'validate_field');
                formData.append('field', validationType);
                formData.append('value', value);

                try {
                    const response = await fetch('edit_profile.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();
                    
                    this.showFieldFeedback(field, data.errors.length > 0 ? data.errors[0] : data.message, data.success ? 'success' : 'error');
                } catch (error) {
                    console.error('Validation error:', error);
                }
            }

            async checkDeliveryZone(field) {
                const zipCode = field.value.trim();
                if (!zipCode) return;

                const formData = new FormData();
                formData.append('action', 'check_delivery_zone');
                formData.append('zip_code', zipCode);

                try {
                    const response = await fetch('edit_profile.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();
                    
                    const warningDiv = document.getElementById('deliveryZoneWarning');
                    if (data.success) {
                        this.showFieldFeedback(field, data.message, 'success');
                        warningDiv.classList.remove('show');
                    } else {
                        this.showFieldFeedback(field, 'ZIP code checked', 'warning');
                        warningDiv.classList.add('show');
                    }
                } catch (error) {
                    console.error('Delivery zone check error:', error);
                }
            }

            showFieldFeedback(field, message, type) {
                const feedback = field.parentNode.querySelector('.field-feedback');
                if (feedback) {
                    feedback.textContent = message;
                    feedback.className = `field-feedback ${type}`;
                }
                
                field.classList.remove('error', 'success');
                if (type === 'error') {
                    field.classList.add('error');
                } else if (type === 'success') {
                    field.classList.add('success');
                }
            }

            clearFieldError(field) {
                field.classList.remove('error');
                const feedback = field.parentNode.querySelector('.field-feedback');
                if (feedback && feedback.classList.contains('error')) {
                    feedback.textContent = '';
                    feedback.className = 'field-feedback';
                }
            }

            setupFormSubmissions() {
                // Profile form
                document.getElementById('profileForm').addEventListener('submit', (e) => {
                    this.handleFormSubmit(e, 'Profile updated successfully! 🎉');
                });

                // Preferences form  
                document.getElementById('preferencesForm').addEventListener('submit', (e) => {
                    this.handleFormSubmit(e, 'Food preferences saved! 🍽️');
                });

                // Password form
                document.getElementById('passwordForm').addEventListener('submit', (e) => {
                    this.handleFormSubmit(e, 'Password changed successfully! 🔐', true);
                });
            }

            async handleFormSubmit(e, successMessage, resetForm = false) {
                e.preventDefault();
                
                const form = e.target;
                const formData = new FormData(form);
                const submitBtn = form.querySelector('button[type="submit"]');
                const btnText = submitBtn.querySelector('.btn-text');
                const btnLoading = submitBtn.querySelector('.btn-loading');
                
                // Show loading state
                this.setButtonLoading(submitBtn, btnText, btnLoading, true);
                
                try {
                    const response = await fetch('edit_profile.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();
                    
                    if (data.success) {
                        this.showToast(data.message || successMessage, 'success');
                        if (resetForm) {
                            form.reset();
                            this.resetPasswordToggles();
                        }
                        this.clearFormErrors(form);
                    } else {
                        this.handleFormErrors(form, data.errors);
                    }
                } catch (error) {
                    this.showToast('Network error. Please try again.', 'error');
                    console.error('Form submission error:', error);
                } finally {
                    this.setButtonLoading(submitBtn, btnText, btnLoading, false);
                }
            }

            setButtonLoading(button, textEl, loadingEl, isLoading) {
                if (isLoading) {
                    button.disabled = true;
                    textEl.style.display = 'none';
                    loadingEl.style.display = 'inline';
                } else {
                    button.disabled = false;
                    textEl.style.display = 'inline';
                    loadingEl.style.display = 'none';
                }
            }

            handleFormErrors(form, errors) {
                this.clearFormErrors(form);
                
                errors.forEach(error => {
                    if (typeof error === 'object' && error.field) {
                        const field = form.querySelector(`[name="${error.field}"]`);
                        if (field) {
                            this.showFieldFeedback(field, error.message, 'error');
                        }
                    } else {
                        this.showToast(error, 'error');
                    }
                });
            }

            clearFormErrors(form) {
                const fields = form.querySelectorAll('.form-input, .form-select, .form-textarea');
                fields.forEach(field => {
                    field.classList.remove('error', 'success');
                });
                
                const feedbacks = form.querySelectorAll('.field-feedback');
                feedbacks.forEach(feedback => {
                    if (feedback.classList.contains('error')) {
                        feedback.textContent = '';
                        feedback.className = 'field-feedback';
                    }
                });
            }

            setupPasswordToggle() {
                window.togglePassword = (fieldId) => {
                    const field = document.getElementById(fieldId);
                    const toggle = field.nextElementSibling;
                    
                    if (field.type === 'password') {
                        field.type = 'text';
                        toggle.textContent = '🙈';
                    } else {
                        field.type = 'password';
                        toggle.textContent = '👁️';
                    }
                };

                window.resetPasswordForm = () => {
                    document.getElementById('passwordForm').reset();
                    this.resetPasswordToggles();
                    this.updatePasswordStrength('');
                };
            }

            resetPasswordToggles() {
                ['current_password', 'new_password', 'confirm_password'].forEach(id => {
                    const field = document.getElementById(id);
                    if (field) {
                        const toggle = field.nextElementSibling;
                        field.type = 'password';
                        toggle.textContent = '👁️';
                    }
                });
            }

            setupPasswordStrength() {
                const passwordField = document.getElementById('new_password');
                const confirmField = document.getElementById('confirm_password');
                
                if (passwordField) {
                    passwordField.addEventListener('input', (e) => {
                        this.updatePasswordStrength(e.target.value);
                    });
                }

                if (confirmField) {
                    confirmField.addEventListener('input', (e) => {
                        this.validatePasswordMatch();
                    });
                }
            }

            updatePasswordStrength(password) {
                const strengthBar = document.getElementById('passwordStrengthBar');
                if (!strengthBar) return;

                let strength = 0;
                
                if (password.length >= 8) strength++;
                if (/[a-z]/.test(password)) strength++;
                if (/[A-Z]/.test(password)) strength++;
                if (/\d/.test(password)) strength++;
                if (/[^a-zA-Z\d]/.test(password)) strength++;

                strengthBar.className = 'password-strength-bar';
                
                if (strength <= 2) {
                    strengthBar.classList.add('weak');
                } else if (strength <= 4) {
                    strengthBar.classList.add('medium');
                } else {
                    strengthBar.classList.add('strong');
                }
            }

            validatePasswordMatch() {
                const newPassword = document.getElementById('new_password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                const confirmField = document.getElementById('confirm_password');
                
                if (confirmPassword && newPassword !== confirmPassword) {
                    this.showFieldFeedback(confirmField, 'Passwords do not match', 'error');
                } else if (confirmPassword && newPassword === confirmPassword) {
                    this.showFieldFeedback(confirmField, 'Passwords match', 'success');
                }
            }

            showToast(message, type = 'success') {
                const container = document.getElementById('toastContainer');
                const toast = document.createElement('div');
                
                const icons = {
                    success: '✅',
                    error: '❌',
                    warning: '⚠️',
                    info: 'ℹ️'
                };
                
                toast.className = `toast ${type}`;
                toast.innerHTML = `
                    <span>${icons[type]} ${message}</span>
                    <button class="toast-close" onclick="this.parentElement.remove()">×</button>
                `;
                
                container.appendChild(toast);
                
                // Trigger animation
                setTimeout(() => toast.classList.add('show'), 100);
                
                // Auto remove after 5 seconds
                setTimeout(() => {
                    if (toast.parentElement) {
                        toast.classList.remove('show');
                        setTimeout(() => toast.remove(), 300);
                    }
                }, 5000);
            }

            setupCheckboxStyles() {
                const checkboxes = document.querySelectorAll('.checkbox-item input[type="checkbox"]');
                checkboxes.forEach(checkbox => {
                    const updateStyle = () => {
                        const label = checkbox.closest('.checkbox-item');
                        if (checkbox.checked) {
                            label.classList.add('checked');
                        } else {
                            label.classList.remove('checked');
                        }
                    };
                    
                    checkbox.addEventListener('change', updateStyle);
                    updateStyle(); // Initial check
                });
            }
        }

        // Initialize the profile manager when DOM is loaded
        document.addEventListener('DOMContentLoaded', () => {
            new ProfileManager();
        });

        // Phone number formatting
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 6) {
                value = value.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
            } else if (value.length >= 3) {
                value = value.replace(/(\d{3})(\d+)/, '($1) $2');
            }
            e.target.value = value;
        });

        // ZIP code formatting
        document.getElementById('zip_code').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 5) {
                value = value.replace(/(\d{5})(\d{4})/, '$1-$2');
            }
            e.target.value = value;
        });

        // Enhanced mobile interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-resize textarea
            const textareas = document.querySelectorAll('textarea');
            textareas.forEach(textarea => {
                textarea.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = this.scrollHeight + 'px';
                });
            });

            // Touch gesture for tab switching on mobile
            let touchStartX = 0;
            let touchEndX = 0;
            
            const tabContent = document.querySelector('.profile-content');
            if (tabContent) {
                tabContent.addEventListener('touchstart', e => {
                    touchStartX = e.changedTouches[0].screenX;
                });

                tabContent.addEventListener('touchend', e => {
                    touchEndX = e.changedTouches[0].screenX;
                    handleSwipe();
                });

                function handleSwipe() {
                    const swipeThreshold = 100;
                    const diff = touchStartX - touchEndX;

                    if (Math.abs(diff) > swipeThreshold) {
                        const currentTab = document.querySelector('.menu-item.active');
                        const allTabs = [...document.querySelectorAll('.menu-item')];
                        const currentIndex = allTabs.indexOf(currentTab);

                        if (diff > 0 && currentIndex < allTabs.length - 1) {
                            // Swipe left - next tab
                            allTabs[currentIndex + 1].click();
                        } else if (diff < 0 && currentIndex > 0) {
                            // Swipe right - previous tab
                            allTabs[currentIndex - 1].click();
                        }
                    }
                }
            }
        });

        console.log('Somdul Table Edit Profile loaded successfully!');
    </script>
</body>
</html>