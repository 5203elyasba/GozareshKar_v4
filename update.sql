-- =================================================================
-- اسکریپت جامع و نهایی به‌روزرسانی دیتابیس
-- =================================================================

-- بخش اول: افزودن ستون‌های تاریخ شمسی برای گزارش‌گیری بهینه
ALTER TABLE `time_logs`
ADD COLUMN `jalali_year` INT(4) NULL DEFAULT NULL AFTER `log_type`,
ADD COLUMN `jalali_month` INT(2) NULL DEFAULT NULL AFTER `jalali_year`,
ADD COLUMN `jalali_day` INT(2) NULL DEFAULT NULL AFTER `jalali_month`,
ADD INDEX `idx_jalali_date` (`jalali_year`, `jalali_month`);

-- بخش دوم: ایجاد جدول برای مشخصات روزهای خاص با قابلیت تایید ادمین
CREATE TABLE IF NOT EXISTS `day_properties` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `log_date` date NOT NULL,
  `day_type` enum('official_holiday','friday_work') NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id_log_date` (`user_id`,`log_date`),
  CONSTRAINT `day_properties_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- بخش سوم: اصلاح جدول لاگ‌های زمانی برای رفع باگ ساعت 00:00
ALTER TABLE `time_logs`
MODIFY COLUMN `end_time` time NULL DEFAULT NULL;
