<?php
if (!defined('ABSPATH')) exit;

class HE_Rest_API {

    public static function register_routes() {
        register_rest_route('he/v1', '/save', [
            'methods'  => 'POST',
            'callback' => [self::class, 'save_evaluation'],
            'permission_callback' => [self::class, 'permissions_check']
        ]);
    }

    /**
     * 🔒 Vérifie les permissions et la sécurité REST
     */
    public static function permissions_check($request) {
        if (!wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')) {
            return new WP_Error('rest_nonce_invalid', 'Nonce invalide.', ['status' => 403]);
        }

        if (!is_user_logged_in()) {
            return new WP_Error('rest_forbidden', 'Connexion requise.', ['status' => 403]);
        }

        return true;
    }

    /**
     * 💾 Enregistre les réponses d’évaluation via l’API REST
     */
    public static function save_evaluation($request) {
        global $wpdb;

        try {
            $user_id     = get_current_user_id();
            $hospital_id = intval($request['hospital_id']);
            $answers     = (array) $request['answers'];

            // 🔍 Détection / fallback du profil
            $profil = sanitize_text_field($request['profil'] ?? '');
            $profil = HE_Utils::detect_user_profil($user_id, $profil);

            if (empty($hospital_id) || empty($answers)) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Paramètres manquants.'
                ], 400);
            }

            // 🔹 Création ou récupération de l’évaluation
            $evaluation_id = HE_Evaluations::get_or_create_evaluation($hospital_id, $user_id, $profil);

            // 🔹 Sauvegarde des réponses
            HE_Evaluations::save_answers($evaluation_id, $answers);

            // 🔹 Recalcul du score (avec cache)
            $score = HE_Scoring::calculate_score($evaluation_id, true);

            // 🔹 Mise à jour du statut
            HE_Evaluations::update_status($evaluation_id, 'submitted');

            HE_Utils::log("[HE_REST] ✅ Évaluation #$evaluation_id enregistrée (profil=$profil, score=$score%)");

            return new WP_REST_Response([
                'success' => true,
                'message' => 'Évaluation enregistrée avec succès.',
                'score'   => $score,
                'profil'  => $profil,
                'evaluation_id' => $evaluation_id
            ], 200);

        } catch (Exception $e) {
            HE_Utils::log('❌ Erreur REST: ' . $e->getMessage());
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Erreur: ' . esc_html($e->getMessage())
            ], 500);
        }
    }
}
