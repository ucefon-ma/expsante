<?php
if (!defined('ABSPATH')) exit;

/**
 * Classe HE_Scoring
 * Calcule et met en cache le score d'une évaluation hospitalière
 * Version améliorée avec gestion d'erreurs et batch processing
 */
class HE_Scoring {

    /**
     * ✅ Calcule (ou récupère) le score d'une évaluation
     * @param int $evaluation_id ID de l'évaluation
     * @param bool $force_recalculate Forcer le recalcul même si en cache
     * @return float Score en pourcentage (0-100)
     */
    public static function calculate_score($evaluation_id, $force_recalculate = false) {
        global $wpdb;

        $evaluation_id = intval($evaluation_id);
        if (!$evaluation_id) {
            HE_Utils::log("❌ ID évaluation invalide pour calculate_score");
            return 0;
        }

        $cache_key = 'he_score_' . $evaluation_id;

        // Vérifie d'abord dans le cache (sauf si force_recalculate)
        if (!$force_recalculate) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                HE_Utils::log("📦 Score en cache pour éval #$evaluation_id : {$cached}%");
                return floatval($cached);
            }
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

        if ($answers === null) {
            HE_Utils::log("❌ Erreur SQL lors de la récupération des réponses: " . $wpdb->last_error);
            return 0;
        }

        if (empty($answers)) {
            HE_Utils::log("⚠️ Aucune réponse trouvée pour évaluation #$evaluation_id");
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
        $update_result = $wpdb->update(
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

        if ($update_result === false) {
            HE_Utils::log("❌ Échec mise à jour score en DB: " . $wpdb->last_error);
            // On continue quand même, le score est calculé
        }

        // Met le score en cache pour 12 heures
        set_transient($cache_key, $score, 12 * HOUR_IN_SECONDS);

        HE_Utils::log("✅ Score calculé pour éval #$evaluation_id = {$score}% (Oui:{$count_oui} Non:{$count_non} N/A:{$count_na} exclus)");
        
        return $score;
    }

    /**
     * ✅ Supprime le cache d'une évaluation
     * @param int $evaluation_id ID de l'évaluation
     * @return bool Succès de la suppression
     */
    public static function clear_cache($evaluation_id) {
        $evaluation_id = intval($evaluation_id);
        if (!$evaluation_id) return false;

        $deleted = delete_transient('he_score_' . $evaluation_id);
        HE_Utils::log("🧹 Cache " . ($deleted ? "vidé" : "déjà vide") . " pour évaluation #$evaluation_id");
        
        return $deleted;
    }

    /**
     * ✅ Vide tous les caches de scores
     * @return int Nombre de caches supprimés
     */
    public static function clear_all_caches() {
        global $wpdb;
        
        $deleted = $wpdb->query("
            DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_he_score_%' 
            OR option_name LIKE '_transient_timeout_he_score_%'
        ");

        if ($deleted === false) {
            HE_Utils::log("❌ Échec purge des caches: " . $wpdb->last_error);
            return 0;
        }

        HE_Utils::log("🧹 $deleted entrées de cache supprimées");
        return $deleted;
    }

    /**
     * ✅ Recalcule tous les scores (avec batch processing)
     * @param int $batch_size Nombre d'évaluations à traiter par batch
     * @param int $offset Offset de départ
     * @return array Résultats du batch
     */
    public static function recalculate_all($batch_size = 50, $offset = 0) {
        global $wpdb;
        
        $batch_size = intval($batch_size);
        $offset = intval($offset);
        
        if ($batch_size <= 0) $batch_size = 50;
        if ($offset < 0) $offset = 0;
        
        // Récupère un batch d'évaluations
        $evaluations = $wpdb->get_col($wpdb->prepare("
            SELECT id 
            FROM {$wpdb->prefix}hospital_evaluations
            ORDER BY id ASC
            LIMIT %d OFFSET %d
        ", $batch_size, $offset));
        
        if ($evaluations === null) {
            HE_Utils::log("❌ Erreur SQL lors du recalcul batch: " . $wpdb->last_error);
            return [
                'success' => false,
                'processed' => 0,
                'errors' => 1,
                'has_more' => false,
                'message' => 'Erreur base de données'
            ];
        }
        
        if (empty($evaluations)) {
            HE_Utils::log("ℹ️ Aucune évaluation à recalculer (offset: $offset)");
            return [
                'success' => true,
                'processed' => 0,
                'errors' => 0,
                'has_more' => false,
                'message' => 'Aucune évaluation trouvée'
            ];
        }
        
        $processed = 0;
        $errors = 0;
        
        foreach ($evaluations as $eval_id) {
            try {
                self::clear_cache($eval_id);
                $score = self::calculate_score($eval_id, true);
                
                if ($score === false || $score === null) {
                    $errors++;
                    HE_Utils::log("⚠️ Échec recalcul pour éval #$eval_id");
                } else {
                    $processed++;
                }
            } catch (Exception $e) {
                $errors++;
                HE_Utils::log("❌ Exception lors du recalcul de éval #$eval_id: " . $e->getMessage());
            }
        }
        
        $has_more = count($evaluations) === $batch_size;
        
        HE_Utils::log("📊 Batch recalculé: $processed réussites, $errors erreurs (offset: $offset)");
        
        return [
            'success' => $errors === 0,
            'processed' => $processed,
            'errors' => $errors,
            'has_more' => $has_more,
            'next_offset' => $offset + $batch_size,
            'message' => "$processed scores recalculés" . ($errors > 0 ? " ($errors erreurs)" : "")
        ];
    }

    /**
     * ✅ Récupère les statistiques de scoring
     * @return array Statistiques globales
     */
    public static function get_statistics() {
        global $wpdb;

        $stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total_evaluations,
                AVG(score_final) as score_moyen,
                MIN(score_final) as score_min,
                MAX(score_final) as score_max,
                SUM(CASE WHEN score_final >= 75 THEN 1 ELSE 0 END) as excellent,
                SUM(CASE WHEN score_final >= 50 AND score_final < 75 THEN 1 ELSE 0 END) as bon,
                SUM(CASE WHEN score_final < 50 THEN 1 ELSE 0 END) as ameliorer
            FROM {$wpdb->prefix}hospital_evaluations
            WHERE score_final IS NOT NULL
        ");

        if (!$stats) {
            return [
                'total_evaluations' => 0,
                'score_moyen' => 0,
                'score_min' => 0,
                'score_max' => 0,
                'excellent' => 0,
                'bon' => 0,
                'ameliorer' => 0
            ];
        }

        return [
            'total_evaluations' => intval($stats->total_evaluations),
            'score_moyen' => round(floatval($stats->score_moyen), 2),
            'score_min' => floatval($stats->score_min),
            'score_max' => floatval($stats->score_max),
            'excellent' => intval($stats->excellent),
            'bon' => intval($stats->bon),
            'ameliorer' => intval($stats->ameliorer)
        ];
    }

    /**
     * ✅ Obtient le score d'une évaluation sans le recalculer
     * @param int $evaluation_id ID de l'évaluation
     * @return float|null Score ou null si pas calculé
     */
    public static function get_cached_score($evaluation_id) {
        global $wpdb;

        $evaluation_id = intval($evaluation_id);
        if (!$evaluation_id) return null;

        $score = $wpdb->get_var($wpdb->prepare("
            SELECT score_final 
            FROM {$wpdb->prefix}hospital_evaluations
            WHERE id = %d
        ", $evaluation_id));

        return $score !== null ? floatval($score) : null;
    }
}
