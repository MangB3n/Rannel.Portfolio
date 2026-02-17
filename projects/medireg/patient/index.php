<?php
// Database connection
$servername = "localhost";
$username = "root";  
$password = "";      
$dbname = "medireg";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get departments for dropdown
$departments = [];
$sql = "SELECT id, department_name FROM departments";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $departments[$row["id"]] = $row["department_name"];
    }
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $first_name = $_POST["first_name"];
    $middle_name = $_POST["middle_name"];
    $last_name = $_POST["last_name"];
    $dob = $_POST["dob"];
    $contact = $_POST["contact"];
    $priority = $_POST["priority"];
    $department_id = $_POST["department"];
    
     
    //Captitalize each name first letter
    $first_name = ucfirst(strtolower($first_name));
    $middle_name = ucfirst(strtolower($middle_name));
    $last_name = ucfirst(strtolower($last_name));

    
    // Validate image upload for priority persons
    if (($priority == 'Senior Citizen' || $priority == 'Person With Disability (PWD)') && empty($_FILES['image']['name'])) {
        $error_message = "Please upload an ID for verification if you selected a priority status.";
    } else {
        // Handle image upload
        $image_path = null;
        if (!empty($_FILES['image']['name'])) {
            $target_dir = "../uploads/";
            $image_path = $target_dir . basename($_FILES["image"]["name"]);
            $image_file_type = strtolower(pathinfo($image_path, PATHINFO_EXTENSION));

            // Check file type
            if (!in_array($image_file_type, ['jpg', 'jpeg', 'png'])) {
                $error_message = "Only JPG, JPEG, and PNG files are allowed.";
            } elseif (!move_uploaded_file($_FILES["image"]["tmp_name"], $image_path)) {
                $error_message = "There was an error uploading the file.";
            }
        }

        if (empty($error_message)) {
            // Insert patient data
            $sql = "INSERT INTO patients (first_name, middle_name, last_name, date_of_birth, contact_number, priority_status, id_image_path) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssss", $first_name, $middle_name, $last_name, $dob, $contact, $priority, $image_path);
            
            if ($stmt->execute()) {
                $patient_id = $conn->insert_id;
                
                // Generate queue number
                $queue_number = generateQueueNumber($conn, $department_id, $priority);
                
                // Insert queue entry
                $sql = "INSERT INTO queue (queue_number, patient_id, department_id, created_at) VALUES (?, ?, ?, NOW())";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sii", $queue_number, $patient_id, $department_id);
                
                if ($stmt->execute()) {
                    $queue_id = $conn->insert_id;
                    // Redirect to success page with queue ID
                    header("Location: queue_success.php?id=" . $queue_id);
                    exit();
                } else {
                    $error_message = "Error creating queue entry: " . $conn->error;
                }
            } else {
                $error_message = "Error: " . $stmt->error;
            }
        }
    }
}

