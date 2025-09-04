<?php
/**
 * Somdul Table - Simple Meal-Kits Management Page with Multi-Select
 * File: admin/meal-kits.php
 * Description: Display all orders from product_orders table with sidebar and bulk status updates
 */
session_start();

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php"); 
    exit();
}

// Use the same database connection approach as other working pages
require_once '../config/database.php';

try {
    // Create database instance like other working pages
    $database = new Database();
    $pdo = $database->getConnection();
} catch (Exception $e) {
    // Fallback database connections
    $configs = [
        ["mysql:host=localhost;dbname=somdul_table;charset=utf8mb4", "root", "root"],
        ["mysql:host=localhost:8889;dbname=somdul_table;charset=utf8mb4", "root", "root"]
    ];
    
    $pdo = null;
    foreach ($configs as $config) {
        try {
            $pdo = new PDO($config[0], $config[1], $config[2]);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            break;
        } catch (PDOException $e) {
            continue;
        }
    }
    
    if ($pdo === null) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// Fetch all orders
try {
    $stmt = $pdo->prepare("
        SELECT * FROM product_orders 
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get order count
    $total_orders = count($orders);
    
    // Calculate stats
    $total_revenue = array_sum(array_column($orders, 'total_amount'));
    $pending_orders = count(array_filter($orders, function($order) {
        return $order['status'] === 'pending';
    }));
    $paid_orders = count(array_filter($orders, function($order) {
        return $order['status'] === 'paid';
    }));
    $shipped_orders = count(array_filter($orders, function($order) {
        return $order['status'] === 'shipped';
    }));
    
} catch (Exception $e) {
    $orders = [];
    $total_orders = 0;
    $total_revenue = 0;
    $pending_orders = 0;
    $paid_orders = 0;
    $shipped_orders = 0;
    $error_message = "Error fetching orders: " . $e->getMessage();
}

// Handle single order status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    try {
        $order_id = $_POST['order_id'];
        $new_status = $_POST['status'];
        
        $stmt = $pdo->prepare("UPDATE product_orders SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_status, $order_id]);
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } catch (Exception $e) {
        $update_error = "Error updating order: " . $e->getMessage();
    }
}

// Handle bulk status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_update_status'])) {
    try {
        $order_ids = $_POST['selected_orders'] ?? [];
        $new_status = $_POST['bulk_status'];
        
        if (empty($order_ids)) {
            $bulk_error = "No orders selected for update.";
        } else {
            // Convert array to comma-separated string for IN clause
            $placeholders = str_repeat('?,', count($order_ids) - 1) . '?';
            $stmt = $pdo->prepare("UPDATE product_orders SET status = ?, updated_at = NOW() WHERE id IN ($placeholders)");
            
            // Prepare parameters: status first, then all order IDs
            $params = array_merge([$new_status], $order_ids);
            $stmt->execute($params);
            
            $updated_count = $stmt->rowCount();
            $bulk_success = "Successfully updated {$updated_count} order(s) to '{$new_status}' status.";
            
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    } catch (Exception $e) {
        $bulk_error = "Error updating orders: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meal-Kit Orders - Somdul Table Admin</title>
    <link href="https://ydpschool.com/fonts/BaticaSans.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            /* Somdul Table Color Hierarchy */
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
            font-family: 'BaticaSans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, var(--cream) 0%, #f8f6f3 100%);
            color: var(--text-dark);
            line-height: 1.6;
        }

        /* Main Content - Account for sidebar */
        .main-content {
            margin-left: 280px;
            flex: 1;
            padding: 2rem;
            transition: var(--transition);
        }

        .page-header {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            margin-bottom: 2rem;
            border: 1px solid var(--border-light);
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--brown);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: var(--text-gray);
            font-size: 1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
            position: relative;
            overflow: hidden;
            transition: var(--transition);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--curry), var(--brown));
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-medium);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--white);
            background: var(--curry);
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: var(--text-gray);
            font-size: 0.9rem;
        }

        .orders-container {
            background: var(--white);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
            overflow: hidden;
        }

        .orders-header {
            background: var(--cream);
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-light);
        }

        .orders-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Bulk Actions Bar */
        .bulk-actions-bar {
            background: var(--curry);
            color: var(--white);
            padding: 1rem 1.5rem;
            display: none;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            border-bottom: 1px solid var(--border-light);
        }

        .bulk-actions-bar.show {
            display: flex;
        }

        .selected-count {
            font-weight: 600;
        }

        .bulk-actions-form {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .bulk-status-select {
            padding: 0.5rem;
            border: none;
            border-radius: var(--radius-sm);
            font-family: inherit;
        }

        .table-container {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-light);
            vertical-align: middle;
        }

        .table th {
            background: var(--cream);
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table tbody tr:hover {
            background: #fafafa;
        }

        .table tbody tr.selected {
            background: rgba(189, 147, 121, 0.1);
        }

        /* Checkbox styling */
        .checkbox-cell {
            width: 40px;
            text-align: center;
        }

        .order-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--curry);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending {
            background: rgba(241, 196, 15, 0.1);
            color: #f39c12;
        }

        .status-paid {
            background: rgba(52, 152, 219, 0.1);
            color: #3498db;
        }

        .status-processing {
            background: rgba(155, 89, 182, 0.1);
            color: #9b59b6;
        }

        .status-shipped {
            background: rgba(46, 204, 113, 0.1);
            color: #27ae60;
        }

        .status-delivered {
            background: rgba(46, 204, 113, 0.1);
            color: #27ae60;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .btn-primary {
            background: var(--curry);
            color: var(--white);
        }

        .btn-primary:hover {
            background: #b8631e;
            transform: translateY(-2px);
        }

        .btn-success {
            background: var(--success);
            color: var(--white);
        }

        .btn-warning {
            background: var(--warning);
            color: var(--white);
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.75rem;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-gray);
        }

        .empty-icon {
            font-size: 4rem;
            margin-bottom: 2rem;
            opacity: 0.3;
            color: var(--curry);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: var(--white);
            border-radius: var(--radius-lg);
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-medium);
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-gray);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-sm);
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--curry);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .info-card {
            background: var(--cream);
            padding: 1rem;
            border-radius: var(--radius-sm);
            border-left: 4px solid var(--curry);
        }

        .info-label {
            font-size: 0.8rem;
            color: var(--text-gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }

        .info-value {
            font-weight: 600;
            color: var(--text-dark);
        }

        .alert {
            padding: 1rem;
            border-radius: var(--radius-sm);
            margin-bottom: 1rem;
        }

        .alert-error {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
            border: 1px solid #ffcdd2;
        }

        .alert-success {
            background: rgba(39, 174, 96, 0.1);
            color: #27ae60;
            border: 1px solid #c8e6c9;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .table th,
            .table td {
                padding: 0.5rem;
                font-size: 0.8rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .bulk-actions-bar {
                flex-direction: column;
                align-items: stretch;
                gap: 1rem;
            }

            .bulk-actions-form {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Include Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">Meal-Kit Orders Management</h1>
                <p class="page-subtitle">View and manage all meal-kit orders from customers</p>
            </div>

            <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
            <?php endif; ?>

            <?php if (isset($update_error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($update_error); ?>
            </div>
            <?php endif; ?>

            <?php if (isset($bulk_error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($bulk_error); ?>
            </div>
            <?php endif; ?>

            <?php if (isset($bulk_success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($bulk_success); ?>
            </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_orders); ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: var(--warning);">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($pending_orders); ?></div>
                    <div class="stat-label">Pending Orders</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: var(--success);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($paid_orders); ?></div>
                    <div class="stat-label">Paid Orders</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: var(--sage);">
                            <i class="fas fa-truck"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($shipped_orders); ?></div>
                    <div class="stat-label">Shipped Orders</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: var(--brown);">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                    </div>
                    <div class="stat-value">$<?php echo number_format($total_revenue, 2); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
            </div>

            <!-- Orders Table -->
            <div class="orders-container">
                <div class="orders-header">
                    <h3 class="orders-title">
                        <i class="fas fa-box" style="color: var(--curry);"></i>
                        All Meal-Kit Orders (<?php echo number_format($total_orders); ?> total)
                    </h3>
                </div>

                <!-- Bulk Actions Bar -->
                <div class="bulk-actions-bar" id="bulkActionsBar">
                    <div class="selected-count" id="selectedCount">0 orders selected</div>
                    <form method="POST" action="" class="bulk-actions-form">
                        <label for="bulk_status" style="color: var(--white); font-weight: 500;">Change status to:</label>
                        <select name="bulk_status" id="bulk_status" class="bulk-status-select" required>
                            <option value="">Select Status</option>
                            <option value="pending">Pending</option>
                            <option value="paid">Paid</option>
                            <option value="processing">Processing</option>
                            <option value="shipped">Shipped</option>
                            <option value="delivered">Delivered</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                        <button type="submit" name="bulk_update_status" class="btn" style="background: var(--white); color: var(--curry);">
                            <i class="fas fa-save"></i>
                            Update Selected
                        </button>
                        <div id="selectedOrderIds"></div>
                    </form>
                </div>

                <?php if (!empty($orders)): ?>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="checkbox-cell">
                                    <input type="checkbox" id="selectAll" class="order-checkbox" title="Select All">
                                </th>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                            <tr class="order-row" data-order-id="<?php echo $order['id']; ?>">
                                <td class="checkbox-cell">
                                    <input type="checkbox" class="order-checkbox individual-checkbox" 
                                           value="<?php echo $order['id']; ?>" 
                                           data-order-id="<?php echo $order['id']; ?>">
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($order['customer_name'] ?: 'N/A'); ?></strong>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($order['customer_email'] ?: 'N/A'); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($order['customer_phone'] ?: 'N/A'); ?>
                                </td>
                                <td>
                                    <strong style="color: var(--curry);">
                                        $<?php echo number_format($order['total_amount'] ?: 0, 2); ?>
                                    </strong>
                                    <?php if ($order['subtotal'] && $order['subtotal'] != $order['total_amount']): ?>
                                    <div style="font-size: 0.8rem; color: var(--text-gray);">
                                        Subtotal: $<?php echo number_format($order['subtotal'], 2); ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $order['status'] ?: 'pending'; ?>">
                                        <i class="fas fa-circle"></i>
                                        <?php echo ucfirst($order['status'] ?: 'pending'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $order['payment_status'] ?: 'pending'; ?>">
                                        <i class="fas fa-circle"></i>
                                        <?php echo ucfirst($order['payment_status'] ?: 'pending'); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($order['created_at']): ?>
                                        <?php echo date('M d, Y', strtotime($order['created_at'])); ?>
                                        <div style="font-size: 0.8rem; color: var(--text-gray);">
                                            <?php echo date('g:i A', strtotime($order['created_at'])); ?>
                                        </div>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                        <button class="btn btn-primary btn-sm" 
                                                onclick="viewOrder('<?php echo $order['id']; ?>')">
                                            <i class="fas fa-eye"></i>
                                            View
                                        </button>
                                        <button class="btn btn-warning btn-sm" 
                                                onclick="updateStatus('<?php echo $order['id']; ?>')">
                                            <i class="fas fa-edit"></i>
                                            Update
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-box empty-icon"></i>
                    <h3>No Orders Found</h3>
                    <p>No meal-kit orders have been placed yet.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- View Order Modal -->
    <div class="modal" id="viewOrderModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Order Details</h3>
                <button class="modal-close" onclick="closeModal('viewOrderModal')">&times;</button>
            </div>
            <div class="modal-body" id="orderDetails">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div class="modal" id="updateStatusModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Update Order Status</h3>
                <button class="modal-close" onclick="closeModal('updateStatusModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="order_id" id="updateOrderId">
                    
                    <div class="form-group">
                        <label class="form-label">Order Status</label>
                        <select name="status" class="form-control" required>
                            <option value="pending">Pending</option>
                            <option value="paid">Paid</option>
                            <option value="processing">Processing</option>
                            <option value="shipped">Shipped</option>
                            <option value="delivered">Delivered</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                        <button type="button" class="btn" onclick="closeModal('updateStatusModal')" 
                                style="background: var(--text-gray); color: var(--white);">
                            Cancel
                        </button>
                        <button type="submit" name="update_status" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Update Status
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Multi-select functionality
        let selectedOrders = [];

        // Select all checkbox handler
        document.getElementById('selectAll').addEventListener('change', function() {
            const individualCheckboxes = document.querySelectorAll('.individual-checkbox');
            const isChecked = this.checked;
            
            individualCheckboxes.forEach(checkbox => {
                checkbox.checked = isChecked;
                updateRowSelection(checkbox);
            });
            
            updateSelectedOrders();
        });

        // Individual checkbox handlers
        document.addEventListener('DOMContentLoaded', function() {
            const individualCheckboxes = document.querySelectorAll('.individual-checkbox');
            
            individualCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    updateRowSelection(this);
                    updateSelectedOrders();
                    
                    // Update select all checkbox
                    const allCheckboxes = document.querySelectorAll('.individual-checkbox');
                    const checkedCheckboxes = document.querySelectorAll('.individual-checkbox:checked');
                    const selectAllCheckbox = document.getElementById('selectAll');
                    
                    if (checkedCheckboxes.length === 0) {
                        selectAllCheckbox.indeterminate = false;
                        selectAllCheckbox.checked = false;
                    } else if (checkedCheckboxes.length === allCheckboxes.length) {
                        selectAllCheckbox.indeterminate = false;
                        selectAllCheckbox.checked = true;
                    } else {
                        selectAllCheckbox.indeterminate = true;
                        selectAllCheckbox.checked = false;
                    }
                });
            });
        });

        function updateRowSelection(checkbox) {
            const row = checkbox.closest('tr');
            if (checkbox.checked) {
                row.classList.add('selected');
            } else {
                row.classList.remove('selected');
            }
        }

        function updateSelectedOrders() {
            const checkedCheckboxes = document.querySelectorAll('.individual-checkbox:checked');
            selectedOrders = Array.from(checkedCheckboxes).map(cb => cb.value);
            
            const bulkActionsBar = document.getElementById('bulkActionsBar');
            const selectedCount = document.getElementById('selectedCount');
            const selectedOrderIds = document.getElementById('selectedOrderIds');
            
            if (selectedOrders.length > 0) {
                bulkActionsBar.classList.add('show');
                selectedCount.textContent = `${selectedOrders.length} order${selectedOrders.length === 1 ? '' : 's'} selected`;
                
                // Add hidden inputs for selected order IDs
                selectedOrderIds.innerHTML = selectedOrders.map(id => 
                    `<input type="hidden" name="selected_orders[]" value="${id}">`
                ).join('');
            } else {
                bulkActionsBar.classList.remove('show');
                selectedOrderIds.innerHTML = '';
            }
        }

        // View order details
        function viewOrder(orderId) {
            <?php 
            // Create JavaScript object with order data
            echo "const orders = " . json_encode($orders) . ";";
            ?>
            
            const order = orders.find(o => o.id === orderId);
            if (!order) {
                alert('Order not found');
                return;
            }

            const orderDetails = document.getElementById('orderDetails');
            orderDetails.innerHTML = `
                <div class="info-grid">
                    <div class="info-card">
                        <div class="info-label">Order Number</div>
                        <div class="info-value">${order.order_number || 'N/A'}</div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Customer Name</div>
                        <div class="info-value">${order.customer_name || 'N/A'}</div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Email</div>
                        <div class="info-value">${order.customer_email || 'N/A'}</div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Phone</div>
                        <div class="info-value">${order.customer_phone || 'N/A'}</div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Total Amount</div>
                        <div class="info-value" style="color: var(--curry);">$${parseFloat(order.total_amount || 0).toFixed(2)}</div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Status</div>
                        <div class="info-value">
                            <span class="status-badge status-${order.status || 'pending'}">
                                ${(order.status || 'pending').charAt(0).toUpperCase() + (order.status || 'pending').slice(1)}
                            </span>
                        </div>
                    </div>
                </div>

                <h4 style="margin: 2rem 0 1rem 0; color: var(--curry);">
                    <i class="fas fa-truck"></i> Shipping Information
                </h4>
                <div style="background: var(--cream); padding: 1rem; border-radius: var(--radius-sm);">
                    <div><strong>Address Line 1:</strong> ${order.shipping_address_line1 || 'Not provided'}</div>
                    ${order.shipping_address_line2 ? `<div><strong>Address Line 2:</strong> ${order.shipping_address_line2}</div>` : ''}
                    <div><strong>City:</strong> ${order.shipping_city || 'N/A'}</div>
                    <div><strong>State:</strong> ${order.shipping_state || 'N/A'}</div>
                    <div><strong>ZIP:</strong> ${order.shipping_zip || 'N/A'}</div>
                    <div><strong>Country:</strong> ${order.shipping_country || 'N/A'}</div>
                </div>

                <h4 style="margin: 2rem 0 1rem 0; color: var(--curry);">
                    <i class="fas fa-clock"></i> Order Timeline
                </h4>
                <div style="background: var(--cream); padding: 1rem; border-radius: var(--radius-sm);">
                    <div><strong>Created:</strong> ${order.created_at ? new Date(order.created_at).toLocaleString() : 'N/A'}</div>
                    <div><strong>Updated:</strong> ${order.updated_at ? new Date(order.updated_at).toLocaleString() : 'N/A'}</div>
                    ${order.shipped_at ? `<div><strong>Shipped:</strong> ${new Date(order.shipped_at).toLocaleString()}</div>` : ''}
                    ${order.delivered_at ? `<div><strong>Delivered:</strong> ${new Date(order.delivered_at).toLocaleString()}</div>` : ''}
                </div>
            `;

            document.getElementById('viewOrderModal').classList.add('show');
        }

        // Update order status
        function updateStatus(orderId) {
            document.getElementById('updateOrderId').value = orderId;
            document.getElementById('updateStatusModal').classList.add('show');
        }

        // Close modal
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('show');
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal.show').forEach(modal => {
                    modal.classList.remove('show');
                });
            }
        });

        console.log('Meal-Kits page with multi-select functionality loaded successfully');
        console.log('Total orders found:', <?php echo $total_orders; ?>);
    </script>
</body>
</html>