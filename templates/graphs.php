<?php
/**
 * Template: Graphiques de répartition des réponses + Analyse par chapitre
 * Shortcode: [hospital_graphs]
 * Fichier: /templates/graphs.php
 * Version: 2.0 avec analyse chapitres
 */

if (!defined('ABSPATH')) exit;

// Les variables sont déjà définies par le shortcode
// $hospital_id, $hospital_name, $is_directeur, $roles
?>

<div class="he-graphs-container">
    <h2 style="color:#1e40af;margin-bottom:25px;display:flex;align-items:center;gap:10px;">
        📊 Graphiques des réponses
        <span style="font-size:16px;color:#6b7280;font-weight:400;">
            — <?php echo esc_html($hospital_name); ?>
        </span>
    </h2>

    <?php if ($is_directeur): ?>
        <!-- 🏆 Vue Directeur : Tous les profils -->
        <p style="background:#dbeafe;padding:12px;border-radius:8px;color:#1e40af;margin-bottom:20px;">
            🏆 <strong>Vue Directeur :</strong> Vous visualisez les graphiques et analyses de tous les profils de votre hôpital.
        </p>

        <div class="he-graphs-grid">
            <?php
            $profils = [
                'Directeur' => ['color' => '#2563eb', 'icon' => '👔'],
                'Administrateur Hôpital' => ['color' => '#9333ea', 'icon' => '💼'],
                'Médecin' => ['color' => '#16a34a', 'icon' => '⚕️'],
                'Infirmier' => ['color' => '#f97316', 'icon' => '💉']
            ];

            foreach ($profils as $profil => $config) {
                he_render_profile_card($hospital_id, $profil, $config['color'], $config['icon']);
            }
            ?>
        </div>

        <!-- 📈 Graphique de synthèse global -->
        <?php he_render_global_graph($hospital_id); ?>

    <?php else: ?>
        <!-- 👤 Vue utilisateur standard : Son propre profil uniquement -->
        <p style="background:#f0fdf4;padding:12px;border-radius:8px;color:#166534;margin-bottom:20px;">
            👤 <strong>Votre profil :</strong> Vous visualisez les statistiques et l'analyse détaillée de votre évaluation.
        </p>

        <?php
        // Mapper le rôle vers le libellé du profil
        $role_mapping = [
            'directeur_hopital' => ['label' => 'Directeur', 'icon' => '👔'],
            'administrateur_hopital' => ['label' => 'Administrateur Hôpital', 'icon' => '💼'],
            'medecin' => ['label' => 'Médecin', 'icon' => '⚕️'],
            'infirmier' => ['label' => 'Infirmier', 'icon' => '💉']
        ];

        $user_role = $roles[0] ?? '';
        $profile_data = $role_mapping[$user_role] ?? ['label' => 'Utilisateur', 'icon' => '👤'];

        he_render_profile_card($hospital_id, $profile_data['label'], '#2563eb', $profile_data['icon'], true);
        ?>
    <?php endif; ?>
</div>

