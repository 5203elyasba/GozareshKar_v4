<?php
require_once 'config.php';
require_once 'JalaliDate.php';

// Authentication and Authorization
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}
if ($_SESSION["role"] !== 'admin') {
    header("location: index.php");
    exit;
}

// Fetch all users from the database
$users = [];
try {
    $sql = "SELECT id, username, full_name, role, created_at FROM users ORDER BY created_at DESC";
    $stmt = $pdo->query($sql);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("ERROR: Could not able to execute $sql. " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل مدیریت</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container my-5">
        <?php if(file_exists('nav.php')) { require_once 'nav.php'; } ?>

        <?php
        // Display success/error messages
        if (isset($_GET['success']) && $_GET['success'] == 'user_created') {
            echo '<div class="alert alert-success">کاربر جدید با موفقیت ایجاد شد.</div>';
        }
        if (isset($_GET['error'])) {
            echo '<div class="alert alert-danger">خطایی در ایجاد کاربر رخ داد.</div>';
        }
        if (isset($_SESSION['form_errors'])) {
            foreach ($_SESSION['form_errors'] as $error) {
                echo '<div class="alert alert-danger">' . htmlspecialchars($error) . '</div>';
            }
            unset($_SESSION['form_errors']);
        }
        ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">مدیریت کاربران</h4>
                <div>
                    <a href="holiday_requests.php" class="btn btn-warning">درخواست‌های تعطیلات</a>
                    <a href="reports.php" class="btn btn-secondary">مشاهده گزارشات کلی</a>
                    <a href="#add-user-form" class="btn btn-primary">افزودن کاربر جدید</a>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>نام کاربری</th>
                                <th>نام کامل</th>
                                <th>نقش</th>
                                <th>تاریخ عضویت</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">هیچ کاربری یافت نشد.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                        <td><?php echo ($user['role'] === 'admin') ? 'مدیر' : 'کارمند'; ?></td>
                                        <td><?php echo JalaliDate::toJalali($user['created_at']); ?></td>
                                        <td>
                                            <a href="my_reports.php?user_id=<?php echo $user['id']; ?>" class="btn btn-success btn-sm">گزارش</a>
                                            <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-info btn-sm">ویرایش</a>
                                            <?php if ($_SESSION['id'] != $user['id']): ?>
                                            <button type="button" class="btn btn-danger btn-sm btn-delete-user" data-userid="<?php echo $user['id']; ?>" data-username="<?php echo htmlspecialchars($user['username']); ?>">حذف</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mt-5" id="add-user-form">
            <div class="card-header">
                <h5 class="mb-0">افزودن کاربر جدید</h5>
            </div>
            <div class="card-body">
                <form action="create_user.php" method="post">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="full_name" class="form-label">نام کامل</label>
                            <input type="text" class="form-control" name="full_name" id="full_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">نام کاربری (انگلیسی)</label>
                            <input type="text" class="form-control" name="username" id="username" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">رمز عبور</label>
                            <input type="password" class="form-control" name="password" id="password" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="role" class="form-label">نقش</label>
                            <select class="form-select" name="role" id="role" required>
                                <option value="employee" selected>کارمند (Employee)</option>
                                <option value="admin">مدیر (Admin)</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="daily_hours_goal" class="form-label">ساعات کاری روزانه</label>
                            <input type="number" step="0.1" class="form-control" name="daily_hours_goal" id="daily_hours_goal" value="8" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="annual_leave_days" class="form-label">مرخصی سالانه (روز)</label>
                            <input type="number" class="form-control" name="annual_leave_days" id="annual_leave_days" value="26" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success">ذخیره کاربر</button>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="main.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const deleteButtons = document.querySelectorAll('.btn-delete-user');
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const userId = this.dataset.userid;
                const username = this.dataset.username;
                if (confirm(`آیا از حذف کاربر '${username}' مطمئن هستید؟ این عمل غیرقابل بازگشت است.`)) {
                    window.location.href = 'delete_user.php?id=' + userId;
                }
            });
        });
    });
    </script>
</body>
</html>
