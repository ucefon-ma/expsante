<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

$current_user = wp_get_current_user();
$roles = $current_user->roles;
$profil = $roles[0] ?? 'Utilisateur';

// 🔹 Mapping rôle → profil clair
$profil_map = [
  'directeur_hopital' => 'Directeur',
  'administrateur_hopital' => 'Administrateur Hôpital',
  'medecin' => 'Médecin',
  'infirmier' => 'Infirmier',
];
$profil_label = $profil_map[$profil] ?? ucfirst(str_replace('_', ' ', $profil));

// 🔹 Récupère les questions actives
$questions = $wpdb->get_results($wpdb->prepare("
  SELECT * FROM {$wpdb->prefix}hospital_questions
  WHERE profil = %s AND active = 1
  ORDER BY chapitre ASC, id ASC
", $profil_label));

if (!$questions) {
  echo '<p>Aucune question trouvée pour votre profil.</p>';
  return;
}

// 🔹 Anciennes réponses (préremplissage)
$user_id = get_current_user_id();
$user_hospital = get_field('hospital_id', 'user_' . $user_id);
$hospital_id = is_array($user_hospital) ? ($user_hospital[0] ?? '') : $user_hospital;

$evaluation = $wpdb->get_row($wpdb->prepare("
  SELECT id FROM {$wpdb->prefix}hospital_evaluations
  WHERE user_id = %d AND hospital_id = %d AND profil = %s
  ORDER BY updated_at DESC LIMIT 1
", $user_id, $hospital_id, $profil_label));

$answers = [];
if ($evaluation) {
  $rows = $wpdb->get_results($wpdb->prepare("
    SELECT question_id, reponse FROM {$wpdb->prefix}hospital_answers
    WHERE evaluation_id = %d
  ", $evaluation->id));
  foreach ($rows as $r) {
    $answers[$r->question_id] = $r->reponse;
  }
}

// 🔹 Groupement par chapitre
$grouped_questions = [];
foreach ($questions as $q) {
  $chapter = $q->chapitre ?: 'Autres';
  $grouped_questions[$chapter][] = $q;
}
?>

<div class="he-form">
  <h3>Évaluation du profil : <strong><?php echo esc_html($profil_label); ?></strong></h3>

  <!-- 📊 Suivi des chapitres -->
  <div class="he-progress-header" id="heChapterStatus"></div>
  <div class="progress-container">
    <div class="progress-bar" id="heProgress"></div>
  </div>

  <form id="evaluationForm">
    <input type="hidden" name="hospital_id" value="<?php echo esc_attr($hospital_id); ?>">

    <div id="heFormSteps">
      <?php $step = 0; foreach ($grouped_questions as $chapter => $qs): ?>
        <div class="he-step" data-step="<?php echo $step; ?>" data-chapter="<?php echo esc_attr($chapter); ?>" style="display:none;">
          <h3 class="he-chapter-title">
            <?php echo esc_html($chapter); ?> 
            <span class="he-chapter-progress" data-chapter-progress="<?php echo $step; ?>">(<?php echo count($qs); ?>)</span>
          </h3>
          <?php foreach ($qs as $q): 
            $checked = $answers[$q->id] ?? ''; ?>
            <div class="he-question">
              <p class="he-question-text"><strong><?php echo esc_html($q->question_text); ?></strong></p>
              <div class="he-radio-group" data-question-id="<?php echo $q->id; ?>">
                <?php foreach (['Oui', 'Non', 'N/A'] as $choice): ?>
                  <label class="he-radio">
                    <input type="radio" name="q_<?php echo $q->id; ?>" value="<?php echo $choice; ?>" 
                      <?php checked($checked, $choice); ?> required>
                    <span><?php echo $choice; ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php $step++; endforeach; ?>
    </div>

    <div class="he-step-nav" style="text-align:center;margin-top:25px;">
      <button type="button" id="hePrev">⬅️ Précédent</button>
      <button type="button" id="heNext">Suivant ➡️</button>
      <button type="submit" id="heSubmit">✅ Soumettre mes réponses</button>
    </div>
  </form>
</div>
