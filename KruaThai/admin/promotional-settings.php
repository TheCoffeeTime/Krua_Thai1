<?php
/**
 * Somdul Table - Admin Promotional Settings Page
 * File: admin/promotional-settings.php
 * Description: Admin interface to manage promotional banner settings
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Database connection
try {
    require_once '../config/database.php';
    $database = new Database();
    $pdo = $database->getConnection();
} catch (Exception $e) {
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=somdul_table;charset=utf8mb4", "root", "root");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        try {
            $pdo = new PDO("mysql:host=localhost:8889;dbname=somdul_table;charset=utf8mb4", "root", "root");
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
}

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_promotional_settings'])) {
    try {
        $settings = [
            'promo_banner_enabled' => isset($_POST['promo_banner_enabled']) ? '1' : '0',
            'promo_banner_desktop_text' => trim($_POST['promo_banner_desktop_text']),
            'promo_banner_mobile_text' => trim($_POST['promo_banner_mobile_text']),
            'promo_banner_left_icon' => trim($_POST['promo_banner_left_icon']),
            'promo_banner_right_icon' => trim($_POST['promo_banner_right_icon']),
            'promo_banner_background_color' => trim($_POST['promo_banner_background_color'])
        ];
        
        // Validate inputs
        $errors = [];
        
        if (empty($settings['promo_banner_desktop_text'])) {
            $errors[] = "Desktop text is required";
        }
        
        if (empty($settings['promo_banner_mobile_text'])) {
            $errors[] = "Mobile text is required";
        }
        
        if (!preg_match('/^#[0-9A-F]{6}$/i', $settings['promo_banner_background_color'])) {
            $errors[] = "Background color must be a valid hex color (e.g., #cf723a)";
        }
        
        if (empty($errors)) {
            $pdo->beginTransaction();
            
            // Update or insert each setting
            $stmt = $pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value, setting_type, description, category, is_public, updated_at) 
                VALUES (?, ?, ?, ?, 'promotion', 1, NOW())
                ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value), 
                updated_at = NOW()
            ");
            
            $settingDescriptions = [
                'promo_banner_enabled' => 'Enable or disable the promotional banner',
                'promo_banner_desktop_text' => 'Desktop promotional banner text',
                'promo_banner_mobile_text' => 'Mobile promotional banner text',
                'promo_banner_left_icon' => 'Left icon for promotional banner',
                'promo_banner_right_icon' => 'Right icon for promotional banner',
                'promo_banner_background_color' => 'Background color for promotional banner'
            ];
            
            $settingTypes = [
                'promo_banner_enabled' => 'boolean',
                'promo_banner_desktop_text' => 'string',
                'promo_banner_mobile_text' => 'string',
                'promo_banner_left_icon' => 'string',
                'promo_banner_right_icon' => 'string',
                'promo_banner_background_color' => 'string'
            ];
            
            foreach ($settings as $key => $value) {
                $stmt->execute([
                    $key,
                    $value,
                    $settingTypes[$key],
                    $settingDescriptions[$key]
                ]);
            }
            
            $pdo->commit();
            
            $message = "Promotional settings updated successfully!";
            $messageType = "success";
            
        } else {
            $message = implode("<br>", $errors);
            $messageType = "error";
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Error updating settings: " . $e->getMessage();
        $messageType = "error";
    }
}

// Load current settings
$currentSettings = [];
try {
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE category = 'promotion'");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($settings as $setting) {
        $currentSettings[$setting['setting_key']] = $setting['setting_value'];
    }
} catch (Exception $e) {
    error_log("Error loading promotional settings: " . $e->getMessage());
}

// Set default values if not found
$defaults = [
    'promo_banner_enabled' => '1',
    'promo_banner_desktop_text' => '50% OFF First Week + Free Cookies for Life',
    'promo_banner_mobile_text' => '50% OFF + Free Cookies',
    'promo_banner_left_icon' => '🪴',
    'promo_banner_right_icon' => '🎉',
    'promo_banner_background_color' => '#cf723a'
];

$currentSettings = array_merge($defaults, $currentSettings);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promotional Settings - Somdul Table Admin</title>
    <meta name="description" content="Manage promotional banner settings for Somdul Table">
    
    <style>
    /* BaticaSans Font Import */
    @font-face {
        font-family: 'BaticaSans';
        src: url('../Font/BaticaSans-Regular.woff2') format('woff2'),
            url('../Font/BaticaSans-Regular.woff') format('woff'),
            url('../Font/BaticaSans-Regular.ttf') format('truetype');
        font-weight: 400;
        font-style: normal;
        font-display: swap;
    }

    @font-face {
        font-family: 'BaticaSans';
        src: url('../Font/BaticaSans-Regular.woff2') format('woff2'),
            url('../Font/BaticaSans-Regular.woff') format('woff'),
            url('../Font/BaticaSans-Regular.ttf') format('truetype');
        font-weight: 700;
        font-style: normal;
        font-display: swap;
    }

    /* CSS Variables - Somdul Table Design System */
    :root {
        --brown: #bd9379;
        --white: #ffffff;
        --cream: #ece8e1;
        --sage: #adb89d;
        --curry: #cf723a;
        --text-dark: #2c3e50;
        --text-gray: #7f8c8d;
        --border-light: #d4c4b8;
        --shadow-soft: 0 4px 12px rgba(189, 147, 121, 0.15);
        --shadow-medium: 0 8px 24px rgba(189, 147, 121, 0.25);
        --radius-sm: 8px;
        --radius-md: 12px;
        --radius-lg: 16px;
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        --success: #28a745;
        --warning: #ffc107;
        --danger: #dc3545;
    }

    /* Global Styles */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'BaticaSans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        line-height: 1.6;
        color: var(--text-dark);
        background: linear-gradient(135deg, #f8f9fa, var(--cream));
        min-height: 100vh;
    }

    .container {
        max-width: 1000px;
        margin: 2rem auto;
        padding: 0 2rem;
    }

    /* Header */
    .admin-header {
        background: var(--white);
        border-radius: var(--radius-lg);
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: var(--shadow-soft);
        text-align: center;
        position: relative;
        overflow: hidden;
    }

    .admin-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--brown), var(--sage), var(--curry));
    }

    .admin-title {
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--brown);
        margin-bottom: 0.5rem;
        font-family: 'BaticaSans', sans-serif;
    }

    .admin-subtitle {
        font-size: 1.1rem;
        color: var(--text-gray);
        font-family: 'BaticaSans', sans-serif;
    }

    /* Navigation */
    .nav-breadcrumb {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 2rem;
        font-family: 'BaticaSans', sans-serif;
    }

    .nav-breadcrumb a {
        color: var(--brown);
        text-decoration: none;
        transition: var(--transition);
    }

    .nav-breadcrumb a:hover {
        color: var(--curry);
    }

    /* Messages */
    .message {
        padding: 1rem 1.5rem;
        border-radius: var(--radius-md);
        margin-bottom: 2rem;
        font-family: 'BaticaSans', sans-serif;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.8rem;
    }

    .message.success {
        background: linear-gradient(135deg, #d4edda, #c3e6cb);
        color: #155724;
        border: 2px solid #c3e6cb;
    }

    .message.error {
        background: linear-gradient(135deg, #f8d7da, #f5c6cb);
        color: #721c24;
        border: 2px solid #f5c6cb;
    }

    .message-icon {
        font-size: 1.2rem;
    }

    /* Form Container */
    .form-container {
        background: var(--white);
        border-radius: var(--radius-lg);
        padding: 2.5rem;
        box-shadow: var(--shadow-soft);
        position: relative;
        overflow: hidden;
    }

    .form-container::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--brown), var(--curry));
    }

    .section-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--brown);
        margin-bottom: 1.5rem;
        font-family: 'BaticaSans', sans-serif;
        display: flex;
        align-items: center;
        gap: 0.8rem;
    }

    .section-title i {
        color: var(--curry);
        font-size: 1.2rem;
    }

    /* Form Groups */
    .form-group {
        margin-bottom: 2rem;
    }

    .form-label {
        display: block;
        margin-bottom: 0.8rem;
        font-weight: 700;
        color: var(--brown);
        font-family: 'BaticaSans', sans-serif;
        font-size: 1.1rem;
    }

    .form-help {
        font-size: 0.9rem;
        color: var(--text-gray);
        margin-top: 0.4rem;
        line-height: 1.4;
        font-family: 'BaticaSans', sans-serif;
    }

    .form-input, .form-textarea {
        width: 100%;
        padding: 1rem 1.2rem;
        border: 2px solid var(--border-light);
        border-radius: var(--radius-md);
        font-size: 1rem;
        font-family: 'BaticaSans', sans-serif;
        transition: var(--transition);
        background: var(--white);
        color: var(--text-dark);
    }

    .form-input:focus, .form-textarea:focus {
        outline: none;
        border-color: var(--brown);
        box-shadow: 0 0 15px rgba(189, 147, 121, 0.2);
        transform: translateY(-1px);
    }

    .form-textarea {
        resize: vertical;
        min-height: 100px;
    }

    /* Color Picker */
    .color-picker-group {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .color-picker {
        width: 60px;
        height: 50px;
        border: 2px solid var(--border-light);
        border-radius: var(--radius-md);
        cursor: pointer;
        transition: var(--transition);
    }

    .color-picker:hover {
        border-color: var(--brown);
        transform: scale(1.05);
    }

    /* Toggle Switch */
    .toggle-group {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .toggle-switch {
        position: relative;
        width: 60px;
        height: 32px;
    }

    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: var(--transition);
        border-radius: 32px;
    }

    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 24px;
        width: 24px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: var(--transition);
        border-radius: 50%;
    }

    input:checked + .toggle-slider {
        background-color: var(--sage);
    }

    input:checked + .toggle-slider:before {
        transform: translateX(28px);
    }

    .toggle-label {
        font-weight: 600;
        color: var(--text-dark);
        font-family: 'BaticaSans', sans-serif;
    }

    /* Icon Input */
    .icon-input-group {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .icon-preview {
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--cream);
        border: 2px solid var(--border-light);
        border-radius: var(--radius-md);
        font-size: 1.5rem;
    }

    .icon-input {
        flex: 1;
    }

    /* Preview Section */
    .preview-section {
        background: var(--cream);
        border-radius: var(--radius-lg);
        padding: 2rem;
        margin-bottom: 2rem;
        border: 2px solid var(--border-light);
    }

    .preview-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: var(--brown);
        margin-bottom: 1.5rem;
        font-family: 'BaticaSans', sans-serif;
        display: flex;
        align-items: center;
        gap: 0.8rem;
    }

    .banner-preview {
        background: var(--curry);
        color: var(--white);
        padding: 12px 20px;
        border-radius: var(--radius-md);
        text-align: center;
        font-family: 'BaticaSans', sans-serif;
        font-weight: 700;
        font-size: 14px;
        margin-bottom: 1rem;
        position: relative;
        transition: var(--transition);
    }

    .banner-preview-content {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }

    .banner-preview.disabled {
        opacity: 0.5;
        text-decoration: line-through;
    }

    /* Buttons */
    .btn {
        padding: 1rem 2rem;
        border: none;
        border-radius: var(--radius-md);
        font-weight: 700;
        font-family: 'BaticaSans', sans-serif;
        cursor: pointer;
        transition: var(--transition);
        font-size: 1rem;
        display: inline-flex;
        align-items: center;
        gap: 0.8rem;
        text-decoration: none;
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
        background: var(--sage);
        color: var(--white);
        box-shadow: var(--shadow-soft);
    }

    .btn-secondary:hover {
        background: #9ca688;
        transform: translateY(-2px);
        box-shadow: var(--shadow-medium);
    }

    .btn-group {
        display: flex;
        gap: 1rem;
        margin-top: 2rem;
        justify-content: flex-end;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .container {
            padding: 0 1rem;
            margin: 1rem auto;
        }

        .admin-header {
            padding: 1.5rem;
        }

        .admin-title {
            font-size: 2rem;
        }

        .form-container {
            padding: 1.5rem;
        }

        .color-picker-group,
        .icon-input-group {
            flex-direction: column;
            align-items: flex-start;
        }

        .btn-group {
            flex-direction: column;
        }

        .btn {
            justify-content: center;
        }
    }

    @media (max-width: 480px) {
        .admin-title {
            font-size: 1.8rem;
        }

        .form-container {
            padding: 1rem;
        }
    }

    /* Animations */
    .fade-in {
        animation: fadeIn 0.6s ease-out;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .slide-in {
        animation: slideIn 0.4s ease-out;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateX(-20px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="admin-header fade-in">
            <h1 class="admin-title">🎨 Promotional Settings</h1>
            <p class="admin-subtitle">Customize the promotional banner displayed at the top of your website</p>
        </div>

        <!-- Breadcrumb -->
        <div class="nav-breadcrumb slide-in">
            <a href="../admin/dashboard.php">📊 Admin Dashboard</a>
            <span style="color: var(--text-gray);">›</span>
            <a href="../admin/settings.php">⚙️ Settings</a>
            <span style="color: var(--text-gray);">›</span>
            <span style="color: var(--text-gray);">🎨 Promotional Settings</span>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?> fade-in">
                <span class="message-icon">
                    <?php echo $messageType === 'success' ? '✅' : '❌'; ?>
                </span>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Preview Section -->
        <div class="preview-section fade-in">
            <h2 class="preview-title">
                <i class="fas fa-eye"></i>
                Live Preview
            </h2>
            
            <div id="bannerPreview" class="banner-preview" style="background-color: <?php echo htmlspecialchars($currentSettings['promo_banner_background_color']); ?>;">
                <div class="banner-preview-content">
                    <span id="previewLeftIcon"><?php echo htmlspecialchars($currentSettings['promo_banner_left_icon']); ?></span>
                    <span id="previewDesktopText"><?php echo htmlspecialchars($currentSettings['promo_banner_desktop_text']); ?></span>
                    <span id="previewRightIcon"><?php echo htmlspecialchars($currentSettings['promo_banner_right_icon']); ?></span>
                </div>
            </div>
            
            <div class="form-help">
                <strong>Preview:</strong> This shows how your promotional banner will appear on the website. Changes are reflected in real-time as you edit the form below.
            </div>
        </div>

        <!-- Form -->
        <div class="form-container slide-in">
            <form method="POST" action="" id="promotional-form">
                <h2 class="section-title">
                    <i class="fas fa-cog"></i>
                    Banner Configuration
                </h2>

                <!-- Enable/Disable Toggle -->
                <div class="form-group">
                    <label class="form-label">Banner Status</label>
                    <div class="toggle-group">
                        <div class="toggle-switch">
                            <input type="checkbox" id="promo_banner_enabled" name="promo_banner_enabled" value="1" 
                                   <?php echo $currentSettings['promo_banner_enabled'] === '1' ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </div>
                        <label for="promo_banner_enabled" class="toggle-label">Enable Promotional Banner</label>
                    </div>
                    <div class="form-help">
                        When disabled, the promotional banner will not appear on any pages of the website.
                    </div>
                </div>

                <!-- Desktop Text -->
                <div class="form-group">
                    <label for="promo_banner_desktop_text" class="form-label">Desktop Text</label>
                    <input type="text" 
                           id="promo_banner_desktop_text" 
                           name="promo_banner_desktop_text" 
                           class="form-input" 
                           value="<?php echo htmlspecialchars($currentSettings['promo_banner_desktop_text']); ?>" 
                           required
                           maxlength="100">
                    <div class="form-help">
                        This text will be displayed on desktop and tablet devices. Keep it concise and compelling.
                    </div>
                </div>

                <!-- Mobile Text -->
                <div class="form-group">
                    <label for="promo_banner_mobile_text" class="form-label">Mobile Text</label>
                    <input type="text" 
                           id="promo_banner_mobile_text" 
                           name="promo_banner_mobile_text" 
                           class="form-input" 
                           value="<?php echo htmlspecialchars($currentSettings['promo_banner_mobile_text']); ?>" 
                           required
                           maxlength="50">
                    <div class="form-help">
                        Shorter version for mobile devices. Should be under 50 characters for optimal display.
                    </div>
                </div>

                <!-- Left Icon -->
                <div class="form-group">
                    <label for="promo_banner_left_icon" class="form-label">Left Icon</label>
                    <div class="icon-input-group">
                        <div class="icon-preview" id="leftIconPreview">
                            <?php echo htmlspecialchars($currentSettings['promo_banner_left_icon']); ?>
                        </div>
                        <input type="text" 
                               id="promo_banner_left_icon" 
                               name="promo_banner_left_icon" 
                               class="form-input icon-input" 
                               value="<?php echo htmlspecialchars($currentSettings['promo_banner_left_icon']); ?>"
                               maxlength="10">
                    </div>
                    <div class="form-help">
                        Emoji or symbol to display on the left side of the text. Use emojis like 🪴, 🎯, or ⭐
                    </div>
                </div>

                <!-- Right Icon -->
                <div class="form-group">
                    <label for="promo_banner_right_icon" class="form-label">Right Icon</label>
                    <div class="icon-input-group">
                        <div class="icon-preview" id="rightIconPreview">
                            <?php echo htmlspecialchars($currentSettings['promo_banner_right_icon']); ?>
                        </div>
                        <input type="text" 
                               id="promo_banner_right_icon" 
                               name="promo_banner_right_icon" 
                               class="form-input icon-input" 
                               value="<?php echo htmlspecialchars($currentSettings['promo_banner_right_icon']); ?>"
                               maxlength="10">
                    </div>
                    <div class="form-help">
                        Emoji or symbol to display on the right side of the text. Use emojis like 🎉, 🚀, or ✨
                    </div>
                </div>

                <!-- Background Color -->
                <div class="form-group">
                    <label for="promo_banner_background_color" class="form-label">Background Color</label>
                    <div class="color-picker-group">
                        <input type="color" 
                               id="color_picker" 
                               class="color-picker" 
                               value="<?php echo htmlspecialchars($currentSettings['promo_banner_background_color']); ?>">
                        <input type="text" 
                               id="promo_banner_background_color" 
                               name="promo_banner_background_color" 
                               class="form-input" 
                               value="<?php echo htmlspecialchars($currentSettings['promo_banner_background_color']); ?>" 
                               required
                               pattern="^#[0-9A-F]{6}$"
                               placeholder="#cf723a">
                    </div>
                    <div class="form-help">
                        Choose the background color for the promotional banner. Use hex format (e.g., #cf723a).
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="btn-group">
                    <a href="../admin/settings.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Back to Settings
                    </a>
                    <button type="submit" name="update_promotional_settings" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Real-time preview updates
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('promotional-form');
            const bannerPreview = document.getElementById('bannerPreview');
            const previewLeftIcon = document.getElementById('previewLeftIcon');
            const previewDesktopText = document.getElementById('previewDesktopText');
            const previewRightIcon = document.getElementById('previewRightIcon');
            const leftIconPreview = document.getElementById('leftIconPreview');
            const rightIconPreview = document.getElementById('rightIconPreview');

            // Enable/Disable toggle
            const enableToggle = document.getElementById('promo_banner_enabled');
            enableToggle.addEventListener('change', function() {
                if (this.checked) {
                    bannerPreview.classList.remove('disabled');
                } else {
                    bannerPreview.classList.add('disabled');
                }
            });

            // Desktop text update
            const desktopTextInput = document.getElementById('promo_banner_desktop_text');
            desktopTextInput.addEventListener('input', function() {
                previewDesktopText.textContent = this.value || 'Enter your promotional text...';
            });

            // Left icon update
            const leftIconInput = document.getElementById('promo_banner_left_icon');
            leftIconInput.addEventListener('input', function() {
                previewLeftIcon.textContent = this.value;
                leftIconPreview.textContent = this.value;
            });

            // Right icon update
            const rightIconInput = document.getElementById('promo_banner_right_icon');
            rightIconInput.addEventListener('input', function() {
                previewRightIcon.textContent = this.value;
                rightIconPreview.textContent = this.value;
            });

            // Color picker sync
            const colorPicker = document.getElementById('color_picker');
            const colorInput = document.getElementById('promo_banner_background_color');
            
            colorPicker.addEventListener('change', function() {
                colorInput.value = this.value;
                bannerPreview.style.backgroundColor = this.value;
            });
            
            colorInput.addEventListener('input', function() {
                if (this.value.match(/^#[0-9A-F]{6}$/i)) {
                    colorPicker.value = this.value;
                    bannerPreview.style.backgroundColor = this.value;
                }
            });

            // Form validation
            form.addEventListener('submit', function(e) {
                const desktopText = desktopTextInput.value.trim();
                const mobileText = document.getElementById('promo_banner_mobile_text').value.trim();
                const colorValue = colorInput.value.trim();
                
                if (!desktopText) {
                    alert('Desktop text is required.');
                    e.preventDefault();
                    desktopTextInput.focus();
                    return;
                }
                
                if (!mobileText) {
                    alert('Mobile text is required.');
                    e.preventDefault();
                    document.getElementById('promo_banner_mobile_text').focus();
                    return;
                }
                
                if (!colorValue.match(/^#[0-9A-F]{6}$/i)) {
                    alert('Please enter a valid hex color code (e.g., #cf723a).');
                    e.preventDefault();
                    colorInput.focus();
                    return;
                }
                
                // Show loading state
                const submitBtn = form.querySelector('button[type="submit"]');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                submitBtn.disabled = true;
            });

            // Character counters
            const addCharacterCounter = (input, maxLength) => {
                const counter = document.createElement('div');
                counter.style.cssText = `
                    font-size: 0.8rem;
                    color: var(--text-gray);
                    text-align: right;
                    margin-top: 0.3rem;
                    font-family: 'BaticaSans', sans-serif;
                `;
                
                const updateCounter = () => {
                    const remaining = maxLength - input.value.length;
                    counter.textContent = `${input.value.length}/${maxLength}`;
                    counter.style.color = remaining < 10 ? 'var(--danger)' : 'var(--text-gray)';
                };
                
                input.addEventListener('input', updateCounter);
                input.parentNode.appendChild(counter);
                updateCounter();
            };

            addCharacterCounter(desktopTextInput, 100);
            addCharacterCounter(document.getElementById('promo_banner_mobile_text'), 50);

            console.log('✅ Promotional settings page loaded successfully');
        });
    </script>
</body>
</html>