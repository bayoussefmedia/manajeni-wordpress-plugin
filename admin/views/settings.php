<?php
/**
 * Page Paramètres Manajeni Connector - Hub de Contrôle Fantastic
 * Fichier: admin/views/settings.php
 */

if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    wp_die('Accès non autorisé.');
}

$logs = get_option('manajeni_connector_logs', []);
$xml_handler = new Manajeni_XML_Handler();
$db = new Manajeni_DB();
$api_url = get_option('manajeni_url', '');
$masked_api_key = $db->get_masked_api_key();

$message = '';
$message_type = '';

if (isset($_POST['clear_logs']) && check_admin_referer('manajeni_settings_nonce')) {
    update_option('manajeni_connector_logs', []);
    $message = 'Historique des logs réinitialisé.';
    $message_type = 'success';
    $logs = [];
}

if (isset($_POST['reset_plugin']) && check_admin_referer('manajeni_settings_nonce')) {
    $options_to_delete = [
        'manajeni_url', 'manajeni_connector_logs', 'manajeni_connected',
        'manajeni_user_session', 'manajeni_api_key_crypted', 'manajeni_api_key_masked',
        'manajeni_last_connection', 'manajeni_last_activity'
    ];
    foreach($options_to_delete as $opt) delete_option($opt);
    $xml_handler->clear_api_key_in_xml(true);
    update_option('manajeni_need_first_login', true, false);
    $message = 'Le connecteur a été réinitialisé. Redirection...';
    $message_type = 'success';
    echo '<script>setTimeout(function(){ window.location.href = "' . admin_url('admin.php?page=manajeni-first-login') . '"; }, 2000);</script>';
}
?>

