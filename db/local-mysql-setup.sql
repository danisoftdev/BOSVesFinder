-- =============================================================================
-- BOSVesFinder — create MySQL database (local dev: root, no password)
-- =============================================================================
--
-- Run:
--   CMD:   mysql -u root < db/local-mysql-setup.sql
--   PS:    Get-Content .\db\local-mysql-setup.sql -Raw | mysql -u root
--   Or:    .\scripts\apply-local-mysql-setup.ps1
--
-- Then set in .env:
--   DB_NAME=vesfinder
--   DB_USER=root
--   DB_PASSWORD=
--   DB_HOST=127.0.0.1
--
-- Apply schema: php bin/seed.php
-- =============================================================================

CREATE DATABASE IF NOT EXISTS `vesfinder`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

-- Optional dedicated user:
-- CREATE USER IF NOT EXISTS 'vesfinder'@'localhost' IDENTIFIED BY 'your_password_here';
-- GRANT ALL PRIVILEGES ON `vesfinder`.* TO 'vesfinder'@'localhost';
-- FLUSH PRIVILEGES;
