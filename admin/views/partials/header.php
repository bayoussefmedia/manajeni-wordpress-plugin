<?php
/**
 * Header Partial - Premium SaaS Experience
 */

$current_user = wp_get_current_user();
$session_data = $current_user && $current_user->exists() ? $current_user->display_name : parse_url(home_url(), PHP_URL_HOST);
$avatar_seed = $current_user && $current_user->exists() ? $current_user->user_email : home_url();
?>

<div class="mj-header" style="padding:12px 24px; background:white; border-radius:16px; margin-bottom:30px; display:flex; justify-content:space-between; align-items:center; box-shadow: var(--mj-shadow-sm);">
    <div class="mj-logo-area" style="display:flex; align-items:center; gap:12px;">
        <div style="width:40px; height:40px; background:var(--mj-primary); border-radius:10px; display:flex; align-items:center; justify-content:center; color:white; font-size:20px;">M</div>
        <h1 style="font-family:'Outfit', sans-serif; font-size: 18px; font-weight: 800; color:var(--mj-slate-900); margin:0;">Manajeni Setup</h1>
    </div>
    
    <div class="mj-user-nav" style="position:relative;">
        <div class="mj-user-trigger" id="mjUserTrigger" style="display:flex; align-items:center; gap:12px; cursor:pointer; padding:6px 12px; border-radius:30px; transition:background 0.2s;">
            <div class="mj-avatar" style="width:32px; height:32px; border-radius:50%; border:2px solid var(--mj-primary-soft); overflow:hidden;">
                <img src="https://www.gravatar.com/avatar/<?php echo esc_attr(md5(strtolower(trim($avatar_seed)))); ?>?s=64&d=mp" alt="User" style="width:100%;">
            </div>
            <span style="font-weight: 600; font-size:13px; color:var(--mj-slate-700)"><?php echo esc_html($session_data); ?></span>
            <span style="font-size: 10px; opacity:0.4">▼</span>
        </div>
        
        <div class="mj-dropdown" id="mjUserDropdown" style="position:absolute; top:calc(100% + 10px); right:0; background:white; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.1); width:220px; z-index:999; border:1px solid var(--mj-slate-100); overflow:hidden; display:none;">
            <div style="padding: 15px; background: var(--mj-slate-50); border-bottom: 1px solid var(--mj-slate-100);">
                <div style="font-weight: 800; font-size: 14px; color:var(--mj-slate-900)"><?php echo esc_html($session_data); ?></div>
                <div style="font-size: 11px; color: var(--mj-slate-500);">Connecté au Cloud</div>
            </div>
            
            <a href="<?php echo admin_url('admin.php?page=manajeni-dashboard'); ?>" class="mj-dropdown-item">📊 Dashboard HUB</a>
            <a href="<?php echo admin_url('admin.php?page=manajeni-settings'); ?>" class="mj-dropdown-item">⚙️ Paramètres</a>
            
            <div style="height:1px; background:var(--mj-slate-100); margin:5px 0;"></div>
            
            <a href="<?php echo admin_url('admin.php?page=manajeni-logout'); ?>" class="mj-dropdown-item" style="color:var(--mj-error) !important;" onclick="return confirm('Voulez-vous vous déconnecter ?')">🔓 Déconnexion</a>
        </div>
    </div>
</div>

<style>
.mj-dropdown.show { display: block; animation: mjFadeIn 0.2s ease-out; }
.mj-dropdown-item { display: block; padding: 12px 15px; text-decoration: none; color: var(--mj-slate-600); font-size: 13px; font-weight: 600; }
.mj-dropdown-item:hover { background: var(--mj-primary-soft); color: var(--mj-primary); }
.mj-user-trigger:hover { background: var(--mj-slate-50); }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const trigger = document.getElementById('mjUserTrigger');
    const dropdown = document.getElementById('mjUserDropdown');
    if (trigger && dropdown) {
        trigger.addEventListener('click', e => { e.stopPropagation(); dropdown.classList.toggle('show'); });
        document.addEventListener('click', () => dropdown.classList.remove('show'));
    }
});
</script>
