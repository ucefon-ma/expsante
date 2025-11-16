<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

/* ==========================================================
   🔸 Suppression sécurisée d'une évaluation
   ========================================================== */
if (isset($_GET['delete_eval'])) {
    $id = intval($_GET['delete_eval']);
    $nonce = $_GET['_wpnonce'] ?? '';
    if (!wp_verify_nonce($nonce, 'he_delete_eval_' . $id)) {
        wp_die('Action non autorisée.');
    }

    // Supprime les réponses liées puis l’évaluation
    $wpdb->delete("{$wpdb->prefix}hospital_answers", ['evaluation_id' => $id], ['%d']);
    $wpdb->delete("{$wpdb->prefix}hospital_evaluations", ['id' => $id], ['%d']);

    echo '<div class="updated notice"><p>🗑️ Évaluation supprimée avec succès.</p></div>';
}

echo '<div class="wrap exp-sante-page">';
echo '<h1>📊 Évaluations hospitalières</h1>';
echo '<div class="exp-card">';

echo '<h2 class="nav-tab-wrapper">';
echo '<a href="#tab1" class="nav-tab nav-tab-active">📈 Statistiques globales</a>';
echo '<a href="#tab2" class="nav-tab">🏥 Scores moyens par hôpital</a>';
echo '<a href="#tab3" class="nav-tab">🕓 Dernières soumissions</a>';
echo '</h2>';

/* ==========================================================
   🧩 ONGLET 1 : Statistiques globales + Contrôle des doublons
   ========================================================== */
?>

<?php
/* ==========================================================
   🧩 ONGLET 1 : Statistiques globales
   ========================================================== */
