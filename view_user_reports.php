<?php
require_once 'config.php';
require_once 'JalaliDate.php';

// Authentication and Authorization
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'admin') {
    header("location: login.php");
    exit;
}

$user_id = $_GET['user_id'] ?? null;
if (!$user_id) {
    header("location: admin.php?error=no_user_id");
    exit;
}

$user_full_name = 'کاربر یافت نشد';
$logs_by_date = [];

$selected_year = $_GET['year'] ?? null;
$selected_month = $_GET['month'] ?? null;

require_once 'ReportCalculator.php';
$calculator = new ReportCalculator($pdo);
// The calculator is no longer date-range based for this view's summary
$report_data = $calculator->calculateForUser($user_id);

try {
    // Fetch user's full name
    $user_stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = :id");
    $user_stmt->execute(['id' => $user_id]);
    $user = $user_stmt->fetch();
    if ($user) {
        $user_full_name = $user['full_name'];
    }

    // Fetch distinct years with logs for this user
    $years_stmt = $pdo->prepare("SELECT DISTINCT YEAR(log_date) as log_year FROM time_logs WHERE user_id = :user_id ORDER BY log_year DESC");
    $years_stmt->execute(['user_id' => $user_id]);
    $available_years = $years_stmt->fetchAll(PDO::FETCH_COLUMN);

    // If a year and month are selected, fetch the logs for that period
    if ($selected_year && $selected_month) {
        $logs_sql = "SELECT log_date, start_time, end_time, log_type FROM time_logs WHERE user_id = :user_id AND YEAR(log_date) = :year AND MONTH(log_date) = :month ORDER BY log_date ASC, start_time ASC";
        $logs_stmt = $pdo->prepare($logs_sql);
        $logs_stmt->execute(['user_id' => $user_id, 'year' => $selected_year, 'month' => $selected_month]);
        $all_logs = $logs_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $all_logs = []; // Don't show logs unless a period is selected
    }

    // Group logs by date
    foreach ($all_logs as $log) {
        $jalali_date = JalaliDate::toJalali($log['log_date']);
        if (!isset($logs_by_date[$jalali_date])) {
            $logs_by_date[$jalali_date] = ['work_hours' => 0, 'break_minutes' => 0, 'entries' => []];
        }

        $start = new DateTime($log['start_time']);
        $end = new DateTime($log['end_time']);
        $diff_seconds = $end->getTimestamp() - $start->getTimestamp();

        if ($log['log_type'] === 'work') {
            $logs_by_date[$jalali_date]['work_hours'] += $diff_seconds / 3600;
            $logs_by_date[$jalali_date]['entries'][] = date('H:i', $start->getTimestamp()) . ' - ' . date('H:i', $end->getTimestamp());
        } else {
            $logs_by_date[$jalali_date]['break_minutes'] += $diff_seconds / 60;
        }
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>گزارشات کاربر: <?php echo htmlspecialchars($user_full_name); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container my-5">
        <?php if(file_exists('nav.php')) { require_once 'nav.php'; } ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0">گزارشات: <?php echo htmlspecialchars($user_full_name); ?></h3>
            <div class="col-md-4">
                <form action="view_user_reports.php" method="get" id="year-select-form">
                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_id); ?>">
                    <select name="year" class="form-select" onchange="this.form.submit()">
                        <option value="">انتخاب سال</option>
                        <?php foreach ($available_years as $year): ?>
                            <option value="<?php echo $year; ?>" <?php if ($year == $selected_year) echo 'selected'; ?>>
                                سال <?php echo $year; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>

        <?php if ($selected_year): ?>
            <div class="list-group list-group-horizontal-md mb-4">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <a href="view_user_reports.php?user_id=<?php echo $user_id; ?>&year=<?php echo $selected_year; ?>&month=<?php echo $m; ?>"
                       class="list-group-item list-group-item-action <?php if ($m == $selected_month) echo 'active'; ?>">
                       <?php echo ["فروردین","اردیبهشت","خرداد","تیر","مرداد","شهریور","مهر","آبان","آذر","دی","بهمن","اسفند"][$m-1]; ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">خلاصه گزارش عملکرد کلی (تمام‌وقت)</h5>
            </div>
            <div class="card-body">
                <?php if ($report_data['success']): ?>
                    <div class="row text-center">
                        <div class="col-md-4">
                            <h6>کل ساعات کاری</h6>
                            <p class="fs-4 fw-bold"><?php echo $report_data['total_work_hours']; ?></p>
                        </div>
                        <div class="col-md-4">
                            <h6>وضعیت اضافه/کسر کار (ساعت)</h6>
                            <p class="fs-4 fw-bold <?php echo ($report_data['total_overtime_undertim_hours'] >= 0) ? 'text-success' : 'text-danger'; ?>">
                                <?php echo $report_data['total_overtime_undertim_hours']; ?>
                            </p>
                        </div>
                        <div class="col-md-4">
                            <h6>مرخصی باقی‌مانده (روز)</h6>
                            <p class="fs-4 fw-bold"><?php echo $report_data['remaining_leave_days']; ?></p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning"><?php echo htmlspecialchars($report_data['error']); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <h4 class="mb-3">جزئیات روزانه</h4>
        <?php if ($selected_year && $selected_month): ?>
            <?php if (empty($all_logs)): ?>
                <div class="alert alert-info">هیچ گزارشی برای این ماه ثبت نشده است.</div>
            <?php else: ?>
                <div class="accordion" id="reports-accordion">
                    <?php foreach ($logs_by_date as $date => $data): ?>
                        <div class="accordion-item">
                        <h2 class="accordion-header" id="heading-<?php echo str_replace('/', '-', $date); ?>">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo str_replace('/', '-', $date); ?>" aria-expanded="false">
                                <div class="w-100 d-flex justify-content-between pe-3">
                                    <strong><?php echo $date; ?></strong>
                                    <span>مجموع ساعت کاری: <?php echo round($data['work_hours'], 2); ?></span>
                                    <span>استراحت: <?php echo round($data['break_minutes']); ?> دقیقه</span>
                                </div>
                            </button>
                        </h2>
                        <div id="collapse-<?php echo str_replace('/', '-', $date); ?>" class="accordion-collapse collapse" data-bs-parent="#reports-accordion">
                            <div class="accordion-body">
                                <h6>بازه های زمانی حضور:</h6>
                                <ul>
                                    <?php foreach($data['entries'] as $entry): ?>
                                        <li><?php echo $entry; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
         <a href="admin.php" class="btn btn-secondary mt-4">بازگشت به پنل مدیریت</a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
