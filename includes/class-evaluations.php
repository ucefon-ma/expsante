<?php
if (!defined('ABSPATH')) exit;

/**
 * Gestion des évaluations et des réponses
 * Version améliorée avec transactions et gestion d'erreurs
 */
class HE_Evaluations {

    /**
     * ✅ Récupère ou crée une évaluation unique
     * Retourne l'ID de l'évaluation ou FALSE en cas d'erreur
     */
    public static function get_or_create_evaluation($hospital_id, $user_id, $profil) {
        global $wpdb;

        $hospital_id = intval($hospital_id);
        $user_id = intval($user_id);
        $profil = sanitize_text_field($profil);

        // Validation des paramètres
        if (!$hospital_id || !$user_id || empty($profil)) {
            HE_Utils::log("❌ Paramètres invalides pour get_or_create_evaluation");
            return false;
        }

        $table = "{$wpdb->prefix}hospital_evaluations";

        // Vérifie si déjà existante
        $existing = $wpdb->get_row($wpdb->prepare("
            SELECT id FROM $table
            WHERE hospital_id = %d 
            AND user_id = %d 
            AND profil = %s
            LIMIT 1
        ", $hospital_id, $user_id, $profil));

        if ($existing) {
            HE_Utils::log("♻️ Évaluation existante #" . $existing->id);
            return intval($existing->id);
        }

        // Crée une nouvelle évaluation
        $result = $wpdb->insert(
            $table,
            [
                'hospital_id' => $hospital_id,
                'user_id'     => $user_id,
                'profil'      => $profil,
                'status'      => 'draft',
                'created_at'  => current_time('mysql'),
                'updated_at'  => current_time('mysql')
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s']
        );

        if ($result === false) {
            HE_Utils::log("❌ Échec création évaluation: " . $wpdb->last_error);
            return false;
        }

        $evaluation_id = $wpdb->insert_id;
        HE_Utils::log("✅ Nouvelle évaluation créée #$evaluation_id");
        
        return $evaluation_id;
    }

    /**
     * ✅ Sauvegarde ou met à jour les réponses avec transaction
     * Retourne TRUE en cas de succès, FALSE en cas d'erreur
     */
    public static function save_answers($evaluation_id, $answers) {
        global $wpdb;

        $evaluation_id = intval($evaluation_id);

        // Validation
        if (!$evaluation_id || empty($answers) || !is_array($answers)) {
            HE_Utils::log("❌ Paramètres invalides pour save_answers");
            return false;
        }

        $table = "{$wpdb->prefix}hospital_answers";
        
        // ✅ Début de la transaction
        $wpdb->query('START TRANSACTION');

        try {
            $success_count = 0;
            
            foreach ($answers as $question_id => $value) {
                $question_id = intval($question_id);
                $value = sanitize_text_field($value);
                
                if (!$question_id) {
                    HE_Utils::log("⚠️ Question ID invalide ignoré");
                    continue;
                }

                // REPLACE pour éviter les doublons
                $result = $wpdb->replace(
                    $table,
                    [
                        'evaluation_id' => $evaluation_id,
                        'question_id'   => $question_id,
                        'reponse'       => $value,
                        'created_at'    => current_time('mysql')
                    ],
                    ['%d', '%d', '%s', '%s']
                );

                if ($result === false) {
                    throw new Exception("Échec sauvegarde question #$question_id: " . $wpdb->last_error);
                }
                
                $success_count++;
            }

            // Met à jour la date de modification de l'évaluation
            $update_result = $wpdb->update(
                "{$wpdb->prefix}hospital_evaluations",
                ['updated_at' => current_time('mysql')],
                ['id' => $evaluation_id],
                ['%s'],
                ['%d']
            );

            if ($update_result === false) {
                throw new Exception("Échec mise à jour timestamp évaluation: " . $wpdb->last_error);
            }

            // ✅ Valide la transaction
            $wpdb->query('COMMIT');

            // Vide le cache et recalcule le score
            HE_Scoring::clear_cache($evaluation_id);
            $score = HE_Scoring::calculate_score($evaluation_id);

            HE_Utils::log("💾 $success_count réponses sauvegardées pour éval #$evaluation_id (Score: {$score}%)");
            
            return true;

        } catch (Exception $e) {
            // ❌ Annule la transaction en cas d'erreur
            $wpdb->query('ROLLBACK');
            HE_Utils::log("❌ Transaction annulée pour éval #$evaluation_id : " . $e->getMessage());
            return false;
        }
    }

    /**
     * ✅ Met à jour le statut d'une évaluation
     * Retourne TRUE en cas de succès, FALSE sinon
     */
    public static function update_status($evaluation_id, $status) {
        global $wpdb;

        $evaluation_id = intval($evaluation_id);
        $status = sanitize_text_field($status);

        // Validation du statut
        $valid_statuses = ['draft', 'submitted', 'validated', 'rejected'];
        if (!in_array($status, $valid_statuses)) {
            HE_Utils::log("⚠️ Statut invalide : $status");
            return false;
        }

        $result = $wpdb->update(
            "{$wpdb->prefix}hospital_evaluations",
            [
                'status'     => $status,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $evaluation_id],
            ['%s', '%s'],
            ['%d']
        );

        if ($result === false) {
            HE_Utils::log("❌ Échec mise à jour statut pour éval #$evaluation_id : " . $wpdb->last_error);
            return false;
        }

        HE_Utils::log("✅ Statut mis à jour pour éval #$evaluation_id : $status");
        return true;
    }

    /**
     * ✅ Récupère une évaluation par son ID
     */
    public static function get_by_id($evaluation_id) {
        global $wpdb;

        $evaluation_id = intval($evaluation_id);
        if (!$evaluation_id) return null;

        $evaluation = $wpdb->get_row($wpdb->prepare("
            SELECT * 
            FROM {$wpdb->prefix}hospital_evaluations
            WHERE id = %d
        ", $evaluation_id));

        return $evaluation;
    }

    /**
     * ✅ Récupère les réponses d'une évaluation
     */
    public static function get_answers($evaluation_id) {
        global $wpdb;

        $evaluation_id = intval($evaluation_id);
        if (!$evaluation_id) return [];

        $answers = $wpdb->get_results($wpdb->prepare("
            SELECT a.*, q.question_text, q.profil, q.chapitre
            FROM {$wpdb->prefix}hospital_answers a
            INNER JOIN {$wpdb->prefix}hospital_questions q ON q.id = a.question_id
            WHERE a.evaluation_id = %d
            ORDER BY q.position, q.id
        ", $evaluation_id));

        return $answers ?: [];
    }

    /**
     * ✅ Nettoie les doublons (maintenance)
     * Retourne le nombre de doublons supprimés
     */
    public static function cleanup_duplicates() {
        global $wpdb;
        
        $table = "{$wpdb->prefix}hospital_evaluations";

        // ✅ Requête sécurisée avec identifiants de table
        $deleted = $wpdb->query("
            DELETE e1 FROM {$table} e1
            INNER JOIN {$table} e2
            WHERE e1.id < e2.id
              AND e1.hospital_id = e2.hospital_id
              AND e1.user_id = e2.user_id
              AND e1.profil = e2.profil
        ");

        if ($deleted === false) {
            HE_Utils::log("❌ Échec cleanup doublons : " . $wpdb->last_error);
            return 0;
        }

        if ($deleted > 0) {
            HE_Utils::log("🧹 $deleted doublon(s) supprimé(s)");
        }

        return $deleted;
    }

    /**
     * ✅ Supprime une évaluation et toutes ses réponses
     * Retourne TRUE en cas de succès
     */
    public static function delete_evaluation($evaluation_id) {
        global $wpdb;

        $evaluation_id = intval($evaluation_id);
        if (!$evaluation_id) return false;

        // Transaction pour garantir la cohérence
        $wpdb->query('START TRANSACTION');

        try {
            // Supprime les réponses
            $answers_deleted = $wpdb->delete(
                "{$wpdb->prefix}hospital_answers",
                ['evaluation_id' => $evaluation_id],
                ['%d']
            );

            // Supprime l'évaluation
            $eval_deleted = $wpdb->delete(
                "{$wpdb->prefix}hospital_evaluations",
                ['id' => $evaluation_id],
                ['%d']
            );

            if ($eval_deleted === false) {
                throw new Exception("Échec suppression évaluation");
            }

            // Vide le cache
            HE_Scoring::clear_cache($evaluation_id);

            $wpdb->query('COMMIT');
            
            HE_Utils::log("🗑️ Évaluation #$evaluation_id supprimée avec $answers_deleted réponses");
            return true;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            HE_Utils::log("❌ Échec suppression éval #$evaluation_id : " . $e->getMessage());
            return false;
        }
    }

    /**
     * ✅ Récupère les évaluations d'un utilisateur
     */
    public static function get_user_evaluations($user_id, $status = null) {
        global $wpdb;

        $user_id = intval($user_id);
        if (!$user_id) return [];

        $where = "user_id = %d";
        $params = [$user_id];

        if ($status) {
            $where .= " AND status = %s";
            $params[] = sanitize_text_field($status);
        }

        $evaluations = $wpdb->get_results($wpdb->prepare("
            SELECT e.*, h.post_title as hospital_name
            FROM {$wpdb->prefix}hospital_evaluations e
            LEFT JOIN {$wpdb->posts} h ON h.ID = e.hospital_id
            WHERE $where
            ORDER BY e.updated_at DESC
        ", $params));

        return $evaluations ?: [];
    }
}
