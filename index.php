<?php
require_once 'config.php';
require_once 'JalaliDate.php';

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){ header("location: login.php"); exit; }

$log_date_jalali = $_GET['date'] ?? JalaliDate::toJalali(date('Y-m-d'));
$work_logs = [];
$total_break_minutes = 0;
$is_editing = isset($_GET['date']);

$gregorian_date_obj = JalaliDate::fromJalaliToDateTime($log_date_jalali);
if ($gregorian_date_obj) {
    $gregorian_date_str = $gregorian_date_obj->format('Y-m-d');
    $user_id = $_SESSION['id'];

    $sql = "SELECT id, start_time, end_time, log_type FROM time_logs WHERE user_id = :user_id AND log_date = :log_date ORDER BY start_time ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $user_id, ':log_date' => $gregorian_date_str]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $log) {
        if ($log['log_type'] === 'work') {
            $work_logs[] = ['id' => $log['id'], 'start' => date('H:i', strtotime($log['start_time'])), 'end' => date('H:i', strtotime($log['end_time']))];
        } else {
            $diff = (new DateTime($log['end_time']))->getTimestamp() - (new DateTime($log['start_time']))->getTimestamp();
            $total_break_minutes += round($diff / 60);
        }
    }
}
$date_parts = explode('/', $log_date_jalali);
$log_date_year = $date_parts[0] ?? '';
$log_date_month = $date_parts[1] ?? '';
$log_date_day = $date_parts[2] ?? '';

// Check if the day is already marked
$day_property = null;
$prop_sql = "SELECT day_type, status FROM day_properties WHERE user_id = :user_id AND log_date = :log_date";
$prop_stmt = $pdo->prepare($prop_sql);
$prop_stmt->execute([':user_id' => $_SESSION['id'], ':log_date' => $gregorian_date_str]);
$day_property = $prop_stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_editing ? 'ویرایش' : 'ثبت'; ?> گزارش</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container my-5">
        <?php if(file_exists('nav.php')) { require_once 'nav.php'; } ?>
        <div class="card" id="log-form-card">
            <div class="card-header d-flex justify-content-between">
                <span><?php echo $is_editing ? 'ویرایش گزارش روز ' . htmlspecialchars($log_date_jalali) : 'ثبت گزارش روزانه'; ?></span>
                <form action="submit_log.php" method="post" id="holiday-request-form" class="d-inline">
                     <input type="hidden" name="log_day" value="<?php echo $log_date_day; ?>">
                     <input type="hidden" name="log_month" value="<?php echo $log_date_month; ?>">
                     <input type="hidden" name="log_year" value="<?php echo $log_date_year; ?>">
                     <input type="hidden" name="day_type" value="official_holiday_request">
                     <button type="submit" class="btn btn-sm btn-info" <?php if($day_property) echo 'disabled'; ?>>درخواست ثبت تعطیلی رسمی</button>
                </form>
            </div>
            <div class="card-body">
                <?php if ($day_property): ?>
                    <div class="alert alert-<?php echo $day_property['status'] === 'approved' ? 'success' : 'warning'; ?>">
                        این روز به عنوان <strong><?php echo $day_property['day_type'] === 'official_holiday' ? 'تعطیل رسمی' : 'روز کاری تعطیل'; ?></strong> با وضعیت <strong><?php echo $day_property['status']; ?></strong> ثبت شده است.
                    </div>
                <?php endif; ?>
                <form action="submit_log.php" method="post" id="log-form">
                    <input type="hidden" name="log_day" value="<?php echo htmlspecialchars($log_date_day); ?>">
                    <input type="hidden" name="log_month" value="<?php echo htmlspecialchars($log_date_month); ?>">
                    <input type="hidden" name="log_year" value="<?php echo htmlspecialchars($log_date_year); ?>">

                    <div class="mb-4">
                        <label class="form-label fw-bold">تاریخ</label>
                        <div class="row g-2 align-items-center">
                             <div class="col"><input type="text" class="form-control" value="<?php echo htmlspecialchars($log_date_day); ?>" readonly></div>
                            <div class="col-5"><input type="text" class="form-control" value="<?php echo ["فروردین","اردیبهشت","خرداد","تیر","مرداد","شهریور","مهر","آبان","آذر","دی","بهمن","اسفند"][$log_date_month-1]; ?>" readonly></div>
                            <div class="col"><input type="text" class="form-control" value="<?php echo htmlspecialchars($log_date_year); ?>" readonly></div>
                            <div class="col-auto align-self-end"><a href="index.php" class="btn btn-outline-secondary">تغییر تاریخ</a></div>
                        </div>
                    </div>
                    <hr>
                    <h5 class="fw-bold">زمان های حضور</h5>
                    <div id="time-intervals-container">
                         <?php if (empty($work_logs)): ?>
                            <div class="row g-2 mb-2 align-items-center time-interval-row">
                                <div class="col"><label class="form-label">ساعت ورود</label><input type="text" class="form-control time-input" name="start_time[]"></div>
                                <div class="col"><label class="form-label">ساعت خروج</label><input type="text" class="form-control time-input" name="end_time[]"></div>
                                <div class="col-auto"><button type="button" class="btn btn-sm btn-danger remove-interval" style="display: none;">-</button></div>
                            </div>
                        <?php else: foreach ($work_logs as $i => $log): ?>
                             <div class="row g-2 mb-2 align-items-center time-interval-row">
                                <div class="col"><label class="form-label">ساعت ورود</label><input type="text" class="form-control time-input" name="start_time[]" value="<?php echo htmlspecialchars($log['start']); ?>"></div>
                                <div class="col"><label class="form-label">ساعت خروج</label><input type="text" class="form-control time-input" name="end_time[]" value="<?php echo htmlspecialchars($log['end']); ?>"></div>
                                <div class="col-auto"><button type="button" class="btn btn-sm btn-danger remove-interval" <?php if ($i==0) echo 'style="display: none;"';?>>-</button></div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                    <button type="button" class="btn btn-outline-success mt-2" id="add-interval">افزودن بازه حضور +</button>
                    <hr>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_overtime" id="is_overtime" value="1">
                        <label class="form-check-label" for="is_overtime">
                            این ساعات به عنوان اضافه‌کار (روز تعطیل/جمعه) ثبت شود.
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">ذخیره گزارش</button>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="main.js"></script>
</body>
</html>
