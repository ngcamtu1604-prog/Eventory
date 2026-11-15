-- =====================================================
-- EVENTORY - CƠ SỞ DỮ LIỆU QUẢN LÝ SỰ KIỆN
-- Một file dùng cho cả:
--   1. Cài đặt database mới (bao gồm dữ liệu mẫu).
--   2. Cập nhật database EVENTORY đã tồn tại (bổ sung dữ liệu mẫu nếu chưa có).
-- File duy nhất: đã gộp cấu trúc database chính, ảnh sự kiện và các module Timeline/Kanban, Ngân sách, Tài nguyên.
-- Có thể dùng để cài database mới hoặc cập nhật database đang sử dụng.
-- File không xóa bảng hoặc dữ liệu hiện có; chỉ dọn hai sự kiện demo cũ được ghi rõ bên dưới.
-- LƯU Ý: nếu bảng events đã có từ trước, hãy Import file này vào phpMyAdmin
-- với database quanly_sukien đang được chọn để bổ sung cột created_by.
-- =====================================================

CREATE DATABASE IF NOT EXISTS quanly_sukien
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE quanly_sukien;

-- 1. Người dùng (vai trò cấp hệ thống: admin / user)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','user') NOT NULL DEFAULT 'user',
    status TINYINT(1) NOT NULL DEFAULT 1,
    avatar_path VARCHAR(255) NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Chuẩn hóa phân quyền hai cấp:
-- Cấp hệ thống chỉ lưu Admin/User trong users.role.
-- Organizer/Participant là vai trò theo từng sự kiện, xác định từ events.created_by
-- và registrations, không lưu thành vai trò tài khoản.
-- Mở rộng tạm ENUM để chuyển dữ liệu cũ an toàn, không làm mất tài khoản.
ALTER TABLE users
    MODIFY role ENUM('admin','organizer','staff','participant','user')
    NOT NULL DEFAULT 'user';

-- Các tài khoản Organizer/Staff/Participant cũ đều trở thành User ở cấp hệ thống.
UPDATE users
SET role = 'user'
WHERE role IN ('organizer','staff','participant');

-- Thu gọn ENUM còn hai vai trò cấp hệ thống.
ALTER TABLE users
    MODIFY role ENUM('admin','user')
    NOT NULL DEFAULT 'user';

-- Bổ sung avatar_path cho database cũ mà không làm mất dữ liệu.
SET @has_avatar_path = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'avatar_path'
);
SET @avatar_sql = IF(
    @has_avatar_path = 0,
    'ALTER TABLE users ADD COLUMN avatar_path VARCHAR(255) NULL DEFAULT NULL AFTER status',
    'SELECT 1'
);
PREPARE avatar_stmt FROM @avatar_sql;
EXECUTE avatar_stmt;
DEALLOCATE PREPARE avatar_stmt;

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

-- 5A-1. Liên kết người được giao việc với tài khoản thật; giữ cột assignee cũ để tương thích.
SET @has_assignee_user_id = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='event_tasks' AND COLUMN_NAME='assignee_user_id');
SET @task_assignee_sql = IF(@has_assignee_user_id=0, 'ALTER TABLE event_tasks ADD COLUMN assignee_user_id INT NULL AFTER assignee', 'SELECT 1');
PREPARE task_assignee_stmt FROM @task_assignee_sql; EXECUTE task_assignee_stmt; DEALLOCATE PREPARE task_assignee_stmt;
SET @has_task_assignee_fk = (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='event_tasks' AND COLUMN_NAME='assignee_user_id' AND REFERENCED_TABLE_NAME='users');
SET @task_assignee_fk_sql = IF(@has_task_assignee_fk=0, 'ALTER TABLE event_tasks ADD CONSTRAINT fk_event_tasks_assignee_user FOREIGN KEY (assignee_user_id) REFERENCES users(id) ON DELETE SET NULL', 'SELECT 1');
PREPARE task_assignee_fk_stmt FROM @task_assignee_fk_sql; EXECUTE task_assignee_fk_stmt; DEALLOCATE PREPARE task_assignee_fk_stmt;

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

