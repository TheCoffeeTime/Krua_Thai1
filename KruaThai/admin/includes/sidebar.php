<?php
/**
 * Somdul Table - Admin Sidebar Component
 * File: admin/includes/sidebar.php
 * Description: Reusable sidebar navigation for admin panel
 */

// Get current page to highlight active navigation
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <img src="../assets/image/LOGO_White Trans.png" 
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
            <a href="dashboard.php" class="nav-item <?= $current_page === 'dashboard' ? 'active' : '' ?>">
                <i class="nav-icon fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="subscriptions.php" class="nav-item <?= $current_page === 'subscriptions' ? 'active' : '' ?>">
                <i class="nav-icon fas fa-shopping-cart"></i>
                <span>Orders</span>
                <?php if (isset($pending_orders_count) && $pending_orders_count > 0): ?>
                <span class="nav-badge"><?= $pending_orders_count ?></span>
                <?php endif; ?>
            </a>
            <a href="meal-kits.php" class="nav-item <?= $current_page === 'orders' ? 'active' : '' ?>">
                <i class="nav-icon fas fa-box"></i>
                <span>Meal Kits</span>
            </a>
            <a href="menus.php" class="nav-item <?= $current_page === 'menus' ? 'active' : '' ?>">
                <i class="nav-icon fas fa-utensils"></i>
                <span>Menus</span>
            </a>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">Management</div>
            <a href="../kitchen/kitchen_dashboard.php" class="nav-item <?= $current_page === 'kitchen_dashboard' ? 'active' : '' ?>">
                <i class="nav-icon fas fa-fire"></i>
                <span>Kitchen Dashboard</span>
            </a>
            <a href="workinprogress.php" class="nav-item <?= $current_page === 'users' ? 'active' : '' ?>">
                <i class="nav-icon fas fa-users"></i>
                <span>Users</span>
            </a>
            <a href="inventory.php" class="nav-item <?= $current_page === 'inventory' ? 'active' : '' ?>">
                <i class="nav-icon fas fa-boxes"></i>
                <span>Inventory</span>
                <?php if (isset($low_stock_count) && $low_stock_count > 0): ?>
                <span class="nav-badge nav-badge-warning"><?= $low_stock_count ?></span>
                <?php endif; ?>
            </a>
            <a href="delivery-management.php" class="nav-item <?= $current_page === 'delivery-management' ? 'active' : '' ?>">
                <i class="nav-icon fas fa-truck"></i>
                <span>Delivery</span>
            </a>
            <a href="reviews.php" class="nav-item <?= $current_page === 'reviews' ? 'active' : '' ?>">
                <i class="nav-icon fas fa-star"></i>
                <span>Reviews</span>
                <?php if (isset($pending_reviews_count) && $pending_reviews_count > 0): ?>
                <span class="nav-badge nav-badge-info"><?= $pending_reviews_count ?></span>
                <?php endif; ?>
            </a>
            <a href="complaints.php" class="nav-item <?= $current_page === 'complaints' ? 'active' : '' ?>">
                <i class="nav-icon fas fa-exclamation-triangle"></i>
                <span>Complaints</span>
                <?php if (isset($open_complaints_count) && $open_complaints_count > 0): ?>
                <span class="nav-badge nav-badge-danger"><?= $open_complaints_count ?></span>
                <?php endif; ?>
            </a>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">Financial</div>
            <a href="payments.php" class="nav-item <?= $current_page === 'payments' ? 'active' : '' ?>">
                <i class="nav-icon fas fa-credit-card"></i>
                <span>Payments</span>
            </a>
            <a href="reports.php" class="nav-item <?= $current_page === 'reports' ? 'active' : '' ?>">
                <i class="nav-icon fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
            <a href="analytics.php" class="nav-item <?= $current_page === 'analytics' ? 'active' : '' ?>">
                <i class="nav-icon fas fa-chart-line"></i>
                <span>Analytics</span>
            </a>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">Content</div>
            <a href="promotional-settings.php" class="nav-item <?= $current_page === 'promotions' ? 'active' : '' ?>">
                <i class="nav-icon fas fa-percent"></i>
                <span>Promotions</span>
            </a>
            <a href="notifications.php" class="nav-item <?= $current_page === 'notifications' ? 'active' : '' ?>">
                <i class="nav-icon fas fa-bell"></i>
                <span>Notifications</span>
            </a>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">System</div>
            <a href="settings.php" class="nav-item <?= $current_page === 'settings' ? 'active' : '' ?>">
                <i class="nav-icon fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <a href="logs.php" class="nav-item <?= $current_page === 'logs' ? 'active' : '' ?>">
                <i class="nav-icon fas fa-file-alt"></i>
                <span>System Logs</span>
            </a>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">Quick Access</div>
            <a href="../index.php" class="nav-item" target="_blank">
                <i class="nav-icon fas fa-external-link-alt"></i>
                <span>View Website</span>
            </a>
            <a href="#" class="nav-item" onclick="logout()" style="color: rgba(255, 255, 255, 0.9);">
                <i class="nav-icon fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </nav>
    
    <!-- Admin Profile Section -->
    <div class="sidebar-footer">
        <div class="admin-profile">
            <div class="admin-avatar">
                <i class="fas fa-user-shield"></i>
            </div>
            <div class="admin-info">
                <div class="admin-name"><?= isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'Administrator' ?></div>
                <div class="admin-role">System Administrator</div>
            </div>
        </div>
    </div>
