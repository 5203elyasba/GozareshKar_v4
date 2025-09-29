<?php
require_once 'config.php';
require_once 'JalaliDate.php';

header('Content-Type: application/json');

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['log_date_jalali']) || !isset($data['day_type'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

$user_id = $_SESSION['id'];
$log_date_jalali = $data['log_date_jalali'];
$day_type = $data['day_type'];

$gregorian_date_obj = JalaliDate::fromJalaliToDateTime($log_date_jalali);
if (!$gregorian_date_obj) {
    echo json_encode(['success' => false, 'message' => 'Invalid Jalali date format.']);
    exit;
}
$log_date_gregorian = $gregorian_date_obj->format('Y-m-d');

try {
    $pdo->beginTransaction();

    // 1. Clean up previous state for the given day from all relevant tables
    $pdo->prepare("DELETE FROM day_properties WHERE user_id = :user_id AND log_date = :log_date")->execute([':user_id' => $user_id, ':log_date' => $log_date_gregorian]);
    $pdo->prepare("DELETE FROM leave_logs WHERE user_id = :user_id AND leave_date = :log_date")->execute([':user_id' => $user_id, ':log_date' => $log_date_gregorian]);

    // 2. Insert new state based on day_type, respecting the database schema
    switch ($day_type) {
        case 'leave':
            $stmt = $pdo->prepare("INSERT INTO leave_logs (user_id, leave_date) VALUES (:user_id, :leave_date)");
            $stmt->execute([':user_id' => $user_id, ':leave_date' => $log_date_gregorian]);
            break;

        case 'official_holiday_no_work':
        case 'official_holiday_work':
            // Both types are stored as 'official_holiday' in the DB.
            // The distinction is made by the presence/absence of time_logs.
            $stmt = $pdo->prepare("INSERT INTO day_properties (user_id, log_date, day_type, status) VALUES (:user_id, :log_date, 'official_holiday', 'approved')");
            $stmt->execute([':user_id' => $user_id, ':log_date' => $log_date_gregorian]);
            break;

        case 'friday_work':
            $stmt = $pdo->prepare("INSERT INTO day_properties (user_id, log_date, day_type, status) VALUES (:user_id, :log_date, 'friday_work', 'approved')");
            $stmt->execute([':user_id' => $user_id, ':log_date' => $log_date_gregorian]);
            break;

        // For 'work' and 'friday', we just need the cleanup. No new record is needed.
    }

    // 3. If the day is set to a non-working type, remove any associated time logs.
    $non_working_types = ['leave', 'official_holiday_no_work', 'friday'];
    if (in_array($day_type, $non_working_types)) {
        $delete_sql = "DELETE FROM time_logs WHERE user_id = :user_id AND log_date = :log_date";
        $delete_stmt = $pdo->prepare($delete_sql);
        $delete_stmt->execute([':user_id' => $user_id, ':log_date' => $log_date_gregorian]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Day type updated successfully.']);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('AJAX Update Day Type Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A database error occurred: ' . $e->getMessage()]);
}
?>