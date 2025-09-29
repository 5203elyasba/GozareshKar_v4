<?php
require_once 'config.php';
require_once 'JalaliDate.php';

// --- Authentication & Setup ---
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ header("location: login.php"); exit; }
if(isset($_SESSION["role"]) && $_SESSION["role"] === 'admin'){ header("location: admin.php"); exit; }

// --- Data Fetching for the Selected Date ---
$user_id = $_SESSION['id'];
$log_date_jalali = $_GET['date'] ?? JalaliDate::toJalali(date('Y-m-d'));
$gregorian_date_obj = JalaliDate::fromJalaliToDateTime($log_date_jalali);
$gregorian_date_str = $gregorian_date_obj ? $gregorian_date_obj->format('Y-m-d') : '';

$work_logs = [];
$day_property = null;
$selected_day_type = 'work'; // Default

if ($gregorian_date_str) {
    // Fetch day properties
    $prop_sql = "SELECT day_type FROM day_properties WHERE user_id = :user_id AND log_date = :log_date";
    $prop_stmt = $pdo->prepare($prop_sql);
    $prop_stmt->execute([':user_id' => $user_id, ':log_date' => $gregorian_date_str]);
    if ($prop = $prop_stmt->fetch(PDO::FETCH_ASSOC)) {
        $selected_day_type = $prop['day_type'];
    } else {
        // If no property, determine if it's a Friday by default
        $is_friday = ($gregorian_date_obj->format('N') == 5);
        $selected_day_type = $is_friday ? 'friday' : 'work';
    }

    // Fetch work logs
    $sql = "SELECT id, start_time, end_time FROM time_logs WHERE user_id = :user_id AND log_date = :log_date ORDER BY start_time ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $user_id, ':log_date' => $gregorian_date_str]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $log) {
        $work_logs[] = [
            'id' => $log['id'],
            'start' => $log['start_time'] ? date('H:i', strtotime($log['start_time'])) : '',
            'end' => $log['end_time'] ? date('H:i', strtotime($log['end_time'])) : ''
        ];
    }
}

list($log_date_year, $log_date_month, $log_date_day) = explode('/', $log_date_jalali);

