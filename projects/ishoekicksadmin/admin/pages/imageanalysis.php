<?php
session_start();
require_once '../includes/database.php';

if(!isset($_SESSION["admin_logged_in"]) || $_SESSION["admin_logged_in"] !== true){
    header("location: ../auth/login.php");
    exit;
}

function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

// --- Statistics ---
$total_analyses = $conn->query("SELECT COUNT(*) as count FROM shoe_analysis")->fetch_assoc()['count'];
$gemini_analyses = $conn->query("SELECT COUNT(*) as count FROM shoe_analysis WHERE analysis_method = 'gemini'")->fetch_assoc()['count'];
$local_analyses = $conn->query("SELECT COUNT(*) as count FROM shoe_analysis WHERE analysis_method = 'local'")->fetch_assoc()['count'];

$popular_query = $conn->query("SELECT shoe_type, COUNT(*) as count FROM shoe_analysis GROUP BY shoe_type ORDER BY count DESC LIMIT 1");
$popular_shoe = $popular_query->num_rows > 0 ? $popular_query->fetch_assoc()['shoe_type'] : 'N/A';

// --- Filtering & Pagination ---
$entries_per_page = isset($_GET['entries']) ? intval($_GET['entries']) : 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $entries_per_page;
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$filter_type = isset($_GET['type']) ? sanitizeInput($_GET['type']) : '';
$filter_method = isset($_GET['method']) ? sanitizeInput($_GET['method']) : '';

$where_clauses = [];
$params = [];
$types = '';

if ($search) {
    $where_clauses[] = "(s.user_email LIKE ? OR s.shoe_type LIKE ? OR s.id LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

if ($filter_type) {
    $where_clauses[] = "s.shoe_type = ?";
    $params[] = $filter_type;
    $types .= 's';
}

if ($filter_method) {
    $where_clauses[] = "s.analysis_method = ?";
    $params[] = $filter_method;
    $types .= 's';
}

$where_sql = $where_clauses ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

$count_query = "SELECT COUNT(*) as count FROM shoe_analysis s $where_sql";
if ($params) {
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $total_records = $count_stmt->get_result()->fetch_assoc()['count'];
} else {
    $total_records = $conn->query($count_query)->fetch_assoc()['count'];
}

$total_pages = ceil($total_records / $entries_per_page);

$query = "SELECT s.id, s.user_email, s.shoe_type, s.confidence_score, s.analysis_method, s.image_path, s.created_at,
          r.condition_status, r.total_price, r.detected_issues, r.recommended_services, r.estimated_days
          FROM shoe_analysis s
          LEFT JOIN analysis_recommendations r ON s.id = r.analysis_id
          $where_sql
          ORDER BY s.created_at DESC 
          LIMIT ? OFFSET ?";

$types_final = $types . 'ii';
$params_final = $params;
$params_final[] = $entries_per_page;
$params_final[] = $offset;

$stmt = $conn->prepare($query);
if ($params_final) {
    $stmt->bind_param($types_final, ...$params_final);
}
$stmt->execute();
$analyses = $stmt->get_result();

$shoe_types = $conn->query("SELECT DISTINCT shoe_type FROM shoe_analysis ORDER BY shoe_type");

$start_entry = $offset + 1;
$end_entry = min($offset + $entries_per_page, $total_records);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Analysis</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
    body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
    background-color: #2b2b2b;
    margin: 0;
}

.main-content {
    margin-left: 250px;
    margin-top: 50px;
    padding: 35px 40px;
    min-height: 100vh;
    background-color: #2b2b2b;
}
.main-content h1{
    text-align: center;
}

@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
        padding: 20px;
    }
}

h1 {
    font-size: 2.75rem;
    font-weight: 600;
    color: #ffffff;
    margin-bottom: 28px;
}

/* Stats Cards - Match Dashboard Style */
.stats-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 32px;
}

