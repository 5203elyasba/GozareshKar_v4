<?php
require_once 'config.php';
require_once 'JalaliDate.php';

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ header("location: login.php"); exit; }

$viewing_user_id = $_SESSION['id'];
if (isset($_GET['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $viewing_user_id = (int)$_GET['user_id'];
}

// --- Fetch user info ---
$viewed_user_info = null;
try {
    $stmt_user = $pdo->prepare("SELECT username, daily_hours_goal, annual_leave_days FROM users WHERE id = :user_id");
    $stmt_user->execute([':user_id' => $viewing_user_id]);
    $viewed_user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) { die("Error fetching user data."); }

if (!$viewed_user_info) die("User not found.");
$user_work_hours_goal = (float)$viewed_user_info['daily_hours_goal'];
$annual_leave_total = (int)$viewed_user_info['annual_leave_days'];
$standard_work_seconds = $user_work_hours_goal * 3600;
$grand_total_deficit_seconds = 0; // Initialize here to guarantee it exists

// --- Fetch Leave Data ---
$leave_taken_count = 0;
try {
    $stmt_leave = $pdo->prepare("SELECT COUNT(id) as leave_count FROM leave_logs WHERE user_id = :user_id");
    $stmt_leave->execute([':user_id' => $viewing_user_id]);
    $leave_result = $stmt_leave->fetch(PDO::FETCH_ASSOC);
    $leave_taken_count = (int)$leave_result['leave_count'];
} catch (PDOException $e) { /* Silently fail */ }
$leave_remaining = $annual_leave_total - $leave_taken_count;

// --- Fetch Logs (Work and Leave) ---
$all_events = [];
try {
    // Fetch Time Logs
    $stmt_logs = $pdo->prepare("SELECT log_date, start_time, end_time, log_type FROM time_logs WHERE user_id = :user_id");
    $stmt_logs->execute([':user_id' => $viewing_user_id]);
    $time_logs = $stmt_logs->fetchAll(PDO::FETCH_ASSOC);

    $daily_reports = [];
    foreach ($time_logs as $log) {
        $date = $log['log_date'];
        if (!isset($daily_reports[$date])) $daily_reports[$date] = ['intervals' => [], 'total_seconds' => 0];
        $start_ts = strtotime($log['start_time']);
        $end_ts = strtotime($log['end_time']);
        if ($end_ts > $start_ts) {
            $diff = $end_ts - $start_ts;
            $daily_reports[$date]['total_seconds'] += ($log['log_type'] === 'work' ? $diff : -$diff);
            if ($log['log_type'] === 'work') $daily_reports[$date]['intervals'][] = ['start' => date('H:i', $start_ts), 'end' => date('H:i', $end_ts)];
        }
    }
    foreach($daily_reports as $date => $report){
        $all_events[$date] = ['type' => 'work_day', 'data' => $report];
    }

    // Fetch Leave Logs
    $stmt_leave = $pdo->prepare("SELECT leave_date, reason FROM leave_logs WHERE user_id = :user_id");
    $stmt_leave->execute([':user_id' => $viewing_user_id]);
    $leave_logs = $stmt_leave->fetchAll(PDO::FETCH_ASSOC);
    foreach ($leave_logs as $leave) {
        $all_events[$leave['leave_date']] = ['type' => 'leave_day', 'data' => ['reason' => $leave['reason']]];
    }

    // Sort all events by date descending
    krsort($all_events);

} catch (PDOException $e) { die('<div class="alert alert-danger">خطا در دریافت اطلاعات.</div>'); }

foreach ($all_events as $event) {
    if ($event['type'] === 'work_day') {
        $grand_total_deficit_seconds += ($event['data']['total_seconds'] - $standard_work_seconds);
    }
}

function format_seconds_to_hours($seconds) {
    $sign = $seconds < 0 ? '-' : '';
    $seconds = abs($seconds);
    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    return sprintf("%s%02d:%02d", $sign, $h, $m);
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>گزارش جامع برای <?php echo htmlspecialchars($viewed_user_info['username']); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container my-4">
    <?php require_once 'nav.php'; ?>

    <h2 class="mb-4">گزارش جامع برای: <span class="text-primary"><?php echo htmlspecialchars($viewed_user_info['username']); ?></span></h2>
    <!-- Summary Cards -->
    <div class="row g-3 text-center mb-4">
        <div class="col-md"><div class="card"><div class="card-header fw-bold">وضعیت کلی کارکرد</div><div class="card-body">مجموع اضافه/کسری کار:<strong class="d-block fs-4 <?php echo ($grand_total_deficit_seconds < 0 ? 'text-danger' : 'text-success'); ?>"><?php echo format_seconds_to_hours($grand_total_deficit_seconds); ?></strong></div></div></div>
        <div class="col-md"><div class="card"><div class="card-header fw-bold">وضعیت مرخصی</div><div class="card-body"><span class="badge bg-secondary">کل: <?php echo $annual_leave_total; ?></span> <span class="badge bg-warning text-dark">مصرف شده: <?php echo $leave_taken_count; ?></span> <span class="badge bg-success">باقیمانده: <?php echo $leave_remaining; ?></span></div></div></div>
    </div>

    <div class="card">
        <div class="card-header">گزارش روزانه</div>
        <div class="card-body p-2 p-md-3">
            <div class="table-responsive">
                <table class="table table-striped table-hover text-center small">
                    <thead class="table-dark"><tr><th>تاریخ</th><th>نوع</th><th>جزئیات</th></tr></thead>
                    <tbody>
                        <?php if (empty($all_events)): ?>
                            <tr><td colspan="3" class="text-center p-4">هیچ گزارشی برای این کاربر ثبت نشده است.</td></tr>
                        <?php else: foreach ($all_events as $date => $event): ?>
                            <tr>
                                <td class="align-middle"><a href="index.php?date=<?php echo JalaliDate::toJalali($date); ?>"><?php echo JalaliDate::toJalali($date); ?></a></td>
                                <?php if ($event['type'] === 'work_day'):
                                    $report = $event['data'];
                                    $deficit = $report['total_seconds'] - $standard_work_seconds;
                                ?>
                                    <td class="align-middle"><span class="badge bg-primary">کاری</span></td>
                                    <td>
                                        <div><strong>ساعات مفید:</strong> <span class="fw-bold"><?php echo format_seconds_to_hours($report['total_seconds']); ?></span></div>
                                        <div><strong>کسری/اضافه:</strong> <span class="fw-bold <?php echo $deficit < 0 ? 'text-danger' : 'text-success'; ?>"><?php echo format_seconds_to_hours($deficit); ?></span></div>
                                        <hr class="my-1">
                                        <?php foreach ($report['intervals'] as $interval) { echo "<div>{$interval['start']} - {$interval['end']}</div>"; } ?>
                                    </td>
                                <?php else: // leave_day ?>
                                    <td class="align-middle"><span class="badge bg-info">مرخصی</span></td>
                                    <td><?php echo htmlspecialchars($event['data']['reason']); ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
