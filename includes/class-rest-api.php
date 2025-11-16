<?php
if (!defined('ABSPATH')) exit;

/**
 * API REST pour EXP Santé
 * Version sécurisée avec validation renforcée
 */
class HE_Rest_API {

    /**
     * Enregistre les routes REST
     */
    public static function register_routes() {
        register_rest_route('he/v1', '/save', [
            'methods'  => 'POST',
            'callback' => [self::class, 'save_evaluation'],
            'permission_callback' => [self::class, 'permissions_check'],
            'args' => [
                'hospital_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ],
                'answers' => [
                    'required' => true,
                    'type' => 'object',
                    'validate_callback' => function($param) {
                        return is_array($param) && !empty($param);
                    }
                ],
                'profil' => [
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
    }

    /**
     * ✅ Vérification des permissions et de la sécurité
     */
    public static function permissions_check($request) {
        // Vérification du nonce
        $nonce = $request->get_header('X-WP-Nonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            HE_Utils::log('⛔ Tentative d\'accès avec nonce invalide');
            return new WP_Error(
                'rest_nonce_invalid', 
                'Nonce invalide ou expiré.',
                ['status' => 403]
            );
        }

        // Vérification de la connexion
        if (!is_user_logged_in()) {
            HE_Utils::log('⛔ Tentative d\'accès non authentifié');
            return new WP_Error(
                'rest_forbidden',
                'Vous devez être connecté pour effectuer cette action.',
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * ✅ Validation des réponses
     */
    private static function validate_answers($answers) {
        $valid_responses = ['Oui', 'Non', 'N/A'];
        $errors = [];

        foreach ($answers as $question_id => $response) {
            // Vérifier que l'ID de question est valide
            if (!is_numeric($question_id) || $question_id <= 0) {
                $errors[] = "ID de question invalide : {$question_id}";
                continue;
            }

            // Vérifier que la réponse est valide
            if (!in_array($response, $valid_responses)) {
                $errors[] = "Réponse invalide pour la question #{$question_id} : {$response}";
            }
        }

        return $errors;
    }

    /**
     * ✅ Vérifie que l'utilisateur peut évaluer cet hôpital
     */
    private static function can_user_evaluate_hospital($user_id, $hospital_id) {
        $user = get_userdata($user_id);
        if (!$user) return false;

        $role = !empty($user->roles) ? $user->roles[0] : '';
        
        // Les admins peuvent tout faire
        if (in_array($role, ['administrator', 'admin_app'])) {
            return true;
        }

        // Les autres rôles doivent être assignés à cet hôpital
        $restricted_roles = ['directeur_hopital', 'administrateur_hopital', 'medecin', 'infirmier'];
        if (in_array($role, $restricted_roles)) {
            $user_hospital = get_user_meta($user_id, 'hospital_id', true);
            
            // ✅ ACF peut stocker la valeur comme tableau ou string
            if (is_array($user_hospital)) {
                $user_hospital = !empty($user_hospital[0]) ? $user_hospital[0] : 0;
            }
            
            $authorized = intval($user_hospital) === intval($hospital_id);
            
            // Debug log
            HE_Utils::log(sprintf(
                "🔐 Vérification accès: User #%d (%s) → Hôpital #%d | User Hospital: %s | Autorisé: %s",
                $user_id,
                $role,
                $hospital_id,
                $user_hospital ?: 'aucun',
                $authorized ? 'OUI' : 'NON'
            ));
            
            return $authorized;
        }

        return false;
    }

    /**
     * ✅ Vérifie qu'une question existe et est active
     */
    private static function validate_questions($question_ids) {
        global $wpdb;
        
        if (empty($question_ids)) return [];
        
        $placeholders = implode(',', array_fill(0, count($question_ids), '%d'));
        
        $existing = $wpdb->get_col($wpdb->prepare("
            SELECT id 
            FROM {$wpdb->prefix}hospital_questions
            WHERE id IN ($placeholders)
            AND active = 1
        ", $question_ids));
        
        $missing = array_diff($question_ids, $existing);
        
        return $missing;
    }

    /**
     * 💾 Enregistre les réponses d'évaluation via l'API REST
     */
    public static function save_evaluation($request) {
        global $wpdb;

        try {
            $user_id = get_current_user_id();
            $hospital_id = intval($request['hospital_id']);
            $answers = (array) $request['answers'];
            $profil = sanitize_text_field($request['profil'] ?? '');

            // ✅ Validation : L'hôpital existe-t-il ?
            if (get_post_type($hospital_id) !== 'hospital') {
                HE_Utils::log("❌ Hôpital #{$hospital_id} inexistant");
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'L\'hôpital spécifié n\'existe pas.'
                ], 400);
            }

            // ✅ Validation : L'utilisateur peut-il évaluer cet hôpital ?
            if (!self::can_user_evaluate_hospital($user_id, $hospital_id)) {
                HE_Utils::log("⛔ User #{$user_id} non autorisé pour hôpital #{$hospital_id}");
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas autorisé à évaluer cet hôpital.'
                ], 403);
            }

            // ✅ Validation : Les réponses sont-elles valides ?
            $answer_errors = self::validate_answers($answers);
            if (!empty($answer_errors)) {
                HE_Utils::log("❌ Réponses invalides : " . implode(', ', $answer_errors));
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Réponses invalides',
                    'errors' => $answer_errors
                ], 400);
            }

            // ✅ Validation : Les questions existent-elles ?
            $question_ids = array_keys($answers);
            $missing_questions = self::validate_questions($question_ids);
            if (!empty($missing_questions)) {
                HE_Utils::log("❌ Questions inexistantes : " . implode(', ', $missing_questions));
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Certaines questions n\'existent pas ou sont désactivées.',
                    'missing_questions' => $missing_questions
                ], 400);
            }

            // 🔹 Détection du profil
            $profil = HE_Utils::detect_user_profil($user_id, $profil);

            // 🔹 Création ou récupération de l'évaluation
            $evaluation_id = HE_Evaluations::get_or_create_evaluation($hospital_id, $user_id, $profil);
            
            if (!$evaluation_id) {
                HE_Utils::log("❌ Échec création évaluation pour user #{$user_id}, hôpital #{$hospital_id}");
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Impossible de créer l\'évaluation. Veuillez réessayer.'
                ], 500);
            }

