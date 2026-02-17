<?php

// Check if session is not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['instructor_id'])) {
    header('Location: ilogin.php');
    exit();
}

// Get current page name
$current_page = basename($_SERVER['PHP_SELF']);

// Get instructor profile information
require_once('../dbconnection.php');
$instructor_id = $_SESSION['instructor_id'];
$stmt = $conn->prepare("SELECT full_name, profile_picture FROM instructors WHERE id = ?");
$stmt->bind_param("i", $instructor_id);
$stmt->execute();
$instructor = $stmt->get_result()->fetch_assoc();
?>

<nav class="sidebar">
    <div class="profile-section text-center mb-4">
        <?php if (!empty($instructor['profile_picture'])): ?>
            <img src="../uploads/profile_pictures/<?php echo htmlspecialchars($instructor['profile_picture']); ?>" 
                 class="profile-picture rounded-circle mb-3" 
                 width="80" height="80" 
                 alt="Profile Picture">
        <?php else: ?>
            <div class="default-profile-picture rounded-circle mb-3 mx-auto">
                <i class="fas fa-user"></i>
            </div>
        <?php endif; ?>
        <h6 class="instructor-name"><?php echo htmlspecialchars($instructor['full_name']); ?></h6>
    </div>

    <div class="sidebar-sticky">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'idashboard.php' ? 'active' : ''; ?>" 
                   href="idashboard.php">
                    <i class="fas fa-home mr-2"></i>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'manage_classes.php' ? 'active' : ''; ?>" 
                   href="manageclasses.php">
                    <i class="fas fa-book mr-2"></i>
                    Manage Classes
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'attendancereports.php' ? 'active' : ''; ?>" 
                   href="attendancereports.php">
                    <i class="fas fa-chart-bar mr-2"></i>
                    Attendance Reports
                </a>
            </li>
           
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'profile.php' ? 'active' : ''; ?>" 
                   href="instructorprofile.php">
                    <i class="fas fa-user mr-2"></i>
                    Profile
                </a>
            </li>
           
            <li class="nav-item mt-4">
                <a class="nav-link text-danger" href="ilogout.php">
                    <i class="fas fa-sign-out-alt mr-2"></i>
                    Logout
                </a>
            </li>
        </ul>
    </div>
</nav>

<style>
.profile-section {
    padding: 20px 0;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    background: transparent;
}

.profile-picture {
    object-fit: cover;
    border: none;
    box-shadow: none;
    background: transparent;
}

.default-profile-picture {
    width: 80px;
    height: 80px;
    background: transparent;
    display: flex;
    align-items: center;
    justify-content: center;
}

.default-profile-picture i {
    font-size: 2rem;
    color: rgba(255,255,255,0.5);
}

.instructor-name {
    color: #fff;
    margin-bottom: 0;
    font-size: 1rem;
    font-weight: 500;
    opacity: 0.9;
}
</style>