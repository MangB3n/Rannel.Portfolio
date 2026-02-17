<?php
session_start();

// Check if admin is logged in, otherwise redirect to login page
if(!isset($_SESSION["admin_logged_in"]) || $_SESSION["admin_logged_in"] !== true){
    header("location: ../auth/login.php");
    exit;
}

require_once '../includes/database.php';

// --- Optimized Statistics ---
$stats_query = "
    SELECT
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM bookings) as total_bookings,
        (SELECT SUM(total_amount) FROM bookings WHERE booking_status = 'Completed') as total_revenue
";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

$totalUsers = $stats['total_users'] ?? 0;
$totalBookings = $stats['total_bookings'] ?? 0;
$totalRevenue = $stats['total_revenue'] ?? 0;

// --- Fetch Recent Bookings ---
$recent_bookings_query = "SELECT customer_name, created_at, booking_status FROM bookings ORDER BY created_at DESC LIMIT 5";
$recent_bookings_result = mysqli_query($conn, $recent_bookings_query);
$recentBookings = [];
if ($recent_bookings_result) {
    while ($row = mysqli_fetch_assoc($recent_bookings_result)) {
        $recentBookings[] = $row;
    }
}

// --- Fetch Data for Bookings Chart (Last 6 Months) ---
$chart_data_query = "
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count
    FROM bookings
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY month ASC
";
$chart_result = mysqli_query($conn, $chart_data_query);
$chart_labels = [];
$chart_values = [];
if ($chart_result) {
    while ($row = mysqli_fetch_assoc($chart_result)) {
        $chart_labels[] = date("M Y", strtotime($row['month'] . "-01"));
        $chart_values[] = $row['count'];
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - iShoeKicks</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #2b2b2b;
        }
        
        .main-content {
            margin-left: 220px;
            margin-top: 50PX;
            padding: 35px 40px;
           
           
            
        }
        .main-content h1{
            text-align: center;
            color: #ffffff
        
            
        }


        h1 {
            font-size: 2.75rem;
            font-weight: 600;
            margin-bottom: 28px;
            color: #ffffff;
        }

        /* STATS SECTION */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card1, .stat-card2, .stat-card3 {
            margin-top: 60px;

            padding: 24px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            transition: all 0.2s ease;
            border: 1px solid rgba(181, 142, 83, 0.3);
        }

        .stat-card1:hover, .stat-card2:hover, .stat-card3:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(181, 142, 83, 0.4);
        }
        
        .stat-card1 { background: linear-gradient(135deg, #8B7355 0%, #A0826D 100%); }
        .stat-card2 { background: linear-gradient(135deg, #B58E53 0%, #D4A574 100%); }
        .stat-card3 { background: linear-gradient(135deg, #9C7A4E 0%, #B8956A 100%); }
        
        .stat-card1 h3, .stat-card2 h3, .stat-card3 h3 {
            margin: 0 0 12px 0;
            color: rgba(255, 255, 255, 0.95);
            font-size: 0.875rem;
            font-weight: 500;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }
        
        .stat-card1 p, .stat-card2 p, .stat-card3 p {
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: -0.5px;
        }

        /* CONTENT CARDS */
        .recent-bookings, .chart-container {
            background: #3a3a3a;
            padding: 24px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            border: 1px solid rgba(181, 142, 83, 0.2);
        }

        .recent-bookings h3, .chart-container h3 {
            margin: 0 0 20px 0;
            color: #B58E53;
            font-size: 1.125rem;
            font-weight: 600;
        }

        /* TABLE STYLING */
        .table {
            margin-bottom: 0;
        }

        .table thead th {
            border-bottom: 2px solid #4a4a4a;
            font-weight: 600;
            font-size: 0.875rem;
            color: #B58E53;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 12px;
            background: transparent;
        }

        .table tbody td {
            padding: 14px 12px;
            vertical-align: middle;
            color: #d4d4d4;
            border-color: #4a4a4a;
        }

        .table tbody tr {
            border-bottom: 1px solid #4a4a4a;
            transition: background-color 0.15s ease;
        }

        .table tbody tr:hover {
            background-color: #454545;
        }

        .badge {
            padding: 6px 12px;
            font-weight: 500;
            font-size: 0.75rem;
            letter-spacing: 0.3px;
        }

    

        /* ANIMATIONS */
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(15px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stat-card1, .stat-card2, .stat-card3 {
            animation: slideUp 0.5s ease-out forwards;
            opacity: 0;
        }

        .stats-container > .stat-card1 { animation-delay: 0.1s; }
        .stats-container > .stat-card2 { animation-delay: 0.15s; }
        .stats-container > .stat-card3 { animation-delay: 0.2s; }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <h1>Dashboard Overview</h1>
        
        <div class="stats-container">
            <div class="stat-card1">
                <h3>Total Users</h3>
                <p><?php echo number_format($totalUsers); ?></p>
            </div>
            <div class="stat-card2">
                <h3>Total Bookings</h3>
                <p><?php echo number_format($totalBookings); ?></p>
            </div>
            <div class="stat-card3">
                <h3>Total Revenue</h3>
                <p>₱<?php echo number_format($totalRevenue, 2); ?></p>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="chart-container">
                    <h3>Bookings Overview</h3>
                    <canvas id="bookingsChart"></canvas>
                </div>
            </div>
            <div class="col-md-6">
                <div class="recent-bookings">
                    <h3>Recent Bookings</h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($recentBookings as $booking): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($booking['customer_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($booking['created_at'])); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo strtolower($booking['booking_status']) == 'completed' ? 'success' : (strtolower($booking['booking_status']) == 'pending' ? 'warning' : 'info'); ?>">
                                        <?php echo ucfirst(htmlspecialchars($booking['booking_status'])); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        const ctx = document.getElementById('bookingsChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'Bookings per Month',
                    data: <?php echo json_encode($chart_values); ?>,
                    backgroundColor: 'rgba(181, 142, 83, 0.1)',
                    borderColor: '#B58E53',
                    borderWidth: 2.5,
                    pointBackgroundColor: '#B58E53',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        labels: {
                            color: "#d4d4d4",
                            font: {
                                size: 13,
                                weight: '500'
                            },
                            padding: 15
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: "#d4d4d4",
                            font: {
                                size: 12
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: "rgba(181, 142, 83, 0.1)"
                        },
                        ticks: {
                            color: "#d4d4d4",
                            font: {
                                size: 12
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>