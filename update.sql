-- =================================================================
-- اسکریپت به‌روزرسانی دیتابیس
-- =================================================================
-- این اسکریپت تمام تغییرات لازم برای رسیدن به آخرین نسخه دیتابیس را شامل می‌شود.
-- نکته: اگر هنگام اجرای دستوری با خطای "Duplicate column" یا "Table already exists"
-- مواجه شدید، یعنی آن تغییر قبلاً اعمال شده است و می‌توانید خطا را نادیده بگیرید.
-- =================================================================


-- اضافه کردن ستون برای ساعات کاری موظفی و مرخصی سالانه به جدول کاربران
ALTER TABLE `users`
ADD COLUMN `daily_hours_goal` float NOT NULL DEFAULT 8 AFTER `role`,
ADD COLUMN `annual_leave_days` INT(11) NOT NULL DEFAULT 26 AFTER `daily_hours_goal`;


-- اضافه کردن ستون برای تفکیک بین زمان کاری و استراحت به جدول لاگ‌های زمانی
ALTER TABLE `time_logs`
ADD COLUMN `log_type` ENUM('work','break') NOT NULL DEFAULT 'work' AFTER `end_time`;


-- ایجاد جدول جدید برای ثبت تاریخ مرخصی‌های استفاده شده
CREATE TABLE IF NOT EXISTS `leave_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `leave_date` date NOT NULL,
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_persian_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id_leave_date` (`user_id`,`leave_date`),
  CONSTRAINT `leave_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- =================================================================
-- افزودن ستون‌های تاریخ شمسی برای گزارش‌گیری بهینه
-- =================================================================
ALTER TABLE `time_logs`
ADD COLUMN `jalali_year` INT(4) NULL AFTER `log_type`,
ADD COLUMN `jalali_month` INT(2) NULL AFTER `jalali_year`,
ADD COLUMN `jalali_day` INT(2) NULL AFTER `jalali_month`,
ADD INDEX `idx_jalali_date` (`jalali_year`, `jalali_month`);
