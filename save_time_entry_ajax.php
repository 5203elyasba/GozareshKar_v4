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
if (!$data || !isset($data['log_date_jalali']) || !isset($data['time']) || !isset($data['type'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

$log_id = isset($data['log_id']) && !empty($data['log_id']) ? (int)$data['log_id'] : null;
$log_date_jalali = $data['log_date_jalali'];
$time = $data['time'];
$type = $data['type']; // 'start' or 'end'
$user_id = $_SESSION['id'];

$gregorian_date_obj = JalaliDate::fromJalaliToDateTime($log_date_jalali);
if (!$gregorian_date_obj) {
    echo json_encode(['success' => false, 'message' => 'Invalid Jalali date format.']);
    exit;
}
$log_date_gregorian = $gregorian_date_obj->format('Y-m-d');
list($jalali_year, $jalali_month, $jalali_day) = explode('/', $log_date_jalali);

try {
    $pdo->beginTransaction();
    $new_log_id = $log_id;

    if ($log_id) {
        // --- UPDATE ---
        // Fetch the other part of the time to perform validation
        $other_type = ($type === 'start') ? 'end_time' : 'start_time';
        $stmt = $pdo->prepare("SELECT $other_type FROM time_logs WHERE id = ?");
        $stmt->execute([$log_id]);
        $other_time = $stmt->fetchColumn();

        $start_time = ($type === 'start') ? $time : $other_time;
        $end_time = ($type === 'end') ? $time : $other_time;

        // Validation: end_time must be after start_time
        if ($start_time && $end_time && strtotime($end_time) <= strtotime($start_time)) {
            throw new Exception('ساعت خروج باید بعد از ساعت ورود باشد.');
        }

        // Validation: Check for overlaps
        $overlap_stmt = $pdo->prepare(
            "SELECT id FROM time_logs WHERE user_id = ? AND log_date = ? AND id != ? AND (
                (? < end_time AND ? > start_time) OR
                (? < end_time AND ? > start_time) OR
                (? >= start_time AND ? <= end_time)
            )"
        );
        $overlap_stmt->execute([$user_id, $log_date_gregorian, $log_id, $start_time, $start_time, $end_time, $end_time, $start_time, $end_time]);
        if ($overlap_stmt->fetch()) {
            throw new Exception('این بازه زمانی با یک بازه دیگر در این روز تداخل دارد.');
        }

        $column_to_update = ($type === 'start') ? 'start_time' : 'end_time';
        $sql = "UPDATE time_logs SET $column_to_update = ? WHERE id = ? AND user_id = ?";
        $pdo->prepare($sql)->execute([$time, $log_id, $user_id]);

    } else {
        // --- INSERT ---
        $start_time = ($type === 'start') ? $time : null;
        $end_time = ($type === 'end') ? $time : null;

        $sql = "INSERT INTO time_logs (user_id, log_date, start_time, end_time, log_type, jalali_year, jalali_month, jalali_day)
                VALUES (?, ?, ?, ?, 'work', ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $log_date_gregorian, $start_time, $end_time, $jalali_year, $jalali_month, $jalali_day]);
        $new_log_id = $pdo->lastInsertId();
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Time saved successfully.', 'log_id' => $new_log_id]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('AJAX Save Time Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>