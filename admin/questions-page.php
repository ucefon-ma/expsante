<?php
if (!defined('ABSPATH')) exit;

/**
 * Page d'administration : Gestion complète des questions
 */
class HE_Admin_Questions_Page {

    public static function render() {
        if (!current_user_can('manage_options')) wp_die('Accès non autorisé.');
        global $wpdb;

        echo '<div class="wrap exp-sante-page">';
         echo '<h1>🧾 Gestion des questions</h1>';
        echo '<div class="exp-card">';
       

        /* ==========================================================
           1️⃣ Import CSV
        ========================================================== */
        if (isset($_POST['he_import_csv']) && check_admin_referer('he_import_csv_action', 'he_import_csv_nonce')) {
            if (!empty($_FILES['questions_csv']['tmp_name'])) {
                $file = fopen($_FILES['questions_csv']['tmp_name'], 'r');
                $count = 0;
                fgetcsv($file, 0, ','); // Ignorer l'entête
                while (($data = fgetcsv($file, 0, ',')) !== FALSE) {
                    if (count($data) < 5) continue;
                    list($profil, $chapitre, $question_text, $poids, $active) = $data;
                    $wpdb->insert("{$wpdb->prefix}hospital_questions", [
                        'profil'        => sanitize_text_field(trim($profil)),
                        'chapitre'      => sanitize_text_field(trim($chapitre)),
                        'question_text' => sanitize_textarea_field(trim($question_text)),
                        'poids'         => intval($poids),
                        'active'        => intval($active),
                        'position'      => 0
                    ]);
                    $count++;
                }
                fclose($file);
                echo '<div class="updated"><p>✅ ' . esc_html($count) . ' questions importées avec succès.</p></div>';
            } else {
                echo '<div class="error"><p>⚠️ Aucun fichier sélectionné.</p></div>';
            }
        }

        echo '<h2>📥 Importer des questions depuis un fichier CSV</h2>';
        echo '<form method="post" enctype="multipart/form-data" style="margin-bottom:25px;padding:15px;background:#f9f9f9;border-radius:6px;">';
        wp_nonce_field('he_import_csv_action', 'he_import_csv_nonce');
        echo '<input type="file" name="questions_csv" accept=".csv" required style="margin-right:10px;">';
        echo '<input type="submit" name="he_import_csv" class="button-primary" value="📤 Importer le fichier">';
        echo '<p style="margin-top:8px;color:#666;font-size:13px;">Format attendu : <code>profil, chapitre, question_text, poids, active</code></p>';
        echo '</form>';

        /* ==========================================================
           2️⃣ Ajout manuel d'une question
        ========================================================== */
        if (isset($_POST['he_add_question']) && check_admin_referer('he_add_question_action', 'he_nonce')) {
            $wpdb->insert("{$wpdb->prefix}hospital_questions", [
                'profil'        => sanitize_text_field($_POST['profil']),
                'chapitre'      => sanitize_text_field($_POST['chapitre']),
                'question_text' => sanitize_textarea_field($_POST['question_text']),
                'poids'         => intval($_POST['poids']),
                'active'        => 1,
                'position'      => 0
            ]);
            echo '<div class="updated"><p>✅ Nouvelle question ajoutée.</p></div>';
        }

        $profils = ['Directeur', 'Administrateur Hôpital', 'Médecin', 'Infirmier'];
        echo '<hr><h2>➕ Ajouter une question manuellement</h2>';
        echo '<form method="POST">';
        wp_nonce_field('he_add_question_action', 'he_nonce');
        echo '<table class="form-table">';
        echo '<tr><th>Profil</th><td><select name="profil" required><option value="">Choisir</option>';
        foreach ($profils as $p) echo "<option value='{$p}'>{$p}</option>";
        echo '</select></td></tr>';
        echo '<tr><th>Chapitre</th><td><input type="text" name="chapitre" required></td></tr>';
        echo '<tr><th>Texte</th><td><textarea name="question_text" rows="2" cols="60" required></textarea></td></tr>';
        echo '<tr><th>Impact</th><td><select name="poids">
                <option value="1">⚪ Léger</option>
                <option value="3">🟢 Moyen</option>
                <option value="5">🔴 Fort</option>
              </select></td></tr>';
        echo '</table>';
        echo '<p><input type="submit" class="button-primary" name="he_add_question" value="Ajouter la question"></p>';
        echo '</form>';

        /* ==========================================================
           3️⃣ Sauvegarde globale (édition / ordre / statut)
        ========================================================== */
        if (isset($_POST['he_save_all']) && check_admin_referer('he_save_all_action', 'he_save_nonce')) {
            if (!empty($_POST['ids'])) {
                foreach ($_POST['ids'] as $i => $id) {
                    $wpdb->update("{$wpdb->prefix}hospital_questions", [
                        'chapitre'      => sanitize_text_field($_POST['chapitre'][$i]),
                        'question_text' => sanitize_textarea_field($_POST['question_text'][$i]),
                        'poids'         => intval($_POST['poids'][$i]),
                        'active'        => isset($_POST['active'][$i]) ? 1 : 0,
                        'position'      => intval($_POST['position'][$i])
                    ], ['id' => intval($id)]);
                }
                echo '<div class="updated"><p>💾 Toutes les modifications ont été enregistrées avec succès.</p></div>';
            }
        }

      /* ==========================================================
   4️⃣ Résumé global
========================================================== */
$total_all      = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hospital_questions");
$total_active   = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hospital_questions WHERE active = 1");
$total_inactive = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hospital_questions WHERE active = 0");

echo '<hr><h2>📊 Résumé global des questions</h2>';

// 🟢 Tableau résumé global
echo '<table class="widefat" style="max-width:600px;margin-bottom:30px;">';
echo '<thead><tr><th>Statut</th><th>Nombre</th></tr></thead><tbody>';
echo '<tr><td>🟢 Questions actives</td><td><strong>' . $total_active . '</strong></td></tr>';
echo '<tr><td>🔴 Questions inactives</td><td><strong>' . $total_inactive . '</strong></td></tr>';
echo '<tr style="background:#f9fafb;"><td><strong>⚪ Total général</strong></td><td><strong>' . $total_all . '</strong></td></tr>';
echo '</tbody></table>';

// 🔹 Tableau résumé par profil
$profils = ['Directeur', 'Administrateur Hôpital', 'Médecin', 'Infirmier'];
echo '<h3>📘 Répartition par profil</h3>';
echo '<table class="widefat" style="max-width:800px;">';
echo '<thead><tr><th>Profil</th><th>🟢 Actives</th><th>🔴 Inactives</th><th>⚪ Total</th></tr></thead><tbody>';

foreach ($profils as $profil) {
    $active   = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}hospital_questions WHERE profil = %s AND active = 1", $profil));
    $inactive = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}hospital_questions WHERE profil = %s AND active = 0", $profil));
    $total    = $active + $inactive;

