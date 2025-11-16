<?php
if (!defined('ABSPATH')) exit;

/**
 * Gestion des rôles personnalisés pour EXP Santé
 * Version corrigée avec sécurité renforcée
 */
class HE_Roles {
    
    /**
     * Enregistre les rôles personnalisés
     */
    public static function register_roles() {
        add_role('directeur_hopital', 'Directeur Hôpital', ['read' => true]);
        add_role('administrateur_hopital', 'Administrateur Hôpital', ['read' => true]);
        add_role('medecin', 'Médecin', ['read' => true]);
        add_role('infirmier', 'Infirmier', ['read' => true]);
        
        HE_Utils::log('✅ Rôles personnalisés enregistrés');
    }

    /**
     * Supprime les rôles personnalisés
     */
    public static function remove_roles() {
        remove_role('directeur_hopital');
        remove_role('administrateur_hopital');
        remove_role('medecin');
        remove_role('infirmier');
        
        HE_Utils::log('🗑️ Rôles personnalisés supprimés');
    }
    
    /**
     * Vérifie qu'un utilisateur a un rôle spécifique
     */
    public static function user_has_role($user_id, $role) {
        $user = get_userdata($user_id);
        if (!$user) return false;
        return in_array($role, (array) $user->roles);
    }
    
    /**
     * Récupère tous les utilisateurs d'un rôle pour un hôpital donné
     */
    public static function get_users_by_role_and_hospital($role, $hospital_id) {
        global $wpdb;
        
        $role = sanitize_text_field($role);
        $hospital_id = intval($hospital_id);
        
        if (!$hospital_id) return [];
        
        $users = get_users([
            'role' => $role,
            'meta_key' => 'hospital_id',
            'meta_value' => $hospital_id,
            'fields' => ['ID', 'display_name', 'user_email']
        ]);
        
        return $users;
    }
}

/**
 * ✅ HOOK DE VALIDATION : Vérifie l'unicité des rôles par hôpital
 * Empêche qu'un hôpital ait plusieurs utilisateurs avec le même rôle
 */
add_action('user_profile_update_errors', function($errors, $update, $user) {
    if (!$update || empty($user->ID)) return;

    global $wpdb;

    $user_id = intval($user->ID);
    
    // Récupère le rôle de l'utilisateur
    $role = !empty($user->roles) ? $user->roles[0] : '';
    
    // Liste des rôles à contrôler
    $restricted_roles = ['directeur_hopital', 'administrateur_hopital', 'medecin', 'infirmier'];
    
    if (!in_array($role, $restricted_roles)) {
        return; // Ce rôle n'est pas concerné
    }

    // Récupère l'hôpital assigné à cet utilisateur
    $hospital_id = get_user_meta($user_id, 'hospital_id', true);
    
    if (!$hospital_id) {
        return; // Pas d'hôpital assigné, pas de contrainte
    }

    // ✅ REQUÊTE SÉCURISÉE avec prepare()
    $meta_key = $wpdb->prefix . 'capabilities';
    
    $existing = $wpdb->get_var($wpdb->prepare("
        SELECT u.ID
        FROM {$wpdb->users} u
        INNER JOIN {$wpdb->usermeta} m_role ON u.ID = m_role.user_id
        INNER JOIN {$wpdb->usermeta} m_hosp ON u.ID = m_hosp.user_id
        WHERE m_role.meta_key = %s
          AND m_role.meta_value LIKE %s
          AND m_hosp.meta_key = 'hospital_id'
          AND m_hosp.meta_value = %s
          AND u.ID != %d
        LIMIT 1
    ", $meta_key, '%' . $wpdb->esc_like($role) . '%', $hospital_id, $user_id));

    if ($existing) {
        $role_label = ucfirst(str_replace('_', ' ', $role));
        $hospital_name = get_the_title($hospital_id) ?: 'cet hôpital';
        
        $errors->add(
            'he_role_conflict', 
            sprintf(
                '<strong>Erreur :</strong> Il existe déjà un <strong>%s</strong> pour <strong>%s</strong>. Un seul utilisateur par profil et par hôpital est autorisé.',
                esc_html($role_label),
                esc_html($hospital_name)
            )
        );
        
        HE_Utils::log("⚠️ Tentative de doublon bloquée : {$role_label} pour hôpital #{$hospital_id}");
    }
}, 10, 3);

/**
 * ✅ HOOK SUPPLÉMENTAIRE : Validation lors de l'assignation d'un hôpital via ACF
 */
add_filter('acf/validate_value/key=field_hospital_id', function($valid, $value, $field, $input) {
    if (!$valid || empty($value)) return $valid;
    
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    if (!$user_id) return $valid;
    
    $user = get_userdata($user_id);
    if (!$user) return $valid;
    
    $role = !empty($user->roles) ? $user->roles[0] : '';
    $restricted_roles = ['directeur_hopital', 'administrateur_hopital', 'medecin', 'infirmier'];
    
    if (!in_array($role, $restricted_roles)) {
        return $valid;
    }
    
    // Vérifie si un autre utilisateur du même rôle a déjà cet hôpital
    $existing_users = HE_Roles::get_users_by_role_and_hospital($role, $value);
    
    foreach ($existing_users as $existing_user) {
        if ($existing_user->ID != $user_id) {
            $role_label = ucfirst(str_replace('_', ' ', $role));
            $hospital_name = get_the_title($value) ?: 'cet hôpital';
            
            return sprintf(
                'Un <strong>%s</strong> est déjà assigné à <strong>%s</strong> (%s). Veuillez choisir un autre hôpital.',
                esc_html($role_label),
                esc_html($hospital_name),
                esc_html($existing_user->display_name)
            );
        }
    }
    
    return $valid;
}, 10, 4);
