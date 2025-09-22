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
$log_id = isset($data['log_id']) && !empty($data['log_id']) ? (int)$data['log_id'] : null;
$log_date_jalali = $data['log_date'] ?? null;
$time = $data['time'] ?? null;
$type = $data['type'] ?? null; // 'start' or 'end'
$user_id = $_SESSION['id'];

if (!$log_date_jalali || !$time || !$type) {
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

// Extract Jalali parts for insertion
list($jalali_year, $jalali_month, $jalali_day) = explode('/', $log_date_jalali);

try {
    $pdo->beginTransaction();

    if ($log_id) {
        // UPDATE existing record
        $column_to_update = ($type === 'start') ? 'start_time' : 'end_time';
        $sql = "UPDATE time_logs SET $column_to_update = :time WHERE id = :id AND user_id = :user_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'time' => $time,
            'id' => $log_id,
            'user_id' => $user_id // Ensure user can only update their own logs
        ]);
        $new_log_id = $log_id;
    } else {
        // INSERT new record
        $start_time = ($type === 'start') ? $time : '00:00:00';
        $end_time = ($type === 'end') ? $time : '00:00:00';

        // Check for an existing partial record for this user/date to avoid duplicates
        $check_sql = "SELECT id FROM time_logs WHERE user_id = :user_id AND log_date = :log_date AND start_time = '00:00:00' AND log_type = 'work'";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute(['user_id' => $user_id, 'log_date' => $log_date_gregorian]);
        $existing_partial = $check_stmt->fetch();

        if ($existing_partial && $type === 'end') {
            // Update the existing partial record with the end time
            $sql = "UPDATE time_logs SET end_time = :time WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['time' => $end_time, 'id' => $existing_partial['id']]);
            $new_log_id = $existing_partial['id'];
        } else {
            $sql = "INSERT INTO time_logs (user_id, log_date, start_time, end_time, log_type, jalali_year, jalali_month, jalali_day) VALUES (:user_id, :log_date, :start_time, :end_time, 'work', :jalali_year, :jalali_month, :jalali_day)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'user_id' => $user_id,
                'log_date' => $log_date_gregorian,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'jalali_year' => $jalali_year,
                'jalali_month' => $jalali_month,
                'jalali_day' => $jalali_day
            ]);
            $new_log_id = $pdo->lastInsertId();
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Time saved successfully.', 'log_id' => $new_log_id]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('AJAX Save Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A database error occurred.']);
}
?>
