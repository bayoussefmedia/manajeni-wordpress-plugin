<?php
/**
 * Page de connexion API - Hub de Sécurité Fantastic
 */

if (!current_user_can('manage_options')) {
    wp_die('Accès non autorisé.');
}

$error = '';
$success = '';
$email = get_option('manajeni_temp_email', '');

if (isset($_POST['save_api_key']) && check_admin_referer('manajeni_api_connection_nonce')) {
    $api_key = sanitize_text_field($_POST['api_key']);
    $password = sanitize_text_field($_POST['password']);
    $simulation_mode = get_option('manajeni_simulation_mode', true);
    
    if ($simulation_mode) {
        if (!empty($api_key) && !empty($password)) {
            if ($password === 'password') {
                $session = new Manajeni_Session();
                $connection_date = current_time('mysql');
                if ($session->login($email, $api_key, $connection_date)) {
                    delete_option('manajeni_temp_email');
                    $success = 'Connexion sécurisée établie ! Redirection vers votre Hub...';
                    echo '<script>setTimeout(function(){ window.location.href = "' . admin_url('admin.php?page=manajeni-dashboard') . '"; }, 2000);</script>';
                } else {
                    $error = 'Erreur d’initialisation de la session.';
                }
            } else {
                $error = 'Mot de passe Manajeni incorrect.';
            }
        } else {
            $error = 'Veuillez remplir tous les champs.';
        }
    } else {
        $error = 'Le mode API réelle n’est pas encore disponible.';
    }
}
?>

<div class="mj-app-container" style="max-width: 800px;">
    
    <?php include MANAJENI_CONNECTOR_PATH . 'admin/views/partials/header.php'; ?>

    <div style="margin-bottom: 40px; text-align:center;">
        <h2 style="font-family:'Outfit', sans-serif; font-size: 28px; font-weight: 800; color: var(--mj-slate-900); margin:0;">🔒 Sécurisation de l'accès</h2>
        <p style="color: var(--mj-slate-500); font-size:16px; margin-top:8px;">Configurez vos clés d'accès API pour finaliser la jonction.</p>
    </div>

    <?php if ($error): ?>
        <div class="notice mj-notice notice-error">
            <p><strong>❌ <?php echo esc_html($error); ?></strong></p>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="notice mj-notice notice-success">
            <p><strong>✅ <?php echo esc_html($success); ?></strong></p>
        </div>
    <?php endif; ?>
    
    <div class="mj-table-wrapper" style="padding: 40px;">
        <form method="post">
            <?php wp_nonce_field('manajeni_api_connection_nonce'); ?>
            
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:30px;">
                <div class="mj-form-group">
                    <label>📧 Identifiant Manajeni</label>
                    <input type="email" class="mj-form-control" value="<?php echo esc_attr($email); ?>" readonly disabled style="background: var(--mj-slate-50); opacity:0.6;">
                </div>

                <div class="mj-form-group">
                    <label>🔑 Confirmation Password</label>
                    <input type="password" name="password" class="mj-form-control" required placeholder="••••••••">
                </div>
            </div>

            <div class="mj-form-group" style="margin-top:20px;">
                <label>💎 Clé API Primaire</label>
                <input type="password" name="api_key" class="mj-form-control" required placeholder="Saisissez votre clé Manajeni">
                <div style="margin-top:12px; background:var(--mj-primary-soft); padding:12px; border-radius:10px; border-left:4px solid var(--mj-primary);">
                    <p style="margin:0; font-size:13px; color:var(--mj-primary); font-weight:600;">
                        Indice : Votre clé API est disponible dans l'onglet "Développeurs" de votre plateforme.
                    </p>
                </div>
            </div>
            
            <div style="margin-top: 40px; display: flex; gap: 20px;">
                <button type="submit" name="save_api_key" class="mj-btn-primary" style="flex: 1; justify-content: center; padding: 18px;">
                    ⚡ Établir la liaison sécurisée
                </button>
                <a href="<?php echo admin_url('admin.php?page=manajeni-dashboard'); ?>" class="mj-btn-secondary" style="display:flex; align-items:center;">
                    Annuler
                </a>
            </div>
        </form>
    </div>
</div>