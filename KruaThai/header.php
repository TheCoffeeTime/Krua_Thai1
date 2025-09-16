<?php
/**
 * Somdul Table - Updated Header Component with Dynamic Promotional Banner
 * File: header.php
 * Include this file in every page that needs the navigation and promo banner
 */

// Ensure session is started (if not already started)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load promotional banner settings from database
$promoSettings = [];
try {
    if (isset($pdo)) {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value, setting_type FROM system_settings WHERE category = 'promotion' AND is_public = 1");
        $stmt->execute();
        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($settings as $setting) {
            $value = $setting['setting_value'];
            
            // Convert boolean strings to actual booleans
            if ($setting['setting_type'] === 'boolean') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }
            
            $promoSettings[$setting['setting_key']] = $value;
        }
    }
} catch (Exception $e) {
    error_log("Error loading promotional settings: " . $e->getMessage());
}

// Set default values if database settings are not available
$promoDefaults = [
    'promo_banner_enabled' => true,
    'promo_banner_desktop_text' => '50% OFF First Week + Free Cookies for Life',
    'promo_banner_mobile_text' => '50% OFF + Free Cookies',
    'promo_banner_left_icon' => '🪴',
    'promo_banner_right_icon' => '🎉',
    'promo_banner_background_color' => '#cf723a'
];

// Merge with defaults
$promoSettings = array_merge($promoDefaults, $promoSettings);

// Initialize notification data if user is logged in
$notificationData = null;
if (isset($_SESSION['user_id'])) {
    // Only load notification manager if user is logged in
    if (file_exists('NotificationManager.php')) {
        require_once 'NotificationManager.php';
        try {
            $notificationManager = new NotificationManager($pdo);
            $userId = $_SESSION['user_id'];
            $unreadCount = $notificationManager->getUnreadCount($userId);
            $recentNotifications = $notificationManager->getUserNotifications($userId, false, 5);
            
            $notificationData = [
                'unreadCount' => $unreadCount,
                'notifications' => $recentNotifications
            ];
        } catch (Exception $e) {
            error_log("Notification system error: " . $e->getMessage());
            // Gracefully continue without notifications if there's an error
            $notificationData = [
                'unreadCount' => 0,
                'notifications' => []
            ];
        }
    }
}
?>

<style>
/* Header Component Styles */

/* BaticaSans Font Import - Local Files */
@font-face {
    font-family: 'BaticaSans';
    src: url('./Font/BaticaSans-Regular.woff2') format('woff2'),
        url('./Font/BaticaSans-Regular.woff') format('woff'),
        url('./Font/BaticaSans-Regular.ttf') format('truetype');
    font-weight: 400;
    font-style: normal;
    font-display: swap;
}

@font-face {
    font-family: 'BaticaSans';
    src: url('./Font/BaticaSans-Italic.woff2') format('woff2'),
        url('./Font/BaticaSans-Italic.woff') format('woff'),
        url('./Font/BaticaSans-Italic.ttf') format('truetype');
    font-weight: 400;
    font-style: italic;
    font-display: swap;
}

@font-face {
    font-family: 'BaticaSans';
    src: url('./Font/BaticaSans-Regular.woff2') format('woff2'),
        url('./Font/BaticaSans-Regular.woff') format('woff'),
        url('./Font/BaticaSans-Regular.ttf') format('truetype');
    font-weight: 500;
    font-style: normal;
    font-display: swap;
}

@font-face {
    font-family: 'BaticaSans';
    src: url('./Font/BaticaSans-Regular.woff2') format('woff2'),
        url('./Font/BaticaSans-Regular.woff') format('woff'),
        url('./Font/BaticaSans-Regular.ttf') format('truetype');
    font-weight: 700;
    font-style: normal;
    font-display: swap;
}

/* CSS Variables - Complete Somdul Table Design System */
:root {
    /* LEVEL 1 (MOST IMPORTANT): BROWN #bd9379 + WHITE */
    --brown: #bd9379;
    --white: #ffffff;
    
    /* LEVEL 2 (SECONDARY): CREAM #ece8e1 */
    --cream: #ece8e1;
    
    /* LEVEL 3 (SUPPORTING): SAGE #adb89d */
    --sage: #adb89d;
    
    /* LEVEL 4 (ACCENT/CONTRAST - LEAST USED): CURRY #cf723a */
    --curry: #cf723a;
    
    /* Text colors using brown hierarchy */
    --text-dark: #2c3e50;
    --text-gray: #7f8c8d;
    --border-light: #d4c4b8; /* Brown-tinted border */
    
    /* Shadows using brown as base (Level 1) */
    --shadow-soft: 0 4px 12px rgba(189, 147, 121, 0.15);
    --shadow-medium: 0 8px 24px rgba(189, 147, 121, 0.25);
    
    /* Border radius */
    --radius-sm: 8px;
    --radius-md: 12px;
    --radius-lg: 16px;
    
    /* Transitions */
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    
    /* Dynamic promotional banner background */
    --promo-bg: <?php echo htmlspecialchars($promoSettings['promo_banner_background_color']); ?>;
}