    echo '<tr>';
    echo '<td><strong>' . esc_html($profil) . '</strong></td>';
    echo '<td style="color:#16a34a;"><strong>' . $active . '</strong></td>';
    echo '<td style="color:#dc2626;"><strong>' . $inactive . '</strong></td>';
    echo '<td style="background:#f9fafb;"><strong>' . $total . '</strong></td>';
    echo '</tr>';
}

echo '</tbody></table>';


        /* ==========================================================
           5️⃣ Liste des questions (édition globale + drag/drop)
        ========================================================== */
        $selected = isset($_GET['profil']) ? sanitize_text_field($_GET['profil']) : '';
        $questions = $selected
            ? $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}hospital_questions WHERE profil=%s ORDER BY position ASC, id ASC", $selected))
            : $wpdb->get_results("SELECT * FROM {$wpdb->prefix}hospital_questions ORDER BY profil, position ASC, id ASC");

        echo '<h2>📋 Liste des questions</h2>';
        echo '<form method="GET" style="margin-bottom:20px;">';
        echo '<input type="hidden" name="page" value="hospital_questions">';
        echo '<label>Filtrer par profil :</label> ';
        echo '<select name="profil" onchange="this.form.submit()"><option value="">Tous</option>';
        foreach ($profils as $p) {
            $sel = selected($selected, $p, false);
            echo "<option value='{$p}' {$sel}>{$p}</option>";
        }
        echo '</select>';
        if ($selected) echo ' <a href="?page=hospital_questions" class="button">Réinitialiser</a>';
        echo '</form>';

        echo '<form method="POST">';
        wp_nonce_field('he_save_all_action', 'he_save_nonce');
        echo '<table class="widefat striped" id="sortable-table">';
        echo '<thead>
                <tr>
                    <th style="width:30px;">↕️</th>
                    <th>ID</th>
                    <th>Profil</th>
                    <th>Chapitre</th>
                    <th>Question</th>
                    <th>Impact</th>
                    <th>Active</th>
                </tr>
              </thead><tbody>';

        if ($questions) {
            foreach ($questions as $index => $q) {
                echo '<tr data-id="' . intval($q->id) . '">';
                echo '<td class="handle">☰</td>';
                echo '<td>' . intval($q->id) . '<input type="hidden" name="ids[]" value="' . intval($q->id) . '"><input type="hidden" name="position[]" value="' . intval($q->position) . '"></td>';
                echo '<td>' . esc_html($q->profil) . '</td>';
                echo '<td><input type="text" name="chapitre[]" value="' . esc_attr(stripslashes($q->chapitre)) . '" style="width:120px;"></td>';
                echo '<td><textarea name="question_text[]" rows="2" cols="40">' . esc_textarea(stripslashes($q->question_text)) . '</textarea></td>';
                echo '<td><select name="poids[]">
                        <option value="1"' . selected($q->poids, 1, false) . '>⚪ Léger</option>
                        <option value="3"' . selected($q->poids, 3, false) . '>🟢 Moyen</option>
                        <option value="5"' . selected($q->poids, 5, false) . '>🔴 Fort</option>
                      </select></td>';
                echo '<td style="text-align:center;"><input type="checkbox" name="active[' . $index . ']" value="1" ' . checked($q->active, 1, false) . '></td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="7">Aucune question trouvée.</td></tr>';
        }

        echo '</tbody></table>';
        echo '<p style="margin-top:15px;"><button type="submit" name="he_save_all" class="button-primary">💾 Enregistrer toutes les modifications</button></p>';
        echo '</form>';
        echo '</div></div>'; // .wrap
    }
}

HE_Admin_Questions_Page::render();
?>



<script>
document.addEventListener('DOMContentLoaded', function() {
    const table = document.querySelector('#sortable-table tbody');
    if (!table) return;
    let dragging;

    table.querySelectorAll('tr').forEach(row => {
        row.draggable = true;
        row.addEventListener('dragstart', () => dragging = row);
        row.addEventListener('dragend', () => dragging = null);
    });

    table.addEventListener('dragover', e => e.preventDefault());
    table.addEventListener('drop', e => {
        e.preventDefault();
        const target = e.target.closest('tr');
        if (dragging && target && dragging !== target) {
            table.insertBefore(dragging, target);
            updatePositions();
        }
    });

    function updatePositions() {
        const rows = table.querySelectorAll('tr');
        rows.forEach((row, i) => {
            const pos = row.querySelector('input[name^="position"]');
            if (pos) pos.value = i + 1;
        });
    }
});
</script>
