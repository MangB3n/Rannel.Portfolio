<?php
//Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "medireg";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get all departments for dropdown
$departments = [];
$sql = "SELECT id, department_name FROM departments";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $departments[$row["id"]] = $row["department_name"];
}

// Get selected department and queue number from form
$selected_dept = isset($_GET['department']) ? intval($_GET['department']) : '';
$queue_number = isset($_GET['queue_number']) ? trim($_GET['queue_number']) : '';

// Prepare data for display card
$dept_name = $selected_dept && isset($departments[$selected_dept]) ? $departments[$selected_dept] : '';
$now_serving = [];
$next_in_line = [];

if ($selected_dept) {
    // Get NOW SERVING for selected department
    $sql = "SELECT q.queue_number
            FROM queue q
            WHERE q.department_id = ? AND q.status = 'Serving' AND DATE(q.created_at) = CURDATE()
            ORDER BY q.created_at ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $selected_dept);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $now_serving[] = $row['queue_number'];
    }

    // Get NEXT IN LINE for selected department
    $sql = "SELECT q.queue_number
            FROM queue q
            WHERE q.department_id = ? AND q.status = 'Waiting' AND DATE(q.created_at) = CURDATE()
            ORDER BY q.created_at ASC
            LIMIT 3";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $selected_dept);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $next_in_line[] = $row['queue_number'];
    }
}
// Auto-refresh interval (in seconds)
$refresh_interval = 10;

?>
<!DOCTYPE html>
<html>
<head>
    <title>Check My Queue Status</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet" media="screen">
    <meta http-equiv="refresh" content="30">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            background: #f0f2f5;
            min-height: 100vh;
            padding: 20px 0;
        }

        .main-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .search-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            padding: 25px;
            margin-bottom: 30px;
        }

        .department-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            max-width: 70%;
            margin: 0 auto;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .department-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .dept-header {
            background: linear-gradient(135deg, #257cff, #1a5cb8);
            color: #fff;
            font-weight: bold;
            font-size: 24px;
            padding: 20px;
            text-align: center;
            margin: 0;
        }

        .now-serving {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            color: #fff;
            font-weight: bold;
            padding: 15px 20px;
            font-size: 20px;
            text-align: center;
        }

        .next-in-line {
            background: linear-gradient(135deg, #ffc107, #d39e00);
            color: #222;
            font-weight: bold;
            padding: 15px 20px;
            font-size: 20px;
            text-align: center;
        }

        .queue-item {
            padding: 15px 20px;
            border-bottom: 1px solid #f1f1f1;
            font-weight: bold;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: background-color 0.2s ease;
        }

        .queue-item:hover {
            background-color: #f8f9fa;
        }

        .highlight {
            background: #ffe082;
            border-radius: 8px;
            padding: 4px 12px;
            font-weight: bold;
            color: #d84315;
            font-size: 14px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .form-control, .form-select {
            width: 100%;
            padding: 12px;
            border-radius: 10px;
            border: 2px solid #e0e0e0;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: #257cff;
            box-shadow: 0 0 0 0.2rem rgba(37, 124, 255, 0.25);
        }

        .btn-primary {
            width: 100%;
            background: linear-gradient(135deg, #257cff, #1a5cb8);
            border: none;
            padding: 12px 20px;
            font-size: 18px;
            margin-top: 20px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(37, 124, 255, 0.3);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #333, #222);
            border: none;
            padding: 12px 25px;
            font-size: 16px;
            margin-top: 20px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .form-label {
            font-size: 18px;
            font-weight: 500;
            margin-bottom: 10px;
            color: #333;
        }

        .text-muted {
            color: #6c757d !important;
            font-style: italic;
        }

        .status-section {
            margin-top: 30px;
        }

        /* Responsive styles */
        @media screen and (max-width: 768px) {
            .main-container {
                padding: 15px;
            }

            .search-card {
                padding: 20px;
            }

            .department-card {
                margin: 20px auto;
            }

            .dept-header {
                font-size: 20px;
                padding: 15px;
            }

            .now-serving, .next-in-line {
                font-size: 18px;
                padding: 12px 15px;
            }

            .queue-item {
                font-size: 16px;
                padding: 12px 15px;
            }
        }

        @media screen and (max-width: 480px) {
            .main-container {
                padding: 10px;
            }

            .search-card {
                padding: 15px;
            }

            .dept-header {
                font-size: 18px;
                padding: 12px;
            }

            .now-serving, .next-in-line {
                font-size: 16px;
                padding: 10px;
            }

            .queue-item {
                font-size: 15px;
                padding: 10px;
            }

            .highlight {
                font-size: 12px;
                padding: 3px 8px;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="search-card">
            <h4 class="mb-4">Queue Status</h4>
            <p class="text-muted mb-4">Select your department to check the current queue status. You can see which numbers are currently being served and which ones are next in line. You may also check the "Go to All Department" button to see the status of all departments.</p>
            <form method="get" class="mb-4">
                <div class="row mb-3">
                    <div class="col-12">
                        <label for="department" class="form-label">Select Department:</label>
                        <select name="department" id="department" class="form-select" required>
                            <option value="">-- Select Department --</option>
                            <?php foreach ($departments as $id => $name): ?>
                                <option value="<?php echo $id; ?>" <?php if ($selected_dept == $id) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Check Status</button>
            </form>
        </div>

        <?php if ($selected_dept): ?>
            <div class="status-section">
                <div class="department-card">
                    <div class="dept-header"><?php echo htmlspecialchars($dept_name); ?></div>
                    <div class="now-serving">NOW SERVING</div>
                    <?php if (!empty($now_serving)): ?>
                        <?php foreach ($now_serving as $num): ?>
                            <div class="queue-item">
                                <?php echo htmlspecialchars($num); ?>
                                <?php if ($queue_number && $queue_number == $num): ?>
                                    <span class="highlight">Your Number</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="queue-item text-muted">No patients currently being served</div>
                    <?php endif; ?>

                    <div class="next-in-line">NEXT IN LINE</div>
                    <?php if (!empty($next_in_line)): ?>
                        <?php foreach ($next_in_line as $num): ?>
                            <div class="queue-item">
                                <?php echo htmlspecialchars($num); ?>
                                <?php if ($queue_number && $queue_number == $num): ?>
                                    <span class="highlight">Your Number</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="queue-item text-muted">No patients waiting</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div style="text-align:center; margin-top:30px;">
            <a href="../admin/display.php" class="btn btn-secondary">View All Department</a>
        </div>
    </div>
</body>
</html>