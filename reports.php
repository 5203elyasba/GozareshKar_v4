<?php
require_once 'config.php';
require_once 'JalaliDate.php';

// --- Authentication & Authorization ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'admin') {
    header("location: login.php");
    exit;
}

// --- Date Selection ---
$current_jalali_date = JalaliDate::toJalali(date('Y-m-d'));
list($current_year, $current_month, $_) = explode('/', $current_jalali_date);
$selected_year = $_GET['year'] ?? $current_year;
$selected_month = $_GET['month'] ?? $current_month;

// --- Data Fetching & Processing ---
$report_data = [];
$company_summary = [
    'total_required_hours' => 0,
    'total_worked_hours' => 0,
    'total_overtime_hours' => 0,
    'total_deficit_hours' => 0,
    'total_leave_days' => 0,
];

try {
    // 1. Get all non-admin users
    $users_stmt = $pdo->query("SELECT id, full_name, daily_hours_goal FROM users WHERE role = 'employee' ORDER BY full_name ASC");
    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

    $first_day_gregorian_str = JalaliDate::fromJalaliToDateTime("$selected_year/$selected_month/01")->format('Y-m-d');
    $days_in_month = JalaliDate::daysInMonth((int)$selected_year, (int)$selected_month);
    $last_day_gregorian_str = JalaliDate::fromJalaliToDateTime("$selected_year/$selected_month/$days_in_month")->format('Y-m-d');

    // 2. Loop through each user and calculate their report
    foreach ($users as $user) {
        $user_id = $user['id'];
        $daily_goal = $user['daily_hours_goal'];

        $user_report = [
            'full_name' => $user['full_name'],
            'required_hours' => 0,
            'worked_hours' => 0,
            'overtime_hours' => 0,
            'deficit_hours' => 0,
            'leave_days' => 0
        ];

        // Fetch data for this user for the selected month
        $prop_sql = "SELECT log_date, day_type FROM day_properties WHERE user_id = ? AND log_date BETWEEN ? AND ?";
        $prop_stmt = $pdo->prepare($prop_sql);
        $prop_stmt->execute([$user_id, $first_day_gregorian_str, $last_day_gregorian_str]);
        $properties_by_date = $prop_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $leave_sql = "SELECT COUNT(*) FROM leave_logs WHERE user_id = ? AND leave_date BETWEEN ? AND ?";
        $leave_stmt = $pdo->prepare($leave_sql);
        $leave_stmt->execute([$user_id, $first_day_gregorian_str, $last_day_gregorian_str]);
        $user_report['leave_days'] = $leave_stmt->fetchColumn();

        $log_sql = "SELECT log_date, start_time, end_time FROM time_logs WHERE user_id = ? AND log_date BETWEEN ? AND ?";
        $log_stmt = $pdo->prepare($log_sql);
        $log_stmt->execute([$user_id, $first_day_gregorian_str, $last_day_gregorian_str]);
        $time_logs = $log_stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

        // Calculate stats for the user
        $workdays_in_month = 0;
        $holiday_credit_hours = 0;
        $monthly_total_hours = 0;
        $monthly_overtime_hours = 0;

        for ($d = 1; $d <= $days_in_month; $d++) {
            $gregorian_date_obj = JalaliDate::fromJalaliToDateTime("$selected_year/$selected_month/$d");
            $gregorian_date_str = $gregorian_date_obj->format('Y-m-d');
            $is_friday = ($gregorian_date_obj->format('N') == 5);

            $day_type = 'work';
            if (isset($properties_by_date[$gregorian_date_str])) {
                $prop_type = $properties_by_date[$gregorian_date_str];
                if ($prop_type === 'official_holiday') $day_type = isset($time_logs[$gregorian_date_str]) ? 'official_holiday_work' : 'official_holiday_no_work';
                elseif ($prop_type === 'friday_work') $day_type = 'friday_work';
            } elseif ($user_report['leave_days'] > 0 && isset($time_logs[$gregorian_date_str])) {
                // Simplified check, better logic would join on leave_logs table
            } elseif ($is_friday) {
                $day_type = 'friday';
            }

            if ($day_type === 'work' && !$is_friday) $workdays_in_month++;
            if ($day_type === 'official_holiday_no_work' || $day_type === 'official_holiday_work') $holiday_credit_hours += $daily_goal;

            if (isset($time_logs[$gregorian_date_str])) {
                $daily_seconds = 0;
                foreach ($time_logs[$gregorian_date_str] as $log) {
                    if ($log['start_time'] && $log['end_time']) {
                        $daily_seconds += strtotime($log['end_time']) - strtotime($log['start_time']);
                    }
                }
                $daily_hours = $daily_seconds / 3600;
                if ($day_type === 'friday_work' || $day_type === 'official_holiday_work') {
                    $monthly_overtime_hours += $daily_hours;
                } else {
                    $monthly_total_hours += $daily_hours;
                }
            }
        }

        $user_report['required_hours'] = $workdays_in_month * $daily_goal;
        $user_report['worked_hours'] = $monthly_total_hours + $holiday_credit_hours;
        $user_report['overtime_hours'] = $monthly_overtime_hours;
        $deficit_surplus = $user_report['worked_hours'] - $user_report['required_hours'];
        $user_report['deficit_hours'] = $deficit_surplus;

        $report_data[] = $user_report;

        // Add to company summary
        $company_summary['total_required_hours'] += $user_report['required_hours'];
        $company_summary['total_worked_hours'] += $user_report['worked_hours'];
        $company_summary['total_overtime_hours'] += $user_report['overtime_hours'];
        $company_summary['total_leave_days'] += $user_report['leave_days'];
    }

} catch (Exception $e) {
    die("Error fetching report data: " . $e->getMessage());
}

