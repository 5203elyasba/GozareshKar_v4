<?php
require_once 'config.php';

// Authentication and Authorization
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'admin') {
    header("location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_POST['user_id'];
    $new_password = $_POST['new_password'];

    if (empty($user_id) || empty($new_password)) {
        header("location: edit_user.php?id={$user_id}&error=empty_password");
        exit;
    }

    try {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET password = :password WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'password' => $hashed_password,
            'id' => $user_id
        ]);

        // Redirect back to edit page with a success message
        header("location: edit_user.php?id={$user_id}&success=password_changed");
        exit;

    } catch (PDOException $e) {
        header("location: edit_user.php?id={$user_id}&error=db_error");
        exit;
    }
} else {
    header("location: admin.php");
    exit;
}
?>
