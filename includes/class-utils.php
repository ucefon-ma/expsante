<?php
if (!defined('ABSPATH')) exit;

/**
 * Classe utilitaire globale EXP Santé
 * -----------------------------------
 * Contient des fonctions réutilisables pour :
 * - la détection du profil utilisateur
 * - la gestion des logs
 * - la purge du cache
 */
class HE_Utils {

    /**
     * Détecte le profil utilisateur à partir de son rôle WordPress
     */
  public static function detect_user_profil($user_id = null, $profil_brut = '') {
    $profil_brut = strtolower(trim(str_replace('_', ' ', $profil_brut)));

    // Si un profil brut est fourni, on tente de le normaliser
    if (!empty($profil_brut)) {
        $map = [
            'directeur hopital'      => 'Directeur',
            'directeur_hopital'      => 'Directeur',
            'directeur'              => 'Directeur',
            'administrateur hopital' => 'Administrateur Hôpital',
            'administrateur_hopital' => 'Administrateur Hôpital',
            'admin hopital'          => 'Administrateur Hôpital',
            'medecin'                => 'Médecin',
            'infirmier'              => 'Infirmier',
        ];
        if (isset($map[$profil_brut])) return $map[$profil_brut];
    }

    // Sinon, on détecte via le rôle WordPress
    $user = $user_id ? get_user_by('id', $user_id) : wp_get_current_user();
    if (!$user || empty($user->roles)) return 'Inconnu';

    $role = strtolower(str_replace('_', ' ', $user->roles[0]));
    $map = [
        'directeur hopital'      => 'Directeur',
        'directeur'              => 'Directeur',
        'administrateur hopital' => 'Administrateur Hôpital',
        'medecin'                => 'Médecin',
        'infirmier'              => 'Infirmier',
    ];

    return $map[$role] ?? ucfirst($role);
}


    /**
     * Écrit dans le debug.log si WP_DEBUG_LOG est activé
     */
    public static function log($message) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[EXP Santé] ' . $message);
        }
    }

    /**
     * Supprime tous les transients de score
     */
    public static function purge_score_cache() {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_he_score_%' OR option_name LIKE '_transient_timeout_he_score_%'");
        self::log('🧹 Purge complète du cache des scores.');
    }
}
