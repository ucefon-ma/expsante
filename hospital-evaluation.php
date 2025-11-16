<?php
/**
 * Plugin Name: Hospital Evaluation System
 * Description: Système complet d’évaluation hospitalière multi-profils (Admin App, Directeur, Administrateur Hôpital, Médecin, Infirmier).
 * Version: 2.0.1
 * Author: Ucefon
 */

if (!defined('ABSPATH')) exit;

// ==========================================================
// 🔹 Définition des chemins constants
// ==========================================================
define('HE_PATH', plugin_dir_path(__FILE__));
define('HE_URL', plugin_dir_url(__FILE__));

// ==========================================================
// 🔹 Inclusion des classes principales
// ==========================================================
require_once HE_PATH . 'includes/class-roles.php';
require_once HE_PATH . 'includes/class-cpt.php';
require_once HE_PATH . 'includes/class-database.php';
require_once HE_PATH . 'includes/class-questions.php';
require_once HE_PATH . 'includes/class-evaluations.php';
require_once HE_PATH . 'includes/class-scoring.php';
require_once HE_PATH . 'includes/class-shortcodes.php';
require_once HE_PATH . 'includes/class-admin-panel.php';
require_once HE_PATH . 'includes/class-rest-api.php';
require_once HE_PATH . 'includes/class-utils.php';

// ==========================================================
// 🧩 Installation du plugin et mise à jour de la DB
// ==========================================================
function he_install_plugin() {
    HE_Roles::register_roles();

    $current_version = get_option('he_db_version', '1.0.0');
    $new_version     = '1.2.0'; // version du schéma SQL actuelle

    // 🧱 Exécute la création / mise à jour des tables
    if (version_compare($current_version, $new_version, '<')) {
        HE_Database::install();
        update_option('he_db_version', $new_version);
        error_log("[HE_INSTALL] ✅ DB mise à jour vers la version {$new_version}");
    }
}
register_activation_hook(__FILE__, 'he_install_plugin');

// ==========================================================
// 🧹 Désactivation du plugin
// ==========================================================
register_deactivation_hook(__FILE__, function() {
    HE_Roles::remove_roles();
});

// ==========================================================
// 🚀 Initialisation
// ==========================================================
add_action('init', [HE_CPT::class, 'register_cpt']);
add_action('init', [HE_Shortcodes::class, 'register']);
add_action('admin_menu', [HE_Admin_Panel::class, 'add_admin_pages']);

// ==========================================================
// 💾 Vérifie la version de la base à chaque chargement (auto-update)
// ==========================================================
add_action('plugins_loaded', function() {
    $current_version = get_option('he_db_version', '1.0.0');
    $new_version     = '1.2.0';
    if (version_compare($current_version, $new_version, '<')) {
        HE_Database::install();
        update_option('he_db_version', $new_version);
        error_log("[HE_UPDATE] 🧱 DB mise à jour automatiquement (v{$current_version} → v{$new_version})");
    }
});

// ==========================================================
// 🎨 Chargement des scripts front-end
// ==========================================================
add_action('wp_enqueue_scripts', function() {
    // JS du formulaire
    wp_enqueue_script('he-form', HE_URL . 'assets/js/form.js', [], '1.1.0', true);

    // CSS front-end
    wp_enqueue_style('he-style', HE_URL . 'assets/css/style.css', [], '1.0.0');

    // Localisation des variables REST
    wp_localize_script('he-form', 'he_rest', [
        'nonce' => wp_create_nonce('wp_rest'),
        'url'   => rest_url('he/v1/save'),
        'profil' => ucfirst(str_replace('_', ' ', wp_get_current_user()->roles[0] ?? 'Inconnu')),
    ]);
});

// ==========================================================
// 🌐 Enregistrement des routes REST
// ==========================================================
add_action('rest_api_init', [HE_Rest_API::class, 'register_routes']);

// ==========================================================
// 🧭 Chargement du style admin + icônes EXP Santé
// ==========================================================
add_action('admin_enqueue_scripts', function($hook) {
    if (strpos($hook, 'exp_sante_') !== false || strpos($hook, 'hospital_') !== false) {
        $plugin_url = plugin_dir_url(__FILE__);

        // 🎨 Feuille de style admin
        wp_enqueue_style('exp-sante-admin', $plugin_url . 'admin/css/exp-sante-admin.css', [], '1.0.1');

        // 💠 Icônes Lucide
        wp_enqueue_style('lucide-icons', 'https://unpkg.com/lucide-static@latest/font/lucide.css', [], '1.0.0');

        // (Facultatif) Script JS admin
        // wp_enqueue_script('exp-sante-admin-js', $plugin_url . 'admin/js/exp-sante-admin.js', ['jquery'], '1.0.0', true);
    }
});
