<?php
require_once 'config.php';

// Role check and authentication
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["role"]) || $_SESSION["role"] !== 'admin'){
    die("Access Denied.");
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>افزودن کاربر جدید</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container mt-5">
        <h2 class="mb-4">افزودن کاربر جدید</h2>
        <div class="card">
            <div class="card-body">
                <form action="handle_add_user.php" method="post">
                    <div class="mb-3">
                        <label for="username" class="form-label">نام کاربری</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">رمز عبور</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label for="full_name" class="form-label">نام کامل</label>
                        <input type="text" class="form-control" id="full_name" name="full_name">
                    </div>
                    <div class="mb-3">
                        <label for="role" class="form-label">نقش</label>
                        <select class="form-select" id="role" name="role">
                            <option value="employee">کارمند (Employee)</option>
                            <option value="admin">ادمین (Admin)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="daily_hours_goal" class="form-label">ساعات کاری موظفی (روزانه)</label>
                        <input type="number" step="0.1" class="form-control" id="daily_hours_goal" name="daily_hours_goal" value="8" required>
                    </div>
                    <div class="mb-3">
                        <label for="annual_leave_days" class="form-label">مرخصی سالانه (روز)</label>
                        <input type="number" class="form-control" id="annual_leave_days" name="annual_leave_days" value="26" required>
                    </div>
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                         <a href="admin.php" class="btn btn-secondary">انصراف</a>
                        <button type="submit" class="btn btn-primary">افزودن کاربر</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
