<?php
// tracking.php
include('../includes/database.php');
session_start();

if(!isset($_SESSION["admin_logged_in"]) || $_SESSION["admin_logged_in"] !== true){
    header("location: ../auth/login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];

// Auto-update arrival_date for bookings with 0000-00-00
$update_query = "UPDATE bookings 
                 SET arrival_date = DATE_ADD(booking_date, INTERVAL 7 DAY) 
                 WHERE (arrival_date = '0000-00-00' OR arrival_date IS NULL) 
                 AND booking_status != 'Cancelled'";
mysqli_query($conn, $update_query);

// Fetch all bookings with tracking details
$query = "SELECT b.*,
         t.crew_name,
            b.rejection_reason,
         CASE 
             WHEN b.tracking_status IS NULL OR b.tracking_status = '' THEN 'Process'
             ELSE b.tracking_status 
         END as current_tracking_status
         FROM bookings b
            
         LEFT JOIN trackings t ON b.id = t.booking_id
         WHERE b.booking_status != 'Cancelled'
         ORDER BY b.booking_date DESC";
$result = mysqli_query($conn, $query);

if (!$result) {
    die("Query failed: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Tracking - iShoeKicks Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    body {
        margin: 0;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
        background: #2b2b2b;
    }

    .main-content {
        margin-left: 250px;
        margin-top: 50px;
        padding: 35px 40px;
        min-height: 100vh;
        background: #2b2b2b;
    }
    .main-content .h2{
        text-align: center;
    }

    @media (max-width: 768px) {
        .main-content {
            margin-left: 0;
            padding: 20px;
        }
    }

    /* Header */
    h2 {
        font-size: 2.75rem;
        font-weight: 600;
        color: #ffffff;
        margin: 0;
        text-align: center;
    }

    /* Stats Cards - Dashboard Style */
    .row.mb-4 .card {
        margin-top: 20px;
        padding: 24px;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        transition: all 0.2s ease;
        border: 1px solid rgba(181, 142, 83, 0.3);
    }

    .row.mb-4 .card.bg-secondary {
        background: linear-gradient(135deg, #8B7355 0%, #A0826D 100%) !important;
    }

    .row.mb-4 .card.bg-info {
        background: linear-gradient(135deg, #B58E53 0%, #D4A574 100%) !important;
    }

    .row.mb-4 .card.bg-warning {
        background: linear-gradient(135deg, #9C7A4E 0%, #B8956A 100%) !important;
    }

    .row.mb-4 .card.bg-success {
        background: linear-gradient(135deg, #8B7355 0%, #A0826D 100%) !important;
    }

    .row.mb-4 .card.bg-primary {
        background: linear-gradient(135deg, #B58E53 0%, #D4A574 100%) !important;
    }

    .row.mb-4 .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(181, 142, 83, 0.4);
    }

    .row.mb-4 .card-body {
        padding: 0;
        background: transparent !important; /* Remove black background */
    }

    .row.mb-4 .card-title {
        margin: 0 0 12px 0;
        color: #ffffff !important;
        font-size: 0.875rem;
        font-weight: 600;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
    }

    .row.mb-4 .card h2 {
        font-size: 2rem;
        font-weight: 700;
        color: #ffffff !important;
        margin: 0;
        letter-spacing: -0.5px;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    /* Override text color classes in stats cards */
    .row.mb-4 .card .text-white {
        color: #ffffff !important;
    }

    /* Main Card */
    .card.shadow-sm {
        background: #3a3a3a;
        border: 1px solid rgba(181, 142, 83, 0.2);
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        overflow: hidden;
    }

    .card-body {
        padding: 24px;
        background: #3a3a3a;
    }

    /* Table Styling */
    .table {
        margin-bottom: 0;
        font-size: 0.9rem;
        background-color: transparent;
    }

    .table thead.table-light th {
        background-color: #2b2b2b !important;
        border-bottom: 2px solid #4a4a4a;
        font-weight: 600;
        font-size: 0.875rem;
        color: #B58E53 ;
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

    /* Force dark background on table rows */
    #trackingTable tbody tr,
    #trackingTable tbody td {
        background-color: #3a3a3a !important;
    }

    #trackingTable tbody tr:hover,
    #trackingTable tbody tr:hover td {
        background-color: #454545 !important;
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

    /* Text Colors */
    .text-primary {
        color: #B58E53 !important;
    }

    .text-muted {
        color: #fff !important;
    }

    /* Status Badges */
    .status-badge,
    .badge {
        padding: 6px 12px;
        font-weight: 500;
        font-size: 0.75rem;
        letter-spacing: 0.3px;
        border-radius: 6px;
    }

    .badge-process { 
        background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
        color: white; 
    }
    
    .badge-ready { 
        background: linear-gradient(135deg, #0dcaf0 0%, #0aa2c0 100%);
        color: white; 
    }
    
    .badge-confirmation { 
        background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
        color: #000; 
    }
    
    .badge-completed { 
        background: linear-gradient(135deg, #198754 0%, #157347 100%);
        color: white; 
    }
    
    .badge-service-rated { 
        background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
        color: white; 
    }

    .badge-pending {
        background: linear-gradient(135deg, #9A7D36 0%, #B58E53 100%) !important;
        color: #fff !important;
    }

    /* Buttons */
    .btn {
        border-radius: 8px;
        padding: 10px 20px;
        font-weight: 500;
        transition: all 0.2s ease;
    }

    .btn-sm {
        padding: 6px 12px;
        font-size: 0.8125rem;
    }

    .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    }

    .btn-primary {
        background: linear-gradient(135deg, #B58E53 0%, #D4A574 100%);
        border: none;
        color: #ffffff;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #D4A574 0%, #B58E53 100%);
        box-shadow: 0 4px 16px rgba(181, 142, 83, 0.4);
    }

    .btn-info {
        background: linear-gradient(135deg, #0dcaf0 0%, #0aa2c0 100%);
        border: none;
        color: #ffffff;
    }

    .btn-warning {
        background: linear-gradient(135deg, #ffc107 0%, #ffb300 100%);
        border: none;
        color: #000;
    }

    .btn-danger {
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        border: none;
        color: #ffffff;
    }

    .btn-success {
        background: linear-gradient(135deg, #198754 0%, #146c43 100%);
        border: none;
        color: #ffffff;
    }

    /* Timeline */
    .timeline {
        position: relative;
        padding: 20px 0;
    }

    .timeline-item {
        position: relative;
        padding-left: 40px;
        margin-bottom: 20px;
        padding-bottom: 20px;
        border-left: 2px solid #4a4a4a;
    }

    .timeline-item::before {
        content: '';
        position: absolute;
        left: -6px;
        top: 0;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #B58E53;
        border: 2px solid #3a3a3a;
        box-shadow: 0 0 0 2px #4a4a4a;
    }

    .timeline-item.active::before {
        background: #198754;
        box-shadow: 0 0 0 2px #198754;
    }

    /* Image Preview */
    .image-preview {
        max-width: 100%;
        max-height: 300px;
        object-fit: contain;
        border-radius: 8px;
        cursor: pointer;
        transition: transform 0.2s ease;
        border: 2px solid #4a4a4a;
    }

    .image-preview:hover {
        transform: scale(1.02);
        border-color: #B58E53;
    }

    .image-upload-preview {
        max-width: 200px;
        max-height: 200px;
        margin-top: 10px;
        border-radius: 8px;
        border: 2px solid #4a4a4a;
        background: #2b2b2b;
    }

    .img-thumbnail {
        border: 2px solid #4a4a4a;
        border-radius: 8px;
        padding: 4px;
        transition: all 0.2s ease;
        background: #2b2b2b;
    }

    .img-thumbnail:hover {
        border-color: #B58E53;
        transform: scale(1.05);
        box-shadow: 0 4px 12px rgba(181, 142, 83, 0.3);
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
        color: #B58E53;
        font-weight: 500;
    }

    input[type="file"] {
        background: #2b2b2b;
        color: #d4d4d4;
        padding: 10px 16px;
        border-radius: 8px;
        border: 1px solid #4a4a4a;
        font-size: 14px;
        font-weight: 500;
        transition: 0.2s ease;
    }

    input[type="file"]:hover {
        border-color: #B58E53;
    }

    .form-title {
        font-style: italic;
        font-weight: 600;
        font-size: 1.25rem;
        color: #B58E53;
        margin-bottom: 8px;
    }

    .form-text {
        color: #999;
    }

    /* Modal Improvements */
    .modal-content {
        border: none;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.5);
        background: #3a3a3a;
        color: #d4d4d4;
    }

    .modal-header {
        background: linear-gradient(135deg, #3a3a3a 0%, #4a4a4a 100%);
        border-bottom: 1px solid rgba(181, 142, 83, 0.3);
        padding: 20px 24px;
        border-radius: 12px 12px 0 0;
    }

    .modal-title {
        font-weight: 600;
        color: #B58E53;
        font-size: 1.125rem;
    }

    .modal-body {
        padding: 24px;
        background: #3a3a3a;
    }

    /* Alert Styling */
    .alert {
        border-radius: 8px;
        border: 1px solid;
        padding: 14px 18px;
    }

    .alert-info {
        background-color: rgba(13, 202, 240, 0.1);
        border-color: rgba(13, 202, 240, 0.3);
        color: #0dcaf0;
    }

    .alert-danger {
        background-color: rgba(220, 53, 69, 0.1);
        border-color: rgba(220, 53, 69, 0.3);
        color: #ff6b7a;
    }

    .alert-heading {
        color: #ff6b7a;
    }

    /* Card inside Modal */
    .modal .card {
        border: 1px solid #4a4a4a;
        border-radius: 8px;
        margin-bottom: 0;
        background: #2b2b2b;
    }

    .modal .card-body {
        padding: 16px;
        background: #2b2b2b;
    }

    .modal .card h6 {
        color: #B58E53;
    }

    /* Border Bottom Sections */
    .border-bottom {
        border-color: rgba(181, 142, 83, 0.3) !important;
        padding-bottom: 12px !important;
        margin-bottom: 16px !important;
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

    .row.mb-4 > [class*="col-"] .card {
        animation: slideUp 0.5s ease-out forwards;
        opacity: 0;
    }

    .row.mb-4 > [class*="col-"]:nth-child(1) .card { animation-delay: 0.1s; }
    .row.mb-4 > [class*="col-"]:nth-child(2) .card { animation-delay: 0.15s; }
    .row.mb-4 > [class*="col-"]:nth-child(3) .card { animation-delay: 0.2s; }
    .row.mb-4 > [class*="col-"]:nth-child(4) .card { animation-delay: 0.25s; }
    .row.mb-4 > [class*="col-"]:nth-child(5) .card { animation-delay: 0.3s; }

    /* Editable Date Field */
    .editable-date {
        padding: 4px 8px;
        border-radius: 4px;
        transition: all 0.2s ease;
        color: #d4d4d4;
    }

    .editable-date:hover {
        background-color: rgba(181, 142, 83, 0.2);
        color: #B58E53 !important;
    }

    .arrival-date-cell {
        cursor: pointer;
    }

    .arrival-date-cell:hover .fa-edit {
        color: #B58E53 !important;
    }

    /* Close button for modals */
    .btn-close {
        filter: invert(1);
    }

    /* Additional dark theme adjustments */
    hr {
        border-color: #4a4a4a;
        opacity: 1;
    }

    .bg-light {
        background-color: #3a3a3a !important;
    }

    small.text-muted {
        color: #999 !important;
    }

    /* Container fluid */
    .container-fluid {
        color: #d4d4d4;
    }

    /* List styling */
    ul {
        color: #d4d4d4;
    }

    /* Paragraph text */
    p {
        color: #d4d4d4;
    }

    p strong {
        color: #ffffff;
    }

    /* Card text */
    .card p {
        color: #d4d4d4;
    }
</style>
</head>
<body>
    <?php include('../includes/sidebar.php'); ?>

    <main class="main-content">
        <div class="container-fluid">
            
            <div class="position-relative mb-4">
                <div class="position-absolute end-0 top-50 translate-middle-y">
                    <button class="btn btn-primary" onclick="refreshTable()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
                
                <h2>Service Tracking Management</h2>
            </div>
           

            <div class="row mb-4">
                <?php
                $stats_query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN tracking_status = 'Process' OR tracking_status IS NULL THEN 1 ELSE 0 END) as in_process,
                    SUM(CASE WHEN tracking_status = 'Ready' THEN 1 ELSE 0 END) as ready,
                    SUM(CASE WHEN tracking_status = 'Confirmation' THEN 1 ELSE 0 END) as confirmation,
                    SUM(CASE WHEN tracking_status = 'Completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN tracking_status = 'Service Rated' THEN 1 ELSE 0 END) as service_rated
                    FROM bookings WHERE booking_status != 'Cancelled'";
                $stats_result = mysqli_query($conn, $stats_query);
                $stats = mysqli_fetch_assoc($stats_result);
                ?>
                
                <div class="col-6 col-md-4 col-lg mb-3">
                    <div class="card text-white bg-secondary">
                        <div class="card-body">
                            <h5 class="card-title">In Process</h5>
                            <h2><?php echo $stats['in_process']; ?></h2>
                        </div>
                    </div>
                </div>
                
                <div class="col-6 col-md-4 col-lg mb-3">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <h5 class="card-title">Ready</h5>
                            <h2><?php echo $stats['ready']; ?></h2>
                        </div>
                    </div>
                </div>
                
                <div class="col-6 col-md-4 col-lg mb-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <h5 class="card-title">Confirmation</h5>
                            <h2><?php echo $stats['confirmation']; ?></h2>
                        </div>
                    </div>
                </div>
                
                <div class="col-6 col-md-4 col-lg mb-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <h5 class="card-title">Completed</h5>
                            <h2><?php echo $stats['completed']; ?></h2>
                        </div>
                    </div>
                </div>
                
                <div class="col-6 col-md-4 col-lg mb-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <h5 class="card-title">Service Rated</h5>
                            <h2><?php echo $stats['service_rated']; ?></h2>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="trackingTable">
                            <thead class="table-light">
                                <tr>
                                    <th><i class="fas fa-hashtag"></i> Booking Ref</th>
                                      <th><i class="fa-solid fa-location-dot"></i>Tracking ID</th>
                                    <th><i class="fas fa-user"></i> Customer</th>
                                    <th><i class="fa-solid fa-mobile"></i> Phone</th>
                                    <th><i class="fa-solid fa-calendar"></i> Booking Date</th>
                                    <th><i class="fa-solid fa-calendar"></i> Arrival Date</th>
                                    <th><i class="fas fa-person"></i> Crew Name</th>
                                    <th>Tracking Status</th>
                                    <th><i class="fa-solid fa-check"></i> Confirmation</th>
                                    <th> Last Updated</th>
                                    <th><i class="fas fa-cogs"></i> Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (mysqli_num_rows($result) > 0) {
                                    while ($row = mysqli_fetch_assoc($result)) {
                                        $status = $row['current_tracking_status'];
                                        $statusClass = 
                                            $status == 'Process' ? 'badge-process' :
                                            ($status == 'Ready' ? 'badge-ready' :
                                            ($status == 'Confirmation' ? 'badge-confirmation' :
                                            ($status == 'Completed' ? 'badge-completed' : 'badge-service-rated')));
                                        
                                        $confirmation_status = isset($row['confirmation_accepted']) && $row['confirmation_accepted'] == 1 
                                            ? '<span class="badge bg-success">Accepted</span>' 
                                            : '<span class="badge badge-pending">Pending</span>';

                                        
                                        $crew_name_display = !empty($row['crew_name']) ? htmlspecialchars($row['crew_name']) : '<span class="text-muted">Not Assigned</span>';
                                        $crew_name_value = !empty($row['crew_name']) ? htmlspecialchars($row['crew_name'], ENT_QUOTES, 'UTF-8') : '';
                                        
                                        echo "<tr>";
                                        echo "<td><strong class='text-white'>".htmlspecialchars($row['booking_reference'])."</strong></td>";
                                        echo "<td>".htmlspecialchars($row['tracking_id'])."</td>";
                                        echo "<td>".htmlspecialchars($row['customer_name'])."</td>";
                                        echo "<td>".htmlspecialchars($row['customer_phone'])."</td>";
                                        echo "<td><small class='text-white'>".date('M d, Y', strtotime($row['booking_date']))."</small></td>";

                                        echo "<td>
    <div class='arrival-date-cell d-flex align-items-center' style='cursor: pointer;'>
        <span class='arrival-date-display editable-date' 
              data-booking-ref='".htmlspecialchars($row['booking_reference'], ENT_QUOTES)."'
              data-current-date='".($row['arrival_date'] == '0000-00-00' || empty($row['arrival_date']) ? date('Y-m-d', strtotime($row['booking_date'] . ' +7 days')) : date('Y-m-d', strtotime($row['arrival_date'])))."'
              title='Click to edit'>
            <small class='text-white'>".($row['arrival_date'] == '0000-00-00' || empty($row['arrival_date']) ? date('Y-m-d', strtotime($row['booking_date'] . ' +7 days')) : date('Y-m-d', strtotime($row['arrival_date'])))."</small>
        </span>
        <i class='fas fa-edit ms-2 text-muted' style='font-size: 0.75rem;'></i>
    </div>
</td>";


                                        echo "<td>".$crew_name_display."</td>";
                                        echo "<td><span class='badge $statusClass status-badge'>".htmlspecialchars($status)."</span></td>";
                                        echo "<td>".$confirmation_status."</td>";
                                        echo "<td><small class='text-white'>".date('M d, Y H:i', strtotime($row['updated_at']))."</small></td>";
                                        echo "<td>
                                            <div class='btn-group btn-group-sm' role='group'>
                                                <button class='btn btn-info' onclick='viewTracking(\"".htmlspecialchars($row['booking_reference'], ENT_QUOTES)."\")' title='View Details'>
                                                    <i class='fas fa-eye'></i>
                                                </button>
                                                <button class='btn btn-primary' onclick='updateTracking(\"".htmlspecialchars($row['booking_reference'], ENT_QUOTES)."\", \"".htmlspecialchars($status, ENT_QUOTES)."\")' title='Update Status'>
                                                    <i class='fas fa-edit'></i>
                                                </button>
                                                <button class='btn btn-warning' onclick='assignCrew(\"".htmlspecialchars($row['booking_reference'], ENT_QUOTES)."\", \"".$crew_name_value."\")' title='Assign Crew'>
                                                    <i class='fas fa-user-plus'></i>
                                                </button>
                                            </div>
                                        </td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='9' class='text-center text-muted py-4'><i class='fas fa-inbox'></i> No bookings found</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Assign Crew Modal -->
    <div class="modal fade" id="assignCrewModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus"></i> Assign Crew Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="assignCrewForm">
                        <input type="hidden" id="crewBookingReference" name="booking_reference">
                        
                        <div class="mb-3">
                            <label for="crewName" class="form-label">Select Crew Member *</label>
                            <select class="form-select" id="crewName" name="crew_name" required>
                                <option value="">-- Select Crew --</option>
                                <option value="Jason">Jason</option>
                                <option value="Michael">Michael</option>
                                <option value="Bea">Bea</option>
                                <option value="Janisa">Janisa</option>
                                <option value="Mike">Mike</option>
                            </select>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-user-check"></i> Assign Crew
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- View Tracking Modal -->
    <div class="modal fade" id="viewTrackingModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-info-circle"></i> Tracking Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <h6 class="border-bottom pb-2">Customer Information</h6>
                            <div id="customerInfo"></div>
                        </div>
                        <div class="col-md-4">
                            <h6 class="border-bottom pb-2">Booking Information</h6>
                            <div id="bookingInfo"></div>
                        </div>
                        <div class="col-md-4">
                            <h6 class="border-bottom pb-2">Payment Information</h6>
                            <div id="paymentInfo"></div>
                        </div>
                    </div>

                    <h6 class="border-bottom pb-2">Shoe Details</h6>
                    <div id="shoeDetails" class="mb-4"></div>

                    <h6 class="border-bottom pb-2">Tracking Images</h6>
                    <div class="row mb-4" id="trackingImages"></div>

                    <h6 class="border-bottom pb-2">Tracking History</h6>
                    <div id="trackingHistory"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Tracking Modal -->
    <div class="modal fade" id="updateTrackingModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Update Tracking Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="updateTrackingForm" enctype="multipart/form-data">
                        <input type="hidden" id="bookingReference" name="booking_reference">
                        
                        <div class="mb-3">
                            <label for="newStatus" class="form-label">Tracking Status *</label>
                            <select class="form-select" id="newStatus" name="new_status" required>
                                <option value="">Select Status</option>
                                <option value="Process">Process</option>
                                <option value="Ready">Ready</option>
                                <option value="Confirmation">Confirmation</option>
                                <option value="Completed">Completed</option>
                                <option value="Service Rated">Service Rated</option>
                            </select>
                            <div class="form-text">Select the current stage of the service</div>
                        </div>

                        <div class="alert alert-info">
                            <strong><i class="fas fa-info-circle"></i> Image Upload Guidelines:</strong>
                            <ul class="mb-0 mt-2">
                                <li><strong>Details:</strong> Upload when status is "Process"</li>
                                <li><strong>Proof of Service (Before):</strong> Upload when status is "Ready"</li>
                                <li><strong>Proof of Service (After):</strong> Upload when status is "Ready"</li>
                                <li><strong>Proof of Delivery:</strong> Upload when status is "Completed"</li>
                            </ul>
                        </div>

                       
                        <div class="mb-3">
                            <label class="form-title">Process:</label>
                            <label class="form-label">Proof of Service Images</label>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="proofOfServiceBefore" class="form-label text-muted">Before:</label>
                                    <input type="file" class="form-control" id="proofOfServiceBefore" name="proof_of_service_before" accept="image/*" onchange="previewImage(this, 'proofServiceBeforePreview')">
                                    <img id="proofServiceBeforePreview" class="image-upload-preview" style="display:none;">
                                </div>
                           
                                <div class="col-md-6 mb-3">
                                    <label for="proofOfServiceAfter" class="form-label text-muted">After:</label>
                                    <input type="file" class="form-control" id="proofOfServiceAfter" name="proof_of_service_after" accept="image/*" onchange="previewImage(this, 'proofServiceAfterPreview')">
                                    <img id="proofServiceAfterPreview" class="image-upload-preview" style="display:none;">
                                </div>
                            </div>
                            <div class="form-text">Upload images showing the shoe before and after the service.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-title">Ready:</label>
                            <label for="detailsImage" class="form-label">Details Image</label>
                            <input type="file" class="form-control" id="detailsImage" name="details_image" accept="image/*" onchange="previewImage(this, 'detailsPreview')">
                            <img id="detailsPreview" class="image-upload-preview" style="display:none;">
                        </div>

                        <div class="mb-3">
                            <label class="form-title">Completed: Proof of Completed:</label>
                            <input type="file" class="form-control" id="proofOfDelivery" name="proof_of_delivery" accept="image/*" onchange="previewImage(this, 'proofDeliveryPreview')">
                            <img id="proofDeliveryPreview" class="image-upload-preview" style="display:none;">
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Tracking Status
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Arrival Date Modal -->
<div class="modal fade" id="editArrivalDateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-calendar-alt"></i> Edit Arrival Date</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editArrivalDateForm">
                    <input type="hidden" id="arrivalDateBookingRef" name="booking_reference">
                    
                    <div class="mb-3">
                        <label for="newArrivalDate" class="form-label">New Arrival Date *</label>
                        <input type="date" class="form-control" id="newArrivalDate" name="arrival_date" required>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Arrival Date
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        let trackingTable;

        $(document).ready(function() {
            trackingTable = $('#trackingTable').DataTable({
                order: [[7, 'desc']],
                pageLength: 25,
                language: {
                    search: "Search bookings:"
                }
            });
        });

        function refreshTable() {
            location.reload();
        }

        function assignCrew(bookingReference, currentCrew) {
            $('#crewBookingReference').val(bookingReference);
            $('#crewName').val(currentCrew);
            $('#assignCrewModal').modal('show');
        }

        $('#assignCrewForm').on('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = $(this).find('button[type="submit"]');
            const originalBtnText = submitBtn.html();
            submitBtn.html('<i class="fas fa-spinner fa-spin"></i> Assigning...').prop('disabled', true);
            
            $.ajax({
                url: 'api/assign_crew.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    console.log('Assign crew response:', response);
                    if (response.success) {
                        alert('Crew member assigned successfully!');
                        $('#assignCrewModal').modal('hide');
                        location.reload();
                    } else {
                        alert('Error: ' + response.message + '\n\nPlease check the console for details.');
                        console.error('Assignment failed:', response);
                        submitBtn.html(originalBtnText).prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', xhr.responseText);
                    console.error('Status:', status);
                    console.error('Error:', error);
                    alert('Error assigning crew: ' + error + '\n\nServer Response: ' + xhr.responseText.substring(0, 200) + '\n\nCheck browser console for full details.');
                    submitBtn.html(originalBtnText).prop('disabled', false);
                }
            });
        });

        function previewImage(input, previewId) {
            const preview = document.getElementById(previewId);
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function viewTracking(bookingReference) {
            $.ajax({
                url: 'api/get_tracking_details.php',
                method: 'POST',
                data: { booking_reference: bookingReference },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        
                        $('#customerInfo').html(`
                            <p><strong>Name:</strong> ${data.customer_name}</p>
                            <p><strong>Email:</strong> ${data.customer_email}</p>
                            <p><strong>Phone:</strong> ${data.customer_phone}</p>
                            <p><strong>Address:</strong> ${data.customer_address || 'N/A'}</p>
                        `);

                        $('#bookingInfo').html(`
                            <p><strong>Reference:</strong> ${data.booking_reference}</p>
                            <p><strong>Branch:</strong> ${data.branch || 'N/A'}</p>
                            <p><strong>Date:</strong> ${new Date(data.booking_date).toLocaleDateString()}</p>
                            <p><strong>Crew:</strong> ${data.crew_name || 'N/A'}</p>
                            <p><strong>Status:</strong> <span class="badge bg-info">${data.tracking_status}</span></p>
                            <p><strong>Confirmation:</strong> ${data.confirmation_accepted == 1 ? '<span class="badge bg-success">Accepted</span>' : '<span class="badge bg-secondary">Pending</span>'}</p>
                        `);

                        $('#paymentInfo').html(`
                            <p><strong>Method:</strong> ${data.payment_method}</p>
                            <p><strong>Total Amount:</strong> ₱${parseFloat(data.total_amount).toFixed(2)}</p>
                            <p><strong>Payment Status:</strong> <span class="badge bg-success">Paid</span></p>
                        `);

                        if (response.shoes && response.shoes.length > 0) {
                            let shoesHtml = '<div class="row">';
                            response.shoes.forEach(shoe => {
                                shoesHtml += `
                                    <div class="col-md-6 mb-3">
                                        <div class="card">
                                            <div class="card-body">
                                                <h6 class="text-primary">Shoe ${shoe.shoe_number}</h6>
                                                <p class="mb-1"><strong>Details:</strong> ${shoe.shoe_info}</p>
                                                <p class="mb-1"><strong>Services:</strong></p>
                                                <ul class="small mb-2">
                                                    ${shoe.services.map(s => '<li>' + s + '</li>').join('')}
                                                </ul>
                                                <p class="mb-0"><strong>Price:</strong> ₱${parseFloat(shoe.price).toFixed(2)}</p>
                                            </div>
                                        </div>
                                    </div>
                                `;
                            });
                            shoesHtml += '</div>';
                            $('#shoeDetails').html(shoesHtml);
                        }

                        let imagesHtml = '';
                        function getImageUrl(filename) {
                            if (!filename) return '';
                            return '/ishoekicksadmin/admin/uploads/' + filename.replace(/^.*[\\\/]/, '');
                        }
                        
                       if (data.proof_of_service_before) {
                                    imagesHtml += `
                                        <div class="col-md-3 mb-3">
                                            <h6 class="mb-2">Proof of Service (Before)</h6>
                                            <img src="${getImageUrl(data.proof_of_service_before)}" class="img-thumbnail image-preview" onclick="viewFullImage(this.src)">
                                        </div>
                                    `;
                                }

                             
                     

                        if (data.proof_of_service_after) {
                            imagesHtml += `
                                <div class="col-md-3 mb-3">
                                    <h6 class="mb-2">Proof of Service (After)</h6>
                                    <img src="${getImageUrl(data.proof_of_service_after)}" class="img-thumbnail image-preview" onclick="viewFullImage(this.src)">
                                </div>
                            `;
                        }

                       // Rejection Reason with Accept Button
                        if (data.rejection_reason && data.rejection_reason.trim() !== '' && data.confirm_rejection != 1) {
                            imagesHtml += `
                                <div class="col-12 mb-3">
                                    <div class="alert alert-danger d-flex align-items-start shadow-sm" role="alert" style="border-left: 4px solid #dc3545;">
                                        <i class="fas fa-exclamation-triangle me-3 mt-1" style="font-size: 1.2rem;"></i>
                                        <div class="flex-grow-1">
                                            <h6 class="alert-heading mb-2 fw-bold">
                                                <i class="fas fa-times-circle"></i> Service Rejection Reason
                                            </h6>
                                            <p class="mb-0" style="font-size: 0.95rem; line-height: 1.6;">${data.rejection_reason}</p>
                                            <div class="mt-3">
                                                <button class="btn btn-success btn-sm" onclick="acknowledgeRejection('${data.booking_reference}')">
                                                    <i class="fas fa-check"></i> Accept Rejection
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `;
                        }

                        if (data.details_image) {
                            imagesHtml += `
                                <div class="col-md-3 mb-3">
                                    <h6 class="mb-2">Details Image</h6>
                                    <img src="${getImageUrl(data.details_image)}" class="img-thumbnail image-preview" onclick="viewFullImage(this.src)">
                                </div>
                            `;
                        }
                        if (data.proof_of_delivery) {
                            imagesHtml += `
                                <div class="col-md-3 mb-3">
                                    <h6 class="mb-2">Proof of Delivery</h6>
                                    <img src="${getImageUrl(data.proof_of_delivery)}" class="img-thumbnail image-preview" onclick="viewFullImage(this.src)">
                                </div>
                            `;
                        }
                        $('#trackingImages').html(imagesHtml || '<p class="text-muted">No images uploaded yet</p>');

                        if (response.history && response.history.length > 0) {
                            let historyHtml = '<div class="timeline">';
                            response.history.forEach(item => {
                                historyHtml += `
                                    <div class="timeline-item ${item.new_status === data.tracking_status ? 'active' : ''}">
                                        <p class="mb-1"><strong>${item.new_status}</strong></p>
                                        <p class="mb-1 small">${item.remarks}</p>
                                        <p class="mb-0"><small class="text-muted">By: ${item.changed_by} | ${new Date(item.created_at).toLocaleString()}</small></p>
                                    </div>
                                `;
                            });
                            historyHtml += '</div>';
                            $('#trackingHistory').html(historyHtml);
                        } else {
                            $('#trackingHistory').html('<p class="text-muted">No tracking history available</p>');
                        }

                        $('#viewTrackingModal').modal('show');
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    alert('Error loading tracking details: ' + error);
                }
            });
        }

        function viewFullImage(src) {
            window.open(src, '_blank');
        }

        function updateTracking(bookingReference, currentStatus) {
            $('#bookingReference').val(bookingReference);
            $('#newStatus').val(currentStatus);
            
            $('#detailsPreview, #proofServiceBeforePreview, #proofServiceAfterPreview, #proofDeliveryPreview').hide();
            
            $('#updateTrackingForm')[0].reset();
            $('#bookingReference').val(bookingReference);
            $('#newStatus').val(currentStatus);
            
            $('#updateTrackingModal').modal('show');
        }

        $('#updateTrackingForm').on('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('admin_email', '<?php echo $_SESSION['admin_email'] ?? 'admin'; ?>');
            
            const submitBtn = $(this).find('button[type="submit"]');
            const originalBtnText = submitBtn.html();
            submitBtn.html('<i class="fas fa-spinner fa-spin"></i> Updating...').prop('disabled', true);
            
            $.ajax({
                url: 'api/update_tracking_status.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    console.log('Response:', response); 
                    
                    if (response.success) {
                        alert('Tracking status updated successfully!' + 
                            (response.uploaded_files && Object.keys(response.uploaded_files).length > 0 
                                ? '\n\nUploaded files: ' + Object.keys(response.uploaded_files).join(', ') 
                                : ''));
                        $('#updateTrackingModal').modal('hide');
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                        submitBtn.html(originalBtnText).prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error details:', xhr.responseText);
                    alert('Error updating tracking: ' + error + '\n\nCheck console for details.');
                    submitBtn.html(originalBtnText).prop('disabled', false);
                }
            });
        });



        // Edit Arrival Date Click Handler
$(document).on('click', '.editable-date', function() {
    const bookingRef = $(this).data('booking-ref');
    let currentDate = $(this).data('current-date');
    
    // The date is already calculated server-side (booking_date + 7 days)
    // So just use it as-is. No need to recalculate.
    
    $('#arrivalDateBookingRef').val(bookingRef);
    $('#newArrivalDate').val(currentDate);
    $('#editArrivalDateModal').modal('show');
});

// Edit Arrival Date Form Submission
$('#editArrivalDateForm').on('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const submitBtn = $(this).find('button[type="submit"]');
    const originalBtnText = submitBtn.html();
    submitBtn.html('<i class="fas fa-spinner fa-spin"></i> Updating...').prop('disabled', true);
    
    $.ajax({
        url: 'api/update_arrival_date.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                alert('Arrival date updated successfully!');
                $('#editArrivalDateModal').modal('hide');
                location.reload();
            } else {
                alert('Error: ' + response.message);
                submitBtn.html(originalBtnText).prop('disabled', false);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', xhr.responseText);
            alert('Error updating arrival date: ' + error);
            submitBtn.html(originalBtnText).prop('disabled', false);
        }
    });
});

function acknowledgeRejection(bookingReference) {
    if (!confirm('Are you sure you want to acknowledge this rejection reason and notify the customer?')) {
        return;
    }
    
    $.ajax({
        url: 'api/accept_rejection_reason.php',
        method: 'POST',
        data: { booking_reference: bookingReference },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                alert('Customer has been notified successfully!');
                $('#viewTrackingModal').modal('hide');
                location.reload();
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            alert('Error: ' + error);
        }
    });
}

    </script>
</body>
</html>