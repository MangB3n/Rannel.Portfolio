<?php

session_start();
require_once('../dbconnection.php');

if (!isset($_SESSION['instructor_id']) || !isset($_POST['class_id']) || !isset($_POST['student_id'])) {
    exit(json_encode(['success' => false, 'message' => 'Invalid request']));
}

$class_id = $_POST['class_id'];
$student_id = $_POST['student_id'];
$instructor_id = $_SESSION['instructor_id'];

// Verify ownership and delete enrollment
$stmt = $conn->prepare("
    DELETE ce FROM class_enrollments ce
    JOIN class_sessions cs ON ce.class_id = cs.id
    WHERE ce.class_id = ? 
    AND ce.student_id = ?
    AND cs.instructor_id = ?
");

$stmt->bind_param("iii", $class_id, $student_id, $instructor_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error removing enrollment']);
}

?>

<script>
// Add to the existing script section
function removeEnrollment(classId, studentId) {
    if (confirm('Are you sure you want to remove this student from the class?')) {
        $.ajax({
            url: 'remove_enrollment.php',
            type: 'POST',
            data: { 
                class_id: classId,
                student_id: studentId 
            },
            success: function(response) {
                try {
                    const result = JSON.parse(response);
                    if (result.success) {
                        viewEnrolledStudents(classId); // Refresh the list
                    } else {
                        alert(result.message || 'Error removing enrollment');
                    }
                } catch (e) {
                    alert('Error processing response');
                }
            }
        });
    }
}
</script>