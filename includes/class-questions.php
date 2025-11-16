<?php 
if (!defined('ABSPATH')) exit;

/**
 * Gestion des questions d'évaluation hospitalière
 * Version améliorée avec validation et sécurité renforcées
 */
class HE_Questions {

    /**
     * ✅ Récupère les questions en fonction du profil utilisateur
     * @param string $profil Rôle utilisateur (ex: directeur_hopital, medecin, etc.)
     * @return array Liste des questions actives pour le profil
     */
    public static function get_by_profile($profil) {
        global $wpdb;

        // Validation du profil
        if (empty($profil)) {
            HE_Utils::log("⚠️ get_by_profile appelé avec profil vide");
            return [];
        }

        // Normalisation du profil
        $profil_normalized = self::normalize_profil($profil);
        
        HE_Utils::log("🔍 Recherche questions pour profil: {$profil} (normalisé: {$profil_normalized})");

        $query = $wpdb->prepare("
            SELECT * 
            FROM {$wpdb->prefix}hospital_questions
            WHERE profil = %s
              AND active = 1
            ORDER BY position ASC, chapitre ASC, id ASC
        ", $profil_normalized);

        $results = $wpdb->get_results($query);

        if ($results === null) {
            HE_Utils::log("❌ Erreur SQL get_by_profile: " . $wpdb->last_error);
            return [];
        }

        HE_Utils::log("✅ " . count($results) . " questions trouvées pour profil: {$profil_normalized}");

        return $results;
    }

    /**
     * ✅ Normalise le nom du profil
     * @param string $profil Profil brut
     * @return string Profil normalisé
     */
    private static function normalize_profil($profil) {
        $mapping = [
            'directeur_hopital'       => 'Directeur',
            'directeur hopital'       => 'Directeur',
            'directeur'               => 'Directeur',
            'administrateur_hopital'  => 'Administrateur Hôpital',
            'administrateur hopital'  => 'Administrateur Hôpital',
            'admin hopital'           => 'Administrateur Hôpital',
            'medecin'                 => 'Médecin',
            'infirmier'               => 'Infirmier'
        ];

        $profil_clean = strtolower(trim(str_replace('_', ' ', $profil)));
        
        return $mapping[$profil_clean] ?? ucfirst($profil);
    }

    /**
     * ✅ Récupère toutes les questions (avec pagination)
     * @param int $limit Nombre de résultats
     * @param int $offset Décalage
     * @param string $profil_filter Filtrer par profil (optionnel)
     * @param bool $active_only Uniquement les questions actives
     * @return array Liste des questions
     */
    public static function get_all($limit = 100, $offset = 0, $profil_filter = null, $active_only = false) {
        global $wpdb;

        $limit = intval($limit);
        $offset = intval($offset);
        
        if ($limit <= 0) $limit = 100;
        if ($offset < 0) $offset = 0;

        $where = "1=1";
        $params = [];

        if ($profil_filter) {
            $where .= " AND profil = %s";
            $params[] = sanitize_text_field($profil_filter);
        }

        if ($active_only) {
            $where .= " AND active = 1";
        }

        $params[] = $limit;
        $params[] = $offset;

        $query = "
            SELECT * 
            FROM {$wpdb->prefix}hospital_questions 
            WHERE $where
            ORDER BY profil ASC, position ASC, id ASC
            LIMIT %d OFFSET %d
        ";

        $results = $wpdb->get_results($wpdb->prepare($query, $params));

        if ($results === null) {
            HE_Utils::log("❌ Erreur SQL get_all: " . $wpdb->last_error);
            return [];
        }

        return $results;
    }

    /**
     * ✅ Compte le nombre total de questions
     * @param string $profil_filter Filtrer par profil (optionnel)
     * @param bool $active_only Uniquement les questions actives
     * @return int Nombre de questions
     */
    public static function count_all($profil_filter = null, $active_only = false) {
        global $wpdb;

        $where = "1=1";
        $params = [];

        if ($profil_filter) {
            $where .= " AND profil = %s";
            $params[] = sanitize_text_field($profil_filter);
        }

        if ($active_only) {
            $where .= " AND active = 1";
        }

        $query = "SELECT COUNT(*) FROM {$wpdb->prefix}hospital_questions WHERE $where";

        if (!empty($params)) {
            return intval($wpdb->get_var($wpdb->prepare($query, $params)));
        }

        return intval($wpdb->get_var($query));
    }

    /**
     * ✅ Ajoute une nouvelle question
     * @param string $profil Profil cible
     * @param string $texte Texte de la question
     * @param string $chapitre Chapitre (optionnel)
     * @param float $poids Poids de la question
     * @param bool $active Question active ou non
     * @param int $position Position dans l'ordre
     * @return int|false ID de la question créée ou false
     */
    public static function add_question($profil, $texte, $chapitre = null, $poids = 1.0, $active = true, $position = 0) {
        global $wpdb;

        // Validation
        if (empty($profil) || empty($texte)) {
            HE_Utils::log("❌ add_question: profil ou texte manquant");
            return false;
        }

        $poids = floatval($poids);
        if ($poids < 0) $poids = 1.0;

        $position = intval($position);
        if ($position < 0) $position = 0;

        $data = [
            'profil'        => sanitize_text_field(self::normalize_profil($profil)),
            'question_text' => sanitize_textarea_field($texte),
            'chapitre'      => $chapitre ? sanitize_text_field($chapitre) : null,
            'poids'         => $poids,
            'active'        => $active ? 1 : 0,
            'position'      => $position,
            'created_at'    => current_time('mysql'),
            'updated_at'    => current_time('mysql')
        ];

        $result = $wpdb->insert(
            "{$wpdb->prefix}hospital_questions",
            $data,
            ['%s', '%s', '%s', '%f', '%d', '%d', '%s', '%s']
        );

        if ($result === false) {
            HE_Utils::log("❌ Échec insertion question: " . $wpdb->last_error);
            return false;
        }

        $question_id = $wpdb->insert_id;
        HE_Utils::log("✅ Question #$question_id créée");

        return $question_id;
    }

    /**
     * ✅ Met à jour une question
     * @param int $id ID de la question
     * @param array $data Données à mettre à jour
     * @return bool Succès de la mise à jour
     */
    public static function update_question($id, $data) {
        global $wpdb;

        $id = intval($id);
        if (!$id) return false;

        // Champs autorisés
        $allowed_fields = ['profil', 'question_text', 'chapitre', 'poids', 'active', 'position'];
        $update_data = [];
        $format = [];

        foreach ($data as $key => $value) {
            if (!in_array($key, $allowed_fields)) continue;

            switch ($key) {
                case 'profil':
                    $update_data[$key] = sanitize_text_field(self::normalize_profil($value));
                    $format[] = '%s';
                    break;
                case 'question_text':
                    $update_data[$key] = sanitize_textarea_field($value);
                    $format[] = '%s';
                    break;
                case 'chapitre':
                    $update_data[$key] = $value ? sanitize_text_field($value) : null;
                    $format[] = '%s';
                    break;
                case 'poids':
                    $update_data[$key] = floatval($value);
                    $format[] = '%f';
                    break;
                case 'active':
                case 'position':
                    $update_data[$key] = intval($value);
                    $format[] = '%d';
                    break;
            }
        }

        if (empty($update_data)) {
            HE_Utils::log("⚠️ update_question: aucune donnée valide à mettre à jour");
            return false;
        }

        $update_data['updated_at'] = current_time('mysql');
        $format[] = '%s';

        $result = $wpdb->update(
            "{$wpdb->prefix}hospital_questions",
            $update_data,
            ['id' => $id],
            $format,
            ['%d']
        );

        if ($result === false) {
            HE_Utils::log("❌ Échec mise à jour question #$id: " . $wpdb->last_error);
            return false;
        }

        HE_Utils::log("✅ Question #$id mise à jour");
        return true;
    }

    /**
     * ✅ Récupère une question par son ID
     * @param int $id ID de la question
     * @return object|null Question ou null
     */
    public static function get_by_id($id) {
        global $wpdb;

        $id = intval($id);
        if (!$id) return null;

        $question = $wpdb->get_row($wpdb->prepare("
            SELECT * 
            FROM {$wpdb->prefix}hospital_questions
            WHERE id = %d
        ", $id));

        return $question;
    }

    /**
     * ✅ Désactive une question sans la supprimer
     * @param int $id ID de la question
     * @return bool Succès de l'opération
     */
    public static function deactivate($id) {
        global $wpdb;

        $id = intval($id);
        if (!$id) return false;

        $result = $wpdb->update(
            "{$wpdb->prefix}hospital_questions",
            [
                'active' => 0,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $id],
            ['%d', '%s'],
            ['%d']
        );

        if ($result === false) {
            HE_Utils::log("❌ Échec désactivation question #$id: " . $wpdb->last_error);
            return false;
        }

        HE_Utils::log("✅ Question #$id désactivée");
        return true;
    }

    /**
     * ✅ Réactive une question
     * @param int $id ID de la question
     * @return bool Succès de l'opération
     */
    public static function activate($id) {
        global $wpdb;

        $id = intval($id);
        if (!$id) return false;

        $result = $wpdb->update(
            "{$wpdb->prefix}hospital_questions",
            [
                'active' => 1,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $id],
            ['%d', '%s'],
            ['%d']
        );

        if ($result === false) {
            HE_Utils::log("❌ Échec activation question #$id: " . $wpdb->last_error);
            return false;
        }

        HE_Utils::log("✅ Question #$id activée");
        return true;
    }

    /**
     * ✅ Supprime définitivement une question
     * @param int $id ID de la question
     * @return bool Succès de la suppression
     */
    public static function delete($id) {
        global $wpdb;

        $id = intval($id);
        if (!$id) return false;

        // Vérifier si la question est utilisée dans des réponses
        $usage_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}hospital_answers
            WHERE question_id = %d
        ", $id));

        if ($usage_count > 0) {
            HE_Utils::log("⚠️ Question #$id utilisée dans $usage_count réponses - désactivation recommandée au lieu de suppression");
            // On peut choisir de bloquer la suppression ou juste avertir
            return false;
        }

        $result = $wpdb->delete(
            "{$wpdb->prefix}hospital_questions",
            ['id' => $id],
            ['%d']
        );

        if ($result === false) {
            HE_Utils::log("❌ Échec suppression question #$id: " . $wpdb->last_error);
            return false;
        }

        HE_Utils::log("🗑️ Question #$id supprimée");
        return true;
    }

    /**
     * ✅ Récupère les profils disponibles
     * @return array Liste des profils uniques
     */
    public static function get_available_profiles() {
        global $wpdb;

        $profiles = $wpdb->get_col("
            SELECT DISTINCT profil 
            FROM {$wpdb->prefix}hospital_questions
            ORDER BY profil ASC
        ");

        return $profiles ?: [];
    }

    /**
     * ✅ Récupère les chapitres d'un profil
     * @param string $profil Profil
     * @return array Liste des chapitres
     */
    public static function get_chapters_by_profile($profil) {
        global $wpdb;

        $profil_normalized = self::normalize_profil($profil);

        $chapters = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT chapitre 
            FROM {$wpdb->prefix}hospital_questions
            WHERE profil = %s
            AND chapitre IS NOT NULL
            AND chapitre != ''
            ORDER BY chapitre ASC
        ", $profil_normalized));

        return $chapters ?: [];
    }
}
