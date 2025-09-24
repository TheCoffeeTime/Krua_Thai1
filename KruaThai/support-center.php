<?php
/**
 * Somdul Table - Customer Support Center
 * File: support-center.php
 * Description: Customer support page for submitting complaints, viewing tickets, and getting help
 * UPDATED: Now uses header.php for consistent navigation and styling
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

// Include the header (contains navbar, promo banner, fonts, and base styles)
include 'header.php';

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Database connection
try {
    $database = new Database();
    $pdo = $database->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'submit_complaint') {
            require_once 'includes/email_functions.php';// เพิ่มบรรทัดนี้
    
    // Generate complaint number (ใช้ format เดียวกัน)
    $complaint_id = bin2hex(random_bytes(16));
    $complaint_number = 'CMP-' . date('Ymd') . '-' . strtoupper(substr(md5($complaint_id), 0, 4));
    
    // Insert complaint (เพิ่ม id)
    $stmt = $pdo->prepare("
        INSERT INTO complaints (id, complaint_number, user_id, subscription_id, category, priority, title, description, expected_resolution, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'open', NOW())
    ");
    
    $stmt->execute([
        $complaint_id, // เพิ่มบรรทัดนี้
        $complaint_number,
        $user_id,
        $_POST['subscription_id'] ?: null,
        $_POST['category'],
        $_POST['priority'],
        $_POST['title'],
        $_POST['description'],
        $_POST['expected_resolution'] ?: null
    ]);
    
    // เพิ่มการส่ง email (เหมือน subscription-status.php)
    sendComplaintConfirmationEmail($pdo, $user_id, $complaint_number, $_POST);
    sendAdminNotificationEmail($pdo, $user_id, $complaint_number, $_POST);
    
 $success_message = "Complaint submitted successfully! Complaint number: {$complaint_number}";
        }
        
    } catch (Exception $e) {
        $error_message = "An error occurred: " . $e->getMessage();
        error_log("Support center error: " . $e->getMessage());
    }
}

// Get user's subscriptions
try {
    $stmt = $pdo->prepare("
        SELECT s.id, s.status, sp.name as plan_name, s.start_date, s.end_date
        FROM subscriptions s
        JOIN subscription_plans sp ON s.plan_id = sp.id
        WHERE s.user_id = ?
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $subscriptions = [];
    error_log("Error fetching subscriptions: " . $e->getMessage());
}

// Get user's complaints
try {
    $stmt = $pdo->prepare("
        SELECT c.*, s.status as subscription_status, sp.name as plan_name
        FROM complaints c
        LEFT JOIN subscriptions s ON c.subscription_id = s.id
        LEFT JOIN subscription_plans sp ON s.plan_id = sp.id
        WHERE c.user_id = ?
        ORDER BY c.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $user_complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $user_complaints = [];
    error_log("Error fetching complaints: " . $e->getMessage());
}

// Get user info
try {
    $stmt = $pdo->prepare("SELECT first_name, last_name, email, phone FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $user_info = ['first_name' => '', 'last_name' => '', 'email' => '', 'phone' => ''];
}

// Email Functions
function sendComplaintConfirmationEmail($pdo, $userId, $complaintNumber, $complaintData) {
    // Get user info
    $stmt = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) return false;
    
    $customerName = $user['first_name'] . ' ' . $user['last_name'];
    $customerEmail = $user['email'];
    
    $subject = "[Somdul Table] รับแจ้งปัญหา #{$complaintNumber}";
    
    $emailBody = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 0 auto; background: #fff; }
            .header { background: linear-gradient(135deg, #cf723a, #e67e22); color: white; padding: 30px 20px; text-align: center; }
            .content { padding: 30px 20px; }
            .complaint-box { background: #f8f9fa; border-left: 5px solid #cf723a; padding: 20px; margin: 20px 0; border-radius: 5px; }
            .info-box { background: linear-gradient(135deg, #3498db, #2980b9); color: white; padding: 20px; border-radius: 10px; margin: 20px 0; }
            .footer { background: #ece8e1; padding: 20px; text-align: center; color: #666; }
            .highlight { color: #cf723a; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1 style='margin: 0; font-size: 2rem;'>🍽️ Somdul Table</h1>
                <p style='margin: 10px 0 0 0; font-size: 1.2rem;'>ยืนยันการรับแจ้งปัญหา</p>
            </div>
            
            <div class='content'>
                <h2>เรียน คุณ {$customerName}</h2>
                
                <p>ขอบคุณที่แจ้งปัญหาให้เราทราบ เราได้รับเรื่องของท่านแล้วและจะดำเนินการแก้ไขโดยเร็วที่สุด</p>
                
                <div class='complaint-box'>
                    <h3 style='margin-top: 0; color: #cf723a;'>📋 รายละเอียดการแจ้งปัญหา</h3>
                    <p><strong>รหัสอ้างอิง:</strong> <span class='highlight'>#{$complaintNumber}</span></p>
                    <p><strong>หัวข้อ:</strong> {$complaintData['title']}</p>
                    <p><strong>ประเภท:</strong> " . ucfirst(str_replace('_', ' ', $complaintData['category'])) . "</p>
                    <p><strong>ระดับความสำคัญ:</strong> " . ucfirst($complaintData['priority']) . "</p>
                    <p><strong>วันที่แจ้ง:</strong> " . date('d/m/Y H:i น.') . "</p>
                </div>
                
                <div class='info-box'>
                    <h3 style='margin-top: 0;'>⏰ ขั้นตอนต่อไป</h3>
                    <p style='margin: 10px 0;'>✅ ทีมงานจะตรวจสอบและติดต่อกลับภายใน <strong>4 ชั่วโมง</strong></p>
                    <p style='margin: 10px 0;'>✅ ท่านสามารถตอบกลับอีเมลนี้ได้โดยตรง ไม่ต้องเข้าเว็บไซต์อีกครั้ง</p>
                    <p style='margin: 10px 0;'>✅ กรณีเร่งด่วน: โทร <strong>(02) 123-4567</strong></p>
                </div>
                
                <p>เราขออภัยในความไม่สะดวก และขอบคุณที่ให้โอกาสเราปรับปรุงการบริการให้ดียิ่งขึ้น</p>
                
                <p>ด้วยความห่วงใย<br>
                <strong>Somdul Table Support Team</strong></p>
            </div>
            
            <div class='footer'>
                <p><strong>Somdul Table - Authentic Thai Meals, Made Healthy</strong></p>
                <p>📧 support@somdultable.com | 📞 (02) 123-4567</p>
                <p style='font-size: 0.9em; margin-top: 15px;'>
                    อีเมลนี้ส่งเกี่ยวกับการแจ้งปัญหา #{$complaintNumber}
                </p>
            </div>
        </div>
    </body>
    </html>";
    
    return sendRealEmail($customerEmail, $subject, $emailBody);
}

function sendAdminNotificationEmail($pdo, $userId, $complaintNumber, $complaintData) {
    // Get customer info
    $stmt = $pdo->prepare("SELECT first_name, last_name, email, phone FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) return false;
    
    $customerName = $customer['first_name'] . ' ' . $customer['last_name'];
    $adminEmail = 'admin@kruathai.com';
    
    $subject = "[🚨 URGENT] New Complaint #{$complaintNumber} - " . ucfirst(str_replace('_', ' ', $complaintData['category']));
    
    $priorityColor = $complaintData['priority'] === 'critical' ? '#c0392b' : 
                    ($complaintData['priority'] === 'high' ? '#e74c3c' : '#f39c12');
    
    $emailBody = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
            .container { max-width: 700px; margin: 0 auto; background: #fff; }
            .header { background: linear-gradient(135deg, #cf723a, #e67e22); color: white; padding: 25px; text-align: center; }
            .priority-{$complaintData['priority']} { border-left: 5px solid {$priorityColor}; padding: 15px; margin: 20px 0; }
            .customer-info { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 15px 0; }
            .complaint-details { background: #e8f4f8; padding: 20px; border-radius: 8px; margin: 15px 0; }
            .action-button { background: #cf723a; color: white; padding: 12px 25px; text-decoration: none; border-radius: 6px; display: inline-block; margin: 10px 0; }
            .footer { background: #f1f1f1; padding: 20px; text-align: center; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1 style='margin: 0;'>🚨 New Customer Complaint Alert</h1>
                <p style='margin: 10px 0 0 0; font-size: 1.1rem;'>Requires Immediate Attention</p>
            </div>
            
            <div style='padding: 25px;'>
                <div class='priority-{$complaintData['priority']}'>
                    <h2 style='margin: 0 0 10px 0; color: {$priorityColor};'>Complaint #{$complaintNumber}</h2>
                    <p style='margin: 0; font-size: 1.1rem;'><strong>Priority:</strong> " . strtoupper($complaintData['priority']) . "</p>
                </div>
                
                <div class='customer-info'>
                    <h3 style='margin: 0 0 15px 0; color: #cf723a;'>👤 Customer Information</h3>
                    <p><strong>Name:</strong> {$customerName}</p>
                    <p><strong>Email:</strong> {$customer['email']}</p>
                    <p><strong>Phone:</strong> " . ($customer['phone'] ?: 'Not provided') . "</p>
                </div>
                
                <div class='complaint-details'>
                    <h3 style='margin: 0 0 15px 0; color: #2980b9;'>📋 Complaint Details</h3>
                    <p><strong>Category:</strong> " . ucfirst(str_replace('_', ' ', $complaintData['category'])) . "</p>
                    <p><strong>Subject:</strong> {$complaintData['title']}</p>
                    <p><strong>Description:</strong></p>
                    <div style='background: white; padding: 15px; border-radius: 5px; margin-top: 10px;'>
                        " . nl2br(htmlspecialchars($complaintData['description'])) . "
                    </div>
                    <p><strong>Submitted:</strong> " . date('d/m/Y H:i น.') . "</p>
                </div>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='https://somdultable.com/admin/complaints.php' class='action-button'>
                        📝 Respond to Complaint Now
                    </a>
                </div>
            </div>
        </div>
    </body>
    </html>";
    
    return sendRealEmail($adminEmail, $subject, $emailBody);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Support Center - Somdul Table</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        /* SUPPORT CENTER SPECIFIC STYLES ONLY - header styles come from header.php */
        
        /* Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Page Header */
        .page-header {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            margin-bottom: 2rem;
            text-align: center;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--brown);
            margin-bottom: 0.5rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .page-subtitle {
            color: var(--text-gray);
            font-size: 1.1rem;
            font-family: 'BaticaSans', sans-serif;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--radius-sm);
            margin-bottom: 2rem;
            border-left: 4px solid;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .alert-success {
            background: rgba(46, 204, 113, 0.1);
            border-left-color: #27ae60;
            color: #27ae60;
        }

        .alert-error {
            background: rgba(231, 76, 60, 0.1);
            border-left-color: #e74c3c;
            color: #e74c3c;
        }

        /* Tabs */
        .tabs {
            display: flex;
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .tab-button {
            flex: 1;
            padding: 1rem;
            background: transparent;
            border: none;
            cursor: pointer;
            font-family: 'BaticaSans', sans-serif;
            font-size: 1rem;
            font-weight: 500;
            color: var(--text-gray);
            transition: var(--transition);
        }

        .tab-button.active {
            background: var(--curry);
            color: var(--white);
        }

        .tab-button:hover:not(.active) {
            background: var(--cream);
        }

        /* Tab Content */
        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Cards */
        .card {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .card-header {
            padding: 1.5rem;
            background: linear-gradient(135deg, var(--cream), #f5f2ef);
            border-bottom: 1px solid var(--border-light);
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--brown);
            margin-bottom: 0.5rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Forms */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-dark);
            font-family: 'BaticaSans', sans-serif;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-light);
            border-radius: var(--radius-sm);
            font-family: 'BaticaSans', sans-serif;
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--curry);
            box-shadow: 0 0 0 3px rgba(207, 114, 58, 0.1);
        }

        .form-control[readonly] {
            background: var(--cream);
            color: var(--text-gray);
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-family: 'BaticaSans', sans-serif;
        }

        .status-open {
            background: rgba(52, 152, 219, 0.1);
            color: #3498db;
        }

        .status-in_progress {
            background: rgba(241, 196, 15, 0.1);
            color: #f39c12;
        }

        .status-resolved {
            background: rgba(46, 204, 113, 0.1);
            color: #27ae60;
        }

        .status-closed {
            background: rgba(149, 165, 166, 0.1);
            color: #95a5a6;
        }

        .priority-low {
            background: rgba(149, 165, 166, 0.1);
            color: #95a5a6;
        }

        .priority-medium {
            background: rgba(241, 196, 15, 0.1);
            color: #f39c12;
        }

        .priority-high {
            background: rgba(230, 126, 34, 0.1);
            color: #e67e22;
        }

        .priority-critical {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        /* Tables */
        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-light);
            font-family: 'BaticaSans', sans-serif;
        }

        .table th {
            background: var(--cream);
            font-weight: 600;
            color: var(--text-dark);
        }

        .table tbody tr:hover {
            background: #fafafa;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-gray);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--sage);
        }

        .empty-state h3 {
            color: var(--brown);
            font-family: 'BaticaSans', sans-serif;
        }

        /* FAQ Section */
        .faq-item {
            border-bottom: 1px solid var(--border-light);
            margin-bottom: 1rem;
        }

        .faq-question {
            padding: 1rem;
            background: transparent;
            border: none;
            width: 100%;
            text-align: left;
            font-family: 'BaticaSans', sans-serif;
            font-size: 1rem;
            font-weight: 500;
            color: var(--text-dark);
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .faq-question:hover {
            background: var(--cream);
        }

        .faq-answer {
            padding: 0 1rem 1rem;
            color: var(--text-gray);
            display: none;
            font-family: 'BaticaSans', sans-serif;
        }

        .faq-answer.show {
            display: block;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .quick-action {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            text-align: center;
            transition: var(--transition);
            cursor: pointer;
            border: 1px solid var(--border-light);
        }

        .quick-action:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-medium);
        }

        .quick-action-icon {
            font-size: 2rem;
            color: var(--curry);
            margin-bottom: 1rem;
        }

        .quick-action-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--brown);
            font-family: 'BaticaSans', sans-serif;
        }

        .quick-action-desc {
            font-size: 0.9rem;
            color: var(--text-gray);
            font-family: 'BaticaSans', sans-serif;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }

        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 2rem;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-gray);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .tabs {
                flex-direction: column;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .quick-actions {
                grid-template-columns: 1fr;
            }

            .table-responsive {
                overflow-x: auto;
            }
        }
    </style>
