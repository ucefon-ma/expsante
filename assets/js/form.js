/**
 * EXP Santé – Formulaire d’évaluation multi-étapes
 * ------------------------------------------------
 * - Navigation fluide entre étapes
 * - Progression visuelle
 * - Soumission sécurisée via REST API (nonce)
 * - Envoi automatique du profil utilisateur
 * - Gestion UX améliorée
 */
document.addEventListener("DOMContentLoaded", function () {
  const form = document.getElementById("evaluationForm");
  if (!form) return;

  const steps = Array.from(document.querySelectorAll(".he-step"));
  const nextBtn = document.getElementById("heNext");
  const prevBtn = document.getElementById("hePrev");
  const submitBtn = document.getElementById("heSubmit");
  const progressBar = document.getElementById("heProgress");
  const chapterHeader = document.getElementById("heChapterStatus");
  const STORAGE_KEY = "he_eval_progress";
  let currentStep = 0;

  // === Construire la légende des chapitres ===
  const chapters = steps.map((s, i) => ({
    name: s.dataset.chapter,
    element: s,
    index: i
  }));

  function renderChapterStatus() {
    chapterHeader.innerHTML = chapters.map(ch => {
      const inputs = ch.element.querySelectorAll("input[type='radio']");
      const questionNames = [...new Set(Array.from(inputs).map(i => i.name))];
      const total = questionNames.length;
      const answered = questionNames.filter(name => {
        return ch.element.querySelector(`input[name='${name}']:checked`);
      }).length;
      const done = answered === total;
      return `<span class="he-chapter-status ${done ? 'done' : 'pending'}" data-step="${ch.index}">
        ${ch.name} (${answered}/${total})
      </span>`;
    }).join("");
  }

  function showStep(index) {
    steps.forEach((s, i) => s.style.display = i === index ? "block" : "none");
    prevBtn.style.display = index === 0 ? "none" : "inline-block";
    nextBtn.style.display = index === steps.length - 1 ? "none" : "inline-block";
    submitBtn.style.display = index === steps.length - 1 ? "inline-block" : "none";
    progressBar.style.width = `${((index + 1) / steps.length) * 100}%`;
    renderChapterStatus();
  }

  form.addEventListener("change", () => {
    renderChapterStatus();
    saveProgress();
  });

  nextBtn.addEventListener("click", () => {
    if (currentStep < steps.length - 1) currentStep++;
    showStep(currentStep);
  });

  prevBtn.addEventListener("click", () => {
    if (currentStep > 0) currentStep--;
    showStep(currentStep);
  });

  // === Sauvegarde locale ===
  function saveProgress() {
    const formData = new FormData(form);
    const data = {};
    for (const [key, value] of formData.entries()) data[key] = value;
    localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
  }

  function restoreProgress() {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (!saved) return;
    const data = JSON.parse(saved);
    Object.entries(data).forEach(([k, v]) => {
      const input = form.querySelector(`[name='${k}'][value='${v}']`);
      if (input) input.checked = true;
    });
    renderChapterStatus();
  }

  restoreProgress();
  showStep(currentStep);

  // === Soumission AJAX ===
  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const formData = new FormData(form);
    const answers = {};
    for (const [key, value] of formData.entries()) {
      if (key.startsWith("q_")) answers[key.replace("q_", "")] = value;
    }
    const payload = {
      hospital_id: formData.get("hospital_id"),
      answers
    };
    try {
      const res = await fetch(he_rest.url, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": he_rest.nonce
        },
        body: JSON.stringify(payload)
      });
      if (res.ok) {
        localStorage.removeItem(STORAGE_KEY);
        form.innerHTML = `<p class="he-success">✅ Évaluation enregistrée !<br>Redirection...</p>`;
        setTimeout(() => window.location.href = "/tableau-de-bord/", 1500);
      } else {
        alert("Erreur lors de la soumission.");
      }
    } catch (err) {
      console.error(err);
      alert("Erreur réseau.");
    }
  });
});
