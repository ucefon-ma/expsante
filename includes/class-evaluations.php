<?php
if (!defined('ABSPATH')) exit;

/**
 * Gestion des évaluations et des réponses
 */
class HE_Evaluations {

    /**
     * Récupère ou crée une évaluation unique
     */
    public static function get_or_create_evaluation($hospital_id, $user_id, $profil) {
        global $wpdb;

        $table = "{$wpdb->prefix}hospital_evaluations";
        $hospital_id = intval($hospital_id);
        $user_id = intval($user_id);
        $profil = sanitize_text_field($profil);

        // Vérifie si déjà existante
        $existing = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $table
            WHERE hospital_id = %d AND user_id = %d AND profil = %s
            LIMIT 1
        ", $hospital_id, $user_id, $profil));

        if ($existing) {
            return intval($existing->id);
        }

        // Crée une nouvelle évaluation
        $wpdb->insert(
            $table,
            [
                'hospital_id' => $hospital_id,
                'user_id'     => $user_id,
                'profil'      => $profil ?: 'Inconnu',
                'status'      => 'draft',
                'created_at'  => current_time('mysql'),
                'updated_at'  => current_time('mysql')
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s']
        );

        return $wpdb->insert_id;
    }

    /**
     * Sauvegarde ou met à jour les réponses
     */
    public static function save_answers($evaluation_id, $answers) {
        global $wpdb;

        $table = "{$wpdb->prefix}hospital_answers";
        $evaluation_id = intval($evaluation_id);

        if (empty($answers) || !is_array($answers)) return;

        foreach ($answers as $question_id => $value) {
            $wpdb->replace(
                $table,
                [
                    'evaluation_id' => $evaluation_id,
                    'question_id'   => intval($question_id),
                    'reponse'       => sanitize_text_field($value)
                ],
                ['%d', '%d', '%s']
            );
        }

        // 🔄 Met à jour la date de modification
        $wpdb->update(
            "{$wpdb->prefix}hospital_evaluations",
            ['updated_at' => current_time('mysql')],
            ['id' => $evaluation_id],
            ['%s'],
            ['%d']
        );

        // 🧹 Vide le cache du score + recalcul
        HE_Scoring::clear_cache($evaluation_id);
        $score = HE_Scoring::calculate_score($evaluation_id);

        error_log("[HE_EVAL] 💾 Réponses sauvegardées et score recalculé pour évaluation #$evaluation_id ($score%)");
    }

    /**
     * Met à jour le statut
     */
    public static function update_status($evaluation_id, $status) {
        global $wpdb;

        $wpdb->update(
            "{$wpdb->prefix}hospital_evaluations",
            [
                'status'     => sanitize_text_field($status),
                'updated_at' => current_time('mysql')
            ],
            ['id' => intval($evaluation_id)],
            ['%s', '%s'],
            ['%d']
        );
    }

    /**
     * Nettoie les doublons (sécurité)
     */
    public static function cleanup_duplicates() {
        global $wpdb;
        $table = "{$wpdb->prefix}hospital_evaluations";

        $deleted = $wpdb->query("
            DELETE e1 FROM $table e1
            INNER JOIN $table e2
            WHERE e1.id < e2.id
              AND e1.hospital_id = e2.hospital_id
              AND e1.user_id = e2.user_id
              AND e1.profil = e2.profil
        ");

        if ($deleted > 0) {
            error_log("[HE_EVAL] 🧹 $deleted doublon(s) supprimé(s)");
        }
    }
}
