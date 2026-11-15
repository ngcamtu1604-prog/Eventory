<?php
require_once __DIR__ . '/../config/auth.php';

$userName = $_SESSION['user']['name'] ?? 'Người dùng';
$userRole = $_SESSION['user']['role'] ?? 'user';
$userEmail = $_SESSION['user']['email'] ?? '';
$userAvatar = $_SESSION['user']['avatar'] ?? '';
$roleLabel = ($userRole === 'admin') ? 'Admin · Quản trị hệ thống' : 'User · Tài khoản thông thường';
$avatarLetter = function_exists('mb_substr')
    ? mb_strtoupper(mb_substr($userName, 0, 1, 'UTF-8'), 'UTF-8')
    : strtoupper(substr($userName, 0, 1));

$currentPage = basename($_SERVER['PHP_SELF']);
function sidebar_active($pages, $currentPage) {
    return in_array($currentPage, (array)$pages, true) ? ' active' : '';
}

function eventory_sidebar_icon($name) {
    $icons = [
        'home' => '<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
        'discover' => '<circle cx="11" cy="11" r="7"/><path d="m16.5 16.5 4 4"/><path d="m13.5 8.5-2 5-5 2 2-5z"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
        'ticket' => '<path d="M4 5h16v4a3 3 0 0 0 0 6v4H4v-4a3 3 0 0 0 0-6z"/><path d="M12 8v1m0 3v1m0 3v1"/>',
        'plus' => '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'check' => '<path d="M9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
        'mic' => '<rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10v2a7 7 0 0 0 14 0v-2M12 19v3m-4 0h8"/>',
        'list' => '<path d="M9 6h12M9 12h12M9 18h12"/><path d="M4 6h.01M4 12h.01M4 18h.01"/>',
        'chart' => '<path d="M4 19V5m0 14h17"/><rect x="7" y="11" width="3" height="5" rx="1"/><rect x="13" y="7" width="3" height="9" rx="1"/><rect x="19" y="3" width="2" height="13" rx="1"/>',
        'shield' => '<path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11z"/><path d="m9 12 2 2 4-4"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'settings' => '<path d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8z"/><path d="m19.4 15 .1.1 1.4 1.1-1.4 2.4-1.7-.6a8 8 0 0 1-1.6.9l-.3 1.8h-2.8l-.3-1.8a8 8 0 0 1-1.6-.9l-1.7.6-1.4-2.4 1.4-1.1a7 7 0 0 1 0-1.9l-1.4-1.1 1.4-2.4 1.7.6a8 8 0 0 1 1.6-.9l.3-1.8h2.8l.3 1.8a8 8 0 0 1 1.6.9l1.7-.6 1.4 2.4-1.4 1.1a7 7 0 0 1 0 1.9z"/>',
    ];
    $path = $icons[$name] ?? $icons['list'];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EVENTORY</title>
    <script>
    (function () {
        try {
            var theme = localStorage.getItem('eventory_theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        } catch (e) {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    })();
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css?v=eventory-final-cards-email-1">

</head>
<body>
<div class="app-shell">
    <div class="app-layout">
        <aside class="sidebar">
            <a class="brand" href="index.php">
                <span class="brand-mark">▦</span>
                <span>EVENTORY</span>
            </a>

            <div class="sidebar-inner">
                <div class="sidebar-group">
                    <div class="sidebar-section-label">MENU</div>
                    <nav class="sidebar-nav" aria-label="Menu chính">
                        <a class="sidebar-link<?= sidebar_active('index.php', $currentPage) ?>" href="index.php">
                            <span class="sidebar-icon"><?= eventory_sidebar_icon('home') ?></span><span>Trang chủ</span>
                        </a>
                        <a class="sidebar-link<?= sidebar_active('events.php', $currentPage) ?>" href="events.php">
                            <span class="sidebar-icon"><?= eventory_sidebar_icon('discover') ?></span><span>Khám phá sự kiện</span>
                        </a>
                        <a class="sidebar-link<?= sidebar_active('my_events.php', $currentPage) ?>" href="my_events.php">
                            <span class="sidebar-icon"><?= eventory_sidebar_icon('calendar') ?></span><span>Sự kiện của tôi</span>
                        </a>
                        <a class="sidebar-link<?= sidebar_active('myevents.php', $currentPage) ?>" href="myevents.php">
                            <span class="sidebar-icon"><?= eventory_sidebar_icon('ticket') ?></span><span>Vé của tôi</span>
                        </a>
                    </nav>
                </div>

                <div class="sidebar-group">
                    <div class="sidebar-section-label">QUẢN LÝ SỰ KIỆN</div>
                    <nav class="sidebar-nav" aria-label="Quản lý sự kiện">
                        <a class="sidebar-link<?= sidebar_active('event_form.php', $currentPage) ?>" href="event_form.php">
                            <span class="sidebar-icon"><?= eventory_sidebar_icon('plus') ?></span><span>Tạo sự kiện</span>
                        </a>
                        <a class="sidebar-link<?= sidebar_active('participants.php', $currentPage) ?>" href="participants.php">
                            <span class="sidebar-icon"><?= eventory_sidebar_icon('users') ?></span><span>Người tham gia</span>
                        </a>
                        <a class="sidebar-link<?= sidebar_active('checkin.php', $currentPage) ?>" href="checkin.php">
                            <span class="sidebar-icon"><?= eventory_sidebar_icon('check') ?></span><span>Check-in / Điểm danh</span>
                        </a>
                        <a class="sidebar-link<?= sidebar_active('registration.php', $currentPage) ?>" href="registration.php">
                            <span class="sidebar-icon"><?= eventory_sidebar_icon('list') ?></span><span>Đăng ký & vé</span>
                        </a>
                        <?php if ($userRole === 'admin'): ?>
                            <a class="sidebar-link<?= sidebar_active('manage.php', $currentPage) ?>" href="manage.php">
                                <span class="sidebar-icon"><?= eventory_sidebar_icon('mic') ?></span><span>Diễn giả & ban tổ chức</span>
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>

                <div class="sidebar-group">
                    <div class="sidebar-section-label">ĐIỀU HÀNH</div>
                    <nav class="sidebar-nav" aria-label="Điều hành">
                        <a class="sidebar-link<?= sidebar_active('timeline.php', $currentPage) ?>" href="timeline.php">
                            <span class="sidebar-icon"><?= eventory_sidebar_icon('calendar') ?></span><span>Timeline & Kanban</span>
                        </a>
                        <a class="sidebar-link<?= sidebar_active('budget.php', $currentPage) ?>" href="budget.php">
                            <span class="sidebar-icon"><?= eventory_sidebar_icon('chart') ?></span><span>Ngân sách & chi phí</span>
                        </a>
                        <a class="sidebar-link<?= sidebar_active('resources.php', $currentPage) ?>" href="resources.php">
                            <span class="sidebar-icon"><?= eventory_sidebar_icon('list') ?></span><span>Tài nguyên</span>
                        </a>
                    </nav>
                </div>

                <div class="sidebar-group">
                    <div class="sidebar-section-label">PHÂN TÍCH & HỆ THỐNG</div>
                    <nav class="sidebar-nav" aria-label="Phân tích và hệ thống">
                        <a class="sidebar-link<?= sidebar_active('survey_admin.php', $currentPage) ?>" href="survey_admin.php">
                            <span class="sidebar-icon"><?= eventory_sidebar_icon('chart') ?></span><span>Khảo sát & đánh giá</span>
                        </a>
                        <a class="sidebar-link<?= sidebar_active('reports.php', $currentPage) ?>" href="reports.php">
                            <span class="sidebar-icon"><?= eventory_sidebar_icon('chart') ?></span><span>Báo cáo & thống kê</span>
                        </a>
                        <?php if ($userRole === 'admin'): ?>
                            <a class="sidebar-link<?= sidebar_active('users.php', $currentPage) ?>" href="users.php">
                                <span class="sidebar-icon"><?= eventory_sidebar_icon('shield') ?></span><span>Tài khoản & phân quyền</span>
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>

                <div class="sidebar-group">
                    <div class="sidebar-section-label">CÁ NHÂN</div>
                    <nav class="sidebar-nav" aria-label="Cá nhân">
                        <a class="sidebar-link<?= sidebar_active('account.php', $currentPage) ?>" href="account.php">
                            <span class="sidebar-icon"><?= eventory_sidebar_icon('user') ?></span><span>Tài khoản</span>
                        </a>
                        <a class="sidebar-link<?= sidebar_active('settings.php', $currentPage) ?>" href="settings.php">
                            <span class="sidebar-icon"><?= eventory_sidebar_icon('settings') ?></span><span>Cài đặt</span>
                        </a>
                    </nav>
                </div>
            </div>

            <div class="sidebar-bottom">
                <span class="sidebar-bottom-dot"></span>
                <span>Discover · Connect · Experience</span>
            </div>
        </aside>

        <div class="app-content">
            <header class="app-header">
                <div class="header-spacer"></div>
                <div class="header-actions">
                    <span class="header-tagline">Every Event Tells a Story.</span>
                    <div class="account-menu" id="accountMenu">
                        <button type="button" class="account-trigger" id="accountTrigger"
                                aria-expanded="false" aria-controls="accountDropdown" aria-label="Mở menu tài khoản">
                            <span class="small d-none d-sm-inline"><?= h($userName) ?></span>
                            <span class="account-avatar">
                                <?php if ($userAvatar !== ''): ?>
                                    <img src="<?= h($userAvatar) ?>" alt="Ảnh đại diện">
                                <?php else: ?>
                                    <?= h($avatarLetter) ?>
                                <?php endif; ?>
                            </span>
                            <span class="account-chevron">▼</span>
                        </button>
                        <div class="account-dropdown" id="accountDropdown">
                            <div class="account-profile">
                                <div class="account-avatar account-avatar-large">
                                    <?php if ($userAvatar !== ''): ?>
                                        <img src="<?= h($userAvatar) ?>" alt="Ảnh đại diện">
                                    <?php else: ?>
                                        <?= h($avatarLetter) ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="account-profile-name"><?= h($userName) ?></div>
                                    <?php if ($userEmail !== ''): ?>
                                        <div class="account-profile-email"><?= h($userEmail) ?></div>
                                    <?php endif; ?>
                                    <span class="account-role"><?= h($roleLabel) ?></span>
                                </div>
                            </div>
                            <div class="account-divider"></div>
                            <a class="account-link" href="account.php">
                                <span class="account-link-icon">👤</span><span>Thông tin tài khoản</span>
                            </a>
                            <div class="account-divider"></div>
                            <a class="account-link logout" href="logout.php">
                                <span class="account-link-icon">↪</span><span>Đăng xuất</span>
                            </a>
                        </div>
                    </div>
                </div>
            </header>
            <main class="workspace-main<?= $currentPage === 'events.php' ? ' events-list-page' : '' ?>">