.stats-cards .card {
    background: linear-gradient(135deg, #B58E53 0%, #D4A574 100%);
    border: 1px solid rgba(181, 142, 83, 0.3);
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    padding: 24px;
    text-align: center;
    transition: all 0.2s ease;
}

.stats-cards .card:nth-child(2) {
    background: linear-gradient(135deg, #8B7355 0%, #A0826D 100%);
}

.stats-cards .card:nth-child(3) {
    background: linear-gradient(135deg, #9C7A4E 0%, #B8956A 100%);
}

.stats-cards .card:nth-child(4) {
    background: linear-gradient(135deg, #8B7355 0%, #A0826D 100%);
}

.stats-cards .card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(181, 142, 83, 0.4);
}

.stats-cards .card h3 {
    font-size: 0.875rem;
    font-weight: 600;
    color: #ffffff;
    margin-bottom: 12px;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
}

.stats-cards .card p {
    font-size: 2rem;
    font-weight: 700;
    color: #ffffff;
    margin: 0;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

/* Filters */
.filters {
    background: #3a3a3a;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 24px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    border: 1px solid rgba(181, 142, 83, 0.2);
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
}

.filters input, .filters select {
    padding: 10px 14px;
    border: 1px solid #4a4a4a;
    border-radius: 8px;
    font-size: 0.9rem;
    transition: all 0.2s ease;
    flex: 1;
    min-width: 150px;
    background: #2b2b2b;
    color: #ffffff;
}

.filters input:focus, .filters select:focus {
    border-color: #B58E53;
    outline: none;
    box-shadow: 0 0 0 3px rgba(181, 142, 83, 0.1);
    background: #2b2b2b;
}

.filters button {
    padding: 10px 24px;
    background: linear-gradient(135deg, #B58E53 0%, #D4A574 100%);
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.2s ease;
}

.filters button:hover {
    background: linear-gradient(135deg, #D4A574 0%, #B58E53 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(181, 142, 83, 0.4);
}

/* Table Container */
.table-container {
    background: #3a3a3a;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    border: 1px solid rgba(181, 142, 83, 0.2);
    overflow: hidden;
    margin-bottom: 24px;
}

.analyses-table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

.analyses-table th {
    background-color: #2b2b2b;
    border-bottom: 2px solid #4a4a4a;
    padding: 14px 12px;
    text-align: left;
    font-weight: 600;
    font-size: 0.875rem;
    color: #B58E53;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.analyses-table td {
    border-bottom: 1px solid #4a4a4a;
    padding: 16px 12px;
    color: #d4d4d4;
    vertical-align: middle;
    background-color: #3a3a3a;
}

.analyses-table tr:hover td {
    background-color: #454545;
}

.thumbnail {
    width: 50px;
    height: 50px;
    object-fit: cover;
    border-radius: 6px;
    cursor: pointer;
    border: 2px solid #4a4a4a;
    transition: all 0.2s ease;
}

.thumbnail:hover {
    border-color: #B58E53;
    transform: scale(1.1);
    box-shadow: 0 4px 12px rgba(181, 142, 83, 0.3);
}

/* Badge Styling */
.badge {
    padding: 6px 12px;
    font-weight: 500;
    font-size: 0.75rem;
    letter-spacing: 0.3px;
    border-radius: 6px;
    border: none;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.badge.bg-info {
    background: linear-gradient(135deg, #0dcaf0 0%, #0aa2c0 100%);
    color: white;
}

.badge.bg-secondary {
    background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
    color: white;
}

.badge.bg-success {
    background: linear-gradient(135deg, #198754 0%, #157347 100%);
    color: white;
}

/* Button Styling */
.btn-view {
    background: linear-gradient(135deg, #B58E53 0%, #D4A574 100%);
    color: white;
    border: none;
    padding: 6px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.8125rem;
    font-weight: 500;
    transition: all 0.2s ease;
}

.btn-view:hover {
    background: linear-gradient(135deg, #D4A574 0%, #B58E53 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(181, 142, 83, 0.4);
}

/* Pagination */
.pagination-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: #3a3a3a;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    border: 1px solid rgba(181, 142, 83, 0.2);
}

.pagination-container p {
    color: #d4d4d4;
}

.pagination {
    display: flex;
    gap: 6px;
}

.page-btn {
    padding: 8px 14px;
    border: 1px solid #4a4a4a;
    background-color: #2b2b2b;
    color: #d4d4d4;
    cursor: pointer;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
    transition: all 0.2s ease;
}

.page-btn:hover {
    border-color: #B58E53;
    color: #B58E53;
    background-color: #3a3a3a;
}

.page-btn.active {
    background: linear-gradient(135deg, #B58E53 0%, #D4A574 100%);
    color: white;
    border-color: #B58E53;
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.7);
}

.modal-content {
    background-color: #3a3a3a;
    margin: 5% auto;
    padding: 0;
    border: none;
    width: 90%;
    max-width: 600px;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.5);
    border: 1px solid rgba(181, 142, 83, 0.2);
}

.modal-header {
    background: linear-gradient(135deg, #3a3a3a 0%, #4a4a4a 100%);
    padding: 20px 24px;
    border-bottom: 1px solid rgba(181, 142, 83, 0.3);
    border-radius: 12px 12px 0 0;
}

.modal-header h2 {
    color: #B58E53;
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
}

.modal-body {
    padding: 24px;
    background: #3a3a3a;
}

.close-modal {
    color: #999;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    line-height: 20px;
    transition: color 0.2s;
}

.close-modal:hover {
    color: #ffffff;
}

.detail-row {
    margin-bottom: 20px;
}

.detail-label {
    font-weight: 600;
    color: #B58E53;
    margin-bottom: 8px;
    font-size: 0.9rem;
}

.detail-row > div:not(.detail-label) {
    background: #2b2b2b;
    padding: 12px;
    border-radius: 6px;
    border: 1px solid #4a4a4a;
    color: #d4d4d4;
}

.detail-row ul {
    margin: 8px 0 0 0;
    padding-left: 20px;
    color: #d4d4d4;
}

.detail-row ul li {
    color: #d4d4d4;
}

/* Text Colors */
strong {
    color: #ffffff;
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

.stats-cards .card {
    animation: slideUp 0.5s ease-out forwards;
    opacity: 0;
}

.stats-cards .card:nth-child(1) { animation-delay: 0.1s; }
.stats-cards .card:nth-child(2) { animation-delay: 0.15s; }
.stats-cards .card:nth-child(3) { animation-delay: 0.2s; }
.stats-cards .card:nth-child(4) { animation-delay: 0.25s; }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'?>
    
    <div class="main-content">
        <h1>Image Analysis</h1>
        
        <div class="stats-cards">
            <div class="card">
                <h3>Total Analysis</h3>
                <p><?php echo $total_analyses; ?></p>
            </div>
            <div class="card">
                <h3>Gemini AI</h3>
                <p><?php echo $gemini_analyses; ?></p>
            </div>
            <div class="card">
                <h3>Local Analysis</h3>
                <p><?php echo $local_analyses; ?></p>
            </div>
            <div class="card">
                <h3>Popular Shoe</h3>
                <p><?php echo htmlspecialchars(ucfirst($popular_shoe)); ?></p>
            </div>
        </div>

        <div class="filters">
            <input type="text" id="searchInput" placeholder="Search by Email, Type, or ID" value="<?php echo htmlspecialchars($search); ?>">
            <select id="typeFilter">
                <option value="">All Shoe Types</option>
                <?php while ($type_row = $shoe_types->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($type_row['shoe_type']); ?>" <?php if ($filter_type === $type_row['shoe_type']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars(ucfirst($type_row['shoe_type'])); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <select id="methodFilter">
                <option value="">All Methods</option>
                <option value="gemini" <?php if ($filter_method === 'gemini') echo 'selected'; ?>>Gemini</option>
                <option value="local" <?php if ($filter_method === 'local') echo 'selected'; ?>>Local</option>
            </select>
            <select id="entriesPerPage">
                <option value="10" <?php if ($entries_per_page == 10) echo 'selected'; ?>>10 per page</option>
                <option value="25" <?php if ($entries_per_page == 25) echo 'selected'; ?>>25 per page</option>
                <option value="50" <?php if ($entries_per_page == 50) echo 'selected'; ?>>50 per page</option>
                <option value="100" <?php if ($entries_per_page == 100) echo'selected'; ?>>100 per page</option>
            </select>
            <button id="applyFilters"><i class="fas fa-filter"></i> Apply</button>
        </div>

        <div class="table-container">
            <table class="analyses-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User Email</th>
                        <th>Shoe Type</th>
                        <th>Confidence</th>
                        <th>Condition</th>
                        <th>Method</th>
                        <th>Image</th>
                        <th>Est. Price</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($analyses->num_rows > 0): ?>
                        <?php while ($row = $analyses->fetch_assoc()): 
                            $image_filename = $row['image_path'];
                            $physical_path = __DIR__ . "/api/uploads/analysis/" . $image_filename;
                            $web_path = "api/uploads/analysis/" . $image_filename;
                            $showImg = (!empty($image_filename) && file_exists($physical_path)) ? $web_path : "";
                        ?>
                            <tr>
                                <td><strong><?php echo $row['id']; ?></strong></td>
                                <td><?php echo htmlspecialchars($row['user_email']); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($row['shoe_type'])); ?></td>
                                <td><span class="badge bg-info"><?php echo number_format($row['confidence_score'] * 100, 0) . '%'; ?></span></td>
                                <td><?php echo htmlspecialchars(ucfirst($row['condition_status'] ?? 'N/A')); ?></td>
                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars(ucfirst($row['analysis_method'])); ?></span></td>
                                <td>
                                    <?php if ($showImg): ?>
                                        <img src="<?php echo $showImg; ?>" alt="Shoe" class="thumbnail" onclick="window.open(this.src, '_blank')">
                                    <?php else: ?>
                                        <span style="color: #999; font-size: 0.8em;">No Image</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo ($row['total_price'] > 0) ? '<strong>₱' . number_format($row['total_price'], 2) . '</strong>' : '-'; ?>
                                </td>
                                <td>
                                    <button class="btn-view view-details" 
                                        data-id="<?php echo $row['id']; ?>"
                                        data-issues='<?php echo htmlspecialchars($row['detected_issues'] ?? '[]'); ?>'
                                        data-services='<?php echo htmlspecialchars($row['recommended_services'] ?? '[]'); ?>'
                                        data-days="<?php echo htmlspecialchars($row['estimated_days'] ?? 'N/A'); ?>"
                                    ><i class="fas fa-eye"></i> View</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="9" style="text-align: center; color: #6c757d; padding: 40px;"><i class="fas fa-inbox"></i> No records found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="pagination-container">
            <p style="margin: 0; color: #6c757d;">Showing <?php echo $start_entry; ?> to <?php echo $end_entry; ?> of <?php echo $total_records; ?> entries</p>
            
            <div class="pagination">
                <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                    <button class="page-btn <?php if ($p == $page) echo 'active'; ?>" data-page="<?php echo $p; ?>">
                        <?php echo $p; ?>
                    </button>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close-modal">&times;</span>
                <h2><i class="fas fa-chart-line"></i> Analysis Details #<span id="modalId"></span></h2>
            </div>
            <div class="modal-body">
                <div class="detail-row">
                    <div class="detail-label"><i class="fas fa-exclamation-triangle"></i> Detected Issues:</div>
                    <div id="modalIssues"></div>
                </div>

                <div class="detail-row">
                    <div class="detail-label"><i class="fas fa-tools"></i> Recommended Services:</div>
                    <ul id="modalServices"></ul>
                </div>

                <div class="detail-row">
                    <div class="detail-label"><i class="fas fa-clock"></i> Estimated Turnaround:</div>
                    <div id="modalDays"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function applyFilters(page = 1) {
            const search = document.getElementById('searchInput').value;
            const type = document.getElementById('typeFilter').value;
            const method = document.getElementById('methodFilter').value;
            const entries = document.getElementById('entriesPerPage').value;
            window.location.href = `imageanalysis.php?page=${page}&search=${encodeURIComponent(search)}&type=${encodeURIComponent(type)}&method=${encodeURIComponent(method)}&entries=${entries}`;
        }

        document.getElementById('applyFilters').addEventListener('click', function() {
            applyFilters(1);
        });

        document.querySelectorAll('.page-btn').forEach(button => {
            button.addEventListener('click', function() {
                applyFilters(this.getAttribute('data-page'));
            });
        });

        const modal = document.getElementById('detailsModal');
        const closeBtn = document.getElementsByClassName('close-modal')[0];

        document.querySelectorAll('.view-details').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('modalId').textContent = this.dataset.id;
                document.getElementById('modalDays').textContent = this.dataset.days;
                
                const issuesDiv = document.getElementById('modalIssues');
                issuesDiv.innerHTML = '';
                try {
                    const issues = JSON.parse(this.dataset.issues);
                    if(issues.length > 0) {
                        issuesDiv.textContent = issues.join(', ');
                    } else {
                        issuesDiv.textContent = "No specific issues detected.";
                    }
                } catch(e) { issuesDiv.textContent = "Error loading issues."; }

                const servicesList = document.getElementById('modalServices');
                servicesList.innerHTML = '';
                try {
                    const services = JSON.parse(this.dataset.services);
                    if(services.length > 0) {
                        services.forEach(s => {
                            const li = document.createElement('li');
                            const name = s.name ? s.name : s;
                            const price = s.price ? ` (₱${s.price})` : '';
                            li.textContent = name + price;
                            servicesList.appendChild(li);
                        });
                    } else {
                        servicesList.innerHTML = '<li>No services recommended.</li>';
                    }
                } catch(e) { servicesList.innerHTML = '<li>Error loading services.</li>'; }

                modal.style.display = "block";
            });
        });

        closeBtn.onclick = function() {
            modal.style.display = "none";
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>
</html>
<?php
$stmt->close();
?>