/* Global reset */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'BaticaSans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    line-height: 1.6;
    color: var(--text-dark);
    background-color: var(--white);
    font-weight: 400;
}

/* Typography using BaticaSans - LEVEL 1: Brown for headings */
h1, h2, h3, h4, h5, h6 {
    font-family: 'BaticaSans', sans-serif;
    font-weight: 700;
    line-height: 1.2;
    color: var(--brown); /* LEVEL 1: Brown instead of text-dark */
}

/* Mobile Navigation Hamburger Menu */
.mobile-menu-toggle {
    display: none;
    background: none;
    border: none;
    cursor: pointer;
    padding: 0.5rem;
    z-index: 1001;
    position: relative;
    transition: var(--transition);
}

.mobile-menu-toggle:hover {
    background: rgba(189, 147, 121, 0.1);
    border-radius: 8px;
}

.mobile-menu-toggle:active {
    transform: scale(0.95);
    background: rgba(189, 147, 121, 0.2);
}

.hamburger {
    width: 24px;
    height: 20px;
    position: relative;
    cursor: pointer;
}

.hamburger span {
    display: block;
    position: absolute;
    height: 3px;
    width: 100%;
    background: var(--brown);
    border-radius: 2px;
    opacity: 1;
    left: 0;
    transform: rotate(0deg);
    transition: 0.25s ease-in-out;
}

.hamburger span:nth-child(1) {
    top: 0px;
}

.hamburger span:nth-child(2),
.hamburger span:nth-child(3) {
    top: 8px;
}

.hamburger span:nth-child(4) {
    top: 16px;
}

.hamburger.open span:nth-child(1) {
    top: 8px;
    width: 0%;
    left: 50%;
}

.hamburger.open span:nth-child(2) {
    transform: rotate(45deg);
}

.hamburger.open span:nth-child(3) {
    transform: rotate(-45deg);
}

.hamburger.open span:nth-child(4) {
    top: 8px;
    width: 0%;
    left: 50%;
}

/* Mobile Navigation Menu - Default Mobile Behavior */
.mobile-nav-menu {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100vh;
    background: var(--white);
    z-index: 1100; /* Higher than navbar */
    transform: translateX(-100%);
    transition: transform 0.3s ease;
}

.mobile-nav-menu.active {
    transform: translateX(0);
    display: block; /* Ensure it's visible when active */
}

/* DESKTOP DROPDOWN BEHAVIOR - NEW */
@media (min-width: 769px) {
    .mobile-nav-menu {
        position: absolute;
        top: 100%;
        left: 0;
        width: 280px;
        height: auto;
        max-height: 400px;
        background: var(--white);
        border-radius: 0;
        box-shadow: var(--shadow-medium);
        border: 2px solid var(--cream);
        transform: translateY(-10px);
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
        overflow: hidden;
    }

    .mobile-nav-menu.active {
        transform: translateY(0);
        opacity: 1;
        visibility: visible;
        display: block;
    }

    /* Desktop dropdown positioning relative to navbar */
    .navbar {
        position: relative;
    }

    /* Hide mobile menu header for desktop dropdown */
    .mobile-menu-header {
        display: none;
    }

    /* Adjust navigation links for desktop dropdown */
    .mobile-nav-links {
        padding: 1rem;
        max-height: 300px;
        overflow-y: auto;
    }

    .mobile-nav-links li {
        margin-bottom: 0.5rem;
    }

    .mobile-nav-links a {
        padding: 0.8rem 1rem;
        font-size: 1rem;
        border-radius: var(--radius-md);
        border-bottom: none;
        transition: all 0.3s ease;
    }

    .mobile-nav-links a:hover {
        background: var(--cream);
        color: var(--brown);
    }
}

/* Mobile Menu Header with Close Button */
.mobile-menu-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 2rem;
    border-bottom: 2px solid var(--cream);
    background: var(--cream);
}

.mobile-menu-logo {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.mobile-menu-logo img {
    height: 50px;
    width: auto;
}

.mobile-menu-title {
    font-family: 'BaticaSans', sans-serif;
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--brown);
}

.mobile-close-btn {
    background: none;
    border: none;
    font-size: 2rem;
    color: var(--brown);
    cursor: pointer;
    padding: 0.5rem;
    transition: var(--transition);
    border-radius: 50%;
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.mobile-close-btn:hover {
    background: rgba(189, 147, 121, 0.1);
    transform: scale(1.1);
}

.mobile-close-btn:active {
    transform: scale(0.95);
    background: rgba(189, 147, 121, 0.2);
}

.mobile-nav-links {
    list-style: none;
    padding: 2rem;
    margin: 0;
}

.mobile-nav-links li {
    margin-bottom: 1.5rem;
}

.mobile-nav-links a {
    display: block;
    padding: 1rem 0;
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--text-dark);
    text-decoration: none;
    border-bottom: 1px solid var(--cream);
    transition: color 0.3s ease;
    font-family: 'BaticaSans', sans-serif;
}

