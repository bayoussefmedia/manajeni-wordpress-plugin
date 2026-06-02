<?php
/**
 * Page de connexion API - Hub de Sécurité Fantastic
 */

if (!current_user_can('manage_options')) {
    wp_die('Accès non autorisé.');
}

$error = '';
$success = '';
$db = new Manajeni_DB();
$current_api_url = get_option('manajeni_url', '');
$masked_api_key = $db->get_masked_api_key();

if (isset($_POST['test_connection']) && check_admin_referer('manajeni_api_connection_nonce')) {
    $api_url = esc_url_raw(wp_unslash($_POST['api_url']));
    $api_key = sanitize_text_field(wp_unslash($_POST['api_key']));

    if (empty($api_url) || empty($api_key)) {
        $error = 'Veuillez renseigner l’URL API et la clé API pour tester la connexion.';
    } else {
        $api_client = new Manajeni_API_Client($api_url, $api_key);
        $result = $api_client->test_connection();

        if ($result['success']) {
            $success = 'Connexion API validée. Vous pouvez maintenant enregistrer la configuration.';
        } else {
            $error = 'Erreur API : ' . $result['message'];
        }
    }
}

if (isset($_POST['save_api_key']) && check_admin_referer('manajeni_api_connection_nonce')) {
    $api_url = esc_url_raw(wp_unslash($_POST['api_url']));
    $api_key = sanitize_text_field(wp_unslash($_POST['api_key']));

    if (empty($api_url)) {
        $error = 'Veuillez saisir une URL API valide.';
    } elseif (empty($api_key) && empty($masked_api_key)) {
        $error = 'Veuillez saisir une clé API valide.';
    } else {
        if (empty($api_key)) {
            $api_key = $db->get_api_key();
        }

        $api_client = new Manajeni_API_Client($api_url, $api_key);
        $result = $api_client->test_connection();

        if (!$result['success']) {
            $error = 'Erreur API : ' . $result['message'];
        } else {
            $session = new Manajeni_Session();
            $connection_date = current_time('mysql');

            if ($session->login($api_key, $api_url, $connection_date)) {
                delete_option('manajeni_need_first_login');
                $success = 'Connexion API sécurisée établie. Redirection vers le tableau de bord...';
                echo '<script>setTimeout(function(){ window.location.href = "' . esc_url(admin_url('admin.php?page=manajeni-dashboard')) . '"; }, 1500);</script>';
            } else {
                $error = 'Erreur lors de la sauvegarde de la configuration API.';
            }
        }
    }
}
?>

<div class="mj-app-container" style="max-width: 800px;">
    
    <?php include MANAJENI_CONNECTOR_PATH . 'admin/views/partials/header.php'; ?>

    <div style="margin-bottom: 40px; text-align:center;">
        <h2 style="font-family:'Outfit', sans-serif; font-size: 28px; font-weight: 800; color: var(--mj-slate-900); margin:0;">🔒 Sécurisation de l'accès</h2>
        <p style="color: var(--mj-slate-500); font-size:16px; margin-top:8px;">Configurez l'URL API Manajeni et la clé d'accès pour finaliser la jonction.</p>
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
                    <label>🌐 API URL</label>
                    <input type="url" name="api_url" class="mj-form-control" value="<?php echo esc_attr($current_api_url); ?>" required placeholder="https://api.manajeni.com">
                </div>

                <div class="mj-form-group">
                    <label>🏠 Site URL</label>
                    <input type="url" class="mj-form-control" value="<?php echo esc_attr(home_url()); ?>" readonly style="background: var(--mj-slate-50); opacity:0.8;">
                </div>
            </div>

            <div class="mj-form-group" style="margin-top:20px;">
                <label>💎 Clé API Primaire</label>
                <input type="password" name="api_key" class="mj-form-control" placeholder="<?php echo $masked_api_key ? esc_attr($masked_api_key) : 'Saisissez votre clé Manajeni'; ?>">
                <div style="margin-top:12px; background:var(--mj-primary-soft); padding:12px; border-radius:10px; border-left:4px solid var(--mj-primary);">
                    <p style="margin:0; font-size:13px; color:var(--mj-primary); font-weight:600;">
                        La clé API est chiffrée en stockage WordPress. Laissez le champ vide pour conserver la clé déjà enregistrée.
                    </p>
                </div>
            </div>
            
            <div style="margin-top: 40px; display: flex; gap: 20px;">
                <button type="submit" name="test_connection" class="mj-btn-secondary" style="flex: 1; justify-content: center; padding: 18px;">
                    Tester la connexion
                </button>
                <button type="submit" name="save_api_key" class="mj-btn-primary" style="flex: 1; justify-content: center; padding: 18px;">
                    Enregistrer
                </button>
                <a href="<?php echo admin_url('admin.php?page=manajeni-dashboard'); ?>" class="mj-btn-secondary" style="display:flex; align-items:center;">
                    Annuler
                </a>
            </div>
        </form>
    </div>
</div>
