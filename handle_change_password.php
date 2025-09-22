<?php
require_once 'config.php';

// Redirect to login if not authenticated
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $user_id = $_SESSION['id'];

    // --- Validation ---
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        header("Location: change_password.php?error=All fields are required.");
        exit;
    }

    if ($new_password !== $confirm_password) {
        header("Location: change_password.php?error=New passwords do not match.");
        exit;
    }

    // --- Process Password Change ---
    try {
        // 1. Get current hashed password from DB
        $sql_fetch = "SELECT password FROM users WHERE id = :id";
        $stmt_fetch = $pdo->prepare($sql_fetch);
        $stmt_fetch->execute([':id' => $user_id]);
        $user = $stmt_fetch->fetch();

        if (!$user) {
            die("User not found.");
        }

        // 2. Verify current password
        if (password_verify($current_password, $user['password'])) {
            // 3. Hash new password
            $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            // 4. Update password in DB
            $sql_update = "UPDATE users SET password = :password WHERE id = :id";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([':password' => $new_hashed_password, ':id' => $user_id]);

            header("Location: change_password.php?success=1");
            exit;

        } else {
            header("Location: change_password.php?error=Incorrect current password.");
            exit;
        }

    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage());
    }

} else {
    header("Location: change_password.php");
    exit;
}
?>
