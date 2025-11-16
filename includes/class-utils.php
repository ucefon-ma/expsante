<?php
if (!defined('ABSPATH')) exit;

/**
 * Classe utilitaire globale EXP Santé
 * Version améliorée avec mapping centralisé et fonctions supplémentaires
 */
class HE_Utils {

    /**
     * Mapping centralisé des rôles WordPress vers les profils métier
     */
    private static $role_mapping = [
        // Variations du rôle Directeur
        'directeur_hopital'      => 'Directeur',
        'directeur hopital'      => 'Directeur',
        'directeur'              => 'Directeur',
        
        // Variations du rôle Administrateur Hôpital
        'administrateur_hopital' => 'Administrateur Hôpital',
        'administrateur hopital' => 'Administrateur Hôpital',
        'admin hopital'          => 'Administrateur Hôpital',
        
        // Rôle Médecin
        'medecin'                => 'Médecin',
        
        // Rôle Infirmier
        'infirmier'              => 'Infirmier',
    ];

    /**
     * ✅ Normalise un profil utilisateur
     * @param string $profil Profil brut
     * @return string Profil normalisé
     */
    public static function normalize_profil($profil) {
        $profil_clean = strtolower(trim(str_replace('_', ' ', $profil)));
        return self::$role_mapping[$profil_clean] ?? ucfirst($profil);
    }

    /**
     * ✅ Détecte le profil utilisateur à partir de son rôle WordPress
     * @param int|null $user_id ID de l'utilisateur (null = utilisateur actuel)
     * @param string $profil_brut Profil fourni (optionnel)
     * @return string Profil normalisé
     */
    public static function detect_user_profil($user_id = null, $profil_brut = '') {
        // Si un profil brut est fourni, on tente de le normaliser
        if (!empty($profil_brut)) {
            $normalized = self::normalize_profil($profil_brut);
            if ($normalized !== 'Inconnu') {
                return $normalized;
            }
        }

        // Sinon, on détecte via le rôle WordPress
        $user = $user_id ? get_user_by('id', $user_id) : wp_get_current_user();
        
        if (!$user || empty($user->roles)) {
            self::log("⚠️ Utilisateur sans rôle détecté (ID: $user_id)");
            return 'Inconnu';
        }

        $role = $user->roles[0];
        $normalized = self::normalize_profil($role);
        
        return $normalized;
    }

    /**
     * ✅ Écrit dans le debug.log si WP_DEBUG_LOG est activé
     * @param string $message Message à logger
     * @param string $level Niveau de log (info, warning, error)
     */
    public static function log($message, $level = 'info') {
        if (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
            return;
        }

        $prefix = match($level) {
            'error' => '❌',
            'warning' => '⚠️',
            'success' => '✅',
            'info' => 'ℹ️',
            default => '•'
        };

        error_log("[EXP Santé] {$prefix} {$message}");
    }

    /**
     * ✅ Supprime tous les transients de score
     * @return int Nombre de transients supprimés
     */
    public static function purge_score_cache() {
        global $wpdb;
        
        $deleted = $wpdb->query("
            DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_he_score_%' 
            OR option_name LIKE '_transient_timeout_he_score_%'
        ");

        if ($deleted === false) {
            self::log("Échec purge cache scores: " . $wpdb->last_error, 'error');
            return 0;
        }

        self::log("$deleted entrées de cache purgées", 'success');
        return $deleted;
    }

    /**
     * ✅ Vérifie si un utilisateur a un rôle spécifique du plugin
     * @param int|null $user_id ID de l'utilisateur (null = utilisateur actuel)
     * @return bool True si l'utilisateur a un rôle EXP Santé
     */
    public static function user_has_plugin_role($user_id = null) {
        $user = $user_id ? get_user_by('id', $user_id) : wp_get_current_user();
        
        if (!$user || empty($user->roles)) {
            return false;
        }

        $plugin_roles = ['directeur_hopital', 'administrateur_hopital', 'medecin', 'infirmier'];
        
        return !empty(array_intersect($user->roles, $plugin_roles));
    }

    /**
     * ✅ Récupère l'hôpital assigné à un utilisateur
     * @param int|null $user_id ID de l'utilisateur (null = utilisateur actuel)
     * @return int|null ID de l'hôpital ou null
     */
    public static function get_user_hospital($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return null;
        }

        $hospital_id = get_user_meta($user_id, 'hospital_id', true);
        
        // ✅ ACF peut stocker comme array ou string
        if (is_array($hospital_id)) {
            $hospital_id = !empty($hospital_id[0]) ? $hospital_id[0] : null;
        }

        return $hospital_id ? intval($hospital_id) : null;
    }

    /**
     * ✅ Récupère tous les profils disponibles
     * @return array Liste des profils
     */
    public static function get_all_profiles() {
        return array_unique(array_values(self::$role_mapping));
    }

    /**
     * ✅ Vérifie si un profil est valide
     * @param string $profil Profil à vérifier
     * @return bool True si valide
     */
    public static function is_valid_profile($profil) {
        $valid_profiles = self::get_all_profiles();
        return in_array($profil, $valid_profiles);
    }

