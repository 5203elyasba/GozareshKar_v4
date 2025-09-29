<?php
require_once 'config.php';
require_once 'JalaliDate.php';

// --- Authentication ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

$user_id = $_SESSION['id'];

// --- Date Selection ---
$current_jalali_date = JalaliDate::toJalali(date('Y-m-d'));
list($current_year, $current_month, $current_day) = explode('/', $current_jalali_date);

$selected_year = $_GET['year'] ?? $current_year;
$selected_month = $_GET['month'] ?? $current_month;

// --- Data Fetching & Processing ---
$logs_by_day = [];
$summary = [
    'monthly_total_hours' => 0,
    'monthly_overtime_hours' => 0,
    'monthly_break_minutes' => 0,
    'holiday_credit_hours' => 0,
    'required_work_hours' => 0,
    'deficit_surplus_hours' => 0,
    'remaining_leave_days' => 0,
];

try {
    // 1. Fetch user's settings (daily goal, annual leave)
    $user_sql = "SELECT daily_hours_goal, annual_leave_days FROM users WHERE id = :id";
    $user_stmt = $pdo->prepare($user_sql);
    $user_stmt->execute(['id' => $user_id]);
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
    $daily_goal = $user_data['daily_hours_goal'] ?? 8;
    $annual_leave_days = $user_data['annual_leave_days'] ?? 26;

    // 2. Fetch all necessary data for the selected month from all relevant tables
    $first_day_gregorian_str = JalaliDate::fromJalaliToDateTime("$selected_year/$selected_month/01")->format('Y-m-d');
    $days_in_month = JalaliDate::daysInMonth((int)$selected_year, (int)$selected_month);
    $last_day_gregorian_str = JalaliDate::fromJalaliToDateTime("$selected_year/$selected_month/$days_in_month")->format('Y-m-d');

    // Fetch day properties (official holidays, friday work)
    $properties_by_date = [];
    $prop_sql = "SELECT log_date, day_type FROM day_properties WHERE user_id = :user_id AND log_date BETWEEN :start_date AND :end_date";
    $prop_stmt = $pdo->prepare($prop_sql);
    $prop_stmt->execute([':user_id' => $user_id, ':start_date' => $first_day_gregorian_str, ':end_date' => $last_day_gregorian_str]);
    while ($row = $prop_stmt->fetch(PDO::FETCH_ASSOC)) {
        $properties_by_date[$row['log_date']] = $row['day_type'];
    }

    // Fetch leave logs
    $leave_by_date = [];
    $leave_sql = "SELECT leave_date FROM leave_logs WHERE user_id = :user_id AND leave_date BETWEEN :start_date AND :end_date";
    $leave_stmt = $pdo->prepare($leave_sql);
    $leave_stmt->execute([':user_id' => $user_id, ':start_date' => $first_day_gregorian_str, ':end_date' => $last_day_gregorian_str]);
    while ($row = $leave_stmt->fetch(PDO::FETCH_ASSOC)) {
        $leave_by_date[$row['leave_date']] = true;
    }

    // Fetch time logs
    $time_logs_by_date = [];
    $log_sql = "SELECT log_date, start_time, end_time FROM time_logs WHERE user_id = :user_id AND log_date BETWEEN :start_date AND :end_date ORDER BY start_time ASC";
    $log_stmt = $pdo->prepare($log_sql);
    $log_stmt->execute([':user_id' => $user_id, ':start_date' => $first_day_gregorian_str, ':end_date' => $last_day_gregorian_str]);
    while ($row = $log_stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!isset($time_logs_by_date[$row['log_date']])) $time_logs_by_date[$row['log_date']] = [];
        $time_logs_by_date[$row['log_date']][] = $row;
    }

    // 3. Loop through every day of the month to build a complete report
    $workdays_in_month = 0;
    $holiday_credit_hours = 0;

    for ($d = 1; $d <= $days_in_month; $d++) {
        $gregorian_date_obj = JalaliDate::fromJalaliToDateTime("$selected_year/$selected_month/$d");
        $gregorian_date_str = $gregorian_date_obj->format('Y-m-d');
        $is_friday = ($gregorian_date_obj->format('N') == 5);

        // Determine the final, definitive day type based on hierarchy
        $day_type = 'work'; // Default
        if (isset($leave_by_date[$gregorian_date_str])) {
            $day_type = 'leave';
        } elseif (isset($properties_by_date[$gregorian_date_str])) {
            $prop_type = $properties_by_date[$gregorian_date_str];
            if ($prop_type === 'official_holiday') {
                $day_type = isset($time_logs_by_date[$gregorian_date_str]) ? 'official_holiday_work' : 'official_holiday_no_work';
            } elseif ($prop_type === 'friday_work') {
                $day_type = 'friday_work';
            }
        } elseif ($is_friday) {
            $day_type = 'friday';
        }

        $logs_by_day[$d] = ['work_hours' => 0, 'entries' => [], 'day_type' => $day_type];

        // Process based on day type for summary calculations
        if ($day_type === 'work') {
            $workdays_in_month++;
        } elseif ($day_type === 'official_holiday_no_work' || $day_type === 'official_holiday_work') {
            $holiday_credit_hours += $daily_goal;
        }

        // Calculate actual worked hours if any time logs exist
        if (isset($time_logs_by_date[$gregorian_date_str])) {
            $daily_total_seconds = 0;
            foreach ($time_logs_by_date[$gregorian_date_str] as $log) {
                $daily_total_seconds += strtotime($log['end_time']) - strtotime($log['start_time']);
                $logs_by_day[$d]['entries'][] = date('H:i', strtotime($log['start_time'])) . ' - ' . date('H:i', strtotime($log['end_time']));
            }
            $daily_hours = $daily_total_seconds / 3600;
            $logs_by_day[$d]['work_hours'] = $daily_hours;

            if ($day_type === 'friday_work' || $day_type === 'official_holiday_work') {
                $summary['monthly_overtime_hours'] += $daily_hours;
            } else {
                $summary['monthly_total_hours'] += $daily_hours;
            }
        }
    }

    // 4. Calculate final summary values
    $total_leave_stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_logs WHERE user_id = :user_id");
    $total_leave_stmt->execute(['user_id' => $user_id]);
    $summary['remaining_leave_days'] = $annual_leave_days - $total_leave_stmt->fetchColumn();

    $summary['required_work_hours'] = $workdays_in_month * $daily_goal;
    $total_credited_hours = $summary['monthly_total_hours'] + $holiday_credit_hours;
    $summary['deficit_surplus_hours'] = $total_credited_hours - $summary['required_work_hours'];

    ksort($logs_by_day);

} catch (Exception $e) {
    die("Error fetching report data: " . $e->getMessage());
}

