<?php
/**
 * Somdul Table - Edit Menu Item with Multiple Images Support
 * File: admin/edit-menu.php
 * Description: Complete form for editing existing menu items with multiple image support
 */
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php"); 
    exit();
}

// Get menu ID from URL
$menu_id = $_GET['id'] ?? '';
if (!$menu_id) {
    header("Location: menus.php");
    exit();
}

$errors = [];
$success_message = '';
$form_data = [];

try {
    $pdo = (new Database())->getConnection();
    
    // Fetch existing menu data
    $stmt = $pdo->prepare("
        SELECT m.*, mc.name as category_name, mc.name_thai as category_name_thai
        FROM menus m 
        LEFT JOIN menu_categories mc ON m.category_id = mc.id
        WHERE m.id = ?
    ");
    $stmt->execute([$menu_id]);
    $menu = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$menu) {
        header("Location: menus.php?error=Menu not found");
        exit();
    }
    
    // Parse JSON fields
    $dietary_tags = $menu['dietary_tags'] ? json_decode($menu['dietary_tags'], true) : [];
    $additional_images = $menu['additional_images'] ? json_decode($menu['additional_images'], true) : [];
    
    // Set form data to existing values
    $form_data = array_merge($menu, [
        'dietary_tags' => $dietary_tags,
        'additional_images' => $additional_images
    ]);
    
} catch (Exception $e) {
    $errors[] = "Error loading menu: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle image deletion if requested
    if (isset($_POST['delete_image'])) {
        $image_to_delete = $_POST['delete_image'];
        $current_additional_images = json_decode($menu['additional_images'] ?? '[]', true);
        
        // Remove the image from array
        $updated_images = array_filter($current_additional_images, function($img) use ($image_to_delete) {
            return $img !== $image_to_delete;
        });
        
        // Delete physical file
        if (file_exists('../' . $image_to_delete)) {
            unlink('../' . $image_to_delete);
        }
        
        // Update database
        $stmt = $pdo->prepare("UPDATE menus SET additional_images = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([json_encode(array_values($updated_images)), $menu_id]);
        
        $success_message = "Image deleted successfully!";
        
        // Refresh menu data
        $stmt = $pdo->prepare("SELECT additional_images FROM menus WHERE id = ?");
        $stmt->execute([$menu_id]);
        $menu['additional_images'] = $stmt->fetchColumn();
        $form_data['additional_images'] = json_decode($menu['additional_images'] ?? '[]', true);
    } else {
        // Regular form submission
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
            'is_seasonal' => isset($_POST['is_seasonal']) ? 1 : 0,
            'is_available' => isset($_POST['is_available']) ? 1 : 0,
            'availability_start' => sanitizeInput($_POST['availability_start'] ?? ''),
            'availability_end' => sanitizeInput($_POST['availability_end'] ?? ''),
            'meta_description' => sanitizeInput($_POST['meta_description'] ?? '')
        ];

        // Validate required fields
        if (empty($form_data['name'])) {
            $errors[] = "Menu name is required";
        }
        
        if (empty($form_data['base_price']) || !is_numeric($form_data['base_price'])) {
            $errors[] = "Valid base price is required";
        }

        // Handle main image upload
        $main_image_url = $menu['main_image_url']; // Keep existing image by default
        if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
            $upload_result = handleImageUpload($_FILES['main_image'], 'menu_images');
            if ($upload_result['success']) {
                $main_image_url = $upload_result['file_path'];
                // Delete old image if exists
                if ($menu['main_image_url'] && file_exists('../' . $menu['main_image_url'])) {
                    unlink('../' . $menu['main_image_url']);
                }
            } else {
                $errors[] = $upload_result['message'];
            }
        }

        // Handle additional images upload
        $current_additional_images = json_decode($menu['additional_images'] ?? '[]', true);
        $new_additional_images = $current_additional_images;

        if (isset($_FILES['additional_images']) && is_array($_FILES['additional_images']['name'])) {
            foreach ($_FILES['additional_images']['name'] as $key => $name) {
                if ($_FILES['additional_images']['error'][$key] === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $_FILES['additional_images']['name'][$key],
                        'type' => $_FILES['additional_images']['type'][$key],
                        'tmp_name' => $_FILES['additional_images']['tmp_name'][$key],
                        'error' => $_FILES['additional_images']['error'][$key],
                        'size' => $_FILES['additional_images']['size'][$key]
                    ];
                    
                    $upload_result = handleImageUpload($file, 'menu_images');
                    if ($upload_result['success']) {
                        $new_additional_images[] = $upload_result['file_path'];
                    } else {
                        $errors[] = "Additional image upload error: " . $upload_result['message'];
                    }
                }
            }
        }

        // Update menu if no errors
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // Generate slug from name
                $slug = generateSlug($form_data['name']);
                
                // Convert arrays to JSON
                $dietary_tags_json = !empty($form_data['dietary_tags']) ? json_encode($form_data['dietary_tags']) : null;
                $additional_images_json = !empty($new_additional_images) ? json_encode($new_additional_images) : null;

                // Update menu
                $menu_sql = "
                    UPDATE menus SET 
                        category_id = ?, name = ?, name_thai = ?, description = ?, ingredients = ?, 
                        cooking_method = ?, main_image_url = ?, additional_images = ?, base_price = ?, portion_size = ?, 
                        preparation_time = ?, calories_per_serving = ?, protein_g = ?, carbs_g = ?, 
                        fat_g = ?, fiber_g = ?, sodium_mg = ?, sugar_g = ?, 
                        dietary_tags = ?, spice_level = ?, is_featured = ?, is_seasonal = ?, 
                        is_available = ?, availability_start = ?, availability_end = ?, slug = ?, 
                        meta_description = ?, updated_at = NOW()
                    WHERE id = ?
                ";

                $stmt = $pdo->prepare($menu_sql);
                $stmt->execute([
                    $form_data['category_id'] ?: null,
                    $form_data['name'],
                    $form_data['name_thai'] ?: null,
                    $form_data['description'] ?: null,
                    $form_data['ingredients'] ?: null,
                    $form_data['cooking_method'] ?: null,
                    $main_image_url ?: null,
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
                    $form_data['is_seasonal'],
                    $form_data['is_available'],
                    $form_data['availability_start'] ?: null,
                    $form_data['availability_end'] ?: null,
                    $slug,
                    $form_data['meta_description'] ?: null,
                    $menu_id
                ]);

                $pdo->commit();

                // Log activity
                logActivity('menu_updated', $_SESSION['user_id'], getRealIPAddress(), [
                    'menu_id' => $menu_id,
                    'menu_name' => $form_data['name']
                ]);

                $success_message = "Menu item '{$form_data['name']}' has been updated successfully!";
                
                // Update form_data with new additional images for display
                $form_data['additional_images'] = $new_additional_images;

            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "Error updating menu item: " . $e->getMessage();
                error_log("Menu update error: " . $e->getMessage());
            }
        }
    }
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

