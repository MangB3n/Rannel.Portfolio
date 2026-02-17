<?php

session_start();
require_once('../dbconnection.php');

// Check if instructor is logged in
if (!isset($_SESSION['instructor_id'])) {
    header('Location: login.php');
    exit();
}

// Get instructor information
$instructor_id = $_SESSION['instructor_id'];
$stmt = $conn->prepare("SELECT * FROM instructors WHERE id = ?");
$stmt->bind_param("i", $instructor_id);
$stmt->execute();
$result = $stmt->get_result();
$instructor = $result->fetch_assoc();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $department = trim($_POST['department']);
    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Handle profile picture upload
    $profile_picture = $instructor['profile_picture']; // Keep existing picture by default
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_picture'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB

        if (!in_array($file['type'], $allowed_types)) {
            $error_message = "Invalid file type. Only JPG, PNG and GIF are allowed.";
        } elseif ($file['size'] > $max_size) {
            $error_message = "File is too large. Maximum size is 5MB.";
        } else {
            // Create uploads directory if it doesn't exist
            $upload_dir = "../uploads/profile_pictures/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            // Generate unique filename
            $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid("profile_") . "." . $file_ext;
            $target_file = $upload_dir . $filename;

            // Delete old profile picture if exists
            if (!empty($instructor['profile_picture'])) {
                $old_file = $upload_dir . $instructor['profile_picture'];
                if (file_exists($old_file)) {
                    unlink($old_file);
                }
            }

            if (move_uploaded_file($file['tmp_name'], $target_file)) {
                $profile_picture = $filename;
            } else {
                $error_message = "Error uploading file.";
            }
        }
    }

    if (!isset($error_message)) {
        // Update the query to include profile picture
        if (!empty($current_password)) {
            // Verify current password
            if (password_verify($current_password, $instructor['password'])) {
                if ($new_password === $confirm_password) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $query = "UPDATE instructors SET full_name = ?, email = ?, department = ?, password = ?, profile_picture = ? WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("sssssi", $full_name, $email, $department, $hashed_password, $profile_picture, $instructor_id);
                } else {
                    $error_message = "New passwords do not match.";
                }
            } else {
                $error_message = "Current password is incorrect.";
            }
        } else {
            $query = "UPDATE instructors SET full_name = ?, email = ?, department = ?, profile_picture = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssssi", $full_name, $email, $department, $profile_picture, $instructor_id);
        }

        if (isset($stmt) && $stmt->execute()) {
            $success_message = "Profile updated successfully!";
            // Refresh instructor data
            $stmt = $conn->prepare("SELECT * FROM instructors WHERE id = ?");
            $stmt->bind_param("i", $instructor_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $instructor = $result->fetch_assoc();
        } else if (!isset($error_message)) {
            $error_message = "Error updating profile.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instructor Profile</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .main-content {
            margin-left: 250px;
            padding: 30px;
            background-color: #f5f5f5;
            min-height: 100vh;
        }
        .profile-section {
            max-width: 800px;
            margin: 0 auto;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .card {
            border: none;
        }
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 15px 20px;
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100%;
            background-color: #343a40;
            color: #fff;
            padding: 20px;
        }
        .profile-picture {
            object-fit: cover;
            border: none;
            box-shadow: none;
            background: transparent;
        }

        .rounded-circle {
            background: transparent;
        }
    </style>
</head>
<body>
    <!-- Include your sidebar -->
    <?php include 'instructorsidebar.php' ?>

    <div class="main-content">
        <div class="profile-section">
            <div class="card">
                <div class="card-header">
                    <h4>My Profile</h4>
                </div>
                <div class="card-body">
                    <?php if (isset($success_message)): ?>
                        <div class="alert alert-success"><?php echo $success_message; ?></div>
                    <?php endif; ?>
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>

                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="form-group">
                            <label>Profile Picture</label>
                            <div class="d-flex align-items-center">
                                <div class="mr-3">
                                    <?php if (!empty($instructor['profile_picture'])): ?>
                                        <img src="../uploads/profile_pictures/<?php echo htmlspecialchars($instructor['profile_picture']); ?>" 
                                             class="rounded-circle profile-picture" width="100" height="100" alt="Profile Picture">
                                    <?php else: ?>
                                        <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center" 
                                             style="width: 100px; height: 100px;">
                                            <i class="fas fa-user fa-3x"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <input type="file" class="form-control-file" id="profile_picture" name="profile_picture" 
                                           accept="image/jpeg,image/png,image/gif">
                                    <small class="text-muted">Maximum file size: 5MB. Supported formats: JPG, PNG, GIF</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="full_name">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" 
                                   value="<?php echo htmlspecialchars($instructor['full_name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo isset($instructor['email']) ? htmlspecialchars($instructor['email']) : ''; ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="department">Department</label>
                            <input type="text" class="form-control" id="department" name="department" 
                                   value="<?php echo htmlspecialchars($instructor['department'] ?? ''); ?>">
                        </div>

                        <hr>
                        <h5>Change Password</h5>
                        <small class="text-muted">Leave blank if you don't want to change your password</small>

                        <div class="form-group mt-3">
                            <label for="current_password">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password">
                        </div>

                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" 
                                   minlength="6">
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                        </div>

                        <button type="submit" name="update_profile" class="btn btn-primary">
                            Update Profile
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>