<?php
/**
 * Somdul Table - Nutrition Tracking Page (Redesigned)
 * File: nutrition-tracking.php
 * Description: Comprehensive nutrition tracking with daily, weekly, and monthly views
 * UPDATED: Now uses header.php for consistent navigation and styling
 * Features: Healthy, balanced nutrition tracking that promotes wellbeing
 */

session_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Include the header (contains navbar, promo banner, fonts, and base styles)
include 'header.php';

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// ===== NUTRITION FUNCTIONS =====
function getNutritionTargets($goal_type = 'maintenance') {
    // Balanced, health-focused nutrition targets
    $targets = [
        'weight_loss' => ['calories' => 1600, 'protein' => 120, 'carbs' => 150, 'fat' => 55, 'description' => 'Balanced approach for gradual, healthy weight management'],
        'maintenance' => ['calories' => 2000, 'protein' => 150, 'carbs' => 250, 'fat' => 65, 'description' => 'Well-rounded nutrition for maintaining current health'],
        'muscle_gain' => ['calories' => 2400, 'protein' => 180, 'carbs' => 300, 'fat' => 80, 'description' => 'Protein-rich meals supporting muscle development'],
        'healthy_thai' => ['calories' => 1800, 'protein' => 135, 'carbs' => 225, 'fat' => 60, 'description' => 'Traditional Thai nutrition with modern health insights']
    ];
    
    return $targets[$goal_type] ?? $targets['maintenance'];
}

function syncLatestOrdersToNutrition($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO subscription_menus (id, subscription_id, menu_id, delivery_date, quantity, status, created_at)
            SELECT 
                CONCAT('auto-', UUID()) as id,
                COALESCE(o.subscription_id, 'manual-order') as subscription_id,
                oi.menu_id,
                o.delivery_date,
                oi.quantity,
                'delivered' as status,
                NOW() as created_at
            FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            WHERE o.user_id = ? 
              AND o.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
              AND o.status IN ('confirmed', 'preparing', 'ready', 'out_for_delivery', 'delivered')
              AND NOT EXISTS (
                  SELECT 1 FROM subscription_menus sm2 
                  WHERE sm2.menu_id = oi.menu_id 
                    AND DATE(sm2.delivery_date) = DATE(o.delivery_date)
                    AND sm2.subscription_id LIKE '%manual%'
              )
        ");
        
        $result = $stmt->execute([$user_id]);
        $affected_rows = $stmt->rowCount();
        
        return [
            'success' => $result,
            'synced_meals' => $affected_rows,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    } catch (Exception $e) {
        error_log("Sync error: " . $e->getMessage());
        return ['success' => false, 'synced_meals' => 0, 'timestamp' => date('Y-m-d H:i:s')];
    }
}

