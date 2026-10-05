-- Separate database for the test suite, so RefreshDatabase never touches
-- development data. The application user gets rights on both and nothing else.
CREATE DATABASE IF NOT EXISTS alumni_connect_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON alumni_connect_testing.* TO 'alumni'@'%';
FLUSH PRIVILEGES;
