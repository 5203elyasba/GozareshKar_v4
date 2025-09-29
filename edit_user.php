<?php
require_once 'config.php';

// Authentication and Authorization
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'admin') {
    header("location: login.php");
    exit;
}

$user_id = $_GET['id'] ?? null;
if (!$user_id) {
    header("location: admin.php");
    exit;
}

// Fetch user data first to use in validation
try {
    $sql = "SELECT username, full_name, role, daily_hours_goal, annual_leave_days FROM users WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $username = $user['username'];
        $full_name = $user['full_name'];
        $role = $user['role'];
        $daily_hours_goal = $user['daily_hours_goal'];
        $annual_leave_days = $user['annual_leave_days'];
    } else {
        header("location: admin.php?error=not_found");
        exit;
    }
} catch (PDOException $e) {
    die("ERROR: Could not fetch user data. " . $e->getMessage());
}


?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ویرایش کاربر</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container my-5">
        <?php if(file_exists('nav.php')) { require_once 'nav.php'; } ?>

        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">ویرایش کاربر</h4>
            </div>
            <div class="card-body">
                <?php
                if (isset($_SESSION['form_errors'])) {
                    foreach ($_SESSION['form_errors'] as $error) {
                        echo '<div class="alert alert-danger">' . htmlspecialchars($error) . '</div>';
                    }
                    unset($_SESSION['form_errors']);
                }
                ?>
                <form action="handle_edit_user.php" method="post">
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="full_name" class="form-label">نام کامل</label>
                            <input type="text" class="form-control" name="full_name" id="full_name" value="<?php echo htmlspecialchars($full_name); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">نام کاربری</label>
                            <input type="text" class="form-control" name="username" id="username" value="<?php echo htmlspecialchars($username); ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="role" class="form-label">نقش</label>
                        <select class="form-select" name="role" id="role" required>
                            <option value="employee" <?php if($role === 'employee') echo 'selected'; ?>>کارمند</option>
                            <option value="admin" <?php if($role === 'admin') echo 'selected'; ?>>مدیر</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="daily_hours_goal" class="form-label">ساعات کاری روزانه</label>
                        <input type="number" step="0.1" class="form-control" name="daily_hours_goal" id="daily_hours_goal" value="<?php echo htmlspecialchars($daily_hours_goal); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="annual_leave_days" class="form-label">مرخصی سالانه (روز)</label>
                        <input type="number" class="form-control" name="annual_leave_days" id="annual_leave_days" value="<?php echo htmlspecialchars($annual_leave_days); ?>" required>
                    </div>
                    <button type="submit" class="btn btn-success">ذخیره تغییرات</button>
                    <a href="admin.php" class="btn btn-secondary">انصراف</a>
                </form>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">تغییر رمز عبور کاربر</h5>
            </div>
            <div class="card-body">
                <form action="handle_admin_change_password.php" method="post">
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                    <div class="mb-3">
                        <label for="new_password" class="form-label">رمز عبور جدید</label>
                        <input type="password" name="new_password" id="new_password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-warning">تغییر رمز عبور</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
