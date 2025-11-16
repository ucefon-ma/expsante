<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class HE_Roles {
    public static function register_roles() {
        add_role('directeur_hopital', 'Directeur Hôpital', ['read' => true]);
        add_role('administrateur_hopital', 'Administrateur Hôpital', ['read' => true]);
        add_role('medecin', 'Médecin', ['read' => true]);
        add_role('infirmier', 'Infirmier', ['read' => true]);
    }

    public static function remove_roles() {
        remove_role('directeur_hopital');
        remove_role('administrateur_hopital');
        remove_role('medecin');
        remove_role('infirmier');
    }
}

if (!defined('ABSPATH')) exit;

/**
 * 🚫 Vérifie qu'un hôpital n'a qu'un seul utilisateur par profil (Directeur, Médecin, etc.)
 * Fonctionne même si ACF ne déclenche pas ses hooks.
 */
add_action('user_profile_update_errors', function($errors, $update, $user) {
    if (!$update || empty($user->ID)) return;

    global $wpdb;

    $user_id = intval($user->ID);
    $role = !empty($user->roles) ? $user->roles[0] : '';
    $restricted_roles = ['directeur_hopital', 'administrateur_hopital', 'medecin', 'infirmier'];
    if (!in_array($role, $restricted_roles)) return;

    // Récupérer le champ hospital_id depuis la base (ACF le stocke dans usermeta)
    $hospital_id = get_user_meta($user_id, 'hospital_id', true);
    if (!$hospital_id) return;

    // Vérifier si un autre utilisateur du même rôle a déjà ce même hôpital
    $existing = $wpdb->get_var($wpdb->prepare("
        SELECT u.ID
        FROM {$wpdb->users} u
        INNER JOIN {$wpdb->usermeta} m_role ON u.ID = m_role.user_id
        INNER JOIN {$wpdb->usermeta} m_hosp ON u.ID = m_hosp.user_id
        WHERE m_role.meta_key = '{$wpdb->prefix}capabilities'
          AND m_role.meta_value LIKE %s
          AND m_hosp.meta_key = 'hospital_id'
          AND m_hosp.meta_value = %s
          AND u.ID != %d
        LIMIT 1
    ", '%' . $role . '%', $hospital_id, $user_id));

    if ($existing) {
        $label = ucfirst(str_replace('_', ' ', $role));
        $hospital_name = get_the_title($hospital_id) ?: 'cet hôpital';
        $errors->add('he_role_conflict', "<strong>Erreur :</strong> Il existe déjà un <strong>{$label}</strong> pour <strong>{$hospital_name}</strong>. Un seul utilisateur par profil et par hôpital est autorisé.");
    }
}, 10, 3);