</div>

<!-- Mobile Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- Mobile Toggle Button -->
<button class="mobile-toggle" id="mobileToggle" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<style>
/* Sidebar Styles */
.sidebar {
    width: 280px;
    background: linear-gradient(135deg, #bd9379 0%, #cf723a 100%);
    color: #ffffff;
    position: fixed;
    height: 100vh;
    overflow-y: auto;
    z-index: 1000;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 8px 24px rgba(0,0,0,0.1);
    scrollbar-width: thin;
    scrollbar-color: rgba(255,255,255,0.3) transparent;
    display: flex;
    flex-direction: column;
}

.sidebar::-webkit-scrollbar {
    width: 6px;
}

.sidebar::-webkit-scrollbar-track {
    background: transparent;
}

.sidebar::-webkit-scrollbar-thumb {
    background-color: rgba(255,255,255,0.3);
    border-radius: 3px;
}

.sidebar::-webkit-scrollbar-thumb:hover {
    background-color: rgba(255,255,255,0.5);
}

.sidebar-header {
    padding: 2rem;
    text-align: center;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0.05));
}

.logo {
    display: flex;
    justify-content: center;
    align-items: center;
    margin-bottom: 1rem;
}

.logo-image {
    max-width: 80px;
    max-height: 80px;
    width: auto;
    height: auto;
    object-fit: contain;
    filter: brightness(1.1) contrast(1.2);
    transition: transform 0.3s ease;
    border-radius: 50%;
    background: rgba(255,255,255,0.1);
    padding: 0.5rem;
}

.logo-image:hover {
    transform: scale(1.05);
}

.sidebar-title {
    font-family: 'BaticaSans', sans-serif;
    font-size: 1.6rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
    text-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.sidebar-subtitle {
    font-size: 0.9rem;
    opacity: 0.8;
    font-weight: 400;
}

.sidebar-nav {
    padding: 1rem 0;
    flex: 1;
    overflow-y: auto;
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
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border-left: 3px solid transparent;
    cursor: pointer;
    position: relative;
}

.nav-item:hover {
    background: rgba(255, 255, 255, 0.1);
    border-left-color: #ffffff;
    color: #ffffff;
    transform: translateX(4px);
}

.nav-item.active {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.15), rgba(255, 255, 255, 0.08));
    border-left-color: #ffffff;
    font-weight: 600;
    color: #ffffff;
    box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
}

.nav-icon {
    font-size: 1.2rem;
    width: 24px;
    text-align: center;
    flex-shrink: 0;
}

.nav-badge {
    background: #e74c3c;
    color: #ffffff;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 0.2rem 0.5rem;
    border-radius: 10px;
    margin-left: auto;
    min-width: 18px;
    text-align: center;
    line-height: 1;
}

.nav-badge-warning {
    background: #f39c12;
}

.nav-badge-info {
    background: #3498db;
}

.nav-badge-danger {
    background: #e74c3c;
}

.sidebar-footer {
    margin-top: auto;
    padding: 1rem;
    background: linear-gradient(135deg, rgba(0,0,0,0.2), rgba(0,0,0,0.1));
    border-top: 1px solid rgba(255,255,255,0.1);
    flex-shrink: 0;
}