.mobile-nav-links a:hover {
    color: var(--brown);
}

/* Main Navbar */
.navbar {
    position: fixed;
    top: 38px;
    left: 0;
    right: 0;
    background: #ece8e1;
    backdrop-filter: blur(10px);
    z-index: 1000;
    transition: var(--transition);
    box-shadow: var(--shadow-soft);
}

.navbar, .navbar * {
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;
    -webkit-tap-highlight-color: transparent;
}

.navbar nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
}

.logo {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    text-decoration: none;
    color: var(--brown);
}

.logo-icon {
    width: 45px;
    height: 45px;
    background: var(--brown);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--white);
    font-size: 1.5rem;
    font-family: 'BaticaSans', sans-serif;
    font-weight: 700;
}

.logo-text {
    font-size: 1.8rem;
    font-weight: 800;
    color: var(--brown);
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
    font-size: 1.1rem; 
}

.nav-links a:hover {
    color: var(--brown);
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
    background: var(--brown);
    color: var(--white);
    box-shadow: var(--shadow-soft);
}

.btn-primary:hover {
    background: #a8855f;
    transform: translateY(-2px);
    box-shadow: var(--shadow-medium);
}

.btn-secondary {
    background: transparent;
    color: var(--brown);
    border: 2px solid var(--brown);
}

.btn-secondary:hover {
    background: var(--brown);
    color: var(--white);
}

/* Profile Icon Styles */
.profile-link {
    text-decoration: none;
    transition: var(--transition);
}

.profile-icon {
    width: 45px;
    height: 45px;
    background: var(--brown);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--white);
    transition: var(--transition);
    box-shadow: var(--shadow-soft);
}

.profile-icon:hover {
    background: #a8855f;
    transform: translateY(-2px);
    box-shadow: var(--shadow-medium);
}

.profile-icon svg {
    width: 24px;
    height: 24px;
}

/* Notification Widget Styles - Integrated into Header Design */
.notification-wrapper {
    position: relative;
    display: inline-block;
}

.notification-bell {
    width: 45px;
    height: 45px;
    background: var(--cream);
    border: 2px solid var(--brown);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--brown);
    cursor: pointer;
    transition: var(--transition);
    position: relative;
    box-shadow: var(--shadow-soft);
}

.notification-bell:hover {
    background: var(--brown);
    color: var(--white);
    transform: translateY(-2px);
    box-shadow: var(--shadow-medium);
}

.notification-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background: var(--curry);
    color: var(--white);
    border-radius: 50%;
    width: 22px;
    height: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 700;
    font-family: 'BaticaSans', sans-serif;
    border: 2px solid var(--white);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.notification-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    background: var(--white);
    border: 2px solid var(--cream);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-medium);
    width: 350px;
    max-height: 500px;
    z-index: 1000;
    display: none;
    margin-top: 8px;
    animation: fadeInDown 0.3s ease;
    overflow: hidden;
}

.notification-dropdown.active {
    display: block;
}

@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.notification-header {
    padding: 1rem;
    border-bottom: 1px solid var(--cream);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
}

.notification-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--brown);
    font-family: 'BaticaSans', sans-serif;
}

.mark-all-read {
    font-size: 0.85rem;
    color: var(--sage);
    cursor: pointer;
    font-family: 'BaticaSans', sans-serif;
    font-weight: 500;
    transition: color 0.3s ease;
}

.mark-all-read:hover {
    color: var(--brown);
}

.notification-list {
    max-height: 400px;
    overflow-y: auto;
    overflow-x: hidden;
}

.notification-item {
    padding: 1rem;
    border-bottom: 1px solid rgba(189, 147, 121, 0.1);
    cursor: pointer;
    transition: background-color 0.3s ease;
    position: relative;
}

.notification-item:hover {
    background: rgba(236, 232, 225, 0.5);
}

.notification-item.unread {
    background: rgba(173, 184, 157, 0.1);
    border-left: 3px solid var(--curry);
}

.notification-item.unread::before {
    content: '';
    position: absolute;
    top: 50%;
    right: 1rem;
    width: 8px;
    height: 8px;
    background: var(--curry);
    border-radius: 50%;
    transform: translateY(-50%);
}

.notification-type-icon {
    font-size: 1.2rem;
    margin-right: 0.5rem;
}

.notification-content {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.notification-details h4 {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-dark);
    margin: 0 0 0.25rem 0;
    font-family: 'BaticaSans', sans-serif;
}

.notification-details p {
    font-size: 0.8rem;
    color: var(--text-gray);
    margin: 0 0 0.25rem 0;
    line-height: 1.4;
    font-family: 'BaticaSans', sans-serif;
}

.notification-time {
    font-size: 0.75rem;
    color: var(--text-gray);
    font-family: 'BaticaSans', sans-serif;
}

.no-notifications {
    padding: 2rem;
    text-align: center;
    color: var(--text-gray);
    font-family: 'BaticaSans', sans-serif;
}

.no-notifications-icon {
    font-size: 2rem;
    margin-bottom: 0.5rem;
    opacity: 0.5;
}

