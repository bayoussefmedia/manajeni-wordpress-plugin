<?php
/**
 * Module Fournisseurs - MVC View
 */
if (!defined('ABSPATH')) exit;

// Variables injectées par Manajeni_Fournisseurs_Controller :
// $fournisseurs, $stats, $apps_handler, $message, $message_type
?>

<div class="mj-app-container">
    <div class="mj-app-title-bar">
        <div><h1><?php _e('🏭 Fournisseurs', 'manajeni-connector'); ?></h1><p><?php _e('Gérez votre catalogue de fournisseurs et partenaires', 'manajeni-connector'); ?></p></div>
        <button class="mj-btn-primary" onclick="mjResetFournisseurForm(); mjOpenModal('modal-new-fournisseur')"><?php _e('+ Nouveau fournisseur', 'manajeni-connector'); ?></button>
    </div>

    <?php 
    if (!empty($message)) $apps_handler->show_message($message, $message_type);
    $apps_handler->render_stats_cards($stats); 
    ?>

    <div class="mj-table-wrapper">
        <table class="mj-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th><?php _e('FOURNISSEUR', 'manajeni-connector'); ?></th>
                    <th><?php _e('CATÉGORIE', 'manajeni-connector'); ?></th>
                    <th><?php _e('VILLE', 'manajeni-connector'); ?></th>
                    <th><?php _e('ICE', 'manajeni-connector'); ?></th>
                    <th><?php _e('ACTIONS', 'manajeni-connector'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($fournisseurs as $f): ?>
                <?php $delete_url = wp_nonce_url(add_query_arg(['action' => 'delete', 'id' => $f['id']]), 'mj_delete_fournisseur_' . $f['id']); ?>
                <tr>
                    <td><?php echo $f['id']; ?></td>
                    <td><strong><?php echo esc_html($f['nom']); ?></strong></td>
                    <td><span class="mj-badge"><?php echo esc_html($f['categorie']); ?></span></td>
                    <td><?php echo esc_html($f['ville']); ?></td>
                    <td><code><?php echo esc_html($f['ice']); ?></code></td>
                    <td>
                        <div style="display:flex; gap:8px;">
                            <button class="mj-btn-icon" onclick='mjEditFournisseur(<?php echo json_encode($f); ?>)' title="<?php _e('Modifier', 'manajeni-connector'); ?>">✏️</button>
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
// Modale Nouveau Fournisseur
$apps_handler->render_modal_start('modal-new-fournisseur', __('Nouveau Fournisseur', 'manajeni-connector'));
?>
<form method="post" action="">
    <?php wp_nonce_field('mj_fournisseur_save', 'mj_nonce'); ?>
    <input type="hidden" name="fournisseur_id" id="mj_fournisseur_id">
    
    <div class="mj-form-group">
        <label><?php _e('Raison sociale', 'manajeni-connector'); ?></label>
        <input type="text" name="nom" class="mj-form-control" required>
    </div>

    <div class="mj-form-group">
        <label><?php _e('Catégorie', 'manajeni-connector'); ?></label>
        <select name="categorie" class="mj-form-control">
            <option value="Marchandises">Achat de marchandises</option>
            <option value="Logistique">Logistique / Transport</option>
            <option value="Services">Services aux entreprises</option>
            <option value="Autre">Autre</option>
        </select>
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

    <div class="mj-modal-footer">
        <button type="button" class="mj-btn-secondary" onclick="mjCloseModal('modal-new-fournisseur')"><?php _e('Annuler', 'manajeni-connector'); ?></button>
        <button type="submit" name="add_fournisseur" class="mj-btn-primary"><?php _e('Enregistrer', 'manajeni-connector'); ?></button>
    </div>
</form>
<?php 
$apps_handler->render_modal_end();
?>

<script>
function mjEditFournisseur(f) {
    document.getElementById('mj_fournisseur_id').value = f.id;
    document.querySelector('#modal-new-fournisseur h2').innerText = "✏️ <?php _e('Modifier Fournisseur', 'manajeni-connector'); ?>";
    
    document.querySelector('input[name="nom"]').value = f.nom;
    document.querySelector('select[name="categorie"]').value = f.categorie;
    document.querySelector('input[name="ville"]').value = f.ville;
    document.querySelector('input[name="ice"]').value = f.ice || '';
    
    mjOpenModal('modal-new-fournisseur');
}

function mjResetFournisseurForm() {
    document.getElementById('mj_fournisseur_id').value = '';
    document.querySelector('#modal-new-fournisseur h2').innerText = "<?php _e('Nouveau Fournisseur', 'manajeni-connector'); ?>";
    document.querySelector('#modal-new-fournisseur form').reset();
}
</script>