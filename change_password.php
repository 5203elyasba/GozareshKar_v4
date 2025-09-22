<?php
require_once 'config.php';

// Authentication
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_new_password = $_POST['confirm_new_password'];
    $user_id = $_SESSION['id'];
    $errors = [];

    // --- Validation ---
    if (empty($current_password) || empty($new_password) || empty($confirm_new_password)) {
        $errors[] = "تمام فیلدها الزامی هستند.";
    }
    if ($new_password !== $confirm_new_password) {
        $errors[] = "رمز عبور جدید و تکرار آن یکسان نیستند.";
    }
    if (strlen($new_password) < 6) {
        $errors[] = "رمز عبور جدید باید حداقل 6 کاراکتر باشد.";
    }

    if (empty($errors)) {
        try {
            // --- Verify current password ---
            $sql = "SELECT password FROM users WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['id' => $user_id]);
            $user = $stmt->fetch();

            if ($user && password_verify($current_password, $user['password'])) {
                // --- Current password is correct, update to new password ---
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

    // If there were errors, redirect back
    if (!empty($errors)) {
        $_SESSION['form_errors'] = $errors;
        header("location: profile.php");
        exit();
    }
} else {
    header("location: profile.php");
    exit;
}
?>
