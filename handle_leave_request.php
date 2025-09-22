<?php
require_once 'config.php';
require_once 'JalaliDate.php';

// Authenticate user
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("location: leave.php");
    exit;
}

// --- Data Retrieval & Date Construction ---
$user_id = $_SESSION['id'];
$reason = trim($_POST['reason']);
$day = $_POST['leave_day'] ?? '';
$month = $_POST['leave_month'] ?? '';
$year = $_POST['leave_year'] ?? '';

// --- Robust Validation ---
if (empty($day) || empty($month) || empty($year) || !is_numeric($day) || !is_numeric($month) || !is_numeric($year)) {
    header("Location: leave.php?error=تاریخ ناقص است. لطفاً روز، ماه و سال را به درستی وارد کنید.");
    exit;
}
$day_int = (int)$day;
$month_int = (int)$month;
$year_int = (int)$year;

if (!checkdate($month_int, $day_int, $year_int)) {
    header("Location: leave.php?error=تاریخ وارد شده نامعتبر است.");
    exit;
}

$leave_date_jalali = sprintf('%04d/%02d/%02d', $year_int, $month_int, $day_int);
$gregorian_date_obj = JalaliDate::fromJalaliToDateTime($leave_date_jalali);
if ($gregorian_date_obj === false) {
    header("Location: leave.php?error=خطا در تبدیل تاریخ شمسی.");
    exit;
}
$gregorian_date_str = $gregorian_date_obj->format('Y-m-d');

// --- Database Operation ---
try {
    // Check if leave already requested for this date
    $sql_check = "SELECT id FROM leave_logs WHERE user_id = :user_id AND leave_date = :leave_date";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([':user_id' => $user_id, ':leave_date' => $gregorian_date_str]);
    if ($stmt_check->rowCount() > 0) {
        header("Location: leave.php?error=برای این تاریخ قبلاً مرخصی ثبت شده است.");
        exit;
    }

    // Insert the new leave log
    $sql_insert = "INSERT INTO leave_logs (user_id, leave_date, reason) VALUES (:user_id, :leave_date, :reason)";
    $stmt_insert = $pdo->prepare($sql_insert);
    $stmt_insert->execute([
        ':user_id' => $user_id,
        ':leave_date' => $gregorian_date_str,
        ':reason' => $reason
    ]);

    header("Location: leave.php?success=1");
    exit;

} catch (PDOException $e) {
    header("Location: leave.php?error=خطای دیتابیس: " . urlencode($e->getMessage()));
    exit;
}
?>
