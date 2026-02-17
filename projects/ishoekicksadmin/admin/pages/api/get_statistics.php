
<?php
// API endpoint to get analysis statistics
// 

require_once '../../pages/api/db_config.php';

// Allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Invalid request method. Use GET.');
}

$conn = getDbConnection();

// Get overall statistics
$query = "SELECT shoe_type, total_analyses, 
          DATE_FORMAT(last_analyzed, '%Y-%m-%d %H:%i:%s') as last_analyzed 
          FROM analysis_statistics 
          ORDER BY total_analyses DESC";

$result = $conn->query($query);

$statistics = [];
$total_analyses = 0;

while ($row = $result->fetch_assoc()) {
    $statistics[] = [
        'shoe_type' => $row['shoe_type'],
        'total_analyses' => intval($row['total_analyses']),
        'last_analyzed' => $row['last_analyzed']
    ];
    $total_analyses += intval($row['total_analyses']);
}

// Get user-specific statistics if user_email is provided
$user_stats = null;
if (isset($_GET['user_email'])) {
    $user_email = sanitizeInput($_GET['user_email']);
    
    if (filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
        $user_stmt = $conn->prepare("SELECT shoe_type, COUNT(*) as count 
                                     FROM image_analysis 
                                     WHERE user_email = ? 
                                     GROUP BY shoe_type 
                                     ORDER BY count DESC");
        $user_stmt->bind_param("s", $user_email);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        
        $user_stats = [];
        while ($row = $user_result->fetch_assoc()) {
            $user_stats[] = [
                'shoe_type' => $row['shoe_type'],
                'count' => intval($row['count'])
            ];
        }
        $user_stmt->close();
    }
}

sendResponse(true, 'Statistics retrieved successfully', [
    'overall_statistics' => $statistics,
    'total_analyses' => $total_analyses,
    'user_statistics' => $user_stats
]);

$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Image Analysis Statistics</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h2>Image Analysis Statistics</h2>
        
        <!-- Overall Statistics -->
        <div class="card mb-4">
            <div class="card-header">
                <h4>Overall Statistics</h4>
            </div>
            <div class="card-body">
                <h5>Total Analyses: <span id="totalAnalyses">Loading...</span></h5>
                <div id="overallStats"></div>
            </div>
        </div>

        <!-- User Statistics -->
        <div class="card">
            <div class="card-header">
                <h4>Your Statistics</h4>
            </div>
            <div class="card-body">
                <div id="userStats"></div>
            </div>
        </div>
    </div>

    <script>
        // Fetch statistics from API
        fetch('api/get_statistics.php?user_email=<?php echo isset($_SESSION["user_email"]) ? $_SESSION["user_email"] : ""; ?>')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update total analyses
                    document.getElementById('totalAnalyses').textContent = data.data.total_analyses;

                    // Display overall statistics
                    const overallStats = document.getElementById('overallStats');
                    let overallHtml = '<table class="table"><thead><tr><th>Shoe Type</th><th>Total Analyses</th><th>Last Analyzed</th></tr></thead><tbody>';
                    data.data.overall_statistics.forEach(stat => {
                        overallHtml += `<tr><td>${stat.shoe_type}</td><td>${stat.total_analyses}</td><td>${stat.last_analyzed}</td></tr>`;
                    });
                    overallHtml += '</tbody></table>';
                    overallStats.innerHTML = overallHtml;

                    // Display user statistics
                    const userStats = document.getElementById('userStats');
                    if (data.data.user_statistics) {
                        let userHtml = '<table class="table"><thead><tr><th>Shoe Type</th><th>Your Analyses</th></tr></thead><tbody>';
                        data.data.user_statistics.forEach(stat => {
                        });
                    } else {
                    }
                }
            })
            .catch(error => console.error('Error:', error));
    </script>
</body>
</html>