// Dietary tags options
$dietary_tags_options = [
    'vegetarian' => 'Vegetarian',
    'vegan' => 'Vegan',
    'gluten_free' => 'Gluten Free',
    'dairy_free' => 'Dairy Free',
    'low_sodium' => 'Low Sodium',
    'keto_friendly' => 'Keto Friendly',
    'paleo' => 'Paleo',
    'organic' => 'Organic',
    'locally_sourced' => 'Locally Sourced',
    'halal' => 'Halal',
    'kids_meal' => 'Kids Meal'
];

// Image upload function
function handleImageUpload($file, $upload_dir) {
    $upload_path = "../uploads/$upload_dir/";
    
    // Create directory if it doesn't exist
    if (!is_dir($upload_path)) {
        mkdir($upload_path, 0777, true);
    }
    
    // Validate file
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.'];
    }
    
    if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
        return ['success' => false, 'message' => 'File size too large. Maximum 5MB allowed.'];
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('menu_' . time() . '_') . '.' . $extension;
    $full_path = $upload_path . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $full_path)) {
        return ['success' => true, 'file_path' => "uploads/$upload_dir/$filename"];
    } else {
        return ['success' => false, 'message' => 'Failed to upload file.'];
    }
}

// Slug generation function
function generateSlug($text) {
    $text = preg_replace('/[^a-zA-Z0-9\s]/', '', $text);
    $text = preg_replace('/\s+/', '-', trim($text));
    return strtolower($text);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Menu - Somdul Table Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Batica+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --brown: #bd9379;
            --cream: #ece8e1;
            --sage: #adb89d;
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
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Batica Sans', sans-serif;
            background: linear-gradient(135deg, var(--cream) 0%, #f8f6f3 100%);
            color: var(--text-dark);
            line-height: 1.6;
        }

        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
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

        .btn-small {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
        }

        /* Form Layout */
        .form-container {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 2rem;
        }

        .form-main {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
            overflow: hidden;
        }

        .form-sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        /* Form Sections */
        .form-section {
            padding: 2rem;
            border-bottom: 1px solid var(--border-light);
        }

        .form-section:last-child {
            border-bottom: none;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--curry);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
            transition: var(--transition);
            background: var(--white);
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--curry);
            box-shadow: 0 0 0 3px rgba(207, 114, 58, 0.1);
        }

        .form-help {
            font-size: 0.8rem;
            color: var(--text-gray);
            margin-top: 0.25rem;
        }

        /* File Upload */
        .file-upload {
            position: relative;
            display: inline-block;
            cursor: pointer;
            width: 100%;
        }

        .file-upload input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .file-upload-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 2rem;
            border: 2px dashed var(--border-light);
            border-radius: var(--radius-sm);
            color: var(--text-gray);
            transition: var(--transition);
            background: var(--cream);
        }

        .file-upload:hover .file-upload-label {
            border-color: var(--curry);
            color: var(--curry);
        }

        .current-image {
            width: 100%;
            max-width: 200px;
            height: auto;
            border-radius: var(--radius-sm);
            margin-bottom: 1rem;
        }

        /* Additional Images Section */
        .additional-images-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .image-item {
            position: relative;
            border-radius: var(--radius-sm);
            overflow: hidden;
            background: var(--cream);
        }

        .image-item img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            display: block;
        }

        .image-delete-btn {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: rgba(231, 76, 60, 0.9);
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.8rem;
        }

        .image-delete-btn:hover {
            background: #e74c3c;
            transform: scale(1.1);
        }

        /* Multiple File Upload */
        .multiple-file-upload {
            position: relative;
            width: 100%;
        }

        .multiple-file-upload input[type="file"] {
            width: 100%;
            padding: 0.75rem;
            border: 2px dashed var(--border-light);
            border-radius: var(--radius-sm);
            background: var(--cream);
            cursor: pointer;
        }

        /* Checkbox Groups */
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            border-radius: var(--radius-sm);
            transition: var(--transition);
        }

        .checkbox-item:hover {
            background: var(--cream);
        }

        .checkbox-item input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--curry);
        }

        .checkbox-item label {
            font-size: 0.85rem;
            cursor: pointer;
            margin: 0;
        }

        /* Preview Card */
        .preview-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
            overflow: hidden;
        }

        .preview-header {
            background: linear-gradient(135deg, var(--cream), #f5f2ef);
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-light);
        }

        .preview-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .preview-body {
            padding: 1.5rem;
        }

        .menu-preview {
            border: 1px solid var(--border-light);
            border-radius: var(--radius-md);
            overflow: hidden;
            background: var(--white);
        }

        .menu-preview-image {
            height: 150px;
            background: linear-gradient(135deg, var(--cream), #f5f2ef);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-gray);
            font-size: 2rem;
        }

        .menu-preview-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .menu-preview-content {
            padding: 1rem;
        }

        .menu-preview-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.25rem;
        }

        .menu-preview-price {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--curry);
            margin-bottom: 0.5rem;
        }

        .menu-preview-description {
            color: var(--text-gray);
            font-size: 0.85rem;
            line-height: 1.4;
        }

        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: var(--radius-sm);
            margin-bottom: 1rem;
            border: 1px solid;
        }

        .alert-success {
            background: rgba(46, 204, 113, 0.1);
            border-color: #27ae60;
            color: #27ae60;
        }

        .alert-error {
            background: rgba(231, 76, 60, 0.1);
            border-color: #e74c3c;
            color: #e74c3c;
        }

        /* Form Actions */
        .form-actions {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
            padding: 1.5rem;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 260px;
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .form-container {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .checkbox-group {
                grid-template-columns: 1fr;
            }

            .header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .additional-images-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <img src="../assets/image/LOGO_BG2.png" 
                         alt="Somdul Table Logo" 
                         class="logo-image">
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
                            <i class="fas fa-edit" style="color: var(--curry); margin-right: 0.5rem;"></i>
                            Edit Menu Item
                        </h1>
                        <p class="page-subtitle">Update your delicious Thai menu item details and images</p>
                    </div>
                    <div class="header-actions">
                        <a href="menus.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            Back to Menus
                        </a>
                        <a href="add-menu.php" class="btn btn-success">
                            <i class="fas fa-plus"></i>
                            Add New Menu
                        </a>
                    </div>
                </div>
            </div>

            <!-- Alerts -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <h4><i class="fas fa-exclamation-triangle"></i> Please fix the following errors:</h4>
                    <ul style="margin: 0.5rem 0 0 1.5rem;">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="POST" enctype="multipart/form-data" id="menuForm">
                <div class="form-container">
                    <!-- Main Form -->
                    <div class="form-main">
                        <!-- Basic Information -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-info-circle"></i>
                                Basic Information
                            </h3>
                            
                            <div class="form-group">
                                <label for="category_id" class="form-label">Category</label>
                                <select id="category_id" name="category_id" class="form-control">
                                    <option value="">Select a category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id'] ?>" <?= ($form_data['category_id'] ?? '') === $category['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($category['name']) ?>
                                            <?php if ($category['name_thai']): ?>
                                                (<?= htmlspecialchars($category['name_thai']) ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="name" class="form-label">Menu Name *</label>
                                    <input type="text" 
                                           id="name" 
                                           name="name" 
                                           class="form-control" 
                                           required 
                                           placeholder="e.g., Pad Thai" 
                                           value="<?= htmlspecialchars($form_data['name'] ?? '') ?>"
                                           oninput="updatePreview()">
                                </div>
                                <div class="form-group">
                                    <label for="name_thai" class="form-label">Thai Name</label>
                                    <input type="text" 
                                           id="name_thai" 
                                           name="name_thai" 
                                           class="form-control" 
                                           placeholder="e.g., ผัดไทย" 
                                           value="<?= htmlspecialchars($form_data['name_thai'] ?? '') ?>"
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
                                    <label for="base_price" class="form-label">Price (USD) *</label>
                                    <input type="number" 
                                           id="base_price" 
                                           name="base_price" 
                                           class="form-control" 
                                           step="0.01" 
                                           min="0" 
                                           required 
                                           placeholder="0.00" 
                                           value="<?= htmlspecialchars($form_data['base_price'] ?? '') ?>"
                                           oninput="updatePreview()">
                                </div>
                                <div class="form-group">
                                    <label for="portion_size" class="form-label">Portion Size</label>
                                    <select id="portion_size" name="portion_size" class="form-control">
                                        <option value="Small" <?= (($form_data['portion_size'] ?? 'Regular') === 'Small') ? 'selected' : '' ?>>Small</option>
                                        <option value="Regular" <?= (($form_data['portion_size'] ?? 'Regular') === 'Regular') ? 'selected' : '' ?>>Regular</option>
                                        <option value="Large" <?= (($form_data['portion_size'] ?? 'Regular') === 'Large') ? 'selected' : '' ?>>Large</option>
                                        <option value="Family" <?= (($form_data['portion_size'] ?? 'Regular') === 'Family') ? 'selected' : '' ?>>Family Size</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="preparation_time" class="form-label">Prep Time (minutes)</label>
                                    <input type="number" 
                                           id="preparation_time" 
                                           name="preparation_time" 
                                           class="form-control" 
                                           min="1" 
                                           value="<?= htmlspecialchars($form_data['preparation_time'] ?? '15') ?>">
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
                        </div>

                        <!-- Image Upload Section -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-image"></i>
                                Menu Images
                            </h3>
                            
                            <!-- Main Image -->
                            <div class="form-group">
                                <label class="form-label">Main Image</label>
                                <?php if (!empty($form_data['main_image_url'])): ?>
                                    <img src="../<?= htmlspecialchars($form_data['main_image_url']) ?>" 
                                         alt="Current main image" 
                                         class="current-image">
                                <?php endif; ?>
                                
                                <div class="file-upload">
                                    <input type="file" 
                                           id="main_image" 
                                           name="main_image" 
                                           accept="image/*" 
                                           onchange="previewMainImage(this)">
                                    <div class="file-upload-label">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <span>Click to upload main image</span>
                                    </div>
                                </div>
                                <div class="form-help">JPG, PNG, GIF, or WebP. Maximum 5MB.</div>
                            </div>

                            <!-- Additional Images -->
                            <div class="form-group">
                                <label class="form-label">Additional Images</label>
                                
                                <!-- Existing Additional Images -->
                                <?php if (!empty($form_data['additional_images'])): ?>
                                    <div class="additional-images-grid">
                                        <?php foreach ($form_data['additional_images'] as $image): ?>
                                            <div class="image-item">
                                                <img src="../<?= htmlspecialchars($image) ?>" alt="Additional image">
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="delete_image" value="<?= htmlspecialchars($image) ?>">
                                                    <button type="submit" class="image-delete-btn" 
                                                            onclick="return confirm('Are you sure you want to delete this image?')">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Upload New Additional Images -->
                                <div class="multiple-file-upload">
                                    <input type="file" 
                                           id="additional_images" 
                                           name="additional_images[]" 
                                           accept="image/*" 
                                           multiple>
                                    <div class="form-help">Hold Ctrl/Cmd to select multiple images. JPG, PNG, GIF, or WebP. Maximum 5MB each.</div>
                                </div>
                            </div>
                        </div>

                        <!-- Nutrition Information -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-heart"></i>
                                Nutrition Information
                            </h3>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="calories_per_serving" class="form-label">Calories per Serving</label>
                                    <input type="number" 
                                           id="calories_per_serving" 
                                           name="calories_per_serving" 
                                           class="form-control" 
                                           step="0.1" 
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
                                           value="<?= htmlspecialchars($form_data['protein_g'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="carbs_g" class="form-label">Carbs (g)</label>
                                    <input type="number" 
                                           id="carbs_g" 
                                           name="carbs_g" 
                                           class="form-control" 
                                           step="0.1" 
                                           min="0" 
                                           value="<?= htmlspecialchars($form_data['carbs_g'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label for="fat_g" class="form-label">Fat (g)</label>
                                    <input type="number" 
                                           id="fat_g" 
                                           name="fat_g" 
                                           class="form-control" 
                                           step="0.1" 
                                           min="0" 
                                           value="<?= htmlspecialchars($form_data['fat_g'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <div class="form-row">
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
                            
                            <div class="form-group">
                                <label for="sugar_g" class="form-label">Sugar (g)</label>
                                <input type="number" 
                                       id="sugar_g" 
                                       name="sugar_g" 
                                       class="form-control" 
                                       step="0.1" 
                                       min="0" 
                                       value="<?= htmlspecialchars($form_data['sugar_g'] ?? '') ?>">
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

                        <!-- Advanced Options -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-cogs"></i>
                                Advanced Options
                            </h3>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="availability_start" class="form-label">Available From</label>
                                    <input type="date" 
                                           id="availability_start" 
                                           name="availability_start" 
                                           class="form-control" 
                                           value="<?= htmlspecialchars($form_data['availability_start'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label for="availability_end" class="form-label">Available Until</label>
                                    <input type="date" 
                                           id="availability_end" 
                                           name="availability_end" 
                                           class="form-control" 
                                           value="<?= htmlspecialchars($form_data['availability_end'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="meta_description" class="form-label">SEO Description</label>
                                <textarea id="meta_description" 
                                          name="meta_description" 
                                          class="form-control" 
                                          rows="2" 
                                          placeholder="SEO-friendly description for search engines..."><?= htmlspecialchars($form_data['meta_description'] ?? '') ?></textarea>
                                <div class="form-help">Used for search engine optimization</div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="form-sidebar">
                        <!-- Menu Preview -->
                        <div class="preview-card">
                            <div class="preview-header">
                                <h3 class="preview-title">
                                    <i class="fas fa-eye"></i>
                                    Menu Preview
                                </h3>
                            </div>
                            <div class="preview-body">
                                <div class="menu-preview" id="menuPreview">
                                    <div class="menu-preview-image" id="previewImage">
                                        <?php if (!empty($form_data['main_image_url'])): ?>
                                            <img src="../<?= htmlspecialchars($form_data['main_image_url']) ?>" alt="Menu preview">
                                        <?php else: ?>
                                            <i class="fas fa-utensils"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="menu-preview-content">
                                        <h4 class="menu-preview-title" id="previewTitle">
                                            <?= htmlspecialchars($form_data['name'] ?? 'Menu Name') ?>
                                        </h4>
                                        <div class="menu-preview-price" id="previewPrice">
                                            $<?= htmlspecialchars($form_data['base_price'] ?? '0') ?>
                                        </div>
                                        <p class="menu-preview-description" id="previewDescription">
                                            <?= htmlspecialchars($form_data['description'] ?? 'Menu description will appear here...') ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Status Settings -->
                        <div class="preview-card">
                            <div class="preview-header">
                                <h3 class="preview-title">
                                    <i class="fas fa-toggle-on"></i>
                                    Status Settings
                                </h3>
                            </div>
                            <div class="preview-body">
                                <div class="form-group">
                                    <div class="checkbox-item">
                                        <input type="checkbox" 
                                               id="is_available" 
                                               name="is_available" 
                                               <?= ($form_data['is_available'] ?? 1) ? 'checked' : '' ?>>
                                        <label for="is_available">Available for ordering</label>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <div class="checkbox-item">
                                        <input type="checkbox" 
                                               id="is_featured" 
                                               name="is_featured" 
                                               <?= ($form_data['is_featured'] ?? 0) ? 'checked' : '' ?>>
                                        <label for="is_featured">Featured menu item</label>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <div class="checkbox-item">
                                        <input type="checkbox" 
                                               id="is_seasonal" 
                                               name="is_seasonal" 
                                               <?= ($form_data['is_seasonal'] ?? 0) ? 'checked' : '' ?>>
                                        <label for="is_seasonal">Seasonal item</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary" style="width: 100%; margin-bottom: 0.5rem;">
                                <i class="fas fa-save"></i>
                                Update Menu Item
                            </button>
                            <a href="menus.php" class="btn btn-secondary" style="width: 100%; text-align: center;">
                                <i class="fas fa-times"></i>
                                Cancel
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Update preview function
        function updatePreview() {
            const name = document.getElementById('name').value || 'Menu Name';
            const price = document.getElementById('base_price').value || '0';
            const description = document.getElementById('description').value || 'Menu description will appear here...';
            
            document.getElementById('previewTitle').textContent = name;
            document.getElementById('previewPrice').textContent = '$' + price;
            document.getElementById('previewDescription').textContent = description;
        }

        // Preview main image function
        function previewMainImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const previewContainer = document.getElementById('previewImage');
                    previewContainer.innerHTML = `<img src="${e.target.result}" alt="Menu preview">`;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Form validation
        document.getElementById('menuForm').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const price = document.getElementById('base_price').value;
            
            if (!name) {
                e.preventDefault();
                alert('Please enter a menu name');
                document.getElementById('name').focus();
                return;
            }
            
            if (!price || parseFloat(price) <= 0) {
                e.preventDefault();
                alert('Please enter a valid price');
                document.getElementById('base_price').focus();
                return;
            }
        });

        // File input styling for additional images
        document.getElementById('additional_images').addEventListener('change', function() {
            const fileCount = this.files.length;
            const label = this.nextElementSibling;
            
            if (fileCount > 0) {
                label.textContent = `${fileCount} image(s) selected`;
            } else {
                label.textContent = 'Hold Ctrl/Cmd to select multiple images. JPG, PNG, GIF, or WebP. Maximum 5MB each.';
            }
        });

        // Initialize preview
        updatePreview();

        console.log('Somdul Table Edit Menu with Multiple Images initialized successfully');
    </script>
</body>
</html>