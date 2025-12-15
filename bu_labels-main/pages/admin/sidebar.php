<?php
// pages/admin/sidebar.php

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
?>
<style>
/* Sidebar Styles */
.admin-sidebar {
    width: 260px;
    background: linear-gradient(180deg, #1a365d 0%, #2d4a8a 100%);
    color: var(--white);
    position: fixed;
    height: 100vh;
    overflow-y: auto;
    transition: var(--transition);
    z-index: 1000;
    box-shadow: 2px 0 8px rgba(0,0,0,0.1);
}

.sidebar-header {
    padding: 1.5rem;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    text-align: center;
    background: rgba(0,0,0,0.1);
}

.sidebar-header .logo {
    display: block;
    margin-bottom: 1rem;
    transition: var(--transition);
}

.sidebar-header .logo:hover {
    transform: scale(1.05);
}

.sidebar-header .logo img {
    height: 45px;
    width: auto;
    filter: brightness(0) invert(1);
}

.sidebar-header h3 {
    font-size: 1.1rem;
    font-weight: 600;
    color: rgba(255,255,255,0.9);
    letter-spacing: 0.5px;
}

.sidebar-menu {
    padding: 1.5rem 0;
}

.menu-section {
    margin-bottom: 1.5rem;
}

.menu-section:last-child {
    margin-bottom: 0;
}

.menu-section h4 {
    font-size: 0.75rem;
    text-transform: uppercase;
    color: rgba(255,255,255,0.6);
    padding: 0 1.5rem;
    margin-bottom: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.5px;
}

.menu-section ul {
    list-style: none;
}

.menu-section li {
    margin-bottom: 0.25rem;
    position: relative;
}

.menu-section li a {
    display: flex;
    align-items: center;
    padding: 0.75rem 1.5rem;
    color: rgba(255,255,255,0.8);
    text-decoration: none;
    transition: all 0.3s ease;
    font-weight: 500;
    position: relative;
    overflow: hidden;
}

.menu-section li a::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 0;
    background: rgba(255,255,255,0.1);
    transition: width 0.3s ease;
}

.menu-section li a:hover {
    color: var(--white);
    background: rgba(255,255,255,0.05);
}

.menu-section li a:hover::before {
    width: 4px;
}

.menu-section li.active a {
    background: rgba(255,255,255,0.1);
    color: var(--white);
    border-left: 4px solid var(--secondary);
}

.menu-section li.active a::before {
    display: none;
}

.menu-section li a i {
    margin-right: 0.75rem;
    font-size: 1.25rem;
    width: 24px;
    text-align: center;
    transition: transform 0.3s ease;
}

.menu-section li a:hover i {
    transform: scale(1.1);
}

.menu-section li a .badge {
    margin-left: auto;
    background: var(--secondary);
    color: var(--white);
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    min-width: 24px;
    text-align: center;
    font-weight: 600;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% {
        box-shadow: 0 0 0 0 rgba(237, 137, 54, 0.7);
    }
    50% {
        box-shadow: 0 0 0 6px rgba(237, 137, 54, 0);
    }
}

.menu-section li a .sub-menu-indicator {
    margin-left: auto;
    font-size: 0.875rem;
    opacity: 0.7;
    transition: transform 0.3s ease;
}

.menu-section li a:hover .sub-menu-indicator {
    transform: translateX(3px);
}

