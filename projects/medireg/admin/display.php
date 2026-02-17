<?php
// Database connection
$servername = "localhost";
$username = "root";  
$password = "";      
$dbname = "medireg";

// Create connection
$conn = new mysqli("localhost", "root", "", "medireg");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get departments
$departments = [];
$sql = "SELECT id, department_name FROM departments";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $departments[$row["id"]] = $row["department_name"];
    }
}

// Get currently serving patients for each department
$now_serving = [];
$sql = "SELECT q.queue_number, q.department_id, d.department_name, 
        CONCAT(p.first_name, ' ', LEFT(p.middle_name, 1), '. ', p.last_name) AS patient_name
        FROM queue q
        JOIN patients p ON q.patient_id = p.id
        JOIN departments d ON q.department_id = d.id
        WHERE q.status = 'Serving' AND DATE(q.created_at) = CURDATE()
        ORDER BY d.department_name";

$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $dept_id = $row["department_id"];
        if (!isset($now_serving[$dept_id])) {
            $now_serving[$dept_id] = [
                'department_name' => $row["department_name"],
                'patients' => []
            ];
        }
        $now_serving[$dept_id]['patients'][] = [
            'queue_number' => $row["queue_number"],
            'patient_name' => $row["patient_name"]
        ];
    }
}

// Get next in line for each department
$next_in_line = [];
foreach ($departments as $dept_id => $dept_name) {
    // Get next waiting patients in order of priority and time
    $sql = "SELECT q.queue_number, q.department_id, 
            CONCAT(p.first_name, ' ', LEFT(p.middle_name, 1), '. ', p.last_name) AS patient_name,
            p.priority_status
            FROM queue q
            JOIN patients p ON q.patient_id = p.id
            WHERE q.department_id = ? AND q.status = 'Waiting' AND DATE(q.created_at) = CURDATE()
            ORDER BY FIELD(p.priority_status, 'Person With Disability (PWD)', 'Senior Citizen', 'None'),
            q.created_at ASC
            LIMIT 3";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $dept_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $waiting_patients = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $waiting_patients[] = [
                'queue_number' => $row["queue_number"],
                'patient_name' => $row["patient_name"],
                'priority_status' => $row["priority_status"]
            ];
        }
    }
    
    $next_in_line[$dept_id] = [
        'department_name' => $dept_name,
        'waiting_patients' => $waiting_patients
    ];
}

// Get recent calls (20 most recent) for today
$recent_calls = [];
$sql = "SELECT q.queue_number, d.department_name, q.created_at,
        CASE 
            WHEN q.status = 'Serving' THEN 'Now Serving'
            ELSE q.status
        END AS status
        FROM queue q
        JOIN departments d ON q.department_id = d.id
        WHERE (q.status = 'Serving' OR q.status = 'Completed') 
        AND DATE(q.created_at) = CURDATE()
        ORDER BY q.id DESC
        LIMIT 20";
        
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $recent_calls[] = [
            'queue_number' => $row["queue_number"],
            'department_name' => $row["department_name"],
            'status' => $row["status"],
            'time' => date('h:i A', strtotime($row["created_at"]))
        ];
    }
}

$conn->close();

