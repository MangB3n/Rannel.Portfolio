<?php
session_start();
require_once '../includes/database.php';

if(!isset($_SESSION["admin_logged_in"]) || $_SESSION["admin_logged_in"] !== true){
    header("location: ../auth/login.php");
    exit;
}

$date_from = isset($_GET['date_from']) && !empty($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-29 days'));
$date_to = isset($_GET['date_to']) && !empty($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

$date_from_sql = mysqli_real_escape_string($conn, $date_from);
$date_to_sql = mysqli_real_escape_string($conn, $date_to . ' 23:59:59');

$summary_query = "
    SELECT
        (SELECT COALESCE(SUM(total_amount), 0) FROM bookings WHERE booking_status = 'Completed' AND created_at BETWEEN '$date_from_sql' AND '$date_to_sql') as total_revenue,
        (SELECT COUNT(*) FROM bookings WHERE created_at BETWEEN '$date_from_sql' AND '$date_to_sql') as total_bookings,
        (SELECT COUNT(*) FROM users WHERE created_at BETWEEN '$date_from_sql' AND '$date_to_sql') as new_customers
";
$summary_result = mysqli_query($conn, $summary_query);
$summary_stats = mysqli_fetch_assoc($summary_result);

$revenue_chart_query = "
    SELECT 
        DATE(created_at) as sale_date, 
        SUM(total_amount) as daily_revenue
    FROM bookings 
    WHERE booking_status = 'Completed' AND created_at BETWEEN '$date_from_sql' AND '$date_to_sql'
    GROUP BY DATE(created_at)
    ORDER BY sale_date ASC
";
$revenue_chart_result = mysqli_query($conn, $revenue_chart_query);
$revenue_chart_labels = [];
$revenue_chart_values = [];
while($row = mysqli_fetch_assoc($revenue_chart_result)) {
    $revenue_chart_labels[] = date('M d', strtotime($row['sale_date']));
    $revenue_chart_values[] = $row['daily_revenue'];
}

$status_chart_query = "
    SELECT booking_status, COUNT(*) as count 
    FROM bookings 
    WHERE created_at BETWEEN '$date_from_sql' AND '$date_to_sql'
    GROUP BY booking_status
";
$status_chart_result = mysqli_query($conn, $status_chart_query);
$status_chart_labels = [];
$status_chart_values = [];
$status_chart_colors = [];

$status_color_map = [
    'Pending' => '#ffc107',
    'Accepted' => '#0dcaf0',
    'Completed' => '#198754',
    'Cancelled' => '#dc3545',
    'Default' => '#6c757d'
];

while($row = mysqli_fetch_assoc($status_chart_result)) {
    $status_chart_labels[] = $row['booking_status'];
    $status_chart_values[] = $row['count'];
    $status_chart_colors[] = $status_color_map[$row['booking_status']] ?? $status_color_map['Default'];
}

$detailed_bookings_query = "SELECT * FROM bookings WHERE created_at BETWEEN '$date_from_sql' AND '$date_to_sql' ORDER BY created_at DESC";
$detailed_bookings_result = mysqli_query($conn, $detailed_bookings_query);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - iShoeKicks Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
    margin: 0;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
    background: #2b2b2b;
}

main {
    margin-left: 250px;
    margin-top: 50px;
    padding: 35px 40px;
    min-height: 100vh;
    background: #2b2b2b;
}

@media (max-width: 768px) {
    main {
        margin-left: 0;
        padding: 20px;
    }
}

/* Header */
h1 {
    font-size: 2.75rem;
    font-weight: 600;
    color: #ffffff;
    margin: 0;
}

/* Card Styling */
.card {
    background: #3a3a3a;
    border: 1px solid rgba(181, 142, 83, 0.2);
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    transition: all 0.2s ease;
    overflow: hidden;
}

.card:hover {
    box-shadow: 0 4px 16px rgba(181, 142, 83, 0.3);
}

.card-body {
    padding: 24px;
    background: #3a3a3a;
}

.card-header {
    background: linear-gradient(135deg, #3a3a3a 0%, #4a4a4a 100%);
    border-bottom: 1px solid rgba(181, 142, 83, 0.3);
    padding: 18px 24px;
    border-radius: 10px 10px 0 0;
    font-weight: 600;
    color: #B58E53;
}

/* Stats Cards */
.stat-card {
    text-align: center;
    padding: 24px;
    background: #3a3a3a;
}

.stat-card h5 {
    font-size: 0.875rem;
    font-weight: 600;
    color: #B58E53;
    margin-bottom: 12px;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

.stat-card h3 {
    font-size: 2rem;
    font-weight: 700;
    color: #ffffff;
    margin: 0;
}

/* Form Elements */
.form-control, .form-select {
    border-radius: 8px;
    border: 1px solid #4a4a4a;
    padding: 10px 14px;
    transition: all 0.2s ease;
    background: #2b2b2b;
    color: #ffffff;
}

.form-control:focus, .form-select:focus {
    border-color: #B58E53;
    box-shadow: 0 0 0 3px rgba(181, 142, 83, 0.1);
    background: #2b2b2b;
    color: #ffffff;
}

.form-label {
    font-weight: 500;
    color: #B58E53;
    margin-bottom: 8px;
}

/* Button Styling */
.btn {
    border-radius: 8px;
    padding: 10px 20px;
    font-weight: 500;
    transition: all 0.2s ease;
}

.btn-primary {
    background: linear-gradient(135deg, #B58E53 0%, #D4A574 100%);
    border: none;
    color: #ffffff;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #D4A574 0%, #B58E53 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(181, 142, 83, 0.4);
}

/* Chart Container */
.chart-container {
    height: 350px;
    position: relative;
}

/* Table Styling */
.table {
    margin-bottom: 0;
    font-size: 0.9rem;
    background-color: transparent;
}

.table thead th {
    background-color: #2b2b2b !important;
    border-bottom: 2px solid #4a4a4a;
    font-weight: 600;
    font-size: 0.875rem;
    color: #B58E53 !important;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 12px;
    white-space: nowrap;
}

.table tbody td {
    padding: 14px 12px;
    vertical-align: middle;
    color: #d4d4d4;
    border-color: #4a4a4a;
    background-color: #3a3a3a !important;
}

.table tbody tr {
    border-bottom: 1px solid #4a4a4a;
    transition: background-color 0.15s ease;
    background-color: #3a3a3a !important;
}

.table-hover tbody tr:hover {
    background-color: #454545 !important;
}

.table-hover tbody tr:hover td {
    background-color: #454545 !important;
}

.table-striped tbody tr:nth-of-type(odd) {
    background-color: #3a3a3a !important;
}

.table-striped tbody tr:nth-of-type(even) {
    background-color: #353535 !important;
}

/* DataTables specific row styling */
table.dataTable tbody tr {
    background-color: #3a3a3a !important;
}

table.dataTable tbody tr:hover {
    background-color: #454545 !important;
}

table.dataTable.stripe tbody tr.odd,
table.dataTable.display tbody tr.odd {
    background-color: #3a3a3a !important;
}

table.dataTable.stripe tbody tr.even,
table.dataTable.display tbody tr.even {
    background-color: #353535 !important;
}

table.dataTable.stripe tbody tr.odd:hover,
table.dataTable.display tbody tr.odd:hover,
table.dataTable.stripe tbody tr.even:hover,
table.dataTable.display tbody tr.even:hover {
    background-color: #454545 !important;
}

/* Badge Styling */

.status-badge,
.badge {
    padding: 6px 12px;
    font-weight: 500;
    font-size: 0.75rem;
    letter-spacing: 0.3px;
    border-radius: 6px;
    border: none;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

/* BOOKING STATUS BADGES */
/* Pending - Yellow */
.badge.bg-primary,
.badge-pending {
    background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%) !important;
    color: #000 !important;
}

/* Accepted - Cyan */
.badge.bg-info,
.badge-accepted {
    background: linear-gradient(135deg, #0dcaf0 0%, #0aa2c0 100%) !important;
    color: white !important;
}

/* Completed - Green */
.badge.bg-success,
.badge-completed {
    background: linear-gradient(135deg, #198754 0%, #157347 100%) !important;
    color: white !important;
}

/* Cancelled - Red */
.badge.bg-danger,
.badge-cancelled {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%) !important;
    color: white !important;
}

/* TRACKING STATUS BADGES */
/* Process - Gray */
.badge-process,
.badge.bg-secondary {
    background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%) !important;
    color: white !important;
}

/* Ready - Cyan */
.badge-ready {
    background: linear-gradient(135deg, #0dcaf0 0%, #0aa2c0 100%) !important;
    color: white !important;
}

/* Confirmation - Yellow */
.badge-confirmation,
.badge.bg-warning {
    background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%) !important;
    color: #000 !important;
}

/* Service Rated - Blue */
.badge-service-rated {
    background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%) !important;
    color: white !important;
}

/* Make badges stand out */
.table tbody .badge {
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    font-weight: 500;
}
/* Text Colors */
.text-primary {
    color: #fff !important;
}

.text-muted {
    color: #999 !important;
}

small.text-muted {
    color: #999 !important;
}

/* DataTables Customization */
.dataTables_wrapper .dataTables_filter input {
    border-radius: 8px;
    border: 1px solid #4a4a4a;
    padding: 8px 14px;
    margin-left: 8px;
    background: #2b2b2b;
    color: #ffffff;
}

.dataTables_wrapper .dataTables_length select {
    border-radius: 8px;
    border: 1px solid #4a4a4a;
    padding: 6px 12px;
    margin: 0 8px;
    background: #2b2b2b;
    color: #ffffff;
}

.dataTables_wrapper .dataTables_filter label,
.dataTables_wrapper .dataTables_length label,
.dataTables_wrapper .dataTables_info {
    color: #d4d4d4;
}

.dataTables_wrapper .dataTables_paginate .paginate_button {
    border-radius: 6px;
    padding: 6px 12px;
    margin: 0 2px;
    color: #d4d4d4 !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background: linear-gradient(135deg, #B58E53 0%, #D4A574 100%) !important;
    border-color: #B58E53 !important;
    color: #ffffff !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background: #4a4a4a !important;
    border-color: #B58E53 !important;
    color: #ffffff !important;
}

/* Animation */
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

.stat-card {
    animation: slideUp 0.5s ease-out forwards;
    opacity: 0;
}

.row.mb-4 > [class*="col-"]:nth-child(1) .stat-card { animation-delay: 0.1s; }
.row.mb-4 > [class*="col-"]:nth-child(2) .stat-card { animation-delay: 0.15s; }
.row.mb-4 > [class*="col-"]:nth-child(3) .stat-card { animation-delay: 0.2s; }

/* Additional dark theme adjustments */
.container-fluid {
    color: #d4d4d4;
    
}


hr {
    border-color: #4a4a4a;
    opacity: 1;
}

/* Strong text styling */
strong {
    color: #ffffff;
}


/* Chart.js Dark Theme Compatibility */
canvas {
    background: transparent !important;
}

/* Ensure chart text is visible */
.chart-container canvas {
    color: #d4d4d4 !important;
}
    </style>
</head>
<body>
    <?php include('../includes/sidebar.php'); ?>

    <main>
        <div class="container-fluid">
            <div class="text-center mb-4">
                <h1>Reports & Analytics</h1>
            </div>

            <!-- Date Range Filter -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="reports.php" class="row g-3 align-items-center">
                        <div class="col-md-5">
                            <label for="date_from" class="form-label"><i class="fas fa-calendar-alt"></i> From Date</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>
                        <div class="col-md-5">
                            <label for="date_to" class="form-label"><i class="fas fa-calendar-alt"></i> To Date</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-chart-bar"></i> Generate</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Summary Statistics -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card stat-card">
                        <h5><i class="fas fa-money-bill-wave"></i> Total Revenue</h5>
                        <h3>₱<?php echo number_format($summary_stats['total_revenue'], 2); ?></h3>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card">
                        <h5><i class="fas fa-calendar-check"></i> Total Bookings</h5>
                        <h3><?php echo number_format($summary_stats['total_bookings']); ?></h3>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card">
                        <h5><i class="fas fa-user-plus"></i> New Customers</h5>
                        <h3><?php echo number_format($summary_stats['new_customers']); ?></h3>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="row mb-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header"><i class="fas fa-chart-line"></i> Revenue Over Time</div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="revenueChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header"><i class="fas fa-chart-pie"></i> Booking Status Breakdown</div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="statusChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Bookings Table -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-table"></i> Detailed Bookings 
                    <small class="text-muted">(<?php echo date('M d, Y', strtotime($date_from)) . ' - ' . date('M d, Y', strtotime($date_to)); ?>)</small>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="bookingsReportTable">
                            <thead>
                                <tr>
                                    <th>Reference</th>
                                    <th>Customer</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Booking Status</th>
                                    <th>Tracking Status</th>
                                </tr>
                            </thead>
                           <tbody>
    <?php while($row = mysqli_fetch_assoc($detailed_bookings_result)): ?>
    <tr>
        <td><strong class="text-primary"><?php echo htmlspecialchars($row['booking_reference']); ?></strong></td>
        <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
        <td><small class="text-muted"><?php echo date('M d, Y', strtotime($row['created_at'])); ?></small></td>
        <td><strong>₱<?php echo number_format($row['total_amount'], 2); ?></strong></td>
        
        <!-- BOOKING STATUS with proper badge classes -->
        <td>
            <?php 
                $booking_status = $row['booking_status'];
                $badgeClass = 'secondary';
                
                if ($booking_status === 'Pending') {
                    $badgeClass = 'primary'; // Yellow
                } elseif ($booking_status === 'Accepted') {
                    $badgeClass = 'info'; // Cyan
                } elseif ($booking_status === 'Completed') {
                    $badgeClass = 'success'; // Green
                } elseif ($booking_status === 'Cancelled') {
                    $badgeClass = 'danger'; // Red
                }
            ?>
            <span class="badge bg-<?php echo $badgeClass; ?>">
                <?php echo htmlspecialchars($booking_status); ?>
            </span>
        </td>
        
        <!-- TRACKING STATUS with custom badge classes -->
        <td>
            <?php 
                $tracking_status = $row['tracking_status'];
                $trackingClass = 'badge-process'; // Default gray
                
                if ($tracking_status === 'Process' || empty($tracking_status)) {
                    $trackingClass = 'badge-process'; // Gray
                } elseif ($tracking_status === 'Ready') {
                    $trackingClass = 'badge-ready'; // Cyan
                } elseif ($tracking_status === 'Confirmation') {
                    $trackingClass = 'badge-confirmation'; // Yellow
                } elseif ($tracking_status === 'Completed') {
                    $trackingClass = 'badge-completed'; // Green
                } elseif ($tracking_status === 'Service Rated') {
                    $trackingClass = 'badge-service-rated'; // Blue
                }
            ?>
            <span class="badge <?php echo $trackingClass; ?>">
                <?php echo htmlspecialchars($tracking_status); ?>
            </span>
        </td>
    </tr>
    <?php endwhile; ?>
</tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#bookingsReportTable').DataTable({
                order: [[2, 'desc']],
                pageLength: 25,
                language: {
                    search: "Search bookings:"
                }
            });
        });

        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($revenue_chart_labels); ?>,
                datasets: [{
                    label: 'Daily Revenue',
                    data: <?php echo json_encode($revenue_chart_values); ?>,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    fill: true,
                    tension: 0.3,
                    borderWidth: 2.5,
                    pointBackgroundColor: '#0d6efd',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
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
                            color: "rgba(0, 0, 0, 0.05)"
                        },
                        ticks: {
                            color: "#d4d4d4",
                            font: {
                                size: 12
                            },
                            callback: function(value) { 
                                return '₱' + value.toLocaleString(); 
                            }
                        }
                    }
                }
            }
        });

        // Booking Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($status_chart_labels); ?>,
                datasets: [{
                    label: 'Bookings',
                    data: <?php echo json_encode($status_chart_values); ?>,
                    backgroundColor: <?php echo json_encode($status_chart_colors); ?>,
                    borderWidth: 2,
                    borderColor: '#fff',
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: "#fff",
                            font: {
                                size: 12,
                                weight: '500'
                            },
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    }
                },
                animation: {
                    animateRotate: true,
                    animateScale: true
                }
            }
        });
    </script>

</body>
</html>