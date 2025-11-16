<?php 
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Gestion des questions d'évaluation hospitalière
 */
class HE_Questions {

    /**
     * Récupère les questions en fonction du profil utilisateur.
     * Compatible avec les rôles WordPress et les libellés visibles en base.
     *
     * @param string $profil Rôle utilisateur (ex: directeur_hopital, medecin, etc.)
     * @return array Liste des questions actives pour le profil
     */
    public static function get_by_profile($profil) {
    global $wpdb;

    // Vérification du rôle reçu
    error_log("[HE_QUESTIONS_DEBUG] rôle brut reçu = " . print_r($profil, true));

    $mapping = [
        'directeur_hopital'       => 'Directeur',
        'administrateur_hopital'  => 'Administrateur Hôpital',
        'medecin'                 => 'Médecin',
        'infirmier'               => 'Infirmier'
    ];

    $profil_clean = strtolower(trim($profil));
    $profil_label = $mapping[$profil_clean] ?? ucfirst($profil);

    error_log("[HE_QUESTIONS_DEBUG] profil_label utilisé pour SQL = " . $profil_label);

    $query = $wpdb->prepare("
        SELECT * 
        FROM {$wpdb->prefix}hospital_questions
        WHERE profil = %s
          AND active = 1
        ORDER BY chapitre ASC, id ASC
    ", $profil_label);

    $results = $wpdb->get_results($query);

    error_log("[HE_QUESTIONS_DEBUG] nombre de questions trouvées = " . count($results));

    return $results;
}



    /**
     * Récupère toutes les questions (utilisé côté admin)
     */
    public static function get_all() {
        global $wpdb;
        return $wpdb->get_results("
            SELECT * 
            FROM {$wpdb->prefix}hospital_questions 
            ORDER BY profil, id ASC
        ");
    }

    /**
     * Ajoute une nouvelle question (utilisé dans le back-office)
     */
    public static function add_question($profil, $texte, $poids = 1.0, $active = true) {
        global $wpdb;
        $inserted = $wpdb->insert("{$wpdb->prefix}hospital_questions", [
            'profil'        => sanitize_text_field($profil),
            'question_text' => sanitize_textarea_field($texte),
            'poids'         => floatval($poids),
            'active'        => $active ? 1 : 0
        ]);
        return $inserted ? $wpdb->insert_id : false;
    }

    /**
     * Désactive une question sans la supprimer
     */
    public static function deactivate($id) {
        global $wpdb;
        return (bool) $wpdb->update(
            "{$wpdb->prefix}hospital_questions",
            ['active' => 0],
            ['id' => intval($id)]
        );
    }

    /**
     * Supprime définitivement une question
     */
    public static function delete($id) {
        global $wpdb;
        return (bool) $wpdb->delete(
            "{$wpdb->prefix}hospital_questions",
            ['id' => intval($id)]
        );
    }
}
