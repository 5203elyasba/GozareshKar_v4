<?php
require_once 'config.php';

// Role check and authentication
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["role"]) || $_SESSION["role"] !== 'admin'){
    header("location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize inputs
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $full_name = trim($_POST['full_name']);
    $role = $_POST['role'];
    $daily_hours_goal = filter_input(INPUT_POST, 'daily_hours_goal', FILTER_VALIDATE_FLOAT);
    $annual_leave_days = filter_input(INPUT_POST, 'annual_leave_days', FILTER_VALIDATE_INT);

    // Validation
    $errors = [];
    if (empty($username) || empty($password) || empty($full_name)) {
        $errors[] = "نام کاربری، رمز عبور و نام کامل الزامی هستند.";
    }
    if ($role !== 'employee' && $role !== 'admin') {
        $errors[] = "نقش انتخاب شده معتبر نیست.";
    }
    if ($daily_hours_goal === false || $annual_leave_days === false) {
        $errors[] = "ساعات کاری و مرخصی باید عدد باشند.";
    }

    // Check if username already exists
    $sql_check = "SELECT id FROM users WHERE username = :username";
    if($stmt_check = $pdo->prepare($sql_check)){
        $stmt_check->execute([':username' => $username]);
        if($stmt_check->rowCount() > 0){
            $errors[] = "این نام کاربری قبلا ثبت شده است. لطفا نام دیگری انتخاب کنید.";
        }
    }
    unset($stmt_check);

    if (!empty($errors)) {
        $_SESSION['form_errors'] = $errors;
        // Optional: Store submitted values in session to repopulate form
        $_SESSION['form_inputs'] = $_POST;
        header("location: add_user.php");
        exit;
    }

    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert new user into the database
    try {
        $sql_insert = "INSERT INTO users (username, password, full_name, role, daily_hours_goal, annual_leave_days) VALUES (:username, :password, :full_name, :role, :daily_hours_goal, :annual_leave_days)";
        $stmt_insert = $pdo->prepare($sql_insert);

        $stmt_insert->execute([
            ':username' => $username,
            ':password' => $hashed_password,
            ':full_name' => $full_name,
            ':role' => $role,
            ':daily_hours_goal' => $daily_hours_goal,
            ':annual_leave_days' => $annual_leave_days
        ]);

        header("Location: admin.php?success=user_created");
        exit;

    } catch (PDOException $e) {
        $_SESSION['form_errors'] = ["خطای پایگاه داده: " . $e->getMessage()];
        header("location: add_user.php");
        exit;
    }

} else {
    header("Location: admin.php");
    exit;
}
?>