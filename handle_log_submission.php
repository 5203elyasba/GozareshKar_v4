<?php
require_once 'config.php';
require_once 'JalaliDate.php';

// --- Authentication & Basic Validation ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("location: index.php");
    exit;
}

$user_id = $_SESSION['id'];

// --- Data Sanitization & Retrieval ---
$log_date_jalali = $_POST['log_date_jalali'] ?? '';
$day_type = $_POST['day_type'] ?? 'work';

// Retrieve time components from dropdowns
$start_hours = $_POST['start_hour'] ?? [];
$start_minutes = $_POST['start_minute'] ?? [];
$end_hours = $_POST['end_hour'] ?? [];
$end_minutes = $_POST['end_minute'] ?? [];

// --- Validation ---
if (empty($log_date_jalali)) {
    header("location: index.php?error=تاریخ مشخص نشده است.");
    exit;
}

$gregorian_date_obj = JalaliDate::fromJalaliToDateTime($log_date_jalali);
if (!$gregorian_date_obj) {
    header("location: index.php?error=فرمت تاریخ نامعتبر است.");
    exit;
}
$log_date_gregorian = $gregorian_date_obj->format('Y-m-d');
list($jalali_year, $jalali_month, $jalali_day) = explode('/', $log_date_jalali);

try {
    $pdo->beginTransaction();

    // 1. Delete all existing records for this day and user
    $pdo->prepare("DELETE FROM time_logs WHERE user_id = :user_id AND log_date = :log_date")->execute([':user_id' => $user_id, ':log_date' => $log_date_gregorian]);
    $pdo->prepare("DELETE FROM day_properties WHERE user_id = :user_id AND log_date = :log_date")->execute([':user_id' => $user_id, ':log_date' => $log_date_gregorian]);

    // 2. Insert the new day property record
    // Note: For 'official_holiday_work', we store it as such, the reporting logic will handle the credit.
    $sql_prop = "INSERT INTO day_properties (user_id, log_date, day_type, status) VALUES (:user_id, :log_date, :day_type, 'approved')";
    $stmt_prop = $pdo->prepare($sql_prop);
    $stmt_prop->execute([
        ':user_id' => $user_id,
        ':log_date' => $log_date_gregorian,
        ':day_type' => $day_type
    ]);

    // 3. If it's a day with work logs, combine times and insert
    $work_day_types = ['work', 'friday_work', 'official_holiday_work'];
    if (in_array($day_type, $work_day_types)) {
        $sql_log = "INSERT INTO time_logs (user_id, log_date, start_time, end_time, log_type, jalali_year, jalali_month, jalali_day) VALUES (:user_id, :log_date, :start_time, :end_time, 'work', :jalali_year, :jalali_month, :jalali_day)";
        $stmt_log = $pdo->prepare($sql_log);

        for ($i = 0; $i < count($start_hours); $i++) {
            // Combine hour and minute, only if both are selected
            if (isset($start_hours[$i], $start_minutes[$i], $end_hours[$i], $end_minutes[$i]) &&
                $start_hours[$i] !== '' && $start_minutes[$i] !== '' &&
                $end_hours[$i] !== '' && $end_minutes[$i] !== '') {

                $start_time = "{$start_hours[$i]}:{$start_minutes[$i]}";
                $end_time = "{$end_hours[$i]}:{$end_minutes[$i]}";

                // Basic validation for time format
                if (strtotime($end_time) > strtotime($start_time)) {
                     $stmt_log->execute([
                        ':user_id' => $user_id,
                        ':log_date' => $log_date_gregorian,
                        ':start_time' => $start_time,
                        ':end_time' => $end_time,
                        ':jalali_year' => $jalali_year,
                        ':jalali_month' => $jalali_month,
                        ':jalali_day' => $jalali_day
                    ]);
                }
            }
        }
    }

    $pdo->commit();
    header("location: index.php?date=" . urlencode($log_date_jalali) . "&success=گزارش با موفقیت ثبت شد.");

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Log Submission Error: ' . $e->getMessage());
    header("location: index.php?date=" . urlencode($log_date_jalali) . "&error=خطای دیتابیس رخ داد. لطفا دوباره تلاش کنید.");
}
?>