<?php
if (!defined('ABSPATH')) exit;

class HE_Shortcodes {
    
    public static function register() {
        add_shortcode('hospital_form', [self::class, 'form_shortcode']);
        add_shortcode('hospital_dashboard', [self::class, 'dashboard_shortcode']);
        add_shortcode('hospital_dashboard_directeur', [self::class, 'dashboard_directeur_shortcode']);
        add_shortcode('hospital_graphs', [self::class, 'graphs_shortcode']);
        add_shortcode('hospital_name', [self::class, 'hospital_name_shortcode']); 
        add_shortcode('hospital_terms', [self::class, 'hospital_terms_shortcode']);
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
     * 📊 Graphiques de répartition des réponses
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

    /**
     * 🏥 Affiche le nom de l'hôpital depuis son ID
     * Shortcode: [hospital_name] ou [hospital_name id="16"]
     * 
     * Usage dans Bricks:
     * [hospital_name id="{acf_hospital_id}"]
     * ou simplement [hospital_name] si le champ ACF hospital_id existe sur la page
     */
    public static function hospital_name_shortcode($atts) {
        // Paramètres par défaut
        $atts = shortcode_atts([
            'id' => null,
            'default' => '—' // Texte par défaut si pas trouvé
        ], $atts);

        // Si pas d'ID fourni, essayer de récupérer depuis ACF
        $hospital_id = $atts['id'];
        
        if (!$hospital_id && function_exists('get_field')) {
            $hospital_id = get_field('hospital_id');
        }

        // Convertir en entier
        $hospital_id = intval($hospital_id);

        // Si toujours pas d'ID, retourner le texte par défaut
        if (!$hospital_id) {
            return esc_html($atts['default']);
        }

        // Vérifier que c'est bien un post de type 'hospital'
        $post_type = get_post_type($hospital_id);
        if ($post_type !== 'hospital') {
            return esc_html($atts['default']);
        }

        // Récupérer le titre de l'hôpital
        $hospital_name = get_the_title($hospital_id);

        // Si pas de titre, retourner le texte par défaut
        if (!$hospital_name) {
            return esc_html($atts['default']);
        }

        return esc_html($hospital_name);
    }

    /**
     * 🏷️ Affiche les termes d'une taxonomie pour un hôpital
     * Shortcode: [hospital_terms] ou [hospital_terms id="16" taxonomy="ville"]
     * 
     * Paramètres:
     * - id: ID de l'hôpital (optionnel, prend depuis ACF si non fourni)
     * - taxonomy: Nom de la taxonomie (par défaut: toutes les taxonomies de 'hospital')
     * - separator: Séparateur entre les termes (par défaut: ', ')
     * - link: Afficher avec liens (yes/no, par défaut: no)
     * - default: Texte si aucun terme (par défaut: '—')
     * 
     * Usage dans Bricks:
     * [hospital_terms id="{acf_hospital_id}" taxonomy="ville"]
     * [hospital_terms id="{acf_hospital_id}" taxonomy="ville" link="yes"]
     * [hospital_terms id="{acf_hospital_id}" taxonomy="ville" separator=" | "]
     */
    public static function hospital_terms_shortcode($atts) {
        // Paramètres par défaut
        $atts = shortcode_atts([
            'id' => null,
            'taxonomy' => '', // Si vide, prend toutes les taxonomies
            'separator' => ', ',
            'link' => 'no',
            'default' => '—'
        ], $atts);

        // Récupérer l'ID de l'hôpital
        $hospital_id = $atts['id'];
        
        if (!$hospital_id && function_exists('get_field')) {
            $hospital_id = get_field('hospital_id');
        }

        $hospital_id = intval($hospital_id);

        if (!$hospital_id) {
            return esc_html($atts['default']);
        }

        // Vérifier que c'est bien un post de type 'hospital'
        if (get_post_type($hospital_id) !== 'hospital') {
            return esc_html($atts['default']);
        }

        $output = [];

        // Si une taxonomie spécifique est demandée
        if (!empty($atts['taxonomy'])) {
            $terms = get_the_terms($hospital_id, $atts['taxonomy']);
            
            if ($terms && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    if ($atts['link'] === 'yes') {
                        $output[] = '<a href="' . esc_url(get_term_link($term)) . '">' . esc_html($term->name) . '</a>';
                    } else {
                        $output[] = esc_html($term->name);
                    }
                }
            }
        } else {
            // Sinon, récupérer toutes les taxonomies du post type 'hospital'
            $taxonomies = get_object_taxonomies('hospital', 'objects');
            
            foreach ($taxonomies as $taxonomy) {
                $terms = get_the_terms($hospital_id, $taxonomy->name);
                
                if ($terms && !is_wp_error($terms)) {
                    foreach ($terms as $term) {
                        if ($atts['link'] === 'yes') {
                            $output[] = '<a href="' . esc_url(get_term_link($term)) . '">' . esc_html($term->name) . '</a>';
                        } else {
                            $output[] = esc_html($term->name);
                        }
                    }
                }
            }
        }

        // Si aucun terme trouvé
        if (empty($output)) {
            return esc_html($atts['default']);
        }

        return implode($atts['separator'], $output);
    }
}