.system-badge {
    display: inline-block;
    background: #e8f5e8;
    color: #388e3c;
    padding: 0.2rem 0.5rem;
    border-radius: 10px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    margin-left: 0.5rem;
}

.notification-item.read {
    opacity: 0.7;
}

.notification-item {
    transition: all 0.3s ease;
}

.notification-item:active {
    transform: scale(0.98);
}

/* Ensure proper cursor for clickable notifications */
.notification-item {
    cursor: pointer;
}

.notification-item:hover .notification-details h4 {
    color: var(--brown);
}

/* Promotional Banner Styles - Updated for Dynamic Content */
.promo-banner {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    background: var(--promo-bg);
    color: var(--white);
    text-align: center;
    padding: 8px 20px;
    font-family: 'BaticaSans', sans-serif;
    font-weight: 700;
    font-size: 14px;
    z-index: 1002;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    animation: glow 2s ease-in-out infinite alternate;
}

<?php if (!$promoSettings['promo_banner_enabled']): ?>
.promo-banner {
    display: none;
}

.navbar {
    top: 0;
}

body.has-header {
    margin-top: 72px;
}

@media (max-width: 768px) {
    body.has-header {
        margin-top: 67px;
    }
}

@media (max-width: 480px) {
    body.has-header {
        margin-top: 62px;
    }
}
<?php endif; ?>

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

/* Desktop text - hide on mobile */
.mobile-text {
    display: none;
}