// Auto-refresh interval (in seconds)
$refresh_interval = 10;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Queue Display - MediReg</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <meta http-equiv="refresh" content="<?php echo $refresh_interval; ?>">
    <style>
        body {
            background-color: #f0f2f5;
            overflow-x: hidden;
        }
        .header {
            background-color: #0A2A4D;
            color: white;
            padding: 15px 0;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }
        .logo-container {
            display: flex;
            align-items: center;
        }
        .logo {
            height: 50px;
            margin-right: 15px;
        }
        .header-title {
            font-size: 30px;
            font-weight: bold;
            margin: 0;
            padding-bottom: 15px;
        }
        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .time-display {
            font-size: 20px;
            font-weight: bold;
            color: white;
        }
        .back-button {
            background-color: white;
            color: #0A2A4D;
            border: none;
            padding: 8px 20px;
            border-radius: 20px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        .back-button:hover {
            background-color: #f0f0f0;
            transform: translateY(-2px);
        }
        .display-container {
            padding: 0 20px 30px;
        }
        .department-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        .dept-header {
            background-color: #0d6efd;
            color: white;
            padding: 10px 15px;
            font-weight: bold;
            font-size: 18px;
        }
        .now-serving {
            background-color: #28a745;
            color: white;
            padding: 8px 15px;
            font-weight: bold;
        }
        .next-in-line {
            background-color: #ffc107;
            color: #343a40;
            padding: 8px 15px;
            font-weight: bold;
        }
        .queue-item {
            padding: 10px 15px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
        }
        .queue-number {
            font-weight: bold;
            font-size: 18px;
        }
        .patient-name {
            color: #6c757d;
        }
        .priority-tag {
            font-size: 12px;
            padding: 2px 8px;
            border-radius: 12px;
            margin-left: 8px;
        }
        .priority-pwd {
            background-color: #dc3545;
            color: white;
        }
        .priority-senior {
            background-color: #dc3545;
            color: white;
        }
        .recent-calls-table {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
        .recent-calls-header {
            background-color: #343a40;
            color: white;
            padding: 10px 15px;
            font-weight: bold;
            font-size: 18px;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }
        .blink {
            animation: blinking 1.5s infinite;
        }
        @keyframes blinking {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        .status-serving {
            color: #28a745;
        }
        .status-completed {
            color: #6c757d;
        }
        .filter-section {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .filter-container {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .filter-label {
            font-weight: bold;
            color: #0A2A4D;
            margin: 0;
            font-size: 16px;
        }
        .filter-select {
            width: 250px;
            height: 40px;
            border: 2px solid #0A2A4D;
            border-radius: 5px;
            padding: 0 10px;
            font-size: 14px;
        }
        .filter-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo-container">
                <a href="../getStarted.html" style="text-decoration: none;">
                    <img src="../images/medlog.png" alt="MediReg Logo" class="logo" style="cursor: pointer;">
                </a>
            </div>
            <div class="header-right">
                <div id="clock" class="time-display"></div>
                <button class="back-button" onclick="history.back()">
                    ← Back
                </button>
            </div>
        </div>
    </div>

    <div class="container-fluid display-container">
        <h1 class="header-title">Queue Display</h1>
        
        <!-- Department Filter -->
        <div class="filter-section mb-4">
            <div class="filter-container">
                <label for="departmentFilter" class="filter-label">Filter by Department:</label>
                <select id="departmentFilter" class="form-select filter-select">
                    <option value="all">All Departments</option>
                    <?php 
                    // Sort departments alphabetically by name
                    asort($departments);
                    foreach($departments as $id => $name): ?>
                        <option value="<?php echo $id; ?>"><?php echo $name; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="row">
            <!-- Department Cards - Left Column -->
            <div class="col-md-8">
                <div class="row" id="departmentCards">
                    <?php foreach($departments as $dept_id => $dept_name): ?>
                    <div class="col-md-6 department-card-container" data-department="<?php echo $dept_id; ?>">
                        <div class="department-card" onclick="handleCardClick(<?php echo $dept_id; ?>)" style="cursor: pointer;">
                            <div class="dept-header"><?php echo $dept_name; ?></div>
                            
                            <!-- Now Serving Section -->
                            <div class="now-serving">NOW SERVING</div>
                            <?php if(isset($now_serving[$dept_id]) && !empty($now_serving[$dept_id]['patients'])): ?>
                                <?php foreach($now_serving[$dept_id]['patients'] as $patient): ?>
                                <div class="queue-item blink">
                                    <div class="queue-number"><?php echo $patient['queue_number']; ?></div>
                                    <div class="patient-name">
                                        <?php 
                                        // Only show first two characters of name for privacy
                                        $name_parts = explode(' ', $patient['patient_name']);
                                        $private_name = '';
                                        foreach($name_parts as $part) {
                                            $private_name .= substr($part, 0, 2) . '. ';
                                        }
                                        echo trim($private_name);
                                        ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="queue-item">
                                    <div class="text-muted">No patients currently being served</div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Next in Line Section -->
                            <div class="next-in-line">NEXT IN LINE</div>
                            <?php if(!empty($next_in_line[$dept_id]['waiting_patients'])): ?>
                                <?php foreach($next_in_line[$dept_id]['waiting_patients'] as $patient): ?>
                                <div class="queue-item">
                                    <div class="queue-number"><?php echo $patient['queue_number']; ?>
                                        <?php if($patient['priority_status'] == 'Person With Disability (PWD)'): ?>
                                            <span class="priority-tag priority-pwd">PWD</span>
                                        <?php elseif($patient['priority_status'] == 'Senior Citizen'): ?>
                                            <span class="priority-tag priority-senior">SC</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="patient-name">
                                        <?php 
                                        // Only show first two characters of name for privacy
                                        $name_parts = explode(' ', $patient['patient_name']);
                                        $private_name = '';
                                        foreach($name_parts as $part) {
                                            $private_name .= substr($part, 0, 2) . '. ';
                                        }
                                        echo trim($private_name);
                                        ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="queue-item">
                                    <div class="text-muted">No patients waiting</div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Recent Calls - Right Column -->
            <div class="col-md-4">
                <div class="recent-calls-table">
                    <div class="recent-calls-header">RECENT CALLS</div>
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Queue #</th>
                                <th>Department</th>
                                <th>Status</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($recent_calls)): ?>
                                <?php foreach($recent_calls as $call): ?>
                                <tr>
                                    <td><strong><?php echo $call['queue_number']; ?></strong></td>
                                    <td><?php echo $call['department_name']; ?></td>
                                    <td class="status-<?php echo strtolower($call['status']); ?>"><?php echo $call['status']; ?></td>
                                    <td><?php echo $call['time']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center">No recent calls</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Instructions Card -->
                <div class="card mt-4">
                    <div class="card-header bg-primary text-white">
                        <strong>Patient Instructions</strong>
                    </div>
                    <div class="card-body">
                        <ol>
                            <li>Please keep an eye on this display for your queue number.</li>
                            <li>When your number appears in the "NOW SERVING" section, proceed to the corresponding department.</li>
                            <li>Priority is given to Persons With Disability (PWD) and Senior Citizens.</li>
                            <li>If you miss your call, please approach the registration desk to assist you.</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    
    <script>
    // Live clock function
    function updateClock() {
        const now = new Date();
        let hours = now.getHours();
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12; // Hour '0' should be '12'
        const minutes = now.getMinutes().toString().padStart(2, '0');
        const seconds = now.getSeconds().toString().padStart(2, '0');
        const timeString = `${hours}:${minutes}:${seconds} ${ampm}`;
        
        document.getElementById('clock').textContent = timeString;
        
        setTimeout(updateClock, 1000);
    }
    
    // Start the clock when page loads
    window.onload = updateClock;

    // Department filter functionality
    document.getElementById('departmentFilter').addEventListener('change', function() {
        const selectedDepartment = this.value;
        const departmentCards = document.querySelectorAll('.department-card-container');
        
        departmentCards.forEach(card => {
            if (selectedDepartment === 'all' || card.dataset.department === selectedDepartment) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    });
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>