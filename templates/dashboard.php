<?php
if (!defined('ABSPATH')) exit;

// Vérification de la connexion
if (!is_user_logged_in()) {
    echo '<div class="he-warning" style="padding:20px;background:#fee2e2;color:#991b1b;border-radius:8px;">
            ⚠️ Accès refusé : vous devez être connecté pour consulter le tableau de bord.
          </div>';
    return;
}

global $wpdb;
$current_user = wp_get_current_user();
$user_id = $current_user->ID;
$roles = $current_user->roles;
$role = $roles[0] ?? 'utilisateur';

// Mapping des rôles WordPress → labels visibles
$profil_map = [
    'directeur_hopital'       => 'Directeur',
    'administrateur_hopital'  => 'Administrateur Hôpital',
    'medecin'                 => 'Médecin',
    'infirmier'               => 'Infirmier',
];
$profil_label = $profil_map[$role] ?? ucfirst(str_replace('_', ' ', $role));

// Déterminer l'hôpital associé (via ACF)
$hospital_id = null;
if (function_exists('get_field')) {
    $acf_hospital = get_field('hospital_id', 'user_' . $user_id);
    $hospital_id = is_array($acf_hospital) ? intval($acf_hospital[0] ?? 0) : intval($acf_hospital);
}

// 🔹 Récupération des évaluations selon le rôle - CORRIGÉ
if ($role === 'directeur_hopital') {
    // ✅ Directeur : Voit TOUTES les évaluations de son hôpital
    $evaluations = $wpdb->get_results($wpdb->prepare("
        SELECT e.*, p.post_title AS hospital_name
        FROM {$wpdb->prefix}hospital_evaluations e
        LEFT JOIN {$wpdb->prefix}posts p ON e.hospital_id = p.ID
        WHERE e.hospital_id = %d
        ORDER BY e.created_at DESC
    ", $hospital_id));
    
} elseif (in_array($role, ['administrateur_hopital', 'medecin', 'infirmier'])) {
    // ✅ Autres profils : Voient SEULEMENT leurs propres évaluations
    $evaluations = $wpdb->get_results($wpdb->prepare("
        SELECT e.*, p.post_title AS hospital_name
        FROM {$wpdb->prefix}hospital_evaluations e
        LEFT JOIN {$wpdb->prefix}posts p ON e.hospital_id = p.ID
        WHERE e.user_id = %d
        ORDER BY e.created_at DESC
    ", $user_id));
    
} else {
    // ❌ Rôle non autorisé
    echo '<div class="he-warning" style="padding:15px;background:#fee2e2;color:#991b1b;border-radius:8px;">
            🚫 Accès refusé : votre rôle ne permet pas d\'accéder à ce tableau de bord.
          </div>';
    return;
}
?>

<div class="he-dashboard">
    <!-- Titre conditionnel selon le rôle -->
    <h2>
        <?php if ($role === 'directeur_hopital'): ?>
            📊 Tableau de bord global
            <span style="font-size:14px;color:#6b7280;font-weight:400;">(Vue Directeur - Tous les profils)</span>
        <?php else: ?>
            📊 Mon tableau de bord
            <span style="font-size:14px;color:#6b7280;font-weight:400;">(Vue personnelle)</span>
        <?php endif; ?>
    </h2>

    <!-- Message d'information pour les non-directeurs -->
    <?php if ($role !== 'directeur_hopital'): ?>
        <div style="background:#dbeafe;padding:12px;border-radius:8px;color:#1e40af;margin-bottom:20px;border-left:4px solid #2563eb;">
            ℹ️ <strong>Note :</strong> Vous visualisez uniquement vos propres évaluations. 
            Seul le Directeur a accès aux scores de tous les profils.
        </div>
    <?php endif; ?>

    <table class="widefat striped" style="margin-top:15px;">
        <thead>
            <tr>
                <th>Profil</th>
                <th>Hôpital</th>
                <th>Date de soumission</th>
                <th>Questions répondues</th>
                <th>Score (%)</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($evaluations): ?>
                <?php foreach ($evaluations as $e):
                    $score = HE_Scoring::calculate_score($e->id);
                    $profil_display = !empty($e->profil) ? esc_html($e->profil) : esc_html($profil_label);
                    $hospital_name = $e->hospital_name ?: '—';
                    $date = !empty($e->created_at) ? date_i18n('d/m/Y H:i', strtotime($e->created_at)) : '—';

                    // Comptage des réponses Oui / Non / N/A
                    $answers = $wpdb->get_row($wpdb->prepare("
                        SELECT 
                            SUM(CASE WHEN reponse = 'Oui' THEN 1 ELSE 0 END) AS oui,
                            SUM(CASE WHEN reponse = 'Non' THEN 1 ELSE 0 END) AS non,
                            SUM(CASE WHEN reponse = 'N/A' THEN 1 ELSE 0 END) AS na
                        FROM {$wpdb->prefix}hospital_answers
                        WHERE evaluation_id = %d
                    ", $e->id));

                    $total_reponses = intval($answers->oui + $answers->non + $answers->na);
                    $response_detail = sprintf(
                        '<div class="he-response-detail">
                            <strong>%d réponses</strong><br>
                            <span style="color:#16a34a;">✅ Oui : %d</span><br>
                            <span style="color:#dc2626;">❌ Non : %d</span><br>
                            <span style="color:#d97706;">⚠️ N/A : %d</span>
                        </div>',
                        $total_reponses,
                        intval($answers->oui),
                        intval($answers->non),
                        intval($answers->na)
                    );
                ?>
                    <tr>
                        <td><?php echo $profil_display; ?></td>
                        <td><?php echo esc_html($hospital_name); ?></td>
                        <td><?php echo esc_html($date); ?></td>
                        <td><?php echo $response_detail; ?></td>
                        <td><strong style="color:#2563eb;font-size:16px;"><?php echo esc_html(number_format($score, 2)); ?>%</strong></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align:center;padding:30px;color:#6b7280;">
                        📭 Aucune évaluation trouvée
                        <?php if ($role === 'directeur_hopital'): ?>
                            <br><small>Aucun profil n'a encore soumis d'évaluation pour cet hôpital.</small>
                        <?php else: ?>
                            <br><small>Vous n'avez pas encore soumis d'évaluation.</small>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <hr style="margin:30px 0;">
    <h3>🧮 Détail du calcul du score</h3>

    <?php
    // ✅ Récupérer la dernière évaluation de l'utilisateur (ou toutes si Directeur)
    if ($role === 'directeur_hopital') {
        // Directeur : Afficher un sélecteur de profil
        echo '<div style="background:#f0f9ff;padding:15px;border-radius:8px;margin-bottom:20px;border-left:4px solid #2563eb;">';
        echo '<p style="margin:0;color:#1e40af;"><strong>📊 Vue Directeur :</strong> Le détail ci-dessous affiche votre propre évaluation.';
        echo '</div>';
    }

    $evaluation_id = $wpdb->get_var($wpdb->prepare("
        SELECT e.id 
        FROM {$wpdb->prefix}hospital_evaluations e
        JOIN {$wpdb->prefix}hospital_answers a ON a.evaluation_id = e.id
        WHERE e.user_id = %d
        ORDER BY e.created_at DESC LIMIT 1
    ", $user_id));

    if ($evaluation_id) {

        // ✅ Calcul global sur toutes les réponses (N/A exclus)
        $all_answers = $wpdb->get_results($wpdb->prepare("
            SELECT q.poids, a.reponse
            FROM {$wpdb->prefix}hospital_answers a
            JOIN {$wpdb->prefix}hospital_questions q ON a.question_id = q.id
            WHERE a.evaluation_id = %d
        ", $evaluation_id));

        $total_poids = 0;
        $scored_poids = 0;
        $count_oui = 0;
        $count_non = 0;
        $count_na = 0;

        foreach ($all_answers as $a) {
            $poids = floatval($a->poids);
            if ($a->reponse === 'Oui') {
                $scored_poids += $poids;
                $total_poids += $poids;
                $count_oui++;
            } elseif ($a->reponse === 'Non') {
                $total_poids += $poids;
                $count_non++;
            } elseif ($a->reponse === 'N/A') {
                $count_na++;
            }
        }

        $final_score = $total_poids > 0 ? round(($scored_poids / $total_poids) * 100, 2) : 0;

        // --- Pagination pour affichage ---
        $per_page = 20;
        $page = isset($_GET['page_question']) ? max(1, intval($_GET['page_question'])) : 1;
        $offset = ($page - 1) * $per_page;

        $details = $wpdb->get_results($wpdb->prepare("
            SELECT q.chapitre, q.question_text, q.poids, a.reponse
            FROM {$wpdb->prefix}hospital_answers a
            JOIN {$wpdb->prefix}hospital_questions q ON a.question_id = q.id
            WHERE a.evaluation_id = %d
            ORDER BY q.chapitre ASC, q.id ASC
            LIMIT %d OFFSET %d
        ", $evaluation_id, $per_page, $offset));

        $total_rows = count($all_answers);

        if ($details) {
            echo '<div class="he-score-details">';
            echo '<table class="widefat striped">';
            echo '<thead><tr><th>Chapitre</th><th>Question</th><th>Réponse</th><th>Impact</th><th>Statut</th></tr></thead><tbody>';

            foreach ($details as $d) {
                $impact_label = ($d->poids == 1) ? '⚪ Léger' : (($d->poids == 3) ? '🟢 Moyen' : '🔴 Fort');
                $statut = '';
                $statut_style = '';
                
                if ($d->reponse === 'Oui') {
                    $statut = '✅ ' . $d->poids . ' pts';
                    $statut_style = 'color:#16a34a;font-weight:600;';
                } elseif ($d->reponse === 'Non') {
                    $statut = '❌ 0 pt';
                    $statut_style = 'color:#dc2626;font-weight:600;';
                } elseif ($d->reponse === 'N/A') {
                    $statut = '⚠️ Exclu';
                    $statut_style = 'color:#d97706;font-weight:600;';
                }

                echo '<tr>';
                echo '<td>' . esc_html($d->chapitre ?: '—') . '</td>';
                echo '<td>' . esc_html(stripslashes($d->question_text)) . '</td>';
                echo '<td><strong>' . esc_html($d->reponse) . '</strong></td>';
                echo '<td>' . esc_html($impact_label) . '</td>';
                echo '<td style="' . $statut_style . '">' . $statut . '</td>';
                echo '</tr>';
            }

            // ✅ Score global affiché, stable
            echo '<tr style="font-weight:bold;background:#e8f5e9;border-top:2px solid #4caf50;">';
            echo '<td colspan="4" style="text-align:right;">✅ Score global :</td>';
            echo '<td style="color:#2e7d32;font-size:16px;">' . esc_html($final_score) . '%</td>';
            echo '</tr>';

            // 🧾 Détail complet du calcul
            echo '<tr style="font-size:13px;color:#333;background:#f0fdf4;"><td colspan="5" style="padding:12px 16px;line-height:1.6;">';
            echo '<strong>📊 Détail du calcul :</strong><br>';
            echo '• Questions <strong style="color:#16a34a;">✅ Oui</strong> : ' . esc_html($count_oui) . ' (' . number_format($scored_poids, 1) . ' points obtenus)<br>';
            echo '• Questions <strong style="color:#dc2626;">❌ Non</strong> : ' . esc_html($count_non) . ' (0 point)<br>';
            echo '• Questions <strong style="color:#d97706;">⚠️ N/A</strong> : ' . esc_html($count_na) . ' (exclues du calcul)<br>';
            echo '• <strong>Total poids évalué</strong> : ' . number_format($total_poids, 1) . ' (uniquement Oui + Non)<br>';
            echo '• <strong>Formule</strong> : (' . number_format($scored_poids, 1) . ' / ' . number_format($total_poids, 1) . ') × 100 = <strong style="color:#2e7d32;">' . esc_html($final_score) . '%</strong>';
            echo '</td></tr>';
            echo '</tbody></table>';

            // Pagination
            $total_pages = ceil($total_rows / $per_page);
            if ($total_pages > 1) {
                echo '<div class="he-pagination" style="margin-top:15px;text-align:center;">';
                for ($i = 1; $i <= $total_pages; $i++) {
                    $active_style = ($i == $page) ? 'font-weight:bold;background:#2563eb;color:#fff;' : '';
                    echo '<a href="?page_question=' . $i . '" style="margin:0 5px;padding:8px 12px;background:#f0f0f0;border-radius:4px;text-decoration:none;color:#333;' . $active_style . '">' . $i . '</a>';
                }
                echo '</div>';
            }

            echo '</div>';
        } else {
            echo '<p style="text-align:center;padding:20px;color:#6b7280;">Aucune donnée trouvée pour cette évaluation.</p>';
        }
    } else {
        echo '<div style="text-align:center;padding:40px;background:#f9fafb;border-radius:8px;color:#6b7280;">';
        echo '<p style="font-size:16px;margin:0;">📭 Aucune évaluation avec réponses trouvée</p>';
        if ($role !== 'directeur_hopital') {
            echo '<p style="margin:10px 0 0 0;font-size:14px;">Soumettez d\'abord une évaluation pour voir le détail du calcul.</p>';
        }
        echo '</div>';
    }
    ?>
</div>

<style>
.he-dashboard h2 {
    color: #1e40af;
    margin-bottom: 20px;
}
.he-response-detail {
    font-size: 13px;
    line-height: 1.6;
}
.he-pagination a {
    display: inline-block;
    transition: all 0.2s ease;
}
.he-pagination a:hover {
    background: #e0e0e0 !important;
    transform: translateY(-2px);
}
.widefat td strong {
    color: #1e40af;
}
.widefat th {
    background: #1e40af;
    font-weight: 600;
}
</style>