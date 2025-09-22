<?php
require_once 'config.php';

// Authentication and Authorization
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'admin') {
    header("location: login.php");
    exit;
}

$user_id_to_delete = $_GET['id'] ?? null;

if (!$user_id_to_delete) {
    header("location: admin.php?error=no_id");
    exit;
}

// Prevent admin from deleting themselves
if ($user_id_to_delete == $_SESSION['id']) {
    header("location: admin.php?error=self_delete");
    exit;
}

try {
    $sql = "DELETE FROM users WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $user_id_to_delete]);

    header("location: admin.php?success=user_deleted");
    exit;

} catch (PDOException $e) {
    // In a real app, you'd log this error
    header("location: admin.php?error=delete_failed");
    exit;
}
?>
