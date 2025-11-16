<?php
if (!defined('ABSPATH')) exit;

/**
 * Classe HE_Scoring
 * Calcule et met en cache le score d'une évaluation hospitalière
 */
class HE_Scoring {

    /**
     * Calcule (ou récupère) le score d'une évaluation
     */
    public static function calculate_score($evaluation_id) {
        global $wpdb;

        $evaluation_id = intval($evaluation_id);
        if (!$evaluation_id) {
            return 0;
        }

        $cache_key = 'he_score_' . $evaluation_id;

        // Vérifie d'abord dans le cache transient
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return floatval($cached);
        }

        // Récupère toutes les réponses de cette évaluation
        $answers = $wpdb->get_results($wpdb->prepare("
            SELECT a.reponse, q.poids 
            FROM {$wpdb->prefix}hospital_answers a
            INNER JOIN {$wpdb->prefix}hospital_questions q 
                ON q.id = a.question_id
            WHERE a.evaluation_id = %d
            AND q.active = 1
        ", $evaluation_id));

        if (empty($answers)) {
            error_log("[HE_SCORE] Aucune réponse trouvée pour évaluation $evaluation_id");
            return 0;
        }

        // Calcul du score pondéré - N/A EXCLUS
        $total_poids = 0;
        $scored_poids = 0;
        $count_oui = 0;
        $count_non = 0;
        $count_na = 0;

        foreach ($answers as $a) {
            $poids = floatval($a->poids);
            if ($poids <= 0) continue;

            // Les N/A ne comptent ni pour ni contre
            if ($a->reponse === 'Oui') {
                $total_poids += $poids;
                $scored_poids += $poids;
                $count_oui++;
            } elseif ($a->reponse === 'Non') {
                $total_poids += $poids;
                $count_non++;
            } elseif ($a->reponse === 'N/A') {
                $count_na++;
            }
        }

        $score = $total_poids > 0 ? round(($scored_poids / $total_poids) * 100, 2) : 0;

        // Mise à jour du score dans la base
        $wpdb->update(
            "{$wpdb->prefix}hospital_evaluations",
            [
                'score_final'       => $score,
                'total_oui'         => $count_oui,
                'total_non'         => $count_non,
                'total_poids'       => $total_poids,
                'last_recalculated' => current_time('mysql')
            ],
            ['id' => $evaluation_id],
            ['%f', '%d', '%d', '%f', '%s'],
            ['%d']
        );

        // Met le score en cache pour 12 heures
        set_transient($cache_key, $score, 12 * HOUR_IN_SECONDS);

        error_log("[HE_SCORE] Score calculé pour eval #$evaluation_id = $score% (Oui:$count_oui Non:$count_non N/A:$count_na exclus)");
        
        return $score;
    }

    /**
     * Supprime le cache d'une évaluation
     */
    public static function clear_cache($evaluation_id) {
        delete_transient('he_score_' . intval($evaluation_id));
        error_log("[HE_SCORE] Cache vidé pour évaluation #$evaluation_id");
    }

    /**
     * Recalcule tous les scores
     */
    public static function recalculate_all() {
        global $wpdb;
        
        $evaluations = $wpdb->get_col("
            SELECT id FROM {$wpdb->prefix}hospital_evaluations
            ORDER BY id ASC
        ");
        
        if (empty($evaluations)) {
            return 0;
        }
        
        $count = 0;
        foreach ($evaluations as $eval_id) {
            self::clear_cache($eval_id);
            self::calculate_score($eval_id);
            $count++;
        }
        
        error_log("[HE_SCORE] $count scores recalculés avec succès");
        return $count;
    }
}