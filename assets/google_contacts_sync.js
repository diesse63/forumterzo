(() => {
  const init = () => {
    const text = document.getElementById('gcs-text-filter');
    const status = document.getElementById('gcs-status-filter');
    const label = document.getElementById('gcs-label-filter');
    const count = document.getElementById('gcs-filter-count');
    const cards = [...document.querySelectorAll('.gcs-card[data-gcs-status]')];
    if (!text || !status || !label || !count || !cards.length) return;
    const apply = () => {
      const normalize = value => String(value || '').toLocaleLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
      const query = normalize(text.value.trim());
      const selected = status.value;
      const selectedLabel = normalize(label.value);
      let visible = 0;
      cards.forEach(card => {
        const matchesText = !query || normalize(card.textContent).includes(query);
        const matchesStatus = selected === 'all' || card.dataset.gcsStatus === selected;
        const labels = [...card.querySelectorAll('.gcs-labels')].map(item => normalize(item.textContent));
        const matchesLabel = selectedLabel === 'all' || labels.some(value => value.includes(selectedLabel));
        const show = matchesText && matchesStatus && matchesLabel;
        card.classList.toggle('gcs-filter-hidden', !show);
        if (show) visible++;
      });
      count.textContent = `Visualizzate ${visible} di ${cards.length}`;
    };
    text.addEventListener('input', apply);
    text.addEventListener('keyup', apply);
    text.addEventListener('change', apply);
    text.addEventListener('search', apply);
    status.addEventListener('change', apply);
    label.addEventListener('change', apply);
    apply();
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
