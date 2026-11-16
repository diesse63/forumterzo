(() => {
  const init = () => {
    const text = document.getElementById('gcs-text-filter');
    const status = document.getElementById('gcs-status-filter');
    const label = document.getElementById('gcs-label-filter');
    const count = document.getElementById('gcs-filter-count');
    const cards = [...document.querySelectorAll('.gcs-card[data-gcs-status]')];
    if (!text || !status || !label || !count || !cards.length) return;
    const apply = () => {
      const query = text.value.trim().toLocaleLowerCase();
      const selected = status.value;
      const selectedLabel = label.value.toLocaleLowerCase();
      let visible = 0;
      cards.forEach(card => {
        const matchesText = !query || card.textContent.toLocaleLowerCase().includes(query);
        const matchesStatus = selected === 'all' || card.dataset.gcsStatus === selected;
        const labels = [...card.querySelectorAll('.gcs-labels')].map(item => item.textContent.toLocaleLowerCase());
        const matchesLabel = selectedLabel === 'all' || labels.some(value => value.includes(selectedLabel));
        const show = matchesText && matchesStatus && matchesLabel;
        card.classList.toggle('gcs-filter-hidden', !show);
        if (show) visible++;
      });
      count.textContent = `Visualizzate ${visible} di ${cards.length}`;
    };
    text.addEventListener('input', apply);
    status.addEventListener('change', apply);
    label.addEventListener('change', apply);
    apply();
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
