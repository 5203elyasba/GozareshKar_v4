<?php
require_once 'config.php';

// --- Authentication ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// --- Handle Password Change Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['current_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_new_password = $_POST['confirm_new_password'];
    $user_id = $_SESSION['id'];
    $errors = [];

    // Validation
    if (empty($current_password) || empty($new_password) || empty($confirm_new_password)) {
        $errors[] = "تمام فیلدهای رمز عبور الزامی هستند.";
    }
    if ($new_password !== $confirm_new_password) {
        $errors[] = "رمز عبور جدید و تکرار آن یکسان نیستند.";
    }
    if (strlen($new_password) < 6) {
        $errors[] = "رمز عبور جدید باید حداقل 6 کاراکتر باشد.";
    }

    if (empty($errors)) {
        try {
            // Verify current password
            $sql = "SELECT password FROM users WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['id' => $user_id]);
            $user = $stmt->fetch();

            if ($user && password_verify($current_password, $user['password'])) {
                // Current password is correct, update to new password
                $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_sql = "UPDATE users SET password = :password WHERE id = :id";
                $update_stmt = $pdo->prepare($update_sql);
                $update_stmt->execute(['password' => $hashed_new_password, 'id' => $user_id]);

                header("location: profile.php?success=password_changed");
                exit();
            } else {
                $errors[] = "رمز عبور فعلی شما اشتباه است.";
            }
        } catch (PDOException $e) {
            $errors[] = "خطای پایگاه داده: " . $e->getMessage();
        }
    }

    // If there were errors, redirect back with them in the session
    if (!empty($errors)) {
        $_SESSION['form_errors'] = $errors;
        header("location: profile.php");
        exit();
    }
}

// --- Fetch User Data for Display ---
$user_id = $_SESSION["id"];
$username = $_SESSION["username"];
// Fetch full_name and role directly from DB to ensure it's up-to-date
try {
    $stmt = $pdo->prepare("SELECT full_name, role FROM users WHERE id = :id");
    $stmt->execute(['id' => $user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $full_name = $user_data['full_name'] ?? 'کاربر';
    $role = $user_data['role'] ?? 'employee';
} catch(PDOException $e) {
    $full_name = 'کاربر';
    $role = 'employee';
}

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
                <p><strong>نقش:</strong> <?php echo ($role === 'admin') ? 'مدیر' : 'کارمند'; ?></p>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">تغییر رمز عبور</h5>
            </div>
            <div class="card-body">
                <form action="profile.php" method="post">
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