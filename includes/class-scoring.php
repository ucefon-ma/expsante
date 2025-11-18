<?php
if (!defined('ABSPATH')) exit;

class HE_Shortcodes {
    
    public static function register() {
        add_shortcode('hospital_form', [self::class, 'form_shortcode']);
        add_shortcode('hospital_dashboard', [self::class, 'dashboard_shortcode']);
        add_shortcode('hospital_dashboard_directeur', [self::class, 'dashboard_directeur_shortcode']);
        add_shortcode('hospital_graphs', [self::class, 'graphs_shortcode']); // ✅ NOUVEAU
    }

    /**
     * 📋 Formulaire d'évaluation
     */
    public static function form_shortcode() {
        ob_start();
        include HE_PATH . 'templates/form-evaluation.php';
        return ob_get_clean();
    }

    /**
     * 📊 Tableau de bord des utilisateurs (Médecin / Infirmier / Administrateur)
     */
    public static function dashboard_shortcode() {
        if (!is_user_logged_in()) {
            return '<div class="he-warning" style="padding:15px;background:#fee2e2;color:#991b1b;border-radius:8px;">
                        ⚠️ Vous devez être connecté pour consulter ce tableau.
                    </div>';
        }

        $user = wp_get_current_user();
        $roles = (array) $user->roles;

        // Rôles autorisés pour ce tableau
        $allowed_roles = ['medecin', 'infirmier', 'administrateur_hopital', 'directeur_hopital'];

        if (!array_intersect($roles, $allowed_roles)) {
            return '<div class="he-warning" style="padding:15px;background:#fff3cd;color:#856404;border-radius:8px;">
                        🚫 Accès refusé : votre profil ne vous autorise pas à consulter ce tableau.
                    </div>';
        }

        ob_start();
        include HE_PATH . 'templates/dashboard.php';
        return ob_get_clean();
    }

    /**
     * 🏥 Tableau de bord global réservé aux Directeurs d'hôpital
     */
    public static function dashboard_directeur_shortcode() {
        if (!is_user_logged_in()) {
            return '<div class="he-warning" style="padding:15px;background:#fee2e2;color:#991b1b;border-radius:8px;">
                        ⚠️ Vous devez être connecté pour consulter ce tableau.
                    </div>';
        }

        $user = wp_get_current_user();

if ( $user->ID > 0 && ! in_array( 'directeur_hopital', (array) $user->roles ) ) {
    return '<div class="he-warning" style="padding:15px;background:#fff3cd;color:#856404;border-radius:8px;">
        🚫 Accès refusé : ce tableau est réservé aux <strong>Directeurs d\'hôpital</strong>.
    </div>';
}
        

        ob_start();
        include HE_PATH . 'templates/dashboard-directeur.php';
        return ob_get_clean();
    }

    /**
     * 📊 Graphiques de répartition des réponses (NOUVEAU)
     * Shortcode: [hospital_graphs]
     */
    public static function graphs_shortcode() {
        if (!is_user_logged_in()) {
            return '<div class="he-warning" style="padding:15px;background:#fee2e2;color:#991b1b;border-radius:8px;">
                        ⚠️ Vous devez être connecté pour consulter les graphiques.
                    </div>';
        }

        global $wpdb;
        $current_user = wp_get_current_user();
        $user_id = $current_user->ID;
        $roles = (array) $current_user->roles;

        // Vérifier les rôles autorisés
        $allowed_roles = ['directeur_hopital', 'administrateur_hopital', 'medecin', 'infirmier'];
        if (!array_intersect($roles, $allowed_roles)) {
            return '<div class="he-warning" style="padding:15px;background:#fff3cd;color:#856404;border-radius:8px;">
                        🚫 Accès refusé : votre profil ne vous autorise pas à consulter ces graphiques.
                    </div>';
        }

        // Récupérer l'hôpital associé
        $hospital_id = null;
        if (function_exists('get_field')) {
            $acf_hospital = get_field('hospital_id', 'user_' . $user_id);
            $hospital_id = is_array($acf_hospital) ? intval($acf_hospital[0] ?? 0) : intval($acf_hospital);
        }

        if (!$hospital_id) {
            return '<p>Aucun hôpital associé à votre profil.</p>';
        }

        $hospital_name = get_the_title($hospital_id) ?: 'Hôpital';
        $is_directeur = in_array('directeur_hopital', $roles);

        ob_start();
        include HE_PATH . 'templates/graphs.php';
        return ob_get_clean();
    }
}
