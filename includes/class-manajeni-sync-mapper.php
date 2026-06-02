<?php
/**
 * Stockage des correspondances entre WordPress/WooCommerce et Manajeni.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Manajeni_Sync_Mapper {

    const OPTION_KEY = 'manajeni_sync_mappings';
    const LOCK_PREFIX = 'manajeni_sync_lock_';

    /**
     * Retourne tous les mappings.
     *
     * @return array
     */
    public function get_all() {
        $mappings = get_option(self::OPTION_KEY, []);

        if (!is_array($mappings)) {
            $mappings = [];
        }

        return wp_parse_args($mappings, [
            'products' => [],
            'clients' => [],
            'orders' => [],
        ]);
    }

    /**
     * Sauvegarde un mapping produit.
     *
     * @param array $mapping Mapping.
     * @return void
     */
    public function save_product_mapping($mapping) {
        $mapping = $this->sanitize_mapping($mapping, ['wc_product_id', 'manajeni_product_id', 'sku', 'updated_at']);
        $mappings = $this->get_all();
        $key = $this->resolve_mapping_key($mapping, ['wc_product_id', 'sku', 'manajeni_product_id']);

        if ('' === $key) {
            return;
        }

        $mappings['products'][$key] = $mapping;
        update_option(self::OPTION_KEY, $mappings, false);
    }

    /**
     * Sauvegarde un mapping client.
     *
     * @param array $mapping Mapping.
     * @return void
     */
    public function save_client_mapping($mapping) {
        $mapping = $this->sanitize_mapping($mapping, ['wp_user_id', 'customer_id', 'manajeni_client_id', 'email', 'updated_at']);
        $mappings = $this->get_all();
        $key = $this->resolve_mapping_key($mapping, ['wp_user_id', 'customer_id', 'email', 'manajeni_client_id']);

        if ('' === $key) {
            return;
        }

        $mappings['clients'][$key] = $mapping;
        update_option(self::OPTION_KEY, $mappings, false);
    }

    /**
     * Sauvegarde un mapping commande.
     *
     * @param array $mapping Mapping.
     * @return void
     */
    public function save_order_mapping($mapping) {
        $mapping = $this->sanitize_mapping($mapping, ['wc_order_id', 'manajeni_order_id', 'order_number', 'updated_at']);
        $mappings = $this->get_all();
        $key = $this->resolve_mapping_key($mapping, ['wc_order_id', 'order_number', 'manajeni_order_id']);

        if ('' === $key) {
            return;
        }

        $mappings['orders'][$key] = $mapping;
        update_option(self::OPTION_KEY, $mappings, false);
    }

    /**
     * Trouve un mapping produit.
     *
     * @param array $criteria Criteres.
     * @return array|null
     */
    public function find_product_mapping($criteria) {
        return $this->find_mapping('products', $criteria);
    }

    /**
     * Trouve un mapping client.
     *
     * @param array $criteria Criteres.
     * @return array|null
     */
    public function find_client_mapping($criteria) {
        return $this->find_mapping('clients', $criteria);
    }

    /**
     * Trouve un mapping commande.
     *
     * @param array $criteria Criteres.
     * @return array|null
     */
    public function find_order_mapping($criteria) {
        return $this->find_mapping('orders', $criteria);
    }

    /**
     * Retourne le nombre de mappings par type.
     *
     * @return array
     */
    public function get_counts() {
        $mappings = $this->get_all();

        return [
            'products' => count($mappings['products']),
            'clients' => count($mappings['clients']),
            'orders' => count($mappings['orders']),
        ];
    }

    /**
     * Indique si un lock existe.
     *
     * @param string $resource Resource.
     * @param string $id Identifiant.
     * @return bool
     */
    public function has_lock($resource, $id) {
        return false !== get_transient($this->get_lock_key($resource, $id));
    }

    /**
     * Pose un lock temporaire.
     *
     * @param string $resource Resource.
     * @param string $id Identifiant.
     * @param int    $ttl Duree.
     * @return void
     */
    public function set_lock($resource, $id, $ttl = 60) {
        set_transient($this->get_lock_key($resource, $id), 1, max(1, absint($ttl)));
    }

    /**
     * Supprime un lock.
     *
     * @param string $resource Resource.
     * @param string $id Identifiant.
     * @return void
     */
    public function release_lock($resource, $id) {
        delete_transient($this->get_lock_key($resource, $id));
    }

    /**
     * Nettoie tous les mappings.
     *
     * @return void
     */
    public function clear() {
        delete_option(self::OPTION_KEY);
    }

    /**
     * Cherche un mapping dans un type donne.
     *
     * @param string $type Type.
     * @param array  $criteria Criteres.
     * @return array|null
     */
    private function find_mapping($type, $criteria) {
        $mappings = $this->get_all();

        if (empty($mappings[$type]) || !is_array($criteria)) {
            return null;
        }

        foreach ($mappings[$type] as $mapping) {
            $match = true;
            foreach ($criteria as $field => $value) {
                if (!isset($mapping[$field]) || (string) $mapping[$field] !== (string) $value) {
                    $match = false;
                    break;
                }
            }

            if ($match) {
                return $mapping;
            }
        }

        return null;
    }

    /**
     * Nettoie un mapping.
     *
     * @param array $mapping Mapping.
     * @param array $allowed_fields Champs autorises.
     * @return array
     */
    private function sanitize_mapping($mapping, $allowed_fields) {
        $clean = [];

        foreach ($allowed_fields as $field) {
            if (!isset($mapping[$field])) {
                continue;
            }

            $value = $mapping[$field];
            if (is_numeric($value)) {
                $clean[$field] = (int) $value;
            } else {
                $clean[$field] = sanitize_text_field((string) $value);
            }
        }

        if (empty($clean['updated_at'])) {
            $clean['updated_at'] = current_time('mysql');
        }

        return $clean;
    }

    /**
     * Determine une cle de mapping stable.
     *
     * @param array $mapping Mapping.
     * @param array $priority_fields Champs prioritaires.
     * @return string
     */
    private function resolve_mapping_key($mapping, $priority_fields) {
        foreach ($priority_fields as $field) {
            if (!empty($mapping[$field])) {
                $value = (string) $mapping[$field];
                if (is_numeric($value)) {
                    return sanitize_key($field) . '_' . absint($value);
                }

                return sanitize_key($field) . '_' . md5($value);
            }
        }

        return '';
    }

    /**
     * Retourne la cle de lock transient.
     *
     * @param string $resource Resource.
     * @param string $id Identifiant.
     * @return string
     */
    private function get_lock_key($resource, $id) {
        return self::LOCK_PREFIX . sanitize_key($resource) . '_' . sanitize_key((string) $id);
    }
}
