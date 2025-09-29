<?php
require_once 'config.php';
require_once 'JalaliDate.php';

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ header("location: login.php"); exit; }
$user_id = $_SESSION['id'];

// --- Fetch User's Leave Data ---
$annual_leave_total = 0;
$leave_taken = [];
try {
    $sql_user = "SELECT annual_leave_days FROM users WHERE id = :user_id";
    $stmt_user = $pdo->prepare($sql_user);
    $stmt_user->execute([':user_id' => $user_id]);
    $user_result = $stmt_user->fetch(PDO::FETCH_ASSOC);
    if ($user_result) $annual_leave_total = (int)$user_result['annual_leave_days'];

    $sql_leave = "SELECT leave_date, reason FROM leave_logs WHERE user_id = :user_id ORDER BY leave_date DESC";
    $stmt_leave = $pdo->prepare($sql_leave);
    $stmt_leave->execute([':user_id' => $user_id]);
    $leave_taken = $stmt_leave->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { die("Error fetching leave data: " . $e->getMessage()); }

$leave_used_count = count($leave_taken);
$leave_remaining = $annual_leave_total - $leave_used_count;

$jalali_today = JalaliDate::toJalali(date('Y-m-d'));
$today_parts = explode('/', $jalali_today);
$log_date_year = $today_parts[0];
$log_date_month = $today_parts[1];
$log_date_day = $today_parts[2];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت مرخصی</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container my-5">
    <?php if(file_exists('nav.php')) { require_once 'nav.php'; } ?>
    <h2 class="mb-4">مدیریت مرخصی</h2>

    <div class="row text-center mb-4 g-3">
        <div class="col-md-4"><div class="card"><div class="card-body"><h5 class="card-title">مرخصی کل</h5><p class="fs-4 fw-bold"><?php echo $annual_leave_total; ?> روز</p></div></div></div>
        <div class="col-md-4"><div class="card"><div class="card-body"><h5 class="card-title">استفاده شده</h5><p class="fs-4 fw-bold"><?php echo $leave_used_count; ?> روز</p></div></div></div>
        <div class="col-md-4"><div class="card"><div class="card-body"><h5 class="card-title">باقیمانده</h5><p class="fs-4 fw-bold text-success"><?php echo $leave_remaining; ?> روز</p></div></div></div>
    </div>

    <div class="card">
        <div class="card-header fw-bold">تاریخچه مرخصی‌های ثبت شده</div>
        <div class="card-body">
            <?php if(empty($leave_taken)): ?>
                <p class="text-center text-muted">موردی برای نمایش وجود ندارد.</p>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach($leave_taken as $leave): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="fw-bold"><?php echo JalaliDate::toJalali($leave['leave_date']); ?></span>
                            <span class="text-muted small"><?php echo htmlspecialchars($leave['reason']); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="main.js"></script>
</body>
</html>
