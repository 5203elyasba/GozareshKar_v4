<?php
require_once 'config.php';
require_once 'JalaliDate.php';

// Redirect if not logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// Redirect admin to admin panel
if (isset($_SESSION["role"]) && $_SESSION["role"] === 'admin') {
    header("location: admin.php");
    exit;
}

// --- Logic to fetch existing data for a given date ---
$user_id = $_SESSION['id'];
$log_date_jalali = $_GET['date'] ?? JalaliDate::toJalali(date('Y-m-d'));
$gregorian_date_obj = JalaliDate::fromJalaliToDateTime($log_date_jalali);
$gregorian_date_str = $gregorian_date_obj ? $gregorian_date_obj->format('Y-m-d') : '';

$work_logs = [];
$day_property = null;

if ($gregorian_date_str) {
    // Fetch day properties
    $prop_sql = "SELECT day_type, status FROM day_properties WHERE user_id = :user_id AND log_date = :log_date";
    $prop_stmt = $pdo->prepare($prop_sql);
    $prop_stmt->execute([':user_id' => $user_id, ':log_date' => $gregorian_date_str]);
    $day_property = $prop_stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch work logs
    $sql = "SELECT id, start_time, end_time FROM time_logs WHERE user_id = :user_id AND log_date = :log_date AND log_type = 'work' ORDER BY start_time ASC";
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

// Determine the selected day type for the form
$selected_day_type = 'work'; // Default
if ($day_property) {
    if ($day_property['day_type'] === 'leave') $selected_day_type = 'leave';
    if ($day_property['day_type'] === 'official_holiday') $selected_day_type = 'official_holiday';
    if ($day_property['day_type'] === 'friday_work') $selected_day_type = 'friday_work';
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ثبت گزارش روزانه</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container my-5">
        <?php if(file_exists('nav.php')) { require_once 'nav.php'; } ?>
        <div class="card">
            <div class="card-header">
                <h3>ثبت گزارش روزانه</h3>
            </div>
            <div class="card-body">
                <form action="handle_daily_log.php" method="post" id="daily-log-form">
                    <!-- Date Selection -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">تاریخ</label>
                        <div class="row g-2 align-items-center">
                            <div class="col"><input type="text" class="form-control" name="log_year" value="<?php echo htmlspecialchars($log_date_year); ?>"></div>
                            <div class="col-5">
                                <select class="form-select" name="log_month">
                                    <?php for($m=1; $m<=12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php if($log_date_month == $m) echo 'selected'; ?>><?php echo ["فروردین","اردیبهشت","خرداد","تیر","مرداد","شهریور","مهر","آبان","آذر","دی","بهمن","اسفند"][$m-1]; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col"><input type="text" class="form-control" name="log_day" value="<?php echo htmlspecialchars($log_date_day); ?>"></div>
                            <div class="col-auto"><button type="button" id="fetch-date-btn" class="btn btn-outline-secondary">بررسی تاریخ</button></div>
                        </div>
                    </div>
                    <input type="hidden" name="log_date" value="<?php echo htmlspecialchars($log_date_jalali); ?>">

                    <!-- Day Type Selection -->
                    <div class="mb-4">
                        <label for="day_type" class="form-label fw-bold">نوع روز</label>
                        <select class="form-select" name="day_type" id="day_type">
                            <option value="work" <?php if($selected_day_type == 'work') echo 'selected';?>>روز کاری</option>
                            <option value="friday_work" <?php if($selected_day_type == 'friday_work') echo 'selected';?>>اضافه‌کار (روز تعطیل/جمعه)</option>
                            <option value="leave" <?php if($selected_day_type == 'leave') echo 'selected';?>>مرخصی</option>
                            <option value="official_holiday" <?php if($selected_day_type == 'official_holiday') echo 'selected';?>>تعطیل رسمی (بدون کارکرد)</option>
                        </select>
                    </div>

                    <!-- Time Intervals -->
                    <div id="time-intervals-section">
                        <h5 class="fw-bold">ساعات کاری</h5>
                        <div id="time-intervals-container">
                            <?php if (empty($work_logs)): ?>
                                <div class="row g-2 mb-2 time-interval-row">
                                    <div class="col"><input type="time" class="form-control" name="start_time[]" placeholder="ساعت ورود"></div>
                                    <div class="col"><input type="time" class="form-control" name="end_time[]" placeholder="ساعت خروج"></div>
                                    <div class="col-auto"><button type="button" class="btn btn-sm btn-danger remove-interval" style="display: none;">-</button></div>
                                </div>
                            <?php else: foreach ($work_logs as $log): ?>
                                <div class="row g-2 mb-2 time-interval-row">
                                    <div class="col"><input type="time" class="form-control" name="start_time[]" value="<?php echo htmlspecialchars($log['start']); ?>"></div>
                                    <div class="col"><input type="time" class="form-control" name="end_time[]" value="<?php echo htmlspecialchars($log['end']); ?>"></div>
                                    <div class="col-auto"><button type="button" class="btn btn-sm btn-danger remove-interval">-</button></div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                        <button type="button" class="btn btn-outline-success mt-2" id="add-interval">+ افزودن بازه</button>
                    </div>

                    <hr>
                    <button type="submit" class="btn btn-primary w-100">ثبت گزارش</button>
                </form>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const dayTypeSelect = document.getElementById('day_type');
        const timeIntervalsSection = document.getElementById('time-intervals-section');
        const addIntervalBtn = document.getElementById('add-interval');
        const timeContainer = document.getElementById('time-intervals-container');
        const fetchDateBtn = document.getElementById('fetch-date-btn');

        function toggleTimeSection() {
            const selectedType = dayTypeSelect.value;
            if (selectedType === 'work' || selectedType === 'friday_work') {
                timeIntervalsSection.style.display = 'block';
            } else {
                timeIntervalsSection.style.display = 'none';
            }
        }

        function updateRemoveButtons() {
            const rows = timeContainer.querySelectorAll('.time-interval-row');
            rows.forEach(row => {
                const removeBtn = row.querySelector('.remove-interval');
                if(removeBtn) removeBtn.style.display = (rows.length > 1) ? 'inline-block' : 'none';
            });
        }

        dayTypeSelect.addEventListener('change', toggleTimeSection);

        addIntervalBtn.addEventListener('click', () => {
            const newRow = document.createElement('div');
            newRow.classList.add('row', 'g-2', 'mb-2', 'time-interval-row');
            newRow.innerHTML = `
                <div class="col"><input type="time" class="form-control" name="start_time[]"></div>
                <div class="col"><input type="time" class="form-control" name="end_time[]"></div>
                <div class="col-auto"><button type="button" class="btn btn-sm btn-danger remove-interval">-</button></div>
            `;
            timeContainer.appendChild(newRow);
            updateRemoveButtons();
        });

        timeContainer.addEventListener('click', e => {
            if (e.target && e.target.classList.contains('remove-interval')) {
                e.target.closest('.time-interval-row').remove();
                updateRemoveButtons();
            }
        });

        fetchDateBtn.addEventListener('click', () => {
            const year = document.querySelector('input[name="log_year"]').value;
            const month = document.querySelector('select[name="log_month"]').value;
            const day = document.querySelector('input[name="log_day"]').value;
            window.location.href = 'new_log.php?date=' + year + '/' + month.padStart(2, '0') + '/' + day.padStart(2, '0');
        });

        // Initial setup
        toggleTimeSection();
        updateRemoveButtons();
    });
    </script>
</body>
</html>