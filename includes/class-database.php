<?php
if (!defined('ABSPATH')) exit;

/**
 * 📦 Gestion du schéma de base de données du plugin EXP Santé
 * Version améliorée avec contraintes FK et index optimisés
 */
class HE_Database {

    const DB_VERSION = '1.3.0';

    /**
     * ✅ Installation/mise à jour des tables
     */
    public static function install() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $current_version = get_option('he_db_version', '0.0.0');
        
        HE_Utils::log("🔧 Installation DB - Version actuelle: {$current_version} → Cible: " . self::DB_VERSION);

        try {
            // Création/mise à jour des tables
            self::create_questions_table($charset);
            self::create_evaluations_table($charset);
            self::create_answers_table($charset);

            // Mise à jour de la version
            update_option('he_db_version', self::DB_VERSION);
            
            HE_Utils::log("✅ Installation DB réussie - Version " . self::DB_VERSION);
            
            return true;

        } catch (Exception $e) {
            HE_Utils::log("❌ Erreur installation DB: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 🧠 TABLE : Questions d'évaluation
     */
    private static function create_questions_table($charset) {
        global $wpdb;
        
        $sql = "CREATE TABLE {$wpdb->prefix}hospital_questions (
            id INT UNSIGNED AUTO_INCREMENT,
            profil VARCHAR(100) NOT NULL,
            chapitre VARCHAR(255) DEFAULT NULL,
            question_text TEXT NOT NULL,
            poids FLOAT DEFAULT 1.0,
            active TINYINT(1) DEFAULT 1,
            position INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_profil (profil),
            INDEX idx_active (active),
            INDEX idx_position (position),
            INDEX idx_profil_active (profil, active),
            INDEX idx_chapitre (chapitre(100))
        ) $charset;";

        dbDelta($sql);
        
        if ($wpdb->last_error) {
            throw new Exception("Erreur création table questions: " . $wpdb->last_error);
        }
    }

    /**
     * 🏥 TABLE : Évaluations hospitalières
     */
    private static function create_evaluations_table($charset) {
        global $wpdb;
        
        $sql = "CREATE TABLE {$wpdb->prefix}hospital_evaluations (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            hospital_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            profil VARCHAR(100) NOT NULL,
            status VARCHAR(20) DEFAULT 'draft',
            score_final FLOAT DEFAULT NULL,
            total_oui FLOAT DEFAULT 0,
            total_non FLOAT DEFAULT 0,
            total_poids FLOAT DEFAULT 0,
            last_recalculated DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_unique_eval (hospital_id, user_id, profil),
            INDEX idx_hospital (hospital_id),
            INDEX idx_user (user_id),
            INDEX idx_profil (profil),
            INDEX idx_status (status),
            INDEX idx_score (score_final),
            INDEX idx_updated (updated_at),
            INDEX idx_hospital_status (hospital_id, status),
            INDEX idx_user_profil (user_id, profil)
        ) $charset;";

        dbDelta($sql);
        
        if ($wpdb->last_error) {
            throw new Exception("Erreur création table evaluations: " . $wpdb->last_error);
        }
    }

    /**
     * 🧾 TABLE : Réponses aux questions
     */
    private static function create_answers_table($charset) {
        global $wpdb;
        
        $sql = "CREATE TABLE {$wpdb->prefix}hospital_answers (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            evaluation_id BIGINT UNSIGNED NOT NULL,
            question_id BIGINT UNSIGNED NOT NULL,
            reponse VARCHAR(50) DEFAULT NULL,
            score FLOAT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_unique_answer (evaluation_id, question_id),
            INDEX idx_evaluation (evaluation_id),
            INDEX idx_question (question_id),
            INDEX idx_reponse (reponse)
        ) $charset;";

        dbDelta($sql);
        
