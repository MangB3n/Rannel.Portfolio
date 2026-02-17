<?php

// Database connection is already included in the page that includes this sidebar
if (!isset($conn)) {
    require_once __DIR__ . '/database.php';
}

if (!isset($_SESSION['admin_id'])) {
    header("location: ../auth/login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];

// Get admin profile info using a separate query
$admin_sql = "SELECT * FROM admin_users WHERE id = ?";
$admin_stmt = $conn->prepare($admin_sql);
if ($admin_stmt) {
    $admin_stmt->bind_param("i", $admin_id);
    $admin_stmt->execute();
    $admin_result = $admin_stmt->get_result();
    $admin = $admin_result->fetch_assoc();
    $admin_stmt->close();
} else {
    $admin = [];
}

// Current page for active link highlighting
$current_page = basename($_SERVER['PHP_SELF']);

?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

<!-- Mobile Toggle Button -->
<button class="mobile-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
    <i class="bi bi-list"></i>
</button>

<!-- Overlay for mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<nav class="sidebar" id="sidebar">
    <div class="profile-section text-center mb-4">
        <?php 
        $profile_pic_path = "../" . $admin['profile_pictures'];
        
        if (!empty($admin['profile_pictures']) && file_exists($profile_pic_path)): 
        ?>
            <img src="<?php echo htmlspecialchars($profile_pic_path); ?>" 
                class="profile-picture rounded-circle mb-3" 
                width="80" height="80" 
                alt="Admin Profile Picture">
        <?php else: ?>
            <div class="default-profile-picture rounded-circle mb-3 mx-auto">
                <i class="bi bi-person"></i>
            </div>
        <?php endif; ?>
        <h6 class="admin-name"><?php echo htmlspecialchars($admin['username']); ?></h6>
        <p class="admin-email text-muted"><?php echo htmlspecialchars($admin['email']); ?></p>
    </div>

    <div class="sidebar-sticky">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="bi bi-speedometer2 me-2"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'booking.php' ? 'active' : ''; ?>" href="booking.php">
                    <i class="bi bi-calendar-check me-2"></i>
                    <span class="nav-text">Booking</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'customers.php' ? 'active' : ''; ?>" href="customers.php">
                    <i class="bi bi-people me-2"></i>
                    <span class="nav-text">Customers</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'tracking.php' ? 'active' : ''; ?>" href="tracking.php">
                    <i class="bi bi-geo-alt me-2"></i>
                    <span class="nav-text">Tracking</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'reports.php' ? 'active' : ''; ?>" href="reports.php">
                    <i class="bi bi-file-earmark-text me-2"></i>
                    <span class="nav-text">Reports</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'imageanalysis.php' ? 'active' : ''; ?>" href="imageanalysis.php">
                    <i class="bi bi-image me-2"></i>
                    <span class="nav-text">Image Analysis</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'livechat.php' ? 'active' : ''; ?>" href="livechat.php">
                    <i class="bi bi-chat-dots me-2"></i>
                    <span class="nav-text">Live Chat</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'profile.php' ? 'active' : ''; ?>" href="profile.php">
                    <i class="bi bi-person me-2"></i>
                    <span class="nav-text">Profile</span>
                </a>
            </li>
            <li class="nav-item mt-4">
                <a class="nav-link text-danger" href="logout.php">
                    <i class="bi bi-box-arrow-right me-2"></i>
                    <span class="nav-text">Logout</span>
                </a>
            </li>
        </ul>
    </div>
</nav>

<style>
/* Base Sidebar Styles */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 250px;
    height: 100vh;
    background: #222;
    color: #fff;
    padding-top: 20px;
    box-shadow: 2px 0 8px rgba(0, 0, 0, 0.1);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
    transition: transform 0.3s ease, width 0.3s ease;
    z-index: 1000;
    overflow-y: auto;
    overflow-x: hidden;
}

/* Scrollbar styling for sidebar */
.sidebar::-webkit-scrollbar {
    width: 6px;
}

.sidebar::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 3px;
}

.sidebar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.3);
}

/* Mobile Toggle Button */
.mobile-toggle {
    position: fixed;
    top: 15px;
    left: 15px;
    width: 45px;
    height: 45px;
    background: #222;
    color: #fff;
    border: none;
    border-radius: 8px;
    display: none;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 1001;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    transition: all 0.3s ease;
}

.mobile-toggle:hover {
    background: #333;
    transform: scale(1.05);
}

.mobile-toggle i {
    font-size: 1.5rem;
}

/* Sidebar Overlay for mobile */
.sidebar-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: none;
    z-index: 999;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.sidebar-overlay.active {
    display: block;
    opacity: 1;
}

/* Profile Section */
.profile-section {
    padding: 30px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    background: transparent;
    text-align: center;
}

.profile-picture {
    object-fit: cover;
    border: 3px solid rgba(255,255,255,0.2);
    background: transparent;
    margin-bottom: 15px;
    border-radius: 50%;
    width: 80px;
    height: 80px;
    transition: transform 0.3s ease;
}

.profile-picture:hover {
    transform: scale(1.05);
}

.default-profile-picture {
    width: 80px;
    height: 80px;
    background: transparent;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px;
    border: 2px solid rgba(255,255,255,0.2);
    border-radius: 50%;
    transition: all 0.3s ease;
}

.default-profile-picture:hover {
    border-color: rgba(255,255,255,0.4);
    transform: scale(1.05);
}

