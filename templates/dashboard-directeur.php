<?php
if (!defined('ABSPATH')) exit;

// Vérifie la connexion
if (!is_user_logged_in()) {
    echo '<div class="he-warning" style="padding:20px;background:#fee2e2;color:#991b1b;border-radius:8px;">
            ⚠️ Accès refusé : vous devez être connecté pour consulter le tableau de bord.
          </div>';
    return;
}

global $wpdb;
$current_user = wp_get_current_user();
$user_id = $current_user->ID;

// 🔄 Gestion du recalcul des scores
if (isset($_GET['recalculate_scores']) && $_GET['recalculate_scores'] == '1') {
    if (current_user_can('manage_options') || in_array('directeur_hopital', $current_user->roles)) {
        $count = HE_Scoring::recalculate_all();
        echo '<div style="background:#d1fae5;border:2px solid #10b981;padding:15px;margin:20px 0;border-radius:8px;">';
        echo '<p style="color:#065f46;margin:0;font-weight:600;">✅ ' . $count . ' scores recalculés avec succès !</p>';
        echo '</div>';
        
        // Redirection pour nettoyer l'URL
        echo '<script>
            setTimeout(function() {
                window.location.href = window.location.pathname + window.location.search.replace(/[?&]recalculate_scores=1/, "");
            }, 2000);
        </script>';
    }
}

// 🔹 Récupère l'hôpital associé
$hospital_id = null;
if (function_exists('get_field')) {
    $acf_hospital = get_field('hospital_id', 'user_' . $user_id);
    $hospital_id = is_array($acf_hospital) ? intval($acf_hospital[0] ?? 0) : intval($acf_hospital);
}
if (!$hospital_id) {
    echo '<p>Aucun hôpital associé à votre profil.</p>';
    return;
}

$hospital_name = get_the_title($hospital_id) ?: '—';