/* Sub-menu */
.sub-menu {
    display: none;
    padding-left: 2.5rem;
    background: rgba(0,0,0,0.05);
    margin-top: 0.25rem;
    border-radius: 0 0 4px 4px;
    overflow: hidden;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.menu-section li.has-submenu.active .sub-menu {
    display: block;
}

.sub-menu li {
    margin-bottom: 0;
}

.sub-menu li a {
    padding: 0.625rem 1rem;
    font-size: 0.875rem;
    border-left: 2px solid transparent;
}

.sub-menu li.active a {
    border-left-color: var(--accent);
    background: rgba(255,255,255,0.05);
}

/* Sidebar Footer */
.sidebar-footer {
    padding: 1.5rem;
    border-top: 1px solid rgba(255,255,255,0.1);
    margin-top: auto;
    background: rgba(0,0,0,0.1);
    position: sticky;
    bottom: 0;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.user-avatar {
    width: 44px;
    height: 44px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    transition: transform 0.3s ease;
}

.user-avatar:hover {
    transform: scale(1.05);
}

.user-info-content {
    flex: 1;
}

.user-info h5 {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--white);
    margin-bottom: 0.125rem;
    line-height: 1.2;
}

.user-info p {
    font-size: 0.75rem;
    color: rgba(255,255,255,0.6);
    line-height: 1.2;
}

.user-status {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    margin-top: 0.25rem;
}

.status-dot {
    width: 8px;
    height: 8px;
    background: var(--success);
    border-radius: 50%;
    animation: blink 2s infinite;
}

@keyframes blink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

/* Sidebar Collapse Button */
.sidebar-toggle {
    position: absolute;
    top: 1rem;
    right: -12px;
    width: 24px;
    height: 24px;
    background: var(--white);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    cursor: pointer;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    transition: var(--transition);
    z-index: 1001;
    display: none;
}

.sidebar-toggle:hover {
    transform: scale(1.1);
}

/* Scrollbar Styling */
.admin-sidebar::-webkit-scrollbar {
    width: 6px;
}

.admin-sidebar::-webkit-scrollbar-track {
    background: rgba(0,0,0,0.1);
}

.admin-sidebar::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.2);
    border-radius: 3px;
}

.admin-sidebar::-webkit-scrollbar-thumb:hover {
    background: rgba(255,255,255,0.3);
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .admin-sidebar {
        width: 100%;
        height: auto;
        max-height: 0;
        overflow: hidden;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 1000;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transition: max-height 0.3s ease;
    }
    
    .admin-sidebar.active {
        max-height: 80vh;
        overflow-y: auto;
    }
    
    .sidebar-toggle {
        display: flex;
        position: fixed;
        top: 1rem;
        left: 1rem;
        right: auto;
        z-index: 1002;
    }
    
    .sidebar-header {
        padding-top: 3rem;
    }
}

