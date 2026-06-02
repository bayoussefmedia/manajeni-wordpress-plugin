<?php
/**
 * Class Manajeni_Fake_API_Client
 * Client API simulé avec persistance via WP Options
 */

if (!defined('ABSPATH')) {
    exit;
}

class Manajeni_Fake_API_Client {
    
    private $option_key = 'manajeni_mock_data';
    
    public function __construct() {
        // Initialiser les données si elles n'existent pas
        if (!get_option($this->option_key)) {
            $this->reset_data();
        }
    }

    private function get_all_data() {
        return get_option($this->option_key, []);
    }

    private function update_all_data($data) {
        update_option($this->option_key, $data);
    }

    public function reset_data() {
        $initial_data = [
            'clients' => [
                ['id' => 1, 'nom' => 'SARL ATLAS', 'email' => 'contact@atlas.ma', 'telephone' => '0522123456', 'adresse' => '12 Bd Mohammed V', 'ville' => 'Casablanca', 'ice' => '123456789012345', 'type' => 'premium', 'chiffre_affaire' => 125000, 'statut' => 'actif'],
                ['id' => 2, 'nom' => 'GROUPE MEDITECH', 'email' => 'admin@meditech.com', 'telephone' => '0537789012', 'adresse' => '34 Rue des Oliviers', 'ville' => 'Rabat', 'ice' => '234567890123456', 'type' => 'premium', 'chiffre_affaire' => 89000, 'statut' => 'actif'],
            ],
            'devis' => [
                [
                    'id' => 1, 'reference' => 'DEV-2024-001', 'statut' => 'accepté', 'date' => '2024-01-15',
                    'client' => ['nom' => 'SARL ATLAS', 'email' => 'contact@atlas.ma', 'ice' => '123456789012345', 'ville' => 'Casablanca'],
                    'montant_ht' => 15000, 'tva' => 20, 'montant_ttc' => 18000,
                    'lignes' => [
                        ['description' => 'Consulting stratégique - Phase analyse', 'quantite' => 5, 'prix_unitaire' => 1500, 'tva' => 20, 'total_ht' => 7500],
                        ['description' => 'Livrables et rapport final', 'quantite' => 1, 'prix_unitaire' => 7500, 'tva' => 20, 'total_ht' => 7500],
                    ],
                ]
            ],
            'factures' => [
                 [
                    'id' => 1, 'reference' => 'FAC-2024-001', 'statut' => 'payée',
                    'date' => '2024-02-01', 'date_echeance' => '2024-03-01',
                    'client' => ['nom' => 'SARL ATLAS', 'email' => 'contact@atlas.ma', 'ice' => '123456789012345', 'ville' => 'Casablanca'],
                    'montant_ht' => 15000, 'tva' => 20, 'montant_ttc' => 18000,
                    'lignes' => [
                        ['description' => 'Consulting stratégique - Phase analyse', 'quantite' => 5, 'prix_unitaire' => 1500, 'tva' => 20, 'total_ht' => 7500],
                    ],
                ]
            ],
            'paiements' => [],
            'charges' => [],
            'projets' => [],
            'taches' => [],
            'catalogue' => [],
            'orders' => [],
            'fournisseurs' => [],
            'rendez_vous' => [],
        ];
        $this->update_all_data($initial_data);
    }

    // ==================== GENERIC CRUD ====================

    private function get_collection($key) {
        $data = $this->get_all_data();
        return isset($data[$key]) ? $data[$key] : [];
    }

    private function save_item($key, $item) {
        $data = $this->get_all_data();
        if (!isset($data[$key])) $data[$key] = [];
        
        if (isset($item['id']) && $item['id'] > 0) {
            foreach ($data[$key] as &$existing) {
                if ($existing['id'] == $item['id']) {
                    $existing = array_merge($existing, $item);
                    $this->update_all_data($data);
                    return true;
                }
            }
        }
        
        $item['id'] = time() . rand(10, 99);
        $data[$key][] = $item;
        $this->update_all_data($data);
        return $item['id'];
    }

    private function delete_item($key, $id) {
        $data = $this->get_all_data();
        if (!isset($data[$key])) return false;
        $data[$key] = array_filter($data[$key], fn($i) => $i['id'] != $id);
        $this->update_all_data($data);
        return true;
    }

    // ==================== MAPPING ====================

    public function get_clients() { return $this->get_collection('clients'); }
    public function create_client($data) { return ['success' => true, 'id' => $this->save_item('clients', $data)]; }
    public function update_client($data) { return ['success' => true, 'id' => $this->save_item('clients', $data)]; }
    public function delete_client($id) { return ['success' => $this->delete_item('clients', $id)]; }

    public function get_devis() { return $this->get_collection('devis'); }
    public function create_devis($data) { return ['success' => true, 'id' => $this->save_item('devis', $data)]; }
    public function update_devis($data) { return ['success' => true, 'id' => $this->save_item('devis', $data)]; }
    public function delete_devis($id) { return ['success' => $this->delete_item('devis', $id)]; }
    public function get_orders() { return $this->get_collection('orders'); }
    public function create_order($data) { return ['success' => true, 'data' => ['id' => $this->save_item('orders', $data)]]; }
    public function update_order($data) { return ['success' => true, 'data' => ['id' => $this->save_item('orders', $data)]]; }

