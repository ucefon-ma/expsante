<?php
if (!defined('ABSPATH')) exit;
global $wpdb;

/* ==========================================================
   🎛️ Filtres temporels
========================================================== */
$period = $_GET['period'] ?? 'month';

switch ($period) {
    case 'quarter':
        $title_period = "📅 Trimestre en cours";
        $date_min = date('Y-m-d', strtotime('-3 months'));
        break;
    case 'year':
        $title_period = "📅 Année en cours";
        $date_min = date('Y-01-01');
        break;
    default:
        $title_period = "📅 Mois en cours";
        $date_min = date('Y-m-01');
        break;
}

/* ==========================================================
   🧠 Étape 1 : récupérer les dernières évaluations par hôpital/profil
========================================================== */
$latest_evals = $wpdb->get_results($wpdb->prepare("
    SELECT MAX(id) as id, hospital_id, profil
    FROM {$wpdb->prefix}hospital_evaluations
    WHERE created_at >= %s
    GROUP BY hospital_id, profil
", $date_min));

/* ==========================================================
   🧩 Étape 2 : calcul des scores par hôpital
========================================================== */
$hospital_scores = [];

if ($latest_evals) {
    foreach ($latest_evals as $eval) {
        $score = HE_Scoring::calculate_score($eval->id);
        $hospital_id = $eval->hospital_id;
        $hospital_scores[$hospital_id][] = $score;
    }
}

/* ==========================================================
   🏥 Étape 3 : calcul global par hôpital et répartition
========================================================== */
$levels = ['Faible' => 0, 'Moyen' => 0, 'Bien' => 0, 'Excellent' => 0];

foreach ($hospital_scores as $scores) {
    $avg = count($scores) ? round(array_sum($scores) / count($scores), 1) : 0;
    if ($avg < 40) $levels['Faible']++;
    elseif ($avg < 60) $levels['Moyen']++;
    elseif ($avg < 80) $levels['Bien']++;
    else $levels['Excellent']++;
}

/* ==========================================================
   👤 Étape 4 : score moyen par profil
========================================================== */
$profils = ['Directeur', 'Administrateur Hôpital', 'Médecin', 'Infirmier'];
$profil_scores = [];

foreach ($profils as $profil) {
    $ids = $wpdb->get_col($wpdb->prepare("
        SELECT MAX(id) FROM {$wpdb->prefix}hospital_evaluations
        WHERE profil = %s AND created_at >= %s
        GROUP BY hospital_id
    ", $profil, $date_min));

    $scores = [];
    foreach ($ids as $id) $scores[] = HE_Scoring::calculate_score($id);
    $profil_scores[$profil] = count($scores) ? round(array_sum($scores) / count($scores), 1) : 0;
}

/* ==========================================================
   🎨 Affichage HTML
========================================================== */


echo '<div class="wrap exp-sante-page">';
echo '<h1>📊 Statistiques des scores</h1>';
echo '<div class="exp-card">';


echo '<form method="GET" style="margin-bottom:20px;">';
echo '<input type="hidden" name="page" value="hospital_statistics">';
echo '<label><strong>Filtrer par période :</strong></label> ';
echo '<select name="period" onchange="this.form.submit()">';
echo '<option value="month"' . selected($period, 'month', false) . '>Mois en cours</option>';
echo '<option value="quarter"' . selected($period, 'quarter', false) . '>Trimestre</option>';
echo '<option value="year"' . selected($period, 'year', false) . '>Année</option>';
echo '</select>';
echo '</form>';

echo "<h2>{$title_period}</h2>";

$total_hospitals = array_sum($levels);

if ($total_hospitals === 0) {
    echo '<p style="padding:20px;background:#fff3cd;border:1px solid #ffeeba;border-radius:6px;">⚠️ Aucune donnée disponible pour la période sélectionnée.</p>';
} else {

    /* 🏥 Répartition des hôpitaux */
    echo '<h3>🏥 Répartition des hôpitaux selon le niveau de score</h3>';
    echo '<div class="he-chart-row">';
    echo '<div class="he-chart-card"><canvas id="chartScores"></canvas></div>';
    echo '<div class="he-table-card">';
    echo '<table class="widefat"><thead><tr><th>Niveau</th><th>Nombre</th><th>%</th></tr></thead><tbody>';
    foreach ($levels as $label => $count) {
        $percent = round(($count / $total_hospitals) * 100, 1);
        echo "<tr><td><strong>{$label}</strong></td><td>{$count}</td><td>{$percent}%</td></tr>";
    }
    echo '</tbody></table></div></div>';

    /* 👤 Moyenne par profil */
    echo '<hr><h3>👤 Moyenne des scores par profil</h3>';
    echo '<div class="he-chart-card" style="max-width:450px;margin:auto;"><canvas id="chartProfils"></canvas></div>';
    echo '<table class="widefat" style="max-width:450px;margin:15px auto;"><thead><tr><th>Profil</th><th>Score moyen (%)</th></tr></thead><tbody>';
    foreach ($profil_scores as $profil => $avg) {
        echo "<tr><td>{$profil}</td><td><strong>{$avg}%</strong></td></tr>";
    }
    echo '</tbody></table>';
}

echo '</div></div>';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const dataLevels = <?php echo json_encode(array_values($levels)); ?>;
    const labelsLevels = <?php echo json_encode(array_keys($levels)); ?>;
    const totalHospitals = <?php echo $total_hospitals; ?>;

    const dataProfils = <?php echo json_encode(array_values($profil_scores)); ?>;
    const labelsProfils = <?php echo json_encode(array_keys($profil_scores)); ?>;

    if (totalHospitals > 0) {
        new Chart(document.getElementById('chartScores'), {
            type: 'doughnut',
            data: {
                labels: labelsLevels,
                datasets: [{
                    data: dataLevels,
                    backgroundColor: ['#dc2626', '#facc15', '#22c55e', '#3b82f6']
                }]
            },
            options: {
                plugins: {
                    title: { display: true, text: 'Répartition des hôpitaux par niveau (%)' },
                    datalabels: {
                        color: function(context) {
                            const bg = context.dataset.backgroundColor[context.dataIndex];
                            const rgb = parseInt(bg.slice(1), 16);
                            const r = (rgb >> 16) & 0xff;
                            const g = (rgb >> 8) & 0xff;
                            const b = (rgb >> 0) & 0xff;
                            const brightness = (r*299 + g*587 + b*114) / 1000;
                            return brightness > 140 ? '#000' : '#fff';
                        },
                        formatter: (value) => totalHospitals ? ((value / totalHospitals) * 100).toFixed(1) + '%' : ''
                    },
                    legend: { position: 'bottom', labels: { boxWidth: 15 } }
                }
            },
            plugins: [ChartDataLabels]
        });

        new Chart(document.getElementById('chartProfils'), {
            type: 'bar',
            data: {
                labels: labelsProfils,
                datasets: [{
                    label: 'Score moyen (%)',
                    data: dataProfils,
                    backgroundColor: ['#2563eb', '#9333ea', '#16a34a', '#f97316']
                }]
            },
            options: {
                plugins: {
                    legend: { display: false },
                    title: { display: true, text: 'Score moyen par profil' }
                },
                scales: { y: { beginAtZero: true, max: 100 } }
            }
        });
    }
});
</script>

