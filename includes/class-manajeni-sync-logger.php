<?php
/**
 * Journalisation dediee a la synchronisation WooCommerce.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Manajeni_Sync_Logger {

    const OPTION_KEY = 'manajeni_sync_logs';
    const MAX_LOGS = 300;

    /**
     * Ajoute une entree de log detaillee.
     *
     * @param string $level Niveau.
     * @param string $action Action.
     * @param string $message Message.
     * @param array  $context Contexte.
     * @return void
     */
    public function log($level, $action, $message, $context = []) {
        $logs = get_option(self::OPTION_KEY, []);

        array_unshift($logs, [
            'date' => current_time('mysql'),
            'level' => sanitize_key($level),
            'action' => sanitize_key($action),
            'message' => sanitize_text_field($message),
            'context' => $this->sanitize_context($context),
        ]);

        update_option(self::OPTION_KEY, array_slice($logs, 0, self::MAX_LOGS), false);

        manajeni_connector_add_log(
            'sync_' . sanitize_key($action),
            'error' === sanitize_key($level) ? 'failure' : 'success',
            sanitize_text_field($message)
        );
    }

    /**
     * Retourne les logs.
     *
     * @param int $limit Limite.
     * @return array
     */
    public function get_logs($limit = 100) {
        $logs = get_option(self::OPTION_KEY, []);

        return array_slice(is_array($logs) ? $logs : [], 0, max(1, absint($limit)));
    }

    /**
     * Retourne les compteurs de log.
     *
     * @return array
     */
    public function get_stats() {
        $logs = $this->get_logs(self::MAX_LOGS);
        $stats = [
            'total' => count($logs),
            'info' => 0,
            'warning' => 0,
            'error' => 0,
        ];

        foreach ($logs as $log) {
            $level = isset($log['level']) ? $log['level'] : 'info';
            if (isset($stats[$level])) {
                $stats[$level]++;
            }
        }

        return $stats;
    }

    /**
     * Vide les logs de sync.
     *
     * @return void
     */
    public function clear() {
        delete_option(self::OPTION_KEY);
    }

    /**
     * Nettoie recursivement le contexte.
     *
     * @param mixed $context Contexte.
     * @return mixed
     */
    private function sanitize_context($context) {
        if (!is_array($context)) {
            return sanitize_text_field((string) $context);
        }

        $clean = [];

        foreach ($context as $key => $value) {
            $clean_key = is_string($key) ? sanitize_key($key) : (string) $key;

            if (is_array($value)) {
                $clean[$clean_key] = $this->sanitize_context($value);
                continue;
            }

            if (is_bool($value) || is_numeric($value) || null === $value) {
                $clean[$clean_key] = $value;
                continue;
            }

            $clean[$clean_key] = sanitize_text_field((string) $value);
        }

        return $clean;
    }
}
