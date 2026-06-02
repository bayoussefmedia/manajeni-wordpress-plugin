<?php
/**
 * Class Manajeni_API_Client
 * Client HTTP WordPress pour l'API Manajeni.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Manajeni_API_Client {

    /**
     * @var string
     */
    private $api_url;

    /**
     * @var string
     */
    private $api_key;

    /**
     * @var string
     */
    private $api_secret;

    /**
     * @var string
     */
    private $api_email;

    /**
     * @var array
     */
    private $endpoint_map = [
        'test' => '/status',
        'clients' => '/clients',
        'catalogue' => '/catalogue',
        'orders' => '/orders',
        'appointments' => '/appointments',
    ];

    /**
     * @param string $api_url URL API optionnelle.
     * @param string $api_key Cle API optionnelle.
     * @param string $api_secret Secret API optionnel.
     * @param string $api_email Email API optionnel.
     */
    public function __construct($api_url = '', $api_key = '', $api_secret = '', $api_email = '') {
        $db = new Manajeni_DB();

        $this->api_url = $this->normalize_base_url($api_url ?: get_option('manajeni_url', 'https://manajeni.com/api/external/v1'));
        $this->api_key = $api_key ?: (string) $db->get_api_key();
        $this->api_secret = $api_secret ?: (string) $db->get_api_secret();
        $this->api_email = $api_email ?: $db->get_api_email();
        $this->endpoint_map = apply_filters('manajeni_connector_api_endpoints', $this->endpoint_map);
    }

    /**
     * Teste la connexion API.
     *
     * @return array
     */
    public function test_connection() {
        $response = $this->request('GET', $this->get_endpoint('test'));

        if ($response['success']) {
            return [
                'success' => true,
                'message' => __('Connexion API reussie.', 'manajeni-connector'),
                'code' => $response['code'],
                'data' => $response['data'],
            ];
        }

        return $response;
    }

    public function get_clients() {
        return $this->extract_collection($this->request('GET', $this->get_endpoint('clients')), 'clients');
    }

    public function create_client($data) {
        return $this->request('POST', $this->get_endpoint('clients'), $data);
    }

    public function update_client($data) {
        return $this->update_resource($this->get_endpoint('clients'), $data);
    }

    public function delete_client($id) {
        return $this->delete_resource($this->get_endpoint('clients'), $id);
    }

    public function get_catalogue() {
        return $this->extract_collection($this->request('GET', $this->get_endpoint('catalogue')), 'catalogue');
    }

    public function create_catalogue_item($data) {
        return $this->request('POST', $this->get_endpoint('catalogue'), $data);
    }

    public function update_catalogue_item($data) {
        return $this->update_resource($this->get_endpoint('catalogue'), $data);
    }

    public function delete_catalogue_item($id) {
        return $this->delete_resource($this->get_endpoint('catalogue'), $id);
    }

    public function get_orders() {
        return $this->extract_collection($this->request('GET', $this->get_endpoint('orders')), 'orders');
    }

    public function create_order($data) {
        return $this->request('POST', $this->get_endpoint('orders'), $data);
    }

    public function get_appointments() {
        return $this->extract_collection($this->request('GET', $this->get_endpoint('appointments')), 'appointments');
    }

    public function create_appointment($data) {
        return $this->request('POST', $this->get_endpoint('appointments'), $data);
    }

    public function get_devis() {
        return $this->get_orders();
    }

    public function create_devis($data) {
        return $this->create_order($data);
    }

    public function update_devis($data) {
        return $this->update_resource($this->get_endpoint('orders'), $data);
    }

    public function delete_devis($id) {
        return $this->delete_resource($this->get_endpoint('orders'), $id);
    }

    public function get_factures() {
        return [];
    }

    public function create_facture($data) {
        return $this->unsupported_mutation($data, __('L\'endpoint factures n\'est pas configure.', 'manajeni-connector'));
    }

    public function update_facture($data) {
        return $this->unsupported_mutation($data, __('L\'endpoint factures n\'est pas configure.', 'manajeni-connector'));
    }

    public function delete_facture($id) {
        return $this->unsupported_mutation(['id' => $id], __('L\'endpoint factures n\'est pas configure.', 'manajeni-connector'));
    }

    public function get_rendez_vous() {
        return $this->get_appointments();
    }

    public function create_rendez_vous($data) {
        return $this->create_appointment($data);
    }

    public function update_rendez_vous($data) {
        return $this->update_resource($this->get_endpoint('appointments'), $data);
    }

    public function delete_rendez_vous($id) {
        return $this->delete_resource($this->get_endpoint('appointments'), $id);
    }

    public function get_paiements() {
        return [];
    }

    public function create_paiement($data) {
        return $this->unsupported_mutation($data, __('L\'endpoint paiements n\'est pas configure.', 'manajeni-connector'));
    }

    public function update_paiement($data) {
        return $this->unsupported_mutation($data, __('L\'endpoint paiements n\'est pas configure.', 'manajeni-connector'));
    }

    public function delete_paiement($id) {
        return $this->unsupported_mutation(['id' => $id], __('L\'endpoint paiements n\'est pas configure.', 'manajeni-connector'));
    }

    public function get_charges() {
        return [];
    }

    public function create_charge($data) {
        return $this->unsupported_mutation($data, __('L\'endpoint charges n\'est pas configure.', 'manajeni-connector'));
    }

    public function update_charge($data) {
        return $this->unsupported_mutation($data, __('L\'endpoint charges n\'est pas configure.', 'manajeni-connector'));
    }

    public function delete_charge($id) {
        return $this->unsupported_mutation(['id' => $id], __('L\'endpoint charges n\'est pas configure.', 'manajeni-connector'));
    }

    public function get_projets() {
        return [];
    }

    public function create_projet($data) {
        return $this->unsupported_mutation($data, __('L\'endpoint projets n\'est pas configure.', 'manajeni-connector'));
    }

    public function update_projet($data) {
        return $this->unsupported_mutation($data, __('L\'endpoint projets n\'est pas configure.', 'manajeni-connector'));
    }

    public function delete_projet($id) {
        return $this->unsupported_mutation(['id' => $id], __('L\'endpoint projets n\'est pas configure.', 'manajeni-connector'));
    }

    public function get_taches() {
        return [];
    }

    public function create_tache($data) {
        return $this->unsupported_mutation($data, __('L\'endpoint taches n\'est pas configure.', 'manajeni-connector'));
    }

    public function update_tache($data) {
        return $this->unsupported_mutation($data, __('L\'endpoint taches n\'est pas configure.', 'manajeni-connector'));
    }

    public function delete_tache($id) {
        return $this->unsupported_mutation(['id' => $id], __('L\'endpoint taches n\'est pas configure.', 'manajeni-connector'));
    }

    public function get_fournisseurs() {
        return [];
    }

    public function create_fournisseur($data) {
        return $this->unsupported_mutation($data, __('L\'endpoint fournisseurs n\'est pas configure.', 'manajeni-connector'));
    }

    public function update_fournisseur($data) {
        return $this->unsupported_mutation($data, __('L\'endpoint fournisseurs n\'est pas configure.', 'manajeni-connector'));
    }

    public function delete_fournisseur($id) {
        return $this->unsupported_mutation(['id' => $id], __('L\'endpoint fournisseurs n\'est pas configure.', 'manajeni-connector'));
    }

    public function get_rapports() {
        return [];
    }

    /**
     * Execute une requete HTTP.
     *
     * @param string     $method Methode HTTP.
     * @param string     $endpoint Endpoint relatif.
     * @param array|null $body Donnees a envoyer.
     * @param array      $query Query string.
     * @return array
     */
    private function request($method, $endpoint, $body = null, $query = []) {
        if (empty($this->api_url)) {
            return $this->error_response(__('URL API Manajeni manquante.', 'manajeni-connector'));
        }

        if (empty($this->api_key)) {
            return $this->error_response(__('Cle API Manajeni manquante.', 'manajeni-connector'));
        }

        $method = strtoupper($method);
        $url = untrailingslashit($this->api_url) . '/' . ltrim($endpoint, '/');

        if (!empty($query)) {
            $url = add_query_arg($query, $url);
        }

        $raw_body = $this->build_raw_body($method, $body);
        $args = [
            'method' => $method,
            'timeout' => (int) apply_filters('manajeni_connector_api_timeout', 20),
            'headers' => $this->build_headers($method, $url, $raw_body),
        ];

        if ('' !== $raw_body) {
            $args['body'] = $raw_body;
            $args['headers']['Content-Type'] = 'application/json';
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $this->error_response($response->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $response_raw_body = wp_remote_retrieve_body($response);
        $decoded = json_decode($response_raw_body, true);

        if ($code < 200 || $code >= 300) {
            $message = $this->extract_error_message($decoded, $response_raw_body);
            return $this->error_response($message, $code, $decoded);
        }

        return [
            'success' => true,
            'code' => $code,
            'message' => __('Requete API reussie.', 'manajeni-connector'),
            'data' => is_array($decoded) ? $decoded : [],
        ];
    }

    /**
     * Construit les headers HTTP appropries au mode d'authentification.
     *
     * @param string $method Methode HTTP.
     * @param string $url URL complete.
     * @param string $raw_body Corps JSON exact.
     * @return array
     */
    private function build_headers($method, $url, $raw_body) {
        $headers = [
            'Accept' => 'application/json',
        ];

        if ($this->uses_legacy_signature()) {
            $origin = home_url();
            $timestamp = (string) time();
            $nonce = wp_generate_uuid4();

            $headers['X-Manajeni-Key'] = $this->api_key;
            $headers['X-Manajeni-Email'] = $this->api_email;
            $headers['X-Manajeni-Origin'] = $origin;
            $headers['X-Manajeni-Timestamp'] = $timestamp;
            $headers['X-Manajeni-Nonce'] = $nonce;
            $headers['X-Manajeni-Signature'] = $this->generate_legacy_signature($method, $url, $timestamp, $nonce, $origin, $this->api_email, $raw_body);

            return $headers;
        }

        $headers['Authorization'] = 'Bearer ' . $this->api_key;
        $headers['X-Manajeni-Origin'] = home_url();

        return $headers;
    }

    /**
     * Genere le corps JSON exact utilise pour la signature et l'envoi.
     *
     * @param string     $method Methode HTTP.
     * @param array|null $body Donnees.
     * @return string
     */
    private function build_raw_body($method, $body) {
        if ('GET' === $method || null === $body) {
            return '';
        }

        return wp_json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Determine si la requete doit utiliser la signature legacy.
     *
     * @return bool
     */
    private function uses_legacy_signature() {
        return 0 === strpos($this->api_key, 'mnj_');
    }

    /**
     * Calcule la signature HMAC legacy Manajeni.
     *
     * Inference: la chaine signee suit l'ordre documente du service Laravel,
     * chaque valeur etant concatenee sur une ligne avec le path URL uniquement.
     *
     * @param string $method Methode HTTP.
     * @param string $url URL complete.
     * @param string $timestamp Timestamp Unix.
     * @param string $nonce Nonce unique.
     * @param string $origin Origine WordPress.
     * @param string $email Email associe.
     * @param string $raw_body Corps JSON exact.
     * @return string
     */
    private function generate_legacy_signature($method, $url, $timestamp, $nonce, $origin, $email, $raw_body) {
        $path = (string) wp_parse_url($url, PHP_URL_PATH);
        $payload = implode("\n", [
            strtoupper($method),
            $path,
            (string) $timestamp,
            (string) $nonce,
            (string) $origin,
            (string) $email,
            (string) $raw_body,
        ]);

        return hash_hmac('sha256', $payload, (string) $this->api_secret);
    }

    /**
     * Normalise une URL de base.
     *
     * @param string $api_url URL brute.
     * @return string
     */
    private function normalize_base_url($api_url) {
        $api_url = trim((string) $api_url);

        if (empty($api_url)) {
            return '';
        }

        return untrailingslashit(esc_url_raw($api_url));
    }

    /**
     * Retourne un endpoint.
     *
     * @param string $key Cle endpoint.
     * @return string
     */
    private function get_endpoint($key) {
        return isset($this->endpoint_map[$key]) ? $this->endpoint_map[$key] : '/' . ltrim($key, '/');
    }

    /**
     * Extrait une collection depuis une reponse.
     *
     * @param array  $response Reponse normalisee.
     * @param string $key Cle preferee.
     * @return array
     */
    private function extract_collection($response, $key) {
        if (!$response['success']) {
            return [];
        }

        $data = $response['data'];

        if (isset($data[$key]) && is_array($data[$key])) {
            return $data[$key];
        }

        if (isset($data['data'][$key]) && is_array($data['data'][$key])) {
            return $data['data'][$key];
        }

        if (isset($data['data']) && is_array($data['data']) && $this->is_list_array($data['data'])) {
            return $data['data'];
        }

        if ($this->is_list_array($data)) {
            return $data;
        }

        return [];
    }

    /**
     * Met a jour une ressource par id.
     *
     * @param string $endpoint Endpoint.
     * @param array  $data Donnees.
     * @return array
     */
    private function update_resource($endpoint, $data) {
        if (empty($data['id'])) {
            return $this->error_response(__('Identifiant manquant pour la mise a jour.', 'manajeni-connector'));
        }

        $id = absint($data['id']);
        unset($data['id']);

        return $this->request('PUT', trailingslashit($endpoint) . $id, $data);
    }

    /**
     * Supprime une ressource par id.
     *
     * @param string $endpoint Endpoint.
     * @param int    $id Identifiant.
     * @return array
     */
    private function delete_resource($endpoint, $id) {
        return $this->request('DELETE', trailingslashit($endpoint) . absint($id), null);
    }

    /**
     * Retourne une reponse d'erreur normalisee.
     *
     * @param string $message Message.
     * @param int    $code Code HTTP.
     * @param mixed  $data Donnees.
     * @return array
     */
    private function error_response($message, $code = 0, $data = []) {
        return [
            'success' => false,
            'code' => (int) $code,
            'message' => (string) $message,
            'data' => is_array($data) ? $data : [],
        ];
    }

    /**
     * Extrait un message d'erreur lisible.
     *
     * @param mixed  $decoded Corps decode.
     * @param string $raw_body Corps brut.
     * @return string
     */
    private function extract_error_message($decoded, $raw_body) {
        if (is_array($decoded)) {
            foreach (['message', 'error', 'detail'] as $key) {
                if (!empty($decoded[$key]) && is_string($decoded[$key])) {
                    return $decoded[$key];
                }
            }

            if (!empty($decoded['errors']) && is_array($decoded['errors'])) {
                return wp_json_encode($decoded['errors']);
            }
        }

        if (!empty($raw_body) && is_string($raw_body)) {
            return wp_strip_all_tags($raw_body);
        }

        return __('Erreur API Manajeni inconnue.', 'manajeni-connector');
    }

    /**
     * Reponse normalisee pour endpoint non configure.
     *
     * @param array  $data Donnees.
     * @param string $message Message.
     * @return array
     */
    private function unsupported_mutation($data, $message) {
        return [
            'success' => false,
            'message' => $message,
            'data' => $data,
            'code' => 0,
        ];
    }

    /**
     * Indique si un tableau est une liste numerique sequentielle.
     *
     * @param array $data Tableau.
     * @return bool
     */
    private function is_list_array($data) {
        if (!is_array($data)) {
            return false;
        }

        return array_keys($data) === range(0, count($data) - 1);
    }
}