            // 🔹 Sauvegarde des réponses
            $save_result = HE_Evaluations::save_answers($evaluation_id, $answers);
            
            if ($save_result === false) {
                HE_Utils::log("❌ Échec sauvegarde réponses pour éval #{$evaluation_id}");
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Erreur lors de la sauvegarde des réponses.'
                ], 500);
            }

            // 🔹 Recalcul du score
            $score = HE_Scoring::calculate_score($evaluation_id);

            // 🔹 Mise à jour du statut
            HE_Evaluations::update_status($evaluation_id, 'submitted');

            HE_Utils::log("✅ Évaluation #$evaluation_id enregistrée (User #{$user_id}, Profil={$profil}, Score={$score}%)");

            return new WP_REST_Response([
                'success' => true,
                'message' => 'Évaluation enregistrée avec succès.',
                'data' => [
                    'evaluation_id' => $evaluation_id,
                    'score' => $score,
                    'profil' => $profil,
                    'hospital_id' => $hospital_id,
                    'answers_count' => count($answers)
                ]
            ], 200);

        } catch (Exception $e) {
            HE_Utils::log('❌ Erreur REST Exception: ' . $e->getMessage());
            
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Une erreur inattendue s\'est produite. Veuillez réessayer.',
                'error' => defined('WP_DEBUG') && WP_DEBUG ? $e->getMessage() : null
            ], 500);
        }
    }
}
