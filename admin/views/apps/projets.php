<?php
/**
 * Module Projets - MVC View
 */
if (!defined('ABSPATH')) exit;

// Variables injected by Manajeni_Projets_Controller:
// $message, $message_type, $projets, $clients, $stats
?>

<div class="mj-app-container">
    <div class="mj-app-title-bar">
        <div>
            <h1>🎯 Projets</h1>
            <p>Pilotage structuré des projets - Budget, avancement, rentabilité</p>
        </div>
        <button class="mj-btn-primary" onclick="document.getElementById('addProjetModal').style.display='flex'">
            + Nouveau projet
        </button>
    </div>
    
    <?php $apps_handler->show_message($message, $message_type); ?>
    <?php $apps_handler->render_stats_cards($stats); ?>
    
    <div class="mj-table-wrapper">
        <table class="mj-table">
            <thead>
                <tr>
                    <th>Projet</th>
                    <th>Client</th>
                    <th>Budget</th>
                    <th>Dépenses</th>
                    <th>Marge</th>
                    <th>Avancement</th>
                    <th>Statut</th>
                    <th>Dates</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($projets as $p): 
                    $marge = $p['budget'] - $p['depenses'];
                    $pourcentage_marge = $p['budget'] > 0 ? round(($marge / $p['budget']) * 100, 1) : 0;
                ?>
                <tr>
                    <td><strong><?php echo esc_html($p['nom']); ?></strong></td>
                    <td><?php echo esc_html($p['client_nom']); ?></td>
                    <td><?php echo number_format($p['budget'], 0); ?> DH</td>
                    <td><?php echo number_format($p['depenses'], 0); ?> DH</td>
                    <td><span style="color: <?php echo $marge >= 0 ? '#10b981' : '#ef4444'; ?>; font-weight:600;">
                        <?php echo number_format($marge, 0); ?> DH (<?php echo $pourcentage_marge; ?>%)
                    </span></td>
                    <td>
                        <div style="background:#e2e8f0; border-radius:20px; overflow:hidden; width:100px;">
                            <div style="background:<?php echo $p['avancement']>=70?'#10b981':($p['avancement']>=30?'#f59e0b':'#ef4444'); ?>; width:<?php echo $p['avancement']; ?>%; padding:4px 0; border-radius:20px; text-align:center; color:white; font-size:10px;">
                                <?php echo $p['avancement']; ?>%
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="mj-badge" style="background:<?php echo $p['statut']==='en_cours'?'#fff8e5':($p['statut']==='termine'?'#e6f6e8':'#f1f5f9'); ?>">
                            <?php echo $p['statut']==='en_cours'?'🔄 En cours':($p['statut']==='termine'?'✅ Terminé':'📋 Planifié'); ?>
                        </span>
                    </td>
                    <td style="font-size:12px;"><?php echo $p['date_debut']; ?> → <?php echo $p['date_fin']; ?></td>
                    <td>
                        <?php $delete_url = wp_nonce_url(add_query_arg(['action' => 'delete', 'id' => $p['id']]), 'mj_delete_projet_' . $p['id']); ?>
                        <button class="mj-btn-icon" onclick="alert('Voir détails projet #<?php echo $p['id']; ?>')">👁️</button>
                        <button class="mj-btn-icon" onclick='mjEditProjet(<?php echo json_encode($p); ?>)' title="Modifier">✏️</button>
                        <a href="<?php echo $delete_url; ?>" class="mj-btn-icon" title="Supprimer" onclick="return confirm('Confirmer la suppression ?')">🗑️</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Ajout Projet -->
<div id="addProjetModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:10000; align-items:center; justify-content:center;">
    <div style="background:white; border-radius:24px; max-width:500px; width:90%; padding:25px;">
        <h2>➕ Nouveau projet</h2>
        <form method="post">
            <?php wp_nonce_field('manajeni_projets_action'); ?>
            <input type="hidden" name="projet_id" id="mj_projet_id">
            <div class="mj-form-group">
                <label class="mj-label">Nom du projet *</label>
                <input type="text" name="nom" class="mj-input" style="width:100%;" required>
            </div>
            <div class="mj-form-group">
                <label class="mj-label">Client</label>
                <select name="client_id" class="mj-input" style="width:100%;">
                    <?php foreach ($clients as $client): ?>
                        <option value="<?php echo $client['id']; ?>"><?php echo esc_html($client['nom']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mj-form-group">
                <label class="mj-label">Budget (DH)</label>
                <input type="number" name="budget" step="0.01" class="mj-input" style="width:100%;">
            </div>
            <div class="mj-form-group">
                <label class="mj-label">Date de début</label>
                <input type="date" name="date_debut" class="mj-input" style="width:100%;">
            </div>
            <div class="mj-form-group">
                <label class="mj-label">Date de fin prévue</label>
                <input type="date" name="date_fin" class="mj-input" style="width:100%;">
            </div>
            <div style="display:flex; gap:10px; margin-top:25px;">
                <button type="submit" name="create_projet" class="mj-btn">Enregistrer</button>
                <button type="button" class="mj-btn-secondary" onclick="document.getElementById('addProjetModal').style.display='none'">Annuler</button>
            </div>
        </form>
    </div>
</div>

<script>
function mjEditProjet(p) {
    document.getElementById('mj_projet_id').value = p.id;
    document.querySelector('#addProjetModal h2').innerText = "✏️ Modifier Projet";
    
    document.querySelector('input[name="nom"]').value = p.nom;
    document.querySelector('select[name="client_id"]').value = p.client_id;
    document.querySelector('input[name="budget"]').value = p.budget;
    document.querySelector('input[name="date_debut"]').value = p.date_debut || '';
    document.querySelector('input[name="date_fin"]').value = p.date_fin || '';
    
    document.getElementById('addProjetModal').style.display = 'flex';
}

function mjResetProjetForm() {
    document.getElementById('mj_projet_id').value = '';
    document.querySelector('#addProjetModal h2').innerText = "➕ Nouveau projet";
    document.querySelector('#addProjetModal form').reset();
}

document.getElementById('addProjetModal')?.addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
</script>