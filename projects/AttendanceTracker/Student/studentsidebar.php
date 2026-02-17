<?php
// Check if session is not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check session timeout (30 minutes)
$timeout = 1800; // 30 minutes in seconds
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    session_unset();
    session_destroy();
    header("Location: slogin.php?msg=timeout");
    exit();
}

// Update last activity time
$_SESSION['last_activity'] = time();

// Check if user is logged in and is a student
if (!isset($_SESSION['student_id']) || $_SESSION['user_type'] !== 'student') {
    header('Location: slogin.php');
    exit();
}

// Get student profile information
require_once('../dbconnection.php');
$student_id = $_SESSION['student_id'];
$stmt = $conn->prepare("SELECT full_name, profile_picture FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

// Get current page name
$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav class="sidebar">
    <div class="profile-section text-center mb-4">
        <?php if (!empty($student['profile_picture'])): ?>
            <img src="../uploads/profile_pictures/<?php echo htmlspecialchars($student['profile_picture']); ?>" 
                 class="profile-picture rounded-circle mb-3" 
                 width="80" height="80" 
                 alt="Profile Picture">
        <?php else: ?>
            <div class="default-profile-picture rounded-circle mb-3 mx-auto">
                <i class="fas fa-user"></i>
            </div>
        <?php endif; ?>
        <h6 class="student-name"><?php echo htmlspecialchars($student['full_name']); ?></h6>
    </div>

    <div class="sidebar-sticky">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'sdashboard.php' ? 'active' : ''; ?>" 
                   href="sdashboard.php">
                    <i class="fas fa-home mr-2"></i>
                    Dashboard
                </a>
            </li>
    
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'logattendance.php' ? 'active' : ''; ?>" 
                   href="logattendance.php">
                    <i class="fas fa-clipboard-check mr-2"></i>
                    Log Attendance
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'enroll_class.php' ? 'active' : ''; ?>" 
                   href="enrollclass.php">
                    <i class="fas fa-plus-square mr-2"></i>
                    Enroll Class
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'attendance_history.php' ? 'active' : ''; ?>" 
                   href="viewrecords.php">
                    <i class="fas fa-history mr-2"></i>
                    Attendance History
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'studentprofile.php' ? 'active' : ''; ?>" 
                   href="studentprofile.php">
                    <i class="fas fa-user mr-2"></i>
                    Profile
                </a>
            </li>
            <li class="nav-item mt-4">
                <a class="nav-link text-danger" href="logout.php">
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

.student-name {
    color: #fff;
    margin-bottom: 0;
    font-size: 1rem;
    font-weight: 500;
    opacity: 0.9;
}
</style>