<?php
if (!defined('ABSPATH')) exit;
global $wpdb;

echo '<div class="wrap exp-sante-page">';
echo '<h1>🧪 Diagnostic du système — EXP Santé</h1>';

/* ==========================================================
   ⚙️ Actions sur la page : purge log & cache
========================================================== */
if (isset($_POST['he_action'])) {
    check_admin_referer('he_diag_action', 'he_diag_nonce');

    if ($_POST['he_action'] === 'purge_log') {
        $log_file = WP_CONTENT_DIR . '/debug.log';
        if (file_exists($log_file)) {
            file_put_contents($log_file, '');
            echo '<div class="updated notice"><p>🧹 <strong>debug.log vidé avec succès.</strong></p></div>';
        } else {
            echo '<div class="error notice"><p>⚠️ Fichier debug.log introuvable.</p></div>';
        }
    }

    if ($_POST['he_action'] === 'purge_cache') {
        global $wpdb;
        $deleted = 0;
        $results = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_he_score_%'");
        foreach ($results as $opt) {
            $transient = str_replace('_transient_', '', $opt);
            delete_transient($transient);
            $deleted++;
        }
        echo '<div class="updated notice"><p>🧹 <strong>Cache supprimé (' . intval($deleted) . ' entrées).</strong></p></div>';
    }
}

/* ==========================================================
   0️⃣ En-tête d’informations générales
========================================================== */
$db_version = get_option('he_db_version', '— (non détectée)');
echo '<div class="exp-card">';
echo '<h2>ℹ️ Informations générales</h2>';
echo '<p><strong>Version du plugin :</strong> 1.0.3</p>';
echo '<p><strong>Version du schéma DB :</strong> <code>' . esc_html($db_version) . '</code></p>';
echo '<form method="post" style="margin-top:15px;">';
wp_nonce_field('he_diag_action', 'he_diag_nonce');
echo '<button type="submit" name="he_action" value="purge_cache" class="button button-primary">🧹 Purger le cache des scores</button> ';
echo '<button type="submit" name="he_action" value="purge_log" class="button">🪵 Purger le debug.log</button>';
echo '</form>';
echo '</div>';

/* ==========================================================
   1️⃣ Vérification des tables essentielles
========================================================== */
$tables = [
    "{$wpdb->prefix}hospital_evaluations",
    "{$wpdb->prefix}hospital_questions",
    "{$wpdb->prefix}hospital_answers"
];

echo '<div class="exp-card"><h2>📋 Vérification des tables</h2>';
$ok = true;

foreach ($tables as $table) {
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
    if ($exists) {
        echo "<p>✅ Table détectée : <code>$table</code></p>";
    } else {
        echo "<p>❌ Table manquante : <code>$table</code></p>";
        $ok = false;
    }
}
echo '</div>';

/* ==========================================================
   2️⃣ Vérification des colonnes & index
========================================================== */
echo '<div class="exp-card"><h2>🧱 Vérification des colonnes & index</h2>';

$columns = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}hospital_evaluations");
$cols = wp_list_pluck($columns, 'Field');

$expected_cols = ['score_final', 'total_oui', 'total_non', 'total_poids', 'last_recalculated'];

foreach ($expected_cols as $col) {
    if (in_array($col, $cols)) {
        echo "<p>✅ Colonne <code>$col</code> trouvée.</p>";
    } else {
        echo "<p>⚠️ Colonne manquante : <code>$col</code></p>";
        $ok = false;
    }
}

// 🔹 Vérifie les index sur hospital_answers
$indexes = $wpdb->get_results("SHOW INDEX FROM {$wpdb->prefix}hospital_answers");
$has_index = false;
foreach ($indexes as $idx) {
    if ($idx->Key_name === 'unique_answer') $has_index = true;
}
echo $has_index
    ? '<p>✅ Index unique <code>unique_answer(evaluation_id, question_id)</code> détecté.</p>'
    : '<p>⚠️ Index unique <code>unique_answer</code> manquant.</p>';

echo '</div>';

/* ==========================================================
   3️⃣ Vérification des doublons
========================================================== */
echo '<div class="exp-card"><h2>🔍 Contrôle des doublons</h2>';
$dupes = $wpdb->get_results("
    SELECT hospital_id, profil, COUNT(*) as nb
    FROM {$wpdb->prefix}hospital_evaluations
    GROUP BY hospital_id, profil
    HAVING nb > 1
");
if ($dupes) {
    echo '<table class="widefat"><thead><tr><th>Hôpital</th><th>Profil</th><th>Nombre</th></tr></thead><tbody>';
    foreach ($dupes as $d) {
        echo "<tr><td>{$d->hospital_id}</td><td>{$d->profil}</td><td>{$d->nb}</td></tr>";
    }
    echo '</tbody></table>';
} else {
    echo '<p>✅ Aucun doublon détecté dans <code>hospital_evaluations</code>.</p>';
}
echo '</div>';

/* ==========================================================
   4️⃣ Vérification du cache des scores
========================================================== */
echo '<div class="exp-card"><h2>⚡ Vérification du cache des scores</h2>';
$random_eval = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}hospital_evaluations ORDER BY RAND() LIMIT 1");
if ($random_eval) {
    $cache = get_transient('he_score_' . $random_eval);
    if ($cache !== false) {
        echo "<p>✅ Cache trouvé pour évaluation #$random_eval → {$cache}%</p>";
    } else {
        echo "<p>⚠️ Aucun cache trouvé pour évaluation #$random_eval (sera régénéré automatiquement).</p>";
    }
} else {
    echo "<p>⚠️ Aucune évaluation trouvée dans la base.</p>";
}
echo '</div>';

/* ==========================================================
   5️⃣ Vérification du debug.log
========================================================== */
echo '<div class="exp-card"><h2>🪵 Erreurs récentes du debug.log</h2>';

$log_file = WP_CONTENT_DIR . '/debug.log';
if (file_exists($log_file)) {
    $lines = array_slice(file($log_file), -20); // 20 dernières lignes
    if (!empty($lines)) {
        echo '<pre style="background:#111;color:#0f0;padding:10px;border-radius:6px;max-height:250px;overflow:auto;">';
        echo esc_html(implode('', $lines));
        echo '</pre>';
    } else {
        echo '<p>✅ Aucune erreur récente détectée.</p>';
    }
} else {
    echo '<p>⚠️ Fichier debug.log introuvable.</p>';
}
echo '</div>';

/* ==========================================================
   6️⃣ Résumé global
========================================================== */
echo '<div class="exp-card"><h2>📊 Résumé global</h2>';
if ($ok) {
    echo '<p class="exp-alert success">✅ Tout semble fonctionnel et prêt pour la production.</p>';
} else {
    echo '<p class="exp-alert warning">⚠️ Des anomalies ont été détectées. Vérifie les colonnes et index.</p>';
}
echo '</div>';

echo '</div>';
?>