// 🔹 Récupère toutes les évaluations pour cet hôpital
$evaluations = $wpdb->get_results($wpdb->prepare("
    SELECT e.*, p.post_title AS hospital_name
    FROM {$wpdb->prefix}hospital_evaluations e
    LEFT JOIN {$wpdb->prefix}posts p ON e.hospital_id = p.ID
    WHERE e.hospital_id = %d
    ORDER BY e.created_at DESC
", $hospital_id));

// Profils à afficher
$profils = ['Directeur', 'Administrateur Hôpital', 'Médecin', 'Infirmier'];
?>
<div class="he-dashboard">
  <h2>Tableau de bord des scores — <?php echo esc_html($hospital_name); ?></h2>

  <table class="widefat striped" style="margin-top:15px;">
    <thead>
      <tr>
        <th>Profil</th>
        <th>Hôpital</th>
        <th>Date de soumission</th>
        <th>Date de modification</th>
        <th>Questions répondues</th>
        <th>Score (%)</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $scores = [];
      if ($evaluations) {
          foreach ($evaluations as $e) {
              $profil = $e->profil ?: '—';
              
              // ✅ Utiliser le score déjà calculé et stocké en DB
              $score = !empty($e->score_final) ? floatval($e->score_final) : HE_Scoring::calculate_score($e->id);
              $scores[$profil] = $score;

              // Compter les réponses pour afficher le détail
              $answer_counts = $wpdb->get_row($wpdb->prepare("
                  SELECT 
                      SUM(CASE WHEN a.reponse = 'Oui' THEN 1 ELSE 0 END) as oui,
                      SUM(CASE WHEN a.reponse = 'Non' THEN 1 ELSE 0 END) as non,
                      SUM(CASE WHEN a.reponse = 'N/A' THEN 1 ELSE 0 END) as na
                  FROM {$wpdb->prefix}hospital_answers a
                  WHERE a.evaluation_id = %d
              ", $e->id));

              $total_responded = ($answer_counts->oui + $answer_counts->non);
              $response_detail = sprintf(
                  '%d réponses (Oui:%d Non:%d N/A:%d)',
                  $total_responded + $answer_counts->na,
                  $answer_counts->oui,
                  $answer_counts->non,
                  $answer_counts->na
              );

              echo '<tr>';
              echo '<td>' . esc_html($profil) . '</td>';
              echo '<td>' . esc_html($hospital_name) . '</td>';
              echo '<td>' . date_i18n('d/m/Y H:i', strtotime($e->created_at)) . '</td>';
              echo '<td>' . date_i18n('d/m/Y H:i', strtotime($e->updated_at)) . '</td>';
              echo '<td><small style="color:#666;">' . esc_html($response_detail) . '</small></td>';
              echo '<td><strong style="color:#2563eb;font-size:15px;">' . esc_html(round($score, 2)) . '%</strong></td>';
              echo '</tr>';
          }
      } else {
          echo '<tr><td colspan="6">Aucune évaluation trouvée.</td></tr>';
      }

      if (!empty($scores)) {
          $global = round(array_sum($scores) / count($scores), 2);
          echo '<tr style="background:#dbeafe;font-weight:bold;border-top:3px solid #2563eb;">
                  <td colspan="5" style="text-align:right;font-size:16px;">📊 Score global de l\'hôpital :</td>
                  <td><strong style="color:#1e40af;font-size:18px;">' . esc_html($global) . '%</strong></td>
                </tr>';
      }
      ?>
    </tbody>
  </table>


  
  <?php
  // 🔍 Vérification de cohérence des scores
  $has_discrepancy = false;
  $discrepancy_details = [];
  
  foreach ($profils as $p) {
      $eval = $wpdb->get_row($wpdb->prepare("
          SELECT id, score_final FROM {$wpdb->prefix}hospital_evaluations
          WHERE hospital_id = %d AND profil = %s
          ORDER BY created_at DESC LIMIT 1
      ", $hospital_id, $p));
      
      if ($eval) {
          $score_db = floatval($eval->score_final);
          $score_calculated = HE_Scoring::calculate_score($eval->id);
          $diff = abs($score_db - $score_calculated);
          
          if ($diff > 0.1) { // Tolérance de 0.1%
              $has_discrepancy = true;
              $discrepancy_details[] = sprintf(
                  '%s : DB=%s%% vs Calculé=%s%% (diff: %s%%)',
                  $p, number_format($score_db, 2), number_format($score_calculated, 2), number_format($diff, 2)
              );
          }
      }
  }
  
  if ($has_discrepancy && current_user_can('manage_options')) {
      echo '<div style="background:#fef2f2;border:2px solid #dc2626;padding:15px;margin-bottom:20px;border-radius:8px;">';
      echo '<h4 style="color:#991b1b;margin:0 0 10px 0;">⚠️ Incohérence de scores détectée</h4>';
      echo '<p style="color:#7f1d1d;margin:0 0 10px 0;">Les scores affichés ne correspondent pas aux scores calculés :</p>';
      echo '<ul style="margin:0;padding-left:20px;color:#7f1d1d;">';
      foreach ($discrepancy_details as $detail) {
          echo '<li>' . esc_html($detail) . '</li>';
      }
      echo '</ul>';
      echo '<a href="?recalculate_scores=1" class="button button-primary" style="margin-top:10px;">♻️ Recalculer tous les scores</a>';
      echo '</div>';
  }
  ?>
  
  <h3>🧮 Détail du calcul du score</h3>

  <div class="he-tabs">
    <div class="tab-buttons">
      <?php foreach ($profils as $i => $p): ?>
        <button class="tab-btn <?php echo $i === 0 ? 'active' : ''; ?>" data-tab="tab-<?php echo $i; ?>" data-profil="<?php echo esc_attr($p); ?>">
          <?php echo esc_html($p); ?>
        </button>
      <?php endforeach; ?>
      <button class="tab-btn" data-tab="tab-global">Calcul score global</button>
    </div>

    <div class="tab-content">
      <?php foreach ($profils as $i => $p): ?>
        <div id="tab-<?php echo $i; ?>" class="tab-panel <?php echo $i === 0 ? 'active' : ''; ?>" data-profil="<?php echo esc_attr($p); ?>">
          <h4><?php echo esc_html($p); ?></h4>
          <?php
          $eval = $wpdb->get_row($wpdb->prepare("
              SELECT id FROM {$wpdb->prefix}hospital_evaluations
              WHERE hospital_id = %d AND profil = %s
              ORDER BY created_at DESC LIMIT 1
          ", $hospital_id, $p));

          if ($eval) {
              $per_page = 20;
              
              // ✅ CORRECTION : Utiliser un paramètre unique par profil
              // Normaliser le nom du profil pour créer un paramètre cohérent
              $profil_normalized = strtolower(str_replace(['é', 'ô', ' '], ['e', 'o', '_'], $p));
              $page_param = 'page_profil_' . $profil_normalized;
              $page = isset($_GET[$page_param]) ? max(1, intval($_GET[$page_param])) : 1;
              $offset = ($page - 1) * $per_page;

              // Compte total pour la pagination
              $total_rows = (int) $wpdb->get_var($wpdb->prepare("
                  SELECT COUNT(*) FROM {$wpdb->prefix}hospital_answers
                  WHERE evaluation_id = %d
              ", $eval->id));

              // Récupère les détails paginés
              $details = $wpdb->get_results($wpdb->prepare("
                  SELECT q.chapitre, q.question_text, q.poids, a.reponse
                  FROM {$wpdb->prefix}hospital_answers a
                  JOIN {$wpdb->prefix}hospital_questions q ON a.question_id = q.id
                  WHERE a.evaluation_id = %d
                  ORDER BY q.chapitre ASC, q.id ASC
                  LIMIT %d OFFSET %d
              ", $eval->id, $per_page, $offset));

              if ($details) {
                  // ✅ CALCUL DU SCORE TOTAL - N/A EXCLUS du calcul
                  // Cette requête récupère TOUTES les réponses pour calculer le score global
                  $all_answers = $wpdb->get_results($wpdb->prepare("
                      SELECT q.poids, a.reponse
                      FROM {$wpdb->prefix}hospital_answers a
                      INNER JOIN {$wpdb->prefix}hospital_questions q ON a.question_id = q.id
                      WHERE a.evaluation_id = %d
                  ", $eval->id));

                  $total_poids = 0;
                  $scored_poids = 0;
                  $count_oui = 0;
                  $count_non = 0;
                  $count_na = 0;
                  
                  foreach ($all_answers as $ans) {
                      $poids = floatval($ans->poids);
                      
                      // ⚠️ NOUVELLE LOGIQUE : Les N/A sont EXCLUS du calcul
                      if ($ans->reponse === 'Oui') {
                          $total_poids += $poids;  // Compte dans le total
                          $scored_poids += $poids; // Compte les points
                          $count_oui++;
                      } elseif ($ans->reponse === 'Non') {
                          $total_poids += $poids;  // Compte dans le total
                          // scored_poids += 0 (pas de points)
                          $count_non++;
                      } elseif ($ans->reponse === 'N/A') {
                          // N/A = Ignoré complètement
                          $count_na++;
                      }
                  }
                  
                  $final_score = $total_poids > 0 
                      ? round(($scored_poids / $total_poids) * 100, 2) 
                      : 0;
                  
                  // 🔍 VALIDATION : Comparer avec HE_Scoring
                  $score_from_class = HE_Scoring::calculate_score($eval->id);
                  $score_diff = abs($final_score - $score_from_class);
                  
                  // Log pour déboguer
                  if ($score_diff > 0.1) {
                      error_log(sprintf(
                          "[HE_DASHBOARD] ⚠️ DIFFÉRENCE DÉTECTÉE | Profil: %s | Eval ID: %d | Manuel: %.2f%% | Classe: %.2f%% | Diff: %.2f%% | Oui:%d Non:%d N/A:%d | Poids:%.1f Points:%.1f",
                          $p, $eval->id, $final_score, $score_from_class, $score_diff, $count_oui, $count_non, $count_na, $total_poids, $scored_poids
                      ));
                  } else {
                      error_log(sprintf(
                          "[HE_DASHBOARD] ✅ OK | Profil: %s | Eval ID: %d | Score: %.2f%% | Oui:%d Non:%d N/A:%d",
                          $p, $eval->id, $final_score, $count_oui, $count_non, $count_na
                      ));
                  }

                  // ✅ AFFICHAGE DES DÉTAILS - Aligner avec la logique de HE_Scoring
                  echo '<table class="widefat striped">';
                  echo '<thead><tr><th>Chapitre</th><th>Question</th><th>Réponse</th><th>Impact</th><th>Statut</th></tr></thead><tbody>';
                  
                  // ⚠️ ATTENTION : Cette boucle affiche SEULEMENT les questions de la page courante
                  // Le score en bas de tableau est calculé sur TOUTES les questions
                  foreach ($details as $d) {
                      $impact_labels = [1 => '⚪ Léger', 3 => '🟢 Moyen', 5 => '🔴 Fort'];
                      $impact_label = $impact_labels[intval($d->poids)] ?? esc_html($d->poids);
                      
                      // Afficher le statut selon la réponse
                      $statut = '';
                      $statut_color = '';
                      
                      if ($d->reponse === 'Oui') {
                          $statut =  number_format(floatval($d->poids), 1) . ' pts';
                          $statut_color = 'color:#16a34a;font-weight:600;';
                      } elseif ($d->reponse === 'Non') {
                          $statut = '0 pt';
                          $statut_color = 'color:#dc2626;font-weight:600;';
                      } elseif ($d->reponse === 'N/A') {
                          $statut = 'Exclu du calcul';
                          $statut_color = 'color:#d97706;font-weight:600;';
                      }

                      echo '<tr>';
                      echo '<td>' . esc_html($d->chapitre ?: '—') . '</td>';
                      echo '<td>' . esc_html($d->question_text) . '</td>';
                      echo '<td><strong>' . esc_html($d->reponse) . '</strong></td>';
                      echo '<td>' . $impact_label . '</td>';
                      echo '<td style="' . $statut_color . '">' . $statut . '</td>';
                      echo '</tr>';
                  }
                  
                  // 🎯 LIGNE DU SCORE TOTAL - DOIT CORRESPONDRE À HE_Scoring::calculate_score()
                  echo '<tr style="font-weight:bold;background:#e8f5e9;border-top:2px solid #4caf50;">';
                  echo '<td colspan="4" style="text-align:right;">✅ Score total du profil ' . esc_html($p) . ' :</td>';
                  echo '<td style="color:#2e7d32;font-size:16px;">';
                  echo esc_html($final_score) . '%';
                  
                  // 🔍 Afficher un badge d'alerte si différence détectée
                  if ($score_diff > 0.1) {
                      echo ' <span style="background:#fef2f2;color:#991b1b;padding:3px 8px;border-radius:4px;font-size:11px;font-weight:600;">';
                      echo '⚠️ DIFF: ' . number_format($score_diff, 2) . '%';
                      echo '</span>';
                  }
                  
                  echo '</td>';
                  echo '</tr>';
                  
                  // Ligne d'explication détaillée
                  echo '<tr style="font-size:12px;color:#666;background:#f0fdf4;">';
                  echo '<td colspan="5" style="padding:12px;">';
                  echo '<strong>📊 Détail du calcul :</strong><br>';
                  echo '• Questions <strong style="color:#16a34a;">Oui</strong> : ' . esc_html($count_oui) . ' (' . number_format($scored_poids, 1) . ' points obtenus)<br>';
                  echo '• Questions <strong style="color:#dc2626;">Non</strong> : ' . esc_html($count_non) . ' (0 point)<br>';
                  echo '• Questions <strong style="color:#d97706;">N/A</strong> : ' . esc_html($count_na) . ' (exclues du calcul)<br>';
                  echo '• <strong>Total poids évalué</strong> : ' . number_format($total_poids, 1) . ' (uniquement Oui + Non)<br>';
                  echo '• <strong>Formule</strong> : (' . number_format($scored_poids, 1) . ' / ' . number_format($total_poids, 1) . ') × 100 = <strong style="color:#2e7d32;">' . esc_html($final_score) . '%</strong>';
                  echo '</td>';
                  echo '</tr>';
                  echo '</tbody></table>';

                  // ✅ PAGINATION CORRIGÉE
                  $total_pages = ceil($total_rows / $per_page);
                  if ($total_pages > 1) {
                      echo '<div class="he-pagination" style="margin-top:15px;text-align:center;">';
                      
                      for ($pnum = 1; $pnum <= $total_pages; $pnum++) {
                          $is_current = ($pnum == $page);
                          $btn_class = $is_current ? 'button button-primary' : 'button';
                          
                          // ✅ Construire l'URL en REMPLAÇANT tous les autres paramètres de pagination
                          $clean_url = remove_query_arg([
                              'page_profil_directeur',
                              'page_profil_administrateur_hopital', 
                              'page_profil_medecin',
                              'page_profil_infirmier'
                          ]);
                          
                          // Ajouter SEULEMENT le paramètre du profil actuel
                          $url = add_query_arg($page_param, $pnum, $clean_url);
                          
                          echo '<a href="' . esc_url($url) . '" class="' . esc_attr($btn_class) . '" style="margin:0 3px;">';
                          echo esc_html($pnum);
                          echo '</a>';
                      }
                      
                      echo '</div>';
                      echo '<p style="text-align:center;margin-top:10px;color:#666;font-size:13px;">';
                      echo 'Page ' . esc_html($page) . ' sur ' . esc_html($total_pages);
                      echo ' (' . esc_html($total_rows) . ' questions au total)';
                      echo '</p>';
                  }
              } else {
                  echo '<p>Aucune donnée trouvée pour ce profil.</p>';
              }
          } else {
              echo '<p>Aucune évaluation trouvée pour ce profil.</p>';
          }
          ?>
        </div>
      <?php endforeach; ?>

      <div id="tab-global" class="tab-panel">
        <h4>Calcul du score global</h4>
        <?php if (!empty($scores)): ?>
          <?php
            $global_score = round(array_sum($scores) / count($scores), 2);

            // Détermination du commentaire
            if ($global_score < 40) {
                $comment_title = "📉 Niveau faible";
                $comment_text  = "Les performances globales de l'hôpital nécessitent une amélioration urgente. Une revue complète des processus et pratiques est recommandée.";
                $color = "#e53935";
            } elseif ($global_score < 60) {
                $comment_title = "⚠️ Niveau moyen";
                $comment_text  = "Les résultats sont moyens. Des ajustements ciblés pourraient améliorer significativement la qualité globale.";
                $color = "#fb8c00";
            } elseif ($global_score < 80) {
                $comment_title = "✅ Niveau bon";
                $comment_text  = "L'hôpital présente de bonnes performances, avec des marges de progression possibles vers l'excellence.";
                $color = "#43a047";
            } else {
                $comment_title = "🏆 Niveau excellent";
                $comment_text  = "Les performances globales de l'hôpital sont excellentes. Un maintien des standards et un partage des bonnes pratiques sont conseillés.";
                $color = "#1e88e5";
            }
          ?>

          <p><strong>Formule :</strong> (Σ scores des profils / nombre de profils) =
             <strong><?php echo esc_html($global_score); ?>%</strong></p>
          <p>Cette moyenne agrège les scores Directeur, Administrateur, Médecin et Infirmier.</p>

          <div class="he-comment" style="margin-top:20px;padding:15px;border-left:5px solid <?php echo esc_attr($color); ?>;background:#fafafa;border-radius:6px;">
            <h4 style="margin:0;color:<?php echo esc_attr($color); ?>;"><?php echo esc_html($comment_title); ?></h4>
            <p style="margin-top:5px;"><?php echo esc_html($comment_text); ?></p>
          </div>

        <?php else: ?>
          <p>Aucune donnée disponible pour calculer le score global.</p>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>

<style>
.he-tabs { margin-top: 20px; }
.tab-buttons { 
  display: flex; 
  flex-wrap: wrap; 
  gap: 5px; 
  margin-bottom: 10px;
  border-bottom: 2px solid #e0e0e0;
  padding-bottom: 5px;
}
.tab-btn {
  background: #f5f5f5;
  border: 1px solid #ddd;
  padding: 10px 18px;
  border-radius: 6px 6px 0 0;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.2s ease;
}
.tab-btn:hover { 
  background: #e0e0e0; 
}
.tab-btn.active { 
  background: #1565c0; 
  color: #fff;
  border-color: #1565c0;
}
.tab-panel { 
  display: none;
  padding-top: 15px;
}
.tab-panel.active { 
  display: block; 
  animation: fadeIn 0.3s ease; 
}
@keyframes fadeIn { 
  from {opacity: 0; transform: translateY(-5px);} 
  to {opacity: 1; transform: translateY(0);} 
}
.he-pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  flex-wrap: wrap;
  gap: 5px;
}
.he-pagination .button {
  min-width: 40px;
  text-align: center;
}
.he-pagination .button-primary {
  font-weight: bold;
}
.statut-style{
  font-size: 12px;
}

.he-dashboard h3 {
    margin-top: 3rem;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const buttons = document.querySelectorAll('.tab-btn');
  const panels = document.querySelectorAll('.tab-panel');
  
  // ✅ ÉTAPE 1 : Restaurer l'onglet actif après pagination
  const urlParams = new URLSearchParams(window.location.search);
  
  // Mapping EXACT des paramètres de pagination vers les index d'onglets
  const paginationParams = {
    'page_profil_directeur': 0,
    'page_profil_administrateur_hopital': 1,  // ✅ CORRIGÉ : hopital avec 'o'
    'page_profil_medecin': 2,
    'page_profil_infirmier': 3
  };
  
  let activeTabIndex = 0; // Par défaut, le premier onglet
  
  // Vérifier quel paramètre de pagination est présent
  for (const [param, index] of Object.entries(paginationParams)) {
    if (urlParams.has(param)) {
      activeTabIndex = index;
      console.log('✅ Paramètre détecté:', param, '→ Onglet', index);
      break; // Prendre seulement le premier trouvé
    }
  }
  
  // Activer l'onglet correspondant
  buttons.forEach((b, i) => {
    if (i === activeTabIndex) {
      b.classList.add('active');
    } else {
      b.classList.remove('active');
    }
  });
  
  panels.forEach((p, i) => {
    if (i === activeTabIndex) {
      p.classList.add('active');
    } else {
      p.classList.remove('active');
    }
  });
  
  // ✅ ÉTAPE 2 : Gestion du changement d'onglet au clic
  buttons.forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      
      // Désactiver tous les boutons et panneaux
      buttons.forEach(b => b.classList.remove('active'));
      panels.forEach(p => p.classList.remove('active'));
      
      // Activer le bouton cliqué et son panneau
      btn.classList.add('active');
      const targetPanel = document.getElementById(btn.dataset.tab);
      if (targetPanel) {
        targetPanel.classList.add('active');
      }
    });
  });
});
</script>