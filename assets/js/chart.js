document.addEventListener("DOMContentLoaded", function(){
  const ctx = document.getElementById('scoreChart');
  if (!ctx) return;
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: ['Directeur', 'Admin Hôpital', 'Médecin', 'Infirmier'],
      datasets: [{
        label: 'Score (%)',
        data: [85, 75, 90, 80],
        borderWidth: 1
      }]
    },
    options: { scales: { y: { beginAtZero: true } } }
  });
});
