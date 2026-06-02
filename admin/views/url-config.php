<?php
/**
 * Page de configuration de l'URL Manajeni
 * Cette page s'affiche après la vérification du compte
 */

if (!defined('ABSPATH')) {
    exit;
}

// Vérifier les droits
if (!current_user_can('manage_options')) {
    wp_die('Accès non autorisé.');
}

$error = '';
$success = '';
$xml_handler = new Manajeni_XML_Handler();

// Vérifier si l'URL est déjà déclarée
$current_url = $xml_handler->get_url();

// Traitement du formulaire
if (isset($_POST['save_manajeni_url']) && check_admin_referer('manajeni_url_config_nonce')) {
    $url = sanitize_text_field($_POST['manajeni_url']);
    
    if (empty($url)) {
        $error = 'Veuillez entrer une URL valide.';
    } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
        $error = 'URL invalide. Format attendu : https://api.manajeni.com';
    } else {
        if ($xml_handler->declare_url($url)) {
            update_option('manajeni_url', $url);
            $success = 'URL déclarée avec succès ! Redirection...';
            manajeni_connector_add_log('url_config', 'success', 'URL déclarée: ' . $url);
            
            // Rediriger vers la page de connexion API après 2 secondes
            echo '<meta http-equiv="refresh" content="2;url=' . admin_url('admin.php?page=manajeni-api-connection') . '">';
        } else {
            $error = 'Erreur lors de la sauvegarde de l\'URL.';
            manajeni_connector_add_log('url_config', 'error', 'Erreur sauvegarde URL');
        }
    }
}
?>

<div class="wrap">
    <h1>🌐 Configuration de l'URL Manajeni</h1>
    
    <?php if ($error): ?>
        <div class="notice notice-error is-dismissible">
            <p>❌ <?php echo esc_html($error); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="notice notice-success is-dismissible">
            <p>✅ <?php echo esc_html($success); ?></p>
        </div>
    <?php endif; ?>
    
    <div style="background: white; padding: 30px; border-radius: 8px; max-width: 600px; margin-top: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        
        <?php if ($current_url && $current_url !== 'null'): ?>
            <div class="notice notice-info">
                <p>📍 URL actuellement configurée : <strong><?php echo esc_html($current_url); ?></strong></p>
                <p>Vous pouvez modifier l'URL ci-dessous si nécessaire.</p>
            </div>
        <?php endif; ?>
        
        <form method="post">
            <?php wp_nonce_field('manajeni_url_config_nonce'); ?>
            
            <p>
                <label for="manajeni_url"><strong>URL de l'API Manajeni</strong></label><br>
                <input type="url" name="manajeni_url" id="manajeni_url" 
                       value="<?php echo esc_attr($current_url && $current_url !== 'null' ? $current_url : ''); ?>" 
                       class="regular-text" 
                       placeholder="https://api.manajeni.com"
                       required>
                <p class="description">Exemple : https://api.manajeni.com ou https://votre-domaine-manajeni.com</p>
            </p>
            
            <p>
                <button type="submit" name="save_manajeni_url" class="button button-primary">
                    ✅ Déclarer l'URL
                </button>
                <a href="<?php echo admin_url('admin.php?page=manajeni-api-connection'); ?>" class="button">
                    ⏭️ Passer (plus tard)
                </a>
            </p>
        </form>
    </div>
    
    <div style="margin-top: 20px; background: #f0f6ff; padding: 15px; border-radius: 8px; max-width: 600px;">
        <h3>ℹ️ Informations</h3>
        <p>L'URL déclarée sera stockée dans :</p>
        <ul>
            <li>📁 <strong>Fichier XML</strong> : <code>/wp-content/uploads/manajeni/config.xml</code></li>
            <li>📦 <strong>Base de données</strong> : Table <code>wp_manajeni_connection</code></li>
        </ul>
    </div>
</div>

<style>
    .regular-text { width: 100%; max-width: 400px; }
    .button-primary { margin-right: 10px; }
</style>