<!-- Styles CSS -->
<style>
.he-graphs-container {
    padding: 25px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    margin: 20px 0;
}
.he-graphs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
    gap: 25px;
    margin-bottom: 30px;
}
.he-graph-card {
    background: #fff;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.06);
}
.he-graph-card.single {
    max-width: 900px;
    margin: 0 auto;
}
.he-graph-card h3 {
    margin: 0 0 20px 0;
    font-size: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.he-graph-canvas {
    max-height: 280px;
    margin-bottom: 20px;
}
.he-stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-top: 20px;
}
.he-stat-item {
    text-align: center;
    padding: 15px 10px;
    background: #f9fafb;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
}
.he-stat-item .label {
    font-size: 13px;
    color: #6b7280;
    margin-bottom: 8px;
    font-weight: 600;
}
.he-stat-item .value {
    font-size: 24px;
    font-weight: 700;
}
.he-global-graph {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    padding: 35px;
    color: white;
    margin-top: 40px;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
}
.he-global-graph h3 {
    margin: 0 0 25px 0;
    color: white;
    font-size: 26px;
    font-weight: 700;
}
.he-no-data {
    text-align: center;
    padding: 50px 20px;
    color: #6b7280;
    background: #f9fafb;
    border-radius: 8px;
    border: 2px dashed #d1d5db;
}
@media (max-width: 768px) {
    .he-graphs-grid {
        grid-template-columns: 1fr;
    }
    .he-stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Scripts Chart.js - Chargement avec vérification -->
<script>
if (typeof Chart === 'undefined') {
    console.log('📊 Chargement de Chart.js...');
    const chartScript = document.createElement('script');
    chartScript.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
    chartScript.onload = function() {
        console.log('✅ Chart.js chargé');
        const datalabelsScript = document.createElement('script');
        datalabelsScript.src = 'https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js';
        datalabelsScript.onload = function() {
            console.log('✅ ChartDataLabels chargé');
            window.dispatchEvent(new Event('chartjs-ready'));
        };
        document.head.appendChild(datalabelsScript);
    };
    document.head.appendChild(chartScript);
} else {
    console.log('✅ Chart.js déjà chargé');
    setTimeout(() => window.dispatchEvent(new Event('chartjs-ready')), 100);
}
</script>

<?php
/* =======================================================
   FONCTION PRINCIPALE : CARTE PROFIL COMPLÈTE
======================================================= */
function he_render_profile_card($hospital_id, $profil, $color, $icon, $single_view = false) {
    global $wpdb;

    $eval = $wpdb->get_row($wpdb->prepare("
        SELECT id, score_final, created_at
        FROM {$wpdb->prefix}hospital_evaluations
        WHERE hospital_id = %d AND profil = %s
        ORDER BY created_at DESC LIMIT 1
    ", $hospital_id, $profil));

    if (!$eval) {
        echo '<div class="he-graph-card' . ($single_view ? ' single' : '') . '">';
        echo '<h3 style="color:' . esc_attr($color) . ';">' . esc_html($icon) . ' ' . esc_html($profil) . '</h3>';
        echo '<div class="he-no-data">📭 Aucune évaluation disponible</div>';
        echo '</div>';
        return;
    }

    $stats = $wpdb->get_row($wpdb->prepare("
        SELECT 
            SUM(CASE WHEN a.reponse = 'Oui' THEN 1 ELSE 0 END) as oui,
            SUM(CASE WHEN a.reponse = 'Non' THEN 1 ELSE 0 END) as non,
            SUM(CASE WHEN a.reponse = 'N/A' THEN 1 ELSE 0 END) as na,
            COUNT(*) as total
        FROM {$wpdb->prefix}hospital_answers a
        WHERE a.evaluation_id = %d
    ", $eval->id));

    $total = intval($stats->total);
    if ($total == 0) {
        echo '<div class="he-graph-card' . ($single_view ? ' single' : '') . '">';
        echo '<h3 style="color:' . esc_attr($color) . ';">' . esc_html($icon) . ' ' . esc_html($profil) . '</h3>';
        echo '<div class="he-no-data">📭 Aucune réponse enregistrée</div>';
        echo '</div>';
        return;
    }

    $oui = intval($stats->oui);
    $non = intval($stats->non);
    $na = intval($stats->na);
    $pct_oui = round(($oui / $total) * 100, 1);
    $pct_non = round(($non / $total) * 100, 1);
    $pct_na = round(($na / $total) * 100, 1);
    $score = !empty($eval->score_final) ? round($eval->score_final, 2) : 0;
    $date_eval = date_i18n('d/m/Y', strtotime($eval->created_at));
    $canvas_id = 'chart_' . sanitize_key(strtolower(str_replace([' ', 'ô', 'é'], ['_', 'o', 'e'], $profil)));

    ?>
    <div class="he-graph-card<?php echo $single_view ? ' single' : ''; ?>">
        <h3 style="color:<?php echo esc_attr($color); ?>;">
            <?php echo esc_html($icon); ?> <?php echo esc_html($profil); ?>
        </h3>
        
        <div style="background:#f0f9ff;padding:10px;border-radius:6px;margin-bottom:15px;font-size:13px;color:#0c4a6e;">
            <strong>Score final :</strong> <span style="font-size:18px;color:#0369a1;"><?php echo esc_html($score); ?>%</span>
            <span style="float:right;color:#64748b;">📅 <?php echo esc_html($date_eval); ?></span>
        </div>

        <canvas id="<?php echo esc_attr($canvas_id); ?>" class="he-graph-canvas"></canvas>

        <div class="he-stats-grid">
            <div class="he-stat-item">
                <div class="label">✅ Oui</div>
                <div class="value" style="color:#16a34a;"><?php echo esc_html($oui); ?></div>
                <small style="font-size:13px;color:#6b7280;"><?php echo esc_html($pct_oui); ?>%</small>
            </div>
            <div class="he-stat-item">
                <div class="label">❌ Non</div>
                <div class="value" style="color:#dc2626;"><?php echo esc_html($non); ?></div>
                <small style="font-size:13px;color:#6b7280;"><?php echo esc_html($pct_non); ?>%</small>
            </div>
            <div class="he-stat-item">
                <div class="label">⚠️ N/A</div>
                <div class="value" style="color:#d97706;"><?php echo esc_html($na); ?></div>
                <small style="font-size:13px;color:#6b7280;"><?php echo esc_html($pct_na); ?>%</small>
            </div>
        </div>

        <script>
        (function() {
            function initChart() {
                const ctx = document.getElementById('<?php echo esc_js($canvas_id); ?>');
                if (!ctx) return;
                if (typeof Chart === 'undefined') {
                    window.addEventListener('chartjs-ready', initChart, { once: true });
                    return;
                }

                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['✅ Oui', '❌ Non', '⚠️ N/A'],
                        datasets: [{
                            data: [<?php echo $oui; ?>, <?php echo $non; ?>, <?php echo $na; ?>],
                            backgroundColor: ['#16a34a', '#dc2626', '#d97706'],
                            borderWidth: 3,
                            borderColor: '#fff',
                            hoverOffset: 10
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { padding: 15, font: { size: 13, weight: 600 }, usePointStyle: true }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const pct = ((context.parsed / <?php echo $total; ?>) * 100).toFixed(1);
                                        return ' ' + context.parsed + ' réponses (' + pct + '%)';
                                    }
                                }
                            },
                            datalabels: {
                                color: '#fff',
                                font: { weight: 'bold', size: 15 },
                                formatter: (value) => {
                                    const pct = ((value / <?php echo $total; ?>) * 100).toFixed(0);
                                    return value > 0 ? pct + '%' : '';
                                }
                            }
                        }
                    },
                    plugins: typeof ChartDataLabels !== 'undefined' ? [ChartDataLabels] : []
                });
            }
            
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initChart);
            } else {
                initChart();
            }
        })();
        </script>
        
        <?php
        // 🎯 ANALYSE PAR CHAPITRE
        he_render_chapter_analysis($eval->id, $profil, $color);
        ?>
    </div>
    <?php
}

