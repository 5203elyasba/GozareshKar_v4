-- =================================================================
-- اسکریپت به‌روزرسانی دیتابیس
-- =================================================================
-- این اسکریپت ستون‌های تاریخ شمسی را به جدول `time_logs` اضافه می‌کند
-- تا مشکل دسته‌بندی گزارشات بر اساس ماه‌های شمسی حل شود.
-- =================================================================

ALTER TABLE `time_logs`
ADD COLUMN `jalali_year` INT(4) NULL DEFAULT NULL AFTER `log_type`,
ADD COLUMN `jalali_month` INT(2) NULL DEFAULT NULL AFTER `jalali_year`,
ADD COLUMN `jalali_day` INT(2) NULL DEFAULT NULL AFTER `jalali_month`,
ADD INDEX `idx_jalali_date` (`jalali_year`, `jalali_month`);
