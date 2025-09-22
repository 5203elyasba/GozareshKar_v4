<?php
require_once 'config.php';
require_once 'JalaliDate.php';

// Authenticate user
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

$user_id = $_SESSION['id'];
$now = new DateTime('now', new DateTimeZone('Asia/Tehran'));
$today_gregorian = $now->format('Y-m-d');
$today_jalali = JalaliDate::toJalali($today_gregorian);

try {
    // Check if any log (work or break) already exists for today
    $sql_check = "SELECT id FROM time_logs WHERE user_id = :user_id AND log_date = :log_date";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([':user_id' => $user_id, ':log_date' => $today_gregorian]);

    // If logs already exist, just redirect to the edit page for today
    if ($stmt_check->rowCount() > 0) {
        header("Location: index.php?date=" . $today_jalali);
        exit;
    }

    // If no logs exist, create a new 1-minute temporary log
    $start_time = $now->format('H:i:s');
    $now->modify('+1 minute');
    $end_time = $now->format('H:i:s');

    $sql_insert = "INSERT INTO time_logs (user_id, log_date, start_time, end_time, log_type) VALUES (:user_id, :log_date, :start_time, :end_time, :log_type)";
    $stmt_insert = $pdo->prepare($sql_insert);
    $stmt_insert->execute([
        ':user_id' => $user_id,
        ':log_date' => $today_gregorian,
        ':start_time' => $start_time,
        ':end_time' => $end_time,
        ':log_type' => 'work'
    ]);

    // Redirect to the edit page for today
    header("Location: index.php?date=" . $today_jalali);
    exit;

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