</head>

<!-- IMPORTANT: Add has-header class for proper spacing -->
<body class="has-header">
    <!-- The header (promo banner + navbar) is already included from header.php -->

    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Customer Support Center</h1>
            <p class="page-subtitle">Submit complaints, track issues, and get help</p>
        </div>

        <!-- Alert Messages -->
        <?php if ($success_message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($success_message) ?>
        </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($error_message) ?>
        </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <div class="quick-action" onclick="showTab('new-complaint')">
                <div class="quick-action-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="quick-action-title">Submit New Complaint</div>
                <div class="quick-action-desc">Report issues with orders or services</div>
            </div>
            
            <div class="quick-action" onclick="showTab('my-tickets')">
                <div class="quick-action-icon">
                    <i class="fas fa-ticket-alt"></i>
                </div>
                <div class="quick-action-title">Check My Complaints</div>
                <div class="quick-action-desc">View status and responses to your complaints</div>
            </div>
            
            <div class="quick-action" onclick="showTab('faq')">
                <div class="quick-action-icon">
                    <i class="fas fa-question-circle"></i>
                </div>
                <div class="quick-action-title">Frequently Asked Questions</div>
                <div class="quick-action-desc">Find answers to common questions</div>
            </div>
            
            <div class="quick-action" onclick="showTab('contact')">
                <div class="quick-action-icon">
                    <i class="fas fa-phone"></i>
                </div>
                <div class="quick-action-title">Contact Us</div>
                <div class="quick-action-desc">Contact channels and company information</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab-button active" onclick="showTab('new-complaint')">
                <i class="fas fa-plus"></i> Submit New Complaint
            </button>
            <button class="tab-button" onclick="showTab('my-tickets')">
                <i class="fas fa-list"></i> My Complaints
            </button>
            <button class="tab-button" onclick="showTab('faq')">
                <i class="fas fa-question"></i> FAQ
            </button>
            <button class="tab-button" onclick="showTab('contact')">
                <i class="fas fa-phone"></i> Contact Us
            </button>
        </div>

        <!-- Tab Content: New Complaint -->
        <div id="new-complaint" class="tab-content active">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Submit New Complaint</h3>
                    <p>Please fill out the form completely so we can assist you in the best way possible</p>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="submit_complaint">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Issue Category *</label>
                                <select name="category" class="form-control" required>
                                    <option value="">Select issue category</option>
                                    <option value="food_quality">Food Quality</option>
                                    <option value="delivery_late">Late Delivery</option>
                                    <option value="delivery_wrong">Wrong Delivery Address</option>
                                    <option value="missing_items">Missing Items</option>
                                    <option value="damaged_package">Damaged Package</option>
                                    <option value="customer_service">Customer Service</option>
                                    <option value="billing">Billing</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Priority Level *</label>
                                <select name="priority" class="form-control" required>
                                    <option value="">Select priority level</option>
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Subscription Package (if applicable)</label>
                            <select name="subscription_id" class="form-control">
                                <option value="">Not related to subscription</option>
                                <?php foreach ($subscriptions as $subscription): ?>
                                <option value="<?= $subscription['id'] ?>">
                                    <?= htmlspecialchars($subscription['plan_name']) ?> 
                                    (<?= date('m/d/Y', strtotime($subscription['start_date'])) ?> - 
                                    <?= $subscription['end_date'] ? date('m/d/Y', strtotime($subscription['end_date'])) : 'Ongoing' ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Issue Title *</label>
                            <input type="text" name="title" class="form-control" placeholder="Brief summary of the issue" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Issue Details *</label>
                            <textarea name="description" class="form-control" placeholder="Please describe the issue in detail so we can assist you appropriately" required></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Expected Resolution</label>
                            <textarea name="expected_resolution" class="form-control" placeholder="How would you like us to resolve this issue? (optional)"></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i>
                            Submit Complaint
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Tab Content: My Tickets -->
        <div id="my-tickets" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">My Complaints</h3>
                    <p>Track status and view responses to your complaints</p>
                </div>
                <div class="card-body">
                    <?php if (empty($user_complaints)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>No Complaints</h3>
                        <p>You haven't submitted any complaints yet</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Number</th>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Priority</th>
                                    <th>Date Submitted</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($user_complaints as $complaint): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($complaint['complaint_number']) ?></strong></td>
                                    <td><?= htmlspecialchars($complaint['title']) ?></td>
                                    <td>
                                        <?php
                                        $categories = [
                                            'food_quality' => 'Food Quality',
                                            'delivery_late' => 'Late Delivery',
                                            'delivery_wrong' => 'Wrong Delivery Address',
                                            'missing_items' => 'Missing Items',
                                            'damaged_package' => 'Damaged Package',
                                            'customer_service' => 'Customer Service',
                                            'billing' => 'Billing',
                                            'other' => 'Other'
                                        ];
                                        echo $categories[$complaint['category']] ?? $complaint['category'];
                                        ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= $complaint['status'] ?>">
                                            <?php
                                            $statuses = [
                                                'open' => 'Open',
                                                'in_progress' => 'In Progress',
                                                'resolved' => 'Resolved',
                                                'closed' => 'Closed',
                                                'escalated' => 'Escalated'
                                            ];
                                            echo $statuses[$complaint['status']] ?? $complaint['status'];
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge priority-<?= $complaint['priority'] ?>">
                                            <?php
                                            $priorities = [
                                                'low' => 'Low',
                                                'medium' => 'Medium',
                                                'high' => 'High',
                                                'critical' => 'Critical'
                                            ];
                                            echo $priorities[$complaint['priority']] ?? $complaint['priority'];
                                            ?>
                                        </span>
                                    </td>
                                    <td><?= date('m/d/Y h:i A', strtotime($complaint['created_at'])) ?></td>
                                    <td>
                                        <button class="btn btn-secondary" onclick="viewComplaint('<?= $complaint['id'] ?>')">
                                            <i class="fas fa-eye"></i> View Details
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tab Content: FAQ -->
        <div id="faq" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Frequently Asked Questions</h3>
                    <p>Answers to the most commonly asked questions by our customers</p>
                </div>
                <div class="card-body">
                    <div class="faq-item">
                        <button class="faq-question" onclick="toggleFAQ(this)">
                            <span>How do I order food through subscription packages?</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="faq-answer">
                            <p>You can order food through subscription packages by:</p>
                            <ol>
                                <li>Logging into your account</li>
                                <li>Selecting the desired package</li>
                                <li>Choosing menu items for each day</li>
                                <li>Setting delivery date and time</li>
                                <li>Making payment</li>
                            </ol>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button class="faq-question" onclick="toggleFAQ(this)">
                            <span>Can I change menu items after placing an order?</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="faq-answer">
                            <p>You can change menu items at least 24 hours before the delivery date by going to your subscription management page and modifying your order.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button class="faq-question" onclick="toggleFAQ(this)">
                            <span>What are the delivery charges?</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="faq-answer">
                            <p>Delivery charges depend on your location:</p>
                            <ul>
                                <li>Within major metro areas: Free for orders over $25</li>
                                <li>Other areas: $5-12 depending on distance</li>
                            </ul>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button class="faq-question" onclick="toggleFAQ(this)">
                            <span>How can I cancel my subscription?</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="faq-answer">
                            <p>You can cancel your subscription by:</p>
                            <ol>
                                <li>Going to your subscription management page</li>
                                <li>Selecting "Cancel Subscription"</li>
                                <li>Providing a reason for cancellation</li>
                                <li>Confirming the cancellation</li>
                            </ol>
                            <p>Note: Cancellation will take effect from the next billing cycle</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button class="faq-question" onclick="toggleFAQ(this)">
                            <span>What should I do if there's a problem with my food?</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="faq-answer">
                            <p>If there's a problem with your food, please:</p>
                            <ol>
                                <li>Take photos of the problematic food</li>
                                <li>Submit a complaint through our system or call us</li>
                                <li>Provide clear details about the problem</li>
                                <li>We will take action within 24 hours</li>
                            </ol>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button class="faq-question" onclick="toggleFAQ(this)">
                            <span>How do you guarantee food quality?</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="faq-answer">
                            <p>We guarantee 100% food quality through:</p>
                            <ul>
                                <li>Using high-quality, fresh ingredients</li>
                                <li>HACCP standards compliance</li>
                                <li>Quality checks at every step</li>
                                <li>Replacement or refund if issues occur</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Content: Contact -->
        <div id="contact" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Contact Us</h3>
                    <p>Contact channels and company information</p>
                </div>
                <div class="card-body">
                    <div class="form-grid">
                        <div class="card">
                            <div class="card-body" style="text-align: center;">
                                <div style="font-size: 2rem; color: var(--curry); margin-bottom: 1rem;">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <h4>Phone</h4>
                                <p style="font-size: 1.2rem; font-weight: 600; color: var(--curry);">1-800-SOMDUL</p>
                                <p style="color: var(--text-gray);">Mon-Fri 9:00 AM - 6:00 PM<br>Sat-Sun 9:00 AM - 5:00 PM</p>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-body" style="text-align: center;">
                                <div style="font-size: 2rem; color: var(--curry); margin-bottom: 1rem;">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <h4>Email</h4>
                                <p style="font-size: 1.2rem; font-weight: 600; color: var(--curry);">support@somdultable.com</p>
                                <p style="color: var(--text-gray);">Response within 24 hours</p>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-body" style="text-align: center;">
                                <div style="font-size: 2rem; color: var(--curry); margin-bottom: 1rem;">
                                    <i class="fas fa-comments"></i>
                                </div>
                                <h4>Live Chat</h4>
                                <p style="font-size: 1.2rem; font-weight: 600; color: var(--curry);">Available 24/7</p>
                                <p style="color: var(--text-gray);">Instant support on our website</p>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-body" style="text-align: center;">
                                <div style="font-size: 2rem; color: var(--curry); margin-bottom: 1rem;">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <h4>Office Address</h4>
                                <p style="color: var(--text-gray);">123 Main Street<br>Food District, NY 10001<br>United States</p>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 2rem; padding: 1.5rem; background: var(--cream); border-radius: var(--radius-md);">
                        <h4 style="margin-bottom: 1rem; color: var(--brown); font-family: 'BaticaSans', sans-serif;">Business Hours</h4>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                            <div>
                                <strong>Customer Service</strong><br>
                                Mon-Fri: 9:00 AM - 6:00 PM<br>
                                Sat-Sun: 9:00 AM - 5:00 PM
                            </div>
                            <div>
                                <strong>Food Delivery</strong><br>
                                Every day: 9:00 AM - 9:00 PM<br>
                                (Including holidays)
                            </div>
                            <div>
                                <strong>Live Chat</strong><br>
                                Available 24/7<br>
                                Instant response
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Complaint Detail Modal -->
    <div id="complaintModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Complaint Details</h3>
                <button class="modal-close" onclick="closeComplaintModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="complaintDetails">
                <!-- Complaint details will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to corresponding button
            event.target.classList.add('active');
        }

        function toggleFAQ(element) {
            const answer = element.nextElementSibling;
            const icon = element.querySelector('i');
            
            if (answer.classList.contains('show')) {
                answer.classList.remove('show');
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            } else {
                // Close all other FAQs
                document.querySelectorAll('.faq-answer').forEach(ans => {
                    ans.classList.remove('show');
                });
                document.querySelectorAll('.faq-question i').forEach(ic => {
                    ic.classList.remove('fa-chevron-up');
                    ic.classList.add('fa-chevron-down');
                });
                
                // Open clicked FAQ
                answer.classList.add('show');
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            }
        }

        function viewComplaint(complaintId) {
            // Here you would typically make an AJAX call to get complaint details
            // For now, we'll show a placeholder
            const modal = document.getElementById('complaintModal');
            const details = document.getElementById('complaintDetails');
            
            details.innerHTML = `
                <div style="text-align: center; padding: 2rem;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--curry);"></i>
                    <p style="margin-top: 1rem;">Loading information...</p>
                </div>
            `;
            
            modal.style.display = 'block';
            
            // Simulate loading delay
            setTimeout(() => {
                details.innerHTML = `
                    <div style="border-bottom: 1px solid var(--border-light); padding-bottom: 1rem; margin-bottom: 1rem;">
                        <p><strong>Complaint Number:</strong> COMP-20250721-ABC123</p>
                        <p><strong>Status:</strong> <span class="status-badge status-in_progress">In Progress</span></p>
                        <p><strong>Priority:</strong> <span class="status-badge priority-medium">Medium</span></p>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <h4>Issue Details</h4>
                        <p>Complaint details will be displayed here...</p>
                    </div>
                    <div>
                        <h4>Responses</h4>
                        <p style="color: var(--text-gray);">No responses yet</p>
                    </div>
                `;
            }, 1000);
        }

        function closeComplaintModal() {
            document.getElementById('complaintModal').style.display = 'none';
        }

        // Close modal when clicking outside
        document.getElementById('complaintModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeComplaintModal();
            }
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const category = document.querySelector('select[name="category"]').value;
            const priority = document.querySelector('select[name="priority"]').value;
            const title = document.querySelector('input[name="title"]').value.trim();
            const description = document.querySelector('textarea[name="description"]').value.trim();
            
            if (!category || !priority || !title || !description) {
                e.preventDefault();
                alert('Please fill out all required fields');
                return false;
            }
            
            if (title.length < 5) {
                e.preventDefault();
                alert('Issue title must be at least 5 characters long');
                return false;
            }
            
            if (description.length < 20) {
                e.preventDefault();
                alert('Issue details must be at least 20 characters long');
                return false;
            }
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 500);
            });
        }, 5000);

        console.log('Somdul Table Support Center loaded successfully');
    </script>
</body>
</html>