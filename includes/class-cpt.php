<?php
if (!defined('ABSPATH')) exit;

class HE_CPT {
    public static function register_cpt() {

        $labels = [
            'name'               => 'Hôpitaux',
            'singular_name'      => 'Hôpital',
            'menu_name'          => 'Hôpitaux',
            'add_new'            => 'Ajouter un hôpital',
            'add_new_item'       => 'Ajouter un nouvel hôpital',
            'new_item'           => 'Nouvel hôpital',
            'edit_item'          => 'Modifier l’hôpital',
            'view_item'          => 'Voir l’hôpital',
            'all_items'          => 'Liste des hôpitaux',
            'search_items'       => 'Rechercher un hôpital',
            'not_found'          => 'Aucun hôpital trouvé',
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'show_ui'            => true,
            'show_in_menu'       => 'exp_sante_dashboard', // 🧠 rattache au menu EXP Santé
            'menu_position'      => null,
            'supports'           => ['title', 'editor'],
            'capability_type'    => 'post',
            'has_archive'        => false,
            'rewrite'            => ['slug' => 'hopital'],
        ];

        register_post_type('hospital', $args);
    }
}