$jalali_months = ["فروردین","اردیبهشت","خرداد","تیر","مرداد","شهریور","مهر","آبان","آذر","دی","بهمن","اسفند"];
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>گزارشات کلی</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container-fluid my-5">
        <?php if(file_exists('nav.php')) { require_once 'nav.php'; } ?>

        <h3 class="mb-4">داشبورد گزارشات کلی</h3>

        <form action="reports.php" method="get" class="row g-3 mb-4 p-3 border rounded bg-light">
            <div class="col-md-4">
                <label for="year" class="form-label">سال</label>
                <select name="year" id="year" class="form-select">
                    <?php for($y = $current_year - 2; $y <= $current_year; $y++): ?>
                        <option value="<?php echo $y; ?>" <?php if ($y == $selected_year) echo 'selected'; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="month" class="form-label">ماه</label>
                <select name="month" id="month" class="form-select">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php if ($m == $selected_month) echo 'selected'; ?>><?php echo $jalali_months[$m-1]; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">نمایش گزارش</button>
            </div>
        </form>

        <div class="row text-center mb-4 g-3">
            <div class="col-lg-3 col-md-6"><div class="card"><div class="card-body"><h6 class="card-title">کل ساعات موظفی</h6><p class="fs-4 fw-bold"><?php echo round($company_summary['total_required_hours']); ?></p></div></div></div>
            <div class="col-lg-3 col-md-6"><div class="card"><div class="card-body"><h6 class="card-title">کل ساعات کاری</h6><p class="fs-4 fw-bold"><?php echo round($company_summary['total_worked_hours']); ?></p></div></div></div>
            <div class="col-lg-3 col-md-6"><div class="card"><div class="card-body"><h6 class="card-title">کل اضافه‌کار</h6><p class="fs-4 fw-bold text-success"><?php echo round($company_summary['total_overtime_hours']); ?></p></div></div></div>
            <div class="col-lg-3 col-md-6"><div class="card"><div class="card-body"><h6 class="card-title">کل مرخصی (روز)</h6><p class="fs-4 fw-bold text-secondary"><?php echo $company_summary['total_leave_days']; ?></p></div></div></div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">گزارش مقایسه‌ای کارمندان برای <?php echo $jalali_months[$selected_month-1] . ' ' . $selected_year; ?></h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>نام کارمند</th>
                                <th>ساعات موظفی</th>
                                <th>ساعات کاری</th>
                                <th>اضافه‌کار</th>
                                <th>کسر/اضافه</th>
                                <th>مرخصی (روز)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($report_data)): ?>
                                <tr><td colspan="6" class="text-center">هیچ داده‌ای برای نمایش وجود ندارد.</td></tr>
                            <?php else: ?>
                                <?php foreach ($report_data as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                        <td><?php echo round($row['required_hours'], 1); ?></td>
                                        <td><?php echo round($row['worked_hours'], 1); ?></td>
                                        <td><?php echo round($row['overtime_hours'], 1); ?></td>
                                        <td>
                                            <?php if ($row['deficit_hours'] >= 0): ?>
                                                <span class="badge bg-success"><?php echo round($row['deficit_hours'], 1); ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-danger"><?php echo round($row['deficit_hours'], 1); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $row['leave_days']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>