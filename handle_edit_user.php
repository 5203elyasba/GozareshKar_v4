<?php
require_once 'config.php';

// Authentication and Authorization
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'admin') {
    header("location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_POST['user_id'] ?? null;
    if (!$user_id) {
        header("location: admin.php");
        exit;
    }

    // Fetch original user data for comparison
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = :id");
    $stmt->execute(['id' => $user_id]);
    $original_user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$original_user) {
        header("location: admin.php?error=not_found");
        exit;
    }

    // Sanitize and validate inputs
    $full_name = trim($_POST['full_name']);
    $username = trim($_POST['username']);
    $role = $_POST['role'];
    $daily_hours_goal = filter_input(INPUT_POST, 'daily_hours_goal', FILTER_VALIDATE_FLOAT);
    $annual_leave_days = filter_input(INPUT_POST, 'annual_leave_days', FILTER_VALIDATE_INT);

    $errors = [];
    if (empty($full_name) || empty($username) || empty($role)) {
        $errors[] = "تمام فیلدهای ستاره‌دار الزامی هستند.";
    }
    if ($daily_hours_goal === false || $annual_leave_days === false) {
        $errors[] = "ساعات کاری و مرخصی باید عدد باشند.";
    }

    // Check for username uniqueness only if it has changed
    if ($username !== $original_user['username']) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "این نام کاربری قبلا ثبت شده است.";
        }
    }

    if (!empty($errors)) {
        $_SESSION['form_errors'] = $errors;
        header("location: edit_user.php?id=" . $user_id);
        exit;
    }

    // If validation passes, update the database
    try {
        $sql = "UPDATE users SET username = :username, full_name = :full_name, role = :role, daily_hours_goal = :daily_hours_goal, annual_leave_days = :annual_leave_days WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'username' => $username,
            'full_name' => $full_name,
            'role' => $role,
            'daily_hours_goal' => $daily_hours_goal,
            'annual_leave_days' => $annual_leave_days,
            'id' => $user_id
        ]);
        header("location: admin.php?success=user_updated");
        exit;
    } catch (PDOException $e) {
        $_SESSION['form_errors'] = ["خطای پایگاه داده: " . $e->getMessage()];
        header("location: edit_user.php?id=" . $user_id);
        exit;
    }
} else {
    // Redirect if accessed directly without POST method
    header("location: admin.php");
    exit;
}
?>