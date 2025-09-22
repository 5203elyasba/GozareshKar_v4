<?php
require_once 'config.php';

// Authentication
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// User data from session
$user_id = $_SESSION["id"];
$username = $_SESSION["username"];
$full_name = $_SESSION["full_name"] ?? 'کاربر'; // Fallback
$role = $_SESSION["role"];

require_once 'ReportCalculator.php';

$calculator = new ReportCalculator($pdo);
$report_data = $calculator->calculateForUser($user_id, null, null);

?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پروفایل کاربری</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container my-5">
        <?php if(file_exists('nav.php')) { require_once 'nav.php'; } ?>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">خلاصه گزارش عملکرد</h5>
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

        <?php
        // Display success/error messages
        if (isset($_GET['success']) && $_GET['success'] == 'password_changed') {
            echo '<div class="alert alert-success">رمز عبور شما با موفقیت تغییر کرد.</div>';
        }
        if (isset($_SESSION['form_errors'])) {
            foreach ($_SESSION['form_errors'] as $error) {
                echo '<div class="alert alert-danger">' . htmlspecialchars($error) . '</div>';
            }
            unset($_SESSION['form_errors']);
        }
        ?>

        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">پروفایل کاربری</h4>
            </div>
            <div class="card-body">
                <p><strong>نام کامل:</strong> <?php echo htmlspecialchars($full_name); ?></p>
                <p><strong>نام کاربری:</strong> <?php echo htmlspecialchars($username); ?></p>
                <p><strong>نقش:</strong> <?php echo htmlspecialchars($role); ?></p>
            </div>
        </div>

        <div class="card mt-5">
            <div class="card-header">
                <h5 class="mb-0">تغییر رمز عبور</h5>
            </div>
            <div class="card-body">
                <form action="change_password.php" method="post">
                    <div class="mb-3">
                        <label for="current_password" class="form-label">رمز عبور فعلی</label>
                        <input type="password" name="current_password" id="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="new_password" class="form-label">رمز عبور جدید</label>
                        <input type="password" name="new_password" id="new_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_new_password" class="form-label">تکرار رمز عبور جدید</label>
                        <input type="password" name="confirm_new_password" id="confirm_new_password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-warning">تغییر رمز</button>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
