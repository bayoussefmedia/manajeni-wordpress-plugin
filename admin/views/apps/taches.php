<?php
/**
 * Module Tâches - MVC View
 */
if (!defined('ABSPATH')) exit;

// Variables injectées par Manajeni_Taches_Controller :
// $taches, $stats, $apps_handler
?>

<div class="mj-app-container">
    <div class="mj-app-title-bar">
        <div><h1><?php _e('✅ Tâches', 'manajeni-connector'); ?></h1><p><?php _e('Organisation et suivi du travail quotidien', 'manajeni-connector'); ?></p></div>
        <button class="mj-btn-primary" onclick="mjResetTacheForm(); mjOpenModal('modal-new-tache')"><?php _e('+ Nouvelle tâche', 'manajeni-connector'); ?></button>
    </div>
    
    <?php 
    if (!empty($message)) $apps_handler->show_message($message, $message_type);
    $apps_handler->render_stats_cards($stats); 
    ?>
    
    <div class="mj-table-wrapper">
        <table class="mj-table">
            <thead>
                <tr>
                    <th><?php _e('TÂCHE', 'manajeni-connector'); ?></th>
                    <th><?php _e('PRIORITÉ', 'manajeni-connector'); ?></th>
                    <th><?php _e('ÉCHÉANCE', 'manajeni-connector'); ?></th>
                    <th><?php _e('STATUT', 'manajeni-connector'); ?></th>
                    <th><?php _e('ACTIONS', 'manajeni-connector'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($taches as $t): ?>
                <?php $delete_url = wp_nonce_url(add_query_arg(['action' => 'delete', 'id' => $t['id']]), 'mj_delete_tache_' . $t['id']); ?>
                <tr>
                    <td><strong><?php echo esc_html($t['titre']); ?></strong></td>
                    <td><span class="mj-badge" style="background:<?php echo $t['priorite']==='urgente'?'#fef2f2':($t['priorite']==='haute'?'#fff8e5':'#f1f5f9'); ?>"><?php echo esc_html($t['priorite']); ?></span></td>
                    <td><?php echo esc_html($t['echeance']); ?></td>
                    <td><span class="mj-badge" style="background:<?php echo $t['statut']==='termine'?'#e6f6e8':'#fff8e5'; ?>"><?php echo esc_html($t['statut']); ?></span></td>
                    <td>
                        <div style="display:flex; gap:8px;">
                            <button class="mj-btn-icon" onclick='mjEditTache(<?php echo json_encode($t); ?>)' title="<?php _e('Modifier', 'manajeni-connector'); ?>">✏️</button>
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
$apps_handler->render_modal_start('modal-new-tache', __('Nouvelle Tâche', 'manajeni-connector'));
?>
<form method="post" action="">
    <?php wp_nonce_field('mj_tache_save', 'mj_nonce'); ?>
    <input type="hidden" name="tache_id" id="mj_tache_id">
    
    <div class="mj-form-group">
        <label><?php _e('Titre de la tâche', 'manajeni-connector'); ?></label>
        <input type="text" name="titre" class="mj-form-control" required>
    </div>

    <div class="mj-form-group">
        <label><?php _e('Priorité', 'manajeni-connector'); ?></label>
        <select name="priorite" class="mj-form-control">
            <option value="normale"><?php _e('Normale', 'manajeni-connector'); ?></option>
            <option value="haute"><?php _e('Haute', 'manajeni-connector'); ?></option>
            <option value="urgente"><?php _e('Urgente', 'manajeni-connector'); ?></option>
        </select>
    </div>

    <div class="mj-form-group">
        <label><?php _e('Date d\'échéance', 'manajeni-connector'); ?></label>
        <input type="date" name="echeance" class="mj-form-control" required>
    </div>

    <div class="mj-modal-footer">
        <button type="button" class="mj-btn-secondary" onclick="mjCloseModal('modal-new-tache')"><?php _e('Annuler', 'manajeni-connector'); ?></button>
        <button type="submit" name="add_tache" class="mj-btn-primary"><?php _e('Enregistrer', 'manajeni-connector'); ?></button>
    </div>
</form>
<?php 
$apps_handler->render_modal_end();
?>

<script>
function mjEditTache(t) {
    document.getElementById('mj_tache_id').value = t.id;
    document.querySelector('#modal-new-tache h2').innerText = "✏️ <?php _e('Modifier Tâche', 'manajeni-connector'); ?>";
    
    document.querySelector('input[name="titre"]').value = t.titre;
    document.querySelector('select[name="priorite"]').value = t.priorite;
    document.querySelector('input[name="echeance"]').value = t.echeance || '';
    
    mjOpenModal('modal-new-tache');
}

function mjResetTacheForm() {
    document.getElementById('mj_tache_id').value = '';
    document.querySelector('#modal-new-tache h2').innerText = "<?php _e('Nouvelle Tâche', 'manajeni-connector'); ?>";
    document.querySelector('#modal-new-tache form').reset();
}
</script>