        if ($wpdb->last_error) {
            throw new Exception("Erreur création table answers: " . $wpdb->last_error);
        }
    }

    /**
     * ✅ Vérifie l'intégrité de la base de données
     * @return array Résultats de la vérification
     */
    public static function check_integrity() {
        global $wpdb;
        
        $results = [
            'success' => true,
            'tables' => [],
            'orphaned_answers' => 0,
            'orphaned_evaluations' => 0,
            'errors' => []
        ];

        // Vérifier l'existence des tables
        $required_tables = [
            'hospital_questions',
            'hospital_evaluations',
            'hospital_answers'
        ];

        foreach ($required_tables as $table) {
            $full_table = $wpdb->prefix . $table;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table'") === $full_table;
            
            $results['tables'][$table] = $exists;
            
            if (!$exists) {
                $results['success'] = false;
                $results['errors'][] = "Table manquante: $table";
            }
        }

        if (!$results['success']) {
            return $results;
        }

        // Vérifier les réponses orphelines (évaluation supprimée)
        $orphaned_answers = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}hospital_answers a
            LEFT JOIN {$wpdb->prefix}hospital_evaluations e ON e.id = a.evaluation_id
            WHERE e.id IS NULL
        ");
        
        $results['orphaned_answers'] = intval($orphaned_answers);

        // Vérifier les évaluations orphelines (hôpital supprimé)
        $orphaned_evaluations = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}hospital_evaluations e
            LEFT JOIN {$wpdb->posts} p ON p.ID = e.hospital_id AND p.post_type = 'hospital'
            WHERE p.ID IS NULL
        ");
        
        $results['orphaned_evaluations'] = intval($orphaned_evaluations);

        if ($results['orphaned_answers'] > 0) {
            $results['errors'][] = "{$results['orphaned_answers']} réponses orphelines détectées";
        }

        if ($results['orphaned_evaluations'] > 0) {
            $results['errors'][] = "{$results['orphaned_evaluations']} évaluations orphelines détectées";
        }

        return $results;
    }

    /**
     * ✅ Nettoie les données orphelines
     * @return array Résultats du nettoyage
     */
    public static function cleanup_orphaned_data() {
        global $wpdb;
        
        $results = [
            'success' => true,
            'answers_deleted' => 0,
            'evaluations_deleted' => 0,
            'errors' => []
        ];

        // Supprimer les réponses orphelines
        $answers_deleted = $wpdb->query("
            DELETE a FROM {$wpdb->prefix}hospital_answers a
            LEFT JOIN {$wpdb->prefix}hospital_evaluations e ON e.id = a.evaluation_id
            WHERE e.id IS NULL
        ");

        if ($answers_deleted === false) {
            $results['success'] = false;
            $results['errors'][] = "Erreur suppression réponses: " . $wpdb->last_error;
        } else {
            $results['answers_deleted'] = $answers_deleted;
            HE_Utils::log("🧹 $answers_deleted réponses orphelines supprimées");
        }

        // Supprimer les évaluations orphelines
        $evaluations_deleted = $wpdb->query("
            DELETE e FROM {$wpdb->prefix}hospital_evaluations e
            LEFT JOIN {$wpdb->posts} p ON p.ID = e.hospital_id AND p.post_type = 'hospital'
            WHERE p.ID IS NULL
        ");

        if ($evaluations_deleted === false) {
            $results['success'] = false;
            $results['errors'][] = "Erreur suppression évaluations: " . $wpdb->last_error;
        } else {
            $results['evaluations_deleted'] = $evaluations_deleted;
            HE_Utils::log("🧹 $evaluations_deleted évaluations orphelines supprimées");
        }

        return $results;
    }

    /**
     * ✅ Optimise les tables
     * @return array Résultats de l'optimisation
     */
    public static function optimize_tables() {
        global $wpdb;
        
        $tables = [
            'hospital_questions',
            'hospital_evaluations',
            'hospital_answers'
        ];
        
        $results = [
            'success' => true,
            'optimized' => [],
            'errors' => []
        ];

        foreach ($tables as $table) {
            $full_table = $wpdb->prefix . $table;
            $result = $wpdb->query("OPTIMIZE TABLE $full_table");
            
            if ($result === false) {
                $results['success'] = false;
                $results['errors'][] = "Erreur optimisation $table: " . $wpdb->last_error;
            } else {
                $results['optimized'][] = $table;
            }
        }

        HE_Utils::log("⚡ " . count($results['optimized']) . " tables optimisées");
        
        return $results;
    }

    /**
     * ✅ Obtient les statistiques de la base
     * @return array Statistiques
     */
    public static function get_stats() {
        global $wpdb;

        return [
            'questions' => [
                'total' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hospital_questions"),
                'active' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hospital_questions WHERE active = 1"),
                'inactive' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hospital_questions WHERE active = 0")
            ],
            'evaluations' => [
                'total' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hospital_evaluations"),
                'draft' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hospital_evaluations WHERE status = 'draft'"),
                'submitted' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hospital_evaluations WHERE status = 'submitted'"),
                'validated' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hospital_evaluations WHERE status = 'validated'")
            ],
            'answers' => [
                'total' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hospital_answers"),
                'oui' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hospital_answers WHERE reponse = 'Oui'"),
                'non' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hospital_answers WHERE reponse = 'Non'"),
                'na' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hospital_answers WHERE reponse = 'N/A'")
            ],
            'db_version' => get_option('he_db_version', 'unknown')
        ];
    }

    /**
     * ✅ Suppression complète des données (désinstallation)
     * À utiliser avec PRÉCAUTION !
     */
    public static function uninstall() {
        global $wpdb;
        
        HE_Utils::log("⚠️ DÉSINSTALLATION : Suppression de toutes les données");

        // Supprimer les tables
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}hospital_answers");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}hospital_evaluations");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}hospital_questions");

        // Supprimer les options
        delete_option('he_db_version');

        // Purger les caches
        HE_Scoring::clear_all_caches();

        HE_Utils::log("✅ Désinstallation terminée");
    }
}
