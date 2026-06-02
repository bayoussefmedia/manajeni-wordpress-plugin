<?php
/**
 * Module Clients - MVC View
 */
if (!defined('ABSPATH')) exit;

// Variables injectées par Manajeni_Clients_Controller :
// $clients, $stats, $apps_handler, $message, $message_type
?>

<div class="mj-app-container">
    <div class="mj-app-title-bar">
        <div><h1><?php _e('👥 Clients', 'manajeni-connector'); ?></h1><p><?php _e('Gérez votre base clients et fiches de contact', 'manajeni-connector'); ?></p></div>
        <button class="mj-btn-primary" onclick="mjShowNewClientModal()"><?php _e('+ Nouveau client', 'manajeni-connector'); ?></button>
    </div>

    <?php 
    if (!empty($message)) $apps_handler->show_message($message, $message_type);
    $apps_handler->render_stats_cards($stats); 
    ?>

    <!-- Barre de recherche -->
    <div class="mj-search-container-box">
        <form method="get" style="display:flex; width:100%; gap:15px; align-items:center;">
            <input type="hidden" name="page" value="manajeni-clients">
            <div style="flex-grow:1; position:relative;">
                <span style="position:absolute; left:16px; top:50%; transform:translateY(-50%); opacity:0.4">🔍</span>
                <input type="text" name="search" placeholder="<?php _e('Rechercher un client...', 'manajeni-connector'); ?>" value="<?php echo isset($_GET['search']) ? esc_attr($_GET['search']) : ''; ?>" class="mj-form-control" style="padding-left:45px !important;">
            </div>
            <button type="submit" class="mj-btn-primary" style="padding: 12px 30px;"><?php _e('Rechercher', 'manajeni-connector'); ?></button>
        </form>
    </div>

    <div class="mj-table-wrapper">
        <table class="mj-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th><?php _e('NOM', 'manajeni-connector'); ?></th>
                    <th><?php _e('VILLE', 'manajeni-connector'); ?></th>
                    <th><?php _e('ICE', 'manajeni-connector'); ?></th>
                    <th><?php _e('TYPE', 'manajeni-connector'); ?></th>
                    <th><?php _e('ACTIONS', 'manajeni-connector'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clients as $c): ?>
                <?php $delete_url = wp_nonce_url(add_query_arg(['action' => 'delete', 'id' => $c['id']]), 'mj_delete_client_' . $c['id']); ?>
                <tr>
                    <td><?php echo $c['id']; ?></td>
                    <td>
                        <div style="font-weight:700; color:#1e293b;"><?php echo esc_html($c['nom']); ?></div>
                        <div style="font-size:12px; color:#64748b;"><?php echo esc_html($c['email']); ?></div>
                    </td>
                    <td><?php echo esc_html($c['ville']); ?></td>
                    <td><code style="background:var(--mj-slate-50); padding:4px 8px; border-radius:6px; font-size:12px; border:1px solid var(--mj-slate-100);"><?php echo esc_html($c['ice']); ?></code></td>
                    <td>
                        <span class="mj-badge <?php echo $c['type'] === 'premium' ? 'mj-badge-premium' : 'mj-badge-info'; ?>">
                            <?php echo $c['type'] === 'premium' ? '⭐ Premium' : '👤 Standard'; ?>
                        </span>
                    </td>
                    <td>
                        <div style="display:flex; gap:8px;">
                            <button class="mj-btn-icon" onclick='mjEditClient(<?php echo json_encode($c); ?>)' title="<?php _e('Modifier', 'manajeni-connector'); ?>">✏️</button>
                            <a href="<?php echo $delete_url; ?>" class="mj-btn-icon" title="<?php _e('Supprimer', 'manajeni-connector'); ?>" onclick="return confirm('<?php _e('Confirmer la suppression ?', 'manajeni-connector'); ?>')">🗑️</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php 
// Modale Nouveau Client
$apps_handler->render_modal_start('modal-new-client', __('Nouveau Client', 'manajeni-connector'));
?>
<form method="post" action="">
    <?php wp_nonce_field('mj_client_save', 'mj_nonce'); ?>
    <input type="hidden" name="client_id" id="mj_client_id">
    
    <div class="mj-form-group">
        <label><?php _e('Nom complet / Entreprise', 'manajeni-connector'); ?></label>
        <input type="text" name="nom" class="mj-form-control" required>
    </div>

    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
        <div class="mj-form-group">
            <label><?php _e('Email', 'manajeni-connector'); ?></label>
            <input type="email" name="email" class="mj-form-control" required>
        </div>
        <div class="mj-form-group">
            <label><?php _e('Téléphone', 'manajeni-connector'); ?></label>
            <input type="text" name="telephone" class="mj-form-control">
        </div>
    </div>

    <div class="mj-form-group">
        <label><?php _e('Adresse', 'manajeni-connector'); ?></label>
        <textarea name="adresse" class="mj-form-control" rows="3"></textarea>
    </div>

    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
        <div class="mj-form-group">
            <label><?php _e('Ville', 'manajeni-connector'); ?></label>
            <input type="text" name="ville" class="mj-form-control" required>
        </div>
        <div class="mj-form-group">
            <label><?php _e('ICE', 'manajeni-connector'); ?></label>
            <input type="text" name="ice" class="mj-form-control">
        </div>
    </div>

    <div class="mj-form-group">
        <label><?php _e('Type de client', 'manajeni-connector'); ?></label>
        <select name="type" class="mj-form-control">
            <option value="standard">Standard</option>
            <option value="premium">Premium</option>
        </select>
    </div>

    <div class="mj-modal-footer">
        <button type="button" class="mj-btn-secondary" onclick="mjCloseModal('modal-new-client')"><?php _e('Annuler', 'manajeni-connector'); ?></button>
        <button type="submit" name="add_client" class="mj-btn-primary"><?php _e('Enregistrer', 'manajeni-connector'); ?></button>
    </div>
</form>
<?php 
$apps_handler->render_modal_end();
?>

<script>
function mjShowNewClientModal() {
    mjResetClientForm();
    mjOpenModal('modal-new-client');
}

function mjEditClient(c) {
    document.getElementById('mj_client_id').value = c.id;
    document.querySelector('#modal-new-client h2').innerText = "✏️ <?php _e('Modifier Client', 'manajeni-connector'); ?>";
    
    document.querySelector('input[name="nom"]').value = c.nom;
    document.querySelector('input[name="email"]').value = c.email;
    document.querySelector('input[name="telephone"]').value = c.telephone || '';
    document.querySelector('textarea[name="adresse"]').value = c.adresse || '';
    document.querySelector('input[name="ville"]').value = c.ville;
    document.querySelector('input[name="ice"]').value = c.ice || '';
    document.querySelector('select[name="type"]').value = c.type;
    
    mjOpenModal('modal-new-client');
}

function mjResetClientForm() {
    // Reset ID and title
    const idField = document.getElementById('mj_client_id');
    if (idField) idField.value = '';
    
    const titleEl = document.querySelector('#modal-new-client h2');
    if (titleEl) titleEl.innerText = "<?php _e('Nouveau Client', 'manajeni-connector'); ?>";
    
    // Reset form fields
    const form = document.querySelector('#modal-new-client form');
    if (form) form.reset();
}
</script>