function calculateDailyNutritionFromSubscription($pdo, $user_id, $date) {
    try {
        $stmt = $pdo->prepare("
            SELECT m.id, m.name_thai, m.name, m.calories_per_serving, m.protein_g, m.carbs_g, m.fat_g, 
                   m.fiber_g, m.sodium_mg, sm.quantity, m.main_image_url, m.category
            FROM subscription_menus sm
            JOIN menus m ON sm.menu_id = m.id
            JOIN subscriptions s ON sm.subscription_id = s.id
            WHERE s.user_id = ? AND DATE(sm.delivery_date) = ?
            AND s.status IN ('active', 'paused')
        ");
        $stmt->execute([$user_id, $date]);
        $meals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $totals = [
            'calories' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0, 'fiber' => 0, 'sodium' => 0,
            'meals_count' => count($meals), 'meals' => []
        ];
        
        foreach ($meals as $meal) {
            $qty = intval($meal['quantity']) ?: 1;
            $totals['calories'] += (floatval($meal['calories_per_serving']) ?: 0) * $qty;
            $totals['protein'] += (floatval($meal['protein_g']) ?: 0) * $qty;
            $totals['carbs'] += (floatval($meal['carbs_g']) ?: 0) * $qty;
            $totals['fat'] += (floatval($meal['fat_g']) ?: 0) * $qty;
            $totals['fiber'] += (floatval($meal['fiber_g']) ?: 0) * $qty;
            $totals['sodium'] += (floatval($meal['sodium_mg']) ?: 0) * $qty;
            $totals['meals'][] = $meal;
        }
        
        // Calculate percentages with health-focused messaging
        $goal_type = $_SESSION['nutrition_goal'] ?? 'maintenance';
        $targets = getNutritionTargets($goal_type);

        $totals['calories_percent'] = $targets['calories'] > 0 ? round(($totals['calories'] / $targets['calories']) * 100) : 0;
        $totals['protein_percent'] = $targets['protein'] > 0 ? round(($totals['protein'] / $targets['protein']) * 100) : 0;
        $totals['carbs_percent'] = $targets['carbs'] > 0 ? round(($totals['carbs'] / $targets['carbs']) * 100) : 0;
        $totals['fat_percent'] = $targets['fat'] > 0 ? round(($totals['fat'] / $targets['fat']) * 100) : 0;

        $totals['targets'] = $targets;
        $totals['goal_type'] = $goal_type;
        $totals['message'] = getNutritionMessage($totals);
        $totals['status_color'] = getNutritionColor($totals['calories_percent']);
        
        return $totals;
    } catch (Exception $e) {
        error_log("Calculate nutrition error: " . $e->getMessage());
        return [
            'calories' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0, 'fiber' => 0, 'sodium' => 0,
            'meals_count' => 0, 'meals' => [], 'message' => 'Unable to load nutrition data',
            'status_color' => '#e74c3c', 'targets' => getNutritionTargets(), 'goal_type' => 'maintenance'
        ];
    }
}

function getNutritionMessage($totals) {
    $calories = $totals['calories'];
    $protein = $totals['protein'];
    $meals = $totals['meals_count'];
    
    if ($meals == 0) {
        return "No meals scheduled for this day. Consider adding some delicious Thai meals to your plan!";
    }
    
    // Health-focused, positive messaging
    if ($calories >= 1200 && $calories <= 2800 && $protein >= 50) {
        if ($calories >= 1600 && $calories <= 2200 && $protein >= 100) {
            return "Excellent nutritional balance! Your Thai meals provide great variety and nutrients.";
        } else {
            return "Good nutrition from your Thai meal selection. You're nourishing your body well!";
        }
    } elseif ($calories < 1200) {
        return "Consider adding more meals to ensure you're getting adequate nutrition throughout the day.";
    } elseif ($protein < 50) {
        return "Your meals look delicious! Consider adding some protein-rich Thai dishes for balanced nutrition.";
    } else {
        return "Your Thai meal plan provides good nutrition. Keep enjoying these flavorful, healthy choices!";
    }
}

function getNutritionColor($percent) {
    if ($percent < 50) return '#f39c12'; // Orange - room for more
    if ($percent < 80) return '#3498db'; // Blue - on track
    if ($percent <= 110) return '#27ae60'; // Green - excellent
    return '#2c3e50'; // Dark - adequate
}

function getWeeklyNutritionSummary($pdo, $user_id, $week_start) {
    try {
        $week_end = date('Y-m-d', strtotime($week_start . ' +6 days'));
        $weekly_data = [];
        
        for ($i = 0; $i < 7; $i++) {
            $date = date('Y-m-d', strtotime($week_start . " +$i days"));
            $daily = calculateDailyNutritionFromSubscription($pdo, $user_id, $date);
            $weekly_data[$date] = $daily;
        }
        
        // Calculate weekly totals and averages
        $week_totals = ['calories' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0, 'fiber' => 0, 'meals' => 0];
        
        foreach ($weekly_data as $daily) {
            $week_totals['calories'] += $daily['calories'];
            $week_totals['protein'] += $daily['protein'];
            $week_totals['carbs'] += $daily['carbs'];
            $week_totals['fat'] += $daily['fat'];
            $week_totals['fiber'] += $daily['fiber'];
            $week_totals['meals'] += $daily['meals_count'];
        }
        
        return [
            'week_start' => $week_start,
            'week_end' => $week_end,
            'daily_data' => $weekly_data,
            'totals' => $week_totals,
            'averages' => [
                'calories' => round($week_totals['calories'] / 7),
                'protein' => round($week_totals['protein'] / 7, 1),
                'carbs' => round($week_totals['carbs'] / 7, 1),
                'fat' => round($week_totals['fat'] / 7, 1),
                'fiber' => round($week_totals['fiber'] / 7, 1),
                'meals' => round($week_totals['meals'] / 7, 1)
            ]
        ];
    } catch (Exception $e) {
        error_log("Weekly summary error: " . $e->getMessage());
        return null;
    }
}

function getMonthlyNutritionSummary($pdo, $user_id, $month_start) {
    try {
        $month_end = date('Y-m-t', strtotime($month_start));
        $days_in_month = date('t', strtotime($month_start));
        
        $stmt = $pdo->prepare("
            SELECT 
                DATE(sm.delivery_date) as date,
                SUM(m.calories_per_serving * sm.quantity) as daily_calories,
                SUM(m.protein_g * sm.quantity) as daily_protein,
                SUM(m.carbs_g * sm.quantity) as daily_carbs,
                SUM(m.fat_g * sm.quantity) as daily_fat,
                SUM(m.fiber_g * sm.quantity) as daily_fiber,
                COUNT(*) as daily_meals
            FROM subscription_menus sm
            JOIN menus m ON sm.menu_id = m.id
            JOIN subscriptions s ON sm.subscription_id = s.id
            WHERE s.user_id = ? 
            AND sm.delivery_date BETWEEN ? AND ?
            AND s.status IN ('active', 'paused')
            GROUP BY DATE(sm.delivery_date)
            ORDER BY DATE(sm.delivery_date)
        ");
        
        $stmt->execute([$user_id, $month_start, $month_end]);
        $monthly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process data
        $month_totals = ['calories' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0, 'fiber' => 0, 'meals' => 0];
        $active_days = 0;
        
        foreach ($monthly_data as $day) {
            if ($day['daily_calories'] > 0) {
                $active_days++;
                $month_totals['calories'] += $day['daily_calories'];
                $month_totals['protein'] += $day['daily_protein'];
                $month_totals['carbs'] += $day['daily_carbs'];
                $month_totals['fat'] += $day['daily_fat'];
                $month_totals['fiber'] += $day['daily_fiber'];
                $month_totals['meals'] += $day['daily_meals'];
            }
        }
        
        return [
            'month_start' => $month_start,
            'month_end' => $month_end,
            'days_in_month' => $days_in_month,
            'active_days' => $active_days,
            'daily_data' => $monthly_data,
            'totals' => $month_totals,
            'averages' => $active_days > 0 ? [
                'calories' => round($month_totals['calories'] / $active_days),
                'protein' => round($month_totals['protein'] / $active_days, 1),
                'carbs' => round($month_totals['carbs'] / $active_days, 1),
                'fat' => round($month_totals['fat'] / $active_days, 1),
                'fiber' => round($month_totals['fiber'] / $active_days, 1),
                'meals' => round($month_totals['meals'] / $active_days, 1)
            ] : ['calories' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0, 'fiber' => 0, 'meals' => 0]
        ];
    } catch (Exception $e) {
        error_log("Monthly summary error: " . $e->getMessage());
        return null;
    }
}

// ===== AJAX HANDLERS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        switch ($_POST['action']) {
            case 'get_daily_nutrition':
                $date = $_POST['date'] ?? $today;
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    echo json_encode(['success' => false, 'error' => 'Invalid date format']);
                    break;
                }
                $nutrition = calculateDailyNutritionFromSubscription($pdo, $user_id, $date);
                echo json_encode(['success' => true, 'data' => $nutrition]);
                break;
                
            case 'get_weekly_summary':
                $week_start = $_POST['week_start'] ?? date('Y-m-d', strtotime('monday this week'));
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $week_start)) {
                    echo json_encode(['success' => false, 'error' => 'Invalid date format']);
                    break;
                }
                $weekly = getWeeklyNutritionSummary($pdo, $user_id, $week_start);
                echo json_encode(['success' => true, 'data' => $weekly]);
                break;
                
            case 'get_monthly_summary':
                $month_start = $_POST['month_start'] ?? date('Y-m-01');
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $month_start)) {
                    echo json_encode(['success' => false, 'error' => 'Invalid date format']);
                    break;
                }
                $monthly = getMonthlyNutritionSummary($pdo, $user_id, $month_start);
                echo json_encode(['success' => true, 'data' => $monthly]);
                break;
                
            case 'set_nutrition_goal':
                $goal_type = $_POST['goal_type'] ?? 'maintenance';
                $allowed_goals = ['weight_loss', 'maintenance', 'muscle_gain', 'healthy_thai'];
                
                if (!in_array($goal_type, $allowed_goals)) {
                    echo json_encode(['success' => false, 'error' => 'Invalid goal type']);
                    break;
                }
                
                $_SESSION['nutrition_goal'] = $goal_type;
                echo json_encode(['success' => true, 'message' => 'Goal updated successfully']);
                break;
                
            case 'sync_latest_orders':
                $sync_result = syncLatestOrdersToNutrition($pdo, $user_id);
                echo json_encode([
                    'success' => true,
                    'updated' => $sync_result['synced_meals'] > 0,
                    'synced_meals' => $sync_result['synced_meals'],
                    'message' => $sync_result['synced_meals'] > 0 ? 
                        "Synced {$sync_result['synced_meals']} new meals" : "No new orders to sync"
                ]);
                break;
                
            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
                break;
        }
    } catch (Exception $e) {
        error_log("AJAX error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Server error occurred']);
    }
    exit();
}

