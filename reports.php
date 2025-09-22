<?php
require_once 'config.php';
require_once 'JalaliDate.php';

// --- Authentication & Authorization ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}
if ($_SESSION["role"] !== 'admin') {
    die("شما دسترسی لازم برای مشاهده این صفحه را ندارید.");
}

// --- Fetch all users for dropdown ---
$all_users = [];
try {
    $user_stmt = $pdo->query("SELECT id, full_name FROM users ORDER BY full_name ASC");
    $all_users = $user_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error fetching users: " . $e->getMessage());
}


// --- Date & User Selection ---
$current_jalali_date = JalaliDate::toJalali(date('Y-m-d'));
list($current_year, $current_month, $current_day) = explode('/', $current_jalali_date);

$selected_user_id = $_GET['user_id'] ?? null;
$selected_year = $_GET['year'] ?? $current_year;
$selected_month = $_GET['month'] ?? $current_month;

// --- Data Fetching ---
$logs_by_day = [];
$monthly_total_hours = 0;
$monthly_total_break_minutes = 0;
$all_logs = [];
$summary_data = [];

// Only fetch data if a user is selected
if ($selected_user_id) {
    // Fetch user data for calculations
    $user_sql = "SELECT daily_hours_goal, annual_leave_days FROM users WHERE id = :id";
    $user_stmt = $pdo->prepare($user_sql);
    $user_stmt->execute(['id' => $selected_user_id]);
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch leave count for the whole year
    $leave_sql = "SELECT COUNT(*) FROM leave_logs WHERE user_id = :user_id";
    $leave_stmt = $pdo->prepare($leave_sql);
    $leave_stmt->execute(['user_id' => $selected_user_id]);
    $total_leave_days = $leave_stmt->fetchColumn();
    try {
        // Fetch all logs for the selected user, year, and month
        $sql = "SELECT * FROM time_logs WHERE user_id = :user_id AND jalali_year = :year AND jalali_month = :month ORDER BY log_date ASC, start_time ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['user_id' => $selected_user_id, 'year' => $selected_year, 'month' => $selected_month]);
        $all_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group logs by day and calculate totals
        foreach ($all_logs as $log) {
            $day = $log['jalali_day'];
            if (!isset($logs_by_day[$day])) {
                $logs_by_day[$day] = ['work_hours' => 0, 'break_minutes' => 0, 'entries' => []];
            }

            $start = new DateTime($log['start_time']);
            $end = new DateTime($log['end_time']);
            $diff_seconds = $end->getTimestamp() - $start->getTimestamp();

            if ($log['log_type'] === 'work') {
                $work_hours = $diff_seconds / 3600;
                $logs_by_day[$day]['work_hours'] += $work_hours;
                $monthly_total_hours += $work_hours;
                $logs_by_day[$day]['entries'][] = date('H:i', $start->getTimestamp()) . ' - ' . date('H:i', $end->getTimestamp());
            } else {
                $break_minutes = $diff_seconds / 60;
                $logs_by_day[$day]['break_minutes'] += $break_minutes;
                $monthly_total_break_minutes += $break_minutes;
            }
        }
        ksort($logs_by_day);

        // --- Summary Calculations ---
        $days_worked = count($logs_by_day);
        $total_hours_goal = $days_worked * ($user_data['daily_hours_goal'] ?? 8);
        $overtime_undertim_hours = $monthly_total_hours - $total_hours_goal;
        $remaining_leave = ($user_data['annual_leave_days'] ?? 26) - $total_leave_days;

        $summary_data = [
            'overtime_undertim' => round($overtime_undertim_hours, 2),
            'remaining_leave' => $remaining_leave
        ];

    } catch (Exception $e) {
        die("Error fetching report data: " . $e->getMessage());
    }
}


// --- Prepare Chart Data ---
$chart_labels = [];
$chart_data = [];
foreach ($logs_by_day as $day => $data) {
    $chart_labels[] = $day;
    $chart_data[] = round($data['work_hours'], 2);
}

// --- Fetch available years for the dropdown ---
$available_years = [];
try {
    $years_sql = "SELECT DISTINCT jalali_year FROM time_logs WHERE jalali_year IS NOT NULL ORDER BY jalali_year DESC";
    $years_stmt = $pdo->query($years_sql);
    $available_years = $years_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    //
}