?>
<div id="tab1" class="tab-content active">
  <h2>📈 Statistiques globales</h2>

  <?php
  // Comptage des hôpitaux publiés
  $total_hopitaux = (int) $wpdb->get_var("
      SELECT COUNT(*) 
      FROM {$wpdb->prefix}posts 
      WHERE post_type = 'hospital' AND post_status = 'publish'
  ");

  // Comptage précis des questions actives par profil
  $total_questions = $wpdb->get_results("
      SELECT profil, COUNT(id) AS total
      FROM {$wpdb->prefix}hospital_questions
      WHERE active = 1
      GROUP BY profil
      ORDER BY profil ASC
  ");

  // Reformatage dans un tableau associatif clair
  $stats = [
      'Directeur'             => 0,
      'Administrateur Hôpital'=> 0,
      'Médecin'               => 0,
      'Infirmier'             => 0
  ];
  foreach ($total_questions as $row) {
      if (isset($stats[$row->profil])) {
          $stats[$row->profil] = (int) $row->total;
      }
  }
  ?>

  <!-- 💡 Section des statistiques globales -->
  <div class="he-stats-cards">
    <div class="he-card he-card-main">
      <div class="he-card-icon">🏥</div>
      <div class="he-card-content">
        <div class="he-card-title">Hôpitaux inscrits</div>
        <div class="he-card-number"><?php echo esc_html($total_hopitaux); ?></div>
      </div>
    </div>

    <?php foreach ($stats as $profil => $nb): ?>
      <div class="he-card">
        <div class="he-card-icon">
          <?php
          echo $profil === 'Directeur' ? '🧑‍💼' :
               ($profil === 'Administrateur Hôpital' ? '🧑‍💼' :
               ($profil === 'Médecin' ? '🧑‍⚕️' : '💉'));
          ?>
        </div>
        <div class="he-card-content">
          <div class="he-card-title"><?php echo esc_html($profil); ?></div>
          <div class="he-card-number"><?php echo esc_html($nb); ?></div>
          <div class="he-card-sub">Questions actives</div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <p style="margin-top:25px;color:#666;font-size:13px;">
    🚨 Ces statistiques affichent uniquement les <strong>questions actives</strong> actuellement disponibles dans la base de données,
    classées par profil d’évaluation.
  </p>
</div>


<?php
/* ==========================================================
   🧮 ONGLET 2 : Scores moyens par hôpital et par profil - CORRIGÉ
   ========================================================== */
?>
<div id="tab2" class="tab-content">
<h2>🏥 Scores moyens par hôpital et par profil</h2>

<form method="get" id="score-filter-form" style="margin-bottom:15px;">
  <input type="hidden" name="page" value="hospital_evaluations">
  <input type="hidden" name="active_tab" value="tab2">
  <button type="button" id="ajax-refresh-tab2" class="button">🔄 Actualiser</button>
</form>

<div id="score-table">
<?php
// Pagination
$per_page = 20;
$current_page = isset($_GET['paged2']) ? max(1, intval($_GET['paged2'])) : 1;
$offset = ($current_page - 1) * $per_page;

// ✅ REQUÊTE CORRIGÉE : N/A exclus du calcul
$evaluations = $wpdb->get_results("
    SELECT e.hospital_id, e.profil, a.reponse, q.poids
    FROM {$wpdb->prefix}hospital_evaluations e
    INNER JOIN {$wpdb->prefix}hospital_answers a ON a.evaluation_id = e.id
    INNER JOIN {$wpdb->prefix}hospital_questions q ON q.id = a.question_id
    WHERE e.id IN (
        SELECT MAX(id)
        FROM {$wpdb->prefix}hospital_evaluations
        GROUP BY hospital_id, profil
    )
    AND e.profil IS NOT NULL 
    AND e.profil != ''
    AND q.active = 1
");

// Traitement des données avec N/A EXCLUS
$hospitals = [];
if ($evaluations) {
    foreach ($evaluations as $row) {
        $h = (int) $row->hospital_id;
        $p = sanitize_text_field($row->profil ?: 'Inconnu');

        if (!isset($hospitals[$h])) {
            $hospitals[$h] = [
                'nom' => get_the_title($h),
                'profils' => [],
            ];
        }

        if (!isset($hospitals[$h]['profils'][$p])) {
            $hospitals[$h]['profils'][$p] = [
                'total_poids' => 0,   // Total du poids des questions Oui+Non
                'scored_poids' => 0,  // Points obtenus (seulement Oui)
                'count_oui' => 0,
                'count_non' => 0,
                'count_na' => 0
            ];
        }

        $poids = floatval($row->poids);
        
        // ✅ NOUVELLE LOGIQUE : N/A exclus
        if ($row->reponse === 'Oui') {
            $hospitals[$h]['profils'][$p]['total_poids'] += $poids;
            $hospitals[$h]['profils'][$p]['scored_poids'] += $poids;
            $hospitals[$h]['profils'][$p]['count_oui']++;
        } elseif ($row->reponse === 'Non') {
            $hospitals[$h]['profils'][$p]['total_poids'] += $poids;
            // scored_poids += 0 (pas de points pour Non)
            $hospitals[$h]['profils'][$p]['count_non']++;
        } elseif ($row->reponse === 'N/A') {
            // ❌ N/A = Exclu complètement du calcul
            $hospitals[$h]['profils'][$p]['count_na']++;
        }
    }
}

// Découpage selon pagination
$total_items = count($hospitals);
$hospital_chunks = array_slice($hospitals, $offset, $per_page, true);

// Table
$profils = ['Directeur', 'Administrateur Hôpital', 'Médecin', 'Infirmier'];

echo '<table class="widefat striped">';
echo '<thead><tr>
        <th>Nom de l\'hôpital</th>';
foreach ($profils as $p) {
    echo '<th>' . esc_html($p) . '</th>';
}
echo '<th>Score global</th></tr></thead><tbody>';

if ($hospital_chunks) {
    foreach ($hospital_chunks as $hopital) {
        echo '<tr>';
        echo '<td><strong>' . esc_html($hopital['nom']) . '</strong></td>';
        
        $sum = 0;
        $count = 0;

        foreach ($profils as $p) {
            if (isset($hopital['profils'][$p])) {
                $data = $hopital['profils'][$p];
                
                // Calcul du score : (points obtenus / total poids évalué) * 100
                $score = $data['total_poids'] > 0 
                    ? round(($data['scored_poids'] / $data['total_poids']) * 100, 1) 
                    : 0;
                
                // Tooltip avec détails
                $tooltip = sprintf(
                    'Oui: %d | Non: %d | N/A: %d (exclus)',
                    $data['count_oui'],
                    $data['count_non'],
                    $data['count_na']
                );
                
                echo '<td title="' . esc_attr($tooltip) . '">';
                echo '<strong style="color:#2563eb;">' . esc_html($score) . '%</strong>';
                
                // Badge si beaucoup de N/A
                if ($data['count_na'] > 5) {
                    echo ' <span style="font-size:11px;color:#d97706;">⚠️ ' . $data['count_na'] . ' N/A</span>';
                }
                
                echo '</td>';
                $sum += $score;
                $count++;
            } else {
                echo '<td style="color:#9ca3af;">—</td>';
            }
        }

        $global = $count ? round($sum / $count, 1) : 0;
        echo '<td><strong style="color:#1e40af;font-size:15px;">' . esc_html($global) . '%</strong></td>';
        echo '</tr>';
    }
} else {
    echo '<tr><td colspan="6" style="text-align:center;padding:30px;color:#6b7280;">📭 Aucune donnée trouvée.</td></tr>';
}
echo '</tbody></table>';

// Légende
echo '<div style="background:#f0f9ff;padding:12px;border-radius:8px;margin-top:15px;font-size:13px;color:#0c4a6e;border-left:4px solid #2563eb;">';
echo '<strong>ℹ️ Méthode de calcul :</strong> Les scores sont calculés sur les questions répondues par <strong>Oui</strong> ou <strong>Non</strong> uniquement. ';
echo 'Les réponses <strong>N/A</strong> sont <strong>exclues</strong> pour encourager des réponses claires.';
echo '</div>';

// Pagination
$total_pages = ceil($total_items / $per_page);
if ($total_pages > 1) {
    echo '<div class="tab-pagination" style="margin-top:15px;text-align:center;">';
    for ($i = 1; $i <= $total_pages; $i++) {
        $args = [
            'page' => 'hospital_evaluations',
            'active_tab' => 'tab2',
            'paged2' => $i
        ];
        $page_url = add_query_arg($args, admin_url('admin.php'));
        
        $btn_class = ($i == $current_page) ? 'button button-primary' : 'button button-small';
        echo '<a href="' . esc_url($page_url) . '" class="' . esc_attr($btn_class) . '" style="margin:0 3px;">' . esc_html($i) . '</a> ';
    }
    echo '</div>';
}
?>
</div>

<script>
jQuery(document).ready(function($){
  $('#ajax-refresh-tab2').on('click', function(){
    const $btn = $(this);
    $btn.text('⏳ Actualisation...');
    
    $.ajax({
      url: window.location.href,
      type: 'GET',
      data: $('#score-filter-form').serialize(),
      success: function(response){
        const updated = $(response).find('#score-table').html();
        $('#score-table').html(updated);
        $btn.text('✅ Mis à jour');
        setTimeout(() => $btn.text('🔄 Actualiser'), 2000);
      },
      error: function(){
        alert('Erreur de mise à jour du tableau.');
        $btn.text('🔄 Actualiser');
      }
    });
  });
});
</script>
</div>

<?php
/* ==========================================================
   🕓 ONGLET 3 : Dernières soumissions avec filtres + suppression
   ========================================================== */
?>
<div id="tab3" class="tab-content">
<h2>🕓 Dernières soumissions</h2>

<form method="get" class="filter-bar" id="filter-form" style="margin-bottom:15px;">
  <input type="hidden" name="page" value="hospital_evaluations">
  <input type="hidden" name="active_tab" value="tab3">

  <label for="profil">Profil :</label>
  <select name="profil" id="profil">
    <option value="">Tous</option>
    <?php
    $profils = ['Directeur','Administrateur Hôpital','Médecin','Infirmier'];
    $selected_profil = $_GET['profil'] ?? '';
    foreach ($profils as $p) {
        $sel = selected($selected_profil, $p, false);
        echo "<option value='{$p}' {$sel}>{$p}</option>";
    }
    ?>
  </select>

  <label for="hopital">Hôpital :</label>
  <select name="hopital" id="hopital">
    <option value="">Tous</option>
    <?php
    $hopitaux = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->prefix}posts WHERE post_type='hospital' AND post_status='publish'");
    $selected_h = isset($_GET['hopital']) ? intval($_GET['hopital']) : 0;
    foreach ($hopitaux as $h) {
        $sel = selected($selected_h, $h->ID, false);
        echo "<option value='{$h->ID}' {$sel}>{$h->post_title}</option>";
    }
    ?>
  </select>

  <button class="button">Filtrer</button>
  <button type="button" id="ajax-refresh" class="button">🔄 Actualiser</button>
</form>

<div id="evaluations-table">
<?php
// ==========================
// 📊 PAGINATION
// ==========================
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

// ==========================
// 🔍 FILTRES
// ==========================
$where = [];
if (!empty($_GET['profil'])) $where[] = $wpdb->prepare("e.profil = %s", sanitize_text_field($_GET['profil']));
if (!empty($_GET['hopital'])) $where[] = $wpdb->prepare("e.hospital_id = %d", intval($_GET['hopital']));
$where_clause = $where ? "WHERE " . implode(" AND ", $where) : '';

// ==========================
// 📋 RÉCUPÉRATION DONNÉES
// ==========================
$total_items = $wpdb->get_var("
    SELECT COUNT(*) 
    FROM {$wpdb->prefix}hospital_evaluations e
    $where_clause
");

$submissions = $wpdb->get_results("
    SELECT e.id, e.user_id, e.hospital_id, e.profil,
           e.created_at, e.updated_at, e.last_recalculated,
           p.post_title AS hospital_name
    FROM {$wpdb->prefix}hospital_evaluations e
    LEFT JOIN {$wpdb->prefix}posts p ON e.hospital_id = p.ID
    $where_clause
    ORDER BY e.updated_at DESC, e.created_at DESC
    LIMIT $per_page OFFSET $offset
");

// ==========================
// 🧮 TABLEAU
// ==========================
echo '<table class="widefat striped">';
echo '<thead><tr>
        <th>Utilisateur</th>
        <th>Profil</th>
        <th>Hôpital</th>
        <th>Créé le</th>
        <th>Modifié le</th>
        <th>Dernier recalcul</th>
        <th>Score (%)</th>
        <th>Action</th>
      </tr></thead><tbody>';

if ($submissions) {
    foreach ($submissions as $s) {
        $user = get_user_by('id', $s->user_id);
        $username = $user ? $user->user_login : '—';
        $score = HE_Scoring::calculate_score($s->id);

        $delete_url = wp_nonce_url(
            admin_url("admin.php?page=hospital_evaluations&delete_eval={$s->id}&active_tab=tab3"),
            'he_delete_eval_' . $s->id
        );

        $date_created = $s->created_at ? date_i18n('d/m/Y H:i', strtotime($s->created_at)) : '—';
        $date_updated = $s->updated_at ? date_i18n('d/m/Y H:i', strtotime($s->updated_at)) : '—';
        $date_recalc  = $s->last_recalculated ? date_i18n('d/m/Y H:i', strtotime($s->last_recalculated)) : '—';

        // 🟢 Label "Nouvelle" si modifiée depuis <24h
        $recent = ($s->updated_at && strtotime($s->updated_at) > strtotime('-24 hours'));
        $recent_label = $recent ? '<span style="color:green;font-weight:600;">🟢</span>' : '';

        echo '<tr>';
        echo '<td>' . esc_html($username) . ' ' . $recent_label . '</td>';
        echo '<td>' . esc_html($s->profil ?: '—') . '</td>';
        echo '<td>' . esc_html($s->hospital_name ?: '—') . '</td>';
        echo '<td>' . esc_html($date_created) . '</td>';
        echo '<td>' . esc_html($date_updated) . '</td>';
        echo '<td>' . esc_html($date_recalc) . '</td>';
        echo '<td><strong>' . esc_html($score) . '%</strong></td>';
        echo '<td><a href="' . esc_url($delete_url) . '" class="button delete-eval" onclick="return confirm(\'Supprimer définitivement cette évaluation ?\');">🗑️ Supprimer</a></td>';
        echo '</tr>';
    }
} else {
    echo '<tr><td colspan="8">Aucune soumission trouvée pour les filtres appliqués.</td></tr>';
}

echo '</tbody></table>';

// ==========================
// 📄 PAGINATION
// ==========================
$total_pages = ceil($total_items / $per_page);

if ($total_pages > 1) {
    echo '<div class="tab-pagination" style="margin-top:15px;text-align:center;">';
    for ($i = 1; $i <= $total_pages; $i++) {
        $page_url = add_query_arg(array_merge($_GET, ['paged' => $i]));
        $active = ($i == $current_page) ? 'style="font-weight:bold;text-decoration:underline;"' : '';
        echo "<a href='" . esc_url($page_url) . "' class='button button-small' {$active}>$i</a> ";
    }
    echo '</div>';
}
?>
</div> <!-- /#evaluations-table -->

<script>
jQuery(document).ready(function($){
  $('#ajax-refresh').on('click', function(){
    const $btn = $(this);
    $btn.text('⏳ Actualisation...');
    const data = $('#filter-form').serialize();
    $.ajax({
      url: window.location.href,
      type: 'GET',
      data: data,
      success: function(response){
        const updated = $(response).find('#evaluations-table').html();
        $('#evaluations-table').html(updated);
        $btn.text('🔄 Actualiser');
      },
      error: function(){
        alert('Erreur de mise à jour du tableau.');
        $btn.text('🔄 Actualiser');
      }
    });
  });
});
</script>
</div>

<style>
.tab-content { display:none; margin-top:20px; }
.tab-content.active { display:block; }
.filter-bar { margin-bottom:15px; }
.filter-bar label { margin-right:5px; font-weight:600; }
.filter-bar select { margin-right:10px; }
.widefat th, .widefat td { text-align:center; vertical-align:middle; }
.widefat td strong { color:#1e40af; }
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const tabs = document.querySelectorAll('.nav-tab');
  const contents = document.querySelectorAll('.tab-content');

  // Déterminer quel onglet doit être actif
  const urlParams = new URLSearchParams(window.location.search);
  const activeTab = urlParams.get('active_tab') || 'tab1';

  // Activer l'onglet correspondant
  tabs.forEach(t => {
    t.classList.toggle('nav-tab-active', t.getAttribute('href') === '#' + activeTab);
  });
  contents.forEach(c => {
    c.classList.toggle('active', c.id === activeTab);
  });

  // Changement au clic
  tabs.forEach(tab => {
    tab.addEventListener('click', e => {
      e.preventDefault();
      tabs.forEach(t => t.classList.remove('nav-tab-active'));
      tab.classList.add('nav-tab-active');
      contents.forEach(c => c.classList.remove('active'));
      const target = document.querySelector(tab.getAttribute('href'));
      if (target) target.classList.add('active');

      // Met à jour l’URL (sans recharger la page)
      const params = new URLSearchParams(window.location.search);
      params.set('active_tab', tab.getAttribute('href').replace('#', ''));
      history.replaceState(null, '', '?' + params.toString());
    });
  });
});
</script>
