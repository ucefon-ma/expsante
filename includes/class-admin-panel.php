<?php
if (!defined('ABSPATH')) exit;

class HE_Admin_Panel {

    public static function add_admin_pages() {

        add_menu_page(
            'EXP Santé',
            'EXP Santé',
            'manage_options', // ✅ Capacité admin
            'exp_sante_dashboard',
            [self::class, 'dashboard_page'],
            'dashicons-heart',
            25
        );

        // Sous-menu : Tableau de bord
        add_submenu_page(
            'exp_sante_dashboard',
            'Tableau de bord',
            'Tableau de bord',
            'manage_options',
            'exp_sante_dashboard',
            [self::class, 'dashboard_page']
        );

        // Sous-menu : Évaluations
        add_submenu_page(
            'exp_sante_dashboard',
            'Évaluations',
            'Évaluations',
            'manage_options',
            'hospital_evaluations',
            [self::class, 'evaluations_page']
        );

        // Sous-menu : Questions
        add_submenu_page(
            'exp_sante_dashboard',
            'Questions',
            'Questions',
            'manage_options',
            'hospital_questions',
            [self::class, 'questions_page']
        );

        // Sous-menu : Statistiques
        add_submenu_page(
            'exp_sante_dashboard',
            'Statistiques',
            'Statistiques',
            'manage_options',
            'hospital_statistics',
            [self::class, 'statistics_page']
        );

        // Sous-menu : Diagnostic
        add_submenu_page(
            'exp_sante_dashboard',
            'Diagnostic',
            'Outils / Diagnostic',
            'manage_options',
            'hospital_diagnostic',
            [self::class, 'diagnostic_page']
        );
    }

    /** Tableau de bord principal */
    public static function dashboard_page() {
        require_once HE_PATH . 'admin/dashboard-page.php';
    }

    /** Page d'évaluations */
    public static function evaluations_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Accès non autorisé.');
        }
        require_once HE_PATH . 'admin/evaluations-page.php';
    }

    /** Page de questions */
    public static function questions_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Accès non autorisé.');
        }
        require_once HE_PATH . 'admin/questions-page.php';
    }

    /** Page statistiques */
    public static function statistics_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Accès non autorisé.');
        }
        require_once HE_PATH . 'admin/statistics-page.php';
    }

    /** Page diagnostic */
    public static function diagnostic_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Accès non autorisé.');
        }
        require_once HE_PATH . 'admin/diagnostic-page.php';
    }
}

// Enregistrement des menus du plugin
add_action('admin_menu', [HE_Admin_Panel::class, 'add_admin_pages']);

/**
 * Réorganiser l'ordre des sous-menus du menu EXP Santé
 */
add_action('admin_menu', function() {
    global $submenu;
    if (!isset($submenu['exp_sante_dashboard'])) return;

    $ordered = [];

    // Identifier les entrées existantes
    foreach ($submenu['exp_sante_dashboard'] as $item) {
        if (strpos($item[2], 'exp_sante_dashboard') !== false) {
            $ordered['dashboard'] = $item;
        } elseif (strpos($item[2], 'hospital_evaluations') !== false) {
            $ordered['evaluations'] = $item;
        } elseif (strpos($item[2], 'hospital_questions') !== false) {
            $ordered['questions'] = $item;
        } elseif (strpos($item[2], 'hospital_statistics') !== false) {
            $ordered['statistics'] = $item; // ✅ ajouté ici
        } elseif (strpos($item[2], 'hospital_diagnostic') !== false) {
            $ordered['diagnostic'] = $item;
        } elseif (strpos($item[2], 'edit.php?post_type=hospital') !== false) {
            $ordered['hopitaux'] = $item;
        }
    }

    // 🔹 Reconstruction du menu dans l’ordre voulu
    $submenu['exp_sante_dashboard'] = [];
    if (isset($ordered['dashboard']))   $submenu['exp_sante_dashboard'][] = $ordered['dashboard'];
    if (isset($ordered['evaluations'])) $submenu['exp_sante_dashboard'][] = $ordered['evaluations'];
    if (isset($ordered['questions']))   $submenu['exp_sante_dashboard'][] = $ordered['questions'];
    if (isset($ordered['statistics']))  $submenu['exp_sante_dashboard'][] = $ordered['statistics']; // ✅ affiché maintenant
    if (isset($ordered['hopitaux']))    $submenu['exp_sante_dashboard'][] = $ordered['hopitaux'];
    if (isset($ordered['diagnostic']))  $submenu['exp_sante_dashboard'][] = $ordered['diagnostic'];
}, 99);