// --- Prepare Chart Data ---
$chart_labels = [];
$chart_data = [];
foreach ($logs_by_day as $day => $data) {
    $chart_labels[] = $day;
    $chart_data[] = round($data['work_hours'], 2);
}

// --- Fetch available years ---
$available_years = [];
try {
    $years_stmt = $pdo->prepare("SELECT DISTINCT jalali_year FROM time_logs WHERE user_id = :user_id AND jalali_year IS NOT NULL ORDER BY jalali_year DESC");
    $years_stmt->execute(['user_id' => $user_id]);
    $available_years = $years_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

$jalali_months = ["فروردین","اردیبهشت","خرداد","تیر","مرداد","شهریور","مهر","آبان","آذر","دی","بهمن","اسفند"];
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>گزارشات من</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container my-5">
        <?php if(file_exists('nav.php')) { require_once 'nav.php'; } ?>

        <h3 class="mb-4">گزارش ماهانه شما</h3>

        <form action="my_reports.php" method="get" class="row g-3 mb-4 p-3 border rounded bg-light">
            <div class="col-md-5">
                <label for="year" class="form-label">سال</label>
                <select name="year" id="year" class="form-select">
                    <?php foreach ($available_years as $year): ?>
                        <option value="<?php echo $year; ?>" <?php if ($year == $selected_year) echo 'selected'; ?>><?php echo $year; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label for="month" class="form-label">ماه</label>
                <select name="month" id="month" class="form-select">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php if ($m == $selected_month) echo 'selected'; ?>><?php echo $jalali_months[$m-1]; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">نمایش</button>
            </div>
        </form>

        <?php if (empty($properties_by_date) && empty($time_logs_by_date) && empty($leave_by_date)): ?>
            <div class="alert alert-info">هیچ گزارشی برای این ماه ثبت نشده است.</div>
        <?php else: ?>
            <div class="row text-center mb-4">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title">ساعات کاری موظفی</h6>
                            <p class="fs-4 fw-bold"><?php echo round($summary['required_work_hours'], 1); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title">ساعات کاری ثبت شده</h6>
                            <p class="fs-4 fw-bold"><?php echo round($summary['monthly_total_hours'] + $holiday_credit_hours, 1); ?></p>
                            <small class="text-muted">(شامل اعتبار تعطیلات رسمی)</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white <?php echo $summary['deficit_surplus_hours'] >= 0 ? 'bg-success' : 'bg-danger'; ?>">
                        <div class="card-body">
                            <h6 class="card-title">کسر/اضافه کار</h6>
                            <p class="fs-4 fw-bold"><?php echo round($summary['deficit_surplus_hours'], 1); ?></p>
                        </div>
                    </div>
                </div>
                 <div class="col-md-3">
                    <div class="card bg-warning text-dark">
                        <div class="card-body">
                            <h6 class="card-title">اضافه‌کار (روز تعطیل/جمعه)</h6>
                            <p class="fs-4 fw-bold"><?php echo round($summary['monthly_overtime_hours'], 1); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">نمودار ساعات کاری روزانه</div>
                <div class="card-body"><canvas id="workHoursChart"></canvas></div>
            </div>

            <div class="accordion" id="reports-accordion">
                <?php foreach ($logs_by_day as $day => $data): ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $day; ?>">
                                <div class="w-100 d-flex justify-content-between pe-3 align-items-center">
                                    <strong><?php echo "{$selected_year}/{$selected_month}/{$day}"; ?></strong>
                                    <div>
                                        <?php
                                            $badges = [
                                                'leave' => '<span class="badge bg-secondary">مرخصی</span>',
                                                'official_holiday_no_work' => '<span class="badge bg-info">تعطیل رسمی</span>',
                                                'official_holiday_work' => '<span class="badge bg-primary">کار در تعطیل رسمی</span>',
                                                'friday_work' => '<span class="badge bg-warning text-dark">کار در جمعه</span>',
                                                'friday' => '<span class="badge bg-light text-dark">جمعه</span>'
                                            ];
                                            echo $badges[$data['day_type']] ?? '';
                                        ?>
                                    </div>
                                    <?php if($data['work_hours'] > 0): ?>
                                        <span>مجموع: <?php echo round($data['work_hours'], 2); ?> ساعت</span>
                                    <?php elseif($data['day_type'] === 'official_holiday_no_work' || $data['day_type'] === 'official_holiday_work'): ?>
                                        <span>+<?php echo $daily_goal; ?> ساعت (اعتبار)</span>
                                    <?php else: ?>
                                        <span></span> <!-- Empty span for alignment -->
                                    <?php endif; ?>
                                </div>
                            </button>
                        </h2>
                        <div id="collapse-<?php echo $day; ?>" class="accordion-collapse collapse" data-bs-parent="#reports-accordion">
                            <div class="accordion-body">
                                <?php
                                $message = '';
                                if ($data['day_type'] === 'leave') $message = 'این روز به عنوان مرخصی ثبت شده است.';
                                if ($data['day_type'] === 'official_holiday_no_work') $message = 'این روز به عنوان تعطیل رسمی (بدون کارکرد) ثبت شده و معادل ' . $daily_goal . ' ساعت کاری برای شما محاسبه گردید.';
                                if ($data['day_type'] === 'official_holiday_work') $message = 'اعتبار تعطیل رسمی (معادل ' . $daily_goal . ' ساعت) برای شما محاسبه شد. ساعات کاری واقعی شما به عنوان اضافه‌کار ثبت گردید.';
                                if ($data['day_type'] === 'friday') $message = 'روز جمعه (تعطیل).';
                                if ($data['day_type'] === 'work' && empty($data['entries'])) $message = 'برای این روز کاری، هیچ بازه زمانی ثبت نشده است.';

                                if (!empty($data['entries'])): ?>
                                    <h6>بازه های زمانی حضور:</h6>
                                    <ul>
                                        <?php foreach($data['entries'] as $entry): ?>
                                            <li><?php echo $entry; ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                                <?php if($message): ?>
                                    <p class="text-muted mt-2"><?php echo $message; ?></p>
                                <?php endif; ?>
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
                options: { scales: { y: { beginAtZero: true } }, responsive: true, plugins: { legend: { display: false } } }
            });
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
