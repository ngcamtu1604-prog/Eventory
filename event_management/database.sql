-- =====================================================
-- EVENTORY - CƠ SỞ DỮ LIỆU QUẢN LÝ SỰ KIỆN
-- Một file dùng cho cả:
--   1. Cài đặt database mới.
--   2. Cập nhật database EVENTORY đã tồn tại.
-- File duy nhất: đã gộp cấu trúc database chính, ảnh sự kiện và các module Timeline/Kanban, Ngân sách, Tài nguyên.
-- Có thể dùng để cài database mới hoặc cập nhật database đang sử dụng.
-- File không xóa bảng hoặc dữ liệu hiện có.
-- LƯU Ý: nếu bảng events đã có từ trước, hãy Import file này vào phpMyAdmin
-- với database quanly_sukien đang được chọn để bổ sung cột created_by.
-- =====================================================

CREATE DATABASE IF NOT EXISTS quanly_sukien
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE quanly_sukien;

-- 1. Người dùng
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','participant') NOT NULL DEFAULT 'participant',
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Diễn giả
CREATE TABLE IF NOT EXISTS speakers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    info TEXT
) ENGINE=InnoDB;

-- 3. Ban tổ chức
CREATE TABLE IF NOT EXISTS organizers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    info TEXT
) ENGINE=InnoDB;

-- 3A. Tạo bảng events theo cấu trúc mới.
-- Nếu bảng events đã tồn tại thì CREATE TABLE IF NOT EXISTS sẽ giữ nguyên dữ liệu.
CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    event_date DATETIME NOT NULL,
    location VARCHAR(255) NOT NULL,
    capacity INT NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'Sắp diễn ra',
    speaker_id INT NULL,
    organizer_id INT NULL,
    created_by INT NULL,
    image_path VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_events_date (event_date),
    INDEX idx_events_created_by (created_by),
    FOREIGN KEY (speaker_id) REFERENCES speakers(id) ON DELETE SET NULL,
    FOREIGN KEY (organizer_id) REFERENCES organizers(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 3B. Tự bổ sung created_by nếu đang cập nhật database phiên bản cũ.
SET @has_created_by = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'events'
      AND COLUMN_NAME = 'created_by'
);

SET @sql = IF(
    @has_created_by = 0,
    'ALTER TABLE events ADD COLUMN created_by INT NULL AFTER organizer_id',
    'SELECT 1'
);
PREPARE eventory_stmt FROM @sql;
EXECUTE eventory_stmt;
DEALLOCATE PREPARE eventory_stmt;

-- 3B-1. Bổ sung đường dẫn ảnh minh họa nếu database cũ chưa có cột.
SET @has_event_image = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'events'
      AND COLUMN_NAME = 'image_path'
);

SET @sql = IF(
    @has_event_image = 0,
    'ALTER TABLE events ADD COLUMN image_path VARCHAR(255) NULL AFTER created_by',
    'SELECT 1'
);
PREPARE eventory_stmt FROM @sql;
EXECUTE eventory_stmt;
DEALLOCATE PREPARE eventory_stmt;

-- 3C. Bổ sung index nếu cột created_by chưa có index.
SET @has_created_by_index = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'events'
      AND COLUMN_NAME = 'created_by'
);

SET @sql = IF(
    @has_created_by_index = 0,
    'ALTER TABLE events ADD INDEX idx_events_created_by (created_by)',
    'SELECT 1'
);
PREPARE eventory_stmt FROM @sql;
EXECUTE eventory_stmt;
DEALLOCATE PREPARE eventory_stmt;

-- 3D. Bổ sung khóa ngoại nếu created_by chưa tham chiếu users(id).
SET @has_created_by_fk = (
    SELECT COUNT(*)
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'events'
      AND COLUMN_NAME = 'created_by'
      AND REFERENCED_TABLE_NAME = 'users'
      AND REFERENCED_COLUMN_NAME = 'id'
);