.desktop-text {
    display: inline;
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

/* Mobile Responsive Styles */
@media (max-width: 768px) {
    .promo-banner {
        padding: 4px 10px;
        font-size: 10px;
        height: 28px;
        display: flex;
        align-items: center;
    }
    
    .navbar {
        top: 28px;
    }
    
    <?php if (!$promoSettings['promo_banner_enabled']): ?>
    .navbar {
        top: 0;
    }
    <?php endif; ?>
    
    .promo-banner-content {
        flex-direction: row;
        gap: 6px;
        justify-content: center;
    }
    
    .promo-text {
        letter-spacing: 0.3px;
    }
    
    .promo-icon {
        font-size: 12px;
    }
    
    .promo-close {
        right: 8px;
        font-size: 14px;
    }

    /* Mobile text switching */
    .desktop-text {
        display: none;
    }

    .mobile-text {
        display: inline;
    }

    /* Mobile Navigation */
    .mobile-menu-toggle {
        display: block !important; /* Force display on mobile */
        z-index: 1101; /* Above mobile menu */
    }

    .nav-links {
        display: none !important; /* Hide desktop nav */
    }

    .navbar > div {
        padding: 0.8rem 1rem !important;
        display: grid !important;
        grid-template-columns: auto 1fr auto;
        align-items: center;
        gap: 1rem;
    }

    .logo {
        order: 2;
        justify-self: center;
    }

    .logo img {
        height: 50px !important;
    }

    .mobile-menu-toggle {
        order: 1;
        justify-self: start;
    }

    .nav-actions {
        order: 3;
        justify-self: end;
        gap: 0.5rem;
    }

    .nav-actions .btn {
        padding: 0.5rem 1rem;
        font-size: 0.85rem;
    }

    .mobile-nav-menu {
        display: block !important; /* Ensure mobile menu is available */
    }

    /* Mobile Notification Adjustments */
    .notification-dropdown {
        width: 300px;
        right: 50%;
        transform: translateX(50%);
        left: auto;
    }
    
    .notification-item {
        padding: 0.75rem;
    }
    
    .notification-details h4 {
        font-size: 0.85rem;
    }
    
    .notification-details p {
        font-size: 0.75rem;
    }

    /* Reset mobile nav menu for mobile screens - Half screen width */
    .mobile-nav-menu {
        position: fixed;
        top: 0;
        left: 0;
        width: 50%;
        height: 100vh;
        background: var(--white);
        z-index: 1100;
        transform: translateX(-100%);
        opacity: 1;
        visibility: visible;
        transition: transform 0.3s ease;
    }

    .mobile-nav-menu.active {
        transform: translateX(0);
        display: block;
    }

    /* Reset mobile menu header for mobile */
    .mobile-menu-header {
        background: var(--cream);
        color: var(--brown);
        padding: 1rem 2rem;
        border-bottom: 2px solid var(--cream);
    }

    .mobile-menu-title {
        color: var(--brown);
        font-size: 1.2rem;
    }

    .mobile-close-btn {
        color: var(--brown);
        width: 50px;
        height: 50px;
        font-size: 2rem;
    }

    .mobile-close-btn:hover {
        background: rgba(189, 147, 121, 0.1);
    }

    /* Reset navigation links for mobile - More compact */
    .mobile-nav-links {
        padding: 1.5rem;
    }

    .mobile-nav-links li {
        margin-bottom: 0.8rem;
    }

    .mobile-nav-links a {
        padding: 0.6rem 0;
        font-size: 1rem;
        border-radius: 0;
        border-bottom: 1px solid var(--cream);
        background: transparent;
    }

    .mobile-nav-links a:hover {
        background: transparent;
        color: var(--brown);
    }
}

@media (max-width: 480px) {
    .promo-banner {
        padding: 2px 5px;
        font-size: 9px;
        height: 24px;
    }

    .promo-banner-content {
        gap: 4px;
    }

    .promo-icon {
        font-size: 10px;
    }

    .promo-close {
        font-size: 12px;
        right: 5px;
    }

    .navbar {
        top: 24px;
    }
    
    <?php if (!$promoSettings['promo_banner_enabled']): ?>
    .navbar {
        top: 0;
    }
    <?php endif; ?>
}

/* Body margin adjustment for pages using this header */
body.has-header {
    margin-top: 110px; /* Default spacing for desktop */
}

@media (max-width: 768px) {
    body.has-header {
        margin-top: 105px; /* Mobile spacing */
    }
}

@media (max-width: 480px) {
    body.has-header {
        margin-top: 100px; /* Small mobile spacing */
    }
}
</style>

<?php if ($promoSettings['promo_banner_enabled']): ?>
<!-- Dynamic Promotional Banner -->
<div class="promo-banner" id="promoBanner">
    <div class="promo-banner-content">
        <span class="promo-icon"><?php echo htmlspecialchars($promoSettings['promo_banner_left_icon']); ?></span>
        <span class="promo-text">
            <span class="desktop-text"><?php echo htmlspecialchars($promoSettings['promo_banner_desktop_text']); ?></span>
            <span class="mobile-text"><?php echo htmlspecialchars($promoSettings['promo_banner_mobile_text']); ?></span>
        </span>
        <span class="promo-icon"><?php echo htmlspecialchars($promoSettings['promo_banner_right_icon']); ?></span>
    </div>
    <button class="promo-close" onclick="closePromoBanner()" title="Close">×</button>
</div>
<?php endif; ?>

<!-- Navigation -->
<nav class="navbar">
<div style="display: flex; justify-content: space-between; align-items: center; padding: 0.6rem 2rem; max-width: 1200px; margin: 0 auto; width: 100%; position: relative;">        <!-- Mobile hamburger menu -->
        <button class="mobile-menu-toggle" id="mobileMenuToggle" onclick="toggleMobileMenu()" type="button" aria-label="Toggle mobile menu">
            <div class="hamburger" id="hamburger">
                <span></span>
                <span></span>
                <span></span>
                <span></span>
            </div>
        </button>

        <a href="index.php" class="logo">
            <img src="./assets/image/logowide.png" alt="Somdul Table" style="height: 80px; width: auto;">
        </a>

        <ul class="nav-links">
            <li><a href="./index.php">Home</a></li>
            <li><a href="./menus.php">Menu</a></li>
            <li><a href="./product.php">Meal-Kits</a></li>
            <li><a href="./index.php#how-it-works">How It Works</a></li>
            <li><a href="./blogs.php">About</a></li>
            <li><a href="./contact.php">Contact</a></li>
        </ul>
        
        <div class="nav-actions">
            <?php if (isset($_SESSION['user_id'])): ?>
                <!-- Notification Widget - Only show when user is logged in -->
                <?php if ($notificationData): ?>
                    <div class="notification-wrapper">
                        <div class="notification-bell" id="notificationBell" onclick="toggleNotifications()">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                            </svg>
                            <?php if ($notificationData['unreadCount'] > 0): ?>
                                <span class="notification-badge"><?php echo min($notificationData['unreadCount'], 99); ?></span>
                            <?php endif; ?>
                        </div>
                        
                            <div class="notification-dropdown" id="notificationDropdown">
                                <div class="notification-header">
                                    <h3 class="notification-title">Notifications</h3>
                                    <?php if ($notificationData['unreadCount'] > 0): ?>
                                        <span class="mark-all-read" onclick="markAllAsRead()">Mark all read</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="notification-list">
                                    <?php if (empty($notificationData['notifications'])): ?>
                                        <div class="no-notifications">
                                            <div class="no-notifications-icon">🔔</div>
                                            <p>No notifications yet</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($notificationData['notifications'] as $notification): ?>
                                            <?php
                                            // Determine notification source and ID
                                            $notificationSource = $notification['notification_source'] ?? 'personal';
                                            $notificationId = $notification['id'];
                                            $isUnread = !$notification['is_read'];
                                            
                                            // Icon mapping
                                            $icons = [
                                                'order' => '🍽️',
                                                'system' => '⚙️',
                                                'promotion' => '🎉',
                                                'delivery' => '🚗',
                                                'payment' => '💳',
                                                'general' => '📢',
                                                'announcement' => '📢'
                                            ];
                                            $icon = $icons[$notification['type']] ?? '📢';
                                            ?>
                                            <div class="notification-item <?php echo $isUnread ? 'unread' : 'read'; ?>" 
                                                data-notification-id="<?php echo htmlspecialchars($notificationId); ?>"
                                                data-notification-source="<?php echo htmlspecialchars($notificationSource); ?>"
                                                onclick="markAsRead('<?php echo htmlspecialchars($notificationId); ?>', '<?php echo htmlspecialchars($notificationSource); ?>')">
                                                <div class="notification-content">
                                                    <span class="notification-type-icon"><?php echo $icon; ?></span>
                                                    <div class="notification-details">
                                                        <h4><?php echo htmlspecialchars($notification['title']); ?></h4>
                                                        <p><?php echo htmlspecialchars($notification['message']); ?></p>
                                                        <span class="notification-time">
                                                            <?php 
                                                            $time = new DateTime($notification['created_at']);
                                                            echo $time->format('M j, g:i A');
                                                            ?>
                                                        </span>
                                                        <?php if ($notificationSource === 'system'): ?>
                                                            <small class="system-badge">System</small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                    </div>
                <?php endif; ?>

                <!-- User Profile Icon -->
                <a href="dashboard.php" class="profile-link" title="Go to Dashboard">
                    <div class="profile-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </div>
                </a>
            <?php else: ?>
                <!-- User is not logged in - show sign in button -->
                <a href="login.php" class="btn btn-secondary">Sign In</a>
            <?php endif; ?>
            <a href="subscribe.php" class="btn btn-primary">Get Started</a>
        </div>

        <!-- Mobile Navigation Menu - Desktop Dropdown / Mobile Fullscreen -->
        <div class="mobile-nav-menu" id="mobileNavMenu">
            <!-- Mobile Menu Header with Close Button -->
            <div class="mobile-menu-header">
                <div class="mobile-menu-logo">
                    <img src="./assets/image/LOGO_BG2.png" alt="Somdul Table">
                    <span class="mobile-menu-title">Somdul Table</span>
                </div>
                <button class="mobile-close-btn" onclick="closeMobileMenu()" type="button" aria-label="Close menu">
                    ×
                </button>
            </div>
            
            <!-- Mobile Navigation Links -->
            <ul class="mobile-nav-links">
                <li><a href="./index.php" onclick="closeMobileMenu()">🏠 Home</a></li>
                <li><a href="./menus.php" onclick="closeMobileMenu()">🍽️ Menu</a></li>
                <li><a href="./product.php" onclick="closeMobileMenu()">📦 Meal-Kits</a></li>
                <li><a href="./index.php#how-it-works" onclick="closeMobileMenu()">❓ How It Works</a></li>
                <li><a href="./blogs.php" onclick="closeMobileMenu()">📚 About</a></li>
                <li><a href="./contact.php" onclick="closeMobileMenu()">📞 Contact</a></li>
            </ul>
        </div>
    </div>
</nav>

<script>
// Header JavaScript Functions

// Global variable to track menu state
window.mobileMenuOpen = false;

// Mobile Navigation Functions
function toggleMobileMenu() {
    try {
        const mobileMenu = document.getElementById('mobileNavMenu');
        const hamburger = document.querySelector('.hamburger');
        const body = document.body;
        
        if (!mobileMenu || !hamburger) {
            console.error('Mobile menu elements not found');
            return;
        }
        
        // Toggle the menu
        mobileMenu.classList.toggle('active');
        hamburger.classList.toggle('open');
        window.mobileMenuOpen = !window.mobileMenuOpen;
        
        // Handle body scroll - only prevent scrolling on mobile
        if (window.innerWidth <= 768) {
            if (mobileMenu.classList.contains('active')) {
                body.style.overflow = 'hidden';
            } else {
                body.style.overflow = 'auto';
            }
        }
        
    } catch (error) {
        console.error('Error in toggleMobileMenu:', error);
    }
}

function closeMobileMenu() {
    try {
        const mobileMenu = document.getElementById('mobileNavMenu');
        const hamburger = document.querySelector('.hamburger');
        const body = document.body;
        
        if (mobileMenu && hamburger) {
            mobileMenu.classList.remove('active');
            hamburger.classList.remove('open');
            body.style.overflow = 'auto';
            window.mobileMenuOpen = false;
        }
    } catch (error) {
        console.error('Error in closeMobileMenu:', error);
    }
}

// Fixed Notification Functions for header.php
// Replace the notification JavaScript section in header.php with this:

// Notification Functions
let notificationDropdownOpen = false;

function toggleNotifications() {
    const dropdown = document.getElementById('notificationDropdown');
    if (!dropdown) return;
    
    notificationDropdownOpen = !notificationDropdownOpen;
    
    if (notificationDropdownOpen) {
        dropdown.classList.add('active');
        // Load latest notifications when opening
        loadNotifications();
    } else {
        dropdown.classList.remove('active');
    }
}

function markAsRead(notificationId, notificationSource) {
    console.log('Marking notification as read:', notificationId, 'Source:', notificationSource);
    
    // Handle both personal and system notifications
    const isSystemNotification = notificationSource === 'system' || String(notificationId).startsWith('sys_');
    const actualSource = isSystemNotification ? 'system' : 'personal';
    
    fetch('ajax/mark_notification_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ 
            notification_id: notificationId,
            notification_source: actualSource
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Mark as read response:', data);
        
        if (data.success) {
            // Update UI immediately
            const item = document.querySelector(`[data-notification-id="${notificationId}"]`);
            if (item) {
                item.classList.remove('unread');
                item.classList.add('read');
                
                // Remove the unread indicator (blue dot)
                const indicator = item.querySelector('::before');
                if (indicator) {
                    item.style.position = 'relative';
                }
            }
            
            // Update badge count
            updateNotificationBadge();
            
            // Update "mark all read" button visibility
            updateMarkAllReadButton();
        } else {
            console.error('Failed to mark notification as read:', data.error);
        }
    })
    .catch(error => {
        console.error('Error marking notification as read:', error);
    });
}

