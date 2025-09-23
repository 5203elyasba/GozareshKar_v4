<?php
require_once 'config.php';
require_once 'JalaliDate.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("location: index.php");
    exit;
}

$user_id = $_SESSION["id"];

// --- Common Date Processing ---
if (!isset($_POST['log_day'], $_POST['log_month'], $_POST['log_year'])) {
    die("خطا: فیلدهای تاریخ ارسال نشده‌اند.");
}
$day_int = (int)$_POST['log_day'];
$month_int = (int)$_POST['log_month'];
$year_int = (int)$_POST['log_year'];

$log_date_jalali = sprintf('%04d/%02d/%02d', $year_int, $month_int, $day_int);
$gregorian_date_obj = JalaliDate::fromJalaliToDateTime($log_date_jalali);
if ($gregorian_date_obj === false) { die("خطا در تبدیل تاریخ شمسی."); }
$gregorian_date_str = $gregorian_date_obj->format('Y-m-d');

// --- Determine Action Type ---
$action_type = $_POST['day_type'] ?? 'log_time';


try {
    $pdo->beginTransaction();

    // Always clear existing properties for this day to avoid conflicts
    $delete_prop_sql = "DELETE FROM day_properties WHERE user_id = :user_id AND log_date = :log_date";
    $delete_prop_stmt = $pdo->prepare($delete_prop_sql);
    $delete_prop_stmt->execute([':user_id' => $user_id, ':log_date' => $gregorian_date_str]);

    // --- Holiday Request Logic ---
    if ($action_type === 'official_holiday_request') {
        $insert_prop_sql = "INSERT INTO day_properties (user_id, log_date, day_type, status) VALUES (:user_id, :log_date, 'official_holiday', 'pending')";
        $insert_prop_stmt = $pdo->prepare($insert_prop_sql);
        $insert_prop_stmt->execute([':user_id' => $user_id, ':log_date' => $gregorian_date_str]);

        // Also clear any time logs for that day, as it's now a pending holiday
        $delete_log_sql = "DELETE FROM time_logs WHERE user_id = :user_id AND log_date = :log_date";
        $delete_log_stmt = $pdo->prepare($delete_log_sql);
        $delete_log_stmt->execute([':user_id' => $user_id, ':log_date' => $gregorian_date_str]);

        $pdo->commit();
        header("location: index.php?date={$log_date_jalali}&success=holiday_requested");
        exit;
    }

    // --- Time Logging Logic ---
    $is_overtime = isset($_POST['is_overtime']) && $_POST['is_overtime'] == '1';
    if ($is_overtime) {
        $prop_sql = "INSERT INTO day_properties (user_id, log_date, day_type, status) VALUES (:user_id, :log_date, 'friday_work', 'approved')";
        $prop_stmt = $pdo->prepare($prop_sql);
        $prop_stmt->execute([':user_id' => $user_id, ':log_date' => $gregorian_date_str]);
    }

    // Clear previous time logs for this day
    $delete_log_sql = "DELETE FROM time_logs WHERE user_id = :user_id AND log_date = :log_date";
    $delete_log_stmt = $pdo->prepare($delete_log_sql);
    $delete_log_stmt->execute([':user_id' => $user_id, ':log_date' => $gregorian_date_str]);

    $work_start_times = $_POST["start_time"] ?? [];
    $work_end_times = $_POST["end_time"] ?? [];

    $insert_sql = "INSERT INTO time_logs (user_id, log_date, start_time, end_time, log_type, jalali_year, jalali_month, jalali_day) VALUES (:user_id, :log_date, :start_time, :end_time, 'work', :jalali_year, :jalali_month, :jalali_day)";
    $insert_stmt = $pdo->prepare($insert_sql);
    $base_params = [
        ':user_id' => $user_id,
        ':log_date' => $gregorian_date_str,
        ':jalali_year' => $year_int,
        ':jalali_month' => $month_int,
        ':jalali_day' => $day_int
    ];

    $has_entries = false;
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
        $has_entries = true;
    }

    if (!$has_entries) {
        // If user submitted an empty form, just redirect without error
        $pdo->commit();
        header("location: index.php?date={$log_date_jalali}");
        exit;
    }

    $pdo->commit();
    header("location: my_reports.php?year={$year_int}&month={$month_int}");

} catch (Exception $e) {
    $pdo->rollBack();
    header("location: index.php?date={$log_date_jalali}&error=db_error&msg=" . urlencode($e->getMessage()));
}
?>