$jalali_months = ["فروردین","اردیبهشت","خرداد","تیر","مرداد","شهریور","مهر","آبان","آذر","دی","بهمن","اسفند"];
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>گزارشات کاربران</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container my-5">
        <?php if(file_exists('nav.php')) { require_once 'nav.php'; } ?>

        <h3 class="mb-4">گزارش ماهانه کاربران</h3>

        <!-- Filter Form -->
        <form action="reports.php" method="get" class="row g-3 mb-4 p-3 border rounded bg-light">
            <div class="col-md-4">
                <label for="user_id" class="form-label">کاربر</label>
                <select name="user_id" id="user_id" class="form-select">
                    <option value="">انتخاب کنید</option>
                    <?php foreach ($all_users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php if ($user['id'] == $selected_user_id) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($user['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="year" class="form-label">سال</label>
                <select name="year" id="year" class="form-select">
                    <?php foreach ($available_years as $year): ?>
                        <option value="<?php echo $year; ?>" <?php if ($year == $selected_year) echo 'selected'; ?>>
                            <?php echo $year; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="month" class="form-label">ماه</label>
                <select name="month" id="month" class="form-select">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php if ($m == $selected_month) echo 'selected'; ?>>
                            <?php echo $jalali_months[$m-1]; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">نمایش</button>
            </div>
        </form>

        <?php if (!$selected_user_id): ?>
            <div class="alert alert-info">لطفا برای مشاهده گزارش، یک کاربر را انتخاب کنید.</div>
        <?php elseif (empty($all_logs)): ?>
            <div class="alert alert-info">هیچ گزارشی برای کاربر و ماه انتخاب شده ثبت نشده است.</div>
        <?php else: ?>
            <!-- Summary Cards -->
            <div class="row text-center mb-4">
                <div class="col-md-6">
                    <div class="card text-white <?php echo $summary_data['overtime_undertim'] >= 0 ? 'bg-success' : 'bg-danger'; ?>">
                        <div class="card-body">
                            <h6 class="card-title">وضعیت اضافه/کسر کار (ساعت)</h6>
                            <p class="fs-4 fw-bold"><?php echo $summary_data['overtime_undertim']; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title">مرخصی باقی‌مانده (روز)</h6>
                            <p class="fs-4 fw-bold"><?php echo $summary_data['remaining_leave']; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row text-center mb-4">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title">مجموع ساعات کاری</h6>
                            <p class="fs-4 fw-bold"><?php echo round($monthly_total_hours, 2); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title">میانگین روزانه</h6>
                            <p class="fs-4 fw-bold"><?php echo (count($logs_by_day) > 0 && $monthly_total_hours > 0) ? round($monthly_total_hours / count($logs_by_day), 2) : 0; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title">مجموع استراحت (دقیقه)</h6>
                            <p class="fs-4 fw-bold"><?php echo round($monthly_total_break_minutes); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chart -->
            <div class="card mb-4">
                <div class="card-header">
                    نمودار ساعات کاری روزانه
                </div>
                <div class="card-body">
                    <canvas id="workHoursChart"></canvas>
                </div>
            </div>

            <!-- Accordion for Daily Details -->
            <div class="accordion" id="reports-accordion">
                <?php foreach ($logs_by_day as $day => $data): ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $day; ?>">
                                <div class="w-100 d-flex justify-content-between pe-3">
                                    <strong><?php echo "{$selected_year}/{$selected_month}/{$day}"; ?></strong>
                                    <span>مجموع ساعت کاری: <?php echo round($data['work_hours'], 2); ?></span>
                                    <span>استراحت: <?php echo round($data['break_minutes']); ?> دقیقه</span>
                                </div>
                            </button>
                        </h2>
                        <div id="collapse-<?php echo $day; ?>" class="accordion-collapse collapse" data-bs-parent="#reports-accordion">
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
    </div>

    <script>
        const ctx = document.getElementById('workHoursChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($chart_labels); ?>,
                    datasets: [{
                        label: 'ساعات کاری',
                        data: <?php echo json_encode($chart_data); ?>,
                        backgroundColor: 'rgba(0, 46, 54, 0.8)',
                        borderColor: 'rgba(0, 46, 54, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'ساعت' }
                        },
                        x: {
                            title: { display: true, text: 'روز ماه' }
                        }
                    },
                    responsive: true,
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
