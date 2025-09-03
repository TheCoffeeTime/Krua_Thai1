<?php
/**
 * Somdul Table - Add New Menu Item (Updated with Multiple Image Support)
 * File: admin/add-menu.php
 * Description: Complete form for adding new menu items with multiple image uploads, nutrition tracking, ingredients
 */
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php"); 
    exit();
}

$errors = [];
$success_message = '';
$form_data = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize form data
    $form_data = [
        'category_id' => sanitizeInput($_POST['category_id'] ?? ''),
        'name' => sanitizeInput($_POST['name'] ?? ''),
        'name_thai' => sanitizeInput($_POST['name_thai'] ?? ''),
        'description' => sanitizeInput($_POST['description'] ?? ''),
        'ingredients' => sanitizeInput($_POST['ingredients'] ?? ''),
        'cooking_method' => sanitizeInput($_POST['cooking_method'] ?? ''),
        'base_price' => sanitizeInput($_POST['base_price'] ?? ''),
        'portion_size' => sanitizeInput($_POST['portion_size'] ?? 'Regular'),
        'preparation_time' => sanitizeInput($_POST['preparation_time'] ?? '15'),
        'calories_per_serving' => sanitizeInput($_POST['calories_per_serving'] ?? ''),
        'protein_g' => sanitizeInput($_POST['protein_g'] ?? ''),
        'carbs_g' => sanitizeInput($_POST['carbs_g'] ?? ''),
        'fat_g' => sanitizeInput($_POST['fat_g'] ?? ''),
        'fiber_g' => sanitizeInput($_POST['fiber_g'] ?? ''),
        'sodium_mg' => sanitizeInput($_POST['sodium_mg'] ?? ''),
        'sugar_g' => sanitizeInput($_POST['sugar_g'] ?? ''),
        'spice_level' => sanitizeInput($_POST['spice_level'] ?? 'medium'),
        'dietary_tags' => $_POST['dietary_tags'] ?? [],
        'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
        'meta_description' => sanitizeInput($_POST['meta_description'] ?? '')
    ];

    // Validation
    if (empty($form_data['name'])) {
        $errors[] = "Menu name is required";
    } elseif (strlen($form_data['name']) < 2) {
        $errors[] = "Menu name must be at least 2 characters";
    }

    if (empty($form_data['base_price'])) {
        $errors[] = "Price is required";
    } elseif (!is_numeric($form_data['base_price']) || $form_data['base_price'] <= 0) {
        $errors[] = "Price must be a valid positive number";
    }

    if (!empty($form_data['preparation_time']) && (!is_numeric($form_data['preparation_time']) || $form_data['preparation_time'] <= 0)) {
        $errors[] = "Preparation time must be a positive number";
    }

    // Validate nutrition values if provided
    $nutrition_fields = ['calories_per_serving', 'protein_g', 'carbs_g', 'fat_g', 'fiber_g', 'sodium_mg', 'sugar_g'];
    foreach ($nutrition_fields as $field) {
        if (!empty($form_data[$field]) && (!is_numeric($form_data[$field]) || $form_data[$field] < 0)) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . " must be a valid positive number";
        }
    }

    // Check for duplicate menu name
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM menus WHERE name = ? OR name_thai = ?");
            $stmt->execute([$form_data['name'], $form_data['name_thai']]);
            if ($stmt->rowCount() > 0) {
                $errors[] = "A menu item with this name already exists";
            }
        } catch (Exception $e) {
            $errors[] = "Error checking for duplicate menu names";
        }
    }

    // Handle main image upload
    $main_image_url = '';
    if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
        $upload_result = handleImageUpload($_FILES['main_image']);
        if ($upload_result['success']) {
            $main_image_url = $upload_result['file_path'];
        } else {
            $errors[] = $upload_result['message'];
        }
    }

    // Handle multiple gallery images upload
    $gallery_images = [];
    $additional_images = [];
    
    if (isset($_FILES['gallery_images']) && is_array($_FILES['gallery_images']['name'])) {
        $file_count = count($_FILES['gallery_images']['name']);
        
        for ($i = 0; $i < $file_count; $i++) {
            if ($_FILES['gallery_images']['error'][$i] === UPLOAD_ERR_OK) {
                $file = [
                    'name' => $_FILES['gallery_images']['name'][$i],
                    'type' => $_FILES['gallery_images']['type'][$i],
                    'tmp_name' => $_FILES['gallery_images']['tmp_name'][$i],
                    'error' => $_FILES['gallery_images']['error'][$i],
                    'size' => $_FILES['gallery_images']['size'][$i]
                ];
                
                $upload_result = handleImageUpload($file);
                if ($upload_result['success']) {
                    $gallery_images[] = $upload_result['file_path'];
                    $additional_images[] = $upload_result['file_path'];
                } else {
                    $errors[] = "Gallery image " . ($i + 1) . ": " . $upload_result['message'];
                }
            }
        }
    }

    // Create menu if no errors
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Generate slug
            $slug = generateSlug($form_data['name']);

            // Prepare JSON data
            $dietary_tags_json = !empty($form_data['dietary_tags']) ? json_encode($form_data['dietary_tags']) : null;
            $gallery_images_json = !empty($gallery_images) ? json_encode($gallery_images) : null;
            $additional_images_json = !empty($additional_images) ? json_encode($additional_images) : null;

            // Insert menu with multiple image support
            $menu_sql = "
                INSERT INTO menus (
                    id, category_id, name, name_thai, description, ingredients, cooking_method,
                    main_image_url, gallery_images, additional_images, base_price, portion_size, preparation_time,
                    calories_per_serving, protein_g, carbs_g, fat_g, fiber_g, sodium_mg, sugar_g,
                    dietary_tags, spice_level, is_featured, 
                    slug, meta_description,
                    is_available, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW()
                )
            ";

            $menu_id = generateUUID();
            $stmt = $pdo->prepare($menu_sql);
            $stmt->execute([
                $menu_id,
                $form_data['category_id'] ?: null,
                $form_data['name'],
                $form_data['name_thai'] ?: null,
                $form_data['description'] ?: null,
                $form_data['ingredients'] ?: null,
                $form_data['cooking_method'] ?: null,
                $main_image_url ?: null,
                $gallery_images_json,
                $additional_images_json,
                $form_data['base_price'],
                $form_data['portion_size'],
                $form_data['preparation_time'],
                $form_data['calories_per_serving'] ?: null,
                $form_data['protein_g'] ?: null,
                $form_data['carbs_g'] ?: null,
                $form_data['fat_g'] ?: null,
                $form_data['fiber_g'] ?: null,
                $form_data['sodium_mg'] ?: null,
                $form_data['sugar_g'] ?: null,
                $dietary_tags_json,
                $form_data['spice_level'],
                $form_data['is_featured'],
                $slug,
                $form_data['meta_description'] ?: null
            ]);

            $pdo->commit();

            // Log activity
            logActivity('menu_created', $_SESSION['user_id'], getRealIPAddress(), [
                'menu_id' => $menu_id,
                'menu_name' => $form_data['name'],
                'images_count' => count($gallery_images) + ($main_image_url ? 1 : 0)
            ]);

            $success_message = "Menu item '{$form_data['name']}' has been created successfully with " . (count($gallery_images) + ($main_image_url ? 1 : 0)) . " images!";
            $form_data = []; // Clear form data on success

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Error creating menu item: " . $e->getMessage();
            error_log("Menu creation error: " . $e->getMessage());
        }
    }
}