    public function get_factures() { return $this->get_collection('factures'); }
    public function create_facture($data) { return ['success' => true, 'id' => $this->save_item('factures', $data)]; }
    public function update_facture($data) { return ['success' => true, 'id' => $this->save_item('factures', $data)]; }
    public function delete_facture($id) { return ['success' => $this->delete_item('factures', $id)]; }

    // Fallbacks pour les autres modules pour ne pas casser le plugin
    public function get_paiements() { return $this->get_collection('paiements'); }
    public function create_paiement($data) { return ['success' => true, 'id' => $this->save_item('paiements', $data)]; }
    public function update_paiement($data) { return ['success' => true, 'id' => $this->save_item('paiements', $data)]; }
    public function delete_paiement($id) { return ['success' => $this->delete_item('paiements', $id)]; }

    public function get_charges() { return $this->get_collection('charges'); }
    public function create_charge($data) { return ['success' => true, 'id' => $this->save_item('charges', $data)]; }
    public function update_charge($data) { return ['success' => true, 'id' => $this->save_item('charges', $data)]; }
    public function delete_charge($id) { return ['success' => $this->delete_item('charges', $id)]; }

    public function get_projets() { return $this->get_collection('projets'); }
    public function create_projet($data) { return ['success' => true, 'id' => $this->save_item('projets', $data)]; }
    public function update_projet($data) { return ['success' => true, 'id' => $this->save_item('projets', $data)]; }
    public function delete_projet($id) { return ['success' => $this->delete_item('projets', $id)]; }

    public function get_taches() { return $this->get_collection('taches'); }
    public function create_tache($data) { return ['success' => true, 'id' => $this->save_item('taches', $data)]; }
    public function update_tache($data) { return ['success' => true, 'id' => $this->save_item('taches', $data)]; }
    public function delete_tache($id) { return ['success' => $this->delete_item('taches', $id)]; }

    public function get_catalogue() { return $this->get_collection('catalogue'); }
    public function create_catalogue_item($data) { return ['success' => true, 'id' => $this->save_item('catalogue', $data)]; }
    public function update_catalogue_item($data) { return ['success' => true, 'id' => $this->save_item('catalogue', $data)]; }
    public function delete_catalogue_item($id) { return ['success' => $this->delete_item('catalogue', $id)]; }

    public function get_fournisseurs() { return $this->get_collection('fournisseurs'); }
    public function create_fournisseur($data) { return ['success' => true, 'id' => $this->save_item('fournisseurs', $data)]; }
    public function update_fournisseur($data) { return ['success' => true, 'id' => $this->save_item('fournisseurs', $data)]; }
    public function delete_fournisseur($id) { return ['success' => $this->delete_item('fournisseurs', $id)]; }

    public function get_rendez_vous() { return $this->get_collection('rendez_vous'); }
    public function create_rendez_vous($data) { return ['success' => true, 'id' => $this->save_item('rendez_vous', $data)]; }
    public function update_rendez_vous($data) { return ['success' => true, 'id' => $this->save_item('rendez_vous', $data)]; }
    public function delete_rendez_vous($id) { return ['success' => $this->delete_item('rendez_vous', $id)]; }
    public function get_capabilities() {
        return [
            'success' => true,
            'code' => 200,
            'message' => 'Capabilities mock chargees.',
            'data' => [
                'resources' => [
                    'clients' => ['clients:read'],
                    'catalogue' => ['catalogue:read'],
                    'quotes' => ['quotes:read'],
                    'invoices' => ['invoices:read'],
                    'payments' => ['payments:read'],
                    'projects' => ['projects:read'],
                    'tasks' => ['tasks:read'],
                    'appointments' => ['appointments:read'],
                    'reports' => ['reports:read'],
                    'expenses' => ['expenses:read'],
                    'suppliers' => ['suppliers:read'],
                ],
                'capabilities' => [
                    'clients:read',
                    'catalogue:read',
                    'quotes:read',
                    'invoices:read',
                    'payments:read',
                    'projects:read',
                    'tasks:read',
                    'appointments:read',
                    'reports:read',
                    'expenses:read',
                    'suppliers:read',
                ],
                'resource_capabilities' => [
                    'clients' => ['clients:read'],
                    'catalogue' => ['catalogue:read'],
                    'quotes' => ['quotes:read'],
                    'invoices' => ['invoices:read'],
                    'payments' => ['payments:read'],
                    'projects' => ['projects:read'],
                    'tasks' => ['tasks:read'],
                    'appointments' => ['appointments:read'],
                    'reports' => ['reports:read'],
                    'expenses' => ['expenses:read'],
                    'suppliers' => ['suppliers:read'],
                ],
                'raw' => [],
            ],
        ];
    }
    public function get_rapports() { 
        return [
            'chiffre_affaires' => [50000, 65000, 45000, 72000],
            'depenses' => [30000, 35000, 28000, 40000],
            'benefices' => [20000, 30000, 17000, 32000],
            'rentabilite_projets' => []
        ];
    }
}
