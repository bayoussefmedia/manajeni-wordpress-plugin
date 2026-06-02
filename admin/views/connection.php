<?php
/**
 * Page de connexion API.
 */

if (!current_user_can('manage_options')) {
    wp_die(esc_html__('Acces non autorise.', 'manajeni-connector'));
}

$error = '';
$success = '';
$db = new Manajeni_DB();
$current_api_url = get_option('manajeni_url', 'https://manajeni.com/api/external/v1');
$current_api_email = $db->get_api_email();
$masked_api_key = $db->get_masked_api_key();
$masked_api_secret = $db->get_masked_api_secret();

if (isset($_POST['test_connection']) && check_admin_referer('manajeni_api_connection_nonce')) {
    $api_url = esc_url_raw(wp_unslash($_POST['api_url'] ?? ''));
    $api_email = sanitize_email(wp_unslash($_POST['api_email'] ?? ''));
    $api_key = sanitize_text_field(wp_unslash($_POST['api_key'] ?? ''));
    $api_secret = sanitize_text_field(wp_unslash($_POST['api_secret'] ?? ''));

    if (empty($api_key)) {
        $api_key = (string) $db->get_api_key();
    }

    if (empty($api_secret)) {
        $api_secret = (string) $db->get_api_secret();
    }

    if (empty($api_email)) {
        $api_email = $current_api_email;
    }

    if (empty($api_url) || empty($api_key)) {
        $error = __('Veuillez renseigner l’URL API et la cle API pour tester la connexion.', 'manajeni-connector');
    } elseif (0 === strpos($api_key, 'mnj_') && (empty($api_secret) || empty($api_email))) {
        $error = __('Le mode legacy Manajeni requiert une cle API mnj_ avec secret API et email associe.', 'manajeni-connector');
    } else {
        $api_client = new Manajeni_API_Client($api_url, $api_key, $api_secret, $api_email);
        $result = $api_client->test_connection();

        if ($result['success']) {
            $success = __('Connexion API validee. Vous pouvez maintenant enregistrer la configuration.', 'manajeni-connector');
        } else {
            $error = __('Erreur API : ', 'manajeni-connector') . $result['message'];
        }
    }
}

if (isset($_POST['save_api_key']) && check_admin_referer('manajeni_api_connection_nonce')) {
    $api_url = esc_url_raw(wp_unslash($_POST['api_url'] ?? ''));
    $api_email = sanitize_email(wp_unslash($_POST['api_email'] ?? ''));
    $api_key = sanitize_text_field(wp_unslash($_POST['api_key'] ?? ''));
    $api_secret = sanitize_text_field(wp_unslash($_POST['api_secret'] ?? ''));

    if (empty($api_key)) {
        $api_key = (string) $db->get_api_key();
    }

    if (empty($api_secret)) {
        $api_secret = (string) $db->get_api_secret();
    }

    if (empty($api_email)) {
        $api_email = $current_api_email;
    }

    if (empty($api_url)) {
        $error = __('Veuillez saisir une URL API valide.', 'manajeni-connector');
    } elseif (empty($api_key)) {
        $error = __('Veuillez saisir une cle API valide.', 'manajeni-connector');
    } elseif (0 === strpos($api_key, 'mnj_') && (empty($api_secret) || empty($api_email))) {
        $error = __('Veuillez saisir le secret API Manajeni et l’email associe a la cle mnj_.', 'manajeni-connector');
    } else {
        $api_client = new Manajeni_API_Client($api_url, $api_key, $api_secret, $api_email);
        $result = $api_client->test_connection();

        if (!$result['success']) {
            $error = __('Erreur API : ', 'manajeni-connector') . $result['message'];
        } else {
            $session = new Manajeni_Session();
            $connection_date = current_time('mysql');

            if ($session->login($api_key, $api_url, $connection_date, $api_secret, $api_email)) {
                delete_option('manajeni_need_first_login');
                $success = __('Connexion API securisee etablie. Redirection vers le tableau de bord...', 'manajeni-connector');
                echo '<script>setTimeout(function(){ window.location.href = "' . esc_url(admin_url('admin.php?page=manajeni-dashboard')) . '"; }, 1500);</script>';
            } else {
                $error = __('Erreur lors de la sauvegarde de la configuration API.', 'manajeni-connector');
            }
        }
    }
}
?>

<div class="mj-app-container" style="max-width: 800px;">
    <?php include MANAJENI_CONNECTOR_PATH . 'admin/views/partials/header.php'; ?>

    <div style="margin-bottom: 40px; text-align:center;">
        <h2 style="font-family:'Outfit', sans-serif; font-size: 28px; font-weight: 800; color: var(--mj-slate-900); margin:0;">🔒 Securisation de l'acces</h2>
        <p style="color: var(--mj-slate-500); font-size:16px; margin-top:8px;">Configurez l'API externe Manajeni avec signature ou Bearer selon votre cle.</p>
    </div>

    <?php if ($error): ?>
        <div class="notice mj-notice notice-error">
            <p><strong><?php echo esc_html($error); ?></strong></p>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="notice mj-notice notice-success">
            <p><strong><?php echo esc_html($success); ?></strong></p>
        </div>
    <?php endif; ?>

    <div class="mj-table-wrapper" style="padding: 40px;">
        <form method="post">
            <?php wp_nonce_field('manajeni_api_connection_nonce'); ?>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:30px;">
                <div class="mj-form-group">
                    <label for="mj-api-url">🌐 API URL</label>
                    <input id="mj-api-url" type="url" name="api_url" class="mj-form-control" value="<?php echo esc_attr($current_api_url); ?>" required placeholder="https://manajeni.com/api/external/v1">
                </div>

                <div class="mj-form-group">
                    <label for="mj-site-url">🏠 Site URL</label>
                    <input id="mj-site-url" type="url" class="mj-form-control" value="<?php echo esc_attr(home_url()); ?>" readonly style="background: var(--mj-slate-50); opacity:0.8;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:30px; margin-top:20px;">
                <div class="mj-form-group">
                    <label for="mj-api-email">📧 Email API Manajeni</label>
                    <input id="mj-api-email" type="email" name="api_email" class="mj-form-control" value="<?php echo esc_attr($current_api_email); ?>" placeholder="compte@manajeni.com">
                </div>

                <div class="mj-form-group">
                    <label for="mj-api-key">💎 Cle API Manajeni</label>
                    <input id="mj-api-key" type="password" name="api_key" class="mj-form-control" placeholder="<?php echo esc_attr($masked_api_key ?: 'Saisissez votre cle mnj_ ou mnt_'); ?>" autocomplete="new-password">
                </div>
            </div>

            <div class="mj-form-group" style="margin-top:20px;">
                <label for="mj-api-secret">🛡️ Secret API Manajeni</label>
                <input id="mj-api-secret" type="password" name="api_secret" class="mj-form-control" placeholder="<?php echo esc_attr($masked_api_secret ?: 'Saisissez votre secret mns_'); ?>" autocomplete="new-password">
                <div style="margin-top:12px; background:var(--mj-primary-soft); padding:12px; border-radius:10px; border-left:4px solid var(--mj-primary);">
                    <p style="margin:0; font-size:13px; color:var(--mj-primary); font-weight:600;">
                        Les champs sensibles sont chiffres en stockage WordPress et restent masques apres sauvegarde. Laissez-les vides pour conserver les valeurs deja enregistrees.
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
                <a href="<?php echo esc_url(admin_url('admin.php?page=manajeni-dashboard')); ?>" class="mj-btn-secondary" style="display:flex; align-items:center;">
                    Annuler
                </a>
            </div>
        </form>
    </div>
</div>
