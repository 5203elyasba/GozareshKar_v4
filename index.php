<?php
require_once 'config.php';
require_once 'JalaliDate.php';

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ header("location: login.php"); exit; }

// If user is an admin, redirect them to the admin panel.
if(isset($_SESSION["role"]) && $_SESSION["role"] === 'admin'){
    header("location: admin.php");
    exit;
}

// --- Fetch existing data for the selected date ---
$user_id = $_SESSION['id'];
$log_date_jalali = $_GET['date'] ?? JalaliDate::toJalali(date('Y-m-d'));
$gregorian_date_obj = JalaliDate::fromJalaliToDateTime($log_date_jalali);
$gregorian_date_str = $gregorian_date_obj ? $gregorian_date_obj->format('Y-m-d') : '';

$work_logs = [];
$day_property = null;
$selected_day_type = 'work'; // Default value

if ($gregorian_date_str) {
    // Fetch day properties if they exist
    $prop_sql = "SELECT day_type FROM day_properties WHERE user_id = :user_id AND log_date = :log_date";
    $prop_stmt = $pdo->prepare($prop_sql);
    $prop_stmt->execute([':user_id' => $user_id, ':log_date' => $gregorian_date_str]);
    $day_property = $prop_stmt->fetch(PDO::FETCH_ASSOC);
    if ($day_property) {
        $selected_day_type = $day_property['day_type'];
    }

    // Fetch work logs for the date
    $sql = "SELECT id, start_time, end_time FROM time_logs WHERE user_id = :user_id AND log_date = :log_date ORDER BY start_time ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $user_id, ':log_date' => $gregorian_date_str]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $log) {
        $work_logs[] = [
            'start' => $log['start_time'] ? date('H:i', strtotime($log['start_time'])) : '',
            'end' => $log['end_time'] ? date('H:i', strtotime($log['end_time'])) : ''
        ];
    }
}

list($log_date_year, $log_date_month, $log_date_day) = explode('/', $log_date_jalali);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ثبت و ویرایش گزارش روزانه</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container my-5">
        <?php if(file_exists('nav.php')) { require_once 'nav.php'; } ?>
        <div class="card" id="log-form-card">
            <div class="card-header">
                <h3 class="mb-0">ثبت گزارش روز <?php echo htmlspecialchars($log_date_jalali); ?></h3>
            </div>
            <div class="card-body">
                <form action="handle_log_submission.php" method="post" id="log-form">
                    <input type="hidden" name="log_date_jalali" value="<?php echo htmlspecialchars($log_date_jalali); ?>">

                    <!-- Date Selection Section -->
                    <div class="mb-4 p-3 border rounded bg-light">
                        <label class="form-label fw-bold">۱. تاریخ مورد نظر را انتخاب کنید</label>
                        <div class="row g-2 align-items-center">
                             <div class="col"><input type="number" class="form-control" name="log_year" value="<?php echo htmlspecialchars($log_date_year); ?>" required></div>
                             <div class="col-5">
                                <select class="form-select" name="log_month" required>
                                    <?php for($m=1; $m<=12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php if($log_date_month == $m) echo 'selected'; ?>><?php echo ["فروردین","اردیبهشت","خرداد","تیر","مرداد","شهریور","مهر","آبان","آذر","دی","بهمن","اسفند"][$m-1]; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col"><input type="number" class="form-control" name="log_day" value="<?php echo htmlspecialchars($log_date_day); ?>" required></div>
                            <div class="col-auto align-self-end"><button type="button" id="fetch-date-btn" class="btn btn-outline-primary">بررسی تاریخ</button></div>
                        </div>
                        <div class="form-text">برای مشاهده یا ویرایش گزارش یک روز دیگر، تاریخ را تغییر داده و دکمه "بررسی تاریخ" را بزنید.</div>
                    </div>

                    <!-- Day Type Section -->
                    <div class="mb-4 p-3 border rounded">
                        <label for="day_type" class="form-label fw-bold">۲. نوع روز را مشخص کنید</label>
                        <select class="form-select" name="day_type" id="day_type">
                            <option value="work" <?php if($selected_day_type == 'work') echo 'selected'; ?>>روز کاری عادی</option>
                            <option value="friday_work" <?php if($selected_day_type == 'friday_work') echo 'selected'; ?>>اضافه‌کار (کار در روز تعطیل/جمعه)</option>
                            <option value="leave" <?php if($selected_day_type == 'leave') echo 'selected'; ?>>مرخصی (با کسر از موجودی)</option>
                            <option value="official_holiday" <?php if($selected_day_type == 'official_holiday') echo 'selected'; ?>>تعطیل رسمی (بدون کارکرد)</option>
                        </select>
                        <div class="form-text">با انتخاب هر گزینه، محاسبات مربوط به حقوق و گزارش‌ها به صورت خودکار انجام خواهد شد.</div>
                    </div>

                    <!-- Time Intervals Section -->
                    <div class="mb-4 p-3 border rounded" id="time-intervals-section">
                        <label class="form-label fw-bold">۳. ساعات کاری را وارد کنید</label>
                        <div id="time-intervals-container">
                            <?php if (empty($work_logs)): ?>
                                <div class="row g-2 mb-2 align-items-center time-interval-row">
                                    <div class="col"><label class="form-label small">ساعت ورود</label><input type="time" class="form-control" name="start_time[]"></div>
                                    <div class="col"><label class="form-label small">ساعت خروج</label><input type="time" class="form-control" name="end_time[]"></div>
                                    <div class="col-auto d-flex align-items-end"><button type="button" class="btn btn-sm btn-danger remove-interval" style="display: none;">-</button></div>
                                </div>
                            <?php else: foreach ($work_logs as $log): ?>
                                <div class="row g-2 mb-2 align-items-center time-interval-row">
                                    <div class="col"><label class="form-label small">ساعت ورود</label><input type="time" class="form-control" name="start_time[]" value="<?php echo htmlspecialchars($log['start']); ?>"></div>
                                    <div class="col"><label class="form-label small">ساعت خروج</label><input type="time" class="form-control" name="end_time[]" value="<?php echo htmlspecialchars($log['end']); ?>"></div>
                                    <div class="col-auto d-flex align-items-end"><button type="button" class="btn btn-sm btn-danger remove-interval">-</button></div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                        <button type="button" class="btn btn-outline-success mt-2" id="add-interval">افزودن بازه جدید +</button>
                        <div class="form-text">اگر در طول روز چند بار ورود و خروج داشته‌اید، از این دکمه استفاده کنید. ساعت‌ها باید به فرمت ۲۴ ساعته باشند (مثلا ۱۴:۳۰).</div>
                    </div>

                    <hr>
                    <button type="submit" class="btn btn-primary w-100 btn-lg">ثبت و ذخیره گزارش</button>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="main.js"></script>
</body>
</html>