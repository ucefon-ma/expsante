<?php
if (!defined('ABSPATH')) exit;
global $wpdb;

/* ==========================================================
   🧮 Données dynamiques pour le tableau de bord
========================================================== */
$total_hopitaux = (int) $wpdb->get_var("
    SELECT COUNT(*) FROM {$wpdb->prefix}posts
    WHERE post_type = 'hospital' AND post_status = 'publish'
");

$total_evaluations = (int) $wpdb->get_var("
    SELECT COUNT(*) FROM {$wpdb->prefix}hospital_evaluations
");

$total_questions = (int) $wpdb->get_var("
    SELECT COUNT(*) FROM {$wpdb->prefix}hospital_questions
");

$total_users = (int) $wpdb->get_var("
    SELECT COUNT(*) FROM {$wpdb->users}
");

/* Dernière activité */
$last_eval = $wpdb->get_row("
    SELECT e.updated_at, p.post_title AS hospital_name, e.profil
    FROM {$wpdb->prefix}hospital_evaluations e
    LEFT JOIN {$wpdb->prefix}posts p ON e.hospital_id = p.ID
    ORDER BY e.updated_at DESC LIMIT 1
");
?>

<div class="wrap exp-sante-page">
  <h1><i class="icon-hospital"></i> EXP Santé — Tableau de bord</h1>

  <p style="font-size:15px;color:#fff;margin-bottom:25px;">
    Bienvenue dans le module <strong>EXP Santé</strong>.  
    Ce tableau de bord vous offre une vue d’ensemble de l’activité du système d’évaluation hospitalière :
    <em>évaluations, questions, statistiques et diagnostics.</em>
  </p>

  <!-- ==========================================================
       🩺 Statistiques rapides
  ========================================================== -->
  <div class="he-stats-cards">
    <div class="he-card he-card-main">
      <div class="he-card-icon">🏥</div>
      <div class="he-card-content">
        <div class="he-card-title">Hôpitaux</div>
        <div class="he-card-number"><?php echo esc_html($total_hopitaux); ?></div>
        <div class="he-card-sub">enregistrés</div>
      </div>
    </div>

    <div class="he-card">
      <div class="he-card-icon">🩺</div>
      <div class="he-card-content">
        <div class="he-card-title">Évaluations</div>
        <div class="he-card-number"><?php echo esc_html($total_evaluations); ?></div>
        <div class="he-card-sub">soumissions reçues</div>
      </div>
    </div>

    <div class="he-card">
      <div class="he-card-icon">🧠</div>
      <div class="he-card-content">
        <div class="he-card-title">Questions</div>
        <div class="he-card-number"><?php echo esc_html($total_questions); ?></div>
        <div class="he-card-sub">actives & inactives</div>
      </div>
    </div>

    <div class="he-card">
      <div class="he-card-icon">👤</div>
      <div class="he-card-content">
        <div class="he-card-title">Utilisateurs</div>
        <div class="he-card-number"><?php echo esc_html($total_users); ?></div>
        <div class="he-card-sub">comptes WordPress</div>
      </div>
    </div>
  </div>

  <!-- ==========================================================
       🕓 Dernière activité
  ========================================================== -->
  <div class="exp-card" style="margin-top:30px;">
    <h2><i class="icon-activity"></i> Dernière activité</h2>
    <?php if ($last_eval): ?>
      <p style="font-size:15px;">
        📅 <strong><?php echo date_i18n('d F Y à H:i', strtotime($last_eval->updated_at)); ?></strong><br>
        👤 Profil : <strong><?php echo esc_html($last_eval->profil); ?></strong><br>
        
        🏥 Hôpital : <strong><?php echo esc_html($last_eval->hospital_name ?: '—'); ?></strong>
      </p>
    <?php else: ?>
      <p>Aucune évaluation enregistrée pour le moment.</p>
    <?php endif; ?>
  </div>

  <!-- ==========================================================
       🔗 Accès rapide
  ========================================================== -->
  <div class="exp-card">
    <h2><i class="icon-layout-dashboard"></i> Accès rapide</h2>
    <div class="he-admin-cards">

      <div class="the-card">
        <h3><i class="icon-stethoscope"></i> Évaluations</h3>
        <p>Consultez les scores soumis par les utilisateurs selon leur profil et les hôpitaux.</p>
        <a href="<?php echo admin_url('admin.php?page=hospital_evaluations'); ?>" class="button button-primary">Ouvrir</a>
      </div>

      <div class="the-card">
        <h3><i class="icon-message-circle-question-mark"></i> Questions</h3>
        <p>Ajoutez, modifiez ou organisez les questions d’évaluation pour chaque profil.</p>
        <a href="<?php echo admin_url('admin.php?page=hospital_questions'); ?>" class="button button-secondary">Gérer</a>
      </div>

      <div class="the-card">
        <h3><i class="icon-presentation-chart"></i> Statistiques</h3>
        <p>Visualisez les performances globales par hôpital et par profil en graphiques.</p>
        <a href="<?php echo admin_url('admin.php?page=hospital_statistics'); ?>" class="button">Voir</a>
      </div>

      <div class="the-card">
        <h3><i class="icon-wrench"></i> Diagnostic</h3>
        <p>Vérifiez l’intégrité du système, corrigez les anomalies et testez la configuration.</p>
        <a href="<?php echo admin_url('admin.php?page=hospital_diagnostic'); ?>" class="button">Analyser</a>
      </div>
    </div>
  </div>
</div>

<style>
.exp-sante-page h1 {
  color:#0d47a1;
  display:flex;
  align-items:center;
  gap:8px;
}
.he-stats-cards {
  display:flex;
  flex-wrap:wrap;
  gap:20px;
  margin-bottom:25px;
}
.he-card {
  background:#fff;
  border:1px solid #e2e8f0;
  border-radius:10px;
  box-shadow:0 2px 4px rgba(0,0,0,0.05);
  padding:20px;
  flex:1 1 200px;
  text-align:center;
  transition:all .2s ease;
}
.he-card:hover { transform:translateY(-3px); box-shadow:0 4px 10px rgba(0,0,0,0.08); }
.he-card-icon { font-size:30px; margin-bottom:8px; }
.he-card-title { font-weight:600; color:#1e40af; }
.he-card-number { font-size:26px; color:#0d47a1; margin-top:5px; }
.he-card-sub { font-size:13px; color:#64748b; }

.the-card {
  background:#fff;
  border:1px solid #e2e8f0;
  border-radius:10px;
  box-shadow:0 1px 4px rgba(0,0,0,0.05);
  padding:20px;
  flex:1;
  min-width:220px;
  transition:all .2s ease;
}
.the-card:hover { transform:translateY(-3px); box-shadow:0 3px 10px rgba(0,0,0,0.1); }
.the-card h3 { color:#0d47a1; display:flex; align-items:center; gap:6px; }
.the-card p { color:#475569; font-size:14px; min-height:45px; }
.the-card .button { margin-top:10px; }
.he-admin-cards {
  display:flex;
  flex-wrap:wrap;
  gap:20px;
  margin-top:20px;
}
.exp-card {
  background:#fff;
  border:1px solid #dbeafe;
  border-radius:12px;
  box-shadow:0 2px 6px rgba(0,0,0,0.05);
  padding:25px;
  margin-bottom:25px;
}
</style>