/* Animation for sidebar items */
@keyframes fadeInLeft {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.menu-section li {
    animation: fadeInLeft 0.3s ease forwards;
    opacity: 0;
}

.menu-section li:nth-child(1) { animation-delay: 0.1s; }
.menu-section li:nth-child(2) { animation-delay: 0.2s; }
.menu-section li:nth-child(3) { animation-delay: 0.3s; }
.menu-section li:nth-child(4) { animation-delay: 0.4s; }
.menu-section li:nth-child(5) { animation-delay: 0.5s; }
.menu-section li:nth-child(6) { animation-delay: 0.6s; }

/* Tooltip for sidebar items */
.menu-section li a .tooltip {
    position: absolute;
    left: 100%;
    top: 50%;
    transform: translateY(-50%);
    background: var(--dark);
    color: white;
    padding: 0.5rem 0.75rem;
    border-radius: 4px;
    font-size: 0.75rem;
    white-space: nowrap;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    pointer-events: none;
    z-index: 1001;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

.menu-section li a .tooltip::before {
    content: '';
    position: absolute;
    left: -6px;
    top: 50%;
    transform: translateY(-50%);
    border-width: 6px 6px 6px 0;
    border-style: solid;
    border-color: transparent var(--dark) transparent transparent;
}

.menu-section li a:hover .tooltip {
    opacity: 1;
    visibility: visible;
    left: calc(100% + 10px);
}

/* Collapsed sidebar state */
.admin-sidebar.collapsed {
    width: 70px;
}

.admin-sidebar.collapsed .sidebar-header h3,
.admin-sidebar.collapsed .menu-section h4,
.admin-sidebar.collapsed .user-info-content,
.admin-sidebar.collapsed .menu-section li a span:not(.badge):not(.sub-menu-indicator) {
    display: none;
}

.admin-sidebar.collapsed .sidebar-header {
    padding: 1rem;
}

.admin-sidebar.collapsed .sidebar-header .logo img {
    height: 35px;
}

.admin-sidebar.collapsed .menu-section li a {
    justify-content: center;
    padding: 0.75rem;
}

.admin-sidebar.collapsed .menu-section li a i {
    margin-right: 0;
    font-size: 1.5rem;
}

.admin-sidebar.collapsed .badge {
    position: absolute;
    top: 8px;
    right: 8px;
    min-width: 18px;
    height: 18px;
    font-size: 0.625rem;
    padding: 0.125rem;
}

.admin-sidebar.collapsed .user-info {
    justify-content: center;
}

.admin-sidebar.collapsed .sidebar-footer {
    padding: 1rem;
}

/* Sidebar theme variations */
.admin-sidebar.light {
    background: linear-gradient(180deg, #ffffff 0%, #f7fafc 100%);
    color: var(--dark);
}

.admin-sidebar.light .sidebar-header {
    border-bottom: 1px solid var(--gray);
}

.admin-sidebar.light .sidebar-header h3 {
    color: var(--primary);
}

.admin-sidebar.light .menu-section h4 {
    color: var(--dark-gray);
}

.admin-sidebar.light .menu-section li a {
    color: var(--dark);
}

.admin-sidebar.light .menu-section li a:hover {
    background: var(--light);
    color: var(--primary);
}

.admin-sidebar.light .menu-section li.active a {
    background: var(--light);
    color: var(--primary);
    border-left-color: var(--primary);
}

.admin-sidebar.light .sidebar-footer {
    border-top: 1px solid var(--gray);
    background: white;
}

.admin-sidebar.light .user-info h5 {
    color: var(--dark);
}

.admin-sidebar.light .user-info p {
    color: var(--dark-gray);
}

.admin-sidebar.light::-webkit-scrollbar-track {
    background: var(--gray);
}

.admin-sidebar.light::-webkit-scrollbar-thumb {
    background: var(--dark-gray);
}
</style>

<aside class="admin-sidebar">
    <!-- Sidebar Toggle Button -->
    <div class="sidebar-toggle" onclick="toggleSidebar()">
        <i class="ri-menu-line"></i>
    </div>
    
    <div class="sidebar-header">
        <a href="../../" class="logo">
            <img src="../../assets/images/logo.png" alt="BU Labels">
        </a>
        <h3>Admin Panel</h3>
    </div>
    
    <div class="sidebar-menu">
        <div class="menu-section">
            <h4>Dashboard</h4>
            <ul>
                <li class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                    <a href="dashboard.php">
                        <i class="ri-dashboard-line"></i>
                        <span>Dashboard</span>
                        <span class="tooltip">Dashboard Overview</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="menu-section">
            <h4>Products</h4>
            <ul>
                <li class="<?php echo ($current_page == 'products.php' && !isset($_GET['action'])) ? 'active' : ''; ?>">
                    <a href="products.php">
                        <i class="ri-box-3-line"></i>
                        <span>All Products</span>
                        <span class="tooltip">View All Products</span>
                    </a>
                </li>
                <li class="<?php echo (isset($_GET['action']) && $_GET['action'] == 'add') ? 'active' : ''; ?>">
                    <a href="products.php?action=add">
                        <i class="ri-add-circle-line"></i>
                        <span>Add Product</span>
                        <span class="tooltip">Add New Product</span>
                    </a>
                </li>
                <li class="<?php echo ($current_page == 'categories.php') ? 'active' : ''; ?>">
                    <a href="categories.php">
                        <i class="ri-list-check"></i>
                        <span>Categories</span>
                        <span class="tooltip">Manage Categories</span>
                    </a>
                </li>
                <li class="<?php echo ($current_page == 'inventory.php') ? 'active' : ''; ?>">
                    <a href="inventory.php">
                        <i class="ri-store-2-line"></i>
                        <span>Inventory</span>
                        <span class="tooltip">Inventory Management</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="menu-section">
            <h4>Orders</h4>
            <ul>
                <li class="<?php echo ($current_page == 'orders.php' && !isset($_GET['status'])) ? 'active' : ''; ?>">
                    <a href="orders.php">
                        <i class="ri-shopping-bag-line"></i>
                        <span>All Orders</span>
                        <?php
                        // Get pending orders count
                        require_once '../../includes/config.php';
                        $conn = getDBConnection();
                        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE order_status = 'processing'");
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $pending_count = $result->fetch_assoc()['count'] ?? 0;
                        
                        if ($pending_count > 0):
                        ?>
                        <span class="badge"><?php echo $pending_count; ?></span>
                        <?php endif; ?>
                        <span class="tooltip">View All Orders</span>
                    </a>
                </li>
                <li class="<?php echo (isset($_GET['status']) && $_GET['status'] == 'processing') ? 'active' : ''; ?>">
                    <a href="orders.php?status=processing">
                        <i class="ri-refresh-line"></i>
                        <span>Processing</span>
                        <span class="tooltip">Processing Orders</span>
                    </a>
                </li>
                <li class="<?php echo (isset($_GET['status']) && $_GET['status'] == 'ready_for_pickup') ? 'active' : ''; ?>">
                    <a href="orders.php?status=ready_for_pickup">
                        <i class="ri-checkbox-circle-line"></i>
                        <span>Ready for Pickup</span>
                        <span class="tooltip">Ready Orders</span>
                    </a>
                </li>
                <li class="<?php echo (isset($_GET['status']) && $_GET['status'] == 'picked_up') ? 'active' : ''; ?>">
                    <a href="orders.php?status=picked_up">
                        <i class="ri-truck-line"></i>
                        <span>Completed</span>
                        <span class="tooltip">Completed Orders</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="menu-section">
            <h4>Reports</h4>
            <ul>
                <li class="<?php echo ($current_page == 'reports.php' && !isset($_GET['type'])) ? 'active' : ''; ?>">
                    <a href="reports.php">
                        <i class="ri-bar-chart-line"></i>
                        <span>Sales Reports</span>
                        <span class="tooltip">Sales Analytics</span>
                    </a>
                </li>
                <li class="<?php echo (isset($_GET['type']) && $_GET['type'] == 'products') ? 'active' : ''; ?>">
                    <a href="reports.php?type=products">
                        <i class="ri-pie-chart-line"></i>
                        <span>Product Reports</span>
                        <span class="tooltip">Product Analytics</span>
                    </a>
                </li>
                <li class="<?php echo (isset($_GET['type']) && $_GET['type'] == 'campus') ? 'active' : ''; ?>">
                    <a href="reports.php?type=campus">
                        <i class="ri-building-line"></i>
                        <span>Campus Reports</span>
                        <span class="tooltip">Campus Analytics</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="menu-section">
            <h4>Users</h4>
            <ul>
                <li class="<?php echo ($current_page == 'users.php' && !isset($_GET['role'])) ? 'active' : ''; ?>">
                    <a href="users.php">
                        <i class="ri-user-line"></i>
                        <span>All Users</span>
                        <span class="tooltip">Manage Users</span>
                    </a>
                </li>
                <li class="<?php echo (isset($_GET['role']) && $_GET['role'] == 'admin') ? 'active' : ''; ?>">
                    <a href="users.php?role=admin">
                        <i class="ri-shield-user-line"></i>
                        <span>Admins</span>
                        <span class="tooltip">Admin Users</span>
                    </a>
                </li>
                <li class="<?php echo (isset($_GET['role']) && $_GET['role'] == 'director') ? 'active' : ''; ?>">
                    <a href="users.php?role=director">
                        <i class="ri-building-line"></i>
                        <span>Campus Directors</span>
                        <span class="tooltip">Campus Directors</span>
                    </a>
                </li>
                <li class="<?php echo (isset($_GET['role']) && $_GET['role'] == 'customer') ? 'active' : ''; ?>">
                    <a href="users.php?role=customer">
                        <i class="ri-user-3-line"></i>
                        <span>Customers</span>
                        <span class="tooltip">Customer Management</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="menu-section">
            <h4>System</h4>
            <ul>
                <li class="<?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>">
                    <a href="settings.php">
                        <i class="ri-settings-3-line"></i>
                        <span>Settings</span>
                        <span class="tooltip">System Settings</span>
                    </a>
                </li>
                <li>
                    <a href="../../logout.php" class="logout-link">
                        <i class="ri-logout-box-line"></i>
                        <span>Logout</span>
                        <span class="tooltip">Logout from Admin</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
    
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <?php 
                $name = sanitize($_SESSION['user']['name'] ?? 'Admin');
                echo strtoupper(substr($name, 0, 1)); 
                ?>
            </div>
            <div class="user-info-content">
                <h5><?php echo $name; ?></h5>
                <p>Administrator</p>
                <div class="user-status">
                    <span class="status-dot"></span>
                    <small>Online</small>
                </div>
            </div>
        </div>
    </div>
</aside>

<script>
// Sidebar functionality
document.addEventListener('DOMContentLoaded', function() {
    // Initialize sidebar
    initSidebar();
    
    // Add logout confirmation
    initLogoutConfirmation();
    
    // Add active state animations
    initActiveStates();
});

function initSidebar() {
    const sidebar = document.querySelector('.admin-sidebar');
    const toggleBtn = document.querySelector('.sidebar-toggle');
    
    if (!sidebar || !toggleBtn) return;
    
    // Toggle sidebar on mobile
    toggleBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        sidebar.classList.toggle('active');
        
        const icon = this.querySelector('i');
        if (sidebar.classList.contains('active')) {
            icon.classList.remove('ri-menu-line');
            icon.classList.add('ri-close-line');
        } else {
            icon.classList.remove('ri-close-line');
            icon.classList.add('ri-menu-line');
        }
    });
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768 && sidebar.classList.contains('active')) {
            if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
                sidebar.classList.remove('active');
                const icon = toggleBtn.querySelector('i');
                icon.classList.remove('ri-close-line');
                icon.classList.add('ri-menu-line');
            }
        }
    });
    
    // Handle sidebar collapse on desktop
    if (window.innerWidth > 768) {
        toggleBtn.addEventListener('dblclick', function() {
            sidebar.classList.toggle('collapsed');
            const icon = this.querySelector('i');
            if (sidebar.classList.contains('collapsed')) {
                icon.classList.remove('ri-menu-line');
                icon.classList.add('ri-arrow-right-line');
            } else {
                icon.classList.remove('ri-arrow-right-line');
                icon.classList.add('ri-menu-line');
            }
        });
    }
    
    // Auto-close sidebar on mobile when clicking a link
    const sidebarLinks = sidebar.querySelectorAll('a');
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('active');
                const icon = toggleBtn.querySelector('i');
                icon.classList.remove('ri-close-line');
                icon.classList.add('ri-menu-line');
            }
        });
    });
}