-- 5D. Dọn hai sự kiện DEMO được tạo sẵn ở phiên bản trước.
-- Chỉ xóa đúng hai tiêu đề mẫu này; các sự kiện khác được giữ nguyên.
-- Các đăng ký/khảo sát/công việc/ngân sách/tài nguyên liên quan sẽ được
-- xóa theo ON DELETE CASCADE đã khai báo ở các bảng tương ứng.
DELETE FROM events
WHERE title IN (
    'Kỹ năng truyền thông sự kiện',
    'Ngày hội Công nghệ số'
);

-- 6. Tài khoản quản trị ban đầu (chỉ thêm nếu email chưa tồn tại).
-- Mật khẩu ban đầu: admin123. Đăng nhập và đổi mật khẩu ngay sau khi cài đặt.
INSERT INTO users (full_name, email, password, role)
SELECT 'Quản trị viên', 'admin@eventhub.vn',
       '$2y$12$W4xt5EaWJsRjTsrszPtYOOi5igba9y0kmQD0xQz.fcP2QRgMguTnK',
       'admin'
WHERE NOT EXISTS (
    SELECT 1 FROM users WHERE email = 'admin@eventhub.vn'
);

-- 7. Không tạo dữ liệu mẫu cho diễn giả và ban tổ chức.
-- Người quản trị tự thêm dữ liệu trong manage.php.


-- PHAN QUYEN HAI CAP:
-- Logic chuyen role cu (organizer/staff/participant) ve user da duoc
-- tich hop trong muc 1 cua file nay. Khong can chay migration_two_level_roles.sql rieng.

-- =====================================================
-- HOÀN TẤT
-- Các bảng event_tasks, event_budget_items và event_resources
-- đã được tạo ở phần 5A, 5B và 5C phía trên.
-- Không cần import database_update_modules.sql riêng nữa.
-- =====================================================


-- =====================================================
-- 8. Không chèn dữ liệu mẫu cho sự kiện, diễn giả hoặc ban tổ chức.
-- Dữ liệu do người dùng tự tạo sẽ được lưu trong các bảng tương ứng.
-- Không xóa dữ liệu diễn giả/ban tổ chức hiện có khi chạy lại file SQL.
-- =====================================================


-- =====================================================
-- 9. DỮ LIỆU MẪU PHỤC VỤ KIỂM THỬ (ĐÃ GỘP TỪ database_demo.sql)
-- Tài khoản User demo: demo.user@eventory.vn / Demo12345!
-- Chỉ thêm bản ghi nếu chưa tồn tại; có thể chạy lại mà không tạo bản ghi trùng.
-- =====================================================
USE quanly_sukien;

INSERT INTO users (full_name, email, password, role, status)
SELECT 'Nguyễn Minh Anh', 'demo.user@eventory.vn',
       '$2y$12$tDj0C.HaxNQ3UK9xduzFauOZt4d66Wg9NO0U8jqNHntKnoUxSOkyq', 'user', 1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email='demo.user@eventory.vn');

SET @admin_id := (SELECT id FROM users WHERE email='admin@eventhub.vn' LIMIT 1);
SET @user_id := (SELECT id FROM users WHERE email='demo.user@eventory.vn' LIMIT 1);

INSERT INTO speakers (name, info)
SELECT 'Trần Hoàng Nam', 'Chuyên gia truyền thông và tổ chức sự kiện'
WHERE NOT EXISTS (SELECT 1 FROM speakers WHERE name='Trần Hoàng Nam');
SET @speaker_id := (SELECT id FROM speakers WHERE name='Trần Hoàng Nam' LIMIT 1);

INSERT INTO organizers (name, info)
SELECT 'CLB Sáng tạo trẻ', 'Đơn vị phối hợp tổ chức các hoạt động sinh viên'
WHERE NOT EXISTS (SELECT 1 FROM organizers WHERE name='CLB Sáng tạo trẻ');
SET @organizer_id := (SELECT id FROM organizers WHERE name='CLB Sáng tạo trẻ' LIMIT 1);