    /**
     * ✅ Formate un score pour l'affichage
     * @param float $score Score à formater
     * @param bool $with_percent Ajouter le symbole %
     * @return string Score formaté
     */
    public static function format_score($score, $with_percent = true) {
        $score = floatval($score);
        $formatted = number_format($score, 2, ',', ' ');
        return $with_percent ? $formatted . '%' : $formatted;
    }

    /**
     * ✅ Obtient la couleur d'un score selon sa valeur
     * @param float $score Score (0-100)
     * @return string Classe CSS ou code couleur
     */
    public static function get_score_color($score) {
        $score = floatval($score);
        
        if ($score >= 75) {
            return 'success'; // Vert
        } elseif ($score >= 50) {
            return 'warning'; // Orange
        } else {
            return 'danger'; // Rouge
        }
    }

    /**
     * ✅ Obtient le label d'un score
     * @param float $score Score (0-100)
     * @return string Label du score
     */
    public static function get_score_label($score) {
        $score = floatval($score);
        
        if ($score >= 75) {
            return 'Excellent';
        } elseif ($score >= 50) {
            return 'Bon';
        } elseif ($score >= 25) {
            return 'À améliorer';
        } else {
            return 'Critique';
        }
    }

    /**
     * ✅ Convertit un statut en label lisible
     * @param string $status Statut
     * @return string Label du statut
     */
    public static function get_status_label($status) {
        $labels = [
            'draft' => 'Brouillon',
            'submitted' => 'Soumise',
            'validated' => 'Validée',
            'rejected' => 'Rejetée'
        ];

        return $labels[$status] ?? ucfirst($status);
    }

    /**
     * ✅ Sanitize un tableau de données
     * @param array $data Données à nettoyer
     * @return array Données nettoyées
     */
    public static function sanitize_array($data) {
        if (!is_array($data)) {
            return [];
        }

        $sanitized = [];
        foreach ($data as $key => $value) {
            $key = sanitize_key($key);
            
            if (is_array($value)) {
                $sanitized[$key] = self::sanitize_array($value);
            } else {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }

        return $sanitized;
    }

    /**
     * ✅ Génère un token de sécurité unique
     * @param string $action Action à sécuriser
     * @return string Token généré
     */
    public static function generate_token($action = 'default') {
        return wp_hash($action . time() . wp_rand());
    }

    /**
     * ✅ Formate une date pour l'affichage
     * @param string $date Date MySQL
     * @param string $format Format de sortie
     * @return string Date formatée
     */
    public static function format_date($date, $format = 'd/m/Y H:i') {
        if (empty($date) || $date === '0000-00-00 00:00:00') {
            return '-';
        }

        $timestamp = strtotime($date);
        return $timestamp ? date_i18n($format, $timestamp) : '-';
    }

    /**
     * ✅ Calcule le temps écoulé depuis une date
     * @param string $date Date MySQL
     * @return string Temps écoulé (ex: "Il y a 2 heures")
     */
    public static function time_ago($date) {
        if (empty($date) || $date === '0000-00-00 00:00:00') {
            return '-';
        }

        return human_time_diff(strtotime($date), current_time('timestamp')) . ' ago';
    }

    /**
     * ✅ Vérifie si une chaîne est un JSON valide
     * @param string $string Chaîne à vérifier
     * @return bool True si JSON valide
     */
    public static function is_json($string) {
        if (!is_string($string)) {
            return false;
        }

        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * ✅ Récupère les informations système pour le debug
     * @return array Informations système
     */
    public static function get_system_info() {
        global $wpdb;

        return [
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
            'plugin_version' => '2.0.1', // À synchroniser avec la version du plugin
            'db_version' => get_option('he_db_version', 'unknown'),
            'mysql_version' => $wpdb->db_version(),
            'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'debug_mode' => defined('WP_DEBUG') && WP_DEBUG,
            'debug_log' => defined('WP_DEBUG_LOG') && WP_DEBUG_LOG
        ];
    }

    /**
     * ✅ Vérifie les prérequis du plugin
     * @return array Résultats de la vérification
     */
    public static function check_requirements() {
        $requirements = [
            'php_version' => [
                'required' => '7.4',
                'current' => PHP_VERSION,
                'met' => version_compare(PHP_VERSION, '7.4', '>=')
            ],
            'wp_version' => [
                'required' => '5.8',
                'current' => get_bloginfo('version'),
                'met' => version_compare(get_bloginfo('version'), '5.8', '>=')
            ],
            'mysql_version' => [
                'required' => '5.6',
                'current' => $GLOBALS['wpdb']->db_version(),
                'met' => version_compare($GLOBALS['wpdb']->db_version(), '5.6', '>=')
            ]
        ];

        $all_met = true;
        foreach ($requirements as $req) {
            if (!$req['met']) {
                $all_met = false;
                break;
            }
        }

        return [
            'all_met' => $all_met,
            'requirements' => $requirements
        ];
    }
}
