<?php
require_once 'config.php';

// Role check and authentication
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["role"]) || $_SESSION["role"] !== 'admin'){
    die("Access Denied.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize inputs
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $full_name = trim($_POST['full_name']);
    $role = $_POST['role'];
    $daily_hours_goal = (float)$_POST['daily_hours_goal'];
    $annual_leave_days = (int)$_POST['annual_leave_days'];

    // Validation
    if (empty($username) || empty($password)) { die("Username and password are required."); }
    if ($role !== 'employee' && $role !== 'admin') { die("Invalid role specified."); }
    if ($daily_hours_goal <= 0) { $daily_hours_goal = 8; }
    if ($annual_leave_days < 0) { $annual_leave_days = 26; }

    // Check if username already exists
    $sql_check = "SELECT id FROM users WHERE username = :username";
    if($stmt_check = $pdo->prepare($sql_check)){
        $stmt_check->execute([':username' => $username]);
        if($stmt_check->rowCount() > 0){
            die("This username is already taken. Please choose another one.");
        }
    }
    unset($stmt_check);

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

        header("Location: admin.php?success=1");
        exit;

    } catch (PDOException $e) {
        die("Error adding user: " . $e->getMessage());
    }

} else {
    header("Location: admin.php");
    exit;
}
?>