SET @sql = IF(
    @has_created_by_fk = 0,
    'ALTER TABLE events ADD CONSTRAINT fk_events_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE eventory_stmt FROM @sql;
EXECUTE eventory_stmt;
DEALLOCATE PREPARE eventory_stmt;

-- 3E. Gán các sự kiện cũ chưa có chủ sở hữu cho admin đầu tiên (nếu đã có admin).
-- Sự kiện hiện có và thông tin đăng ký không bị xóa.
UPDATE events
SET created_by = (
    SELECT MIN(id)
    FROM users
    WHERE role = 'admin'
)
WHERE created_by IS NULL
  AND EXISTS (SELECT 1 FROM users WHERE role = 'admin');

-- 4. Đăng ký tham gia
CREATE TABLE IF NOT EXISTS registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    attendance_code VARCHAR(20) NOT NULL UNIQUE,
    attended TINYINT(1) NOT NULL DEFAULT 0,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    checked_in_at DATETIME NULL,
    UNIQUE KEY unique_registration (event_id, user_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 5. Khảo sát đánh giá
CREATE TABLE IF NOT EXISTS surveys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    rating TINYINT NOT NULL,
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_survey (event_id, user_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 5A. Công việc Timeline & Kanban cho từng sự kiện.
CREATE TABLE IF NOT EXISTS event_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    status ENUM('todo','doing','done') NOT NULL DEFAULT 'todo',
    priority ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
    due_date DATE NULL,
    assignee VARCHAR(120) NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_event_tasks_event_status (event_id, status),
    CONSTRAINT fk_event_tasks_event FOREIGN KEY (event_id)
        REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT fk_event_tasks_user FOREIGN KEY (created_by)
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 5B. Dự toán và chi phí thực tế của sự kiện.
CREATE TABLE IF NOT EXISTS event_budget_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    item_name VARCHAR(180) NOT NULL,
    category VARCHAR(100) NOT NULL DEFAULT 'Khác',
    quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_status ENUM('planned','paid') NOT NULL DEFAULT 'planned',
    note TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_event_budget_event (event_id),
    CONSTRAINT fk_event_budget_event FOREIGN KEY (event_id)
        REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT fk_event_budget_user FOREIGN KEY (created_by)
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 5C. Danh sách tài nguyên, thiết bị và vật tư cho sự kiện.
CREATE TABLE IF NOT EXISTS event_resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    resource_name VARCHAR(180) NOT NULL,
    category VARCHAR(100) NOT NULL DEFAULT 'Thiết bị',
    quantity INT NOT NULL DEFAULT 1,
    unit VARCHAR(40) NOT NULL DEFAULT 'cái',
    resource_status ENUM('planned','ready','in_use','returned') NOT NULL DEFAULT 'planned',
    responsible VARCHAR(120) NULL,
    note TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_event_resources_event (event_id),
    CONSTRAINT fk_event_resources_event FOREIGN KEY (event_id)
        REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT fk_event_resources_user FOREIGN KEY (created_by)
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 6. Tài khoản quản trị ban đầu (chỉ thêm nếu email chưa tồn tại).
-- Mật khẩu ban đầu: admin123. Đăng nhập và đổi mật khẩu ngay sau khi cài đặt.
INSERT INTO users (full_name, email, password, role)
SELECT 'Quản trị viên', 'admin@eventhub.vn',
       '$2y$12$W4xt5EaWJsRjTsrszPtYOOi5igba9y0kmQD0xQz.fcP2QRgMguTnK',
       'admin'
WHERE NOT EXISTS (
    SELECT 1 FROM users WHERE email = 'admin@eventhub.vn'
);

-- 7. Dữ liệu mẫu tối thiểu (chỉ thêm nếu bảng đang trống).
INSERT INTO speakers (name, info)
SELECT 'TS. Nguyễn Minh Anh', 'Diễn giả lĩnh vực công nghệ và chuyển đổi số'
WHERE NOT EXISTS (SELECT 1 FROM speakers);

INSERT INTO organizers (name, info)
SELECT 'Đoàn Thanh niên', 'Đơn vị tổ chức chương trình'
WHERE NOT EXISTS (SELECT 1 FROM organizers);


-- =====================================================
-- HOÀN TẤT
-- Các bảng event_tasks, event_budget_items và event_resources
-- đã được tạo ở phần 5A, 5B và 5C phía trên.
-- Không cần import database_update_modules.sql riêng nữa.
-- =====================================================