/* =======================================================
   FONCTION : ANALYSE PAR CHAPITRE
======================================================= */
function he_render_chapter_analysis($eval_id, $profil, $color) {
    global $wpdb;

    $chapters = $wpdb->get_results($wpdb->prepare("
        SELECT 
            COALESCE(NULLIF(q.chapitre, ''), 'Sans catégorie') as chapitre,
            SUM(CASE WHEN a.reponse = 'Oui' THEN 1 ELSE 0 END) as oui,
            SUM(CASE WHEN a.reponse = 'Non' THEN 1 ELSE 0 END) as non,
            SUM(CASE WHEN a.reponse = 'N/A' THEN 1 ELSE 0 END) as na,
            SUM(CASE WHEN a.reponse = 'Oui' THEN q.poids ELSE 0 END) as poids_oui,
            SUM(CASE WHEN a.reponse IN ('Oui', 'Non') THEN q.poids ELSE 0 END) as poids_total,
            COUNT(*) as total
        FROM {$wpdb->prefix}hospital_answers a
        INNER JOIN {$wpdb->prefix}hospital_questions q ON a.question_id = q.id
        WHERE a.evaluation_id = %d
        GROUP BY COALESCE(NULLIF(q.chapitre, ''), 'Sans catégorie')
        ORDER BY chapitre ASC
    ", $eval_id));

    if (empty($chapters)) return;

    $chapter_data = [];
    foreach ($chapters as $ch) {
        $score = $ch->poids_total > 0 ? round(($ch->poids_oui / $ch->poids_total) * 100, 1) : 0;
        $chapter_data[] = [
            'nom' => $ch->chapitre,
            'score' => $score,
            'oui' => intval($ch->oui),
            'non' => intval($ch->non),
            'na' => intval($ch->na),
            'total' => intval($ch->total)
        ];
    }

    usort($chapter_data, function($a, $b) { return $a['score'] <=> $b['score']; });
    $canvas_id = 'chapter_' . sanitize_key(strtolower(str_replace([' ', 'ô', 'é'], ['_', 'o', 'e'], $profil)));
    ?>

    <div style="margin-top:25px;padding:20px;background:#f9fafb;border-radius:10px;border:2px solid #e5e7eb;">
        <h4 style="margin:0 0 20px 0;color:<?php echo esc_attr($color); ?>;display:flex;align-items:center;gap:8px;">
            🎯 Analyse par Chapitre
            <span style="font-size:13px;color:#6b7280;font-weight:400;">(<?php echo count($chapter_data); ?> chapitres)</span>
        </h4>

        <canvas id="<?php echo esc_attr($canvas_id); ?>" style="max-height:350px;margin-bottom:25px;"></canvas>

        <div style="background:#fff;padding:20px;border-radius:8px;border-left:4px solid #ef4444;">
            <h5 style="margin:0 0 15px 0;color:#dc2626;">🔴 Top 3 à Améliorer</h5>
            
            <?php
            $top_3 = array_slice($chapter_data, 0, 3);
            $icons = ['🔴', '🟠', '🟡'];
            $colors = ['#dc2626', '#f97316', '#eab308'];
            
            foreach ($top_3 as $i => $ch):
                $action = $ch['score'] < 50 ? "Formation urgente + audit approfondi" : 
                         ($ch['score'] < 70 ? "Révision protocoles + sensibilisation" : "Maintenir bonnes pratiques");
            ?>
                <div style="margin-bottom:15px;padding-bottom:15px;<?php echo $i < 2 ? 'border-bottom:1px solid #e5e7eb;' : ''; ?>">
                    <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
                        <span><strong><?php echo $icons[$i]; ?> <?php echo esc_html($ch['nom']); ?></strong></span>
                        <span style="font-size:20px;font-weight:700;color:<?php echo $colors[$i]; ?>;"><?php echo $ch['score']; ?>%</span>
                    </div>
                    <div style="font-size:13px;color:#6b7280;margin-bottom:8px;">
                        ✅ <?php echo $ch['oui']; ?> | ❌ <?php echo $ch['non']; ?> | ⚠️ <?php echo $ch['na']; ?> | 📊 <?php echo $ch['total']; ?> questions
                    </div>
                    <div style="height:8px;background:#e5e7eb;border-radius:4px;margin-bottom:10px;">
                        <div style="height:100%;background:<?php echo $colors[$i]; ?>;width:<?php echo $ch['score']; ?>%;border-radius:4px;"></div>
                    </div>
                    <div style="padding:10px;background:#fef2f2;border-radius:6px;font-size:13px;color:#7f1d1d;">
                        💡 <strong>Action :</strong> <?php echo esc_html($action); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
    (function() {
        function initChapterChart() {
            const ctx = document.getElementById('<?php echo esc_js($canvas_id); ?>');
            if (!ctx) return;
            if (typeof Chart === 'undefined') {
                window.addEventListener('chartjs-ready', initChapterChart, { once: true });
                return;
            }

            const data = <?php echo json_encode($chapter_data); ?>;
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(ch => ch.nom),
                    datasets: [{
                        label: 'Score (%)',
                        data: data.map(ch => ch.score),
                        backgroundColor: data.map(ch => ch.score < 50 ? '#dc2626' : ch.score < 70 ? '#f97316' : ch.score < 85 ? '#eab308' : '#16a34a'),
                        borderRadius: 6
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    scales: {
                        x: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } },
                        y: { ticks: { font: { size: 12, weight: 600 } } }
                    },
                    plugins: { legend: { display: false } }
                }
            });
        }
        
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initChapterChart);
        } else {
            initChapterChart();
        }
    })();
    </script>
    <?php
}