// Helper functions
function handleImageUpload($file) {
    $upload_dir = '../uploads/menus/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB

    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.'];
    }

    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'File size too large. Maximum 5MB allowed.'];
    }

    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('menu_') . '_' . time() . '.' . $file_extension;
    $file_path = $upload_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        return ['success' => true, 'file_path' => 'uploads/menus/' . $filename];
    } else {
        return ['success' => false, 'message' => 'Failed to upload image.'];
    }
}

function generateSlug($text) {
    $slug = strtolower($text);
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}

// Get categories for dropdown
try {
    $categories_sql = "SELECT id, name, name_thai FROM menu_categories WHERE is_active = 1 ORDER BY sort_order, name";
    $stmt = $pdo->prepare($categories_sql);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categories = [];
    error_log("Error fetching categories: " . $e->getMessage());
}

$dietary_tags_options = [
    'vegetarian' => 'Vegetarian',
    'vegan' => 'Vegan',
    'gluten_free' => 'Gluten-Free',
    'dairy_free' => 'Dairy-Free',
    'keto' => 'Keto-Friendly',
    'paleo' => 'Paleo-Friendly',
    'low_carb' => 'Low Carb',
    'high_protein' => 'High Protein',
    'diabetic_friendly' => 'Diabetic-Friendly',
    'heart_healthy' => 'Heart Healthy',
    'kids_meal' => 'Kids meal'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Menu - Somdul Table Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --cream: #ece8e1;
            --sage: #adb89d;
            --brown: #bd9379;
            --curry: #cf723a;
            --white: #ffffff;
            --text-dark: #2c3e50;
            --text-gray: #7f8c8d;
            --border-light: #e8e8e8;
            --shadow-soft: 0 4px 12px rgba(0,0,0,0.05);
            --shadow-medium: 0 8px 24px rgba(0,0,0,0.1);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --success: #27ae60;
            --danger: #e74c3c;
            --warning: #f39c12;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'BaticaSans', 'Sarabun', sans-serif;
            background: linear-gradient(135deg, var(--cream) 0%, #f8f6f3 100%);
            color: var(--text-dark);
            line-height: 1.6;
        }

        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar - Same as other pages */
        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, var(--brown) 0%, var(--curry) 100%);
            color: var(--white);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            transition: var(--transition);
            box-shadow: var(--shadow-medium);
        }

        .sidebar-header {
            padding: 2rem;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .logo {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .logo-image {
            max-width: 80px;
            max-height: 80px;
            width: auto;
            height: auto;
            object-fit: contain;
            filter: brightness(1.1) contrast(1.2);
            transition: transform 0.3s ease;
        }

        .logo-image:hover {
            transform: scale(1.05);
        }

        .sidebar-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .sidebar-subtitle {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-section {
            margin-bottom: 1.5rem;
        }

        .nav-section-title {
            padding: 0 1.5rem 0.5rem;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.7;
            font-weight: 600;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 1.5rem;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            transition: var(--transition);
            border-left: 3px solid transparent;
            cursor: pointer;
        }

        .nav-item:hover {
            background: rgba(255, 255, 255, 0.1);
            border-left-color: var(--white);
        }

        .nav-item.active {
            background: rgba(255, 255, 255, 0.15);
            border-left-color: var(--white);
            font-weight: 600;
        }

        .nav-icon {
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            flex: 1;
            padding: 2rem;
            transition: var(--transition);
        }

        /* Header */
        .page-header {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            margin-bottom: 2rem;
            border: 1px solid var(--border-light);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: var(--text-gray);
            font-size: 1rem;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--curry), #e67e22);
            color: var(--white);
            box-shadow: var(--shadow-soft);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .btn-secondary {
            background: var(--white);
            color: var(--text-dark);
            border: 1px solid var(--border-light);
            box-shadow: var(--shadow-soft);
        }

        .btn-secondary:hover {
            background: var(--cream);
            transform: translateY(-1px);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--sage), #27ae60);
            color: var(--white);
        }

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: var(--white);
        }

        /* Form Container */
        .form-container {
            background: var(--white);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
            overflow: hidden;
        }

        .form-header {
            background: linear-gradient(135deg, var(--cream), #f5f2ef);
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-light);
        }

        .form-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .form-description {
            color: var(--text-gray);
            font-size: 0.9rem;
        }

        .form-body {
            padding: 2rem;
        }

        /* Form Layout */
        .form-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--curry);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--cream);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-row-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
        }

        .required {
            color: var(--danger);
        }

        .form-control {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
            transition: var(--transition);
            background: var(--white);
            color: var(--text-dark);
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--curry);
            box-shadow: 0 0 0 3px rgba(207, 114, 58, 0.1);
        }

        .form-control.error {
            border-color: var(--danger);
            background-color: #fff5f5;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .form-help {
            font-size: 0.8rem;
            color: var(--text-gray);
            margin-top: 0.25rem;
        }

        /* File Upload */
        .file-upload {
            position: relative;
            display: block;
            padding: 2rem;
            border: 2px dashed var(--border-light);
            border-radius: var(--radius-sm);
            text-align: center;
            transition: var(--transition);
            cursor: pointer;
            background: #fafafa;
        }

        .file-upload:hover {
            border-color: var(--curry);
            background: rgba(207, 114, 58, 0.05);
        }

        .file-upload input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .file-upload-icon {
            font-size: 2rem;
            color: var(--curry);
            margin-bottom: 0.5rem;
        }

        .file-upload-text {
            color: var(--text-gray);
            font-size: 0.9rem;
        }

        /* Multiple File Upload Styles */
        .multiple-file-upload {
            border: 2px dashed var(--curry);
            border-radius: var(--radius-sm);
            padding: 2rem;
            text-align: center;
            background: rgba(207, 114, 58, 0.05);
            cursor: pointer;
            transition: var(--transition);
        }

        .multiple-file-upload:hover {
            background: rgba(207, 114, 58, 0.1);
        }

        .upload-hint {
            color: var(--text-gray);
            font-size: 0.85rem;
            margin-top: 0.5rem;
        }

        /* Image Preview Grid */
        .image-preview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .preview-image-container {
            position: relative;
            aspect-ratio: 1;
            border-radius: var(--radius-sm);
            overflow: hidden;
            border: 2px solid var(--border-light);
            background: #f8f9fa;
        }

        .preview-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .remove-image-btn {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(231, 76, 60, 0.9);
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            cursor: pointer;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }

        .remove-image-btn:hover {
            background: #e74c3c;
            transform: scale(1.1);
        }

        /* Checkbox Groups */
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.75rem;
            margin-top: 0.5rem;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem;
            background: var(--cream);
            border-radius: var(--radius-sm);
            border: 2px solid transparent;
            transition: var(--transition);
            cursor: pointer;
        }

        .checkbox-item:hover {
            border-color: var(--curry);
            background: rgba(207, 114, 58, 0.1);
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

        /* Alerts */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--radius-sm);
            margin-bottom: 1.5rem;
            font-weight: 500;
            border-left: 4px solid;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            border-left-color: var(--success);
            color: var(--success);
        }

        .alert-error {
            background: rgba(220, 53, 69, 0.1);
            border-left-color: var(--danger);
            color: var(--danger);
        }

        .alert ul {
            margin: 0;
            padding-left: 1.2rem;
        }

        .alert ul li {
            margin-bottom: 0.3rem;
        }

        /* Preview Panel */
        .preview-panel {
            background: var(--cream);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            border: 1px solid var(--border-light);
            position: sticky;
            top: 2rem;
        }

        .preview-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--curry);
            margin-bottom: 1rem;
            text-align: center;
        }

        .menu-preview {
            background: var(--white);
            border-radius: var(--radius-sm);
            overflow: hidden;
            box-shadow: var(--shadow-soft);
        }

        .menu-preview-image {
            height: 150px;
            background: linear-gradient(135deg, #f8f9fa, var(--cream));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-gray);
            font-size: 2rem;
            position: relative;
        }

        .menu-preview-body {
            padding: 1rem;
        }

        .menu-preview-name {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.25rem;
        }

        .menu-preview-name-thai {
            font-size: 0.9rem;
            color: var(--text-gray);
            margin-bottom: 0.5rem;
        }

        .menu-preview-price {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--curry);
            margin-bottom: 0.5rem;
        }

        .menu-preview-description {
            font-size: 0.8rem;
            color: var(--text-gray);
            margin-bottom: 0.75rem;
            line-height: 1.4;
        }

        .menu-preview-nutrition {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
            font-size: 0.75rem;
        }

        .nutrition-item {
            text-align: center;
            padding: 0.25rem;
            background: var(--cream);
            border-radius: 4px;
        }

        .nutrition-value {
            font-weight: 600;
            color: var(--text-dark);
        }

        .nutrition-label {
            color: var(--text-gray);
        }

        /* Preview Gallery */
        .preview-gallery {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .preview-gallery-image {
            aspect-ratio: 1;
            border-radius: 4px;
            overflow: hidden;
            background: var(--cream);
        }

        .preview-gallery-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Form Actions */
        .form-actions {
            background: var(--cream);
            padding: 1.5rem 2rem;
            border-top: 1px solid var(--border-light);
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        /* Loading States */
        .loading {
            pointer-events: none;
            opacity: 0.7;
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

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 3000;
        }

        .toast {
            background: var(--white);
            border-left: 4px solid var(--curry);
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow-medium);
            padding: 1rem;
            margin-bottom: 0.5rem;
            min-width: 300px;
            transform: translateX(100%);
            transition: var(--transition);
        }

        .toast.show {
            transform: translateX(0);
        }

        .toast.success {
            border-left-color: var(--success);
        }

        .toast.error {
            border-left-color: var(--danger);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                width: 260px;
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .page-header {
                padding: 1.5rem;
            }

            .header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-row,
            .form-row-3 {
                grid-template-columns: 1fr;
            }

            .checkbox-group {
                grid-template-columns: 1fr;
            }

            .preview-panel {
                position: static;
                margin-top: 2rem;
            }

            .image-preview-grid {
                grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            }
        }

        /* Utilities */
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .d-none { display: none; }
        .d-block { display: block; }
        .mb-0 { margin-bottom: 0; }
        .mb-1 { margin-bottom: 0.5rem; }
        .mb-2 { margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <img src="../assets/image/LOGO_BG2.png" 
                         alt="Somdul Table Logo" 
                         class="logo-image"
                         loading="lazy">
                </div>
                <div class="sidebar-title">Somdul Table</div>
                <div class="sidebar-subtitle">Admin Panel</div>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Main</div>
                    <a href="dashboard.php" class="nav-item">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="orders.php" class="nav-item">
                        <i class="nav-icon fas fa-shopping-cart"></i>
                        <span>Orders</span>
                    </a>
                    <a href="menus.php" class="nav-item active">
                        <i class="nav-icon fas fa-utensils"></i>
                        <span>Menus</span>
                    </a>
                    <a href="subscriptions.php" class="nav-item">
                        <i class="nav-icon fas fa-calendar-alt"></i>
                        <span>Subscriptions</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Management</div>
                    <a href="users.php" class="nav-item">
                        <i class="nav-icon fas fa-users"></i>
                        <span>Users</span>
                    </a>
                    <a href="inventory.php" class="nav-item">
                        <i class="nav-icon fas fa-boxes"></i>
                        <span>Inventory</span>
                    </a>
                    <a href="delivery-zones.php" class="nav-item">
                        <i class="nav-icon fas fa-map-marked-alt"></i>
                        <span>Delivery Zones</span>
                    </a>
                    <a href="reviews.php" class="nav-item">
                        <i class="nav-icon fas fa-star"></i>
                        <span>Reviews</span>
                    </a>
                    <a href="complaints.php" class="nav-item">
                        <i class="nav-icon fas fa-exclamation-triangle"></i>
                        <span>Complaints</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Financial</div>
                    <a href="payments.php" class="nav-item">
                        <i class="nav-icon fas fa-credit-card"></i>
                        <span>Payments</span>
                    </a>
                    <a href="reports.php" class="nav-item">
                        <i class="nav-icon fas fa-chart-bar"></i>
                        <span>Reports</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">System</div>
                    <a href="settings.php" class="nav-item">
                        <i class="nav-icon fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                    <a href="logout.php" class="nav-item" onclick="logout()" style="color: rgba(255, 255, 255, 0.9);">
                        <i class="nav-icon fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="header-content">
                    <div>
                        <h1 class="page-title">
                            <i class="fas fa-plus" style="color: var(--curry); margin-right: 0.5rem;"></i>
                            Add New Menu Item
                        </h1>
                        <p class="page-subtitle">Create a delicious new Thai dish with multiple images</p>
                    </div>
                    <div class="header-actions">
                        <a href="menus.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            Back to Menus
                        </a>
                        <button type="button" class="btn btn-primary" onclick="previewMenu()">
                            <i class="fas fa-eye"></i>
                            Preview
                        </button>
                    </div>
                </div>
            </div>

            <!-- Alerts -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error" role="alert">
                    <strong><i class="fas fa-exclamation-triangle"></i> Please correct the following errors:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="alert alert-success" role="alert">
                    <strong><i class="fas fa-check-circle"></i> Success!</strong><br>
                    <?php echo htmlspecialchars($success_message); ?>
                    <br><br>
                    <a href="menus.php" class="btn btn-secondary" style="margin-top: 0.5rem;">
                        <i class="fas fa-list"></i>
                        View All Menus
                    </a>
                    <button type="button" class="btn btn-primary" onclick="resetForm()" style="margin-top: 0.5rem;">
                        <i class="fas fa-plus"></i>
                        Add Another Menu
                    </button>
                </div>
            <?php endif; ?>

            <!-- Form Container -->
            <form method="POST" enctype="multipart/form-data" id="menuForm" novalidate>
                <div class="form-container">
                    <div class="form-header">
                        <h2 class="form-title">
                            <i class="fas fa-utensils" style="color: var(--curry); margin-right: 0.5rem;"></i>
                            Menu Information
                        </h2>
                        <p class="form-description">Fill in the details for your new menu item. Upload multiple images to showcase your dish. Required fields are marked with *</p>
                    </div>

                    <div class="form-body">
                        <div class="form-grid">
                            <!-- Main Form -->
                            <div>
                                <!-- Basic Information -->
                                <div class="form-section">
                                    <h3 class="section-title">
                                        <i class="fas fa-info-circle"></i>
                                        Basic Information
                                    </h3>
                                    
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="name" class="form-label">Menu Name <span class="required">*</span></label>
                                            <input type="text" 
                                                   id="name" 
                                                   name="name" 
                                                   class="form-control" 
                                                   required 
                                                   value="<?= htmlspecialchars($form_data['name'] ?? '') ?>"
                                                   oninput="updatePreview()">
                                            <div class="form-help">English name of the dish</div>
                                        </div>
                                        <div class="form-group">
                                            <label for="name_thai" class="form-label">Thai Name</label>
                                            <input type="text" 
                                                   id="name_thai" 
                                                   name="name_thai" 
                                                   class="form-control"
                                                   value="<?= htmlspecialchars($form_data['name_thai'] ?? '') ?>"
                                                   oninput="updatePreview()">
                                            <div class="form-help">Thai name of the dish</div>
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="category_id" class="form-label">Category</label>
                                            <select id="category_id" name="category_id" class="form-control">
                                                <option value="">Select Category</option>
                                                <?php foreach ($categories as $category): ?>
                                                    <option value="<?= $category['id'] ?>" 
                                                            <?= (($form_data['category_id'] ?? '') === $category['id']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($category['name']) ?>
                                                        <?php if ($category['name_thai']): ?>
                                                            (<?= htmlspecialchars($category['name_thai']) ?>)
                                                        <?php endif; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="base_price" class="form-label">Price ($) <span class="required">*</span></label>
                                            <input type="number" 
                                                   id="base_price" 
                                                   name="base_price" 
                                                   class="form-control" 
                                                   step="0.01" 
                                                   min="0" 
                                                   required
                                                   value="<?= htmlspecialchars($form_data['base_price'] ?? '') ?>"
                                                   oninput="updatePreview()">
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea id="description" 
                                                  name="description" 
                                                  class="form-control" 
                                                  rows="3" 
                                                  placeholder="Describe this delicious dish..."
                                                  oninput="updatePreview()"><?= htmlspecialchars($form_data['description'] ?? '') ?></textarea>
                                        <div class="form-help">Brief description that will appear on the menu</div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="portion_size" class="form-label">Portion Size</label>
                                            <select id="portion_size" name="portion_size" class="form-control">
                                                <option value="Small" <?= (($form_data['portion_size'] ?? 'Regular') === 'Small') ? 'selected' : '' ?>>Small</option>
                                                <option value="Regular" <?= (($form_data['portion_size'] ?? 'Regular') === 'Regular') ? 'selected' : '' ?>>Regular</option>
                                                <option value="Large" <?= (($form_data['portion_size'] ?? 'Regular') === 'Large') ? 'selected' : '' ?>>Large</option>
                                                <option value="Family" <?= (($form_data['portion_size'] ?? 'Regular') === 'Family') ? 'selected' : '' ?>>Family Size</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="preparation_time" class="form-label">Prep Time (minutes)</label>
                                            <input type="number" 
                                                   id="preparation_time" 
                                                   name="preparation_time" 
                                                   class="form-control" 
                                                   min="1" 
                                                   value="<?= htmlspecialchars($form_data['preparation_time'] ?? '15') ?>">
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="spice_level" class="form-label">Spice Level</label>
                                        <select id="spice_level" name="spice_level" class="form-control">
                                            <option value="mild" <?= (($form_data['spice_level'] ?? 'medium') === 'mild') ? 'selected' : '' ?>>🌶️ Mild</option>
                                            <option value="medium" <?= (($form_data['spice_level'] ?? 'medium') === 'medium') ? 'selected' : '' ?>>🌶️🌶️ Medium</option>
                                            <option value="hot" <?= (($form_data['spice_level'] ?? 'medium') === 'hot') ? 'selected' : '' ?>>🌶️🌶️🌶️ Hot</option>
                                            <option value="extra_hot" <?= (($form_data['spice_level'] ?? 'medium') === 'extra_hot') ? 'selected' : '' ?>>🌶️🌶️🌶️🌶️ Extra Hot</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Image Upload Sections -->
                                <div class="form-section">
                                    <h3 class="section-title">
                                        <i class="fas fa-image"></i>
                                        Menu Images
                                    </h3>
                                    
                                    <!-- Main Image Upload -->
                                    <div class="form-group">
                                        <label class="form-label">Main Image</label>
                                        <label class="file-upload">
                                            <input type="file" name="main_image" accept="image/*" onchange="previewMainImage(this)">
                                            <div class="file-upload-icon">
                                                <i class="fas fa-camera"></i>
                                            </div>
                                            <div class="file-upload-text">
                                                <strong>Click to upload main image</strong><br>
                                                JPG, PNG, GIF or WebP (Max 5MB)
                                            </div>
                                        </label>
                                        <div id="mainImagePreviewContainer" class="d-none" style="margin-top: 1rem;">
                                            <img id="mainImagePreview" style="max-width: 200px; border-radius: var(--radius-sm);">
                                        </div>
                                    </div>

                                    <!-- Gallery Images Upload -->
                                    <div class="form-group">
                                        <label class="form-label">Gallery Images (Optional)</label>
                                        <div class="multiple-file-upload" id="galleryUploadArea">
                                            <input type="file" 
                                                   name="gallery_images[]" 
                                                   id="galleryImages" 
                                                   accept="image/*" 
                                                   multiple 
                                                   onchange="previewGalleryImages(this)"
                                                   style="position: absolute; opacity: 0; width: 100%; height: 100%; cursor: pointer;">
                                            <div class="file-upload-icon">
                                                <i class="fas fa-images"></i>
                                            </div>
                                            <div class="file-upload-text">
                                                <strong>Click to upload gallery images</strong><br>
                                                or drag and drop multiple images
                                            </div>
                                            <div class="upload-hint">
                                                Select multiple images to showcase your dish from different angles
                                            </div>
                                        </div>
                                        
                                        <!-- Gallery Preview Grid -->
                                        <div id="galleryPreviewGrid" class="image-preview-grid d-none"></div>
                                    </div>
                                </div>

                                <!-- Nutrition Information -->
                                <div class="form-section">
                                    <h3 class="section-title">
                                        <i class="fas fa-heart"></i>
                                        Nutrition Information
                                    </h3>
                                    
                                    <div class="form-row-3">
                                        <div class="form-group">
                                            <label for="calories_per_serving" class="form-label">Calories</label>
                                            <input type="number" 
                                                   id="calories_per_serving" 
                                                   name="calories_per_serving" 
                                                   class="form-control" 
                                                   min="0" 
                                                   value="<?= htmlspecialchars($form_data['calories_per_serving'] ?? '') ?>"
                                                   oninput="updatePreview()">
                                        </div>
                                        <div class="form-group">
                                            <label for="protein_g" class="form-label">Protein (g)</label>
                                            <input type="number" 
                                                   id="protein_g" 
                                                   name="protein_g" 
                                                   class="form-control" 
                                                   step="0.1" 
                                                   min="0" 
                                                   value="<?= htmlspecialchars($form_data['protein_g'] ?? '') ?>"
                                                   oninput="updatePreview()">
                                        </div>
                                        <div class="form-group">
                                            <label for="carbs_g" class="form-label">Carbs (g)</label>
                                            <input type="number" 
                                                   id="carbs_g" 
                                                   name="carbs_g" 
                                                   class="form-control" 
                                                   step="0.1" 
                                                   min="0" 
                                                   value="<?= htmlspecialchars($form_data['carbs_g'] ?? '') ?>"
                                                   oninput="updatePreview()">
                                        </div>
                                    </div>

                                    <div class="form-row-3">
                                        <div class="form-group">
                                            <label for="fat_g" class="form-label">Fat (g)</label>
                                            <input type="number" 
                                                   id="fat_g" 
                                                   name="fat_g" 
                                                   class="form-control" 
                                                   step="0.1" 
                                                   min="0" 
                                                   value="<?= htmlspecialchars($form_data['fat_g'] ?? '') ?>"
                                                   oninput="updatePreview()">
                                        </div>
                                        <div class="form-group">
                                            <label for="fiber_g" class="form-label">Fiber (g)</label>
                                            <input type="number" 
                                                   id="fiber_g" 
                                                   name="fiber_g" 
                                                   class="form-control" 
                                                   step="0.1" 
                                                   min="0" 
                                                   value="<?= htmlspecialchars($form_data['fiber_g'] ?? '') ?>">
                                        </div>
                                        <div class="form-group">
                                            <label for="sodium_mg" class="form-label">Sodium (mg)</label>
                                            <input type="number" 
                                                   id="sodium_mg" 
                                                   name="sodium_mg" 
                                                   class="form-control" 
                                                   step="0.1" 
                                                   min="0" 
                                                   value="<?= htmlspecialchars($form_data['sodium_mg'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>

                                <!-- Ingredients & Cooking -->
                                <div class="form-section">
                                    <h3 class="section-title">
                                        <i class="fas fa-leaf"></i>
                                        Ingredients & Cooking
                                    </h3>
                                    
                                    <div class="form-group">
                                        <label for="ingredients" class="form-label">Ingredients</label>
                                        <textarea id="ingredients" 
                                                  name="ingredients" 
                                                  class="form-control" 
                                                  rows="3" 
                                                  placeholder="List the main ingredients..."><?= htmlspecialchars($form_data['ingredients'] ?? '') ?></textarea>
                                        <div class="form-help">List the main ingredients used in this dish</div>
                                    </div>

                                    <div class="form-group">
                                        <label for="cooking_method" class="form-label">Cooking Method</label>
                                        <textarea id="cooking_method" 
                                                  name="cooking_method" 
                                                  class="form-control" 
                                                  rows="3" 
                                                  placeholder="Describe how this dish is prepared..."><?= htmlspecialchars($form_data['cooking_method'] ?? '') ?></textarea>
                                        <div class="form-help">Brief description of the cooking method</div>
                                    </div>
                                </div>

                                <!-- Dietary Tags -->
                                <div class="form-section">
                                    <h3 class="section-title">
                                        <i class="fas fa-tags"></i>
                                        Dietary Tags
                                    </h3>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Select Dietary Tags</label>
                                        <div class="checkbox-group">
                                            <?php foreach ($dietary_tags_options as $value => $label): ?>
                                                <div class="checkbox-item">
                                                    <input type="checkbox" 
                                                           id="dietary_<?= $value ?>" 
                                                           name="dietary_tags[]" 
                                                           value="<?= $value ?>"
                                                           <?= in_array($value, $form_data['dietary_tags'] ?? []) ? 'checked' : '' ?>>
                                                    <label for="dietary_<?= $value ?>"><?= $label ?></label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Additional Settings -->
                                <div class="form-section">
                                    <h3 class="section-title">
                                        <i class="fas fa-cog"></i>
                                        Additional Settings
                                    </h3>
                                    
                                    <div class="form-row">
                                        <div class="form-group">
                                            <div class="checkbox-item">
                                                <input type="checkbox" 
                                                       id="is_featured" 
                                                       name="is_featured" 
                                                       value="1"
                                                       <?= ($form_data['is_featured'] ?? 0) ? 'checked' : '' ?>>
                                                <label for="is_featured">
                                                    <strong>Featured Item</strong><br>
                                                    <small>Display prominently on menu</small>
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="meta_description" class="form-label">SEO Description</label>
                                        <textarea id="meta_description" 
                                                  name="meta_description" 
                                                  class="form-control" 
                                                  rows="2" 
                                                  maxlength="160"
                                                  placeholder="Brief description for search engines..."><?= htmlspecialchars($form_data['meta_description'] ?? '') ?></textarea>
                                        <div class="form-help">Optional: Description for search engines (max 160 characters)</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Preview Panel -->
                            <div class="preview-panel">
                                <h3 class="preview-title">
                                    <i class="fas fa-eye"></i>
                                    Live Preview
                                </h3>
                                <div class="menu-preview" id="menuPreview">
                                    <div class="menu-preview-image" id="previewImage">
                                        <i class="fas fa-utensils"></i>
                                    </div>
                                    <div class="preview-gallery" id="previewGallery" style="display: none;"></div>
                                    <div class="menu-preview-body">
                                        <div class="menu-preview-name" id="previewName">Menu Name</div>
                                        <div class="menu-preview-name-thai" id="previewNameThai"></div>
                                        <div class="menu-preview-price" id="previewPrice">$0.00</div>
                                        <div class="menu-preview-description" id="previewDescription"></div>
                                        <div class="menu-preview-nutrition" id="previewNutrition">
                                            <div class="nutrition-item">
                                                <div class="nutrition-value" id="previewCalories">-</div>
                                                <div class="nutrition-label">Calories</div>
                                            </div>
                                            <div class="nutrition-item">
                                                <div class="nutrition-value" id="previewProtein">-</div>
                                                <div class="nutrition-label">Protein</div>
                                            </div>
                                            <div class="nutrition-item">
                                                <div class="nutrition-value" id="previewCarbs">-</div>
                                                <div class="nutrition-label">Carbs</div>
                                            </div>
                                            <div class="nutrition-item">
                                                <div class="nutrition-value" id="previewFat">-</div>
                                                <div class="nutrition-label">Fat</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <a href="menus.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                            Cancel
                        </a>
                        <button type="button" class="btn btn-secondary" onclick="resetForm()">
                            <i class="fas fa-undo"></i>
                            Reset Form
                        </button>
                        <button type="submit" class="btn btn-success" id="submitBtn">
                            <i class="fas fa-save"></i>
                            <span id="submitText">Create Menu Item</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <script>
        // Global variables for image handling
        let galleryImages = [];
        let mainImageFile = null;

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Add Menu page initialized with multiple image support');
            updatePreview();
            initializeCheckboxStyling();
            initializeDragAndDrop();
        });

        // Initialize drag and drop for gallery images
        function initializeDragAndDrop() {
            const galleryUploadArea = document.getElementById('galleryUploadArea');
            
            galleryUploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.style.background = 'rgba(207, 114, 58, 0.15)';
            });
            
            galleryUploadArea.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.style.background = 'rgba(207, 114, 58, 0.05)';
            });
            
            galleryUploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                this.style.background = 'rgba(207, 114, 58, 0.05)';
                
                const files = e.dataTransfer.files;
                handleGalleryFiles(files);
            });
        }

        // Handle gallery files (from drop or input)
        function handleGalleryFiles(files) {
            const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            const maxSize = 5 * 1024 * 1024; // 5MB
            
            Array.from(files).forEach(file => {
                if (!validTypes.includes(file.type)) {
                    showToast(`${file.name} is not a valid image type`, 'error');
                    return;
                }
                
                if (file.size > maxSize) {
                    showToast(`${file.name} is too large (max 5MB)`, 'error');
                    return;
                }
                
                // Add to gallery images array
                galleryImages.push(file);
                addImageToGalleryPreview(file, galleryImages.length - 1);
            });
            
            updateGalleryPreview();
            updatePreview();
        }

        // Preview main image
        function previewMainImage(input) {
            if (input.files && input.files[0]) {
                mainImageFile = input.files[0];
                const reader = new FileReader();
                reader.onload = function(e) {
                    const previewContainer = document.getElementById('mainImagePreviewContainer');
                    const previewImg = document.getElementById('mainImagePreview');
                    const previewImageDiv = document.getElementById('previewImage');
                    
                    previewImg.src = e.target.result;
                    previewContainer.classList.remove('d-none');
                    
                    // Update preview panel
                    previewImageDiv.innerHTML = `<img src="${e.target.result}" style="width: 100%; height: 100%; object-fit: cover;">`;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Preview gallery images
        function previewGalleryImages(input) {
            if (input.files && input.files.length > 0) {
                handleGalleryFiles(input.files);
            }
        }

        // Add image to gallery preview
        function addImageToGalleryPreview(file, index) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const galleryGrid = document.getElementById('galleryPreviewGrid');
                galleryGrid.classList.remove('d-none');
                
                const imageContainer = document.createElement('div');
                imageContainer.className = 'preview-image-container';
                imageContainer.dataset.index = index;
                
                imageContainer.innerHTML = `
                    <img src="${e.target.result}" alt="Gallery Image ${index + 1}" class="preview-image">
                    <button type="button" class="remove-image-btn" onclick="removeGalleryImage(${index})">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                
                galleryGrid.appendChild(imageContainer);
                updateGalleryPreview();
            };
            reader.readAsDataURL(file);
        }

        // Remove gallery image
        function removeGalleryImage(index) {
            galleryImages.splice(index, 1);
            updateGalleryPreview();
            updatePreview();
            showToast('Image removed', 'info');
        }

        // Update gallery preview
        function updateGalleryPreview() {
            const galleryGrid = document.getElementById('galleryPreviewGrid');
            const previewGallery = document.getElementById('previewGallery');
            
            if (galleryImages.length === 0) {
                galleryGrid.classList.add('d-none');
                previewGallery.style.display = 'none';
                return;
            }
            
            galleryGrid.classList.remove('d-none');
            galleryGrid.innerHTML = '';
            
            // Update preview panel gallery
            previewGallery.innerHTML = '';
            previewGallery.style.display = 'grid';
            
            galleryImages.forEach((file, index) => {
                // Add to main gallery grid
                const imageContainer = document.createElement('div');
                imageContainer.className = 'preview-image-container';
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    imageContainer.innerHTML = `
                        <img src="${e.target.result}" alt="Gallery Image ${index + 1}" class="preview-image">
                        <button type="button" class="remove-image-btn" onclick="removeGalleryImage(${index})">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    
                    // Add to preview gallery (first 4 images only)
                    if (index < 4) {
                        const previewImg = document.createElement('div');
                        previewImg.className = 'preview-gallery-image';
                        previewImg.innerHTML = `<img src="${e.target.result}" alt="Preview ${index + 1}">`;
                        previewGallery.appendChild(previewImg);
                    }
                };
                reader.readAsDataURL(file);
                
                galleryGrid.appendChild(imageContainer);
            });
            
            // Show count if more than 4 images
            if (galleryImages.length > 4) {
                const countDiv = document.createElement('div');
                countDiv.className = 'preview-gallery-image';
                countDiv.innerHTML = `<div style="display: flex; align-items: center; justify-content: center; height: 100%; background: var(--cream); color: var(--text-dark); font-weight: 600;">+${galleryImages.length - 3}</div>`;
                previewGallery.appendChild(countDiv);
            }
        }

        // Update preview in real-time
        function updatePreview() {
            const name = document.getElementById('name').value || 'Menu Name';
            const nameThai = document.getElementById('name_thai').value;
            const price = document.getElementById('base_price').value || '0';
            const description = document.getElementById('description').value;
            const calories = document.getElementById('calories_per_serving').value;
            const protein = document.getElementById('protein_g').value;
            const carbs = document.getElementById('carbs_g').value;
            const fat = document.getElementById('fat_g').value;

            document.getElementById('previewName').textContent = name;
            document.getElementById('previewNameThai').textContent = nameThai;
            document.getElementById('previewNameThai').style.display = nameThai ? 'block' : 'none';
            document.getElementById('previewPrice').textContent = `$${parseFloat(price).toFixed(2)}`;
            document.getElementById('previewDescription').textContent = description;
            document.getElementById('previewDescription').style.display = description ? 'block' : 'none';
            
            document.getElementById('previewCalories').textContent = calories || '-';
            document.getElementById('previewProtein').textContent = protein ? protein + 'g' : '-';
            document.getElementById('previewCarbs').textContent = carbs ? carbs + 'g' : '-';
            document.getElementById('previewFat').textContent = fat ? fat + 'g' : '-';
        }

        // Initialize checkbox styling
        function initializeCheckboxStyling() {
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
        }

        // Reset form
        function resetForm() {
            if (confirm('Are you sure you want to reset the form? All entered data and uploaded images will be lost.')) {
                document.getElementById('menuForm').reset();
                
                // Reset images
                document.getElementById('mainImagePreviewContainer').classList.add('d-none');
                document.getElementById('galleryPreviewGrid').classList.add('d-none');
                document.getElementById('previewImage').innerHTML = '<i class="fas fa-utensils"></i>';
                document.getElementById('previewGallery').style.display = 'none';
                
                // Clear image arrays
                galleryImages = [];
                mainImageFile = null;
                
                // Reset checkbox styling
                document.querySelectorAll('.checkbox-item').forEach(item => {
                    item.classList.remove('checked');
                });
                
                updatePreview();
                showToast('Form has been reset', 'info');
            }
        }

        // Preview menu (same as live preview but with animation)
        function previewMenu() {
            const preview = document.getElementById('menuPreview');
            preview.style.transform = 'scale(1.05)';
            preview.style.boxShadow = '0 15px 35px rgba(0,0,0,0.2)';
            
            setTimeout(() => {
                preview.style.transform = 'scale(1)';
                preview.style.boxShadow = 'var(--shadow-soft)';
            }, 200);
            
            const imageCount = (mainImageFile ? 1 : 0) + galleryImages.length;
            showToast(`Preview updated! ${imageCount} image(s) ready for upload.`, 'info');
        }

        // Form submission handling
        document.getElementById('menuForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            const submitText = document.getElementById('submitText');
            
            // Validate required fields
            const name = document.getElementById('name').value.trim();
            const price = document.getElementById('base_price').value.trim();
            
            if (!name) {
                e.preventDefault();
                showToast('Please enter a menu name', 'error');
                document.getElementById('name').focus();
                return;
            }
            
            if (!price || parseFloat(price) <= 0) {
                e.preventDefault();
                showToast('Please enter a valid price', 'error');
                document.getElementById('base_price').focus();
                return;
            }
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.classList.add('loading');
            
            const imageCount = (mainImageFile ? 1 : 0) + galleryImages.length;
            submitText.textContent = `Creating Menu with ${imageCount} image${imageCount !== 1 ? 's' : ''}...`;
            
            // Re-enable after timeout (in case of server errors)
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.classList.remove('loading');
                submitText.textContent = 'Create Menu Item';
            }, 15000);
        });

        // Real-time validation
        document.getElementById('name').addEventListener('blur', function() {
            if (this.value.trim() === '') {
                this.classList.add('error');
                showToast('Menu name is required', 'error');
            } else {
                this.classList.remove('error');
            }
        });

        document.getElementById('base_price').addEventListener('blur', function() {
            const price = parseFloat(this.value);
            if (this.value.trim() === '' || isNaN(price) || price <= 0) {
                this.classList.add('error');
                showToast('Please enter a valid price', 'error');
            } else {
                this.classList.remove('error');
            }
        });

        // Toast notification system
        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            
            toast.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                        <span>${message}</span>
                    </div>
                    <button onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; cursor: pointer; color: var(--text-gray);">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            container.appendChild(toast);
            
            // Show toast
            setTimeout(() => toast.classList.add('show'), 100);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }

        // Logout function
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                showToast('Logging out...', 'info');
                
                fetch('../auth/logout.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=logout'
                })
                .then(response => {
                    window.location.href = '../login.php';
                })
                .catch(error => {
                    console.error('Logout error:', error);
                    window.location.href = '../login.php';
                });
            }
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                showToast('Auto-save feature coming soon!', 'info');
            }
            if (e.ctrlKey && e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('submitBtn').click();
            }
            if (e.key === 'Escape') {
                const activeElement = document.activeElement;
                if (activeElement && activeElement.tagName !== 'BODY') {
                    activeElement.blur();
                }
            }
        });

        // Monitor form completion
        function updateFormProgress() {
            const requiredFields = document.querySelectorAll('input[required], textarea[required]');
            const filledFields = Array.from(requiredFields).filter(field => field.value.trim() !== '').length;
            const imageCount = (mainImageFile ? 1 : 0) + galleryImages.length;
            const progress = Math.round((filledFields / requiredFields.length) * 100);
            
            // Update page title with progress and image count
            document.title = `Add New Menu (${progress}% complete, ${imageCount} images) - Somdul Table Admin`;
            
            return progress;
        }

        // Monitor form completion
        document.querySelectorAll('input, textarea, select').forEach(field => {
            field.addEventListener('input', updateFormProgress);
        });

        // Performance monitoring
        window.addEventListener('load', function() {
            const loadTime = performance.now();
            console.log(`Add Menu page loaded in ${Math.round(loadTime)}ms`);
        });

        console.log('Somdul Table Add Menu with Multiple Images initialized successfully');
    </script>
</body>
</html>