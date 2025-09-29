<?php
require_once 'config.php';
require_once 'JalaliDate.php';

// Authentication and Authorization
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}
if ($_SESSION["role"] !== 'admin') {
    header("location: index.php");
    exit;
}

// Fetch all users from the database
$users = [];
try {
    $sql = "SELECT id, username, full_name, role, created_at FROM users ORDER BY created_at DESC";
    $stmt = $pdo->query($sql);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("ERROR: Could not able to execute $sql. " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل مدیریت</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container my-5">
        <?php if(file_exists('nav.php')) { require_once 'nav.php'; } ?>

        <?php
        // Display success/error messages
        if (isset($_GET['success']) && $_GET['success'] == 'user_created') {
            echo '<div class="alert alert-success">کاربر جدید با موفقیت ایجاد شد.</div>';
        }
        if (isset($_GET['error'])) {
            echo '<div class="alert alert-danger">خطایی در ایجاد کاربر رخ داد.</div>';
        }
        if (isset($_SESSION['form_errors'])) {
            foreach ($_SESSION['form_errors'] as $error) {
                echo '<div class="alert alert-danger">' . htmlspecialchars($error) . '</div>';
            }
            unset($_SESSION['form_errors']);
        }
        ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">مدیریت کاربران</h4>
                <div>
                    <a href="reports.php" class="btn btn-secondary">مشاهده گزارشات کلی</a>
                    <a href="add_user.php" class="btn btn-primary">افزودن کاربر جدید</a>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>نام کاربری</th>
                                <th>نام کامل</th>
                                <th>نقش</th>
                                <th>تاریخ عضویت</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">هیچ کاربری یافت نشد.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                        <td><?php echo ($user['role'] === 'admin') ? 'مدیر' : 'کارمند'; ?></td>
                                        <td><?php echo JalaliDate::toJalali($user['created_at']); ?></td>
                                        <td>
                                            <a href="my_reports.php?user_id=<?php echo $user['id']; ?>" class="btn btn-success btn-sm">گزارش</a>
                                            <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-info btn-sm">ویرایش</a>
                                            <?php if ($_SESSION['id'] != $user['id']): ?>
                                            <button type="button" class="btn btn-danger btn-sm btn-delete-user" data-userid="<?php echo $user['id']; ?>" data-username="<?php echo htmlspecialchars($user['username']); ?>">حذف</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="main.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const deleteButtons = document.querySelectorAll('.btn-delete-user');
        deleteButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const userId = this.dataset.userid;
                const username = this.dataset.username;
                if (confirm(`آیا از حذف کاربر '${username}' مطمئن هستید؟ این عمل غیرقابل بازگشت است.`)) {
                    // Create a form dynamically and submit it via POST
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'delete_user.php';

                    const hiddenField = document.createElement('input');
                    hiddenField.type = 'hidden';
                    hiddenField.name = 'user_id';
                    hiddenField.value = userId;

                    form.appendChild(hiddenField);
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        });
    });
    </script>
</body>
</html>
