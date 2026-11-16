(() => {
  const init = () => {
    const text = document.getElementById('gcs-text-filter');
    const status = document.getElementById('gcs-status-filter');
    const count = document.getElementById('gcs-filter-count');
    const cards = [...document.querySelectorAll('.gcs-card[data-gcs-status]')];
    if (!text || !status || !count || !cards.length) return;
    const apply = () => {
      const query = text.value.trim().toLocaleLowerCase();
      const selected = status.value;
      let visible = 0;
      cards.forEach(card => {
        const matchesText = !query || card.textContent.toLocaleLowerCase().includes(query);
        const matchesStatus = selected === 'all' || card.dataset.gcsStatus === selected;
        const show = matchesText && matchesStatus;
        card.classList.toggle('gcs-filter-hidden', !show);
        if (show) visible++;
      });
      count.textContent = `Visualizzate ${visible} di ${cards.length}`;
    };
    text.addEventListener('input', apply);
    status.addEventListener('change', apply);
    apply();
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
