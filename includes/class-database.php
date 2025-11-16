<?php
if (!defined('ABSPATH')) exit;

/**
 * 📦 Gestion du schéma de base de données du plugin EXP Santé
 */
class HE_Database {

    public static function install() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        /* ==========================================================
           🧠 TABLE : Questions d’évaluation
        ========================================================== */
        $sql_questions = "CREATE TABLE {$wpdb->prefix}hospital_questions (
            id INT AUTO_INCREMENT,
            profil VARCHAR(100) NOT NULL,
            chapitre VARCHAR(255) DEFAULT NULL,
            question_text TEXT NOT NULL,
            poids FLOAT DEFAULT 1,
            active TINYINT(1) DEFAULT 1,
            position INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX profil_idx (profil),
            INDEX active_idx (active),
            INDEX position_idx (position),
            PRIMARY KEY (id)
        ) $charset;";

        /* ==========================================================
           🏥 TABLE : Évaluations hospitalières
        ========================================================== */
        $sql_evaluations = "CREATE TABLE {$wpdb->prefix}hospital_evaluations (
            id BIGINT AUTO_INCREMENT,
            hospital_id BIGINT NOT NULL,
            user_id BIGINT NOT NULL,
            profil VARCHAR(100) NOT NULL,
            status VARCHAR(20) DEFAULT 'draft',
            score_final FLOAT DEFAULT NULL,
            total_oui FLOAT DEFAULT 0,
            total_non FLOAT DEFAULT 0,
            total_poids FLOAT DEFAULT 0,
            last_recalculated DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX hospital_idx (hospital_id),
            INDEX profil_idx (profil),
            INDEX status_idx (status),
            INDEX updated_idx (updated_at),
            PRIMARY KEY (id)
        ) $charset;";

        /* ==========================================================
           🧾 TABLE : Réponses aux questions
        ========================================================== */
        $sql_answers = "CREATE TABLE {$wpdb->prefix}hospital_answers (
            id BIGINT AUTO_INCREMENT,
            evaluation_id BIGINT NOT NULL,
            question_id BIGINT NOT NULL,
            reponse VARCHAR(50),
            score FLOAT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX eval_idx (evaluation_id),
            INDEX question_idx (question_id),
            UNIQUE KEY unique_answer (evaluation_id, question_id),
            PRIMARY KEY (id)
        ) $charset;";

        // 📥 Création / mise à jour des tables
        dbDelta($sql_questions);
        dbDelta($sql_evaluations);
        dbDelta($sql_answers);

        // ✅ Enregistre la version de la base
        update_option('he_db_version', '1.2.0');
    }
}
