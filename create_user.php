<?php
require_once 'config.php';

// Authentication and Authorization
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'admin') {
    header("location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate input
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $full_name = trim($_POST['full_name']);
    $role = $_POST['role'];

    $errors = [];
    if (empty($username)) {
        $errors[] = "نام کاربری الزامی است.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = "نام کاربری فقط می‌تواند شامل حروف انگلیسی، اعداد و آندرلاین باشد.";
    }

    if (empty($password)) {
        $errors[] = "رمز عبور الزامی است.";
    }
    if (empty($full_name)) {
        $errors[] = "نام کامل الزامی است.";
    }
    if ($role !== 'admin' && $role !== 'employee') {
        $errors[] = "نقش نامعتبر است.";
    }

    // Check if username already exists
    if (empty($errors)) {
        $sql = "SELECT id FROM users WHERE username = :username";
        if ($stmt = $pdo->prepare($sql)) {
            $stmt->bindParam(":username", $username, PDO::PARAM_STR);
            if ($stmt->execute()) {
                if ($stmt->rowCount() == 1) {
                    $errors[] = "این نام کاربری قبلا ثبت شده است.";
                }
            } else {
                $errors[] = "خطایی رخ داد. لطفا دوباره تلاش کنید.";
            }
            unset($stmt);
        }
    }

    // If no errors, insert into database
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (username, password, full_name, role) VALUES (:username, :password, :full_name, :role)";

        if ($stmt = $pdo->prepare($sql)) {
            $stmt->bindParam(":username", $username, PDO::PARAM_STR);
            $stmt->bindParam(":password", $hashed_password, PDO::PARAM_STR);
            $stmt->bindParam(":full_name", $full_name, PDO::PARAM_STR);
            $stmt->bindParam(":role", $role, PDO::PARAM_STR);

            if ($stmt->execute()) {
                header("location: admin.php?success=user_created");
                exit();
            } else {
                header("location: admin.php?error=db_error");
                exit();
            }
            unset($stmt);
        }
    }

    // If there were errors, redirect back with errors
    if (!empty($errors)) {
        $_SESSION['form_errors'] = $errors;
        header("location: admin.php#add-user-form");
        exit();
    }

    unset($pdo);
} else {
    header("location: admin.php");
    exit;
}
?>