/* =======================================================
   FONCTION : GRAPHIQUE GLOBAL
======================================================= */
function he_render_global_graph($hospital_id) {
    global $wpdb;

    $stats = $wpdb->get_row($wpdb->prepare("
        SELECT 
            SUM(CASE WHEN a.reponse = 'Oui' THEN 1 ELSE 0 END) as oui,
            SUM(CASE WHEN a.reponse = 'Non' THEN 1 ELSE 0 END) as non,
            SUM(CASE WHEN a.reponse = 'N/A' THEN 1 ELSE 0 END) as na
        FROM {$wpdb->prefix}hospital_answers a
        INNER JOIN {$wpdb->prefix}hospital_evaluations e ON e.id = a.evaluation_id
        WHERE e.hospital_id = %d
    ", $hospital_id));

    $total = $stats->oui + $stats->non + $stats->na;
    if ($total == 0) return;

    ?>
    <div class="he-global-graph">
        <h3>🌍 Vue d'ensemble de l'hôpital</h3>
        <canvas id="chart_global" style="max-height:300px;"></canvas>
    </div>

    <script>
    (function() {
        function initGlobal() {
            const ctx = document.getElementById('chart_global');
            if (!ctx || typeof Chart === 'undefined') {
                window.addEventListener('chartjs-ready', initGlobal, { once: true });
                return;
            }

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Global'],
                    datasets: [
                        { label: '✅ Oui', data: [<?php echo $stats->oui; ?>], backgroundColor: '#16a34a' },
                        { label: '❌ Non', data: [<?php echo $stats->non; ?>], backgroundColor: '#dc2626' },
                        { label: '⚠️ N/A', data: [<?php echo $stats->na; ?>], backgroundColor: '#d97706' }
                    ]
                },
                options: {
                    scales: {
                        x: { stacked: true, ticks: { color: '#fff' } },
                        y: { stacked: true, beginAtZero: true, ticks: { color: '#fff' } }
                    },
                    plugins: { legend: { labels: { color: '#fff' } } }
                }
            });
        }
        
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initGlobal);
        } else {
            initGlobal();
        }
    })();
    </script>
    <?php
}
?>