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


// Initialize variables
$queue_number = "";
$generated_time = "";
$department_code = "";
$priority_code = "";
$queue_id = "";


// Check if queue ID is passed
if(isset($_GET['id']) && is_numeric($_GET['id'])) {
    $queue_id = intval($_GET['id']);
   
    // Get queue information
    $sql = "SELECT q.queue_number, q.created_at, d.department_code, d.department_name, p.priority_status
            FROM queue q
            JOIN departments d ON q.department_id = d.id
            JOIN patients p ON q.patient_id = p.id
            WHERE q.id = ?";
   
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $queue_id);
    $stmt->execute();
    $result = $stmt->get_result();
   
    if($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $queue_number = $row['queue_number'];
        $generated_time = date('n/j/Y, g:i:s A', strtotime($row['created_at']));
        $department_code = $row['department_code'];
        $department_name = $row['department_name'];
        $priority_status = $row['priority_status'];
       
        // Determine priority code for display
        if($priority_status == 'Senior Citizen') {
            $priority_code = "S";
        } elseif($priority_status == 'Person With Disability (PWD)') {
            $priority_code = "P";
        } else {
            $priority_code = "R"; // Regular
        }
    } else {
        // Queue not found
        header("Location: index.php?error=queue_not_found");
        exit();
    }
} else {
    // No queue ID provided
    header("Location: index.php?error=missing_queue");
    exit();
}


$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Queue Number - MediReg</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }


        .queue-card-container {
            width: 100%;
            max-width: 400px;
            margin: 20px auto;
        }


        .queue-card { 
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 20px;
            height: 65vh; /* dito iaadjust yung height ng card */ 
        }


        .queue-header {
            text-align: center;
            margin-bottom: 20px;
        }


        .queue-title {
            color: #333;
            font-size: 30px;
            font-weight: bold;
            margin: 0;
        }


        .queue-content {
            background-color: #f8f9fa;
            border-radius: 6px;
            padding: 20px;
            text-align: center;
        }


        .queue-number-display {
            font-size: 36px;
            font-weight: bold;
            color: #0d6efd;
            margin: 20px 0;
        }


        .queue-instructions {
            margin: 20px 0;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 6px;
            border-left: 4px solid #0d6efd;
        }


        .queue-instructions p {
            margin: 8px 0;
            color: #444;
            font-size: 15px;
            line-height: 1.4;
        }


        .queue-instructions p:first-child {
            color: #0d6efd;
            font-weight: 500;
        }


        .queue-timestamp {
            color: #666;
            font-size: 14px;
        }


        .btn-view-all, .phone-btn {
            width: 100%;
            max-width: 400px;
            padding: 12px;
            margin: 10px auto;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            display: block;
        }


        .btn-view-all {
            background-color: #0d6efd;
            color: white;
        }


        .phone-btn {
            background-color: #333;
            color: white;
        }


        .print-section {
            margin: 20px 0;
            text-align: center;
        }


        .print-btn, .home-btn {
            padding: 8px 16px;
            margin: 0 5px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }


        .print-btn {
            background-color: #28a745;
            color: white;
        }


        .home-btn {
            background-color: #6c757d;
            color: white;
        }


        @media (max-width: 480px) {
            body {
                padding: 10px;
            }


            .queue-number-display {
                font-size: 28px;
            }


            .queue-title {
                font-size: 20px;
            }
        }


        @media print {
            .no-print {
                display: none;
            }
            body {
                background-color: white;
            }
        }
    </style>
</head>
<body>
    <div class="queue-card-container">
        <div class="queue-card">
            <div class="queue-header">
                <h1 class="queue-title">QUEUE NUMBER</h1>
            </div>
           
            <div class="queue-content">                
                <div class="queue-number-display">
                    <?php echo $queue_number; ?>
                </div>
               
                <div class="queue-timestamp" style="font-style: italic;">
                    Generated on: <?php echo $generated_time; ?>
                </div>
            </div>
            <div class="queue-instructions">
                <h5 style="font-weight: bold;">Note:</h5>
                <p>Keep this queue number for reference by printing or saving it to device.</p>
                <p>A 5-minute grace period is provided when your number is called. Please be present at the department within this time.</p>
                <p>Thank you for your patience.</p>
            </div>
        </div>
    </div>
   
    <div class="print-section no-print">
        <button class="print-btn" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
        <button class="home-btn" onclick="location.href='index.php'"><i class="fas fa-home"></i> Home</button>
       
       
    </div>


    <a href="../patient/patientdisplay.php" class="btn btn-primary btn-view-all no-print">VIEW DEPARTMENT</a>
    <button class="phone-btn" onclick="saveQueueNumber()"><i class="fas fa-phone"></i> SAVE QUEUE NUMBER</button>
 
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"> </script>
     <script>
        saveQueueNumber = () => {
            const queueNumber = "<?php echo $queue_number; ?>";
            const departmentName = "<?php echo $department_name; ?>";
            const priorityCode = "<?php echo $priority_code; ?>";
            const message = `Your Queue Number: ${queueNumber}\nDepartment: ${departmentName}\nPriority Code: ${priorityCode}`;
           
        if (queueNumber && departmentName && priorityCode) {
            alert(message);
        } else {
            alert("Queue information is incomplete.");
            return;
        }


        const canvas = document.createElement("canvas");
        const ctx = canvas.getContext("2d");


        canvas.width = 600;
        canvas.height = 400;


        ctx.fillStyle = "#ffffff";
        ctx.fillRect(0, 0, canvas.width, canvas.height);


        ctx.fillStyle = "#000000";
        ctx.font = "20px Arial";
        ctx.textAlign = "center";


        ctx.fillText("Your Queue Number", canvas.width / 2, 50);
        ctx.fillText(queueNumber, canvas.width / 2, 90);
        ctx.fillText(`Department: ${departmentName}`, canvas.width / 2, 130);
        ctx.fillText(`Priority Code: ${priorityCode}`, canvas.width / 2, 170);


        canvas.toBlob((blob) => {
            const url = URL.createObjectURL(blob);
            const a = document.createElement("a");
            a.href = url;
            a.download = "queue_number.png";
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }, "image/png");


    }
     </script>
   
</body>
</html>
