<?php
require_once 'config.php';
require_once 'JalaliDate.php';

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

// --- Data Sanitization & Validation ---
$log_date_jalali = $data['log_date_jalali'] ?? null;
$day_type = $data['day_type'] ?? null;
$user_id = $_SESSION['id'];

if (!$log_date_jalali || !$day_type) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

// Convert Jalali to Gregorian for DB
$gregorian_date_obj = JalaliDate::fromJalaliToDateTime($log_date_jalali);
if (!$gregorian_date_obj) {
    echo json_encode(['success' => false, 'message' => 'Invalid Jalali date format.']);
    exit;
}
$log_date_gregorian = $gregorian_date_obj->format('Y-m-d');

try {
    // Use INSERT ... ON DUPLICATE KEY UPDATE for an atomic operation
    // This requires a UNIQUE key on (user_id, log_date) in the day_properties table.
    // Let's assume this unique key exists.
    $sql = "INSERT INTO day_properties (user_id, log_date, day_type, status)
            VALUES (:user_id, :log_date, :day_type, 'approved')
            ON DUPLICATE KEY UPDATE day_type = :day_type";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':user_id' => $user_id,
        ':log_date' => $log_date_gregorian,
        ':day_type' => $day_type
    ]);

    // If the day is set to a non-working type, we should remove any time logs.
    $non_working_types = ['leave', 'official_holiday_no_work', 'friday'];
    if (in_array($day_type, $non_working_types)) {
        $delete_sql = "DELETE FROM time_logs WHERE user_id = :user_id AND log_date = :log_date";
        $delete_stmt = $pdo->prepare($delete_sql);
        $delete_stmt->execute([':user_id' => $user_id, ':log_date' => $log_date_gregorian]);
    }

    echo json_encode(['success' => true, 'message' => 'Day type updated successfully.']);

} catch (Exception $e) {
    error_log('AJAX Update Day Type Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A database error occurred.']);
}
?>