function generateQueueNumber($conn, $department_id, $priority) {
    // Get department code
    $sql = "SELECT department_code FROM departments WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $department_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $department = $result->fetch_assoc();
    $dept_code = isset($department["department_code"]) ? $department["department_code"] : "UNK";

    // Get current date in format YYYYMMDD
    $date_code = date("Ymd");

    // Count total queue entries for today for this department
    $sql = "SELECT COUNT(*) as count FROM queue 
            WHERE department_id = ? AND DATE(created_at) = CURDATE()";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $department_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $count = isset($row["count"]) ? intval($row["count"]) +  1 : 1;

    // Format count to 3 digits (001, 002, etc.)
    $formatted_count = sprintf('%03d', $count);

    // Priority prefix
    if ($priority == 'Senior Citizen') {
        $priority_prefix = 'SC';
    } elseif ($priority == 'Person With Disability (PWD)') {
        $priority_prefix = 'PWD';
    } else {
        $priority_prefix = 'REG';
    }

    // Combine all parts to form queue number
    $queue_number = $priority_prefix . "-" . $dept_code . "-" . $date_code . "-" . $formatted_count;

    return $queue_number;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Registration System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet" media="screen">
    <style>
        body {
            background-color: #f5f5f5;
        }
        .registration-container {
            max-width: 600px;
            margin: 30px auto;
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .form-header {
            text-align: center;
            color: #0d6efd;
            margin-bottom: 25px;
            font-weight: bold;
        }
        .btn-generate {
            width: 100%;
            margin-top: 15px;
        }
        .result-box {
            margin-top: 20px;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
        }
        .success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .logo-container {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }

        .logo {
        width: 236px;
        height: 70px;
        }
        .form-label{
            font-weight: bold;
        }

        .form-select{

            height: 50px;
            border: 1px solid black;
            border-radius: 5px;
            padding: 0 1rem;
        }
        .form-control{
            height: 50px;
            border: 1px solid black;
            border-radius: 5px;
            padding: 0 1rem;
        }
        .btn{
            height: 50px;
            border: 1px solid none;
            border-radius: 50px;
            padding: 0 1rem;
        }
        input[type="file"] {
       
            padding: 10px;
            height: auto;
            line-height: 1.5;
        }
        input[type="file"]::-webkit-file-upload-button {
            background: #0d6efd;
            color: #333;
            
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            
        }

    </style>
</head>
<body>
    
    <div class="container">
    <div class="logo-container">
          <img
            class="logo"
            alt="Medireg sample logo"
            src="../images/medlog.png">
        </div>
        <div class="registration-container">
            <h2 class="form-header">PATIENT INFORMATION</h2>
            
            
            <form method="POST" enctype="multipart/form-data" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="mb-3">
                    <label for="first_name" class="form-label">First Name *</label>
                    <input type="text" class="form-control" id="first_name" name="first_name" required>
                </div>
                
                <div class="mb-3">
                    <label for="middle_name" class="form-label">Middle Name *</label>
                    <input type="text" class="form-control" id="middle_name" name="middle_name">
                </div>
                
                <div class="mb-3">
                    <label for="last_name" class="form-label">Last Name *</label>
                    <input type="text" class="form-control" id="last_name" name="last_name" required>
                </div>
                
                <div class="mb-3">
                    <label for="dob" class="form-label">Date of Birth *</label>
                    <input type="date" class="form-control" id="dob" name="dob" required>
                </div>
                
                <div class="mb-3">
                    <label for="contact" class="form-label">Contact Number *</label>
                    <input type="text" class="form-control" id="contact" maxlength="11" pattern="^09\d{9}$" name="contact" required>
                </div>
                
                <h4 class="mt-4 mb-3">PRIORITY STATUS</h4>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="priority" id="priority_senior" value="Senior Citizen" checked>
                    <label class="form-check-label" for="priority_senior">
                        Senior Citizen
                    </label>
                </div>
                
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="priority" id="priority_pwd" value="Person With Disability (PWD)">
                    <label class="form-check-label" for="priority_pwd">
                        Person With Disability (PWD)
                    </label>
                </div>
                
                <div class="form-check mb-4">
                    <input class="form-check-input" type="radio" name="priority" id="priority_none" value="None">
                    <label class="form-check-label" for="priority_none">
                        Regular
                    </label>
                </div>
                
                <label for="image">Upload ID for Verification (for priority Senior/PWD):</label><br>
                <input 
                    type="file" 
                    class="form-control" 
                    id="image" 
                    name="image" 
                    accept="image/*" 
                    capture="environment"><br><br>

            
               
                       
                
                <h4 class="mb-3">DEPARTMENT SELECTION</h4>
                <div class="mb-3">
                    <select class="form-select" name="department" required>
                        <option value="">--Select Department--</option>
                        <?php foreach($departments as $id => $name): ?>
                        <option value="<?php echo $id; ?>"><?php echo $name; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary btn-generate" id="generateBtn">GENERATE</button>
                <script>
                    document.querySelector('form').addEventListener('submit', function(e) {
                        // Add a delay before navigating (simulate processing)
                        const btn = document.getElementById('generateBtn');
                        btn.disabled = true;
                        btn.textContent = 'Processing...';
                        setTimeout(() => {
                            // Allow form to submit after 2 seconds
                            e.target.submit();
                        }, 2000);
                        e.preventDefault(); // Prevent immediate submit
                    });
                </script>
            
            </form>
        </div>
    </div>
            


    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js">
    </script>
    <script>
            const contact = document.getElementById('contact').value;
            const phonePattern = /^09\\d{9}$/;

                if (!phonePattern.test(contact)) {
                alert('Phone number must start with 09 and be exactly 11 digits.');
                return;
                }
    
    </script>
    <script>
    document.querySelector('form').addEventListener('submit', function (e) {
        const priority = document.querySelector('input[name="priority"]:checked').value;
        const image = document.getElementById('image').value;

        if ((priority === 'Senior Citizen' || priority === 'Person With Disability (PWD)') && !image) {
            alert('Please upload an ID for verification if you selected a priority status.');
            e.preventDefault();
        }
    });
</script>
</body>
</html>