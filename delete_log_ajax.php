<?php
require_once 'config.php';

header('Content-Type: application/json');

// --- Authentication & Basic Validation ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['log_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid payload. Log ID is required.']);
    exit;
}

// --- Data Sanitization & Deletion ---
$log_id = (int)$data['log_id'];
$user_id = $_SESSION['id'];

if ($log_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Log ID.']);
    exit;
}

try {
    // Ensure the user can only delete their own logs
    $sql = "DELETE FROM time_logs WHERE id = :log_id AND user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':log_id' => $log_id,
        ':user_id' => $user_id
    ]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Log entry deleted successfully.']);
    } else {
        // This could happen if the log_id doesn't exist or doesn't belong to the user
        echo json_encode(['success' => false, 'message' => 'Log entry not found or permission denied.']);
    }

} catch (Exception $e) {
    error_log('AJAX Delete Log Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A database error occurred.']);
}
?>