function initLogoutConfirmation() {
    const logoutLinks = document.querySelectorAll('.logout-link');
    
    logoutLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to logout?')) {
                e.preventDefault();
            }
        });
    });
}

function initActiveStates() {
    // Add active class animations
    const activeLinks = document.querySelectorAll('.menu-section li.active a');
    
    activeLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Prevent default only for active links to show animation
            if (this.parentElement.classList.contains('active')) {
                e.preventDefault();
                
                // Add click animation
                this.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    this.style.transform = '';
                }, 200);
            }
        });
    });
    
    // Add hover effect for tooltips on collapsed sidebar
    const sidebar = document.querySelector('.admin-sidebar');
    if (sidebar.classList.contains('collapsed')) {
        const menuItems = sidebar.querySelectorAll('.menu-section li a');
        
        menuItems.forEach(item => {
            item.addEventListener('mouseenter', function() {
                const tooltip = this.querySelector('.tooltip');
                if (tooltip) {
                    tooltip.style.opacity = '1';
                    tooltip.style.visibility = 'visible';
                    tooltip.style.left = 'calc(100% + 10px)';
                }
            });
            
            item.addEventListener('mouseleave', function() {
                const tooltip = this.querySelector('.tooltip');
                if (tooltip) {
                    tooltip.style.opacity = '0';
                    tooltip.style.visibility = 'hidden';
                    tooltip.style.left = '100%';
                }
            });
        });
    }
}

