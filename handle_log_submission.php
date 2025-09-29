<?php
require_once 'config.php';
require_once 'JalaliDate.php';

// --- Authentication & Basic Validation ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("location: index.php"); // Redirect back if not a POST request
    exit;
}

$user_id = $_SESSION['id'];

// --- Data Sanitization & Retrieval ---
$log_date_jalali = $_POST['log_date_jalali'] ?? '';
$day_type = $_POST['day_type'] ?? 'work';
$start_times = $_POST['start_time'] ?? [];
$end_times = $_POST['end_time'] ?? [];

// --- Validation ---
if (empty($log_date_jalali)) {
    header("location: index.php?error=تاریخ مشخص نشده است.");
    exit;
}

// Convert Jalali to Gregorian for DB operations
$gregorian_date_obj = JalaliDate::fromJalaliToDateTime($log_date_jalali);
if (!$gregorian_date_obj) {
    header("location: index.php?error=فرمت تاریخ نامعتبر است.");
    exit;
}
$log_date_gregorian = $gregorian_date_obj->format('Y-m-d');
list($jalali_year, $jalali_month, $jalali_day) = explode('/', $log_date_jalali);

try {
    $pdo->beginTransaction();

    // 1. Delete all existing records for this day and user to ensure a clean slate
    $sql_delete_logs = "DELETE FROM time_logs WHERE user_id = :user_id AND log_date = :log_date";
    $stmt_delete_logs = $pdo->prepare($sql_delete_logs);
    $stmt_delete_logs->execute([':user_id' => $user_id, ':log_date' => $log_date_gregorian]);

    $sql_delete_props = "DELETE FROM day_properties WHERE user_id = :user_id AND log_date = :log_date";
    $stmt_delete_props = $pdo->prepare($sql_delete_props);
    $stmt_delete_props->execute([':user_id' => $user_id, ':log_date' => $log_date_gregorian]);

    // 2. Insert the new day property record for all submission types
    $sql_prop = "INSERT INTO day_properties (user_id, log_date, day_type, status) VALUES (:user_id, :log_date, :day_type, 'approved')";
    $stmt_prop = $pdo->prepare($sql_prop);
    $stmt_prop->execute([
        ':user_id' => $user_id,
        ':log_date' => $log_date_gregorian,
        ':day_type' => $day_type
    ]);

    // 3. If it's a working day ('work' or 'friday_work'), insert the time intervals
    if ($day_type === 'work' || $day_type === 'friday_work') {
        $sql_log = "INSERT INTO time_logs (user_id, log_date, start_time, end_time, log_type, jalali_year, jalali_month, jalali_day) VALUES (:user_id, :log_date, :start_time, :end_time, 'work', :jalali_year, :jalali_month, :jalali_day)";
        $stmt_log = $pdo->prepare($sql_log);

        for ($i = 0; $i < count($start_times); $i++) {
            // Only insert if both start and end times are provided and valid
            if (!empty($start_times[$i]) && !empty($end_times[$i]) && preg_match("/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/", $start_times[$i]) && preg_match("/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/", $end_times[$i])) {
                $stmt_log->execute([
                    ':user_id' => $user_id,
                    ':log_date' => $log_date_gregorian,
                    ':start_time' => $start_times[$i],
                    ':end_time' => $end_times[$i],
                    ':jalali_year' => $jalali_year,
                    ':jalali_month' => $jalali_month,
                    ':jalali_day' => $jalali_day
                ]);
            }
        }
    }

    $pdo->commit();
    // Redirect back to the log page with a success message
    header("location: index.php?date=" . urlencode($log_date_jalali) . "&success=گزارش با موفقیت ثبت شد.");

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Log Submission Error: ' . $e->getMessage());
    // Redirect back with a generic error message
    header("location: index.php?date=" . urlencode($log_date_jalali) . "&error=خطای دیتابیس رخ داد. لطفا دوباره تلاش کنید.");
}
?>