<?php

session_start();
require_once('../dbconnection.php');

if (!isset($_SESSION['instructor_id']) || !isset($_POST['class_id'])) {
    exit('Unauthorized access');
}

$class_id = $_POST['class_id'];
$instructor_id = $_SESSION['instructor_id'];

// Verify this class belongs to the instructor
$stmt = $conn->prepare("
    SELECT s.*, ce.enrollment_date 
    FROM students s
    JOIN class_enrollments ce ON s.id = ce.student_id
    JOIN class_sessions cs ON ce.class_id = cs.id
    WHERE cs.id = ? AND cs.instructor_id = ?
    ORDER BY s.full_name
");
$stmt->bind_param("ii", $class_id, $instructor_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<table class="table">
    <thead>
        <tr>
            <th>Student ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Enrollment Date</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($student = $result->fetch_assoc()): ?>
        <tr>
            <td><?php echo htmlspecialchars($student['student_id']); ?></td>
            <td><?php echo htmlspecialchars($student['full_name']); ?></td>
            <td><?php echo htmlspecialchars($student['email']); ?></td>
            <td><?php echo date('M d, Y', strtotime($student['enrollment_date'])); ?></td>
            <td>
                <button class="btn btn-danger btn-sm" 
                        onclick="removeEnrollment(<?php echo $class_id; ?>, <?php echo $student['id']; ?>)">
                    <i class="fas fa-user-minus"></i> Remove
                </button>
            </td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>