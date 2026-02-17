<?php
session_start();
require_once '../includes/database.php';

if(!isset($_SESSION["admin_logged_in"]) || $_SESSION["admin_logged_in"] !== true){
    header("location: ../auth/login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];

// Fetch all customers with their Facebook accounts
$query = "
    SELECT 
        u.id,
        u.name,
        u.email,
        'N/A' AS facebook_id,
        u.contact,
        u.created_at
    FROM users u

    UNION

    SELECT 
        uf.id,
        uf.name,
        uf.email,
        uf.facebook_id,
        uf.contact,
        uf.created_at
    FROM users_facebook uf

    ORDER BY created_at DESC
";

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
    <title>Customer Management - iShoeKicks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

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
        h2 {
            font-size: 2.75rem;
            font-weight: 600;
            color: #ffffff;
            margin: 0;
            text-align: center;
        }

        /* Card Styling */
        .card {
            background: #3a3a3a;
            border: 1px solid rgba(181, 142, 83, 0.2);
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            overflow: hidden;
            margin-top: 70px;
        }

        .card-body {
            padding: 24px;
            background: #3a3a3a;
        }

        /* Button Styling */
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

        .btn-info:hover {
            background: linear-gradient(135deg, #0aa2c0 0%, #0dcaf0 100%);
            box-shadow: 0 4px 16px rgba(13, 202, 240, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            border: none;
            color: #ffffff;
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #c82333 0%, #dc3545 100%);
            box-shadow: 0 4px 16px rgba(220, 53, 69, 0.4);
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
            color: #fff !important;
        }

        .text-muted {
            color: #fff !important;
        }

        /* Badge Styling */
        .badge {
            padding: 6px 12px;
            font-weight: 500;
            font-size: 0.75rem;
            letter-spacing: 0.3px;
            border-radius: 6px;
        }

        .bg-info {
            background: linear-gradient(135deg, #0dcaf0 0%, #0aa2c0 100%) !important;
        }

        .bg-success {
            background: linear-gradient(135deg, #198754 0%, #157347 100%) !important;
        }

        .bg-warning {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%) !important;
            color: #000 !important;
        }

        .bg-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%) !important;
        }

        .bg-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%) !important;
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

        /* Close button for modals */
        .btn-close {
            filter: invert(1);
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

        /* Customer Info Section */
        .customer-info-section {
            background: #2b2b2b;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            border: 1px solid #4a4a4a;
        }

        .customer-info-section h6 {
            color: #B58E53;
            font-weight: 600;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid rgba(181, 142, 83, 0.3);
        }

        .customer-info-section p {
            margin-bottom: 8px;
            color: #d4d4d4;
        }

        .customer-info-section strong {
            color: #B58E53;
        }

        /* List Group Styling */
        .list-group-item {
            border: 1px solid #4a4a4a;
            border-radius: 6px !important;
            margin-bottom: 8px;
            transition: all 0.2s ease;
            background: #2b2b2b;
            color: #d4d4d4;
        }

        .list-group-item:hover {
            background-color: #454545;
            transform: translateX(4px);
            border-color: #B58E53;
        }

        .list-group-item strong {
            color: #B58E53;
        }

        /* Additional dark theme adjustments */
        hr {
            border-color: #4a4a4a;
            opacity: 1;
        }

        small.text-muted {
            color: #999 !important;
        }

        .container-fluid {
            color: #d4d4d4;
        }

        p {
            color: #d4d4d4;
        }
    </style>
</head>
<body>
    <?php include('../includes/sidebar.php'); ?>

    <main>
        <div class="container-fluid">
            
                <h2>Customer Management</h2>
            

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="customersTable">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-hashtag"></i> ID</th>
                                    <th><i class="fas fa-user"></i> Name</th>
                                    <th><i class="fas fa-envelope"></i> Email</th>
                                    <th><i class="fab fa-facebook"></i> Facebook Account</th>
                                    <th><i class="fas fa-phone"></i> Contact</th>
                                    <th><i class="fas fa-shopping-bag"></i> Total Bookings</th>
                                    <th><i class="fas fa-calendar"></i> Joined Date</th>
                                    <th><i class="fas fa-cogs"></i> Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (mysqli_num_rows($result) > 0) {
                                    while ($row = mysqli_fetch_assoc($result)) {
                                        $customerId = mysqli_real_escape_string($conn, $row['id']);
                                        $bookingsQuery = "SELECT COUNT(*) as total FROM bookings WHERE user_id = '$customerId'";
                                        $bookingsResult = mysqli_query($conn, $bookingsQuery);
                                        $bookingsCount = mysqli_fetch_assoc($bookingsResult)['total'];

                                        echo "<tr>";
                                        echo "<td><strong class='text-primary'>".$row['id']."</strong></td>";
                                        echo "<td>".htmlspecialchars($row['name'])."</td>";
                                        echo "<td>".htmlspecialchars($row['email'])."</td>";
                                        echo "<td>".htmlspecialchars($row['facebook_id'] ?? 'N/A')."</td>";
                                        echo "<td>".htmlspecialchars($row['contact'])."</td>";
                                        echo "<td><span class='badge bg-info'>".$bookingsCount."</span></td>";
                                        echo "<td><small class='text-muted'>".date('M d, Y', strtotime($row['created_at']))."</small></td>";
                                        echo "<td>
                                            <div class='btn-group btn-group-sm' role='group'>
                                                <button class='btn btn-info' onclick='viewCustomer(".$row['id'].")' title='View Details'>
                                                    <i class='fas fa-eye'></i>
                                                </button>
                                                <button class='btn btn-danger' onclick='deleteCustomer(".$row['id'].")' title='Delete Customer'>
                                                    <i class='fas fa-trash'></i>
                                                </button>
                                            </div>
                                        </td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='8' class='text-center text-muted py-4'><i class='fas fa-users-slash'></i> No customers found</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- View Customer Modal -->
    <div class="modal fade" id="viewCustomerModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-circle"></i> Customer Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="customerDetails">
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-spinner fa-spin fa-2x"></i>
                        <p class="mt-2">Loading customer details...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
    $(document).ready(function() {
        $('#customersTable').DataTable({
            order: [[6, 'desc']],
            pageLength: 25,
            language: {
                search: "Search customers:"
            }
        });
    });

    function viewCustomer(id) {
        $.get('api/customers.php?id=' + id, function(response) {
            if (response.success) {
                const customer = response.data;
                let html = `
                    <div class="row">
                        <div class="col-md-6">
                            <div class="customer-info-section">
                                <h6><i class="fas fa-user"></i> Personal Information</h6>
                                <p><strong>ID:</strong> ${customer.id}</p>
                                <p><strong>Name:</strong> ${customer.name}</p>
                                <p><strong>Email:</strong> ${customer.email}</p>
                                <p><strong>Phone:</strong> ${customer.phone || customer.contact || 'N/A'}</p>
                                <p><strong>Facebook ID:</strong> ${customer.facebook_id || 'N/A'}</p>
                                <p><strong>Joined:</strong> ${new Date(customer.created_at).toLocaleDateString()}</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="customer-info-section">
                                <h6><i class="fas fa-history"></i> Booking History</h6>
                                <div id="bookingHistory">
                                    <div class="text-center text-muted">
                                        <i class="fas fa-spinner fa-spin"></i>
                                        <p class="small">Loading bookings...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                $('#customerDetails').html(html);
                loadBookingHistory(id);
                $('#viewCustomerModal').modal('show');
            } else {
                alert('Error: ' + response.error);
            }
        }).fail(function() {
            alert('An error occurred while fetching customer details.');
        });
    }

    function loadBookingHistory(customerId) {
        $.ajax({
            url: 'api/bookings.php',
            type: 'GET',
            data: { user_id: customerId },
            success: function(response) {
                console.log('API Response:', response);
                
                if (response.success && response.data && response.data.length > 0) {
                    let bookingsHtml = '<ul class="list-group">';
                    response.data.forEach(booking => {
                        let statusClass = 'bg-secondary';
                        if (booking.status === 'Completed') statusClass = 'bg-success';
                        else if (booking.status === 'Pending') statusClass = 'bg-warning';
                        else if (booking.status === 'Cancelled') statusClass = 'bg-danger';
                        else if (booking.status === 'Confirmed') statusClass = 'bg-info';
                        
                        bookingsHtml += `
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong><i class="fas fa-shoe-prints"></i> ${booking.service_type || 'Service'}</strong><br>
                                        <small class="text-muted">${booking.shoe_details || 'No details'}</small><br>
                                        <small class="text-muted"><i class="far fa-calendar"></i> ${booking.booking_date || 'N/A'}</small>
                                    </div>
                                    <span class="badge ${statusClass}">${booking.status || 'Unknown'}</span>
                                </div>
                            </li>
                        `;
                    });
                    bookingsHtml += '</ul>';
                    $('#bookingHistory').html(bookingsHtml);
                } else {
                    $('#bookingHistory').html('<p class="text-muted text-center"><i class="fas fa-inbox"></i> No bookings found</p>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                console.error('Response:', xhr.responseText);
                $('#bookingHistory').html('<p class="text-danger text-center"><i class="fas fa-exclamation-triangle"></i> Error loading bookings</p>');
            }
        });
    }

    function deleteCustomer(id) {
        if (confirm('⚠️ WARNING: This will permanently delete:\n\n• Customer account\n• All bookings\n• All chat messages\n• All archived data\n\nThis action CANNOT be undone!\n\nAre you absolutely sure?')) {
            const deleteBtn = event.target.closest('button');
            const originalHtml = deleteBtn.innerHTML;
            deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            deleteBtn.disabled = true;

            $.ajax({
                type: 'DELETE',
                url: 'api/customers.php',
                data: JSON.stringify({ id: id }),
                contentType: 'application/json',
                success: function(response) {
                    if (response.success) {
                        alert('✅ Customer deleted successfully!');
                        location.reload();
                    } else {
                        alert('❌ Error: ' + response.error);
                        deleteBtn.innerHTML = originalHtml;
                        deleteBtn.disabled = false;
                    }
                },
                error: function(xhr, status, error) {
                    alert('❌ Network error: ' + error);
                    deleteBtn.innerHTML = originalHtml;
                    deleteBtn.disabled = false;
                }
            });
        }
    }
    </script>
</body>
</html>