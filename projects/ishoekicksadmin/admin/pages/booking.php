<?php
session_start();

require_once '../includes/database.php';

if(!isset($_SESSION["admin_logged_in"]) || $_SESSION["admin_logged_in"] !== true){
    header("location: ../auth/login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];

// Fetch all bookings - customer details are already in the bookings table
$sql = "SELECT * FROM bookings ORDER BY created_at DESC";
$result = mysqli_query($conn, $sql);

if (!$result) {
    die("Query failed: " . mysqli_error($conn));
}

// Service map for services
$service_map = [
    'regular_cleaning' => ['name' => 'Regular Cleaning', 'price' => 499],
    'vip_cleaning' => ['name' => 'VIP Cleaning', 'price' => 699],
    'repainting' => ['name' => 'Repainting', 'price' => 1699],
    'restitching' => ['name' => 'Restitching', 'price' => 1699],
    'restoring' => ['name' => 'Restoring', 'price' => 1699],
    'partial_regluing' => ['name' => 'Partial Regluing', 'price' => 1699],
    'single_sole' => ['name' => 'Single Sole', 'price' => 2199],
    'sole_pair' => ['name' => 'Sole Pair', 'price' => 2699],
    'multiple_sole' => ['name' => 'Multiple Sole', 'price' => 2899],
    'deodorizing' => ['name' => 'Deodorizing', 'price' => 200],
    'rush_fee' => ['name' => 'Rush Fee', 'price' => 149]
];

// Helper to resolve image path or URL
function resolveImageUrl($value) {
    if (empty($value)) return '';
    $value = trim($value);
    
    // 1. If already a full URL or data URI, return as-is
    if (preg_match('#^(https?://|data:)#i', $value)) return $value;
    
    // 2. Check if it's raw Base64 data from the mobile app
    if (strpos($value, 'iVBOR') === 0) {
        $base64Data = preg_replace('/\s+/', '', $value);
        return 'data:image/png;base64,' . $base64Data;
    }

    if (strpos($value, '/9j/') === 0) { // Common JPEG Base64 prefix
        $base64Data = preg_replace('/\s+/', '', $value);
        return 'data:image/jpeg;base64,' . $base64Data;
    }

    // 3. If not Base64 or URL, assume it's a filename and clean it
    $cleanFilename = preg_replace('#^.*[\\\/]#', '', $value);
    $cleanFilename = preg_replace('#^uploads\/#', '', $cleanFilename);
    
    // 4. Check if it's a tracking image
    if (strpos($value, 'receipt_image') !== false || 
        strpos($value, 'proof_service_') !== false || 
        strpos($value, 'details_') !== false || 
        strpos($value, 'proof_delivery_') !== false ||
        strpos($value, 'proof_of_payment') !== false) {
        return '../uploads/' . $cleanFilename;
    }
    
    // 5. For shoe images from ishoekicks project
    if (strpos($value, 'uploads/shoes/') !== false) {
        return 'http://localhost/ishoekicks/' . $value;
    }
    
    // 6. Default fallback
    return 'http://localhost/ishoekicks/uploads/shoes/' . $cleanFilename;
}

// Auto-refresh interval (in seconds)
$refresh_interval = 15;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Management - iShoeKicks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
   
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/react@18/umd/react.development.js" crossorigin></script>
    <script src="https://unpkg.com/react-dom@18/umd/react-dom.development.js" crossorigin></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    
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
    .border-bottom {
        border-color: rgba(181, 142, 83, 0.3) !important;
        padding-bottom: 16px;
        margin-bottom: 28px;
    }

    h1.h2 {
        font-size: 2.75rem;
        font-weight: 600;
        color: #ffffff;
        margin: 0;
        text-align: center;
    }

    /* Stats Cards - Dashboard Style */
    .row.mb-4 .card {
        margin-top: 70px;
        padding: 24px;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        transition: all 0.2s ease;
        border: 1px solid rgba(181, 142, 83, 0.3);
        background: linear-gradient(135deg, #8B7355 0%, #A0826D 100%);
    }

    .row.mb-4 > [class*="col-"]:nth-child(2) .card {
        background: linear-gradient(135deg, #B58E53 0%, #D4A574 100%);
    }

    .row.mb-4 > [class*="col-"]:nth-child(3) .card {
        background: linear-gradient(135deg, #9C7A4E 0%, #B8956A 100%);
    }

    .row.mb-4 > [class*="col-"]:nth-child(4) .card {
        background: linear-gradient(135deg, #8B7355 0%, #A0826D 100%);
    }

    .row.mb-4 .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(181, 142, 83, 0.4);
    }

    .row.mb-4 .card .card-body {
        padding: 0;
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

    /* Override any conflicting text color classes */
    .row.mb-4 .card .text-warning,
    .row.mb-4 .card .text-info,
    .row.mb-4 .card .text-success,
    .row.mb-4 .card .text-muted {
        color: #ffffff !important;
    }

    /* Main Table Card */
    .card {
        background: #3a3a3a;
        border: 1px solid rgba(181, 142, 83, 0.2);
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        overflow: hidden;
    }

    .card-header {
        background: linear-gradient(135deg, #3a3a3a 0%, #4a4a4a 100%);
        border-bottom: 1px solid rgba(181, 142, 83, 0.3);
        padding: 18px 24px;
    }

    .card-header h5 {
        margin: 0;
        font-size: 1.125rem;
        font-weight: 600;
        color: #B58E53;
    }

    .card-body {
        padding: 24px;
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
        color: #B58E53;
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
        cursor: pointer;
    }

    .table-hover tbody tr:hover td {
        background-color: #454545 !important;
    }

    /* Force dark background on table rows */
    #bookingsTable tbody tr,
    #bookingsTable tbody td {
        background-color: #3a3a3a !important;
    }

    #bookingsTable tbody tr:hover,
    #bookingsTable tbody tr:hover td {
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

    /* Customer Info Styling */
    .customer-info strong {
        color: #ffffff;
        font-weight: 600;
    }

    .customer-info small {
        font-size: 0.8125rem;
        line-height: 1.6;
        color: #d4d4d4;
    }

    /* Text Colors */
    .text-primary {
        color: #B58E53 !important;
    }

    .text-muted {
        color: #fff !important;
    }

    /* Badge Improvements */
    .badge {
        padding: 6px 12px;
        font-weight: 500;
        font-size: 0.75rem;
        letter-spacing: 0.3px;
        border-radius: 6px;
    }

    /* Image Thumbnails */
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

    /* Button Group */
    .btn-group-sm .btn {
        padding: 6px 12px;
        font-size: 0.8125rem;
        border-radius: 6px;
        transition: all 0.2s ease;
    }

    .btn-group-sm .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    }

    /* Receipt Image Wrapper */
    .receipt-image-wrapper {
        text-align: center;
        border: 1px solid #4a4a4a;
        border-radius: 8px;
        padding: 15px;
        background-color: #2b2b2b;
        margin: 10px 0;
    }

    .receipt-image-wrapper img {
        max-width: 100%;
        max-height: 250px;
        object-fit: contain;
        cursor: pointer;
        border-radius: 6px;
        transition: transform 0.2s ease;
    }

    .receipt-image-wrapper img:hover {
        transform: scale(1.02);
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
    }

    .modal-body {
        padding: 24px;
        background: #3a3a3a;
    }

    /* Form Controls */
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

    .row.mb-4 > .col-12 .card,
    .row.mb-4 > [class*="col-"] .card {
        animation: slideUp 0.5s ease-out forwards;
        opacity: 0;
    }

    .row.mb-4 > [class*="col-"]:nth-child(1) .card { animation-delay: 0.1s; }
    .row.mb-4 > [class*="col-"]:nth-child(2) .card { animation-delay: 0.15s; }
    .row.mb-4 > [class*="col-"]:nth-child(3) .card { animation-delay: 0.2s; }
    .row.mb-4 > [class*="col-"]:nth-child(4) .card { animation-delay: 0.25s; }

    /* Editable Amount Highlight */
    .editable-amount {
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .editable-amount:hover {
        transform: scale(1.05);
        box-shadow: 0 2px 8px rgba(181, 142, 83, 0.3);
    }

    /* Alert Styling */
    .alert {
        border-radius: 8px;
        border: 1px solid;
        padding: 14px 18px;
        background: #2b2b2b;
        color: #d4d4d4;
    }

    .alert-info {
        border-color: rgba(13, 202, 240, 0.3);
        background: rgba(13, 202, 240, 0.1);
    }

    .alert-light {
        border-color: #4a4a4a;
        background: #3a3a3a;
    }

    /* Manual Services Section */
    .manual-services-section {
        background-color: #2b2b2b;
        padding: 16px;
        border-radius: 8px;
        margin-top: 12px;
        border: 1px solid #4a4a4a;
    }

    .manual-service-row {
        background: #3a3a3a;
        border: 1px solid #4a4a4a;
        border-radius: 6px;
        padding: 12px;
        margin-bottom: 10px;
    }

    /* Service Cards in Modal */
    .card.border-primary,
    .card.border-success,
    .card.border-info {
        border-width: 2px !important;
        background: #3a3a3a;
    }

    .card.border-primary {
        border-color: #0d6efd !important;
    }

    .card.border-success {
        border-color: #198754 !important;
    }

    .card.border-info {
        border-color: #0dcaf0 !important;
    }

    /* Button Styling */
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

    .btn-outline-secondary {
        border-color: #4a4a4a;
        color: #d4d4d4;
        background: transparent;
    }

    .btn-outline-secondary:hover {
        background: #4a4a4a;
        border-color: #B58E53;
        color: #ffffff;
    }

    .btn-outline-primary {
        border-color: #B58E53;
        color: #B58E53;
        background: transparent;
    }

    .btn-outline-primary:hover {
        background: linear-gradient(135deg, #B58E53 0%, #D4A574 100%);
        border-color: #B58E53;
        color: #ffffff;
    }

    /* Close button for modals */
    .btn-close {
        filter: invert(1);
    }

    /* Form check styling */
    .form-check-input {
        background-color: #2b2b2b;
        border-color: #4a4a4a;
    }

    .form-check-input:checked {
        background-color: #B58E53;
        border-color: #B58E53;
    }

    .form-check-input:focus {
        border-color: #B58E53;
        box-shadow: 0 0 0 0.25rem rgba(181, 142, 83, 0.25);
    }

    .form-check-label {
        color: #d4d4d4;
    }

    /* Status badges color adjustments for dark theme */
    .badge.bg-warning {
        color: #000;
    }

    .badge.bg-light {
        background-color: #4a4a4a !important;
        color: #ffffff;
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
        color: #fff !important;
    }

    /* Card headers with color backgrounds */
    .card-header.bg-primary {
        background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%) !important;
    }

    .card-header.bg-success {
        background: linear-gradient(135deg, #198754 0%, #146c43 100%) !important;
    }

    .card-header.bg-info {
        background: linear-gradient(135deg, #0dcaf0 0%, #0aa2c0 100%) !important;
    }

    

    /* Change placeholder color for textareas inside the Edit Services Modal */
    #editServicesModal textarea::placeholder {
        color: #C9C8C7 !important;
        opacity: 1; /* Ensures the color is solid on Firefox */
    }

    /* Optional: Ensure the actual text you type is also light */
    #editServicesModal textarea {
        color: #C9C8C7 !important; 
    }

</style>
</head>

<body>
    <?php include('../includes/sidebar.php'); ?>

    <main>
            <h1 class="h2">Booking Management</h1>
        

        <!-- Quick Stats -->
        <div class="row mb-4">
            <div class="col-12 col-md-6 col-lg-3 mb-4 mb-lg-0">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title text-muted">Total Bookings</h5>
                        <h2 class="mt-2 mb-0"><?php echo mysqli_num_rows($result); ?></h2>
                    </div>
                </div>
            </div>
            <?php
            $status_counts = array(
                'Pending' => 0,
                'In Progress' => 0,
                'Completed' => 0
            );
            mysqli_data_seek($result, 0);
            while ($row = mysqli_fetch_assoc($result)) {
                $bs = isset($row['booking_status']) ? $row['booking_status'] : (isset($row['status']) ? $row['status'] : null);
                if ($bs === 'Pending') {
                    $status_counts['Pending']++;
                } elseif ($bs === 'Accepted') {
                    $status_counts['In Progress']++;
                } elseif ($bs === 'Completed') {
                    $status_counts['Completed']++;
                } 
            }
            mysqli_data_seek($result, 0);
            ?>
            <div class="col-12 col-md-6 col-lg-3 mb-4 mb-lg-0">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title text-warning">Pending</h5>
                        <h2 class="mt-2 mb-0"><?php echo $status_counts['Pending']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-3 mb-4 mb-lg-0">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title text-info">In Progress</h5>
                        <h2 class="mt-2 mb-0"><?php echo $status_counts['In Progress']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-3 mb-4 mb-lg-0">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title text-success">Completed</h5>
                        <h2 class="mt-2 mb-0"><?php echo $status_counts['Completed']; ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bookings Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list"></i> All Bookings</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-striped" id="bookingsTable">
                        <thead>
                            <tr>
                                <th><i class="fas fa-hashtag"></i> Reference</th>
                                <th><i class="fas fa-user"></i> Customer</th>
                                <th><i class="fa-solid fa-shoe-prints"></i> Services</th>
                                <th><i class="fas fa-image"></i> Shoe 1</th>
                                <th><i class="fas fa-image"></i> Shoe 2</th>
                                <th><i class="fas fa-receipt"></i> Receipt</th>
                                <th><i class="fas fa-money-bill"></i> Amount</th>
                                <th><i class="fas fa-calendar"></i> Date</th>
                                <th><i class="fas fa-check-circle"></i> Booking Status</th>
                                <th>Tracking Status</th>
                                <th><i class="fas fa-cogs"></i> Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if (mysqli_num_rows($result) > 0) {
                                while ($row = mysqli_fetch_assoc($result)): 
                            ?>
                            <tr class="align-middle">
                                <td>
                                    <strong class="text-white"><?php echo isset($row['booking_reference']) ? htmlspecialchars($row['booking_reference']) : 'N/A'; ?></strong>
                                </td>
                                <td>
                                    <div class="customer-info">
                                        <strong><?php echo isset($row['customer_name']) ? htmlspecialchars($row['customer_name']) : 'N/A'; ?></strong><br>
                                        <small class="text-muted">
                                            <i class="fas fa-envelope"></i> <?php echo isset($row['customer_email']) ? htmlspecialchars($row['customer_email']) : 'N/A'; ?>
                                        </small><br>
                                        <small class="text-muted">
                                            <i class="fas fa-phone"></i> <?php echo isset($row['customer_phone']) ? htmlspecialchars($row['customer_phone']) : 'N/A'; ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $booking_id = $row['id'];
                                    $services_query = "SELECT * FROM booking_shoes WHERE booking_id = '$booking_id'";
                                    $services_result = mysqli_query($conn, $services_query);
                                    $services_list = [];
                                    if ($services_result && mysqli_num_rows($services_result) > 0) {
                                        while ($shoe_row = mysqli_fetch_assoc($services_result)) {
                                            foreach ($service_map as $key => $service) {
                                                if (isset($shoe_row[$key]) && $shoe_row[$key] == 1) {
                                                    $services_list[] = $service['name'];
                                                }
                                            }
                                        }
                                    }
                                    $unique_services = array_unique($services_list);
                                    if (!empty($unique_services)) {
                                        foreach ($unique_services as $service_name) {
                                            echo '<span class="badge bg-secondary me-1 mb-1" style="font-size: 0.7rem; padding: 0.35rem 0.5rem;">' . htmlspecialchars($service_name) . '</span>';
                                        }
                                    } else {
                                        echo '<span class="text-muted">N/A</span>';
                                    }
                                    ?>
                                </td>
                                <td class="text-center">
                                    <?php if(isset($row['shoe1_image']) && !empty($row['shoe1_image'])): ?>
                                        <img src="<?php echo htmlspecialchars(resolveImageUrl($row['shoe1_image'])); ?>" 
                                             alt="Shoe 1" 
                                             class="img-thumbnail" 
                                             style="max-width: 80px; height: 80px; object-fit: cover; cursor: pointer;" 
                                             title="Click to view full image"
                                             onclick="viewFullImage('<?php echo htmlspecialchars($row['shoe1_image']); ?>')">
                                    <?php else: ?>
                                        <div class="img-thumbnail d-flex align-items-center justify-content-center mx-auto" 
                                             style="width: 80px; height: 80px; background-color: #f8f9fa;">
                                            <small class="text-muted"><i class="fas fa-image"></i></small>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if(isset($row['shoe2_image']) && !empty($row['shoe2_image'])): ?>
                                        <img src="<?php echo htmlspecialchars(resolveImageUrl($row['shoe2_image'])); ?>" 
                                             alt="Shoe 2" 
                                             class="img-thumbnail" 
                                             style="max-width: 80px; height: 80px; object-fit: cover; cursor: pointer;" 
                                             title="Click to view full image"
                                             onclick="viewFullImage('<?php echo htmlspecialchars($row['shoe2_image']); ?>')">
                                    <?php else: ?>
                                        <div class="img-thumbnail d-flex align-items-center justify-content-center mx-auto" 
                                             style="width: 80px; height: 80px; background-color: #f8f9fa;">
                                            <small class="text-muted"><i class="fas fa-image"></i></small>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php 
                                    if(isset($row['receipt_image']) && !empty($row['receipt_image'])): 
                                        $imageUrl = resolveImageUrl($row['receipt_image']);
                                        $encodedUrl = htmlspecialchars(json_encode($imageUrl), ENT_QUOTES, 'UTF-8');
                                    ?>
                                        <?php if (!empty($imageUrl)): ?>
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-secondary" 
                                                    onclick="event.stopPropagation(); viewReceiptImage(<?php echo $encodedUrl; ?>)"
                                                    title="Click to view receipt image">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted" title="Could not resolve image path"><i class="fas fa-exclamation-triangle"></i> Invalid Path</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted"><i class="fas fa-receipt"></i> No receipt</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-success editable-amount" 
                                          style="cursor: pointer; font-size: 0.9em;" 
                                          title="Click to edit amount"
                                          data-ref="<?php echo htmlspecialchars($row['booking_reference']); ?>"
                                          data-amount="<?php echo isset($row['total_amount']) ? htmlspecialchars($row['total_amount']) : '0.00'; ?>">
                                        ₱<?php echo isset($row['total_amount']) ? number_format($row['total_amount'], 2) : '0.00'; ?>
                                    </span><br>
                                    <small class="text-muted"><?php echo isset($row['payment_method']) ? htmlspecialchars($row['payment_method']) : 'N/A'; ?></small>
                                </td>
                                <td>
                                    <small class="text-muted"><?php echo isset($row['created_at']) ? date('M d, Y', strtotime($row['created_at'])) : 'N/A'; ?></small>
                                </td>
                                <td>
                                    <?php 
                                        $booking_status = ['Pending', 'Accepted', 'Completed', 'Cancelled'];
                                        $status = in_array($row['booking_status'], $booking_status) ? htmlspecialchars($row['booking_status']) : 'N/A';
                                        $badgeClass = 'secondary';
                                        if ($status === 'Pending') $badgeClass = 'warning';
                                        elseif ($status === 'Accepted') $badgeClass = 'info';
                                        elseif ($status === 'Completed') $badgeClass = 'success';
                                        elseif ($status === 'Cancelled') $badgeClass = 'danger';
                                    ?>
                                    <span class="badge bg-<?php echo $badgeClass; ?> cursor-pointer" style="cursor: pointer; font-size: 0.9em;" title="Click to update" onclick="event.stopPropagation(); openStatusManagementModal('<?php echo htmlspecialchars($row['booking_reference']); ?>', '<?php echo htmlspecialchars($row['booking_status']); ?>', '<?php echo htmlspecialchars($row['tracking_status']); ?>')">
                                        <i class="fas fa-info-circle"></i> <?php echo $status; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                        $tracking_status = ['Process', 'Ready', 'Confirmation', 'Completed', 'Service Rated'];
                                        $tstatus = in_array($row['tracking_status'], $tracking_status) ? htmlspecialchars($row['tracking_status']) : 'N/A';
                                        $tbadgeClass = 'secondary';
                                        if ($tstatus === 'Process') $tbadgeClass = 'primary';
                                        elseif ($tstatus === 'Ready') $tbadgeClass = 'info';
                                        elseif ($tstatus === 'Confirmation') $tbadgeClass = 'warning';
                                        elseif ($tstatus === 'Completed') $tbadgeClass = 'success';
                                        elseif ($tstatus === 'Service Rated') $tbadgeClass = 'dark';
                                    ?>
                                    <span class="badge bg-<?php echo $tbadgeClass; ?>" style="font-size: 0.9em;">
                                        <i class="fas fa-box"></i> <?php echo $tstatus; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group" onclick="event.stopPropagation();">
                                        <button type="button" 
                                                class="btn btn-info" 
                                                title="View Details"
                                                onclick="event.stopPropagation(); openBookingDetailsModal('<?php echo htmlspecialchars($row['booking_reference']); ?>')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="button" 
                                                class="btn btn-primary" 
                                                title="Update Status"
                                                onclick="event.stopPropagation(); openStatusManagementModal('<?php echo htmlspecialchars($row['booking_reference']); ?>', '<?php echo htmlspecialchars($row['booking_status']); ?>', '<?php echo htmlspecialchars($row['tracking_status']); ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button"
                                                class="btn btn-warning"
                                                title="Edit Services"
                                                onclick="event.stopPropagation(); openEditServicesModal('<?php echo htmlspecialchars($row['booking_reference']); ?>')">
                                            <i class="fas fa-concierge-bell"></i>
                                        </button>
                                        <!-- ✅ ADD THIS DELETE BUTTON HERE -->
        <button type="button"
                class="btn btn-danger"
                title="Delete Booking"
                onclick="event.stopPropagation(); deleteBooking('<?php echo htmlspecialchars($row['booking_reference']); ?>')">
            <i class="fas fa-trash"></i>
        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php 
                                endwhile;
                            } else {
                                echo '<tr><td colspan="11" class="text-center text-muted py-4"><i class="fas fa-inbox"></i> No bookings found</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Booking Details Modal -->
    <div class="modal fade" id="bookingDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-info-circle"></i> Booking Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="bookingDetailsContent">
                    <p class="text-center text-muted">Loading...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Services Modal -->
    <div class="modal fade" id="editServicesModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-concierge-bell"></i> Edit Services</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editServicesForm">
                        <input type="hidden" id="editServicesBookingRef" name="booking_reference">
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            <strong>Instructions:</strong> Select standard services for each shoe, then add custom manual services if needed. Each shoe has its own manual services section.
                        </div>
                        
                        <div id="servicesCheckboxes" class="row">
                            <!-- Shoe service checkboxes and manual services will be loaded here by JavaScript -->
                        </div>
                        
                        <hr>
                        
                        <div class="d-flex justify-content-between align-items-center bg-light p-3 rounded">
                            <div>
                                <h5 class="mb-0 text-primary">
                                    <i class="fas fa-calculator"></i> 
                                    New Total: <strong>₱<span id="newTotalAmount">0.00</span></strong>
                                </h5>
                                <small class="text-muted">This includes all selected services and manual services</small>
                            </div>
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save"></i> Save All Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- React Modal Root -->
    <div id="status-modal-root"></div>

    <script>
        let bookingsTable;
        
        document.addEventListener('DOMContentLoaded', function() {
            bookingsTable = $('#bookingsTable').DataTable({
                order: [[7, 'desc']],
                pageLength: 10,
                responsive: true,
                language: {
                    search: "Filter bookings:",
                    lengthMenu: "Show _MENU_ entries"
                }
            });
            
            $(document).on('click', '.shoe-images img', function(e) {
                e.preventDefault();
                e.stopPropagation();
                viewFullImage(this.src);
            });
        });

        // Handle inline amount editing
        $('#bookingsTable tbody').on('click', '.editable-amount', function(e) {
            e.stopPropagation();
            
            const span = $(this);
            const originalAmount = span.data('amount');
            const bookingRef = span.data('ref');
            
            if (span.find('input').length > 0) return;

            const input = $('<input type="number" class="form-control form-control-sm" style="width: 100px;" />');
            input.val(originalAmount);
            
            span.hide().after(input);
            input.focus();

            const saveOrCancel = (e) => {
                if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== 'Escape') {
                    return;
                }

                const newAmount = input.val();
                
                if (e.key === 'Escape' || newAmount === originalAmount) {
                    input.remove();
                    span.show();
                    return;
                }

                const formData = new FormData();
                formData.append('booking_reference', bookingRef);
                formData.append('total_amount', newAmount);
                
                span.text('Saving...').show();
                input.remove();

                fetch('api/update_booking_status.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const formattedAmount = parseFloat(newAmount).toFixed(2);
                        span.text(`₱${formattedAmount}`);
                        span.data('amount', newAmount);
                    } else {
                        alert('Error updating amount: ' + data.message);
                        span.text(`₱${parseFloat(originalAmount).toFixed(2)}`);
                    }
                })
                .catch(err => {
                    alert('A network error occurred.');
                    span.text(`₱${parseFloat(originalAmount).toFixed(2)}`);
                });
            };

            input.on('blur', saveOrCancel);
            input.on('keydown', saveOrCancel);
        });

        function viewReceiptImage(src) {
            if (!src || src.trim() === '') {
                alert('No image to display');
                return;
            }
            
            const resolvedUrl = resolveImageUrl(src);
            
            if (resolvedUrl.startsWith('data:')) {
                const w = window.open();
                w.document.write(`
                    <html>
                        <head><title>Image</title></head>
                        <body style="background-color: #000; margin: 0; height: 100vh; display: flex; align-items: center; justify-content: center;">
                            <img src="${resolvedUrl}" style="max-width: 100%; max-height: 100vh; object-fit: contain;">
                        </body>
                    </html>
                `);
                return;
            }
            
            window.open(resolvedUrl, '_blank');
        }

        function viewFullImage(src) {
            if (!src || src.trim() === '') {
                alert('No image to display');
                return;
            }
            
            const resolvedUrl = resolveImageUrl(src);
            
            if (resolvedUrl.startsWith('data:')) {
                const w = window.open();
                w.document.write('<html><body style="margin:0; padding:0;"><img src="' + resolvedUrl + '" style="max-width:100%; max-height:100%; object-fit: contain;"></body></html>');
                return;
            }
            
            window.open(resolvedUrl, '_blank');
        }

        function resolveImageUrl(filename) {
            if (!filename || filename.trim() === '') return '';
            filename = filename.trim();

            if (/^(https?:\/\/|data:)/i.test(filename)) {
                return filename;
            }

            if (filename.startsWith('iVBOR')) {
                const base64Data = filename.replace(/\s/g, ''); 
                return 'data:image/png;base64,' + base64Data;
            }

            if (filename.startsWith('/9j/')) {
                const base64Data = filename.replace(/\s/g, ''); 
                return 'data:image/jpeg;base64,' + base64Data;
            }

            let cleanFilename = filename.replace(/^.*[\\\/]/, '');
            cleanFilename = cleanFilename.replace(/^uploads\//, '');

            if (filename.includes('receipt_image') || 
                filename.includes('receipt_') || 
                filename.includes('proof_service_') || 
                filename.includes('proof_delivery_') ||
                filename.includes('proof_of_payment') ||
                filename.includes('details_')) {
                return '../uploads/' + cleanFilename;
            }

            if (filename.includes('uploads/shoes/')) {
                return 'http://localhost/ishoekicks/' + filename;
            }

            return 'http://localhost/ishoekicks/uploads/shoes/' + cleanFilename;
        }

        window.openBookingDetailsModal = function(bookingReference) {
            $.ajax({
                url: 'api/get_booking.php',
                method: 'GET',
                data: { booking_reference: bookingReference },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        var booking = response.data;
                        
                        var proofOfPaymentHtml = '';
                        if (booking.proof_of_payment) {
                            const proofUrl = resolveImageUrl(booking.proof_of_payment);
                            proofOfPaymentHtml = `
                                <div class="receipt-image-wrapper">
                                    <label class="form-label d-block text-center"><strong>Proof of Payment</strong></label>
                                    <img src="${proofUrl}" 
                                         alt="Proof of Payment" 
                                         class="d-block mx-auto"
                                         style="max-height: 250px; object-fit: contain; cursor: pointer;"
                                         title="Click to view full size"
                                         onclick="viewReceiptImage('${proofUrl}')"
                                         onerror="this.parentElement.innerHTML='<p class=\"text-center text-danger\"><i class=\"fas fa-exclamation-circle\"></i> Failed to load image</p>'">
                                </div>
                            `;
                        } else {
                            proofOfPaymentHtml = '<p><strong>Proof of Payment:</strong> <span class="text-muted">Not uploaded</span></p>';
                        }

                        var receiptImageHtml = '';
                        if (booking.receipt_image) {
                            const receiptUrl = resolveImageUrl(booking.receipt_image);
                            receiptImageHtml = `
                                <div class="receipt-image-wrapper">
                                    <label class="form-label d-block text-center"><strong>Receipt Image</strong></label>
                                    <img src="${receiptUrl}" 
                                         alt="Receipt" 
                                         class="d-block mx-auto"
                                         style="max-height: 250px; object-fit: contain; cursor: pointer;"
                                         title="Click to view full size"
                                         onclick="viewReceiptImage('${receiptUrl}')" />
                                </div>
                            `;
                        } else {
                            receiptImageHtml = '<p><strong>Receipt Image:</strong> <span class="text-muted">Not uploaded</span></p>';
                        }
                        
                        var detailsHtml = `
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Reference:</strong> ${booking.booking_reference}</p>
                                    <p><strong>Customer:</strong> ${booking.customer_name}</p>
                                    <p><strong>Email:</strong> ${booking.customer_email}</p>
                                    <p><strong>Phone:</strong> ${booking.customer_phone || 'N/A'}</p>
                                    <p><strong>Address:</strong> ${booking.customer_address || 'N/A'}</p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Payment Method:</strong> ${booking.payment_method}</p>
                                    <p><strong>Amount:</strong> ₱${parseFloat(booking.total_amount).toFixed(2)}</p>
                                    <p><strong>Booking Status:</strong> <span class="badge bg-primary">${booking.booking_status}</span></p>
                                    <p><strong>Tracking Status:</strong> <span class="badge bg-info">${booking.tracking_status || 'N/A'}</span></p>
                                    <p><strong>Created:</strong> ${new Date(booking.created_at).toLocaleDateString()}</p>
                                </div>
                            </div>
                            <hr>
                            <h6>Shoe & Service Information</h6>
                            <div id="shoesAndServicesContainer">Loading...</div>
                            <hr>
                            ${proofOfPaymentHtml}
                            ${receiptImageHtml}
                        `;
                        $('#bookingDetailsContent').html(detailsHtml);
                        $('#bookingDetailsModal').modal('show');
                        
                        $.ajax({
                            url: 'api/get_booking_shoes.php',
                            method: 'GET',
                            data: { booking_reference: bookingReference },
                            dataType: 'json',
                            success: function(shoesResponse) {
                                if (shoesResponse.success && shoesResponse.shoes && shoesResponse.shoes.length > 0) {
                                    let shoesHtml = '';
                                    const serviceMap = <?php echo json_encode($service_map); ?>;
                                    
                                    shoesResponse.shoes.forEach((shoe, index) => {
                                        const shoeNum = index + 1;
                                        const shoeInfo = [shoe.shoe_brand, shoe.shoe_model, shoe.shoe_color, 'Size: ' + shoe.shoe_size].filter(Boolean).join(' ');
                                        
                                        shoesHtml += `<div class="mb-3 p-3 border rounded bg-light">`;
                                        shoesHtml += `<h6 class="text-primary mb-2"><i class="fas fa-shoe-prints"></i> Shoe ${shoeNum}</h6>`;
                                        shoesHtml += `<p class="mb-2"><strong>Details:</strong> ${shoeInfo}</p>`;
                                        shoesHtml += `<p class="mb-2"><strong>Services:</strong> `;
                                        
                                        let services = [];
                                        for (const [key, service] of Object.entries(serviceMap)) {
                                            if (shoe[key] == 1) {
                                                services.push(`<span class="badge bg-secondary me-1">${service.name}</span>`);
                                            }
                                        }
                                        
                                        if (services.length > 0) {
                                            shoesHtml += services.join(' ');
                                        } else {
                                            shoesHtml += '<span class="text-muted">No services selected</span>';
                                        }
                                        
                                        shoesHtml += `</p>`;
                                        
                                        if (shoe.other_specify && shoe.other_specify.trim() !== '') {
                                            shoesHtml += `<p class="mb-0"><strong>Other Specify:</strong> <span class="text-info">${shoe.other_specify}</span></p>`;
                                        } else {
                                            shoesHtml += `<p class="mb-0"><strong>Other Specify:</strong> <span class="text-muted">None</span></p>`;
                                        }
                                        
                                        shoesHtml += `</div>`;
                                    });
                                    
                                    $('#shoesAndServicesContainer').html(shoesHtml);
                                } else {
                                    $('#shoesAndServicesContainer').html('<p class="text-muted">No shoe details available</p>');
                                }
                            },
                            error: function() {
                                $('#shoesAndServicesContainer').html('<p class="text-muted">Error loading shoe details</p>');
                            }
                        });
                    } else {
                        alert('Error loading booking details: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    alert('Error loading booking details');
                }
            });
        };

        const serviceMap = <?php echo json_encode($service_map); ?>;
        let editServicesModal;

        document.addEventListener('DOMContentLoaded', function() {
            editServicesModal = new bootstrap.Modal(document.getElementById('editServicesModal'));
        });

      // --- Main Function to Open Modal ---
    function openEditServicesModal(bookingReference) {
        $('#editServicesBookingRef').val(bookingReference);
        const checkboxesContainer = $('#servicesCheckboxes');
        
        // Show Loading State
        checkboxesContainer.html(`
            <div class="col-12 text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-2 text-muted">Loading shoe details...</p>
            </div>
        `);

        // Fetch Shoe Data
        $.ajax({
            url: 'api/get_booking_shoes.php',
            method: 'GET',
            data: { booking_reference: bookingReference },
            dataType: 'json',
            success: function(shoesResponse) {
                if (shoesResponse.success && shoesResponse.shoes) {
                    let checkboxesHtml = '';
                    
                    // Style definitions for the dynamic headers
                    const colors = [
                        {bg: 'bg-primary', border: 'border-primary'}, // Blue
                        {bg: 'bg-success', border: 'border-success'}, // Green
                        {bg: 'bg-info', border: 'border-info'}        // Teal
                    ];

                    // --- DEFINING THE GROUPS TO MATCH YOUR IMAGE ---
                    const serviceGroups = {
                        'CLEANING SERVICES': ['regular_cleaning', 'vip_cleaning'],
                        'OTHER SERVICES (It comes with VIP cleaning)': ['repainting', 'restitching', 'restoring'],
                        'REGLUING': ['partial_regluing', 'single_sole', 'sole_pair', 'multiple_sole', 'rush_fee', 'deodorizing']
                    };

                    // Loop through each shoe
                    shoesResponse.shoes.forEach((shoe, index) => {
                        const shoeNum = index + 1;
                        const shoeInfo = [shoe.shoe_brand, shoe.shoe_model, shoe.shoe_color, 'Size: ' + shoe.shoe_size]
                                         .filter(Boolean).join(' • ');
                        const color = colors[index % colors.length];
                        
                        // Start Card
                        checkboxesHtml += `
                            <div class="col-12 mb-4">
                                <div class="card ${color.border}" style="border-width: 2px;">
                                    <div class="card-header ${color.bg} text-white">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0 fw-bold"><i class="fas fa-shoe-prints"></i> Shoe</h6>
                                            <span class="badge" style="background-color: rgba(33, 37, 41, 0.6); color: #C9C8C7;">
                                                ${shoeInfo || 'No details'}
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="card-body bg-dark text-light">
                                        <div class="row">
                                            <div class="col-12">`;

                        // --- LOOP THROUGH GROUPS ---
                        for (const [groupTitle, keys] of Object.entries(serviceGroups)) {
                            // Group Header
                            checkboxesHtml += `
                                <h6 class="text-uppercase fw-bold mt-3 mb-2" style="color: #B58E53; font-size: 0.85rem; letter-spacing: 0.5px;">
                                    ${groupTitle}
                                </h6>`;

                            // Loop Services in this Group
                            keys.forEach(key => {
                                if (serviceMap[key]) {
                                    const service = serviceMap[key];
                                    const isChecked = shoe[key] == 1 ? 'checked' : '';
                                    
                                    checkboxesHtml += `
                                        <div class="form-check mb-2">
                                            <input class="form-check-input service-checkbox" 
                                                   type="checkbox" 
                                                   name="shoe${shoeNum}_services[]" 
                                                   value="${key}" 
                                                   id="shoe${shoeNum}_service_${key}" 
                                                   data-price="${service.price}"
                                                   data-shoe="${shoeNum}"
                                                   style="border-color: #B58E53;"
                                                   ${isChecked}>
                                            <label class="form-check-label d-flex justify-content-between w-100" for="shoe${shoeNum}_service_${key}">
                                                <span>${service.name}</span>
                                                <span class="text-muted">₱${parseFloat(service.price).toFixed(2)}</span>
                                            </label>
                                        </div>`;
                                }
                            });

                            // Special Case: Add Textarea under "OTHER SERVICES" group
                            if (groupTitle.startsWith('OTHER SERVICES')) {
                                const otherSpecifyValue = shoe.other_specify || '';
                                checkboxesHtml += `
                                    <div class="ms-4 mb-3">
                                        <label class="form-label small text-muted mb-1">
                                            <i class="fas fa-pencil-alt"></i> If Others (Please Specify):
                                        </label>
                                        <textarea class="form-control form-control-sm bg-dark text-light border-secondary" 
                                                  name="shoe${shoeNum}_other_specify" 
                                                  rows="2" 
                                                  style="color: #C9C8C7 !important;"
                                                  placeholder="Specific instructions...">${otherSpecifyValue}</textarea>
                                    </div>`;
                            }
                        }

                        checkboxesHtml += `
                                            </div> </div> <hr class="border-secondary my-3">

                                        <div class="manual-services-section border border-secondary rounded p-3" style="background: #333;">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h6 class="mb-0 text-info">
                                                    <i class="fas fa-plus-circle"></i> Manual / Custom Services
                                                </h6>
                                                <button type="button" class="btn btn-sm btn-outline-info add-manual-service-btn" data-shoe="${shoeNum}">
                                                    <i class="fas fa-plus"></i> Add Service
                                                </button>
                                            </div>
                                            <div id="shoe${shoeNum}_manualServicesContainer" class="manual-services-container"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>`;
                    });

                    checkboxesContainer.html(checkboxesHtml);
                    loadManualServices(bookingReference);
                    updateTotalAmount();
                    editServicesModal.show();
                }
            },
            error: function() {
                alert('Error fetching shoe services.');
            }
        });
    }

        function loadManualServices(bookingReference) {
            $.ajax({
                url: 'api/get_manual_services.php',
                method: 'GET',
                data: { booking_reference: bookingReference },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.services && response.services.length > 0) {
                        response.services.forEach(function(service) {
                            const shoeNum = service.shoe_number || 1;
                            addManualServiceRow(shoeNum, service.id, service.service_name, service.service_price);
                        });
                    }
                }
            });
        }

        function addManualServiceRow(shoeNum, id = '', name = '', price = '') {
            const container = $(`#shoe${shoeNum}_manualServicesContainer`);
            const rowId = id || 'new_' + Date.now();
            
            let serviceOptions = '<option value="">-- Select Service --</option>';
            for (const key in serviceMap) {
                const service = serviceMap[key];
                const selected = name === service.name ? 'selected' : '';
                serviceOptions += `<option value="${service.name}" data-price="${service.price}" ${selected}>${service.name} - ₱${service.price}</option>`;
            }
            
            const row = $(`
                <div class="manual-service-row mb-2 p-2 border rounded bg-light" data-id="${rowId}" data-shoe="${shoeNum}">
                    <div class="row align-items-center g-2">
                        <div class="col-md-6">
                            <select class="form-select form-select-sm manual-service-dropdown" data-shoe="${shoeNum}">
                                ${serviceOptions}
                            </select>
                        </div>
                        <div class="col-md-4">
                            <input type="number" 
                                   class="form-control form-control-sm manual-service-price" 
                                   name="manual_services[${rowId}][price]" 
                                   min="0" 
                                   step="0.01" 
                                   placeholder="Price" 
                                   value="${price}">
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-danger btn-sm w-100 remove-manual-service" data-id="${rowId}">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <input type="hidden" class="manual-service-name" name="manual_services[${rowId}][name]" value="${name}">
                    <input type="hidden" name="manual_services[${rowId}][shoe_number]" value="${shoeNum}">
                    ${id ? `<input type="hidden" name="manual_services[${rowId}][id]" value="${id}">` : ''}
                </div>
            `);
            
            row.find('.manual-service-dropdown').val(name);
            
            container.append(row);
            
            row.find('.manual-service-price').on('change', updateTotalAmount);
        }

        $(document).on('change', '.manual-service-dropdown', function() {
            const row = $(this).closest('.manual-service-row');
            const selectedOption = $(this).find('option:selected');
            const selectedValue = $(this).val();
            const nameInput = row.find('.manual-service-name');
            const priceInput = row.find('.manual-service-price');
            
            if (selectedValue === 'custom') {
                nameInput.show().val('').focus();
                priceInput.val('');
            } else if (selectedValue) {
                const serviceName = selectedValue;
                const servicePrice = selectedOption.data('price');
                nameInput.hide().val(serviceName);
                priceInput.val(servicePrice);
                updateTotalAmount();
            } else {
                nameInput.hide().val('');
                priceInput.val('');
                updateTotalAmount();
            }
        });

        $(document).on('click', '.remove-manual-service', function() {
            const rowId = $(this).data('id');
            $(`.manual-service-row[data-id="${rowId}"]`).remove();
            updateTotalAmount();
        });

        $(document).on('click', '.add-manual-service-btn', function() {
            const shoeNum = $(this).data('shoe');
            addManualServiceRow(shoeNum);
        });

        $(document).on('change', '.service-checkbox, .manual-service-price', updateTotalAmount);

        function updateTotalAmount() {
            let total = 0;
            
            $('.service-checkbox:checked').each(function() {
                total += parseFloat($(this).data('price'));
            });

            $('.manual-service-price').each(function() {
                const price = parseFloat($(this).val()) || 0;
                total += price;
            });

            $('#newTotalAmount').text(total.toFixed(2));
        }

        $('#editServicesForm').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const submitBtn = form.find('button[type="submit"]');
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');

            const formData = new FormData(this);

            fetch('api/update_booking_services.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Services updated successfully!');
                    editServicesModal.hide();
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => {
                console.error('Update services error:', err);
                alert('An error occurred while saving.');
            })
            .finally(() => {
                submitBtn.prop('disabled', false).html('<i class="fas fa-save"></i> Save All Changes');
            });
        });
    </script>

    <!-- React Status Update Modal Component -->
    <script type="text/babel">
        const { useState, useEffect, useRef } = React;

        function StatusUpdateModal() {
            const [bookingReference, setBookingReference] = useState(null);
            const [bookingStatus, setBookingStatus] = useState('');
            const [rejectionReason, setRejectionReason] = useState('');
            const [proofOfPayment, setProofOfPayment] = useState(null);
            
            const modalRef = useRef(null);
            const bootstrapModalRef = useRef(null);

            useEffect(() => {
                if (modalRef.current) {
                    bootstrapModalRef.current = new bootstrap.Modal(modalRef.current);
                }

                const handleOpenModal = (e) => {
                    const { bookingReference, bookingStatus } = e.detail;
                    setBookingReference(bookingReference);
                    setBookingStatus(bookingStatus);
                    setRejectionReason('');
                    setProofOfPayment(null);

                    fetch(`api/get_booking.php?booking_reference=${bookingReference}`)
                        .then(res => res.json())
                        .then(data => {
                            if (data.success && data.data.proof_of_payment) {
                                setProofOfPayment(resolveImageUrl(data.data.proof_of_payment));
                            }
                        })
                        .catch(err => console.error('Error fetching booking:', err));

                    bootstrapModalRef.current.show();
                };

                document.addEventListener('openStatusModal', handleOpenModal);

                return () => {
                    document.removeEventListener('openStatusModal', handleOpenModal);
                };
            }, []);

            const handleSubmit = (e) => {
                e.preventDefault();
                const formData = new FormData();
                formData.append('booking_reference', bookingReference);
                formData.append('booking_status', bookingStatus);
                if (bookingStatus === 'Cancelled') formData.append('rejection_reason', rejectionReason);

                fetch('api/update_booking_status.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert('✅ Booking status updated successfully!');
                        bootstrapModalRef.current.hide();
                        location.reload();
                    } else {
                        alert(`❌ Error: ${data.message}`);
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    alert('Error updating booking status');
                });
            };

            return (
                <div className="modal fade" id="statusManagementModalReact" ref={modalRef} tabIndex="-1">
                    <div className="modal-dialog modal-lg">
                        <div className="modal-content">
                            <div className="modal-header">
                                <h5 className="modal-title"><i className="fas fa-edit"></i> Update Booking Status</h5>
                                <button type="button" className="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div className="modal-body">
                                <form onSubmit={handleSubmit}>
                                    <div className="mb-3">
                                        <label htmlFor="newBookingStatus" className="form-label"><strong>Booking Status *</strong></label>
                                        <select className="form-select" value={bookingStatus} onChange={e => setBookingStatus(e.target.value)} required>
                                            <option value="">Select Status</option>
                                            <option value="Pending">Pending</option>
                                            <option value="Accepted">Accepted</option>
                                            <option value="Completed">Completed</option>
                                            <option value="Cancelled">Cancelled</option>
                                        </select>
                                    </div>

                                    {proofOfPayment && (
                                        <div className="mb-3">
                                            <label className="form-label"><strong>Customer's Proof of Payment</strong></label>
                                            <div className="receipt-image-wrapper">
                                                <img src={proofOfPayment} 
                                                     alt="Proof of Payment" 
                                                     style={{ maxWidth: '100%', maxHeight: '250px', objectFit: 'contain', cursor: 'pointer' }}
                                                     title="Click to view full size"
                                                     onClick={() => viewReceiptImage(proofOfPayment)}
                                                     onError={(e) => e.target.parentElement.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-circle"></i> Failed to load image</span>'} />
                                            </div>
                                        </div>
                                    )}

                                    {bookingStatus === 'Cancelled' && (
                                        <div className="mb-3">
                                            <label htmlFor="rejectionReason" className="form-label"><strong>Rejection Reason *</strong></label>
                                            <textarea className="form-control" 
                                                      id="rejectionReason"
                                                      value={rejectionReason} 
                                                      onChange={e => setRejectionReason(e.target.value)} 
                                                      rows="3"
                                                      placeholder="Explain why this booking is being cancelled..."
                                                      required></textarea>
                                        </div>
                                    )}

                                    <button type="submit" className="btn btn-primary w-100">
                                        <i className="fas fa-save"></i> Update Booking Status
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            );
        }
        // Add this function to handle booking deletion
