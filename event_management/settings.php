<?php
require_once 'config/auth.php';
require_login();
require 'includes/header.php';
?>
<div class="settings-page">
    <div class="settings-heading">
        <div>
            <span class="settings-eyebrow">PERSONAL WORKSPACE</span>
            <h2 class="page-title mb-1">Cài đặt</h2>
            <p class="muted mb-0">Điều chỉnh cách EVENTORY hoạt động trên thiết bị của bạn.</p>
        </div>
        <a href="account.php" class="settings-profile-link">
            <span class="settings-profile-icon">⌘</span>
            <span><strong>Tài khoản của tôi</strong><small>Thông tin và bảo mật</small></span>
            <span aria-hidden="true">›</span>
        </a>
    </div>

    <div class="settings-layout">
        <section class="card settings-card">
            <div class="settings-card-heading">
                <span class="settings-icon settings-icon-notify">♧</span>
                <div>
                    <h5>Thông báo</h5>
                    <p>Chọn những thông tin bạn muốn EVENTORY nhắc nhở.</p>
                </div>
            </div>
            <label class="settings-option">
                <span><strong>Nhắc nhở sự kiện sắp diễn ra</strong><small>Hiển thị nhắc nhở cho những sự kiện bạn đã đăng ký.</small></span>
                <input class="form-check-input settings-switch" type="checkbox" id="settingEventReminder" checked>
            </label>
            <label class="settings-option">
                <span><strong>Cập nhật sự kiện</strong><small>Bật thông báo giao diện khi thông tin sự kiện thay đổi.</small></span>
                <input class="form-check-input settings-switch" type="checkbox" id="settingEventUpdates" checked>
            </label>
        </section>

        <section class="card settings-card">
            <div class="settings-card-heading">
                <span class="settings-icon settings-icon-display">◐</span>
                <div>
                    <h5>Giao diện</h5>
                    <p>Tùy chỉnh cách hiển thị để thao tác thoải mái hơn.</p>
                </div>
            </div>
            <label class="settings-option">
                <span><strong>Giao diện gọn</strong><small>Thu nhỏ khoảng cách trong nội dung và menu.</small></span>
                <input class="form-check-input settings-switch" type="checkbox" id="settingCompact">
            </label>
            <label class="settings-option">
                <span><strong>Chuyển động nhẹ</strong><small>Giảm hiệu ứng chuyển động khi di chuyển giữa các mục.</small></span>
                <input class="form-check-input settings-switch" type="checkbox" id="settingReduceMotion">
            </label>
            <div class="settings-info"><span>✦</span> Bộ màu Midnight & Lavender được giữ thống nhất trên toàn hệ thống.</div>
        </section>

        <section class="card settings-card settings-security-card">
            <div class="settings-card-heading">
                <span class="settings-icon settings-icon-security">♙</span>
                <div>
                    <h5>Tài khoản & bảo mật</h5>
                    <p>Quản lý thông tin cá nhân và bảo vệ tài khoản.</p>
                </div>
            </div>
            <div class="settings-action-row">
                <div><strong>Thông tin cá nhân</strong><small>Cập nhật tên hiển thị, email và ảnh đại diện.</small></div>
                <a href="account.php" class="btn btn-outline-primary btn-sm">Quản lý tài khoản</a>
            </div>
            <div class="settings-action-row">
                <div><strong>Mật khẩu</strong><small>Thay đổi mật khẩu đăng nhập của bạn.</small></div>
                <a href="account.php#change-password" class="btn btn-primary btn-sm">Đổi mật khẩu</a>
            </div>
        </section>

        <section class="settings-savebar">
            <div>
                <strong id="settingsSaveTitle">Tùy chọn được lưu tự động</strong>
                <small id="settingsSaveNote">Các tùy chọn này chỉ áp dụng trên trình duyệt hiện tại.</small>
            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="resetSettings">Khôi phục mặc định</button>
        </section>
    </div>
</div>

