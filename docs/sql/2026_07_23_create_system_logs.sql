-- Manual SQL for the system_logs table (equivalent to Laravel migration
-- 2026_07_23_150455_create_system_logs_table.php).
--
-- NOTE: if you deploy the updated app image and let docker/entrypoint.sh run
-- `php artisan migrate --force`, this table gets created automatically —
-- you do NOT need to run this file too. Only run this if you want to apply
-- the schema change manually ahead of / instead of a redeploy.

CREATE TABLE `system_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` varchar(20) DEFAULT NULL,
  `action` varchar(20) NOT NULL,
  `loggable_type` varchar(100) NOT NULL,
  `loggable_id` varchar(30) NOT NULL,
  `description` varchar(255) NOT NULL,
  `properties` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`properties`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `system_logs_user_id_foreign` (`user_id`),
  KEY `system_logs_loggable_type_loggable_id_index` (`loggable_type`,`loggable_id`),
  CONSTRAINT `system_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mark this migration as already applied, so a later
-- `php artisan migrate --force` (which entrypoint.sh runs on every
-- container start) doesn't try to create the table again and fail.
INSERT INTO `migrations` (`migration`, `batch`)
VALUES ('2026_07_23_150455_create_system_logs_table', (SELECT COALESCE(MAX(batch), 0) + 1 FROM (SELECT batch FROM migrations) AS m));