<div class="mj-app-container">
    
    <?php include MANAJENI_CONNECTOR_PATH . 'admin/views/partials/header.php'; ?>

    <div style="margin-bottom: 40px;">
        <h2 style="font-family:'Outfit', sans-serif; font-size: 28px; font-weight: 800; color: var(--mj-slate-900); margin:0;">⚙️ Contrôle Central</h2>
        <p style="color: var(--mj-slate-500); font-size:16px; margin-top:8px;">Gérez l'infrastructure et la sécurité de votre connecteur.</p>
    </div>

    <?php if ($message): ?>
        <div class="notice mj-notice notice-<?php echo $message_type; ?>">
            <p><strong><?php echo esc_html($message); ?></strong></p>
        </div>
    <?php endif; ?>
    
    <div style="display:grid; grid-template-columns: 350px 1fr; gap: 30px; align-items: start;">
        
        <!-- Colonne Gauche : Config & Status -->
        <div style="display:flex; flex-direction:column; gap:30px;">
            
            <div class="mj-table-wrapper" style="padding:30px;">
                <h3 style="font-family:'Outfit', sans-serif; font-size:18px; font-weight:800; margin-bottom:20px;">⚡ Mode Opérationnel</h3>
                <div style="margin-bottom: 18px;">
                    <?php if (manajeni_connector_is_dev_mode()): ?>
                        <div style="background: var(--mj-warning-soft); color: var(--mj-warning); padding: 15px; border-radius: 12px; font-weight: 800; text-align: center; border: 1px solid rgba(245, 158, 11, 0.2);">
                            🧪 MODE DEVELOPPEMENT
                        </div>
                    <?php else: ?>
                        <div style="background: var(--mj-success-soft); color: var(--mj-success); padding: 15px; border-radius: 12px; font-weight: 800; text-align: center; border: 1px solid rgba(16, 185, 129, 0.2);">
                            🌐 MODE API REEL
                        </div>
                    <?php endif; ?>
                </div>
                <p style="font-size:13px; color:var(--mj-slate-500); margin:0;">
                    Le mode est pilote par la constante <code>MANAJENI_CONNECTOR_DEV_MODE</code>.
                </p>
            </div>

            <div class="mj-table-wrapper" style="padding:30px;">
                <h3 style="font-family:'Outfit', sans-serif; font-size:18px; font-weight:800; margin-bottom:20px;">🔐 Connexion API</h3>
                <div style="display:grid; gap:12px;">
                    <div style="display:flex; justify-content:space-between; font-size:13px; color:var(--mj-slate-500)">
                        <span>API URL</span>
                        <strong style="color:var(--mj-slate-900)"><?php echo esc_html($api_url ?: 'Non configuree'); ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:13px; color:var(--mj-slate-500)">
                        <span>API Key</span>
                        <strong style="color:var(--mj-slate-900)"><?php echo esc_html($masked_api_key ?: 'Non configuree'); ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:13px; color:var(--mj-slate-500)">
                        <span>Site URL</span>
                        <strong style="color:var(--mj-slate-900)"><?php echo esc_html(home_url()); ?></strong>
                    </div>
                </div>
            </div>
            
            <div class="mj-table-wrapper" style="padding:30px;">
                <h3 style="font-family:'Outfit', sans-serif; font-size:18px; font-weight:800; margin-bottom:20px;">ℹ️ Diagnostics Info</h3>
                <div style="display:grid; gap:12px;">
                    <div style="display:flex; justify-content:space-between; font-size:13px; color:var(--mj-slate-500)">
                        <span>Version Core</span>
                        <strong style="color:var(--mj-slate-900)"><?php echo MANAJENI_CONNECTOR_VERSION; ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:13px; color:var(--mj-slate-500)">
                        <span>Moteur PHP</span>
                        <strong style="color:var(--mj-slate-900)"><?php echo phpversion(); ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:13px; color:var(--mj-slate-500)">
                        <span>WordPress Instance</span>
                        <strong style="color:var(--mj-slate-900)"><?php echo get_bloginfo('version'); ?></strong>
                    </div>
                </div>
            </div>
            
            <div class="mj-table-wrapper" style="padding:30px; border: 2px solid var(--mj-error-soft);">
                <h3 style="font-family:'Outfit', sans-serif; font-size:18px; font-weight:800; margin-bottom:10px; color:var(--mj-error)">🚨 Zone Critique</h3>
                <p style="font-size: 13px; color: var(--mj-slate-500); margin-bottom:20px;">Action irréversible. Toutes les données locales seront purgées.</p>
                <form method="post" onsubmit="return confirm('Attention : Cette action va déconnecter le site et effacer vos clés API. Continuer ?');">
                    <?php wp_nonce_field('manajeni_settings_nonce'); ?>
                    <button type="submit" name="reset_plugin" class="mj-btn-secondary" style="width: 100%; color:var(--mj-error); border-color:var(--mj-error-soft); justify-content:center;">
                        Réinitialiser le Connecteur 🗑️
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Colonne Droite : Logs Historique -->
        <div class="mj-table-wrapper">
            <div style="padding: 25px 30px; border-bottom: 1px solid var(--mj-slate-100); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="font-family:'Outfit', sans-serif; font-size:18px; font-weight:800; margin:0;">📜 Flux d'Activité</h3>
                <form method="post">
                    <?php wp_nonce_field('manajeni_settings_nonce'); ?>
                    <button type="submit" name="clear_logs" class="mj-btn-secondary" style="padding: 8px 16px; font-size: 12px; font-weight:800;">
                        EFFACER L'HISTORIQUE
                    </button>
                </form>
            </div>
            
            <div style="max-height: 800px; overflow-y: auto;">
                <?php if (empty($logs)): ?>
                    <div style="padding: 80px 0; text-align: center; color: var(--mj-slate-300);">
                        <div style="font-size: 50px; margin-bottom: 15px;">📭</div>
                        <div style="font-weight:600;">Aucun évènement enregistré</div>
                    </div>
                <?php else: ?>
                    <table class="mj-table">
                        <thead>
                            <tr>
                                <th>Date & Heure</th>
                                <th>Opération</th>
                                <th>Statut</th>
                                <th>Description technique</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_reverse($logs) as $log): ?>
                            <tr>
                                <td style="font-size:12px; color:var(--mj-slate-400)"><?php echo esc_html($log['date']); ?></td>
                                <td><strong style="color:var(--mj-slate-700)"><?php echo strtoupper(str_replace('_', ' ', esc_html($log['action']))); ?></strong></td>
                                <td>
                                    <?php if ($log['status'] === 'success'): ?>
                                        <span class="mj-badge" style="background:var(--mj-success-soft); color:var(--mj-success)">SUCCESS</span>
                                    <?php else: ?>
                                        <span class="mj-badge" style="background:var(--mj-error-soft); color:var(--mj-error)">FAILURE</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:12px; color:var(--mj-slate-500)"><?php echo esc_html($log['details']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