.default-profile-picture i {
    font-size: 2.5rem;
    color: rgba(255,255,255,0.6);
}

.admin-name {
    color: #fff;
    margin-bottom: 5px;
    margin-top: 0;
    font-size: 1.1rem;
    font-weight: 600;
    opacity: 1;
}

.admin-email {
    color: rgba(255,255,255,0.6);
    font-size: 0.85rem !important;
    margin-bottom: 0;
}

/* Navigation Links */
.sidebar .nav-link {
    display: flex;
    align-items: center;
    color: #fff;
    padding: 14px 20px;
    text-decoration: none;
    transition: all 0.3s ease;
    border-left: 4px solid transparent;
    margin-bottom: 0;
    font-size: 0.95rem;
    white-space: nowrap;
}

.sidebar .nav-link i {
    margin-right: 12px;
    font-size: 1.2rem;
    color: rgba(255,255,255,0.8);
    min-width: 24px;
    transition: all 0.3s ease;
}

.sidebar .nav-link:hover {
    background: rgba(13, 110, 253, 0.8);
    border-left-color: #fff;
}

.sidebar .nav-link:hover i {
    color: #fff;
    transform: scale(1.1);
}

.sidebar .nav-link.active {
    background: linear-gradient(135deg, #0a58ca 0%, #0d6efd 100%);
    border-left-color: #fff;
    font-weight: 600;
    color: #fff;
}

.sidebar .nav-link.active i {
    color: #fff;
}

.sidebar .nav-link.text-danger:hover {
    background: rgba(220, 53, 69, 0.8);
}

.sidebar-sticky {
    margin-top: 0;
    padding: 10px 0;
}

.sidebar-sticky .nav {
    padding: 0;
}

/* Desktop Main Content */
main {
    margin-left: 250px;
    padding: 40px;
    transition: margin-left 0.3s ease;
}

/* Tablet Styles (768px - 1024px) */
@media (max-width: 1024px) {
    .sidebar {
        width: 220px;
    }
    
    main {
        margin-left: 220px;
        padding: 30px;
    }
}

/* Mobile Styles (below 768px) */
@media (max-width: 768px) {
    /* Show mobile toggle button */
    .mobile-toggle {
        display: flex;
    }
    
    /* Hide sidebar by default on mobile */
    .sidebar {
        transform: translateX(-100%);
        width: 280px;
        max-width: 85vw;
    }
    
    /* Show sidebar when active */
    .sidebar.active {
        transform: translateX(0);
    }
    
    /* Adjust main content */
    main {
        margin-left: 0;
        padding: 80px 20px 20px 20px;
    }
    
    /* Adjust profile section for mobile */
    .profile-section {
        padding: 20px 15px;
    }
    
    .profile-picture,
    .default-profile-picture {
        width: 70px;
        height: 70px;
    }
    
    .default-profile-picture i {
        font-size: 2rem;
    }
    
    .admin-name {
        font-size: 1rem;
    }
    
    .admin-email {
        font-size: 0.8rem !important;
    }
    
    /* Adjust navigation for mobile */
    .sidebar .nav-link {
        padding: 12px 15px;
        font-size: 0.9rem;
    }
    
    .sidebar .nav-link i {
        font-size: 1.1rem;
    }
}

/* Small Mobile Styles (below 480px) */
@media (max-width: 480px) {
    .sidebar {
        width: 100%;
        max-width: 100vw;
    }
    
    .mobile-toggle {
        width: 40px;
        height: 40px;
        top: 10px;
        left: 10px;
    }
    
    .mobile-toggle i {
        font-size: 1.3rem;
    }
    
    main {
        padding: 70px 15px 15px 15px;
    }
}

/* Animation for smooth transitions */
@keyframes slideIn {
    from {
        transform: translateX(-100%);
    }
    to {
        transform: translateX(0);
    }
}

.sidebar.active {
    animation: slideIn 0.3s ease;
}
</style>

<script>
// Sidebar Toggle Functionality
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const navLinks = document.querySelectorAll('.sidebar .nav-link');
    
    // Toggle sidebar on button click
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
            
            // Update button icon
            const icon = this.querySelector('i');
            if (sidebar.classList.contains('active')) {
                icon.classList.remove('bi-list');
                icon.classList.add('bi-x');
            } else {
                icon.classList.remove('bi-x');
                icon.classList.add('bi-list');
            }
        });
    }
    
    // Close sidebar when clicking overlay
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
            
            // Reset button icon
            const icon = sidebarToggle.querySelector('i');
            icon.classList.remove('bi-x');
            icon.classList.add('bi-list');
        });
    }
    
    // Close sidebar when clicking a nav link on mobile
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                
                // Reset button icon
                const icon = sidebarToggle.querySelector('i');
                icon.classList.remove('bi-x');
                icon.classList.add('bi-list');
            }
        });
    });
    
    // Close sidebar on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
            
            // Reset button icon
            const icon = sidebarToggle.querySelector('i');
            icon.classList.remove('bi-x');
            icon.classList.add('bi-list');
        }
    });
    
    // Handle window resize
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth > 768) {
                // Remove mobile classes on desktop
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                
                // Reset button icon
                if (sidebarToggle) {
                    const icon = sidebarToggle.querySelector('i');
                    icon.classList.remove('bi-x');
                    icon.classList.add('bi-list');
                }
            }
        }, 250);
    });
});
</script>