function markAllAsRead() {
    fetch('ajax/mark_all_notifications_read.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update UI - remove unread class from all notifications
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
                item.classList.add('read');
            });
            
            // Hide mark all read button
            const markAllBtn = document.querySelector('.mark-all-read');
            if (markAllBtn) markAllBtn.style.display = 'none';
            
            // Update badge
            updateNotificationBadge();
        } else {
            console.error('Failed to mark all notifications as read:', data.error);
        }
    })
    .catch(error => {
        console.error('Error marking all notifications as read:', error);
    });
}

function updateNotificationBadge() {
    fetch('ajax/get_notification_count.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const badge = document.querySelector('.notification-badge');
            if (data.count > 0) {
                if (badge) {
                    badge.textContent = Math.min(data.count, 99);
                    badge.style.display = 'flex';
                } else {
                    // Create badge if it doesn't exist
                    const bell = document.getElementById('notificationBell');
                    if (bell) {
                        const newBadge = document.createElement('span');
                        newBadge.className = 'notification-badge';
                        newBadge.textContent = Math.min(data.count, 99);
                        bell.appendChild(newBadge);
                    }
                }
            } else if (badge) {
                badge.style.display = 'none';
            }
        }
    })
    .catch(error => console.error('Error updating notification badge:', error));
}

