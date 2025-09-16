<?php
/**
 * Somdul Table - Home Page with Database Integration and Coming Soon Popup
 * File: index.php
 * FIXED: Character encoding and JavaScript issues
 * ADDED: ZIP Code Checking Functionality
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once 'config/database.php';

// Include the header (contains navbar, promo banner, fonts, and base styles)
include 'header.php';

// Create email_signups table if it doesn't exist (using main database)
try {
    $create_table_sql = "CREATE TABLE IF NOT EXISTS email_signups (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        signup_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('active', 'unsubscribed') DEFAULT 'active',
        ip_address VARCHAR(45),
        user_agent TEXT,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_email (email),
        INDEX idx_status (status),
        INDEX idx_signup_date (signup_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($create_table_sql);
} catch (PDOException $e) {
    error_log("Error creating email_signups table: " . $e->getMessage());
}

// Initialize variables for popup
$popup_message = "";
$popup_message_type = "";

// Handle popup form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_popup_signup'])) {
    $name = trim($_POST['popup_name']);
    $email = trim($_POST['popup_email']);
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    
    // Validate input
    if (empty($name) || empty($email)) {
        $popup_message = "Please fill in all fields.";
        $popup_message_type = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $popup_message = "Please enter a valid email address.";
        $popup_message_type = "error";
    } else {
        try {
            // Check if email already exists
            $check_email_sql = "SELECT id FROM email_signups WHERE email = ?";
            $check_stmt = $pdo->prepare($check_email_sql);
            $check_stmt->execute([$email]);
            
            if ($check_stmt->fetch()) {
                $popup_message = "This email is already registered! We'll notify you when we open.";
                $popup_message_type = "info";
            } else {
                // Insert new signup
                $insert_sql = "INSERT INTO email_signups (name, email, ip_address, user_agent) VALUES (?, ?, ?, ?)";
                $insert_stmt = $pdo->prepare($insert_sql);
                
                if ($insert_stmt->execute([$name, $email, $ip_address, $user_agent])) {
                    $popup_message = "Thank you, " . htmlspecialchars($name) . "! You're now on our exclusive preview list. We'll notify you at " . htmlspecialchars($email) . " when Somdul Table opens.";
                    $popup_message_type = "success";
                    
                    // Log successful signup
                    error_log("New email signup: {$email} from IP: {$ip_address}");
                } else {
                    $popup_message = "Sorry, there was an error processing your request. Please try again.";
                    $popup_message_type = "error";
                    error_log("Database insert error for email signup: {$email}");
                }
            }
        } catch (PDOException $e) {
            // Check for duplicate entry error
            if ($e->getCode() == 23000) { // Duplicate entry
                $popup_message = "This email is already registered! We'll notify you when we open.";
                $popup_message_type = "info";
            } else {
                $popup_message = "Sorry, there was an error processing your request. Please try again.";
                $popup_message_type = "error";
                error_log("Database error in email signup: " . $e->getMessage());
            }
        }
    }
}

try {
    // Fetch categories for navigation
    $categories = [];
    $stmt = $pdo->prepare("
        SELECT id, name, name_thai 
        FROM menu_categories 
        WHERE is_active = 1 
        ORDER BY sort_order ASC
    ");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch featured menus for display (limit to 6 for carousel)
    $stmt = $pdo->prepare("
        SELECT m.*, c.name AS category_name, c.name_thai AS category_name_thai
        FROM menus m 
        LEFT JOIN menu_categories c ON m.category_id = c.id 
        WHERE m.is_available = 1 
        ORDER BY m.is_featured DESC, m.updated_at DESC 
        LIMIT 6
    ");
    $stmt->execute();
    $featured_menus = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Home page database error: " . $e->getMessage());
    $categories = [];
    $featured_menus = [];
}

// Category icons mapping
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

// Default icon for categories not in mapping
$default_icon = '<path d="M12 2c-1.1 0-2 .9-2 2v2H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-2V4c0-1.1-.9-2-2-2zm0 2v2h-2V4h2zm-4 4h8v2h-8V8zm0 4h8v6H8v-6z"/>';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Somdul Table - Authentic Thai Restaurant Management</title>
    <meta name="description" content="Experience authentic Thai cuisine with Somdul Table - Your premier Thai restaurant management system in the US">
    
    <style>
    /* Page-specific styles only - header styles come from header.php */
    
    /* Coming Soon Popup Styles */
    .popup-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        backdrop-filter: blur(5px);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }

    .popup-overlay.active {
        opacity: 1;
        visibility: visible;
    }

    /* Minimized floating state */
    .popup-overlay.minimized {
        opacity: 1;
        visibility: visible;
        background: transparent;
        backdrop-filter: none;
        align-items: flex-end;
        justify-content: flex-end;
        padding: 2rem;
        pointer-events: none; /* Allow clicks through overlay */
    }

    .popup-container {
        background: var(--cream);
        border-radius: 20px;
        max-width: 600px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
        position: relative;
        padding: 3rem 2rem;
        text-align: center;
        transform: translateY(50px);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        pointer-events: auto;
    }

    .popup-overlay.active .popup-container {
        transform: translateY(0);
    }

    /* Add a subtle glow effect to the minimized circle */
    .popup-overlay.minimized .popup-container {
        width: 70px;
        height: 70px;
        max-width: 70px;
        max-height: 70px;
        padding: 0;
        border-radius: 50%;
        background: var(--brown);
        box-shadow: 0 8px 25px rgba(189, 147, 121, 0.4), 0 0 0 2px var(--curry);
        transform: translateY(0);
        overflow: visible;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
    }

    .popup-overlay.minimized .popup-container:hover {
        transform: scale(1.1);
        box-shadow: 0 12px 35px rgba(189, 147, 121, 0.6), 0 0 0 3px var(--curry);
    }

    /* Tooltip for floating circle */
    .popup-overlay.minimized .popup-container::after {
        content: 'Click to join our preview list!';
        position: absolute;
        bottom: 85px;
        right: 0;
        background: var(--text-dark);
        color: var(--white);
        padding: 8px 12px;
        border-radius: 8px;
        font-size: 12px;
        font-family: 'BaticaSans', sans-serif;
        white-space: nowrap;
        opacity: 0;
        transform: translateY(10px);
        transition: all 0.3s ease;
        pointer-events: none;
        z-index: 10001;
    }

    .popup-overlay.minimized .popup-container:hover::after {
        opacity: 1;
        transform: translateY(0);
    }

    /* Arrow for tooltip */
    .popup-overlay.minimized .popup-container:hover::before {
        content: '';
        position: absolute;
        bottom: 75px;
        right: 25px;
        width: 0;
        height: 0;
        border-left: 6px solid transparent;
        border-right: 6px solid transparent;
        border-top: 6px solid var(--text-dark);
        opacity: 1;
        z-index: 10001;
    }

    /* Floating circle content */
    .floating-circle-content {
        display: none;
        color: var(--white);
        font-size: 1.8rem;
        font-weight: bold;
        font-family: 'BaticaSans', sans-serif;
        animation: pulse 2s infinite;
        position: relative;
    }

    .popup-overlay.minimized .floating-circle-content {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
    }

    /* Add a small notification dot */
    .floating-circle-content::after {
        content: '';
        position: absolute;
        top: 8px;
        right: 8px;
        width: 12px;
        height: 12px;
        background: var(--curry);
        border: 2px solid var(--white);
        border-radius: 50%;
        animation: ping 1.5s infinite;
    }

    @keyframes ping {
        0% {
            transform: scale(1);
            opacity: 1;
        }
        75%, 100% {
            transform: scale(1.4);
            opacity: 0;
        }
    }

    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }

    /* Hide all popup content when minimized */
    .popup-overlay.minimized .popup-logo,
    .popup-overlay.minimized .popup-main-heading,
    .popup-overlay.minimized .popup-decorative-element,
    .popup-overlay.minimized .popup-subtitle,
    .popup-overlay.minimized .popup-description,
    .popup-overlay.minimized .popup-value-text,
    .popup-overlay.minimized .popup-message,
    .popup-overlay.minimized .popup-form-container,
    .popup-overlay.minimized .popup-browse-btn,
    .popup-overlay.minimized .popup-controls {
        display: none;
    }

    .popup-close {
        background: none;
        border: none;
        font-size: 1.8rem;
        color: var(--text-gray);
        cursor: pointer;
        transition: color 0.3s ease;
        z-index: 10001;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
    }

    .popup-close:hover {
        color: var(--brown);
        background: rgba(189, 147, 121, 0.1);
    }

    .popup-minimize {
        background: none;
        border: none;
        font-size: 1.8rem;
        color: var(--text-gray);
        cursor: pointer;
        transition: color 0.3s ease;
        z-index: 10001;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        margin-right: 8px;
    }

    .popup-minimize:hover {
        color: var(--sage);
        background: rgba(173, 184, 157, 0.1);
    }

    .popup-controls {
        position: absolute;
        top: 1rem;
        right: 1rem;
        display: flex;
        align-items: center;
        z-index: 10001;
    }

    .popup-logo {
        margin-bottom: 2rem;
    }

    .popup-logo h1 {
        font-size: 3rem;
        font-weight: bold;
        color: var(--sage);
        letter-spacing: 6px;
        margin-bottom: 0.5rem;
        text-transform: uppercase;
        font-family: 'BaticaSans', sans-serif;
    }

    .popup-logo p {
        font-size: 1rem;
        color: var(--sage);
        letter-spacing: 3px;
        text-transform: uppercase;
        font-family: 'BaticaSans', sans-serif;
    }

    .popup-main-heading {
        font-size: 2.5rem;
        font-weight: bold;
        color: var(--brown);
        margin-bottom: 2rem;
        line-height: 1.2;
        font-family: 'BaticaSans', sans-serif;
    }

    .popup-subtitle {
        font-size: 1.4rem;
        color: var(--text-gray);
        font-style: italic;
        margin-bottom: 1.5rem;
        font-family: 'BaticaSans', sans-serif;
    }

    .popup-description {
        font-size: 1.1rem;
        color: var(--text-dark);
        margin-bottom: 1rem;
        line-height: 1.6;
        font-family: 'BaticaSans', sans-serif;
    }

    .popup-highlight {
        font-weight: bold;
        color: var(--curry);
    }

    .popup-value-text {
        font-size: 1.1rem;
        color: var(--text-dark);
        margin-bottom: 2rem;
        line-height: 1.6;
        font-family: 'BaticaSans', sans-serif;
    }

    .popup-decorative-element {
        width: 80px;
        height: 2px;
        background-color: var(--sage);
        margin: 1.5rem auto;
    }

    .popup-form-container {
        max-width: 400px;
        margin: 0 auto;
    }

    .popup-form-group {
        margin-bottom: 1rem;
    }

    .popup-form-input {
        width: 100%;
        padding: 1rem 1.5rem;
        font-size: 1rem;
        border: 2px solid var(--brown);
        border-radius: 50px;
        background-color: var(--white);
        color: var(--text-dark);
        font-family: 'BaticaSans', sans-serif;
        outline: none;
        transition: all 0.3s ease;
    }

    .popup-form-input:focus {
        border-color: var(--curry);
        box-shadow: 0 0 10px rgba(207, 114, 58, 0.3);
        transform: translateY(-2px);
    }

    .popup-form-input::placeholder {
        color: var(--text-gray);
    }

    .popup-submit-btn {
        width: 100%;
        padding: 1rem 1.5rem;
        font-size: 1.1rem;
        font-weight: bold;
        color: var(--white);
        background: var(--brown);
        border: none;
        border-radius: 50px;
        cursor: pointer;
        font-family: 'BaticaSans', sans-serif;
        text-transform: uppercase;
        letter-spacing: 1px;
        transition: all 0.3s ease;
    }

    .popup-submit-btn:hover {
        background: var(--curry);
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(207, 114, 58, 0.3);
    }

    /* Popup Message styling */
    .popup-message {
        margin-bottom: 1.5rem;
        padding: 1rem;
        border-radius: 10px;
        font-size: 0.95rem;
        font-weight: 500;
        font-family: 'BaticaSans', sans-serif;
    }

    .popup-message.success {
        background-color: #d4edda;
        color: #155724;
        border: 2px solid #c3e6cb;
    }

    .popup-message.error {
        background-color: #f8d7da;
        color: #721c24;
        border: 2px solid #f5c6cb;
    }

    .popup-message.info {
        background-color: #d1ecf1;
        color: #0c5460;
        border: 2px solid #bee5eb;
    }

    /* Browse Menu Button */
    .popup-browse-btn {
        margin-top: 1.5rem;
        padding: 0.8rem 2rem;
        font-size: 1rem;
        font-weight: 600;
        color: var(--brown);
        background: transparent;
        border: 2px solid var(--brown);
        border-radius: 50px;
        cursor: pointer;
        font-family: 'BaticaSans', sans-serif;
        text-decoration: none;
        display: inline-block;
        transition: all 0.3s ease;
    }

    .popup-browse-btn:hover {
        background: var(--brown);
        color: var(--white);
        transform: translateY(-2px);
    }

    /* Responsive popup */
    @media (max-width: 768px) {
        .popup-container {
            padding: 2rem 1.5rem;
        }

        .popup-logo h1 {
            font-size: 2rem;
            letter-spacing: 3px;
        }

        .popup-main-heading {
            font-size: 1.8rem;
        }

        .popup-subtitle {
            font-size: 1.1rem;
        }

        .popup-description, .popup-value-text {
            font-size: 1rem;
        }
    }

    /* How It Works Section - Updated Styles */
    .hiw-items {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 2rem;
        margin-top: 3rem;
    }

    .hiw-step {
        position: relative; /* Add relative positioning */
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        padding: 0; /* Remove padding since image will fill */
        background: var(--white);
        border-radius: 15px;
        box-shadow: var(--shadow-soft);
        transition: var(--transition);
        overflow: hidden; /* Ensure image doesn't overflow rounded corners */
        height: 400px; /* Set fixed height for consistency */
    }

    .hiw-step--with-border {
        border: 2px solid var(--cream);
    }

    .hiw-step:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-medium);
    }

    .hiw-step-image {
        position: absolute; /* Position image absolutely to fill entire card */
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 15px; /* Match parent border radius */
        z-index: 1; /* Behind text content */
    }

    .hiw-step-text {
        position: absolute; /* Position text absolutely over image */
        bottom: 0;
        left: 0;
        right: 0;
        text-align: center;
        padding: 1.2rem; /* Reduced padding for smaller text area */
        background: rgba(255, 255, 255, 0.9); /* Semi-transparent white background */
        backdrop-filter: blur(10px); /* Add blur effect for better readability */
        border-radius: 0 0 15px 15px; /* Rounded bottom corners */
        z-index: 2; /* Above image */
        min-height: 120px; /* Fixed minimum height for consistency */
        display: flex;
        flex-direction: column;
        justify-content: center; /* Center content vertically */
    }

    .hiw-step-text .font-bold {
        font-size: 1.1rem; /* Reduced from 1.3rem */
        font-weight: 700;
        color: var(--text-dark);
        margin-bottom: 0.6rem; /* Reduced margin */
        font-family: 'BaticaSans', sans-serif;
    }

    .step-text {
        color: var(--text-dark); /* Changed from gray to dark for better contrast */
        line-height: 1.4; /* Reduced line height */
        font-family: 'BaticaSans', sans-serif;
        font-weight: 500; /* Slightly bolder for better readability */
        font-size: 0.9rem; /* Smaller text size */
        
        /* Limit to exactly 3 lines */
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
        height: calc(1.4em * 3); /* Fixed height for exactly 3 lines */
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .hiw-items {
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }
        
        .hiw-step {
            height: 300px; /* Smaller height for mobile */
        }
        
        .hiw-step-text {
            padding: 1rem; /* Reduced padding */
            min-height: 100px; /* Consistent height for mobile */
        }
        
        .hiw-step-text .font-bold {
            font-size: 1rem; /* Smaller title */
            margin-bottom: 0.5rem;
        }
        
        .step-text {
            font-size: 0.8rem; /* Smaller text */
            line-height: 1.3;
        }
    }

    @media (max-width: 480px) {
        .hiw-items {
            grid-template-columns: 1fr;
        }
        
        .hiw-step {
            height: 350px; /* Adjust height for single column */
        }
        
        .hiw-step-text {
            padding: 1rem; /* Consistent padding */
            min-height: 110px; /* Consistent height for single column */
        }
        
        .hiw-step-text .font-bold {
            font-size: 1rem;
        }
        
        .step-text {
            font-size: 0.85rem;
        }
    }

    /* Meet the Chefs Section */
    .chefs-section {
        padding: 5rem 2rem;
        background: linear-gradient(135deg, var(--cream) 0%, #f8f9fa 100%);
    }

    .chefs-container {
        max-width: 1200px;
        margin: 0 auto;
        text-align: center;
    }

    .chefs-title {
        font-size: 2.5rem;
        margin-bottom: 1rem;
        color: var(--text-dark);
        font-family: 'BaticaSans', sans-serif;
        font-weight: 700;
    }

    .chefs-subtitle {
        font-size: 1.2rem;
        color: var(--text-gray);
        margin-bottom: 3rem;
        font-family: 'BaticaSans', sans-serif;
        font-weight: 400;
    }

    .chefs-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 2.5rem;
        margin-top: 3rem;
    }

    .chef-card {
        background: var(--white);
        border-radius: 20px;
        padding: 2rem;
        box-shadow: var(--shadow-soft);
        transition: var(--transition);
        text-align: center;
        position: relative;
        overflow: hidden;
    }

    .chef-card:hover {
        transform: translateY(-10px);
        box-shadow: var(--shadow-medium);
    }

    .chef-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(135deg, var(--curry), var(--brown));
    }

    .chef-image {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        object-fit: cover;
        margin: 0 auto 1.5rem;
        border: 4px solid var(--cream);
        transition: var(--transition);
    }

    .chef-card:hover .chef-image {
        border-color: var(--curry);
        transform: scale(1.05);
    }

    .chef-name {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--text-dark);
        margin-bottom: 0.5rem;
        font-family: 'BaticaSans', sans-serif;
    }

    .chef-title {
        font-size: 1rem;
        color: var(--curry);
        margin-bottom: 1rem;
        font-family: 'BaticaSans', sans-serif;
        font-weight: 600;
    }

    .chef-description {
        color: var(--text-gray);
        line-height: 1.6;
        margin-bottom: 1.5rem;
        font-family: 'BaticaSans', sans-serif;
        font-size: 0.95rem;
    }

    .chef-credentials {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        justify-content: center;
    }

    .credential-badge {
        background: linear-gradient(135deg, var(--sage), var(--brown));
        color: var(--white);
        padding: 0.3rem 0.8rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        font-family: 'BaticaSans', sans-serif;
    }

    /* Reviews Section */
    .reviews-section {
        padding: 5rem 2rem;
        background: var(--white);
    }

    .reviews-container {
        max-width: 1200px;
        margin: 0 auto;
        text-align: center;
    }

    .reviews-title {
        font-size: 2.5rem;
        margin-bottom: 1rem;
        color: var(--text-dark);
        font-family: 'BaticaSans', sans-serif;
        font-weight: 700;
    }

    .reviews-subtitle {
        font-size: 1.2rem;
        color: var(--text-gray);
        margin-bottom: 3rem;
        font-family: 'BaticaSans', sans-serif;
        font-weight: 400;
    }

    .reviews-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 2rem;
        margin-top: 3rem;
    }

    .review-card {
        background: var(--white);
        border-radius: 15px;
        padding: 2rem;
        box-shadow: var(--shadow-soft);
        transition: var(--transition);
        text-align: left;
        border: 2px solid var(--cream);
        position: relative;
    }

    .review-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-medium);
        border-color: var(--curry);
    }

    .review-stars {
        display: flex;
        gap: 0.2rem;
        margin-bottom: 1rem;
    }

    .star {
        color: #ffc107;
        font-size: 1.2rem;
    }

    .review-text {
        color: var(--text-dark);
        line-height: 1.6;
        margin-bottom: 1.5rem;
        font-family: 'BaticaSans', sans-serif;
        font-size: 1rem;
        font-style: italic;
        position: relative;
    }

    .review-text::before {
        content: '"';
        font-size: 2rem;
        color: var(--curry);
        position: absolute;
        top: -0.5rem;
        left: -0.5rem;
        font-weight: 700;
    }

    .review-text::after {
        content: '"';
        font-size: 2rem;
        color: var(--curry);
        font-weight: 700;
    }

    .reviewer-info {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .reviewer-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--curry), var(--brown));
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--white);
        font-weight: 700;
        font-family: 'BaticaSans', sans-serif;
        font-size: 1.2rem;
    }

    .reviewer-details h4 {
        color: var(--text-dark);
        margin: 0;
        font-family: 'BaticaSans', sans-serif;
        font-weight: 600;
        font-size: 1rem;
    }

    .reviewer-details p {
        color: var(--text-gray);
        margin: 0;
        font-family: 'BaticaSans', sans-serif;
        font-size: 0.9rem;
    }

    .review-platform {
        position: absolute;
        top: 1rem;
        right: 1rem;
        background: var(--cream);
        color: var(--text-gray);
        padding: 0.3rem 0.8rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        font-family: 'BaticaSans', sans-serif;
    }

    /* Responsive Design for New Sections */
    @media (max-width: 768px) {
        .chefs-title, .reviews-title {
            font-size: 2rem;
        }

        .chefs-subtitle, .reviews-subtitle {
            font-size: 1rem;
        }

        .chefs-grid, .reviews-grid {
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }

        .chef-card, .review-card {
            padding: 1.5rem;
        }

        .chef-image {
            width: 100px;
            height: 100px;
        }
    }

    /* Hero Section Styles (using CSS variables from header.php) */
    .hero-section {
        min-height: 100vh;
        display: flex;
        align-items: center;
        padding: 2rem;
        background: url('./assets/image/padthai2.png') center/cover no-repeat, linear-gradient(135deg, var(--cream) 0%, #f8f9fa 100%);
        position: relative;
        overflow: hidden;
        margin-top: 110px; /* Account for header */
    }

    @media (max-width: 768px) {
        .hero-section {
            margin-top: 105px; /* Mobile spacing for header */
        }
    }

    @media (max-width: 480px) {
        .hero-section {
            margin-top: 100px; /* Small mobile spacing for header */
        }
    }

    .hero-container {
        position: relative;
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        max-width: 1400px;
        margin: 0 auto;
        padding: 0 2rem;
    }

    .hero-container .background {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, var(--white) 0%, var(--cream) 100%);
        z-index: -1;
    }

    .hero-content {
        flex: 1;
        max-width: 600px;
        padding: 3rem 2rem;
        z-index: 10;
    }

    .hero-content h1 {
        font-size: 4rem;
        margin-bottom: 1.5rem;
        font-weight: 800;
        color: var(--brown);
        line-height: 1.1;
        font-family: 'BaticaSans', sans-serif;
    }

    .hero-content p {
        font-size: 1.3rem;
        margin-bottom: 2rem;
        color: var(--text-gray);
        line-height: 1.6;
        font-family: 'BaticaSans', sans-serif;
    }

    .hero-form {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .zip-input-container {
        position: relative;
        width: 100%;
        max-width: 400px;
    }

    .zip-input {
        width: 100%;
        padding: 1rem 4rem 1rem 1.5rem; /* Added right padding for button */
        border: 2px solid var(--border-light);
        border-radius: 50px;
        font-size: 1.1rem;
        font-family: 'BaticaSans', sans-serif;
        background: var(--white);
        transition: all 0.3s;
        outline: none;
    }

    .zip-input:focus {
        border-color: var(--brown);
        box-shadow: 0 0 0 3px rgba(189, 147, 121, 0.1);
    }

    .zip-input::placeholder {
        color: #999;
        font-family: 'BaticaSans', sans-serif;
    }

    /* ZIP Input States */
    .zip-input.loading {
        border-color: var(--sage);
        background: #f8f9fa;
    }

    .zip-input.success {
        border-color: #28a745;
        background: #f8fff9;
    }

    .zip-input.error {
        border-color: #dc3545;
        background: #fff8f8;
    }

    /* ZIP Check Button */
    .zip-check-btn {
        position: absolute;
        right: 8px;
        top: 50%;
        transform: translateY(-50%);
        width: 40px;
        height: 40px;
        border: none;
        border-radius: 50%;
        background: var(--brown);
        color: var(--white);
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        font-weight: bold;
        display: none;
    }

    .zip-check-btn:hover:not(:disabled) {
        background: var(--curry);
        transform: translateY(-50%) scale(1.05);
    }

    .zip-check-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: translateY(-50%);
    }

    .zip-check-btn .loading-icon {
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* ZIP Feedback */
    .zip-feedback {
        margin-top: 0.5rem;
        padding: 0;
        border-radius: 12px;
        font-size: 0.9rem;
        font-family: 'BaticaSans', sans-serif;
        opacity: 0;
        transform: translateY(-10px);
        transition: all 0.4s ease; /* Slower transition */
        max-height: 0;
        overflow: hidden;
        position: relative;
        z-index: 100; /* Ensure it appears above other elements */
    }

    .zip-feedback.show {
        opacity: 1;
        transform: translateY(0);
        max-height: 800px; /* Increased max height */
        padding: 1rem;
    }

    .zip-feedback.success {
        color: #4c7355;
        border: 2px solid #8e9781ff;
        box-shadow: 0 4px 15px rgba(40, 167, 69, 0.2); /* Added shadow */
    }

    .zip-feedback.error {
        background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
        color: #721c24;
        border: 2px solid #f1b0b7;
        box-shadow: 0 4px 15px rgba(220, 53, 69, 0.2); /* Added shadow */
    }

    .zip-feedback.warning {
        background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
        color: #856404;
        border: 2px solid #fdd835;
        box-shadow: 0 4px 15px rgba(255, 193, 7, 0.2); /* Added shadow */
    }

    .feedback-icon {
        margin-right: 0.5rem;
        font-size: 1.1rem;
    }

    /* Delivery Information Styling */
    .delivery-info {
        margin-top: 1rem;
        padding: 1rem;
        background: rgba(255, 255, 255, 0.9);
        border-radius: 10px;
        border: 2px solid rgba(189, 147, 121, 0.2);
    }

    .delivery-info h4 {
        margin: 0 0 1rem 0;
        color: var(--brown);
        font-size: 1.1rem;
        font-weight: 700;
        font-family: 'BaticaSans', sans-serif;
    }

    .delivery-detail {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.5rem;
        padding: 0.3rem 0;
        border-bottom: 1px solid rgba(189, 147, 121, 0.1);
        font-size: 0.9rem;
    }

    .delivery-detail:last-child {
        border-bottom: none;
        margin-bottom: 0;
    }

    .delivery-detail span {
        color: var(--text-gray);
        font-weight: 500;
    }

    .delivery-detail strong {
        color: var(--text-dark);
        font-weight: 700;
    }

    /* ZIP Suggestions */
    .zip-suggestions {
        margin-top: 1rem;
        padding: 1rem;
        background: rgba(255, 255, 255, 0.9);
        border-radius: 10px;
        border: 2px solid rgba(189, 147, 121, 0.2);
    }

    .zip-suggestions h5 {
        margin: 0 0 0.8rem 0;
        color: var(--brown);
        font-size: 1rem;
        font-weight: 600;
        font-family: 'BaticaSans', sans-serif;
    }

    .suggestion-list {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .suggestion-item {
        background: var(--cream);
        color: var(--brown);
        padding: 0.4rem 0.8rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        border: 2px solid transparent;
        font-family: 'BaticaSans', sans-serif;
    }

    .suggestion-item:hover {
        background: var(--brown);
        color: var(--white);
        border-color: var(--curry);
        transform: translateY(-1px);
    }

    .order-now-button {
        background: var(--curry);
        color: var(--white);
        padding: 1rem 2.5rem;
        border: none;
        border-radius: 50px;
        cursor: pointer;
        font-weight: 700;
        font-size: 1.2rem;
        font-family: 'BaticaSans', sans-serif;
        text-decoration: none;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        max-width: 200px;
        margin-top: 1rem;
    }

    .order-now-button:hover:not(.disabled) {
        background: #b8631e;
        transform: translateY(-2px);
        box-shadow: var(--shadow-medium);
    }

    .order-now-button.disabled {
        background: #ccc;
        color: #666;
        cursor: not-allowed;
        opacity: 0.7;
    }

    .order-now-button.enabled {
        background: var(--curry);
        color: var(--white);
        cursor: pointer;
        opacity: 1;
    }

    /* Hero Videos - Vertical Sliding Animation */
    .hero-videos {
        flex: 1;
        height: 80vh;
        display: flex;
        gap: 1.5rem;
        align-items: center;
        justify-content: center;
    }

    .image-column {
        flex: 1;
        height: 100%;
        max-width: 300px;
        position: relative;
        overflow: hidden;
    }

    .image-slider, .image-slider-reverse {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
        animation-duration: 40s;
        animation-timing-function: linear;
        animation-iteration-count: infinite;
    }

    .image-slider {
        animation-name: slideDown;
    }

    .image-slider-reverse {
        animation-name: slideUp;
    }

    @keyframes slideDown {
        0% { transform: translateY(-50%); }
        100% { transform: translateY(0%); }
    }

    @keyframes slideUp {
        0% { transform: translateY(0%); }
        100% { transform: translateY(-50%); }
    }

    .video-container {
        position: relative;
        width: 100%;
        height: 200px;
        max-width: 200px;
        overflow: hidden;
        border-radius: 0px;
        background: linear-gradient(45deg, var(--brown), var(--sage));
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--white);
        font-weight: 600;
        font-family: 'BaticaSans', sans-serif;
        font-size: 1.2rem;
        clip-path: inset(0);
    }

    .video-container img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center center;
        border-radius: 12px;
    }

    .video-container video {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center center;
        border-radius: 12px;
    }

    /* Mobile video controls */
    @media (max-width: 768px) {
        .hero-videos {
            height: 300px;
        }
        
        .video-container {
            height: 120px;
        }
        
        .video-container video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center center;
            border-radius: 12px;
        }

        .zip-input-container {
            max-width: 100%;
        }

        .zip-input {
            padding: 0.9rem 3.5rem 0.9rem 1.2rem; /* Adjusted for mobile */
            font-size: 1rem;
        }

        .zip-check-btn {
            width: 36px;
            height: 36px;
            right: 6px;
            font-size: 1rem;
        }

        .order-now-button {
            max-width: 100%;
            font-size: 1rem;
            padding: 0.9rem 2rem;
        }

        .delivery-info {
            padding: 0.8rem;
        }

        .delivery-info h4 {
            font-size: 1rem;
        }

        .delivery-detail {
            font-size: 0.85rem;
            flex-direction: column;
            align-items: flex-start;
            gap: 0.2rem;
        }

        .zip-suggestions {
            padding: 0.8rem;
        }

        .zip-suggestions h5 {
            font-size: 0.9rem;
        }

        .suggestion-item {
            font-size: 0.75rem;
            padding: 0.3rem 0.6rem;
        }
    }

    .mobile-device .hero-video,
    .mobile-device video {
        pointer-events: auto;
    }

    @media (max-width: 480px) {
        .hero-videos {
            height: 250px;
        }
        
        .video-container {
            height: 100px;
            font-size: 0.8rem;
        }
    }

    /* Meal Cards Container */
    .meal-cards-container {
        position: relative;
        overflow: hidden;
        margin-bottom: 2rem;
    }

    .meal-cards-track {
        display: flex;
        gap: 1.5rem;
        transition: transform 0.3s ease;
        padding: 0 2rem;
    }

    .meal-card {
        flex: 0 0 320px;
        height: 400px;
        border-radius: 15px;
        position: relative;
        overflow: hidden;
        cursor: pointer;
        transition: transform 0.3s ease;
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
    }

    .meal-card:hover {
        transform: translateY(-5px);
    }

    .meal-card::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(
            180deg,
            transparent 0%,
            transparent 40%,
            rgba(189, 147, 121, 0.3) 70%,
            rgba(189, 147, 121, 0.8) 100%
        );
    }

    .meal-card-content {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        padding: 1.5rem;
        color: var(--white);
        z-index: 2;
    }

    .meal-card-title {
        font-size: 1.3rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        font-family: 'BaticaSans', sans-serif;
        color: var(--white) !important;
    }

    .meal-card-description {
        font-size: 0.9rem;
        opacity: 0.9;
        margin-bottom: 1rem;
        font-family: 'BaticaSans', sans-serif;
    }

    .meal-card-chef {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .chef-image {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        object-fit: cover;
    }

    .chef-info {
        font-size: 0.8rem;
        opacity: 0.8;
    }

    .chef-info span {
        font-weight: 400;
    }

    /* Scroll Controls */
    .scroll-controls {
        display: flex;
        justify-content: center;
        gap: 1rem;
        margin-top: 2rem;
    }

    .scroll-btn {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        border: 2px solid var(--brown);
        background: var(--white);
        color: var(--brown);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        transition: var(--transition);
    }

    .scroll-btn:hover {
        background: var(--brown);
        color: var(--white);
    }

    .scroll-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* Steps Section */
    .steps-section {
        padding: 5rem 2rem;
        background: var(--cream);
    }

    .steps-container {
        max-width: 1200px;
        margin: 0 auto;
        text-align: center;
    }

    .steps-title {
        font-size: 2.5rem;
        margin-bottom: 3rem;
        color: var(--brown);
        font-family: 'BaticaSans', sans-serif;
        font-weight: 700;
    }

    .steps-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 2rem;
    }

    .step {
        text-align: center;
        padding: 2rem;
    }

    .step-number {
        width: 80px;
        height: 80px;
        background: var(--brown);
        color: var(--white);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        font-weight: 700;
        font-family: 'BaticaSans', sans-serif;
        margin: 0 auto 1rem;
    }

    .step h3 {
        font-size: 1.3rem;
        margin-bottom: 1rem;
        color: var(--brown);
        font-family: 'BaticaSans', sans-serif;
        font-weight: 600;
    }

    .step p {
        color: var(--text-gray);
        font-family: 'BaticaSans', sans-serif;
    }

    /* Menu Navigation Container */
    .menu-nav-container {
        margin-bottom: 32px;
        width: 100%;
        padding: 20px 0;
        background: var(--cream);
        border-radius: 0;
        box-shadow: none;
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
        overflow-y: hidden;
        scrollbar-width: none;
        -ms-overflow-style: none;
        padding: 0 60px; /* Add padding to account for scroll buttons */
        max-width: 1200px;
        margin: 0 auto;
        width: 100%;
        scroll-behavior: smooth;
    }

    .menu-nav-wrapper::-webkit-scrollbar {
        display: none;
    }

    .menu-nav-list {
        display: flex;
        gap: 0;
        min-width: max-content;
        align-items: center;
        justify-content: center;
    }

    .menu-nav-container,
    .menu-nav-container * {
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
        -webkit-tap-highlight-color: transparent;
    }

    .menu-nav-item {
        display: flex;
        align-items: center;
        gap: 8px;
        height: 54px;
        padding: 0 16px;
        border: none;
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
        border-radius: var(--radius-sm);
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

    .menu-nav-item:hover .menu-nav-icon svg {
        fill: var(--brown);
    }

    .menu-nav-item.active .menu-nav-icon svg {
        fill: var(--brown);
    }

    .menu-nav-text {
        font-size: 14px;
        font-weight: 600;
    }

    /* Responsive design */
    @media (max-width: 768px) {
        .hero-container {
            flex-direction: column;
            text-align: center;
        }

        .hero-content {
            order: 1;
            max-width: 100%;
            padding: 2rem 1rem;
        }

        .hero-content h1 {
            font-size: 2.5rem;
        }

        .hero-content p {
            font-size: 1rem;
        }

        .hero-videos {
            order: 2;
            height: 300px;
            flex-direction: row;
            gap: 1rem;
        }

        .image-column {
            height: 100%;
        }

        .video-container {
            min-height: 120px;
            font-size: 0.9rem;
        }

        .steps-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .menu-nav-container {
            margin-bottom: 24px;
        }
        
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
        
        .menu-nav-item {
            padding: 0 12px;
            font-size: 13px;
        }
        
        .menu-nav-icon {
            width: 20px;
            height: 20px;
        }

        .meal-card {
            flex: 0 0 280px;
            height: 360px;
        }

        .meal-cards-track {
            padding: 0 1rem;
        }
    }

    @media (max-width: 480px) {
        .steps-grid {
            grid-template-columns: 1fr;
        }
        
        .hero-content h1 {
            font-size: 2rem;
        }
        
        .hero-videos {
            height: 250px;
        }
        
        .video-container {
            min-height: 100px;
            font-size: 0.8rem;
        }

        .meal-card {
            flex: 0 0 250px;
            height: 320px;
        }
    }

    /* Additional CSS that was potentially missing */
    
    /* Loading states and transitions */
    .loading {
        opacity: 0.7;
        pointer-events: none;
        position: relative;
    }

    .loading::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 20px;
        height: 20px;
        margin: -10px 0 0 -10px;
        border: 2px solid var(--cream);
        border-top: 2px solid var(--brown);
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    /* Error states */
    .error {
        border-color: #dc3545 !important;
        background-color: #fff5f5 !important;
    }

    .error-message {
        color: #dc3545;
        font-size: 0.875rem;
        margin-top: 0.25rem;
        font-family: 'BaticaSans', sans-serif;
    }

    /* Success states */
    .success {
        border-color: #9aa48bff !important;
        background-color: #f0fff4 !important;
    }

    .success-message {
        color: #68866fff;
        font-size: 0.875rem;
        margin-top: 0.25rem;
        font-family: 'BaticaSans', sans-serif;
    }

    /* Animation classes */
    .animate-in {
        animation: fadeInUp 0.6s ease-out forwards;
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .fade-in {
        animation: fadeIn 0.8s ease-out forwards;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    /* Accessibility */
    .skip-link:focus {
        clip: unset !important;
        height: auto !important;
        overflow: visible !important;
        position: absolute !important;
        width: auto !important;
    }

    .keyboard-navigation *:focus {
        outline: 2px solid var(--curry) !important;
        outline-offset: 2px !important;
    }

    /* Mobile menu enhancements */
    .menu-open {
        overflow: hidden;
    }

    .mobile-menu-toggle.active {
        transform: rotate(180deg);
    }

    /* Lazy loading */
    img.lazy {
        opacity: 0;
        transition: opacity 0.3s;
    }

    img.lazy.loaded {
        opacity: 1;
    }

    /* Scrollbar styling */
    ::-webkit-scrollbar {
        width: 8px;
    }

    ::-webkit-scrollbar-track {
        background: var(--cream);
    }

    ::-webkit-scrollbar-thumb {
        background: var(--brown);
        border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: var(--curry);
    }

    /* Print styles */
    @media print {
        .popup-overlay,
        .hero-videos,
        .scroll-controls,
        .menu-nav-scroll-btn {
            display: none !important;
        }
        
        body {
            font-size: 12pt;
            line-height: 1.5;
        }
        
        h1, h2, h3 {
            page-break-after: avoid;
        }
    }

    /* High contrast mode support */
    @media (prefers-contrast: high) {
        :root {
            --brown: #000000;
            --curry: #000000;
            --sage: #000000;
            --text-dark: #000000;
            --white: #ffffff;
            --cream: #ffffff;
        }
    }

    /* Reduced motion support */
    @media (prefers-reduced-motion: reduce) {
        *,
        *::before,
        *::after {
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
        }
        
        .image-slider,
        .image-slider-reverse {
            animation: none !important;
        }
    }

    /* Additional component styles */
    .badge {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.75rem;
        font-size: 0.75rem;
        font-weight: 600;
        border-radius: 50px;
        font-family: 'BaticaSans', sans-serif;
    }

    .badge-primary {
        background: var(--curry);
        color: var(--white);
    }

    .badge-secondary {
        background: var(--sage);
        color: var(--white);
    }

    .badge-success {
        background: #28a745;
        color: var(--white);
    }

    /* Tooltip component */
    .tooltip {
        position: relative;
        cursor: help;
    }

    .tooltip::before {
        content: attr(data-tooltip);
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        background: var(--text-dark);
        color: var(--white);
        padding: 0.5rem;
        border-radius: 4px;
        font-size: 0.875rem;
        white-space: nowrap;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
        z-index: 1000;
    }

    .tooltip:hover::before {
        opacity: 1;
        visibility: visible;
        transform: translateX(-50%) translateY(-5px);
    }

    /* Modal backdrop */
    .modal-backdrop {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 9999;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }

    .modal-backdrop.active {
        opacity: 1;
        visibility: visible;
    }

    /* Enhanced form styling */
    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 600;
        color: var(--text-dark);
        font-family: 'BaticaSans', sans-serif;
    }

    .form-control {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 2px solid var(--border-light);
        border-radius: 8px;
        font-size: 1rem;
        font-family: 'BaticaSans', sans-serif;
        transition: all 0.3s ease;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--brown);
        box-shadow: 0 0 0 3px rgba(189, 147, 121, 0.1);
    }

    /* Grid system */
    .row {
        display: flex;
        flex-wrap: wrap;
        margin: 0 -15px;
    }

    .col {
        flex: 1;
        padding: 0 15px;
    }

    .col-2 { flex: 0 0 16.666667%; max-width: 16.666667%; }
    .col-3 { flex: 0 0 25%; max-width: 25%; }
    .col-4 { flex: 0 0 33.333333%; max-width: 33.333333%; }
    .col-6 { flex: 0 0 50%; max-width: 50%; }
    .col-8 { flex: 0 0 66.666667%; max-width: 66.666667%; }
    .col-12 { flex: 0 0 100%; max-width: 100%; }

    @media (max-width: 768px) {
        .col,
        .col-2,
        .col-3,
        .col-4,
        .col-6,
        .col-8 {
            flex: 0 0 100%;
            max-width: 100%;
        }
    }

    /* Spacing utilities */
    .mt-1 { margin-top: 0.25rem; }
    .mt-2 { margin-top: 0.5rem; }
    .mt-3 { margin-top: 1rem; }
    .mt-4 { margin-top: 1.5rem; }
    .mt-5 { margin-top: 3rem; }

    .mb-1 { margin-bottom: 0.25rem; }
    .mb-2 { margin-bottom: 0.5rem; }
    .mb-3 { margin-bottom: 1rem; }
    .mb-4 { margin-bottom: 1.5rem; }
    .mb-5 { margin-bottom: 3rem; }

    .pt-1 { padding-top: 0.25rem; }
    .pt-2 { padding-top: 0.5rem; }
    .pt-3 { padding-top: 1rem; }
    .pt-4 { padding-top: 1.5rem; }
    .pt-5 { padding-top: 3rem; }

    .pb-1 { padding-bottom: 0.25rem; }
    .pb-2 { padding-bottom: 0.5rem; }
    .pb-3 { padding-bottom: 1rem; }
    .pb-4 { padding-bottom: 1.5rem; }
    .pb-5 { padding-bottom: 3rem; }

    /* Text utilities */
    .text-center { text-align: center; }
    .text-left { text-align: left; }
    .text-right { text-align: right; }

    .text-primary { color: var(--brown); }
    .text-secondary { color: var(--sage); }
    .text-success { color: #28a745; }
    .text-danger { color: #dc3545; }
    .text-warning { color: #ffc107; }
    .text-info { color: #17a2b8; }
    .text-muted { color: var(--text-gray); }

    .font-weight-normal { font-weight: 400; }
    .font-weight-bold { font-weight: 700; }
    .font-italic { font-style: italic; }

    /* Display utilities */
    .d-none { display: none; }
    .d-block { display: block; }
    .d-inline { display: inline; }
    .d-inline-block { display: inline-block; }
    .d-flex { display: flex; }

    /* Flex utilities */
    .justify-content-start { justify-content: flex-start; }
    .justify-content-center { justify-content: center; }
    .justify-content-end { justify-content: flex-end; }
    .justify-content-between { justify-content: space-between; }
    .justify-content-around { justify-content: space-around; }

    .align-items-start { align-items: flex-start; }
    .align-items-center { align-items: center; }
    .align-items-end { align-items: flex-end; }

    /* Border utilities */
    .border { border: 1px solid var(--border-light); }
    .border-top { border-top: 1px solid var(--border-light); }
    .border-bottom { border-bottom: 1px solid var(--border-light); }
    .border-0 { border: 0; }

    .rounded { border-radius: 0.375rem; }
    .rounded-lg { border-radius: 0.5rem; }
    .rounded-pill { border-radius: 50px; }
    .rounded-circle { border-radius: 50%; }

/* Custom scrollbar for popup container */
.popup-container::-webkit-scrollbar {
    width: 6px;
}

.popup-container::-webkit-scrollbar-track {
    background: transparent;
    border-radius: 10px;
    margin: 10px 0; /* This creates space from top/bottom rounded corners */
}

.popup-container::-webkit-scrollbar-thumb {
    background: rgba(189, 147, 121, 0.6);
    border-radius: 10px;
    border: none;
}

.popup-container::-webkit-scrollbar-thumb:hover {
    background: var(--brown);
}

.popup-container::-webkit-scrollbar-corner {
    background: transparent;
}
    </style>
</head>

<body class="has-header">
    <!-- Coming Soon Popup -->
    <div class="popup-overlay active" id="comingSoonPopup">
        <div class="popup-container" onclick="restorePopup(event)">
            <div class="popup-controls">
                <button class="popup-minimize" onclick="minimizePopup()" title="Minimize">−</button>
                <button class="popup-close" onclick="closeComingSoonPopup()" title="Close">&times;</button>
            </div>
            
            <!-- Floating circle content (hidden by default) -->
            <div class="floating-circle-content">
                S
            </div>
            
            <div class="popup-logo">
                <h1>Somdul</h1>
                <p>Table</p>
            </div>
            
            <h2 class="popup-main-heading">Somdul Table<br>is opening soon</h2>
            
            <div class="popup-decorative-element"></div>
            
            <p class="popup-subtitle">Until then...</p>
            
            <p class="popup-description">
                Join our exclusive preview list to be the first to experience 
                <span class="popup-highlight">authentic Thai flavors</span>, crafted with traditional recipes, 
                fresh ingredients, and the warmth of Thai hospitality.
            </p>
            
            <p class="popup-value-text">
                Be among the first to reserve your table and receive special 
                <span class="popup-highlight">opening week offers</span> exclusive to our 
                founding <em>&lt;somdul&gt;</em> family.
            </p>
            
            <?php if (!empty($popup_message)): ?>
                <div class="popup-message <?php echo $popup_message_type; ?>">
                    <?php echo $popup_message; ?>
                </div>
            <?php endif; ?>
            
            <form class="popup-form-container" method="POST" action="">
                <div class="popup-form-group">
                    <input type="text" 
                           name="popup_name" 
                           class="popup-form-input" 
                           placeholder="Your name" 
                           required>
                </div>
                
                <div class="popup-form-group">
                    <input type="email" 
                           name="popup_email" 
                           class="popup-form-input" 
                           placeholder="Your best email" 
                           required>
                </div>
                
                <button type="submit" name="submit_popup_signup" class="popup-submit-btn">Reserve My Spot!</button>
            </form>
            
            <a href="#" onclick="minimizePopup(); return false;" class="popup-browse-btn">Browse Our Preview Menu</a>
        </div>
    </div>

    <!-- Hero Vertical Slider -->
    <section class="hero-section" id="home" data-testid="hero-vertical-slider">
        <div class="hero-container" data-testid="hero-vertical-slider-image-columns-container">
            <div class="background"></div>
            
            <div class="hero-content">
                <h1>Fresh Thai Meals Delivered Daily</h1>
                <p>Experience authentic Thai flavors crafted by expert chefs and delivered fresh to your door. Healthy, delicious, and perfectly spiced to your preference.</p>
                
                <div class="hero-form">
                    <div class="zip-input-container">
                        <input type="text" id="zipCodeInput" class="zip-input" placeholder="Enter your ZIP code" maxlength="5">
                        <button type="button" id="zipCheckBtn" class="zip-check-btn" title="Check ZIP code">
                            <span class="check-icon">✓</span>
                            <span class="loading-icon" style="display: none;">⟳</span>
                        </button>
                    </div>
                    <div id="zipFeedback" class="zip-feedback"></div>
                    <a href="./menus.php" id="orderButton" class="order-now-button disabled">Check ZIP</a>
                </div>
            </div>
            
            <div class="hero-videos">
                <!-- Left Column - Sliding Up -->
                <div class="image-column" data-testid="image-column">
                    <div class="image-slider-reverse">
                        <div class="video-container">
                            <div class="video-container">
                                <video class="hero-video" muted loop playsinline>
                                    <source src="assets/videos/video1.mp4" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            </div>
                        </div>
                        <div class="video-container">
                            <img src="assets/image/image1.jpg" alt="Pad Thai">
                        </div>
                        <div class="video-container">
                            <div class="video-container">
                                <video class="hero-video" muted loop playsinline>
                                    <source src="assets/videos/video7.mp4" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            </div>
                        </div>
                        <div class="video-container">
                            <img src="assets/image/image2.jpg" alt="Pad Thai">
                        </div>
                        <div class="video-container">
                            <div class="video-container">
                                <video class="hero-video" muted loop playsinline>
                                    <source src="assets/videos/video2.mp4" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            </div>
                        </div>
                        <div class="video-container">
                            <img src="assets/image/image3.jpg" alt="Pad Thai">
                        </div>
                    </div>
                </div>

                <!-- Middle Column - Sliding Down -->
                <div class="image-column" data-testid="image-column">
                    <div class="image-slider">
                        <div class="video-container">
                            <div class="video-container">
                                <video class="hero-video" muted loop playsinline>
                                    <source src="assets/videos/video8.mp4" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            </div>
                        </div>
                        <div class="video-container">
                            <img src="assets/image/image4.jpg" alt="Pad Thai">
                        </div>
                        <div class="video-container">
                            <div class="video-container">
                                <video class="hero-video" muted loop playsinline>
                                    <source src="assets/videos/video3.mp4" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            </div>
                        </div>
                        <div class="video-container">
                            <img src="assets/image/image5.jpg" alt="Pad Thai">
                        </div>
                        <div class="video-container">
                            <div class="video-container">
                                <video class="hero-video" muted loop playsinline>
                                    <source src="assets/videos/video6.mp4" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            </div>
                        </div>
                        <div class="video-container">
                            <img src="assets/image/image6.jpg" alt="Pad Thai">
                        </div>
                    </div>
                </div>

                <!-- Right Column - Sliding Up -->
                <div class="image-column" data-testid="image-column">
                    <div class="image-slider-reverse">
                        <div class="video-container">
                            <div class="video-container">
                                <video class="hero-video" muted loop playsinline>
                                    <source src="assets/videos/video9.mp4" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            </div>
                        </div>
                        <div class="video-container">
                            <img src="assets/image/image7.jpg" alt="Pad Thai">
                        </div>
                        <div class="video-container">
                            <div class="video-container">
                                <video class="hero-video" muted loop playsinline>
                                    <source src="assets/videos/video9.mp4" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            </div>
                        </div>
                        <div class="video-container">
                            <img src="assets/image/image8.jpg" alt="Pad Thai">
                        </div>
                        <div class="video-container">
                            <div class="video-container">
                                <video class="hero-video" muted loop playsinline>
                                    <source src="assets/videos/video4.mp4" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            </div>
                        </div>
                        <div class="video-container">
                            <img src="assets/image/image9.jpg" alt="Pad Thai">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Menu Section -->
    <section class="menu-section" id="menu">
        <div class="menu-container">            
            <div class="menu-nav-container">
                <button class="menu-nav-scroll-btn menu-nav-scroll-left" id="menuScrollLeft" aria-label="Scroll left">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
                
                <div class="menu-nav-wrapper" id="menuNavWrapper">
                    <div class="menu-nav-list">
                        <?php if (empty($categories)): ?>
                            <!-- Fallback categories if database is empty -->
                            <button class="menu-nav-item active" data-category="all">
                                <span class="menu-nav-icon">
                                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <?php echo $default_icon; ?>
                                    </svg>
                                </span>
                                <span class="menu-nav-text">All Items</span>
                            </button>
                        <?php else: ?>
                            <!-- All items button -->
                            <button class="menu-nav-item active" data-category="all">
                                <span class="menu-nav-icon">
                                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>
                                    </svg>
                                </span>
                                <span class="menu-nav-text">All Items</span>
                            </button>
                            
                            <!-- Dynamic categories from database -->
                            <?php foreach ($categories as $index => $category): ?>
                                <?php 
                                $category_name = $category['name'] ?: $category['name_thai'];
                                $icon_path = $category_icons[$category_name] ?? $default_icon;
                                ?>
                                <button class="menu-nav-item" data-category="<?php echo htmlspecialchars($category['id']); ?>">
                                    <span class="menu-nav-icon">
                                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <?php echo $icon_path; ?>
                                        </svg>
                                    </span>
                                    <span class="menu-nav-text">
                                        <?php echo htmlspecialchars($category_name); ?>
                                    </span>
                                </button>
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

            <div class="meal-cards-container">
                <div class="meal-cards-track" id="mealCardsTrack">
                    <?php if (empty($featured_menus)): ?>
                        <!-- Fallback content if no menus in database -->
                        <div class="meal-card" style="background: linear-gradient(45deg, var(--brown), var(--sage));" data-menu-id="fallback-1" onclick="openMenuDetails('fallback-1')">
                            <div class="meal-card-content">
                                <h3 class="meal-card-title">Thai Green Curry</h3>
                                <p class="meal-card-description">Aromatic green curry with Thai basil and coconut milk</p>
                                <div class="meal-card-chef">
                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--white); display: flex; align-items: center; justify-content: center; color: var(--curry); font-weight: bold;">👨‍🍳</div>
                                    <div class="chef-info">
                                        <span>by</span> Chef Narong
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Dynamic menus from database -->
                        <?php foreach ($featured_menus as $menu): ?>
                            <?php
                            $menu_name = $menu['name'] ?: $menu['name_thai'];
                            $category_name = $menu['category_name'] ?: $menu['category_name_thai'];
                            $description = $menu['description'] ?: 'Authentic Thai cuisine made healthy';
                            
                            // Determine background image or fallback
                            $background_style = '';
                            if ($menu['main_image_url'] && file_exists($menu['main_image_url'])) {
                                $background_style = "background-image: url('" . htmlspecialchars($menu['main_image_url']) . "');";
                            } else {
                                // Fallback gradient based on category - using new color hierarchy
                                $gradients = [
                                    'Rice Bowls' => 'linear-gradient(45deg, var(--brown), var(--sage))',
                                    'Thai Curries' => 'linear-gradient(45deg, var(--sage), var(--cream))',
                                    'Noodle Dishes' => 'linear-gradient(45deg, var(--brown), var(--cream))',
                                    'default' => 'linear-gradient(45deg, var(--brown), var(--sage))'
                                ];
                                $gradient = $gradients[$category_name] ?? $gradients['default'];
                                $background_style = "background: $gradient;";
                            }
                            ?>
                            <div class="meal-card" 
                                 style="<?php echo $background_style; ?>" 
                                 data-menu-id="<?php echo htmlspecialchars($menu['id']); ?>" 
                                 onclick="openMenuDetails('<?php echo htmlspecialchars($menu['id']); ?>')">
                                <div class="meal-card-content">
                                    <h3 class="meal-card-title">
                                        <?php echo htmlspecialchars($menu_name); ?>
                                    </h3>
                                    <p class="meal-card-description">
                                        <?php echo htmlspecialchars($description); ?>
                                    </p>
                                    <div class="meal-card-chef">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Scroll Controls -->
            <div class="scroll-controls">
                <button class="scroll-btn" id="scrollLeft">←</button>
                <button class="scroll-btn" id="scrollRight">→</button>
            </div>
        </div>
    </section>

    <section class="steps-section" id="how-it-works">
        <div class="steps-container">
            <h2 class="steps-title">How It Works</h2>
            <div data-testid="hiw-items" class="hiw-items">
                <div data-testid="hiw-step" class="hiw-step hiw-step--with-border">
                    <img src="assets/image/weeklyplan.jpg" alt="Pick your weekly plan" class="hiw-step-image">
                    <div data-testid="hiw-step-text" class="hiw-step-text">
                        <p class="font-bold">Pick your weekly plan</p>
                        <p data-testid="step-text" class="step-text">Choose from 4 to 16 meals per week — you can pause, skip, or cancel deliveries at any time.</p>
                    </div>
                </div>
                <div data-testid="hiw-step" class="hiw-step hiw-step--with-border">
                    <img src="assets/image/selectingmeal.jpeg" alt="Pick your weekly plan" class="hiw-step-image">
                    <div data-testid="hiw-step-text" class="hiw-step-text">
                        <p class="font-bold">Select your meals</p>
                        <p data-testid="step-text" class="step-text">Browse our menu and select your meals — new offerings added weekly.</p>
                    </div>
                </div>
                <div data-testid="hiw-step" class="hiw-step hiw-step--with-border">
                    <img src="assets/image/chefcooking.jpg" alt="Pick your weekly plan" class="hiw-step-image">
                    <div data-testid="hiw-step-text" class="hiw-step-text">
                        <p class="font-bold">Let chefs work their magic</p>
                        <p data-testid="step-text" class="step-text">Every meal is made to order in small batches with the craft and artistry offered only by top-tier chefs.</p>
                    </div>
                </div>
                <div data-testid="hiw-step" class="hiw-step hiw-step--with-border">
                    <img src="assets/image/streetfood.jpg" alt="Pick your weekly plan" class="hiw-step-image">
                    <div data-testid="hiw-step-text" class="hiw-step-text">
                        <p class="font-bold">Sit back and enjoy</p>
                        <p data-testid="step-text" class="step-text">Delivered fresh every week, enjoy chef-crafted meals in minutes with no prep or cleanup.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Customer Reviews Section -->
    <section class="reviews-section" id="reviews">
        <div class="reviews-container">
            <h2 class="reviews-title">What Our Customers Say</h2>
            <p class="reviews-subtitle">Join thousands of satisfied customers who trust Somdul Table for authentic Thai cuisine</p>
            
            <div class="reviews-grid">
                <div class="review-card">
                    <div class="review-platform">Google</div>
                    <div class="review-stars">
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                    </div>
                    <p class="review-text">
                        The Pad Thai is absolutely incredible! It tastes exactly like what I had in Bangkok. The delivery is always on time and the packaging keeps everything fresh. I've been ordering for 6 months now and never disappointed.
                    </p>
                    <div class="reviewer-info">
                        <div class="reviewer-avatar">SM</div>
                        <div class="reviewer-details">
                            <h4>Sarah Mitchell</h4>
                            <p>Regular Customer • Los Angeles, CA</p>
                        </div>
                    </div>
                </div>

                <div class="review-card">
                    <div class="review-platform">Yelp</div>
                    <div class="review-stars">
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                    </div>
                    <p class="review-text">
                        As someone who lived in Thailand for 3 years, I can say this is the most authentic Thai food I've found in the US. The green curry is phenomenal and the spice levels are perfect. Highly recommend!
                    </p>
                    <div class="reviewer-info">
                        <div class="reviewer-avatar">DJ</div>
                        <div class="reviewer-details">
                            <h4>David Johnson</h4>
                            <p>Food Enthusiast • New York, NY</p>
                        </div>
                    </div>
                </div>

                <div class="review-card">
                    <div class="review-platform">TrustPilot</div>
                    <div class="review-stars">
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                    </div>
                    <p class="review-text">
                        Amazing quality and portion sizes! The mango sticky rice dessert is to die for. Customer service is excellent too - they accommodated my dietary restrictions perfectly. Will definitely order again!
                    </p>
                    <div class="reviewer-info">
                        <div class="reviewer-avatar">ER</div>
                        <div class="reviewer-details">
                            <h4>Emily Rodriguez</h4>
                            <p>Verified Customer • Chicago, IL</p>
                        </div>
                    </div>
                </div>

                <div class="review-card">
                    <div class="review-platform">Google</div>
                    <div class="review-stars">
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                    </div>
                    <p class="review-text">
                        The convenience of having restaurant-quality Thai food delivered weekly is unmatched. The flavors are complex and authentic, and I love trying new dishes each week. The packaging is eco-friendly too!
                    </p>
                    <div class="reviewer-info">
                        <div class="reviewer-avatar">MT</div>
                        <div class="reviewer-details">
                            <h4>Michael Thompson</h4>
                            <p>Busy Professional • Miami, FL</p>
                        </div>
                    </div>
                </div>

                <div class="review-card">
                    <div class="review-platform">Facebook</div>
                    <div class="review-stars">
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                    </div>
                    <p class="review-text">
                        I ordered for a dinner party and everyone was blown away! The Tom Yum soup was perfectly balanced and the presentation was beautiful. My Thai friends were impressed with the authenticity. 5 stars!
                    </p>
                    <div class="reviewer-info">
                        <div class="reviewer-avatar">LC</div>
                        <div class="reviewer-details">
                            <h4>Lisa Chen</h4>
                            <p>Event Host • San Francisco, CA</p>
                        </div>
                    </div>
                </div>

                <div class="review-card">
                    <div class="review-platform">Google</div>
                    <div class="review-stars">
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                    </div>
                    <p class="review-text">
                        Been ordering from Somdul Table for over a year. The consistency in quality is remarkable. Each dish is packed with flavor and arrives hot. The subscription model makes meal planning so easy!
                    </p>
                    <div class="reviewer-info">
                        <div class="reviewer-avatar">RK</div>
                        <div class="reviewer-details">
                            <h4>Robert Kim</h4>
                            <p>Loyal Customer • Seattle, WA</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

<script>
    // Page-specific JavaScript for index.php
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🏠 Index.php page loaded');
        
        // Check if mobile menu functions are available from header.php
        setTimeout(function() {
            console.log('🔍 Checking header functions:', {
                toggleMobileMenu: typeof window.toggleMobileMenu,
                fixHamburgerMenu: typeof window.fixHamburgerMenu,
                debugHamburgerButton: typeof window.debugHamburgerButton
            });
            
            // If on mobile and hamburger menu has issues, run debug
            if (window.innerWidth <= 768 && window.debugHamburgerButton) {
                const isWorking = window.debugHamburgerButton();
                if (!isWorking && window.fixHamburgerMenu) {
                    console.log('🔧 Running hamburger menu fix...');
                    window.fixHamburgerMenu();
                }
            }
        }, 500);

        // Initialize zipcode checker when DOM is ready
        initializeZipcodeChecker();
    });

    // Zipcode checking functionality
    function initializeZipcodeChecker() {
        const zipInput = document.getElementById('zipCodeInput');
        const zipCheckBtn = document.getElementById('zipCheckBtn');
        const zipFeedback = document.getElementById('zipFeedback');
        const orderButton = document.getElementById('orderButton');
        
        if (!zipInput || !zipCheckBtn || !zipFeedback || !orderButton) {
            console.warn('Zipcode checker elements not found');
            return;
        }
        
        let currentZipCode = '';
        let checkTimeout;
        
        // Format zip code input (numbers only)
        zipInput.addEventListener('input', function() {
            const oldValue = this.value;
            this.value = this.value.replace(/\D/g, '').substring(0, 5);
            
            console.log('📝 ZIP input changed:', this.value, 'Length:', this.value.length);
            
            // Auto-check when 5 digits entered
            if (this.value.length === 5) {
                clearTimeout(checkTimeout);
                checkTimeout = setTimeout(() => {
                    checkZipCode(this.value);
                }, 500);
            } else {
                // Only reset state and hide feedback if user completely cleared the input
                if (this.value.length === 0) {
                    console.log('🧹 Input cleared, resetting state');
                    resetZipCodeState();
                } else {
                    // Just reset the order button, but keep any existing feedback
                    resetOrderButton();
                    currentZipCode = '';
                    console.log('⚪ Partial input, keeping feedback visible');
                }
            }
        });
        
        // Enter key support
        zipInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && this.value.length === 5) {
                e.preventDefault();
                checkZipCode(this.value);
            }
        });
        
        // Check button click
        zipCheckBtn.addEventListener('click', function() {
            const zipCode = zipInput.value.trim();
            if (zipCode.length === 5) {
                checkZipCode(zipCode);
            } else {
                showFeedback('Please enter a 5-digit ZIP code', 'error');
            }
        });
        
        // Check zip code availability
        function checkZipCode(zipCode) {
            if (zipCode === currentZipCode) {
                return; // Don't check the same zip code again
            }
            
            currentZipCode = zipCode;
            setLoadingState(true);
            
            fetch(`ajax/check_zipcode.php?zip_code=${encodeURIComponent(zipCode)}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(data => {
                    setLoadingState(false);
                    handleZipCodeResponse(data, zipCode);
                })
                .catch(error => {
                    console.error('Zipcode check error:', error);
                    setLoadingState(false);
                    showFeedback('Unable to verify ZIP code. Please try again.', 'error');
                    resetOrderButton();
                });
        }
        
        // Handle API response
        function handleZipCodeResponse(data, zipCode) {
            if (data.success) {
                showSuccessFeedback(data, zipCode);
                enableOrderButton(zipCode);
                zipInput.className = 'zip-input success';
            } else {
                showErrorFeedback(data, zipCode);
                resetOrderButton();
                zipInput.className = 'zip-input error';
            }
        }
        
        // Show success feedback with delivery info
        function showSuccessFeedback(data, zipCode) {
            const deliveryInfo = data.zone ? `

            ` : '';
            
            showFeedback(
                `<span class="feedback-icon">✓</span>${data.message}${deliveryInfo}`,
                'success'
            );
        }
        
        // Show error feedback with suggestions
        function showErrorFeedback(data, zipCode) {
            let suggestionHTML = '';
            
            if (data.suggestions && data.suggestions.length > 0) {
                suggestionHTML = `
                    <div class="zip-suggestions">
                        <h5>🔍 We deliver to these nearby areas:</h5>
                        <div class="suggestion-list">
                            ${data.suggestions.map(suggestion => `
                                <span class="suggestion-item" onclick="selectSuggestion('${suggestion.zip_code}')">
                                    ${suggestion.zip_code} (${suggestion.area})
                                </span>
                            `).join('')}
                        </div>
                    </div>
                `;
            }
            
            const icon = data.type === 'warning' ? '⚠️' : '❌';
            showFeedback(
                `<span class="feedback-icon">${icon}</span>${data.message}${suggestionHTML}`,
                data.type || 'error'
            );
        }
        
        // Show feedback message
        function showFeedback(message, type) {
            console.log('🔔 Showing feedback:', type, message.substring(0, 50) + '...');
            
            zipFeedback.innerHTML = message;
            zipFeedback.className = `zip-feedback ${type}`;
            
            // Trigger animation with longer delay
            setTimeout(() => {
                zipFeedback.classList.add('show');
                console.log('✅ Feedback displayed with class:', zipFeedback.className);
            }, 50); // Increased from 10ms
            
            // Clear any existing hide timeout
            if (window.zipFeedbackTimeout) {
                clearTimeout(window.zipFeedbackTimeout);
                console.log('🚫 Cleared existing feedback timeout');
            }
            
            // Auto-hide only error messages after 10 seconds, keep success/warning messages
            if (type === 'error') {
                window.zipFeedbackTimeout = setTimeout(() => {
                    console.log('⏰ Auto-hiding error feedback after 10 seconds');
                    hideFeedback();
                }, 10000); // 10 seconds for errors
            } else {
                console.log('📌 Feedback will stay visible (success/warning type)');
            }
            // Success and warning messages stay visible until user interaction
        }
        
        // Hide feedback
        function hideFeedback() {
            console.log('👻 Hiding feedback');
            zipFeedback.classList.remove('show');
            setTimeout(() => {
                zipFeedback.innerHTML = '';
                zipFeedback.className = 'zip-feedback';
                console.log('🧹 Feedback cleared');
            }, 300);
        }
        
        // Clear feedback when user starts typing again
        // (This is now handled in the main input event listener above)
        
        // Set loading state
        function setLoadingState(loading) {
            const checkIcon = zipCheckBtn.querySelector('.check-icon');
            const loadingIcon = zipCheckBtn.querySelector('.loading-icon');
            
            if (loading) {
                console.log('⏳ Setting loading state ON');
                zipInput.className = 'zip-input loading';
                zipCheckBtn.disabled = true;
                checkIcon.style.display = 'none';
                loadingIcon.style.display = 'block';
                // Don't hide feedback during loading - user should see previous state
                console.log('📌 Keeping existing feedback during loading');
            } else {
                console.log('✅ Setting loading state OFF');
                zipCheckBtn.disabled = false;
                checkIcon.style.display = 'block';
                loadingIcon.style.display = 'none';
                // Don't change input class here - let the response handler do it
            }
        }
        
        // Enable order button with valid zip code
        function enableOrderButton(zipCode) {
            orderButton.className = 'order-now-button enabled';
            orderButton.textContent = 'View Menu';
            orderButton.href = `./menus.php?zip=${encodeURIComponent(zipCode)}`;
            orderButton.onclick = null;
            
            // Store zip code for later use
            sessionStorage.setItem('deliveryZipCode', zipCode);
        }
        
        // Reset order button
        function resetOrderButton() {
            orderButton.className = 'order-now-button disabled';
            orderButton.textContent = 'Check ZIP First';
            orderButton.href = '#';
            orderButton.onclick = function(e) {
                e.preventDefault();
                zipInput.focus();
                return false;
            };
            
            // Clear stored zip code
            sessionStorage.removeItem('deliveryZipCode');
        }
        
        // Reset zip code state
        function resetZipCodeState() {
            console.log('🔄 Resetting ZIP code state');
            zipInput.className = 'zip-input';
            // Only hide feedback when input is completely empty
            if (zipInput.value.length === 0) {
                console.log('🧹 Input is empty, hiding feedback');
                hideFeedback();
            } else {
                console.log('📝 Input has content, keeping feedback');
            }
            resetOrderButton();
            currentZipCode = '';
        }
        
        // Global function for suggestion clicks
        window.selectSuggestion = function(zipCode) {
            zipInput.value = zipCode;
            zipInput.focus();
            checkZipCode(zipCode);
        };
        
        // Make hideFeedback globally accessible
        window.hideFeedback = hideFeedback;
        
        // Check if there's a stored zip code from previous session
        const storedZip = sessionStorage.getItem('deliveryZipCode');
        if (storedZip && storedZip.length === 5) {
            zipInput.value = storedZip;
            setTimeout(() => {
                checkZipCode(storedZip);
            }, 500);
        }
    }

    // Function to handle meal card clicks - redirects to menus.php with menu ID
    function openMenuDetails(menuId) {
        // Redirect to menus.php with the menu ID as a parameter
        window.location.href = `menus.php?show_menu=${encodeURIComponent(menuId)}`;
    }

    // Coming Soon Popup Functions
    function minimizePopup() {
        const popup = document.getElementById('comingSoonPopup');
        popup.classList.remove('active');
        popup.classList.add('minimized');
        
        // Allow scrolling when minimized
        document.body.style.overflow = 'auto';
        
        // Store in sessionStorage that popup was minimized (not completely closed)
        sessionStorage.setItem('comingSoonPopupState', 'minimized');
    }

    function restorePopup(event) {
        // Only restore if the popup is minimized and we clicked on the minimized circle
        const popup = document.getElementById('comingSoonPopup');
        if (popup.classList.contains('minimized')) {
            // Prevent event bubbling if clicked on form elements
            if (event && (event.target.tagName === 'INPUT' || event.target.tagName === 'BUTTON' || event.target.tagName === 'FORM')) {
                return;
            }
            
            popup.classList.remove('minimized');
            popup.classList.add('active');
            
            // Prevent scrolling when popup is active
            document.body.style.overflow = 'hidden';
            
            // Update session storage
            sessionStorage.setItem('comingSoonPopupState', 'active');
        }
    }

    function closeComingSoonPopup() {
        const popup = document.getElementById('comingSoonPopup');
        popup.classList.remove('active', 'minimized');
        
        // Allow scrolling
        document.body.style.overflow = 'auto';
        
        // Store in sessionStorage that popup was completely closed
        sessionStorage.setItem('comingSoonPopupState', 'closed');
    }

    function minimizePopup() {
        const popup = document.getElementById('comingSoonPopup');
        popup.classList.remove('active');
        popup.classList.add('minimized');
        
        // Allow scrolling when minimized
        document.body.style.overflow = 'auto';
        
        // Store in sessionStorage that popup was minimized (not completely closed)
        sessionStorage.setItem('comingSoonPopupState', 'minimized');
    }

    // Check if popup should be shown
    function checkPopupDisplay() {
        const popup = document.getElementById('comingSoonPopup');
        const popupState = sessionStorage.getItem('comingSoonPopupState');
        
        // Handle different states
        if (popupState === 'closed') {
            // Completely hidden
            popup.classList.remove('active', 'minimized');
            document.body.style.overflow = 'auto';
        } else if (popupState === 'minimized') {
            // Show as minimized floating circle
            popup.classList.remove('active');
            popup.classList.add('minimized');
            document.body.style.overflow = 'auto';
        } else {
            // Show full popup (default for new visitors)
            popup.classList.add('active');
            popup.classList.remove('minimized');
            document.body.style.overflow = 'hidden';
        }
    }

    // Auto-hide success messages after 5 seconds
    function autoHideMessages() {
        const successMessage = document.querySelector('.popup-message.success');
        if (successMessage) {
            setTimeout(function() {
                successMessage.style.opacity = '0';
                setTimeout(function() {
                    successMessage.style.display = 'none';
                }, 500);
            }, 5000);
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Mobile video handling - FIXED: Prevent autoplay issues but keep videos visible
        function handleHeroVideos() {
            const videos = document.querySelectorAll('.hero-video');
            const isMobile = window.innerWidth <= 768;
            const isTouchDevice = 'ontouchstart' in window;
            
            videos.forEach(video => {
                if (isMobile || isTouchDevice) {
                    // On mobile/touch devices: disable autoplay but keep videos visible
                    video.pause();
                    video.currentTime = 0;
                    video.removeAttribute('autoplay');
                    video.muted = true;
                    video.controls = false; // Remove controls to prevent accidental fullscreen
                    video.playsInline = true; // Prevent fullscreen on iOS
                    video.preload = 'metadata'; // Reduced preloading
                    
                    // Prevent fullscreen and picture-in-picture
                    video.disablePictureInPicture = true;
                    video.setAttribute('webkit-playsinline', 'true');
                    
                    // Add click handler to play/pause instead of going fullscreen
                    video.addEventListener('click', function(e) {
                        e.preventDefault();
                        if (this.paused) {
                            this.play();
                        } else {
                            this.pause();
                        }
                    });
                    
                } else {
                    // On desktop: enable videos with autoplay
                    video.preload = 'metadata';
                    video.muted = true;
                    video.setAttribute('autoplay', '');
                    video.setAttribute('playsinline', '');
                    
                    // Try to play the video
                    const playPromise = video.play();
                    if (playPromise !== undefined) {
                        playPromise.catch(e => {
                            console.log('Video autoplay prevented:', e);
                            // Fallback: try to play on user interaction
                            document.addEventListener('click', () => {
                                video.play().catch(() => {});
                            }, { once: true });
                        });
                    }
                }
            });
        }

        // Detect mobile more accurately
        function isMobileDevice() {
            return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) 
                || window.innerWidth <= 768 
                || 'ontouchstart' in window;
        }

        // Enhanced mobile detection and video handling
        if (isMobileDevice()) {
            // Add mobile class to body for additional CSS targeting
            document.body.classList.add('mobile-device');
            
            // Handle videos properly for mobile
            document.addEventListener('DOMContentLoaded', function() {
                document.querySelectorAll('video').forEach(video => {
                    // Prevent autoplay but don't hide videos
                    video.autoplay = false;
                    video.muted = true;
                    video.playsInline = true;
                    video.controls = false;
                    video.disablePictureInPicture = true;
                    
                    // Prevent fullscreen on tap
                    video.addEventListener('webkitbeginfullscreen', function(e) {
                        e.preventDefault();
                    });
                    
                    video.addEventListener('fullscreenchange', function(e) {
                        if (document.fullscreenElement === video) {
                            document.exitFullscreen();
                        }
                    });
                });
            });
        }

        // Initial video setup
        handleHeroVideos();

        // Handle window resize
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(handleHeroVideos, 250);
        });

        // Check popup display on page load
        checkPopupDisplay();
        
        // Auto-hide messages
        autoHideMessages();
        
        // Add interactive hover effects to popup form inputs
        document.querySelectorAll('.popup-form-input').forEach(input => {
            input.addEventListener('focus', function() {
                this.style.transform = 'translateY(-2px)';
                this.style.boxShadow = '0 4px 15px rgba(173, 184, 157, 0.2)';
            });
            
            input.addEventListener('blur', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = 'none';
            });
        });

        // Close popup when clicking outside (only when not minimized)
        document.getElementById('comingSoonPopup').addEventListener('click', function(e) {
            if (e.target === this && !this.classList.contains('minimized')) {
                minimizePopup();
            }
        });

        // Close popup on Escape key (minimize it instead)
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const popup = document.getElementById('comingSoonPopup');
                if (popup.classList.contains('active')) {
                    minimizePopup();
                }
            }
        });

        // Menu functionality
        const menuItems = document.querySelectorAll('.menu-nav-item');
        const mealCardsTrack = document.getElementById('mealCardsTrack');
        const scrollLeftBtn = document.getElementById('scrollLeft');
        const scrollRightBtn = document.getElementById('scrollRight');
        
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
        
        // Initialize scroll tracking
        let currentScroll = 0;
        const cardWidth = 320 + 24; // card width + gap
        
        menuItems.forEach(item => {
            item.addEventListener('click', function() {
                // Remove active class from all items
                menuItems.forEach(i => i.classList.remove('active'));
                
                // Add active class to clicked item
                this.classList.add('active');
                
                // Get category for filtering
                const category = this.getAttribute('data-category');
                console.log('Selected category:', category);
                
                // Show loading state
                showLoadingState();
                
                // Filter menus by category
                filterMenusByCategory(category);
            });
        });
        
        function showLoadingState() {
            mealCardsTrack.innerHTML = `
                <div class="loading-container" style="display: flex; justify-content: center; align-items: center; width: 100%; height: 400px;">
                    <div class="loading-spinner" style="
                        border: 4px solid #ece8e1;
                        border-top: 4px solid #bd9379;
                        border-radius: 50%;
                        width: 50px;
                        height: 50px;
                        animation: spin 1s linear infinite;
                    "></div>
                </div>
            `;
            
            // Add spin animation if not already defined
            if (!document.querySelector('#spin-animation')) {
                const style = document.createElement('style');
                style.id = 'spin-animation';
                style.textContent = `
                    @keyframes spin {
                        0% { transform: rotate(0deg); }
                        100% { transform: rotate(360deg); }
                    }
                `;
                document.head.appendChild(style);
            }
        }
        
        function filterMenusByCategory(categoryId) {
            // Make AJAX request to filter menus
            const url = `ajax/filter_menus.php?category=${encodeURIComponent(categoryId)}&limit=10`;
            
            fetch(url)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        updateMenuCards(data.menus);
                    } else {
                        console.error('Filter error:', data.error);
                        showErrorState(data.error || 'Failed to load menus');
                    }
                })
                .catch(error => {
                    console.error('Network error:', error);
                    showErrorState('Network error occurred. Please try again.');
                });
        }
        
        function updateMenuCards(menus) {
            if (!menus || menus.length === 0) {
                showEmptyState();
                return;
            }
            
            // Generate HTML for menu cards
            const cardsHTML = menus.map(menu => {
                // Log debug info to console
                console.log('Menu:', menu.name, 'Image URL:', menu.image_url, 'Has Image:', menu.has_image);
                
                // Determine the complete background style
                let backgroundStyle = '';
                if (menu.has_image && menu.image_url) {
                    // Use background-image with proper sizing and positioning
                    backgroundStyle = `background-image: url('${menu.image_url}'); background-size: cover; background-position: center; background-repeat: no-repeat;`;
                } else {
                    // Use the gradient from the server response
                    backgroundStyle = menu.background_style;
                }
                
                return `
                    <div class="meal-card" 
                         style="${backgroundStyle}" 
                         data-menu-id="${menu.id}" 
                         onclick="openMenuDetails('${menu.id}')">
                        <div class="meal-card-content">
                            <h3 class="meal-card-title">${menu.name}</h3>
                            <p class="meal-card-description">${menu.description}</p>
                            <div class="meal-card-chef">
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
            
            // Update the track with new cards
            mealCardsTrack.innerHTML = cardsHTML;
            
            // Reset scroll position
            currentScroll = 0;
            mealCardsTrack.style.transform = `translateX(${currentScroll}px)`;
            
            // Update scroll buttons
            updateScrollButtons();
            
            // Update menu navigation scroll buttons
            setTimeout(updateMenuScrollButtons, 100);
            
            // Re-enable touch/swipe functionality for new cards
            setupTouchEvents();
        }
        
        function showEmptyState() {
            mealCardsTrack.innerHTML = `
                <div class="empty-state" style="
                    display: flex; 
                    flex-direction: column; 
                    justify-content: center; 
                    align-items: center; 
                    width: 100%; 
                    height: 400px; 
                    text-align: center;
                    color: var(--text-gray);
                ">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🍽️</div>
                    <h3 style="color: var(--text-dark); margin-bottom: 0.5rem;">No meals found</h3>
                    <p>Try selecting a different category or check back later for new additions.</p>
                </div>
            `;
            updateScrollButtons();
            setTimeout(updateMenuScrollButtons, 100);
        }
        
        function showErrorState(message) {
            mealCardsTrack.innerHTML = `
                <div class="error-state" style="
                    display: flex; 
                    flex-direction: column; 
                    justify-content: center; 
                    align-items: center; 
                    width: 100%; 
                    height: 400px; 
                    text-align: center;
                    color: var(--text-gray);
                ">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">⚠️</div>
                    <h3 style="color: var(--text-dark); margin-bottom: 0.5rem;">Oops! Something went wrong</h3>
                    <p>${message}</p>
                    <button onclick="location.reload()" style="
                        margin-top: 1rem;
                        padding: 0.8rem 1.5rem;
                        background: var(--brown);
                        color: white;
                        border: none;
                        border-radius: 50px;
                        cursor: pointer;
                        font-family: 'BaticaSans', sans-serif;
                        font-weight: 600;
                    ">Retry</button>
                </div>
            `;
            updateScrollButtons();
            setTimeout(updateMenuScrollButtons, 100);
        }
        
        function updateScrollButtons() {
            const visibleCards = Math.floor(window.innerWidth / cardWidth);
            const maxScroll = -(mealCardsTrack.children.length - visibleCards) * cardWidth;
            
            scrollLeftBtn.disabled = currentScroll >= 0;
            scrollRightBtn.disabled = currentScroll <= maxScroll || mealCardsTrack.children.length <= visibleCards;
        }
        
        function setupTouchEvents() {
            let startX, currentX, isDragging = false;
            
            // Remove existing listeners to avoid duplicates
            mealCardsTrack.removeEventListener('touchstart', handleTouchStart);
            mealCardsTrack.removeEventListener('touchmove', handleTouchMove);
            mealCardsTrack.removeEventListener('touchend', handleTouchEnd);
            
            // Add new listeners
            mealCardsTrack.addEventListener('touchstart', handleTouchStart);
            mealCardsTrack.addEventListener('touchmove', handleTouchMove);
            mealCardsTrack.addEventListener('touchend', handleTouchEnd);
            
            function handleTouchStart(e) {
                startX = e.touches[0].clientX;
                isDragging = true;
            }
            
            function handleTouchMove(e) {
                if (!isDragging) return;
                currentX = e.touches[0].clientX;
                const diffX = currentX - startX;
                const newScroll = currentScroll + diffX;
                
                const visibleCards = Math.floor(window.innerWidth / cardWidth);
                const maxScroll = -(mealCardsTrack.children.length - visibleCards) * cardWidth;
                
                if (newScroll <= 0 && newScroll >= maxScroll) {
                    mealCardsTrack.style.transform = `translateX(${newScroll}px)`;
                }
            }
            
            function handleTouchEnd(e) {
                if (!isDragging) return;
                isDragging = false;
                
                const diffX = currentX - startX;
                const visibleCards = Math.floor(window.innerWidth / cardWidth);
                const maxScroll = -(mealCardsTrack.children.length - visibleCards) * cardWidth;
                
                if (Math.abs(diffX) > 50) {
                    if (diffX > 0) {
                        // Swipe right (scroll left)
                        currentScroll = Math.min(currentScroll + cardWidth, 0);
                    } else {
                        // Swipe left (scroll right)
                        currentScroll = Math.max(currentScroll - cardWidth, maxScroll);
                    }
                    mealCardsTrack.style.transform = `translateX(${currentScroll}px)`;
                    updateScrollButtons();
                }
            }
        }
        
        // Scroll button functionality
        scrollLeftBtn.addEventListener('click', () => {
            currentScroll = Math.min(currentScroll + cardWidth * 2, 0);
            mealCardsTrack.style.transform = `translateX(${currentScroll}px)`;
            updateScrollButtons();
        });
        
        scrollRightBtn.addEventListener('click', () => {
            const visibleCards = Math.floor(window.innerWidth / cardWidth);
            const maxScroll = -(mealCardsTrack.children.length - visibleCards) * cardWidth;
            currentScroll = Math.max(currentScroll - cardWidth * 2, maxScroll);
            mealCardsTrack.style.transform = `translateX(${currentScroll}px)`;
            updateScrollButtons();
        });
        
        // Initialize scroll buttons
        updateScrollButtons();
        
        // Setup initial touch events
        setupTouchEvents();
        
        // Update on window resize
        window.addEventListener('resize', () => {
            const visibleCards = Math.floor(window.innerWidth / cardWidth);
            const maxScroll = -(mealCardsTrack.children.length - visibleCards) * cardWidth;
            if (currentScroll < maxScroll) {
                currentScroll = maxScroll;
                mealCardsTrack.style.transform = `translateX(${currentScroll}px)`;
            }
            updateScrollButtons();
        });
    });
    
    // Show popup if form was submitted successfully
    <?php if (!empty($popup_message) && $popup_message_type === 'success'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            // Clear session storage to show popup with success message
            sessionStorage.removeItem('comingSoonPopupState');
            checkPopupDisplay();
        });
    <?php endif; ?>
</script>
</body>
</html>