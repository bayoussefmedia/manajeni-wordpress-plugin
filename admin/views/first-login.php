<?php
/**
 * Première page de connexion - Hub d'Activation Fantastic
 */

if (!current_user_can('manage_options')) {
    wp_die('Accès non autorisé.');
}

$error = '';
if (isset($_POST['manajeni_first_login']) && check_admin_referer('manajeni_first_login_nonce')) {
    $email = sanitize_email($_POST['email']);
    $password = sanitize_text_field($_POST['password']);
    
    $db_handler = new Manajeni_DB();
    $result = $db_handler->verify_credentials($email, $password);
    
    if ($result['success']) {
        update_option('manajeni_temp_email', $email);
        delete_option('manajeni_need_first_login');
        
        $xml_handler = new Manajeni_XML_Handler();
        if (!$xml_handler->is_url_declared()) {
            $default_url = home_url();
            $xml_handler->declare_url($default_url);
            update_option('manajeni_url', $default_url);
        }
        
        manajeni_connector_add_log('first_login', 'success', 'Activation réussie pour ' . $email);
        manajeni_safe_redirect(admin_url('admin.php?page=manajeni-api-connection'));
        exit;
    } else {
        $error = $result['message'];
    }
}
?>

<div class="mj-app-container" style="display:flex; align-items:center; justify-content:center; min-height:80vh;">
    
    <div style="width: 100%; max-width: 500px;">
        <div style="text-align: center; margin-bottom: 40px;">
            <div style="width:80px; height:80px; background:white; border-radius:30px; display:inline-flex; align-items:center; justify-content:center; font-size:40px; box-shadow: var(--mj-shadow-xl); margin-bottom:20px;">🚀</div>
            <h1 style="font-family:'Outfit', sans-serif; font-size: 32px; font-weight: 800; color:var(--mj-slate-900); margin:0;">Manajeni Workspace</h1>
            <p style="color:var(--mj-slate-500); font-size:16px; margin-top:8px;">Activation de votre instance connectée</p>
        </div>

        <?php if ($error): ?>
            <div class="notice mj-notice notice-error">
                <p><strong>Erreur :</strong> <?php echo esc_html($error); ?></p>
            </div>
        <?php endif; ?>
        
        <div class="mj-table-wrapper" style="padding: 40px;">
            <form method="post">
                <?php wp_nonce_field('manajeni_first_login_nonce'); ?>
                
                <div class="mj-form-group">
                    <label>📧 Email Manajeni</label>
                    <input type="email" name="email" class="mj-form-control" required placeholder="nom@entreprise.com">
                </div>
                
                <div class="mj-form-group">
                    <label>🔒 Mot de passe</label>
                    <input type="password" name="password" class="mj-form-control" required placeholder="••••••••">
                </div>
                
                <div style="background: var(--mj-warning-soft); padding: 15px; border-radius: 12px; margin-bottom: 25px; border: 1px solid rgba(245, 158, 11, 0.2); display:flex; gap:12px; align-items:center;">
                    <span style="font-size:20px;">💡</span>
                    <p style="margin: 0; font-size: 13px; color: var(--mj-warning); font-weight:600;">
                        Mode Simulation : <code>test@manajeni.com</code> / <code>password</code>
                    </p>
                </div>
                
                <div style="margin-top: 30px;">
                    <button type="submit" name="manajeni_first_login" class="mj-btn-primary" style="width: 100%; justify-content: center; padding: 18px;">
                        Activer le connecteur ⚡
                    </button>
                </div>
            </form>
        </div>
        
        <div style="text-align: center; margin-top: 30px; color: var(--mj-slate-400); font-size: 13px;">
            &copy; <?php echo date('Y'); ?> Manajeni Core System - Security Verified 🔐
        </div>
    </div>
</div>

<style>
/* Réajustement pour la page de login */
#wpbody-content { padding-bottom: 0; }
.mj-app-container { animation: mjFadeIn 0.8s cubic-bezier(0.16, 1, 0.3, 1); }
</style>