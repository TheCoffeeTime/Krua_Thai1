<?php
/**
 * Kitchen Dashboard - Somdul Table (COMPLETE ENGLISH VERSION)
 * File: kitchen_dashboard.php
 * Role: kitchen, admin only
 * Status: PRODUCTION READY ✅
 * Focus: Delivery preparation (Wednesday & Saturday only)
 * Language: English
 * Timezone: America/New_York
 * 
 * 🔥 FEATURES:
 * - Query logic fixed - no incorrect DISTINCT usage
 * - Shows complete customer and menu information
 * - Debug information included
 */

// Start output buffering to prevent header issues
ob_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session FIRST
session_start();

// Set timezone to US Eastern
date_default_timezone_set('America/New_York');

// Role-based access control
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Check if user has kitchen or admin role
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['kitchen', 'admin'])) {
    die('<div style="padding: 20px; background: #f8d7da; color: #721c24; border-radius: 10px; margin: 20px; font-family: Arial;">
        <h3>🚫 Access Denied</h3>
        <p>You do not have permission to access the kitchen dashboard.</p>
        <p>Required roles: Kitchen Staff or Admin</p>
        <a href="../admin/dashboard.php" style="background: #007bff; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;">← Back to Dashboard</a>
    </div>');
}

require_once '../config/database.php';
require_once '../includes/functions.php';

// Database connection with PDO
try {
    $database = new Database();
    $pdo = $database->getConnection();
} catch (Exception $e) {
    die("❌ Database connection failed: " . $e->getMessage());
}

// Get upcoming delivery days (Wednesday & Saturday) for next 4 weeks
function getUpcomingDeliveryDays($weeks = 4) {
    $deliveryDays = [];
    $today = new DateTime();
    $today->setTimezone(new DateTimeZone('America/New_York'));
    
    for ($week = 0; $week < $weeks; $week++) {
        // Find Wednesday of the week
        $wednesday = clone $today;
        $wednesday->modify("+" . $week . " weeks");
        $wednesday->modify("wednesday this week");
        
        // Find Saturday of the week
        $saturday = clone $today;
        $saturday->modify("+" . $week . " weeks");
        $saturday->modify("saturday this week");
        
        // Add only future dates
        if ($wednesday >= $today) {
            $deliveryDays[] = [
                'date' => $wednesday->format('Y-m-d'),
                'display' => 'Wednesday ' . $wednesday->format('m/d/Y')
            ];
        }
        
        if ($saturday >= $today) {
            $deliveryDays[] = [
                'date' => $saturday->format('Y-m-d'),
                'display' => 'Saturday ' . $saturday->format('m/d/Y')
            ];
        }
    }
    
    return $deliveryDays;
}

// Check if selected date is valid delivery date
function isValidDeliveryDate($date) {
    $dayOfWeek = date('N', strtotime($date)); // 1=Monday, 3=Wednesday, 6=Saturday
    return in_array($dayOfWeek, [3, 6]); // Only Wednesday(3) and Saturday(6)
}

// Get filter parameters
$available_delivery_days = getUpcomingDeliveryDays();
$selected_date = $_GET['date'] ?? ($available_delivery_days[0]['date'] ?? date('Y-m-d'));
$status_filter = $_GET['status'] ?? 'all';
$export_type = $_GET['export'] ?? '';

// Check if it's Wednesday or Saturday
if (!isValidDeliveryDate($selected_date)) {
    $selected_date = $available_delivery_days[0]['date'] ?? date('Y-m-d');
}

// Kitchen data arrays
$delivery_orders = [];
$meal_summary = [];
$total_customers = 0;
$total_meals = 0;
$error_message = '';

try {
    // STEP 1: Get all customers with deliveries on selected date
    $customer_query = "
        SELECT 
            s.id as subscription_id,
            s.user_id,
            s.preferred_delivery_time,
            s.special_instructions as subscription_notes,
            u.first_name,
            u.last_name,
            u.phone,
            u.delivery_address,
            u.city,
            u.dietary_preferences,
            u.allergies,
            u.spice_level,
            sp.name as plan_name,
            sp.meals_per_week,
            COUNT(sm.id) as total_meals_today
        FROM subscriptions s
        JOIN users u ON s.user_id = u.id
        JOIN subscription_plans sp ON s.plan_id = sp.id
        JOIN subscription_menus sm ON s.id = sm.subscription_id
        WHERE sm.delivery_date = ?
        AND s.status = 'active'
        AND sm.status = 'scheduled'
        GROUP BY s.id, s.user_id, u.first_name, u.last_name, u.phone, 
                 u.delivery_address, u.city, u.dietary_preferences, u.allergies, 
                 u.spice_level, sp.name, sp.meals_per_week,
                 s.preferred_delivery_time, s.special_instructions
        ORDER BY s.preferred_delivery_time ASC, u.last_name ASC
    ";
    
    $stmt = $pdo->prepare($customer_query);
    $stmt->execute([$selected_date]);
    $customers_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_customers = count($customers_data);
    
    // STEP 2: Get menu items for each customer
    foreach ($customers_data as $customer_data) {
        $customer = $customer_data;
        $customer['meals'] = [];
        
        // Query meals for this customer
        $meals_query = "
            SELECT 
                sm.id as subscription_menu_id,
                sm.quantity,
                sm.customizations,
                sm.special_requests,
                m.id as menu_id,
                m.name as menu_name,
                m.base_price,
                m.ingredients,
                m.cooking_method,
                m.preparation_time,
                m.spice_level as menu_spice_level,
                mc.name as category_name
            FROM subscription_menus sm
            JOIN menus m ON sm.menu_id = m.id
            LEFT JOIN menu_categories mc ON m.category_id = mc.id
            WHERE sm.delivery_date = ?
            AND sm.subscription_id = ?
            AND sm.status = 'scheduled'
            ORDER BY mc.sort_order ASC, m.name ASC
        ";
        
        $meals_stmt = $pdo->prepare($meals_query);
        $meals_stmt->execute([$selected_date, $customer_data['subscription_id']]);
        $meals = $meals_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($meals as $meal) {
            $customer['meals'][] = $meal;
            $total_meals += $meal['quantity'];
            
            // Create meal summary for kitchen
            $meal_key = $meal['menu_name'];
            if (!isset($meal_summary[$meal_key])) {
                $meal_summary[$meal_key] = [
                    'menu_id' => $meal['menu_id'],
                    'name' => $meal['menu_name'],
                    'category' => $meal['category_name'],
                    'total_quantity' => 0,
                    'prep_time' => $meal['preparation_time'],
                    'spice_level' => $meal['menu_spice_level'],
                    'base_price' => $meal['base_price'],
                    'customizations' => [],
                    'special_requests' => [],
                    'ingredients' => $meal['ingredients'],
                    'cooking_method' => $meal['cooking_method'],
                    'customers' => []
                ];
            }
            
            $meal_summary[$meal_key]['total_quantity'] += $meal['quantity'];
            $meal_summary[$meal_key]['customers'][] = $customer['first_name'] . ' ' . $customer['last_name'];
            
            // Collect customizations
            if (!empty($meal['customizations'])) {
                $customs = json_decode($meal['customizations'], true);
                if ($customs) {
                    foreach ($customs as $custom) {
                        if (!in_array($custom, $meal_summary[$meal_key]['customizations'])) {
                            $meal_summary[$meal_key]['customizations'][] = $custom;
                        }
                    }
                }
            }
            
            // Collect special requests
            if (!empty($meal['special_requests'])) {
                if (!in_array($meal['special_requests'], $meal_summary[$meal_key]['special_requests'])) {
                    $meal_summary[$meal_key]['special_requests'][] = $meal['special_requests'];
                }
            }
        }
        
        // Add customer to final array (only if they have meals)
        if (!empty($customer['meals'])) {
            $delivery_orders[] = $customer;
        }
    }
    
    // Sort meal summary by total quantity (highest first)
    uasort($meal_summary, function($a, $b) {
        return $b['total_quantity'] - $a['total_quantity'];
    });
    
} catch (Exception $e) {
    error_log("Kitchen Dashboard Error: " . $e->getMessage());
    $error_message = "Error loading kitchen data: " . $e->getMessage();
}

// Handle CSV Export
if ($export_type === 'csv') {
    // Clean any existing output
    ob_end_clean();
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="kitchen_prep_' . $selected_date . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // UTF-8 BOM for proper Excel display
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers
    fputcsv($output, [
        'Delivery Date', 'Customer Name', 'Phone', 'Address', 
        'Delivery Time', 'Plan', 'Menu', 'Quantity', 'Customizations',
        'Special Requests', 'Dietary Preferences', 'Allergies', 'Spice Level'
    ]);
    
    // Data rows
    foreach ($delivery_orders as $customer) {
        foreach ($customer['meals'] as $meal) {
            fputcsv($output, [
                $selected_date,
                $customer['first_name'] . ' ' . $customer['last_name'],
                $customer['phone'] ?? '',
                ($customer['delivery_address'] ?? '') . ', ' . ($customer['city'] ?? ''),
                $customer['preferred_delivery_time'] ?? '3:00 PM - 6:00 PM',
                $customer['plan_name'] ?? 'Standard Plan',
                $meal['menu_name'],
                $meal['quantity'],
                $meal['customizations'] ?? '',
                $meal['special_requests'] ?? '',
                $customer['dietary_preferences'] ?? '',
                $customer['allergies'] ?? '',
                $customer['spice_level'] ?? ''
            ]);
        }
    }
    
    fclose($output);
    exit;
}

// Handle JSON Export
if ($export_type === 'json') {
    // Clean any existing output
    ob_end_clean();
    
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="kitchen_data_' . $selected_date . '.json"');
    
    $export_data = [
        'delivery_date' => $selected_date,
        'total_customers' => $total_customers,
        'total_meals' => $total_meals,
        'customers' => $delivery_orders,
        'meal_summary' => $meal_summary,
        'exported_at' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kitchen Dashboard - Somdul Table</title>
    <link href="https://ydpschool.com/fonts/BaticaSans.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            /* Somdul Table Brand Colors */
            --brown: #bd9379;
            --cream: #ece8e1;
            --sage: #adb89d;
            --curry: #cf723a;
            --white: #ffffff;
            --text-dark: #2c3e50;
            --text-gray: #6c757d;
            --border-light: #e9ecef;
            --shadow-soft: 0 2px 10px rgba(0,0,0,0.05);
            --shadow-medium: 0 4px 20px rgba(0,0,0,0.1);
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 14px;
            --transition: all 0.3s ease;
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
            font-family: 'BaticaSans', 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--cream) 0%, #f1f3f4 100%);
            min-height: 100vh;
            color: var(--text-dark);
            line-height: 1.6;
        }

        .header {
            background: linear-gradient(135deg, var(--curry) 0%, var(--brown) 100%);
            color: white;
            padding: 1.5rem 2rem;
            box-shadow: var(--shadow-medium);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .header-title {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-title h1 {
            font-size: 1.8rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .header-meta {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.15);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            backdrop-filter: blur(10px);
            font-size: 0.9rem;
        }

        .btn-back {
            background: rgba(255, 255, 255, 0.15);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 0.6rem 1.2rem;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
            backdrop-filter: blur(10px);
        }

        .btn-back:hover {
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-1px);
            color: white;
        }

        /* Print & Export Dropdown Styles */
        .print-export-dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-trigger {
            background: rgba(255, 255, 255, 0.15);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 0.6rem 1.2rem;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
            backdrop-filter: blur(10px);
        }

        .dropdown-trigger:hover {
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-1px);
        }

        .dropdown-trigger.active {
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.5);
        }

        .dropdown-arrow {
            transition: transform 0.3s ease;
        }

        .dropdown-trigger.active .dropdown-arrow {
            transform: rotate(180deg);
        }

        .dropdown-panel {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-medium);
            min-width: 220px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1000;
            margin-top: 0.5rem;
        }

        .dropdown-panel.active {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-header {
            padding: 1rem;
            border-bottom: 1px solid var(--border-light);
            background: linear-gradient(135deg, var(--cream), #f8f6f3);
            border-radius: var(--radius-md) var(--radius-md) 0 0;
        }

        .dropdown-title {
            font-weight: 600;
            color: var(--curry);
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .dropdown-options {
            padding: 0.5rem 0;
        }

        .dropdown-option {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: var(--text-dark);
            text-decoration: none;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            font-family: inherit;
            font-size: 0.9rem;
        }

        .dropdown-option:hover {
            background: var(--cream);
            color: var(--curry);
        }

        .dropdown-option i {
            width: 18px;
            color: var(--curry);
        }

        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .dashboard-controls {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            margin-bottom: 2rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            align-items: end;
        }

        .control-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .control-label {
            font-weight: 500;
            color: var(--curry);
            font-size: 0.9rem;
        }

        .control-input {
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-sm);
            font-size: 1rem;
            transition: var(--transition);
            font-family: inherit;
        }

        .control-input:focus {
            outline: none;
            border-color: var(--curry);
            box-shadow: 0 0 0 3px rgba(207, 114, 58, 0.1);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            justify-content: center;
            font-family: inherit;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--curry), var(--brown));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--sage), #27ae60);
            color: white;
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .btn-outline {
            background: white;
            border: 2px solid var(--cream);
            color: var(--curry);
        }

        .btn-outline:hover {
            background: var(--cream);
            border-color: var(--curry);
        }

        .stats-grid {
            display: flex;
            gap: 2rem;
            margin-bottom: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .stat-card {
            background: transparent;
            padding: 0.75rem 1rem;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-light);
            text-align: center;
            position: relative;
            overflow: hidden;
            transition: var(--transition);
            min-width: 120px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            border-color: var(--curry);
            background: rgba(255, 255, 255, 0.5);
        }

        .stat-top {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.25rem;
        }

        .stat-icon {
            font-size: 1.2rem;
            color: var(--curry);
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--curry);
        }

        .stat-label {
            font-size: 0.8rem;
            color: var(--text-gray);
            font-weight: 500;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .main-content {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            overflow: hidden;
        }

        .sidebar {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            padding: 2rem;
            max-height: 800px;
            overflow-y: auto;
        }

        .section-header {
            background: linear-gradient(135deg, var(--cream), #f8f6f3);
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--curry);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .customer-list {
            max-height: 600px;
            overflow-y: auto;
        }

        .customer-card {
            border-bottom: 1px solid #f3f4f6;
            transition: var(--transition);
            cursor: pointer;
        }

        .customer-card:hover {
            background: var(--cream);
        }

        .customer-card-header {
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, var(--cream) 0%, #f8f6f3 100%);
            border-left: 4px solid var(--curry);
            user-select: none;
        }

        .customer-card-header:hover {
            background: linear-gradient(135deg, #e6ddd4 0%, #f0ede8 100%);
        }

        .customer-card-content {
            padding: 1.5rem 2rem;
            display: none;
            background: white;
        }

        .customer-card.expanded .customer-card-content {
            display: block;
        }

        .customer-card-toggle {
            color: var(--curry);
            font-size: 1.2rem;
            transition: transform 0.3s ease;
        }

        .customer-card.expanded .customer-card-toggle {
            transform: rotate(180deg);
        }

        .customer-name-compact {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--curry);
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .customer-meal-count {
            background: var(--curry);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .customer-card-header .delivery-time {
            background: linear-gradient(135deg, var(--sage), #27ae60);
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 16px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .customer-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .customer-info h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--curry);
            margin-bottom: 0.25rem;
        }

        .customer-details {
            font-size: 0.9rem;
            color: var(--text-gray);
            line-height: 1.4;
        }

        .customer-details div {
            margin-bottom: 0.25rem;
        }

        .delivery-time {
            background: linear-gradient(135deg, var(--sage), #27ae60);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .meals-list {
            margin-top: 1rem;
        }

        .meal-item {
            background: var(--cream);
            padding: 1rem;
            border-radius: var(--radius-sm);
            margin-bottom: 0.5rem;
            border-left: 4px solid var(--curry);
        }

        .meal-item:last-child {
            margin-bottom: 0;
        }

        .meal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .meal-name {
            font-weight: 600;
            color: var(--curry);
        }

        .meal-quantity {
            background: var(--curry);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .meal-meta {
            font-size: 0.85rem;
            color: var(--text-gray);
            line-height: 1.4;
        }

        .meal-meta div {
            margin-bottom: 0.25rem;
        }

        .prep-summary-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--curry);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .prep-item {
            border: 1px solid var(--border-light);
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
            transition: var(--transition);
            overflow: hidden;
        }

        .prep-item:hover {
            box-shadow: var(--shadow-soft);
            transform: translateY(-2px);
        }

        .prep-item-header {
            background: linear-gradient(135deg, var(--cream), white);
            padding: 1rem 1.5rem;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 4px solid var(--curry);
            user-select: none;
        }

        .prep-item-header:hover {
            background: linear-gradient(135deg, #e6ddd4, #f8f6f3);
        }

        .prep-item-content {
            padding: 1.5rem;
            display: none;
            background: white;
        }

        .prep-item.expanded .prep-item-content {
            display: block;
        }

        .prep-item-toggle {
            color: var(--curry);
            font-size: 1.1rem;
            transition: transform 0.3s ease;
        }

        .prep-item.expanded .prep-item-toggle {
            transform: rotate(180deg);
        }

        .prep-name-compact {
            font-weight: 600;
            color: var(--curry);
            font-size: 1.1rem;
        }

        .prep-meta {
            font-size: 0.9rem;
            color: var(--text-gray);
            line-height: 1.5;
        }

        .prep-meta div {
            margin-bottom: 0.25rem;
        }

        .prep-customers {
            margin-top: 0.5rem;
            font-weight: 500;
            color: var(--sage);
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--text-gray);
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--cream);
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--curry);
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--radius-sm);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-error {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .delivery-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, var(--sage), #27ae60);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* Loading state */
        .loading {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .loading::after {
            content: '';
            width: 16px;
            height: 16px;
            border: 2px solid transparent;
            border-top: 2px solid currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @media print {
            body * {
                visibility: hidden;
            }
            .print-area, .print-area * {
                visibility: visible;
            }
            .print-area {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .no-print {
                display: none !important;
            }
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .dashboard-controls {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 1rem;
                padding: 1.5rem;
            }
        }

        @media (max-width: 768px) {
            .main-container {
                padding: 1rem;
            }
            
            .header {
                padding: 1rem;
            }
            
            .header-title h1 {
                font-size: 1.5rem;
            }
            
            .stats-grid {
                gap: 1rem;
                margin-bottom: 1rem;
            }
            
            .stat-card {
                min-width: 100px;
                padding: 0.5rem 0.75rem;
            }
            
            .dropdown-panel {
                left: -50px;
                right: auto;
                min-width: 200px;
            }
        }

/* Full Calendar Grid Styles */
.calendar-dropdown {
    position: relative;
    width: 100%;
}

.calendar-trigger {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid var(--border-light);
    border-radius: var(--radius-sm);
    background: white;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: var(--transition);
    font-family: inherit;
    font-size: 1rem;
}

.calendar-trigger:hover,
.calendar-trigger.active {
    border-color: var(--curry);
    box-shadow: 0 0 0 3px rgba(207, 114, 58, 0.1);
}

.calendar-trigger-content {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.calendar-trigger-icon {
    color: var(--curry);
    font-size: 1.1rem;
}

.calendar-trigger-text {
    font-weight: 500;
    color: var(--text-dark);
}

.calendar-trigger-arrow {
    color: var(--text-gray);
    transition: transform 0.3s ease;
}

.calendar-trigger.active .calendar-trigger-arrow {
    transform: rotate(180deg);
}

.calendar-dropdown-panel {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 2px solid var(--curry);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-medium);
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.3s ease;
    min-width: 320px;
}

.calendar-dropdown-panel.active {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: linear-gradient(135deg, var(--cream), #f8f6f3);
    border-bottom: 1px solid var(--border-light);
    border-radius: var(--radius-md) var(--radius-md) 0 0;
}

.calendar-nav-btn {
    background: none;
    border: none;
    color: var(--curry);
    font-size: 1.2rem;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 50%;
    transition: var(--transition);
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
}

.calendar-nav-btn:hover {
    background: var(--curry);
    color: white;
    transform: scale(1.1);
}

.calendar-title {
    font-weight: 600;
    color: var(--curry);
    font-size: 1.1rem;
    min-width: 140px;
    text-align: center;
}

.calendar-grid {
    padding: 1rem;
}

.calendar-days-header {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 0.25rem;
    margin-bottom: 0.5rem;
}

.calendar-day-header {
    text-align: center;
    font-weight: 600;
    color: var(--text-gray);
    font-size: 0.8rem;
    padding: 0.5rem;
}

.calendar-days {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 0.25rem;
}

.calendar-day {
    aspect-ratio: 1;
    border: 1px solid transparent;
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: var(--transition);
    position: relative;
    min-height: 40px;
    font-size: 0.9rem;
    font-weight: 500;
    background: #f8f9fa;
}

.calendar-day.other-month {
    color: #ccc;
    cursor: not-allowed;
    background: transparent;
}

.calendar-day.current-month {
    color: var(--text-dark);
    background: white;
    border-color: var(--border-light);
}

.calendar-day.delivery-day {
    background: linear-gradient(135deg, var(--sage), #27ae60);
    color: white;
    border-color: var(--sage);
    font-weight: 600;
    cursor: pointer;
}

.calendar-day.delivery-day:hover {
    background: linear-gradient(135deg, #27ae60, var(--sage));
    transform: scale(1.05);
    box-shadow: var(--shadow-soft);
}

.calendar-day.selected {
    background: linear-gradient(135deg, var(--curry), var(--brown));
    color: white;
    border-color: var(--curry);
    transform: scale(1.05);
    box-shadow: var(--shadow-soft);
}

.calendar-day.today {
    border: 2px solid var(--curry);
    font-weight: 700;
}

.calendar-day.disabled {
    color: #ddd;
    cursor: not-allowed;
    background: #f8f9fa;
}

.calendar-footer {
    padding: 1rem;
    border-top: 1px solid var(--border-light);
    background: var(--cream);
    border-radius: 0 0 var(--radius-md) var(--radius-md);
}

.calendar-legend {
    display: flex;
    gap: 1rem;
    font-size: 0.8rem;
    justify-content: center;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    color: var(--text-gray);
}

.legend-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
}

.delivery-dot {
    background: linear-gradient(135deg, var(--sage), #27ae60);
}

.selected-dot {
    background: linear-gradient(135deg, var(--curry), var(--brown));
}

@media (max-width: 768px) {
    .calendar-dropdown-panel {
        left: -1rem;
        right: -1rem;
        min-width: auto;
    }
    
    .calendar-grid {
        padding: 0.75rem;
    }
    
    .calendar-day {
        min-height: 36px;
        font-size: 0.8rem;
    }
    
    .calendar-legend {
        flex-direction: column;
        gap: 0.5rem;
        align-items: center;
    }
}

    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <div class="header-title">
               <h1>
                    <i class="fas fa-utensils"></i>
                    Kitchen Dashboard
                </h1>
             <div class="delivery-indicator">
                    <i class="fas fa-calendar-alt"></i>
                    Wed & Sat Delivery
                </div>
            </div>
            <div class="header-meta">
                <div class="meta-item">
                    <a href="manage_order.php" class="btn-back">
                    <i class="fas fa-user-circle"></i>
                    <span>Mark accept orders</span>
                    </a>
                </div>
                <div class="meta-item">
                    <a href="../admin/dashboard.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back to Dashboard</span>
                    </a>
                </div>
                <!-- Print & Export Dropdown -->
                <div class="print-export-dropdown">
                    <div class="dropdown-trigger" onclick="togglePrintDropdown()">
                        <i class="fas fa-print"></i>
                        <span>Print & Export</span>
                        <i class="fas fa-chevron-down dropdown-arrow"></i>
                    </div>
                    
                    <div class="dropdown-panel" id="printDropdownPanel">
                        <div class="dropdown-header">
                            <div class="dropdown-title">
                                <i class="fas fa-download"></i>
                                Export Options
                            </div>
                        </div>
                        <div class="dropdown-options">
                            <button class="dropdown-option" onclick="printPrepSheet()">
                                <i class="fas fa-clipboard-list"></i>
                                <span>Print Prep Sheet</span>
                            </button>
                            
                            <button class="dropdown-option" onclick="printDeliverySheet()">
                                <i class="fas fa-truck"></i>
                                <span>Print Delivery Sheet</span>
                            </button>
                            
                            <button class="dropdown-option" onclick="printIngredientsList()">
                                <i class="fas fa-carrot"></i>
                                <span>Print Ingredients List</span>
                            </button>
                            
                            <a href="?date=<?php echo $selected_date; ?>&export=csv" class="dropdown-option">
                                <i class="fas fa-file-csv"></i>
                                <span>Export </span>
                            </a>
                            
                            <a href="?date=<?php echo $selected_date; ?>&export=json" class="dropdown-option">
                                <i class="fas fa-file-code"></i>
                                <span>Export JSON</span>
                            </a>
                            
                            <button class="dropdown-option" onclick="exportToExcel()">
                                <i class="fas fa-file-excel"></i>
                                <span>Export Excel</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="main-container">

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

  
<!-- Dashboard Controls Section -->
<div class="dashboard-controls">
   <div class="control-group">
        <label class="control-label">Select Delivery Date (Wed & Sat Only)</label>
        
        <!-- Full Calendar Grid -->
        <div class="calendar-dropdown">
            <div class="calendar-trigger" onclick="toggleCalendar()">
                <div class="calendar-trigger-content">
                    <i class="fas fa-calendar-alt calendar-trigger-icon"></i>
                    <span class="calendar-trigger-text" id="selectedDateText">
                        <?php echo !empty($selected_date) ? date('D, M j, Y', strtotime($selected_date)) : 'Select Date'; ?>
                    </span>
                </div>
                <i class="fas fa-chevron-down calendar-trigger-arrow"></i>
            </div>
            
            <div class="calendar-dropdown-panel" id="calendarPanel">
                <!-- Calendar Header with Navigation -->
                <div class="calendar-header">
                    <button type="button" class="calendar-nav-btn" onclick="changeMonth(-1)">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <div class="calendar-title" id="calendarTitle">
                        January 2025
                    </div>
                    <button type="button" class="calendar-nav-btn" onclick="changeMonth(1)">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                
                <!-- Days of Week Header -->
                <div class="calendar-grid">
                    <div class="calendar-days-header">
                        <div class="calendar-day-header">S</div>
                        <div class="calendar-day-header">M</div>
                        <div class="calendar-day-header">T</div>
                        <div class="calendar-day-header">W</div>
                        <div class="calendar-day-header">T</div>
                        <div class="calendar-day-header">F</div>
                        <div class="calendar-day-header">S</div>
                    </div>
                    
                    <!-- Calendar Days Grid -->
                    <div class="calendar-days" id="calendarDays">
                        <!-- JavaScript will generate calendar days here -->
                    </div>
                </div>
                
                <!-- Legend -->
                <div class="calendar-footer">
                    <div class="calendar-legend">
                        <div class="legend-item">
                            <span class="legend-dot delivery-dot"></span>
                            <span>Delivery Days (Wed & Sat)</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-dot selected-dot"></span>
                            <span>Selected Date</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="control-group">
        <label class="control-label">Status Filter</label>
        <select class="control-input" onchange="filterByStatus(this.value)">
            <option value="all" <?php echo ($status_filter === 'all') ? 'selected' : ''; ?>>All Orders</option>
            <option value="scheduled" <?php echo ($status_filter === 'scheduled') ? 'selected' : ''; ?>>Scheduled</option>
            <option value="in_progress" <?php echo ($status_filter === 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
            <option value="completed" <?php echo ($status_filter === 'completed') ? 'selected' : ''; ?>>Completed</option>
        </select>
    </div>
   
    <div class="control-group">
        <label class="control-label">Quick Actions</label>
        <button class="btn btn-primary" onclick="refreshData()">
            <i class="fas fa-sync-alt"></i> Refresh
        </button>
    </div>
</div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-number"><?php echo $total_customers; ?></div>
                </div>
                <div class="stat-label">Customers</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon">
                        <i class="fas fa-bowl-food"></i>
                    </div>
                    <div class="stat-number"><?php echo $total_meals; ?></div>
                </div>
                <div class="stat-label">Meals</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-number"><?php echo count($meal_summary); ?></div>
                </div>
                <div class="stat-label">Menu Items</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-weekend"></i>
                    </div>
                    <div class="stat-number"><?php echo date('N', strtotime($selected_date)) == 3 ? 'WED' : 'SAT'; ?></div>
                </div>
                <div class="stat-label">Delivery Day</div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="content-grid">
            <!-- Customer Orders -->
            <div class="main-content">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-truck-fast"></i>
                        Delivery Schedule - <?php echo !empty($selected_date) ? date('l, M j', strtotime($selected_date)) : 'Date not selected'; ?>
                    </h2>
                    <div style="display: flex; gap: 1rem;">
                        <button class="btn btn-outline" onclick="toggleAllCustomerCards()" style="font-size: 0.85rem; padding: 0.5rem 1rem;">
                            <i class="fas fa-expand-arrows-alt"></i> Toggle All
                        </button>
                        <button class="btn btn-primary" onclick="markAllCompleted()">
                            <i class="fas fa-check-circle"></i> Mark All Complete
                        </button>
                    </div>
                </div>
                
                <div class="customer-list">
                    <?php if (empty($delivery_orders)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                           <h3>No Deliveries Scheduled</h3>
                            <p>No meal deliveries are scheduled for the selected date</p>
                            <small>Try selecting a different date or check if there are active subscriptions</small>
                        </div>
                    <?php else: ?>
                        <?php foreach ($delivery_orders as $index => $customer): ?>
                            <div class="customer-card" id="customer-<?php echo $customer['user_id']; ?>">
                                <div class="customer-card-header" onclick="toggleCustomerCard('<?php echo $customer['user_id']; ?>')">
                                    <div class="customer-name-compact">
                                        <i class="fas fa-user"></i>
                                        <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>
                                        <span class="customer-meal-count"><?php echo count($customer['meals']); ?> meals</span>
                                        <span class="delivery-time">
                                            <i class="fas fa-clock"></i>
                                            <?php echo htmlspecialchars($customer['preferred_delivery_time'] ?? '3:00 PM - 6:00 PM'); ?>
                                        </span>
                                    </div>
                                    <i class="fas fa-chevron-down customer-card-toggle"></i>
                                </div>
                                
                                <div class="customer-card-content">
                                    <div class="customer-header">
                                        <div class="customer-info">
                                            <h3><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></h3>
                                            <div class="customer-details">
                                                <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($customer['phone'] ?? 'No phone provided'); ?></div>
                                                <div><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars(($customer['delivery_address'] ?? 'No address') . ', ' . ($customer['city'] ?? '')); ?></div>
                                                <div><i class="fas fa-tag"></i> Plan: <?php echo htmlspecialchars($customer['plan_name'] ?? 'Standard Plan'); ?></div>
                                                <div><i class="fas fa-utensils"></i> Total Meals Today: <?php echo count($customer['meals']); ?></div>
                                                <?php if (!empty($customer['dietary_preferences'])): ?>
                                                    <div><i class="fas fa-leaf"></i> Diet: <?php echo htmlspecialchars($customer['dietary_preferences']); ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($customer['allergies'])): ?>
                                                    <div><i class="fas fa-exclamation-triangle"></i> Allergies: <?php echo htmlspecialchars($customer['allergies']); ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($customer['subscription_notes'])): ?>
                                                    <div><i class="fas fa-sticky-note"></i> Notes: <?php echo htmlspecialchars($customer['subscription_notes']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="delivery-time">
                                            <i class="fas fa-clock"></i>
                                            <?php echo htmlspecialchars($customer['preferred_delivery_time'] ?? '3:00 PM - 6:00 PM'); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="meals-list">
                                        <?php foreach ($customer['meals'] as $meal): ?>
                                            <div class="meal-item">
                                                <div class="meal-header">
                                                    <div class="meal-name"><?php echo htmlspecialchars($meal['menu_name']); ?></div>
                                                    <div class="meal-quantity">x<?php echo $meal['quantity']; ?></div>
                                                </div>
                                                <div class="meal-meta">
                                                    <?php if (!empty($meal['category_name'])): ?>
                                                        <div><strong>Category:</strong> <?php echo htmlspecialchars($meal['category_name']); ?></div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($meal['preparation_time'])): ?>
                                                        <div><strong>Prep Time:</strong> <?php echo $meal['preparation_time']; ?> minutes</div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($meal['menu_spice_level'])): ?>
                                                        <div><strong>Spice Level:</strong> <?php echo ucfirst($meal['menu_spice_level']); ?></div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($meal['customizations'])): ?>
                                                        <div><strong>Customizations:</strong> <?php echo htmlspecialchars($meal['customizations']); ?></div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($meal['special_requests'])): ?>
                                                        <div><strong>Special Requests:</strong> <?php echo htmlspecialchars($meal['special_requests']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Meal Preparation Summary -->
            <div class="sidebar">
                <div class="prep-summary-title">
                    <i class="fas fa-clipboard-list"></i>
                    Kitchen Prep Summary
                    <button class="btn btn-outline" onclick="toggleAllPrepItems()" style="font-size: 0.8rem; padding: 0.4rem 0.8rem; margin-left: auto;">
                        <i class="fas fa-expand-arrows-alt"></i> Toggle All
                    </button>
                </div>
                
                <?php if (empty($meal_summary)): ?>
                    <div class="empty-state">
                        <i class="fas fa-utensils"></i>
                        <h3>No Meals to Prepare</h3>
                        <p>No meal preparations required for this date</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($meal_summary as $meal): ?>
                        <div class="prep-item" id="prep-<?php echo $meal['menu_id']; ?>">
                            <div class="prep-item-header" onclick="togglePrepItem('<?php echo $meal['menu_id']; ?>')">
                                <div class="prep-name-compact"><?php echo htmlspecialchars($meal['name']); ?></div>
                                <div style="display: flex; align-items: center; gap: 1rem;">
                                    <div class="prep-quantity"><?php echo $meal['total_quantity']; ?> portions</div>
                                    <i class="fas fa-chevron-down prep-item-toggle"></i>
                                </div>
                            </div>
                            <div class="prep-item-content">
                                <div class="prep-meta">
                                    <?php if (!empty($meal['category'])): ?>
                                        <div><strong>Category:</strong> <?php echo htmlspecialchars($meal['category']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($meal['prep_time'])): ?>
                                        <div><strong>Prep Time:</strong> <?php echo $meal['prep_time']; ?> min/portion</div>
                                        <div><strong>Total Time:</strong> <?php echo ($meal['prep_time'] * $meal['total_quantity']); ?> minutes</div>
                                    <?php endif; ?>
                                    <?php if (!empty($meal['spice_level'])): ?>
                                        <div><strong>Spice Level:</strong> <?php echo ucfirst($meal['spice_level']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($meal['cooking_method'])): ?>
                                        <div><strong>Cooking Method:</strong> <?php echo htmlspecialchars($meal['cooking_method']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($meal['ingredients'])): ?>
                                        <div><strong>Key Ingredients:</strong> <?php echo htmlspecialchars($meal['ingredients']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($meal['customizations'])): ?>
                                        <div><strong>Customizations:</strong> <?php echo implode(', ', $meal['customizations']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($meal['special_requests'])): ?>
                                        <div><strong>Special Requests:</strong> <?php echo implode(', ', $meal['special_requests']); ?></div>
                                    <?php endif; ?>
                                    <div class="prep-customers">
                                        <strong>For Customers:</strong> <?php echo implode(', ', array_unique($meal['customers'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Print Areas (Hidden) -->
    <div id="prep-print-area" class="print-area" style="display: none;">
        <div style="text-align: center; margin-bottom: 2rem;">
            <h1 style="color: #cf723a; margin-bottom: 0.5rem;">Somdul Table - Kitchen Prep Sheet</h1>
            <h2 style="color: #6c757d;">Delivery Date: <?php echo !empty($selected_date) ? date('l, F j, Y', strtotime($selected_date)) : 'Date not selected'; ?></h2>
            <p style="color: #666;">Printed: <?php echo date('m/d/Y H:i:s'); ?> (Eastern Time)</p>
        </div>
        
        <div style="margin-bottom: 2rem;">
            <h3 style="background: #ece8e1; padding: 1rem; border-radius: 8px; color: #cf723a;">
                Prep Summary - Total <?php echo count($meal_summary); ?> items / <?php echo $total_meals; ?> portions
            </h3>
        </div>
        
        <?php foreach ($meal_summary as $meal): ?>
            <div style="border: 1px solid #ddd; margin-bottom: 1rem; border-radius: 8px; overflow: hidden;">
                <div style="background: #cf723a; color: white; padding: 1rem; font-weight: bold; font-size: 1.2rem;">
                    <?php echo htmlspecialchars($meal['name']); ?> - <?php echo $meal['total_quantity']; ?> portions
                </div>
                <div style="padding: 1rem;">
                    <p><strong>Category:</strong> <?php echo htmlspecialchars($meal['category'] ?? 'Not specified'); ?></p>
                    <p><strong>Prep Time:</strong> <?php echo $meal['prep_time'] ?? 'Not specified'; ?> min/portion</p>
                    <p><strong>Total Time:</strong> <?php echo ($meal['prep_time'] ?? 0) * $meal['total_quantity']; ?> minutes</p>
                    <p><strong>Cooking Method:</strong> <?php echo htmlspecialchars($meal['cooking_method'] ?? 'Standard recipe'); ?></p>
                    <?php if (!empty($meal['ingredients'])): ?>
                        <p><strong>Ingredients:</strong> <?php echo htmlspecialchars($meal['ingredients']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($meal['customizations'])): ?>
                        <p><strong>Customizations:</strong> <?php echo implode(', ', $meal['customizations']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($meal['special_requests'])): ?>
                        <p><strong>Special Requests:</strong> <?php echo implode(', ', $meal['special_requests']); ?></p>
                    <?php endif; ?>
                    <p><strong>Customers:</strong> <?php echo implode(', ', array_unique($meal['customers'])); ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <script>
        // Print & Export Dropdown Functions
        let printDropdownOpen = false;

        function togglePrintDropdown() {
            const trigger = document.querySelector('.dropdown-trigger');
            const panel = document.getElementById('printDropdownPanel');
            
            printDropdownOpen = !printDropdownOpen;
            
            if (printDropdownOpen) {
                trigger.classList.add('active');
                panel.classList.add('active');
            } else {
                trigger.classList.remove('active');
                panel.classList.remove('active');
            }
        }

        // Customer Card Toggle Functions
        function toggleCustomerCard(customerId) {
            console.log('Toggling customer card:', customerId); // Debug log
            const card = document.getElementById(`customer-${customerId}`);
            if (card) {
                card.classList.toggle('expanded');
                console.log('Card expanded:', card.classList.contains('expanded')); // Debug log
            } else {
                console.error('Customer card not found:', `customer-${customerId}`);
            }
        }

        // Prep Item Toggle Functions
        function togglePrepItem(menuId) {
            console.log('Toggling prep item:', menuId); // Debug log
            const item = document.getElementById(`prep-${menuId}`);
            if (item) {
                item.classList.toggle('expanded');
                console.log('Item expanded:', item.classList.contains('expanded')); // Debug log
            } else {
                console.error('Prep item not found:', `prep-${menuId}`);
            }
        }

        // Toggle All Customer Cards
        function toggleAllCustomerCards() {
            const cards = document.querySelectorAll('.customer-card');
            const expandedCards = document.querySelectorAll('.customer-card.expanded');
            
            if (expandedCards.length === cards.length) {
                // All are expanded, collapse all
                cards.forEach(card => card.classList.remove('expanded'));
            } else {
                // Some are collapsed, expand all
                cards.forEach(card => card.classList.add('expanded'));
            }
        }

        // Toggle All Prep Items
        function toggleAllPrepItems() {
            const items = document.querySelectorAll('.prep-item');
            const expandedItems = document.querySelectorAll('.prep-item.expanded');
            
            if (expandedItems.length === items.length) {
                // All are expanded, collapse all
                items.forEach(item => item.classList.remove('expanded'));
            } else {
                // Some are collapsed, expand all
                items.forEach(item => item.classList.add('expanded'));
            }
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.querySelector('.print-export-dropdown');
            
            if (printDropdownOpen && !dropdown.contains(event.target)) {
                document.querySelector('.dropdown-trigger').classList.remove('active');
                document.getElementById('printDropdownPanel').classList.remove('active');
                printDropdownOpen = false;
            }
        });

        function filterByDate(date) {
            if (date) {
                window.location.href = `?date=${date}&status=<?php echo $status_filter; ?>`;
            }
        }

        function filterByStatus(status) {
            if (status) {
                window.location.href = `?date=<?php echo $selected_date; ?>&status=${status}`;
            }
        }

        function refreshData() {
            window.location.reload();
        }

        function printPrepSheet() {
            const mealSummary = <?php echo json_encode($meal_summary, JSON_UNESCAPED_UNICODE); ?>;
            
            let prepContent = `
                <div style="text-align: center; margin-bottom: 2rem;">
                    <h1 style="color: #cf723a; margin-bottom: 0.5rem;">Somdul Table - Kitchen Prep Sheet</h1>
                    <h2 style="color: #6c757d;">Delivery Date: <?php echo !empty($selected_date) ? date('l, F j, Y', strtotime($selected_date)) : 'Date not selected'; ?></h2>
                    <p style="color: #666;">Printed: ${new Date().toLocaleString('en-US')} (Eastern Time)</p>
                </div>
                
                <div style="margin-bottom: 2rem;">
                    <h3 style="background: #ece8e1; padding: 1rem; border-radius: 8px; color: #cf723a;">
                        Prep Summary - Total ${Object.keys(mealSummary).length} items / <?php echo $total_meals; ?> portions
                    </h3>
                </div>
                
                <div style="font-size: 1.2rem; line-height: 2;">
            `;
            
            let counter = 1;
            Object.values(mealSummary).forEach(meal => {
                prepContent += `
                    <div style="margin-bottom: 0.5rem; padding: 0.5rem; border-bottom: 1px solid #eee;">
                        ${counter}. ${meal.name} - ${meal.total_quantity} portions
                    </div>
                `;
                counter++;
            });
            
            prepContent += `</div>`;
            
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Kitchen Prep Sheet - <?php echo !empty($selected_date) ? date('m/d/Y', strtotime($selected_date)) : 'Date not selected'; ?></title>
                        <style>
                            body { 
                                font-family: 'BaticaSans', 'Inter', sans-serif; 
                                margin: 20px; 
                                font-size: 14px;
                            }
                            h1 { margin-bottom: 10px; }
                            h2 { margin-bottom: 20px; }
                            @media print {
                                body { margin: 0; }
                                div { page-break-inside: avoid; }
                            }
                        </style>
                    </head>
                    <body>${prepContent}</body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }

        function printDeliverySheet() {
            // Create delivery sheet content
            let deliveryContent = `
                <div style="text-align: center; margin-bottom: 2rem;">
                    <h1 style="color: #cf723a;">Somdul Table - Delivery Sheet</h1>
                    <h2>Delivery Date: <?php echo !empty($selected_date) ? date('l, F j, Y', strtotime($selected_date)) : 'Date not selected'; ?></h2>
                    <p>Total <?php echo $total_customers; ?> customers / <?php echo $total_meals; ?> meals</p>
                </div>
                <table style="width: 100%; border-collapse: collapse; margin-top: 1rem;">
                    <thead>
                        <tr style="background: #ece8e1;">
                            <th style="border: 1px solid #ddd; padding: 0.5rem;">No.</th>
                            <th style="border: 1px solid #ddd; padding: 0.5rem;">Customer</th>
                            <th style="border: 1px solid #ddd; padding: 0.5rem;">Phone</th>
                            <th style="border: 1px solid #ddd; padding: 0.5rem;">Address</th>
                            <th style="border: 1px solid #ddd; padding: 0.5rem;">Delivery Time</th>
                            <th style="border: 1px solid #ddd; padding: 0.5rem;">Menu Items</th>
                            <th style="border: 1px solid #ddd; padding: 0.5rem;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            // Build delivery sheet content dynamically
            const customers = <?php echo json_encode($delivery_orders, JSON_UNESCAPED_UNICODE); ?>;
            
            customers.forEach((customer, index) => {
                let menuItems = '';
                customer.meals.forEach(meal => {
                    menuItems += `${meal.menu_name} (${meal.quantity})<br>`;
                });
                
                deliveryContent += `
                    <tr>
                        <td style="border: 1px solid #ddd; padding: 0.5rem; text-align: center;">${index + 1}</td>
                        <td style="border: 1px solid #ddd; padding: 0.5rem;">${customer.first_name} ${customer.last_name}</td>
                        <td style="border: 1px solid #ddd; padding: 0.5rem;">${customer.phone || ''}</td>
                        <td style="border: 1px solid #ddd; padding: 0.5rem;">${(customer.delivery_address || '') + ', ' + (customer.city || '')}</td>
                        <td style="border: 1px solid #ddd; padding: 0.5rem;">${customer.preferred_delivery_time || '3:00 PM - 6:00 PM'}</td>
                        <td style="border: 1px solid #ddd; padding: 0.5rem;">${menuItems}</td>
                        <td style="border: 1px solid #ddd; padding: 0.5rem; text-align: center;">☐</td>
                    </tr>
                `;
            });
            
            deliveryContent += `
                    </tbody>
                </table>
            `;
            
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Delivery Sheet - <?php echo !empty($selected_date) ? date('m/d/Y', strtotime($selected_date)) : 'Date not selected'; ?></title>
                        <style>
                            body { font-family: 'BaticaSans', 'Inter', sans-serif; margin: 20px; }
                            table { width: 100%; border-collapse: collapse; }
                            th, td { border: 1px solid #ddd; padding: 0.5rem; font-size: 12px; }
                            th { background: #f5f5f5; font-weight: bold; }
                            h1 { margin-bottom: 10px; }
                            h2 { margin-bottom: 20px; color: #666; }
                        </style>
                    </head>
                    <body>${deliveryContent}</body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();

            // Close dropdown after printing
            togglePrintDropdown();
        }

        function printIngredientsList() {
            // Create ingredients summary from PHP data
            const mealSummary = <?php echo json_encode($meal_summary, JSON_UNESCAPED_UNICODE); ?>;
            const ingredients = new Map();
            
            // Process each meal to extract ingredients
            Object.values(mealSummary).forEach(meal => {
                if (meal.ingredients) {
                    const mealIngredients = meal.ingredients.split(',');
                    const quantity = meal.total_quantity;
                    
                    mealIngredients.forEach(ingredient => {
                        const trimmed = ingredient.trim();
                        if (trimmed) {
                            ingredients.set(trimmed, (ingredients.get(trimmed) || 0) + quantity);
                        }
                    });
                }
            });
            
            let ingredientsContent = `
                <div style="text-align: center; margin-bottom: 2rem;">
                    <h1 style="color: #cf723a;">Somdul Table - Ingredients List</h1>
                    <h2>Delivery Date: <?php echo !empty($selected_date) ? date('l, F j, Y', strtotime($selected_date)) : 'Date not selected'; ?></h2>
                    <p>Generated: ${new Date().toLocaleString('en-US')}</p>
                </div>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #ece8e1;">
                            <th style="border: 1px solid #ddd; padding: 1rem;">Ingredient</th>
                            <th style="border: 1px solid #ddd; padding: 1rem;">Quantity Needed</th>
                            <th style="border: 1px solid #ddd; padding: 1rem;">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            if (ingredients.size === 0) {
                ingredientsContent += `
                    <tr>
                        <td colspan="3" style="border: 1px solid #ddd; padding: 1rem; text-align: center; color: #666;">
                            No ingredients data available
                        </td>
                    </tr>
                `;
            } else {
                // Sort ingredients alphabetically
                const sortedIngredients = Array.from(ingredients.entries()).sort((a, b) => a[0].localeCompare(b[0]));
                
                sortedIngredients.forEach(([ingredient, count]) => {
                    ingredientsContent += `
                        <tr>
                            <td style="border: 1px solid #ddd; padding: 0.75rem;">${ingredient}</td>
                            <td style="border: 1px solid #ddd; padding: 0.75rem; text-align: center;">${count} portions</td>
                            <td style="border: 1px solid #ddd; padding: 0.75rem;"></td>
                        </tr>
                    `;
                });
            }
            
            ingredientsContent += `
                    </tbody>
                </table>
                <div style="margin-top: 2rem; font-size: 0.9rem; color: #666;">
                    <p><strong>Note:</strong> This list is automatically generated from menu ingredients. Please verify quantities and check inventory before ordering.</p>
                </div>
            `;
            
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Ingredients List - <?php echo !empty($selected_date) ? date('m/d/Y', strtotime($selected_date)) : 'Date not selected'; ?></title>
                        <style>
                            body { font-family: 'BaticaSans', 'Inter', sans-serif; margin: 20px; }
                            table { width: 100%; border-collapse: collapse; }
                            th, td { border: 1px solid #ddd; padding: 0.75rem; }
                            th { background: #f5f5f5; font-weight: bold; }
                            h1 { margin-bottom: 10px; }
                            h2 { margin-bottom: 20px; color: #666; }
                            @media print {
                                body { margin: 0; }
                                table { page-break-inside: auto; }
                                tr { page-break-inside: avoid; page-break-after: auto; }
                            }
                        </style>
                    </head>
                    <body>${ingredientsContent}</body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();

            // Close dropdown after printing
            togglePrintDropdown();
        }

        function markAllCompleted() {
            if (confirm('Are you sure you want to mark all meal preparations as completed?')) {
                // Show loading state
                const btn = event.target;
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                btn.disabled = true;
                
                // Simulate API call (replace with actual AJAX call)
                setTimeout(() => {
                    // Visual feedback
                    document.querySelectorAll('.prep-item').forEach(item => {
                        item.style.opacity = '0.6';
                        item.style.background = 'linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%)';
                    });
                    
                    // Show success message
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-success';
                    alertDiv.innerHTML = '<i class="fas fa-check-circle"></i> All meal preparations marked as completed!';
                    document.querySelector('.main-container').insertBefore(alertDiv, document.querySelector('.dashboard-controls'));
                    
                    // Reset button
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    
                    // Remove alert after 3 seconds
                    setTimeout(() => {
                        alertDiv.remove();
                    }, 3000);
                }, 1000);
            }
        }

        // Auto-refresh every 5 minutes (optional)
        setInterval(() => {
            if (confirm('Would you like to automatically refresh the data?')) {
                window.location.reload();
            }
        }, 300000); // 5 minutes

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey) {
                switch(e.key) {
                    case 'p':
                        e.preventDefault();
                        printPrepSheet();
                        break;
                    case 'r':
                        e.preventDefault();
                        refreshData();
                        break;
                    case '1':
                        e.preventDefault();
                        toggleAllCustomerCards();
                        break;
                    case '2':
                        e.preventDefault();
                        toggleAllPrepItems();
                        break;
                }
            }
        });

        // Log successful load
        console.log('✅ Kitchen Dashboard loaded successfully');
        console.log('📊 Statistics:');
        console.log('- Total customers:', <?php echo $total_customers; ?>);
        console.log('- Total meals:', <?php echo $total_meals; ?>);
        console.log('- Selected date:', '<?php echo $selected_date; ?>');
        console.log('- Timezone: Eastern Time (America/New_York)');
        
        // Export to Excel function
        function exportToExcel() {
            const customers = <?php echo json_encode($delivery_orders, JSON_UNESCAPED_UNICODE); ?>;
            
            // Create CSV content
            let csvContent = '\ufeff'; // UTF-8 BOM for proper Excel display
            csvContent += 'Date,Customer Name,Phone,Address,Delivery Time,Plan,Menu,Quantity,Customizations,Special Requests,Dietary Preferences,Allergies,Spice Level\n';
            
            customers.forEach(customer => {
                customer.meals.forEach(meal => {
                    const row = [
                        '<?php echo $selected_date; ?>',
                        `"${customer.first_name} ${customer.last_name}"`,
                        `"${customer.phone || ''}"`,
                        `"${(customer.delivery_address || '') + ', ' + (customer.city || '')}"`,
                        `"${customer.preferred_delivery_time || '3:00 PM - 6:00 PM'}"`,
                        `"${customer.plan_name || 'Standard Plan'}"`,
                        `"${meal.menu_name}"`,
                        meal.quantity,
                        `"${meal.customizations || ''}"`,
                        `"${meal.special_requests || ''}"`,
                        `"${customer.dietary_preferences || ''}"`,
                        `"${customer.allergies || ''}"`,
                        `"${customer.spice_level || ''}"`
                    ];
                    csvContent += row.join(',') + '\n';
                });
            });
            
            // Create and download file
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', `kitchen_prep_<?php echo $selected_date; ?>.csv`);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            // Close dropdown after export
            togglePrintDropdown();
        }


        let calendarOpen = false;
let currentCalendarDate = new Date();
let availableDeliveryDates = <?php echo json_encode($available_delivery_days); ?>;
let selectedDateValue = '<?php echo $selected_date; ?>';

function toggleCalendar() {
    const trigger = document.querySelector('.calendar-trigger');
    const panel = document.getElementById('calendarPanel');
    
    calendarOpen = !calendarOpen;
    
    if (calendarOpen) {
        trigger.classList.add('active');
        panel.classList.add('active');
        generateCalendar();
    } else {
        trigger.classList.remove('active');
        panel.classList.remove('active');
    }
}

function changeMonth(direction) {
    currentCalendarDate.setMonth(currentCalendarDate.getMonth() + direction);
    generateCalendar();
}

function generateCalendar() {
    const year = currentCalendarDate.getFullYear();
    const month = currentCalendarDate.getMonth();
    
    // Update calendar title
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'];
    document.getElementById('calendarTitle').textContent = `${monthNames[month]} ${year}`;
    
    // Get first day of month and number of days
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const daysInMonth = lastDay.getDate();
    const startingDayOfWeek = firstDay.getDay(); // 0 = Sunday
    
    // Get previous month's last days
    const prevMonth = new Date(year, month, 0);
    const daysInPrevMonth = prevMonth.getDate();
    
    let calendarHTML = '';
    let dayCount = 1;
    let nextMonthDay = 1;
    
    // Generate 6 weeks (42 days) to fill calendar grid
    for (let week = 0; week < 6; week++) {
        for (let day = 0; day < 7; day++) {
            const cellIndex = week * 7 + day;
            let dayNumber, cellClass, cellDate, isClickable = false;
            
            if (cellIndex < startingDayOfWeek) {
                // Previous month days
                dayNumber = daysInPrevMonth - startingDayOfWeek + cellIndex + 1;
                cellClass = 'calendar-day other-month';
                cellDate = new Date(year, month - 1, dayNumber);
            } else if (dayCount <= daysInMonth) {
                // Current month days
                dayNumber = dayCount;
                cellClass = 'calendar-day current-month';
                cellDate = new Date(year, month, dayNumber);
                
                // Check if it's today
                const today = new Date();
                if (cellDate.toDateString() === today.toDateString()) {
                    cellClass += ' today';
                }
                
                // Check if it's a delivery day (Wednesday = 3, Saturday = 6)
                const dayOfWeek = cellDate.getDay();
                if (dayOfWeek === 3 || dayOfWeek === 6) { // Wednesday or Saturday
                    const dateString = cellDate.getFullYear() + '-' + 
                        String(cellDate.getMonth() + 1).padStart(2, '0') + '-' + 
                        String(cellDate.getDate()).padStart(2, '0');
                    
                    // Check if this delivery date has available orders
                    const hasDelivery = availableDeliveryDates.some(d => d.date === dateString);
                    
                    if (hasDelivery) {
                        cellClass += ' delivery-day';
                        isClickable = true;
                        
                        // Check if selected
                        if (dateString === selectedDateValue) {
                            cellClass += ' selected';
                        }
                    } else {
                        cellClass += ' disabled';
                    }
                } else {
                    cellClass += ' disabled';
                }
                
                dayCount++;
            } else {
                // Next month days
                dayNumber = nextMonthDay;
                cellClass = 'calendar-day other-month';
                cellDate = new Date(year, month + 1, nextMonthDay);
                nextMonthDay++;
            }
            
            const clickHandler = isClickable ? 
                `onclick="selectCalendarDate('${cellDate.getFullYear()}-${String(cellDate.getMonth() + 1).padStart(2, '0')}-${String(cellDate.getDate()).padStart(2, '0')}')"` : '';
            
            calendarHTML += `<div class="${cellClass}" ${clickHandler}>${dayNumber}</div>`;
        }
        
        // Stop generating weeks if we've shown all days of current month and next month days
        if (dayCount > daysInMonth && nextMonthDay > 7) break;
    }
    
    document.getElementById('calendarDays').innerHTML = calendarHTML;
}

function selectCalendarDate(dateString) {
    // Find the delivery date object
    const deliveryDate = availableDeliveryDates.find(d => d.date === dateString);
    
    if (deliveryDate) {
        // Update display text
        const date = new Date(dateString);
        const displayText = deliveryDate.display;
        document.getElementById('selectedDateText').textContent = displayText;
        
        // Close calendar
        document.querySelector('.calendar-trigger').classList.remove('active');
        document.getElementById('calendarPanel').classList.remove('active');
        calendarOpen = false;
        
        // Navigate to selected date
        filterByDate(dateString);
    }
}

// Close calendar when clicking outside
document.addEventListener('click', function(event) {
    const dropdown = document.querySelector('.calendar-dropdown');
    
    if (calendarOpen && !dropdown.contains(event.target)) {
        document.querySelector('.calendar-trigger').classList.remove('active');
        document.getElementById('calendarPanel').classList.remove('active');
        calendarOpen = false;
    }
});

// Keyboard support
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape' && calendarOpen) {
        toggleCalendar();
    }
    if (event.key === 'Escape' && printDropdownOpen) {
        togglePrintDropdown();
    }
});

// Initialize calendar when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Set current calendar date to selected date or current month
    if (selectedDateValue) {
        currentCalendarDate = new Date(selectedDateValue);
    }
});
    </script>
</body>
</html>