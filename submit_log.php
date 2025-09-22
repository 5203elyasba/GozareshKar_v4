<?php
require_once 'config.php';
require_once 'JalaliDate.php';

// Authenticate user
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("location: index.php");
    exit;
}

// --- Data Retrieval & Validation ---
if (!isset($_POST['log_day'], $_POST['log_month'], $_POST['log_year'])) {
    die("خطا: فیلدهای تاریخ ارسال نشده‌اند.");
}
if (!is_numeric($_POST['log_day']) || !is_numeric($_POST['log_month']) || !is_numeric($_POST['log_year'])) {
    die("خطا: مقادیر تاریخ باید عددی باشند.");
}

$day_int = (int)$_POST['log_day'];
$month_int = (int)$_POST['log_month'];
$year_int = (int)$_POST['log_year'];

// The JalaliDate class's conversion function will return false for an invalid logical date (e.g., 1403/06/32)
// This is the correct way to validate, instead of using the Gregorian checkdate() function.
$log_date_jalali = sprintf('%04d/%02d/%02d', $year_int, $month_int, $day_int);
$gregorian_date_obj = JalaliDate::fromJalaliToDateTime($log_date_jalali);
if ($gregorian_date_obj === false) { die("خطا در تبدیل تاریخ شمسی."); }
$gregorian_date_str = $gregorian_date_obj->format('Y-m-d');

// --- Data Retrieval (continued) ---
$user_id = $_SESSION["id"];
$work_start_times = $_POST["start_time"] ?? [];
$work_end_times = $_POST["end_time"] ?? [];
$total_break_minutes = (int)($_POST['total_break_minutes'] ?? 0);

// --- Database Operation (Transaction) ---
try {
    $pdo->beginTransaction();

    // Delete existing time logs for this user on this date
    $delete_sql = "DELETE FROM time_logs WHERE user_id = :user_id AND log_date = :log_date";
    $delete_stmt = $pdo->prepare($delete_sql);
    $delete_stmt->execute([':user_id' => $user_id, ':log_date' => $gregorian_date_str]);

    // Insert new work intervals
    $insert_sql = "INSERT INTO time_logs (user_id, log_date, start_time, end_time, log_type, jalali_year, jalali_month, jalali_day) VALUES (:user_id, :log_date, :start_time, :end_time, :log_type, :jalali_year, :jalali_month, :jalali_day)";
    $insert_stmt = $pdo->prepare($insert_sql);
    $base_params = [
        ':user_id' => $user_id,
        ':log_date' => $gregorian_date_str,
        ':jalali_year' => $year_int,
        ':jalali_month' => $month_int,
        ':jalali_day' => $day_int
    ];

    for ($i = 0; $i < count($work_start_times); $i++) {
        $start = $work_start_times[$i];
        $end = $work_end_times[$i];
        if (empty($start) || empty($end) || strtotime($end) <= strtotime($start)) continue;

        $params = array_merge($base_params, [
            ':start_time' => $start,
            ':end_time' => $end,
            ':log_type' => 'work'
        ]);
        $insert_stmt->execute($params);
    }

    // Insert total break minutes as a single log entry
    if ($total_break_minutes > 0) {
        $break_start_time = '00:00:00';
        $break_end_time = date('H:i:s', strtotime("+$total_break_minutes minutes", strtotime($break_start_time)));

        $params = array_merge($base_params, [
            ':start_time' => $break_start_time,
            ':end_time' => $break_end_time,
            ':log_type' => 'break'
        ]);
        $insert_stmt->execute($params);
    }

    $pdo->commit();
    header("location: reports.php?success=1");

} catch (Exception $e) {
    $pdo->rollBack();
    header("location: index.php?date={$log_date_jalali}&error=db_error&msg=" . urlencode($e->getMessage()));
}
?>