function updateMarkAllReadButton() {
    const hasUnread = document.querySelectorAll('.notification-item.unread').length > 0;
    const markAllBtn = document.querySelector('.mark-all-read');
    if (markAllBtn) {
        markAllBtn.style.display = hasUnread ? 'inline' : 'none';
    }
}

function loadNotifications() {
    fetch('ajax/get_notifications.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateNotificationList(data.notifications);
        }
    })
    .catch(error => console.error('Error loading notifications:', error));
}

function updateNotificationList(notifications) {
    const notificationList = document.querySelector('.notification-list');
    if (!notificationList) return;
    
    if (notifications.length === 0) {
        notificationList.innerHTML = `
            <div class="no-notifications">
                <div class="no-notifications-icon">🔔</div>
                <p>No notifications yet</p>
            </div>
        `;
        return;
    }
    
    notificationList.innerHTML = notifications.map(notification => {
        const isUnread = !notification.is_read;
        const notificationId = notification.id;
        const notificationSource = notification.notification_source || 'personal';
        
        console.log('Rendering notification:', notificationId, 'Source:', notificationSource, 'Unread:', isUnread);
        
        const typeIcon = {
            'order': '🍽️',
            'system': '⚙️',
            'promotion': '🎉',
            'delivery': '🚗',
            'payment': '💳',
            'general': '📢',
            'announcement': '📢'
        }[notification.type] || '📢';
        
        const timeStr = new Date(notification.created_at).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit'
        });
        
        return `
            <div class="notification-item ${isUnread ? 'unread' : 'read'}" 
                 data-notification-id="${notificationId}"
                 data-notification-source="${notificationSource}"
                 onclick="markAsRead('${notificationId}', '${notificationSource}')">
                <div class="notification-content">
                    <span class="notification-type-icon">${typeIcon}</span>
                    <div class="notification-details">
                        <h4>${notification.title}</h4>
                        <p>${notification.message}</p>
                        <span class="notification-time">${timeStr}</span>
                        ${notificationSource === 'system' ? '<small class="system-badge">System</small>' : ''}
                    </div>
                </div>
            </div>
        `;
    }).join('');
    
    // Update mark all read button visibility
    updateMarkAllReadButton();
}

// Robust Mobile Menu Initialization
function initializeMobileMenu() {
    const hamburger = document.getElementById('mobileMenuToggle');
    
    if (!hamburger) {
        console.warn('Mobile menu toggle not found');
        return;
    }
    
    // Ensure hamburger is properly styled and accessible
    hamburger.style.cssText = `
        display: block !important;
        position: relative !important;
        z-index: 1105 !important;
        pointer-events: auto !important;
        cursor: pointer !important;
        background: none !important;
        border: none !important;
        padding: 0.5rem !important;
    `;
    
    // Remove any existing listeners by cloning the element
    const newHamburger = hamburger.cloneNode(true);
    hamburger.parentNode.replaceChild(newHamburger, hamburger);
    
    // Add robust click handler
    newHamburger.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        toggleMobileMenu();
    }, { capture: true });
    
    // Add touch handler for mobile devices
    newHamburger.addEventListener('touchstart', function(e) {
        e.preventDefault();
        e.stopPropagation();
        toggleMobileMenu();
    }, { passive: false });
    
    console.log('✅ Mobile menu initialized successfully');
}