// ===== FETCH DATA FOR PAGE =====
try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Get selected date and view
    $selected_date = $_GET['date'] ?? $today;
    $view_type = $_GET['view'] ?? 'daily';
    
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
        $selected_date = $today;
    }
    
    // Ensure date is not in future
    if ($selected_date > $today) {
        $selected_date = $today;
    }
    
    // Get data based on view type
    $selected_nutrition = calculateDailyNutritionFromSubscription($pdo, $user_id, $selected_date);
    $weekly_summary = getWeeklyNutritionSummary($pdo, $user_id, date('Y-m-d', strtotime('monday this week', strtotime($selected_date))));
    $monthly_summary = getMonthlyNutritionSummary($pdo, $user_id, date('Y-m-01', strtotime($selected_date)));
    
    // Get user info
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Page load error: " . $e->getMessage());
    $error_message = "Unable to load nutrition data. Please try again later.";
    
    // Fallback data
    $selected_nutrition = ['calories' => 0, 'protein' => 0, 'meals_count' => 0, 'meals' => []];
    $weekly_summary = null;
    $monthly_summary = null;
    $user_info = ['first_name' => 'User'];
}

$page_title = "Nutrition Tracking";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Somdul Table</title>
    <meta name="description" content="Track your nutrition with healthy Thai meals from Somdul Table">
    
    <style>
        /* NUTRITION TRACKING SPECIFIC STYLES ONLY - Base styles come from header.php */
        
        /* Main Content Layout */
        .main-content {
            padding-top: 2rem;
            min-height: calc(100vh - 100px);
            padding-left: 2rem;
            padding-right: 2rem;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, var(--brown) 0%, var(--sage) 100%);
            color: var(--white);
            padding: 3rem 2rem;
            margin-bottom: 3rem;
            border-radius: var(--radius-lg);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="nutrition" width="50" height="50" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="2" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="2" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23nutrition)"/></svg>');
            opacity: 0.3;
        }

        .page-header-content {
            position: relative;
            z-index: 1;
        }

        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
        }

        .page-header p {
            font-size: 1.2rem;
            opacity: 0.9;
        }

        /* View Tabs */
        .view-tabs {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin: 2rem 0;
            padding: 0.5rem;
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
        }

        .tab-button {
            padding: 1rem 2rem;
            border: none;
            background: transparent;
            color: var(--text-gray);
            font-weight: 600;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: var(--transition);
            font-family: 'BaticaSans', sans-serif;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .tab-button:hover {
            background: var(--cream);
            color: var(--brown);
        }

        .tab-button.active {
            background: var(--brown);
            color: var(--white);
            box-shadow: var(--shadow-soft);
        }

        /* Content Sections */
        .content-section {
            display: none;
            animation: fadeIn 0.3s ease-in-out;
        }

        .content-section.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Cards */
        .nutrition-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-soft);
            border: 1px solid rgba(189, 147, 121, 0.1);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--cream);
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--brown);
            margin: 0;
        }

        .card-subtitle {
            color: var(--text-gray);
            margin: 0;
            font-size: 1rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-item {
            text-align: center;
            padding: 1.5rem;
            background: var(--cream);
            border-radius: var(--radius-md);
            border: 2px solid transparent;
            transition: var(--transition);
        }

        .stat-item:hover {
            border-color: var(--brown);
            transform: translateY(-2px);
        }

        .stat-value {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--brown);
            margin-bottom: 0.5rem;
            display: block;
        }

        .stat-label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.25rem;
        }

        .stat-subtitle {
            font-size: 0.9rem;
            color: var(--text-gray);
        }

        /* Progress Bars */
        .nutrition-progress {
            margin: 2rem 0;
        }

        .progress-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            padding: 1rem;
            background: var(--cream);
            border-radius: var(--radius-md);
        }

        .progress-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: var(--white);
        }

        .progress-icon.protein { background: var(--curry); }
        .progress-icon.carbs { background: var(--sage); }
        .progress-icon.fat { background: var(--brown); }

        .progress-info {
            flex: 1;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .progress-name {
            font-weight: 600;
            color: var(--text-dark);
        }

        .progress-value {
            font-weight: 700;
            color: var(--brown);
        }

        .progress-bar {
            height: 8px;
            background: var(--white);
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: var(--brown);
            border-radius: 4px;
            transition: width 0.6s ease;
        }

        /* Meals Display */
        .meals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .meal-card {
            background: var(--white);
            border-radius: var(--radius-md);
            overflow: hidden;
            box-shadow: var(--shadow-soft);
            border: 1px solid rgba(189, 147, 121, 0.1);
            transition: var(--transition);
        }

        .meal-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-medium);
        }

        .meal-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: linear-gradient(135deg, var(--cream), var(--sage));
        }

        .meal-content {
            padding: 1.5rem;
        }

        .meal-name {
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }

        .meal-category {
            color: var(--curry);
            font-size: 0.9rem;
            margin-bottom: 1rem;
            font-weight: 500;
        }

        .meal-nutrition {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
        }

        .nutrition-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            padding: 0.5rem;
            background: var(--cream);
            border-radius: var(--radius-sm);
        }

        /* Calendar View */
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1rem;
            margin: 2rem 0;
        }

        .calendar-day {
            aspect-ratio: 1;
            background: var(--white);
            border-radius: var(--radius-md);
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            border: 2px solid transparent;
            position: relative;
        }

        .calendar-day:hover {
            border-color: var(--brown);
            transform: scale(1.05);
        }

        .calendar-day.active {
            background: var(--brown);
            color: var(--white);
        }

        .day-number {
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .day-calories {
            font-size: 0.8rem;
            color: var(--curry);
            font-weight: 600;
        }

        /* Goal Setting */
        .goal-selection {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }

        .goal-option {
            padding: 2rem;
            background: var(--white);
            border: 2px solid var(--cream);
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
        }

        .goal-option:hover {
            border-color: var(--brown);
            transform: translateY(-2px);
        }

        .goal-option.selected {
            background: var(--brown);
            color: var(--white);
            border-color: var(--brown);
        }

        .goal-emoji {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            display: block;
        }

        .goal-name {
            font-weight: 700;
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }

        .goal-description {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--text-gray);
        }

        .empty-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Date Navigation */
        .date-navigation {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 2rem;
            margin: 2rem 0;
            padding: 1.5rem;
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
        }

        .nav-button {
            background: var(--brown);
            color: var(--white);
            border: none;
            padding: 1rem 1.5rem;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
        }

        .nav-button:hover:not(:disabled) {
            background: var(--curry);
            transform: translateY(-1px);
        }

        .nav-button:disabled {
            background: var(--text-gray);
            cursor: not-allowed;
        }

        .date-display {
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--brown);
        }

        /* Loading States */
        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            color: var(--text-gray);
        }

        .spinner {
            width: 24px;
            height: 24px;
            border: 3px solid var(--cream);
            border-top: 3px solid var(--brown);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 1rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .meals-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }
            
            .page-header {
                padding: 2rem 1rem;
                margin-bottom: 2rem;
            }
            
            .page-header h1 {
                font-size: 2rem;
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .view-tabs {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .tab-button {
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .meals-grid {
                grid-template-columns: 1fr;
            }
            
            .date-navigation {
                flex-direction: column;
                gap: 1rem;
            }
            
            .calendar-grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 0.5rem;
            }
            
            .goal-selection {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .nutrition-card {
                padding: 1.5rem;
                margin-bottom: 1.5rem;
            }
            
            .page-header h1 {
                font-size: 1.8rem;
            }
            
            .calendar-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
    </style>
</head>

<!-- IMPORTANT: Add has-header class for proper spacing -->
<body class="has-header">
    <!-- The header (promo banner + navbar + notifications) is already included from header.php -->

    <div class="main-content">
        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <div class="page-header-content">
                    <h1>
                        <span>🥗</span>
                        Nutrition Tracking
                    </h1>
                    <p>Monitor your healthy Thai meal journey with comprehensive nutrition insights</p>
                </div>
            </div>

            <?php if (isset($error_message)): ?>
            <div class="nutrition-card" style="background: #f8d7da; border-color: #f5c6cb; color: #721c24;">
                <p><strong>⚠️ <?= htmlspecialchars($error_message) ?></strong></p>
            </div>
            <?php endif; ?>

            <!-- View Tabs -->
            <div class="view-tabs">
                <button class="tab-button active" data-view="daily">
                    📅 Daily View
                </button>
                <button class="tab-button" data-view="weekly">
                    📊 Weekly Summary
                </button>
                <button class="tab-button" data-view="monthly">
                    📈 Monthly Overview
                </button>
                <button class="tab-button" data-view="goals">
                    🎯 Goals & Settings
                </button>
            </div>

            <!-- Daily View -->
            <div class="content-section active" id="daily-view">
                <!-- Date Navigation -->
                <div class="date-navigation">
                    <button class="nav-button" onclick="navigateDate(-1)">
                        ← Previous Day
                    </button>
                    <div class="date-display">
                        <input type="date" id="selectedDate" value="<?= htmlspecialchars($selected_date) ?>" 
                               max="<?= htmlspecialchars($today) ?>" 
                               min="<?= date('Y-m-d', strtotime('-30 days')) ?>"
                               onchange="loadDateNutrition(this.value)"
                               style="border: none; background: transparent; color: var(--brown); font-weight: 700; font-size: 1.2rem;">
                    </div>
                    <button class="nav-button" onclick="navigateDate(1)" <?= $selected_date >= $today ? 'disabled' : '' ?>>
                        Next Day →
                    </button>
                </div>

                <!-- Daily Stats -->
                <div class="nutrition-card">
                    <div class="card-header">
                        <div>
                            <h2 class="card-title">Today's Nutrition Summary</h2>
                            <p class="card-subtitle"><?= $selected_nutrition['message'] ?></p>
                        </div>
                    </div>
                    
                    <div class="stats-grid">
                        <div class="stat-item">
                            <span class="stat-value"><?= number_format($selected_nutrition['calories']) ?></span>
                            <div class="stat-label">Calories</div>
                            <div class="stat-subtitle">From <?= $selected_nutrition['meals_count'] ?> meals</div>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?= number_format($selected_nutrition['protein'], 1) ?>g</span>
                            <div class="stat-label">Protein</div>
                            <div class="stat-subtitle"><?= $selected_nutrition['protein_percent'] ?>% of goal</div>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?= number_format($selected_nutrition['carbs'], 1) ?>g</span>
                            <div class="stat-label">Carbohydrates</div>
                            <div class="stat-subtitle"><?= $selected_nutrition['carbs_percent'] ?>% of goal</div>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?= number_format($selected_nutrition['fat'], 1) ?>g</span>
                            <div class="stat-label">Healthy Fats</div>
                            <div class="stat-subtitle"><?= $selected_nutrition['fat_percent'] ?>% of goal</div>
                        </div>
                    </div>

                    <!-- Progress Bars -->
                    <div class="nutrition-progress">
                        <div class="progress-item">
                            <div class="progress-icon protein">🥩</div>
                            <div class="progress-info">
                                <div class="progress-header">
                                    <span class="progress-name">Protein</span>
                                    <span class="progress-value"><?= number_format($selected_nutrition['protein'], 1) ?>g / <?= $selected_nutrition['targets']['protein'] ?>g</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?= min($selected_nutrition['protein_percent'], 100) ?>%"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="progress-item">
                            <div class="progress-icon carbs">🍚</div>
                            <div class="progress-info">
                                <div class="progress-header">
                                    <span class="progress-name">Carbohydrates</span>
                                    <span class="progress-value"><?= number_format($selected_nutrition['carbs'], 1) ?>g / <?= $selected_nutrition['targets']['carbs'] ?>g</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?= min($selected_nutrition['carbs_percent'], 100) ?>%"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="progress-item">
                            <div class="progress-icon fat">🥑</div>
                            <div class="progress-info">
                                <div class="progress-header">
                                    <span class="progress-name">Healthy Fats</span>
                                    <span class="progress-value"><?= number_format($selected_nutrition['fat'], 1) ?>g / <?= $selected_nutrition['targets']['fat'] ?>g</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?= min($selected_nutrition['fat_percent'], 100) ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Today's Meals -->
                <?php if ($selected_nutrition['meals_count'] > 0): ?>
                <div class="nutrition-card">
                    <div class="card-header">
                        <h2 class="card-title">Your Thai Meals Today</h2>
                        <p class="card-subtitle">Delicious and nutritious selections</p>
                    </div>
                    
                    <div class="meals-grid">
                        <?php foreach ($selected_nutrition['meals'] as $meal): ?>
                            <div class="meal-card">
                                <?php if (!empty($meal['main_image_url'])): ?>
                                    <img src="<?= htmlspecialchars($meal['main_image_url']) ?>" 
                                         alt="<?= htmlspecialchars($meal['name'] ?? $meal['name_thai']) ?>" 
                                         class="meal-image" 
                                         loading="lazy">
                                <?php else: ?>
                                    <div class="meal-image" style="display: flex; align-items: center; justify-content: center; color: var(--text-gray); font-size: 2rem;">
                                        🍽️
                                    </div>
                                <?php endif; ?>
                                
                                <div class="meal-content">
                                    <div class="meal-name">
                                        <?= htmlspecialchars($meal['name'] ?? $meal['name_thai']) ?>
                                    </div>
                                    
                                    <?php if (!empty($meal['category'])): ?>
                                        <div class="meal-category"><?= htmlspecialchars($meal['category']) ?></div>
                                    <?php endif; ?>
                                    
                                    <div class="meal-nutrition">
                                        <div class="nutrition-item">
                                            <span>🔥</span>
                                            <span><?= number_format(floatval($meal['calories_per_serving']) * intval($meal['quantity'] ?: 1)) ?> cal</span>
                                        </div>
                                        <div class="nutrition-item">
                                            <span>🥩</span>
                                            <span><?= number_format(floatval($meal['protein_g']) * intval($meal['quantity'] ?: 1), 1) ?>g protein</span>
                                        </div>
                                        <div class="nutrition-item">
                                            <span>🍚</span>
                                            <span><?= number_format(floatval($meal['carbs_g']) * intval($meal['quantity'] ?: 1), 1) ?>g carbs</span>
                                        </div>
                                        <div class="nutrition-item">
                                            <span>🥑</span>
                                            <span><?= number_format(floatval($meal['fat_g']) * intval($meal['quantity'] ?: 1), 1) ?>g fat</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="nutrition-card">
                    <div class="empty-state">
                        <div class="empty-icon">🍽️</div>
                        <h3>No Meals Scheduled</h3>
                        <p>Add some delicious Thai meals to your plan for better nutrition tracking!</p>
                        <a href="subscribe.php" class="btn btn-primary" style="margin-top: 1rem; display: inline-flex; align-items: center; gap: 0.5rem;">
                            <span>🍜</span> Browse Meal Plans
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Weekly View -->
            <div class="content-section" id="weekly-view">
                <div class="loading">
                    <div class="spinner"></div>
                    Loading weekly data...
                </div>
            </div>

            <!-- Monthly View -->
            <div class="content-section" id="monthly-view">
                <div class="loading">
                    <div class="spinner"></div>
                    Loading monthly data...
                </div>
            </div>

            <!-- Goals View -->
            <div class="content-section" id="goals-view">
                <div class="nutrition-card">
                    <div class="card-header">
                        <h2 class="card-title">Nutrition Goals</h2>
                        <p class="card-subtitle">Choose a goal that supports your health and wellbeing</p>
                    </div>
                    
                    <div class="goal-selection">
                        <div class="goal-option <?= ($_SESSION['nutrition_goal'] ?? 'maintenance') === 'maintenance' ? 'selected' : '' ?>" 
                             onclick="setNutritionGoal('maintenance', this)">
                            <span class="goal-emoji">⚖️</span>
                            <div class="goal-name">Balanced Maintenance</div>
                            <div class="goal-description">
                                2,000 calories daily with balanced macronutrients for overall health and energy
                            </div>
                        </div>
                        
                        <div class="goal-option <?= ($_SESSION['nutrition_goal'] ?? '') === 'healthy_thai' ? 'selected' : '' ?>" 
                             onclick="setNutritionGoal('healthy_thai', this)">
                            <span class="goal-emoji">🇹🇭</span>
                            <div class="goal-name">Healthy Thai Focus</div>
                            <div class="goal-description">
                                1,800 calories featuring traditional Thai nutrition wisdom with modern health insights
                            </div>
                        </div>
                        
                        <div class="goal-option <?= ($_SESSION['nutrition_goal'] ?? '') === 'weight_loss' ? 'selected' : '' ?>" 
                             onclick="setNutritionGoal('weight_loss', this)">
                            <span class="goal-emoji">🌱</span>
                            <div class="goal-name">Mindful Eating</div>
                            <div class="goal-description">
                                1,600 calories with emphasis on nutrition density and mindful portion control
                            </div>
                        </div>
                        
                        <div class="goal-option <?= ($_SESSION['nutrition_goal'] ?? '') === 'muscle_gain' ? 'selected' : '' ?>" 
                             onclick="setNutritionGoal('muscle_gain', this)">
                            <span class="goal-emoji">💪</span>
                            <div class="goal-name">Active Lifestyle</div>
                            <div class="goal-description">
                                2,400 calories with higher protein to support an active, fitness-focused lifestyle
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Nutrition Tracking JavaScript
        let currentView = 'daily';
        let selectedDate = '<?= $selected_date ?>';

        document.addEventListener('DOMContentLoaded', function() {
            initializeNutritionTracking();
        });

        function initializeNutritionTracking() {
            console.log('Nutrition Tracking initialized');
            
            // Setup tab switching
            setupTabs();
            
            // Setup date navigation
            setupDateNavigation();
            
            // Load initial data
            loadWeeklyData();
            loadMonthlyData();
            
            // Setup auto-refresh
            setupAutoRefresh();
        }

        function setupTabs() {
            const tabButtons = document.querySelectorAll('.tab-button');
            const contentSections = document.querySelectorAll('.content-section');
            
            tabButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const viewType = button.getAttribute('data-view');
                    switchView(viewType);
                });
            });
        }

        function switchView(viewType) {
            // Update active tab
            document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
            document.querySelector(`[data-view="${viewType}"]`).classList.add('active');
            
            // Update active content
            document.querySelectorAll('.content-section').forEach(section => section.classList.remove('active'));
            document.getElementById(`${viewType}-view`).classList.add('active');
            
            currentView = viewType;
            
            // Load data if needed
            if (viewType === 'weekly') {
                loadWeeklyData();
            } else if (viewType === 'monthly') {
                loadMonthlyData();
            }
        }

        function setupDateNavigation() {
            // Date input change handler is already inline
        }

        function navigateDate(direction) {
            const currentDate = new Date(selectedDate);
            currentDate.setDate(currentDate.getDate() + direction);
            
            const today = new Date();
            const minDate = new Date();
            minDate.setDate(today.getDate() - 30);
            
            if (currentDate <= today && currentDate >= minDate) {
                const newDateStr = currentDate.toISOString().split('T')[0];
                loadDateNutrition(newDateStr);
            }
        }

        function loadDateNutrition(date) {
            selectedDate = date;
            document.getElementById('selectedDate').value = date;
            
            // Update URL
            const url = new URL(window.location);
            url.searchParams.set('date', date);
            window.history.pushState({}, '', url);
            
            // Refresh page with new date
            window.location.reload();
        }

        function loadWeeklyData() {
            const weeklyView = document.getElementById('weekly-view');
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_weekly_summary&week_start=' + encodeURIComponent(getMonday(selectedDate))
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    renderWeeklyView(data.data);
                } else {
                    weeklyView.innerHTML = '<div class="empty-state"><h3>Unable to load weekly data</h3></div>';
                }
            })
            .catch(error => {
                console.error('Weekly data error:', error);
                weeklyView.innerHTML = '<div class="empty-state"><h3>Error loading weekly data</h3></div>';
            });
        }

        function loadMonthlyData() {
            const monthlyView = document.getElementById('monthly-view');
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_monthly_summary&month_start=' + encodeURIComponent(getMonthStart(selectedDate))
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    renderMonthlyView(data.data);
                } else {
                    monthlyView.innerHTML = '<div class="empty-state"><h3>Unable to load monthly data</h3></div>';
                }
            })
            .catch(error => {
                console.error('Monthly data error:', error);
                monthlyView.innerHTML = '<div class="empty-state"><h3>Error loading monthly data</h3></div>';
            });
        }

        function renderWeeklyView(weeklyData) {
            const weeklyView = document.getElementById('weekly-view');
            
            const html = `
                <div class="nutrition-card">
                    <div class="card-header">
                        <h2 class="card-title">Weekly Summary</h2>
                        <p class="card-subtitle">${formatDateRange(weeklyData.week_start, weeklyData.week_end)}</p>
                    </div>
                    
                    <div class="stats-grid">
                        <div class="stat-item">
                            <span class="stat-value">${weeklyData.totals.calories.toLocaleString()}</span>
                            <div class="stat-label">Total Calories</div>
                            <div class="stat-subtitle">${weeklyData.averages.calories}/day average</div>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">${weeklyData.totals.protein.toFixed(0)}g</span>
                            <div class="stat-label">Total Protein</div>
                            <div class="stat-subtitle">${weeklyData.averages.protein}g/day average</div>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">${weeklyData.totals.meals}</span>
                            <div class="stat-label">Total Meals</div>
                            <div class="stat-subtitle">${weeklyData.averages.meals}/day average</div>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">${weeklyData.totals.fiber.toFixed(0)}g</span>
                            <div class="stat-label">Total Fiber</div>
                            <div class="stat-subtitle">${weeklyData.averages.fiber}g/day average</div>
                        </div>
                    </div>
                    
                    ${renderWeeklyChart(weeklyData.daily_data)}
                </div>
            `;
            
            weeklyView.innerHTML = html;
        }

        function renderMonthlyView(monthlyData) {
            const monthlyView = document.getElementById('monthly-view');
            
            const html = `
                <div class="nutrition-card">
                    <div class="card-header">
                        <h2 class="card-title">Monthly Overview</h2>
                        <p class="card-subtitle">${formatMonth(monthlyData.month_start)} - ${monthlyData.active_days} active days</p>
                    </div>
                    
                    <div class="stats-grid">
                        <div class="stat-item">
                            <span class="stat-value">${monthlyData.totals.calories.toLocaleString()}</span>
                            <div class="stat-label">Total Calories</div>
                            <div class="stat-subtitle">${monthlyData.averages.calories}/day average</div>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">${monthlyData.totals.protein.toFixed(0)}g</span>
                            <div class="stat-label">Total Protein</div>
                            <div class="stat-subtitle">${monthlyData.averages.protein}g/day average</div>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">${monthlyData.totals.meals}</span>
                            <div class="stat-label">Total Meals</div>
                            <div class="stat-subtitle">${monthlyData.active_days}/${monthlyData.days_in_month} days active</div>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">${Math.round((monthlyData.active_days / monthlyData.days_in_month) * 100)}%</span>
                            <div class="stat-label">Consistency</div>
                            <div class="stat-subtitle">Days with meals tracked</div>
                        </div>
                    </div>
                </div>
            `;
            
            monthlyView.innerHTML = html;
        }

        function renderWeeklyChart(dailyData) {
            const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            let chartHtml = '<div class="calendar-grid" style="margin-top: 2rem;">';
            
            Object.keys(dailyData).forEach((date, index) => {
                const dayData = dailyData[date];
                const dayName = days[index] || days[index % 7];
                const isActive = date === selectedDate;
                
                chartHtml += `
                    <div class="calendar-day ${isActive ? 'active' : ''}" onclick="loadDateNutrition('${date}')">
                        <div class="day-number">${dayName.substr(0, 3)}</div>
                        <div class="day-number">${new Date(date).getDate()}</div>
                        <div class="day-calories">${dayData.calories.toLocaleString()} cal</div>
                    </div>
                `;
            });
            
            chartHtml += '</div>';
            return chartHtml;
        }

        function setNutritionGoal(goalType, element) {
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=set_nutrition_goal&goal_type=' + encodeURIComponent(goalType)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI
                    document.querySelectorAll('.goal-option').forEach(opt => opt.classList.remove('selected'));
                    element.classList.add('selected');
                    
                    showNotification('Nutrition goal updated successfully! 🎯', 'success');
                    
                    // Refresh after a delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showNotification('Failed to update goal', 'error');
                }
            })
            .catch(error => {
                console.error('Goal update error:', error);
                showNotification('Network error', 'error');
            });
        }

        function setupAutoRefresh() {
            // Periodic sync check (reduced frequency)
            setInterval(async function() {
                if (!document.hidden && Math.random() < 0.1) {
                    try {
                        const response = await fetch('', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'action=sync_latest_orders'
                        });
                        const data = await response.json();
                        
                        if (data.success && data.updated && data.synced_meals > 0) {
                            showNotification(`Added ${data.synced_meals} new meals to tracking`, 'success');
                        }
                    } catch (error) {
                        console.log('Auto-sync check failed:', error);
                    }
                }
            }, 5 * 60 * 1000);
        }

        // Utility functions
        function getMonday(dateStr) {
            const date = new Date(dateStr);
            const day = date.getDay();
            const diff = date.getDate() - day + (day === 0 ? -6 : 1);
            const monday = new Date(date.setDate(diff));
            return monday.toISOString().split('T')[0];
        }

        function getMonthStart(dateStr) {
            const date = new Date(dateStr);
            return new Date(date.getFullYear(), date.getMonth(), 1).toISOString().split('T')[0];
        }

        function formatDateRange(start, end) {
            const startDate = new Date(start);
            const endDate = new Date(end);
            const options = { month: 'short', day: 'numeric' };
            return `${startDate.toLocaleDateString('en-US', options)} - ${endDate.toLocaleDateString('en-US', options)}`;
        }

        function formatMonth(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
        }

        function showNotification(message, type = 'info') {
            const colors = {
                success: '#27ae60',
                error: '#e74c3c',
                info: '#3498db',
                warning: '#f39c12'
            };
            
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 2rem;
                right: 2rem;
                background: ${colors[type]};
                color: white;
                padding: 1rem 1.5rem;
                border-radius: 12px;
                font-weight: 600;
                z-index: 10000;
                box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                transform: translateX(100%);
                transition: transform 0.3s ease;
                max-width: 320px;
            `;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 100);
            
            setTimeout(() => {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 4000);
        }

        console.log('Somdul Table Nutrition Tracking loaded successfully! 🥗');
    </script>
</body>
</html>