INSERT INTO events (title, description, event_date, location, capacity, status, speaker_id, organizer_id, created_by)
SELECT 'Ngày hội Công nghệ và Sáng tạo', 'Không gian trải nghiệm công nghệ, đổi mới sáng tạo và kết nối sinh viên.', DATE_ADD(NOW(), INTERVAL 7 DAY), 'Hội trường A', 150, 'Sắp diễn ra', @speaker_id, @organizer_id, @admin_id
WHERE @admin_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM events WHERE title='Ngày hội Công nghệ và Sáng tạo');

INSERT INTO events (title, description, event_date, location, capacity, status, speaker_id, organizer_id, created_by)
SELECT 'Workshop Kỹ năng tổ chức sự kiện', 'Chia sẻ quy trình lập kế hoạch, điều phối nhân sự và quản trị rủi ro sự kiện.', DATE_ADD(NOW(), INTERVAL 14 DAY), 'Phòng hội thảo B', 80, 'Sắp diễn ra', @speaker_id, @organizer_id, @admin_id
WHERE @admin_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM events WHERE title='Workshop Kỹ năng tổ chức sự kiện');

INSERT INTO events (title, description, event_date, location, capacity, status, speaker_id, organizer_id, created_by)
SELECT 'Ngày hội Kết nối tân sinh viên', 'Hoạt động giao lưu, định hướng và kết nối cộng đồng sinh viên.', DATE_SUB(NOW(), INTERVAL 10 DAY), 'Sân trung tâm', 200, 'Đã kết thúc', @speaker_id, @organizer_id, @admin_id
WHERE @admin_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM events WHERE title='Ngày hội Kết nối tân sinh viên');

-- Một sự kiện do tài khoản User demo tạo để kiểm thử vai trò Organizer.
INSERT INTO events (title, description, event_date, location, capacity, status, speaker_id, organizer_id, created_by)
SELECT 'Giao lưu Kỹ năng mềm cho sinh viên', 'Sự kiện minh họa do tài khoản người dùng tạo và tự quản lý.', DATE_ADD(NOW(), INTERVAL 21 DAY), 'Phòng sinh hoạt chung C', 60, 'Sắp diễn ra', @speaker_id, @organizer_id, @user_id
WHERE @user_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM events WHERE title='Giao lưu Kỹ năng mềm cho sinh viên');

SET @event_user_owned := (SELECT id FROM events WHERE title='Giao lưu Kỹ năng mềm cho sinh viên' LIMIT 1);
INSERT INTO registrations (event_id, user_id, attendance_code, attended, checked_in_at)
SELECT @event_user_owned, @admin_id, 'EVTDEMO04', 0, NULL
WHERE @event_user_owned IS NOT NULL AND @admin_id IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM registrations WHERE event_id=@event_user_owned AND user_id=@admin_id);

SET @event_upcoming := (SELECT id FROM events WHERE title='Ngày hội Công nghệ và Sáng tạo' LIMIT 1);
SET @event_workshop := (SELECT id FROM events WHERE title='Workshop Kỹ năng tổ chức sự kiện' LIMIT 1);
SET @event_past := (SELECT id FROM events WHERE title='Ngày hội Kết nối tân sinh viên' LIMIT 1);

INSERT INTO registrations (event_id, user_id, attendance_code, attended, checked_in_at)
SELECT @event_upcoming, @user_id, 'EVTDEMO01', 0, NULL
WHERE @event_upcoming IS NOT NULL AND @user_id IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM registrations WHERE event_id=@event_upcoming AND user_id=@user_id);

INSERT INTO registrations (event_id, user_id, attendance_code, attended, checked_in_at)
SELECT @event_workshop, @user_id, 'EVTDEMO02', 0, NULL
WHERE @event_workshop IS NOT NULL AND @user_id IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM registrations WHERE event_id=@event_workshop AND user_id=@user_id);

