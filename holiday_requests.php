<?php
require_once 'config.php';
require_once 'JalaliDate.php';

// --- Authentication & Authorization ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'admin') {
    header("location: login.php");
    exit;
}

// --- Handle Actions (Approve/Reject) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = $_POST['request_id'];
    $action = $_POST['action'];
    $user_id_for_log = $_POST['user_id_for_log'];
    $log_date_for_log = $_POST['log_date_for_log'];

    try {
        if ($action === 'approve') {
            // 1. Update status to 'approved'
            $sql = "UPDATE day_properties SET status = 'approved' WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['id' => $request_id]);

            // 2. Log the full work day for the user
            $user_sql = "SELECT daily_hours_goal FROM users WHERE id = :id";
            $user_stmt = $pdo->prepare($user_sql);
            $user_stmt->execute(['id' => $user_id_for_log]);
            $daily_goal = $user_stmt->fetchColumn() ?: 8;

            list($j_year, $j_month, $j_day) = explode('/', JalaliDate::toJalali($log_date_for_log));

            $start_time = '08:00:00';
            $end_time = date('H:i:s', strtotime($start_time) + ($daily_goal * 3600));

            $insert_sql = "INSERT INTO time_logs (user_id, log_date, start_time, end_time, log_type, jalali_year, jalali_month, jalali_day) VALUES (:user_id, :log_date, :start_time, :end_time, 'work', :jalali_year, :jalali_month, :jalali_day)";
            $insert_stmt = $pdo->prepare($insert_sql);
            $insert_stmt->execute([
                ':user_id' => $user_id_for_log,
                ':log_date' => $log_date_for_log,
                ':start_time' => $start_time,
                ':end_time' => $end_time,
                ':jalali_year' => (int)$j_year,
                ':jalali_month' => (int)$j_month,
                ':jalali_day' => (int)$j_day
            ]);

        } elseif ($action === 'reject') {
            $sql = "UPDATE day_properties SET status = 'rejected' WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['id' => $request_id]);
        }
        header("location: holiday_requests.php?success=1");
        exit;
    } catch (Exception $e) {
        $error = "خطا در پردازش درخواست: " . $e->getMessage();
    }
}


// --- Fetch Pending Requests ---
try {
    $sql = "SELECT dp.id, dp.log_date, u.full_name, u.id as user_id FROM day_properties dp JOIN users u ON dp.user_id = u.id WHERE dp.status = 'pending' AND dp.day_type = 'official_holiday' ORDER BY dp.created_at ASC";
    $stmt = $pdo->query($sql);
    $pending_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("خطا در بارگذاری درخواست‌ها: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>درخواست‌های تعطیلات رسمی</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container my-5">
        <?php if(file_exists('nav.php')) { require_once 'nav.php'; } ?>

        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">درخواست‌های ثبت تعطیلی رسمی</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($error)): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
                <?php if (isset($_GET['success'])): ?><div class="alert alert-success">عملیات با موفقیت انجام شد.</div><?php endif; ?>

                <?php if (empty($pending_requests)): ?>
                    <div class="alert alert-info">هیچ درخواست جدیدی وجود ندارد.</div>
                <?php else: ?>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>کاربر</th>
                                <th>تاریخ درخواست شده</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_requests as $request): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($request['full_name']); ?></td>
                                    <td><?php echo JalaliDate::toJalali($request['log_date']); ?></td>
                                    <td>
                                        <form action="holiday_requests.php" method="post" class="d-inline">
                                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                            <input type="hidden" name="user_id_for_log" value="<?php echo $request['user_id']; ?>">
                                            <input type="hidden" name="log_date_for_log" value="<?php echo $request['log_date']; ?>">
                                            <button type="submit" name="action" value="approve" class="btn btn-sm btn-success">تایید</button>
                                            <button type="submit" name="action" value="reject" class="btn btn-sm btn-danger">رد</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                 <a href="admin.php" class="btn btn-secondary mt-3">بازگشت</a>
            </div>
        </div>
    </div>
</body>
</html>