function deleteBooking(bookingReference) {
    // Show confirmation dialog
    if (!confirm(`⚠️ Are you sure you want to delete booking ${bookingReference}?\n\nThis action cannot be undone and will permanently delete:\n• Booking record\n• Associated shoe records\n• Manual services\n• Tracking updates`)) {
        return;
    }

    // Show loading state
    const loadingOverlay = $('<div class="position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center" style="background: rgba(0,0,0,0.5); z-index: 9999;"><div class="spinner-border text-light" role="status"><span class="visually-hidden">Loading...</span></div></div>');
    $('body').append(loadingOverlay);

    // Create form data
    const formData = new FormData();
    formData.append('booking_reference', bookingReference);

    // Send delete request
    fetch('api/delete_booking.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        loadingOverlay.remove();
        
        if (data.success) {
            // Show success message
            alert('✅ Booking deleted successfully!');
            
            // Reload the page to refresh the table
            location.reload();
        } else {
            // Show error message
            alert('❌ Error: ' + data.message);
        }
    })
    .catch(error => {
        loadingOverlay.remove();
        console.error('Delete error:', error);
        alert('❌ An error occurred while deleting the booking. Please try again.');
    });
}

        ReactDOM.render(<StatusUpdateModal />, document.getElementById('status-modal-root'));

        window.openStatusManagementModal = (bookingReference, bookingStatus) => {
            const event = new CustomEvent('openStatusModal', { detail: { bookingReference, bookingStatus } });
            document.dispatchEvent(event);
        };
    </script>


</div>
</body>
</html>