<?php
require_once 'config.php';

// Authentication and Authorization
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'admin') {
    header("location: login.php");
    exit;
}

$user_id = $_GET['id'] ?? null;
if (!$user_id) {
    header("location: admin.php");
    exit;
}

$user = null;
$full_name = '';
$role = '';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = trim($_POST['full_name']);
    $role = $_POST['role'];

    if (empty($full_name) || ($role !== 'admin' && $role !== 'employee')) {
        $error = "لطفا تمام فیلدها را به درستی پر کنید.";
    } else {
        try {
            $sql = "UPDATE users SET full_name = :full_name, role = :role WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['full_name' => $full_name, 'role' => $role, 'id' => $user_id]);
            header("location: admin.php?success=user_updated");
            exit;
        } catch (PDOException $e) {
            $error = "خطای پایگاه داده: " . $e->getMessage();
        }
    }
}

// Fetch user data for the form
try {
    $sql = "SELECT full_name, role FROM users WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $full_name = $user['full_name'];
        $role = $user['role'];
    } else {
        header("location: admin.php?error=not_found");
        exit;
    }
} catch (PDOException $e) {
    die("ERROR: Could not fetch user data. " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ویرایش کاربر</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container my-5">
        <?php if(file_exists('nav.php')) { require_once 'nav.php'; } ?>

        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">ویرایش کاربر</h4>
            </div>
            <div class="card-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <form action="edit_user.php?id=<?php echo $user_id; ?>" method="post">
                    <div class="mb-3">
                        <label for="full_name" class="form-label">نام کامل</label>
                        <input type="text" class="form-control" name="full_name" id="full_name" value="<?php echo htmlspecialchars($full_name); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="role" class="form-label">نقش</label>
                        <select class="form-select" name="role" id="role" required>
                            <option value="employee" <?php if($role === 'employee') echo 'selected'; ?>>کارمند</option>
                            <option value="admin" <?php if($role === 'admin') echo 'selected'; ?>>مدیر</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-success">ذخیره تغییرات</button>
                    <a href="admin.php" class="btn btn-secondary">انصراف</a>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
