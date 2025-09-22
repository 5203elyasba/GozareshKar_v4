<?php
// Initialize the session
session_start();

// Check if the user is already logged in, if yes then redirect him to welcome page
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    header("location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به سیستم</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        body { display: flex; align-items: center; justify-content: center; height: 100vh; }
        .login-container { max-width: 400px; width: 100%; padding: 2rem; }
    </style>
</head>
<body>
    <div class="login-container">
        <h2 class="text-center mb-4">ورود به پنل</h2>
        <?php
        if(!empty($_GET['error'])){
            echo '<div class="alert alert-danger">نام کاربری یا رمز عبور اشتباه است.</div>';
        }
        ?>
        <form action="handle_login.php" method="post">
            <div class="mb-3">
                <label for="username" class="form-label">نام کاربری</label>
                <input type="text" name="username" id="username" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">رمز عبور</label>
                <input type="password" name="password" id="password" class="form-control" required>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary">ورود</button>
            </div>
        </form>
    </div>
</body>
</html>