<style>
.settings-page{width:100%;max-width:1080px;margin:0 auto}
.settings-heading{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:25px;flex-wrap:wrap}
.settings-eyebrow{display:block;margin-bottom:7px;color:#a995dc;font-size:10px;font-weight:800;letter-spacing:1.5px}
.settings-profile-link{display:flex;align-items:center;gap:11px;padding:10px 14px;border:1px solid rgba(185,160,244,.2);border-radius:14px;background:rgba(74,55,112,.32);color:#f5efff;text-decoration:none;transition:.2s}
.settings-profile-link:hover{background:rgba(112,78,174,.35);color:#fff;transform:translateY(-1px)}
.settings-profile-icon{width:36px;height:36px;display:grid;place-items:center;border-radius:11px;background:#8051df;color:#fff;font-size:20px}
.settings-profile-link strong,.settings-profile-link small{display:block}
.settings-profile-link strong{font-size:12px}
.settings-profile-link small{margin-top:2px;color:#b8acd5;font-size:10px}
.settings-profile-link>span:last-child{font-size:23px;color:#bda6f2}
.settings-layout{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
.settings-card{padding:22px 24px;border:1px solid rgba(224,216,245,.7);border-radius:18px;background:#fff;box-shadow:0 8px 24px rgba(12,7,32,.08);min-width:0}
.settings-card-heading{display:flex;align-items:center;gap:13px;padding-bottom:17px;margin-bottom:3px;border-bottom:1px solid #eeeaf5}
.settings-icon{width:44px;height:44px;flex:0 0 44px;display:grid;place-items:center;border-radius:14px;font-size:23px}
.settings-icon-notify{background:#f0eaff;color:#8051df}
.settings-icon-display{background:#eaf2ff;color:#5579bd}
.settings-icon-security{background:#e9f6f0;color:#438c6c}
.settings-card-heading h5{margin:0 0 4px;color:#2c2441;font-size:16px;font-weight:750}
.settings-card-heading p{margin:0;color:#817995;font-size:11px;line-height:1.5}
.settings-option{display:flex;align-items:center;justify-content:space-between;gap:15px;padding:15px 0;border-bottom:1px solid #f0edf5;cursor:pointer}
.settings-option:last-of-type{border-bottom:0}
.settings-option strong,.settings-option small{display:block}
.settings-option strong{color:#332b49;font-size:12px;font-weight:700}
.settings-option small{margin-top:4px;color:#898198;font-size:10.5px;line-height:1.45}
.settings-switch{width:2.35em!important;height:1.25em!important;flex:0 0 auto;cursor:pointer}
.settings-switch:checked{background-color:#8051df;border-color:#8051df}
.settings-info{display:flex;align-items:flex-start;gap:8px;margin-top:9px;padding:10px 12px;border-radius:10px;background:#f7f3ff;color:#78669e;font-size:10.5px;line-height:1.5}
.settings-info span{color:#8051df}
.settings-security-card{grid-column:1/-1}
.settings-action-row{display:flex;align-items:center;justify-content:space-between;gap:15px;padding:14px 0;border-bottom:1px solid #f0edf5}
.settings-action-row:last-child{border-bottom:0}
.settings-action-row strong,.settings-action-row small{display:block}
.settings-action-row strong{font-size:12px;color:#332b49}
.settings-action-row small{margin-top:4px;font-size:10.5px;color:#898198}
.settings-savebar{grid-column:1/-1;display:flex;align-items:center;justify-content:space-between;gap:14px;padding:5px 2px}
.settings-savebar strong,.settings-savebar small{display:block}
.settings-savebar strong{font-size:11px;color:#e8ddff}
.settings-savebar small{margin-top:3px;color:#a99dc9;font-size:10px}
.settings-savebar .btn{border-color:rgba(210,195,245,.35);color:#ded2f7}
.settings-savebar .btn:hover{background:#30234f;color:#fff}
body.compact-ui .workspace-main{padding-top:18px;padding-bottom:24px}
body.compact-ui .workspace-main .card{border-radius:12px}
body.compact-ui .sidebar-link{min-height:38px}
body.reduce-motion *,body.reduce-motion *::before,body.reduce-motion *::after{scroll-behavior:auto!important;animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important}
@media(max-width:850px){.settings-layout{grid-template-columns:1fr}.settings-security-card,.settings-savebar{grid-column:auto}}
@media(max-width:520px){.settings-card{padding:17px}.settings-heading{align-items:flex-start}.settings-action-row{align-items:flex-start;flex-direction:column}.settings-savebar{align-items:flex-start;flex-direction:column}}
</style>
<script>
document.addEventListener('DOMContentLoaded',function(){
    const config={
        reminder:{id:'settingEventReminder',key:'eventory_setting_event_reminder',fallback:true},
        updates:{id:'settingEventUpdates',key:'eventory_setting_event_updates',fallback:true},
        compact:{id:'settingCompact',key:'eventory_setting_compact',fallback:false},
        motion:{id:'settingReduceMotion',key:'eventory_setting_reduce_motion',fallback:false}
    };
    const note=document.getElementById('settingsSaveNote');
    const title=document.getElementById('settingsSaveTitle');
    function apply(){
        document.body.classList.toggle('compact-ui',document.getElementById(config.compact.id).checked);
        document.body.classList.toggle('reduce-motion',document.getElementById(config.motion.id).checked);
    }
    Object.values(config).forEach(function(item){
        const control=document.getElementById(item.id);
        const saved=localStorage.getItem(item.key);
        control.checked=saved===null?item.fallback:saved==='1';
        control.addEventListener('change',function(){
            localStorage.setItem(item.key,control.checked?'1':'0');
            apply();
            title.textContent='Đã lưu thay đổi';
            note.textContent='Tùy chọn đã được lưu trên trình duyệt hiện tại.';
        });
    });
    apply();
    document.getElementById('resetSettings').addEventListener('click',function(){
        Object.values(config).forEach(function(item){
            localStorage.removeItem(item.key);
            document.getElementById(item.id).checked=item.fallback;
        });
        apply();
        title.textContent='Đã khôi phục mặc định';
        note.textContent='Các tùy chọn đã trở về trạng thái ban đầu.';
    });
});
</script>
<?php require 'includes/footer.php'; ?>