// Check if hamburger button is actually clickable (debugging)
function debugHamburgerButton() {
    const hamburger = document.getElementById('mobileMenuToggle');
    if (hamburger) {
        const rect = hamburger.getBoundingClientRect();
        const elementAtPoint = document.elementFromPoint(
            rect.left + rect.width/2, 
            rect.top + rect.height/2
        );
        
        if (elementAtPoint !== hamburger && !hamburger.contains(elementAtPoint)) {
            console.warn('⚠️ Hamburger button may be blocked by:', elementAtPoint);
            console.log('Hamburger position:', rect);
            return false;
        } else {
            console.log('✅ Hamburger button is accessible');
            return true;
        }
    }
    return false;
}

// Close mobile menu when clicking outside
document.addEventListener('click', function(event) {
    const mobileMenu = document.getElementById('mobileNavMenu');
    const hamburger = document.getElementById('mobileMenuToggle');
    
    if (mobileMenu && hamburger && mobileMenu.classList.contains('active') && 
        !mobileMenu.contains(event.target) && 
        !hamburger.contains(event.target)) {
        closeMobileMenu();
    }
    
    // Close notification dropdown when clicking outside
    const notificationWrapper = document.querySelector('.notification-wrapper');
    if (notificationWrapper && !notificationWrapper.contains(event.target) && notificationDropdownOpen) {
        toggleNotifications();
    }
});

// Close mobile menu on escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        if (window.mobileMenuOpen) {
            closeMobileMenu();
        }
        if (notificationDropdownOpen) {
            toggleNotifications();
        }
    }
});

// Promo Banner Functions
function closePromoBanner() {
    const promoBanner = document.getElementById('promoBanner');
    const navbar = document.querySelector('.navbar');
    
    if (promoBanner && navbar) {
        promoBanner.style.transform = 'translateY(-100%)';
        promoBanner.style.opacity = '0';
        
        setTimeout(() => {
            promoBanner.style.display = 'none';
            navbar.style.top = '0';
            
            // Adjust body margin when banner is hidden
            document.body.style.marginTop = '72px';
            
            // Store banner closed state
            localStorage.setItem('promoBannerClosed', 'true');
        }, 300);
    }
}

// Check if banner was previously closed
document.addEventListener('DOMContentLoaded', function() {
    if (localStorage.getItem('promoBannerClosed') === 'true') {
        const promoBanner = document.getElementById('promoBanner');
        const navbar = document.querySelector('.navbar');
        
        if (promoBanner && navbar) {
            promoBanner.style.display = 'none';
            navbar.style.top = '0';
            document.body.style.marginTop = '72px';
        }
    }
});

// Navbar background on scroll
window.addEventListener('scroll', function() {
    const navbar = document.querySelector('.navbar');
    if (navbar) {
        if (window.scrollY > 100) {
            navbar.style.background = '#ece8e1';
        } else {
            navbar.style.background = '#ece8e1';
        }
    }
});

// DOM Content Loaded - Enhanced with mobile menu debugging
document.addEventListener('DOMContentLoaded', function() {
    try {
        // Initialize mobile menu with robust handling
        setTimeout(function() {
            initializeMobileMenu();
            
            // Debug hamburger accessibility
            if (window.innerWidth <= 768) {
                debugHamburgerButton();
            }
        }, 100);
        
        // Add click handlers to mobile nav links
        const mobileNavLinks = document.querySelectorAll('.mobile-nav-links a');
        mobileNavLinks.forEach(link => {
            link.addEventListener('click', function() {
                closeMobileMenu();
            });
        });
        
        // Smooth scrolling for navigation links
        const navLinks = document.querySelectorAll('a[href^="#"]');
        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                const targetSection = document.querySelector(targetId);
                
                if (targetSection) {
                    targetSection.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
        
        // Re-initialize mobile menu on window resize
        let resizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                if (window.innerWidth <= 768) {
                    initializeMobileMenu();
                }
            }, 250);
        });
        
        // Auto-refresh notification count every 30 seconds
        if (document.querySelector('.notification-wrapper')) {
            setInterval(updateNotificationBadge, 30000);
        }
        
    } catch (error) {
        console.error('Error during header initialization:', error);
    }
});

// Global Functions - Make available to all pages
window.toggleMobileMenu = toggleMobileMenu;
window.closeMobileMenu = closeMobileMenu;
window.closePromoBanner = closePromoBanner;
window.initializeMobileMenu = initializeMobileMenu;
window.debugHamburgerButton = debugHamburgerButton;
window.toggleNotifications = toggleNotifications;
window.markAsRead = markAsRead;
window.markAllAsRead = markAllAsRead;

// Backup fix function for pages that might need it
window.fixHamburgerMenu = function() {
    console.log('🔧 Running emergency hamburger menu fix...');
    initializeMobileMenu();
    return debugHamburgerButton();
};
</script>