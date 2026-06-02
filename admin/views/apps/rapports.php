<?php
/**
 * Module Rapports - MVC View
 */
if (!defined('ABSPATH')) exit;

// Variables injectées par Manajeni_Rapports_Controller :
// $rapports, $mois, $ca_total, $depenses_total, $benefice_total, $stats, $apps_handler
?>

<div class="mj-app-container">
    <div class="mj-app-title-bar">
        <div><h1>📈 Rapports</h1><p>Analyses performances entreprise</p></div>
        <button class="mj-btn-primary" onclick="alert('Exporter PDF')">📄 Exporter</button>
    </div>
    
    <div class="mj-stats-grid">
        <div class="mj-stat-card" style="border-left: 5px solid #3b82f6 !important;">
            <div class="mj-stat-icon-wrapper" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%) !important;">💰</div>
            <div class="mj-stat-info">
                <div class="mj-stat-value"><?php echo number_format($ca_total, 0, ',', ' '); ?> DH</div>
                <div class="mj-stat-label">Chiffre d'Affaires</div>
            </div>
        </div>
        <div class="mj-stat-card" style="border-left: 5px solid #ef4444 !important;">
            <div class="mj-stat-icon-wrapper" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%) !important;">💸</div>
            <div class="mj-stat-info">
                <div class="mj-stat-value"><?php echo number_format($depenses_total, 0, ',', ' '); ?> DH</div>
                <div class="mj-stat-label">Dépenses</div>
            </div>
        </div>
        <div class="mj-stat-card" style="border-left: 5px solid #10b981 !important;">
            <div class="mj-stat-icon-wrapper" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;">💎</div>
            <div class="mj-stat-info">
                <div class="mj-stat-value"><?php echo number_format($benefice_total, 0, ',', ' '); ?> DH</div>
                <div class="mj-stat-label">Bénéfice</div>
            </div>
        </div>
    </div>
    
    <div class="mj-card"><h2>📊 Rentabilité par projet</h2>
        <table class="mj-table"><thead><tr><th>Projet</th><th>Revenus</th><th>Dépenses</th><th>Marge</th><th>Rentabilité</th></tr></thead>
        <tbody><?php foreach ($rapports['rentabilite_projets'] as $p): $renta = $p['revenus']>0 ? round(($p['marge']/$p['revenus'])*100,1) : 0; ?>
        <tr><td><strong><?php echo $p['projet']; ?></strong></td><td><?php echo number_format($p['revenus'],0); ?> DH</td><td><?php echo number_format($p['depenses'],0); ?> DH</td><td><?php echo number_format($p['marge'],0); ?> DH</td><td><span class="mj-badge" style="background:<?php echo $renta>0?'#e6f6e8':'#fef2f2'; ?>"><?php echo $renta; ?>%</span></td></tr>
        <?php endforeach; ?></tbody></table>
    </div>
</div>