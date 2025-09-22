<?php
// --- One-Time Migration Script ---
// This script should be run once from the command line or by accessing it in the browser.
// It populates the new jalali_year, jalali_month, and jalali_day columns for existing records in the `time_logs` table.

require_once 'config.php';
require_once 'JalaliDate.php';

echo "<!DOCTYPE html><html lang='fa' dir='rtl'><head><title>مهاجرت تاریخ‌های شمسی</title><link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css'></head><body class='container my-5'>";
echo "<h1>شروع فرآیند مهاجرت تاریخ‌های شمسی برای جدول `time_logs`...</h1>";

try {
    $pdo->beginTransaction();

    // Select all records that haven't been migrated yet
    $select_sql = "SELECT id, log_date FROM time_logs WHERE jalali_year IS NULL";
    $select_stmt = $pdo->query($select_sql);
    $logs_to_migrate = $select_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($logs_to_migrate)) {
        echo "<div class='alert alert-info'>هیچ رکوردی برای مهاجرت یافت نشد. به نظر می‌رسد تمام داده‌ها به‌روز هستند.</div>";
        $pdo->commit();
        echo "</body></html>";
        exit;
    }

    echo "<p>" . count($logs_to_migrate) . " رکورد برای به‌روزرسانی یافت شد. در حال پردازش...</p>";

    $update_sql = "UPDATE time_logs SET jalali_year = :year, jalali_month = :month, jalali_day = :day WHERE id = :id";
    $update_stmt = $pdo->prepare($update_sql);

    $updated_count = 0;
    foreach ($logs_to_migrate as $log) {
        $gregorian_date = $log['log_date'];
        if (!$gregorian_date) {
            continue; // Skip if date is null
        }
        $jalali_date_str = JalaliDate::toJalali($gregorian_date);

        list($j_year, $j_month, $j_day) = explode('/', $jalali_date_str);

        $update_stmt->execute([
            ':year' => (int)$j_year,
            ':month' => (int)$j_month,
            ':day' => (int)$j_day,
            ':id' => $log['id']
        ]);
        $updated_count++;
    }

    $pdo->commit();

    echo "<div class='alert alert-success'>عملیات با موفقیت انجام شد. " . $updated_count . " رکورد به‌روزرسانی شد.</div>";
    echo "<p class='text-danger'>لطفاً پس از اتمام کار، این فایل (`migrate_jalali_dates.php`) را از سرور خود حذف کنید تا از اجرای مجدد آن جلوگیری شود.</p>";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "<div class='alert alert-danger'>خطایی در هنگام مهاجرت رخ داد: " . $e->getMessage() . "</div>";
}

echo "</body></html>";
?>