INSERT INTO registrations (event_id, user_id, attendance_code, attended, checked_in_at)
SELECT @event_past, @user_id, 'EVTDEMO03', 1, DATE_SUB(NOW(), INTERVAL 9 DAY)
WHERE @event_past IS NOT NULL AND @user_id IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM registrations WHERE event_id=@event_past AND user_id=@user_id);

INSERT INTO surveys (event_id, user_id, rating, comment)
SELECT @event_past, @user_id, 5, 'Chương trình được tổ chức tốt, nội dung hữu ích.'
WHERE @event_past IS NOT NULL AND @user_id IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM surveys WHERE event_id=@event_past AND user_id=@user_id);

INSERT INTO event_tasks (event_id, title, description, status, priority, due_date, assignee, assignee_user_id, created_by)
SELECT @event_upcoming, 'Hoàn thiện kịch bản chương trình', 'Rà soát timeline và nội dung từng phần.', 'doing', 'high', DATE_ADD(CURDATE(), INTERVAL 4 DAY), 'Nguyễn Minh Anh', @user_id, @admin_id
WHERE @event_upcoming IS NOT NULL AND NOT EXISTS (SELECT 1 FROM event_tasks WHERE event_id=@event_upcoming AND title='Hoàn thiện kịch bản chương trình');

INSERT INTO event_tasks (event_id, title, description, status, priority, due_date, assignee, assignee_user_id, created_by)
SELECT @event_upcoming, 'Chuẩn bị thiết bị trình chiếu', 'Kiểm tra máy chiếu, micro và âm thanh.', 'todo', 'medium', DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'Nguyễn Minh Anh', @user_id, @admin_id
WHERE @event_upcoming IS NOT NULL AND NOT EXISTS (SELECT 1 FROM event_tasks WHERE event_id=@event_upcoming AND title='Chuẩn bị thiết bị trình chiếu');

INSERT INTO event_budget_items (event_id, item_name, category, quantity, unit_cost, payment_status, note, created_by)
SELECT @event_upcoming, 'Thuê thiết bị âm thanh', 'Thiết bị', 1, 2500000, 'planned', 'Dự toán minh họa', @admin_id
WHERE @event_upcoming IS NOT NULL AND NOT EXISTS (SELECT 1 FROM event_budget_items WHERE event_id=@event_upcoming AND item_name='Thuê thiết bị âm thanh');

INSERT INTO event_budget_items (event_id, item_name, category, quantity, unit_cost, payment_status, note, created_by)
SELECT @event_upcoming, 'Thiết kế ấn phẩm truyền thông', 'Truyền thông', 1, 800000, 'paid', 'Đã thanh toán minh họa', @admin_id
WHERE @event_upcoming IS NOT NULL AND NOT EXISTS (SELECT 1 FROM event_budget_items WHERE event_id=@event_upcoming AND item_name='Thiết kế ấn phẩm truyền thông');

INSERT INTO event_resources (event_id, resource_name, category, quantity, unit, resource_status, responsible, note, created_by)
SELECT @event_upcoming, 'Micro không dây', 'Thiết bị', 4, 'chiếc', 'ready', 'Nguyễn Minh Anh', 'Đã kiểm tra pin và tín hiệu', @admin_id
WHERE @event_upcoming IS NOT NULL AND NOT EXISTS (SELECT 1 FROM event_resources WHERE event_id=@event_upcoming AND resource_name='Micro không dây');

INSERT INTO event_resources (event_id, resource_name, category, quantity, unit, resource_status, responsible, note, created_by)
SELECT @event_upcoming, 'Bảng tên đại biểu', 'Vật tư', 30, 'cái', 'planned', 'Ban hậu cần', 'In trước ngày diễn ra', @admin_id
WHERE @event_upcoming IS NOT NULL AND NOT EXISTS (SELECT 1 FROM event_resources WHERE event_id=@event_upcoming AND resource_name='Bảng tên đại biểu');

-- Hoàn tất. Dữ liệu mẫu có thể xóa thủ công theo tiêu đề/email demo nếu cần.
