<?php
require_once 'config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

// Get input data
$data = json_decode(file_get_contents('php://input'), true);
$log_id = $data['log_id'] ?? null;

if (empty($log_id)) {
    echo json_encode(['success' => false, 'message' => 'Log ID is missing.']);
    exit;
}

try {
    $user_id = $_SESSION['id'];

    // Prepare and execute the delete statement
    // IMPORTANT: Also check for user_id to ensure users can only delete their own logs
    $sql = "DELETE FROM time_logs WHERE id = :log_id AND user_id = :user_id";
    $stmt = $pdo->prepare($sql);

    $stmt->bindParam(':log_id', $log_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Log entry deleted successfully.']);
        } else {
            // This means the log_id didn't exist or didn't belong to the user
            echo json_encode(['success' => false, 'message' => 'Log entry not found or permission denied.']);
        }
    } else {
        throw new Exception("Failed to execute delete statement.");
    }
} catch (Exception $e) {
    // It's better not to expose detailed SQL errors to the client.
    error_log("Error in delete_log_ajax.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while deleting the log entry.']);
}
?>
