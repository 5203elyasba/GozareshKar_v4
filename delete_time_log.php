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
if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$log_id = $data['log_id'] ?? null;
$user_id = $_SESSION['id'];

if (!$log_id) {
    echo json_encode(['success' => false, 'message' => 'Log ID is missing.']);
    exit;
}

try {
    // We must ensure the user is deleting their OWN log.
    $sql = "DELETE FROM time_logs WHERE id = :log_id AND user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'log_id' => $log_id,
        'user_id' => $user_id
    ]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Log entry deleted successfully.']);
    } else {
        // This can happen if the user tries to delete a log that isn't theirs,
        // or if the log_id is invalid.
        echo json_encode(['success' => false, 'message' => 'Could not delete log entry. It may not exist or you may not have permission.']);
    }

} catch (Exception $e) {
    error_log("Delete Log Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A database error occurred.']);
}
?>