// Helper function to generate time dropdowns
function generate_time_dropdowns($prefix, $log_id, $selectedValue = '') {
    $hour = '';
    $minute = '';
    if ($selectedValue && strpos($selectedValue, ':') !== false) {
        list($hour, $minute) = explode(':', $selectedValue);
    }

    $hour_html = "<select name='{$prefix}_hour' class='form-select time-select' data-type='{$prefix}' data-log-id='{$log_id}'><option value=''>-</option>";
    for ($h = 0; $h <= 23; $h++) {
        $h_padded = str_pad($h, 2, '0', STR_PAD_LEFT);
        $selected = ($h_padded === $hour) ? 'selected' : '';
        $hour_html .= "<option value='{$h_padded}' {$selected}>{$h_padded}</option>";
    }
    $hour_html .= "</select>";

    $minute_html = "<select name='{$prefix}_minute' class='form-select time-select' data-type='{$prefix}' data-log-id='{$log_id}'><option value=''>-</option>";
    for ($m = 0; $m <= 59; $m+=5) { // 5-minute increments
        $m_padded = str_pad($m, 2, '0', STR_PAD_LEFT);
        $selected = ($m_padded === $minute) ? 'selected' : '';
        $minute_html .= "<option value='{$m_padded}' {$selected}>{$m_padded}</option>";
    }
    $minute_html .= "</select>";

    return "<div class='input-group'>{$hour_html}{$minute_html}</div>";
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ثبت گزارش روزانه</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container my-5">
        <?php if(file_exists('nav.php')) { require_once 'nav.php'; } ?>
        <div class="card" id="log-form-card">
            <div class="card-header"><h3 class="mb-0">ثبت گزارش روز <span id="current-date-display"><?php echo htmlspecialchars($log_date_jalali); ?></span></h3></div>
            <div class="card-body">
                <form id="log-form" data-jalali-date="<?php echo htmlspecialchars($log_date_jalali); ?>">

                    <!-- Date Selection -->
                    <div class="mb-4 p-3 border rounded bg-light">
                        <label class="form-label fw-bold">۱. انتخاب تاریخ</label>
                        <div class="row g-2 align-items-center">
                             <div class="col"><input type="number" class="form-control" id="log_year" value="<?php echo htmlspecialchars($log_date_year); ?>"></div>
                             <div class="col-5"><select class="form-select" id="log_month"><?php for($m=1; $m<=12; $m++){ echo "<option value='{$m}' ".($log_date_month == $m ? 'selected' : '').">".["فروردین","اردیبهشت","خرداد","تیر","مرداد","شهریور","مهر","آبان","آذر","دی","بهمن","اسفند"][$m-1]."</option>"; } ?></select></div>
                            <div class="col"><input type="number" class="form-control" id="log_day" value="<?php echo htmlspecialchars($log_date_day); ?>"></div>
                            <div class="col-auto align-self-end"><button type="button" id="fetch-date-btn" class="btn btn-outline-primary">بررسی تاریخ</button></div>
                        </div>
                    </div>

                    <!-- Day Type Selection -->
                    <div class="mb-4 p-3 border rounded">
                        <label for="day_type" class="form-label fw-bold">۲. تعیین نوع روز <span class="status-icon" id="day-type-status"></span></label>
                        <select class="form-select" id="day_type">
                            <option value="work" <?php if($selected_day_type == 'work') echo 'selected'; ?>>روز کاری عادی</option>
                            <option value="friday" <?php if($selected_day_type == 'friday') echo 'selected'; ?>>جمعه (تعطیل)</option>
                            <option value="leave" <?php if($selected_day_type == 'leave') echo 'selected'; ?>>مرخصی (کسر از موجودی)</option>
                            <option value="official_holiday_no_work" <?php if($selected_day_type == 'official_holiday_no_work') echo 'selected'; ?>>تعطیل رسمی (بدون کارکرد)</option>
                            <option value="official_holiday_work" <?php if($selected_day_type == 'official_holiday_work') echo 'selected'; ?>>کار در روز تعطیل رسمی</option>
                            <option value="friday_work" <?php if($selected_day_type == 'friday_work') echo 'selected'; ?>>کار در روز جمعه</option>
                        </select>
                    </div>

                    <!-- Time Intervals Section -->
                    <div class="mb-4 p-3 border rounded" id="time-intervals-section">
                        <label class="form-label fw-bold">۳. انتخاب ساعات کاری</label>
                        <div id="time-intervals-container">
                            <?php if (empty($work_logs)): ?>
                                <div class="row g-3 mb-2 align-items-center time-interval-row" data-log-id="">
                                    <div class="col-12 col-md-5"><label class="form-label small d-md-none">ورود</label><?php echo generate_time_dropdowns('start', ''); ?></div>
                                    <div class="col-12 col-md-5"><label class="form-label small d-md-none">خروج</label><?php echo generate_time_dropdowns('end', ''); ?></div>
                                    <div class="col-12 col-md-2 d-flex justify-content-end align-items-center">
                                        <span class="status-icon me-2"></span>
                                        <button type="button" class="btn btn-sm btn-danger remove-interval" style="display: none;">-</button>
                                    </div>
                                </div>
                            <?php else: foreach ($work_logs as $log): ?>
                                <div class="row g-3 mb-2 align-items-center time-interval-row" data-log-id="<?php echo $log['id']; ?>">
                                    <div class="col-12 col-md-5"><label class="form-label small d-md-none">ورود</label><?php echo generate_time_dropdowns('start', $log['id'], $log['start']); ?></div>
                                    <div class="col-12 col-md-5"><label class="form-label small d-md-none">خروج</label><?php echo generate_time_dropdowns('end', $log['id'], $log['end']); ?></div>
                                    <div class="col-12 col-md-2 d-flex justify-content-end align-items-center">
                                        <span class="status-icon me-2"></span>
                                        <button type="button" class="btn btn-sm btn-danger remove-interval">-</button>
                                    </div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                        <button type="button" class="btn btn-outline-success mt-2" id="add-interval">افزودن بازه جدید +</button>
                    </div>

                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="main.js"></script>
</body>
</html>