.admin-profile {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.admin-avatar {
    width: 40px;
    height: 40px;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    border: 2px solid rgba(255,255,255,0.3);
}

.admin-info {
    flex: 1;
}

.admin-name {
    font-weight: 600;
    font-size: 0.9rem;
    margin-bottom: 0.1rem;
}

.admin-role {
    font-size: 0.75rem;
    opacity: 0.8;
}

/* Mobile Sidebar */
.sidebar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999;
}

.mobile-toggle {
    display: none;
    position: fixed;
    top: 1rem;
    left: 1rem;
    z-index: 1001;
    background: #cf723a;
    color: #ffffff;
    border: none;
    width: 48px;
    height: 48px;
    border-radius: 50%;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    cursor: pointer;
    transition: all 0.3s ease;
}

.mobile-toggle:hover {
    background: #bd9379;
    transform: scale(1.05);
}

/* Responsive Design */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
    }
    
    .sidebar.show {
        transform: translateX(0);
    }
    
    .sidebar-overlay.show {
        display: block;
    }
    
    .mobile-toggle {
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .main-content {
        margin-left: 0 !important;
    }
}

@media (max-width: 480px) {
    .sidebar {
        width: 260px;
    }
    
    .sidebar-header {
        padding: 1.5rem;
    }
    
    .logo-image {
        max-width: 60px;
        max-height: 60px;
    }
    
    .sidebar-title {
        font-size: 1.4rem;
    }
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .sidebar {
        background: linear-gradient(135deg, #8b6f47 0%, #a5542a 100%);
    }
    
    .nav-item:hover {
        background: rgba(255, 255, 255, 0.15);
    }
    
    .nav-item.active {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0.1));
    }
}

/* Print styles */
@media print {
    .sidebar {
        display: none;
    }
}
</style>

<script>
// Sidebar JavaScript functionality
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    sidebar.classList.toggle('show');
    overlay.classList.toggle('show');
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(e) {
    const sidebar = document.getElementById('sidebar');
    const mobileToggle = document.getElementById('mobileToggle');
    
    if (window.innerWidth <= 768 && 
        !sidebar.contains(e.target) && 
        !mobileToggle.contains(e.target) && 
        sidebar.classList.contains('show')) {
        toggleSidebar();
    }
});

// Handle escape key to close sidebar on mobile
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && window.innerWidth <= 768) {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        if (sidebar.classList.contains('show')) {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        }
    }
});

// Simple but effective logout function
function logout() {
    if (confirm('Are you sure you want to logout?')) {
        // Show loading state
        const logoutBtn = document.querySelector('.nav-item[onclick="logout()"]');
        const originalHTML = logoutBtn.innerHTML;
        
        if (logoutBtn) {
            logoutBtn.innerHTML = '<i class="nav-icon fas fa-spinner fa-spin"></i><span>Logging out...</span>';
            logoutBtn.style.pointerEvents = 'none';
        }
        
        // Simple direct navigation to logout
        window.location.href = '../logout.php';
        
        // Fallback in case the above doesn't work
        setTimeout(() => {
            if (logoutBtn) {
                logoutBtn.innerHTML = originalHTML;
                logoutBtn.style.pointerEvents = '';
            }
            // Force redirect
            window.location.replace('../logout.php');
        }, 2000);
    }
}

// Initialize sidebar functionality
document.addEventListener('DOMContentLoaded', function() {
    // Add smooth scrolling to nav items
    document.querySelectorAll('.nav-item').forEach(item => {
        item.addEventListener('click', function(e) {
            // Add click animation
            this.style.transform = 'scale(0.98)';
            setTimeout(() => {
                this.style.transform = '';
            }, 100);
        });
    });
    
    // Auto-close sidebar on mobile when navigating
    if (window.innerWidth <= 768) {
        document.querySelectorAll('.nav-item[href]').forEach(link => {
            link.addEventListener('click', function() {
                setTimeout(() => {
                    toggleSidebar();
                }, 200);
            });
        });
    }
    
    // Add logout button hover effect
    const logoutBtn = document.querySelector('.nav-item[onclick="logout()"]');
    if (logoutBtn) {
        logoutBtn.addEventListener('mouseenter', function() {
            this.style.background = 'rgba(231, 76, 60, 0.2)';
        });
        
        logoutBtn.addEventListener('mouseleave', function() {
            this.style.background = '';
        });
    }
});

// Handle window resize
window.addEventListener('resize', function() {
    if (window.innerWidth > 768) {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
    }
});
</script>