// Global function to toggle sidebar
function toggleSidebar() {
    const sidebar = document.querySelector('.admin-sidebar');
    const toggleBtn = document.querySelector('.sidebar-toggle');
    
    if (sidebar && toggleBtn) {
        sidebar.classList.toggle('active');
        
        const icon = toggleBtn.querySelector('i');
        if (sidebar.classList.contains('active')) {
            icon.classList.remove('ri-menu-line');
            icon.classList.add('ri-close-line');
        } else {
            icon.classList.remove('ri-close-line');
            icon.classList.add('ri-menu-line');
        }
    }
}

// Handle window resize
window.addEventListener('resize', function() {
    const sidebar = document.querySelector('.admin-sidebar');
    const toggleBtn = document.querySelector('.sidebar-toggle');
    
    if (window.innerWidth > 768) {
        // Reset mobile state
        if (sidebar && toggleBtn) {
            sidebar.classList.remove('active');
            const icon = toggleBtn.querySelector('i');
            icon.classList.remove('ri-close-line');
            icon.classList.add('ri-menu-line');
        }
    }
});

// Add keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + B to toggle sidebar
    if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
        e.preventDefault();
        toggleSidebar();
    }
    
    // Escape to close sidebar on mobile
    if (e.key === 'Escape' && window.innerWidth <= 768) {
        const sidebar = document.querySelector('.admin-sidebar');
        const toggleBtn = document.querySelector('.sidebar-toggle');
        
        if (sidebar && sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
            const icon = toggleBtn.querySelector('i');
            icon.classList.remove('ri-close-line');
            icon.classList.add('ri-menu-line');
        }
    }
});
</script>