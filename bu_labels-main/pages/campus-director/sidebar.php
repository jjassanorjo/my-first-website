<?php
// pages/campus-director/sidebar.php

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
$campus = $_SESSION['user']['campus'] ?? 'main';
?>
<aside class="admin-sidebar">
    <div class="sidebar-header">
        <a href="../../" class="logo">
            <img src="../../assets/images/logo.png" alt="BU Labels">
        </a>
        <h3>Campus Director</h3>
        <p class="campus-name"><?php echo ucfirst($campus); ?> Campus</p>
    </div>
    
    <div class="sidebar-menu">
        <div class="menu-section">
            <h4>Dashboard</h4>
            <ul>
                <li class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                    <a href="dashboard.php">
                        <i class="ri-dashboard-line"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="menu-section">
            <h4>Orders</h4>
            <ul>
                <li class="<?php echo ($current_page == 'orders.php') ? 'active' : ''; ?>">
                    <a href="orders.php">
                        <i class="ri-shopping-bag-line"></i>
                        <span>Campus Orders</span>
                        <span class="badge" id="pending-count">0</span>
                    </a>
                </li>
                <li>
                    <a href="orders.php?status=processing">
                        <i class="ri-refresh-line"></i>
                        <span>Processing</span>
                    </a>
                </li>
                <li>
                    <a href="orders.php?status=ready_for_pickup">
                        <i class="ri-checkbox-circle-line"></i>
                        <span>Ready for Pickup</span>
                    </a>
                </li>
                <li>
                    <a href="orders.php?status=picked_up">
                        <i class="ri-truck-line"></i>
                        <span>Completed</span>
                    </a>
                </li>
                <li>
                    <a href="pickup.php">
                        <i class="ri-calendar-event-line"></i>
                        <span>Pickup Schedule</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="menu-section">
            <h4>Inventory</h4>
            <ul>
                <li class="<?php echo ($current_page == 'inventory.php') ? 'active' : ''; ?>">
                    <a href="inventory.php">
                        <i class="ri-store-2-line"></i>
                        <span>Campus Inventory</span>
                    </a>
                </li>
                <li>
                    <a href="inventory.php?action=lowstock">
                        <i class="ri-alarm-warning-line"></i>
                        <span>Low Stock</span>
                    </a>
                </li>
                <li>
                    <a href="inventory.php?action=transfers">
                        <i class="ri-truck-line"></i>
                        <span>Stock Transfers</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="menu-section">
            <h4>Reports</h4>
            <ul>
                <li class="<?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
                    <a href="reports.php">
                        <i class="ri-bar-chart-line"></i>
                        <span>Campus Reports</span>
                    </a>
                </li>
                <li>
                    <a href="reports.php?type=daily">
                        <i class="ri-calendar-line"></i>
                        <span>Daily Report</span>
                    </a>
                </li>
                <li>
                    <a href="reports.php?type=weekly">
                        <i class="ri-line-chart-line"></i>
                        <span>Weekly Report</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="menu-section">
            <h4>System</h4>
            <ul>
                <li>
                    <a href="../../account.php">
                        <i class="ri-user-line"></i>
                        <span>My Account</span>
                    </a>
                </li>
                <li>
                    <a href="../../logout.php">
                        <i class="ri-logout-box-line"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
    
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <i class="ri-user-line"></i>
            </div>
            <div>
                <h5><?php echo sanitize($_SESSION['user']['name']); ?></h5>
                <p>Campus Director</p>
                <small><?php echo ucfirst($campus); ?> Campus</small>
            </div>
        </div>
    </div>
</aside>

<script>
// Update pending order count
function updatePendingCount() {
    fetch('../../api/director.php?action=pending_count&campus=<?php echo $campus; ?>')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const badge = document.getElementById('pending-count');
                if (badge) {
                    badge.textContent = data.count;
                }
            }
        });
}

// Update count on page load
document.addEventListener('DOMContentLoaded', updatePendingCount);
</script>