<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4" style="background-color: #002e36 !important;">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ? 'admin.php' : 'index.php'; ?>">ثبت گزارش روزانه</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">ثبت گزارش</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="my_reports.php">گزارشات من</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="leave.php">مرخصی</a>
                </li>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link" href="admin.php">پنل مدیریت</a>
                </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <?php echo "پروفایل: " . htmlspecialchars($_SESSION["username"]); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                        <li><a class="dropdown-item" href="profile.php">مشاهده پروفایل</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php">خروج</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
