<?php
session_start();
require_once '../includes/database.php';
$admin_id = $_SESSION['admin_id'];

// Fetch admin info
$stmt = $conn->prepare("SELECT * FROM admin_users WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $new_password = trim($_POST['password']);
    $profile_pictures = $admin['profile_pictures']; // keep old image by default

    // Handle image upload
    if (isset($_FILES['profile_pictures']) && $_FILES['profile_pictures']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB

        $file = $_FILES['profile_pictures'];

        if (!in_array($file['type'], $allowed_types)) {
            $error_message = "Invalid file type. Only JPG, PNG, and GIF allowed.";
        } elseif ($file['size'] > $max_size) {
            $error_message = "File is too large. Maximum 5MB allowed.";
        } else {
            $upload_dir = "../uploads/profile_pictures/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid("admin_") . "." . $file_ext;
            $target_file = $upload_dir . $filename;

            // Delete old image if exists
            if (!empty($admin['profile_pictures']) && file_exists("../" . $admin['profile_pictures'])) {
                unlink("../" . $admin['profile_pictures']);
            }

            if (move_uploaded_file($file['tmp_name'], $target_file)) {
                // Store relative path from root (without ../)
                $profile_pictures = "uploads/profile_pictures/" . $filename;
                $success_message = "Image uploaded successfully.";
            } else {
                $error_message = "Error uploading file. Check folder permissions.";
            }
        }
    }

    if (!isset($error_message)) {
        // Update password only if filled
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $sql = "UPDATE admin_users SET username=?, email=?, password=?, profile_pictures=? WHERE id=?";
            $update = $conn->prepare($sql);
            if (!$update) {
                $error_message = "Prepare failed: " . $conn->error;
            } else {
                $update->bind_param("ssssi", $username, $email, $hashed_password, $profile_pictures, $admin_id);
            }
        } else {
            $sql = "UPDATE admin_users SET username=?, email=?, profile_pictures=? WHERE id=?";
            $update = $conn->prepare($sql);
            if (!$update) {
                $error_message = "Prepare failed: " . $conn->error;
            } else {
                $update->bind_param("sssi", $username, $email, $profile_pictures, $admin_id);
            }
        }

        if (!isset($error_message) && $update->execute()) {
            if (!isset($success_message)) {
                $success_message = "Profile updated successfully!";
            } else {
                $success_message .= " Profile data saved!";
            }
            
            // Refresh data
            $stmt = $conn->prepare("SELECT * FROM admin_users WHERE id = ?");
            $stmt->bind_param("i", $admin_id);
            $stmt->execute();
            $admin = $stmt->get_result()->fetch_assoc();
        } elseif (!isset($error_message)) {
            $error_message = "Error updating profile: " . $update->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - iShoeKicks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #2b2b2b;
            color: #d4d4d4;
        }

        .main-content {
            margin-left: 250px; /* Aligned with typical sidebar width */
            margin-top: 50px;
            padding: 35px 40px;
            min-height: 100vh;
        }

        h3 {
            font-size: 1.75rem;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 20px;
        }

        /* CARD STYLING (Matches Dashboard) */
        .profile-card {
            background: #3a3a3a;
            border: 1px solid rgba(181, 142, 83, 0.2);
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            padding: 40px;
            max-width: 700px;
            margin: 0 auto; /* Center the card */
            animation: slideUp 0.5s ease-out forwards;
        }

        /* PROFILE IMAGE */
        .profile-img-container {
            position: relative;
            display: inline-block;
            margin-bottom: 25px;
        }

        .profile-card img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #B58E53; /* Gold border */
            box-shadow: 0 4px 15px rgba(0,0,0,0.5);
            transition: transform 0.3s ease;
        }

        .profile-card img:hover {
            transform: scale(1.05);
        }

        /* FORM ELEMENTS (Dark Theme) */
        .form-label {
            color: #B58E53; /* Gold labels */
            font-weight: 500;
            margin-bottom: 8px;
        }

        .form-control {
            background-color: #2b2b2b;
            border: 1px solid #4a4a4a;
            color: #ffffff;
            border-radius: 8px;
            padding: 12px 15px;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            background-color: #2b2b2b;
            border-color: #B58E53;
            color: #ffffff;
            box-shadow: 0 0 0 3px rgba(181, 142, 83, 0.1);
        }

        /* File Input Styling */
        input[type="file"] {
            background: #2b2b2b;
            color: #d4d4d4;
        }
        input[type="file"]::file-selector-button {
            background-color: #4a4a4a;
            color: #fff;
            border: none;
            padding: 8px 12px;
            margin-right: 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: background .2s;
        }
        input[type="file"]::file-selector-button:hover {
            background-color: #B58E53;
            color: #000;
        }

        /* BUTTONS */
        .btn-save {
            background: linear-gradient(135deg, #B58E53 0%, #D4A574 100%);
            border: none;
            color: #ffffff;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            width: 100%;
            margin-top: 10px;
            transition: all 0.2s ease;
        }

        .btn-save:hover {
            background: linear-gradient(135deg, #D4A574 0%, #B58E53 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(181, 142, 83, 0.4);
            color: #ffffff;
        }

        .text-muted {
            color: #999 !important;
        }

        /* ALERTS */
        .alert-success {
            background-color: rgba(25, 135, 84, 0.2);
            border-color: #198754;
            color: #75b798;
        }
        
        .alert-danger {
            background-color: rgba(220, 53, 69, 0.2);
            border-color: #dc3545;
            color: #ea868f;
        }

        /* Placeholder Text Color */
        .form-control::placeholder {
            color: #898989 !important;
            opacity: 1; /* Ensures the color is vibrant on Firefox */
        }

        /* ANIMATION */
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        
        <div class="profile-card">
            <div class="text-center mb-4">
                <h3 class="mb-4">My Profile</h3>
                
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success fade show mb-4" role="alert">
                        <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger fade show mb-4" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <?php
                $image_path = '../uploads/profile_pictures/default.png';
                if (!empty($admin['profile_pictures'])) {
                    $check_path = "../" . $admin['profile_pictures'];
                    if (file_exists($check_path)) {
                        $image_path = $check_path;
                    }
                }
                ?>
                <div class="profile-img-container">
                    <img src="<?= htmlspecialchars($image_path); ?>" alt="Profile Picture">
                </div>
                <p class="text-white mb-0 fw-bold"><?= htmlspecialchars($admin['username']); ?></p>
                <small class="text-muted">Administrator</small>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <div class="mb-4">
                    <label class="form-label"><i class="fas fa-user me-2"></i>Username</label>
                    <input type="text" name="username" value="<?= htmlspecialchars($admin['username']); ?>" class="form-control" required>
                </div>

                <div class="mb-4">
                    <label class="form-label"><i class="fas fa-envelope me-2"></i>Email Address</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($admin['email']); ?>" class="form-control" required>
                </div>

                <div class="mb-4">
                    <label class="form-label"><i class="fas fa-lock me-2"></i>New Password</label>
                    <input type="password" name="password" placeholder="Leave blank to keep current password" class="form-control" >
                </div>

                <div class="mb-4">
                    <label class="form-label"><i class="fas fa-image me-2"></i>Profile Picture</label>
                    <input type="file" name="profile_pictures" class="form-control" accept="image/*">
                    <div class="form-text text-muted mt-2"><i class="fas fa-info-circle"></i> Max 5MB. Formats: JPG, PNG, GIF</div>
                </div>

                <button type="submit" class="btn btn-save">
                    <i class="fas fa-save me-2"></i>Update Profile
                </button>
            </form>

            <div class="text-center mt-4 pt-3 border-top border-secondary">
                <small class="text-muted">
                    <i class="fas fa-clock me-1"></i> Account Created: <?= date('F d, Y', strtotime($admin['created_at'])); ?>
                </small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>