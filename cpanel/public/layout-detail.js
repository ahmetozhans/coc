'use strict';

(() => {
  const detail = document.getElementById('layoutDetail');
  if (!detail) return;
  const {id, gameUrl} = detail.dataset;
  const share = document.getElementById('shareLink');
  const message = document.getElementById('detailMessage');
  const pageUrl = share.value;
  const record = (...args) => window.MahanStats?.record(id, ...args);
  detail.querySelector('[data-game-open]')?.addEventListener('click', () => record('copy'));

  for (const button of detail.querySelectorAll('[data-copy]')) {
    button.hidden = false;
    button.addEventListener('click', async () => {
      const isGame = button.dataset.copy === 'game';
      const value = isGame ? gameUrl : pageUrl;
      share.value = pageUrl;
      try {
        await navigator.clipboard.writeText(value);
        if (isGame) record('copy');
        message.textContent = 'Bağlantı kopyalandı.';
      } catch {
        share.value = value;
        share.focus();
        share.select();
        message.textContent = 'Bağlantıyı seçtim. Tarayıcının kopyalama komutunu kullanabilirsin.';
      }
    });
  }

  const favorite = detail.querySelector('[data-favorite]');
  if (!favorite) return;
  const normalizedGameUrl = new URL(gameUrl).href;
  function favorites() {
    const stored = JSON.parse(localStorage.getItem('vf-favorites') || '[]');
    return Array.isArray(stored) ? stored : [];
  }
  function paint(active) {
    favorite.setAttribute('aria-pressed', String(active));
    favorite.textContent = active ? '♥ Favoriden çıkar' : '♡ Favoriye ekle';
  }
  try {
    paint(favorites().includes(normalizedGameUrl));
    favorite.hidden = false;
  } catch {
    return;
  }
  favorite.addEventListener('click', () => {
    try {
      const stored = favorites();
      const active = !stored.includes(normalizedGameUrl);
      const next = active ? [...stored, normalizedGameUrl] : stored.filter(item => item !== normalizedGameUrl);
      localStorage.setItem('vf-favorites', JSON.stringify(next));
      paint(active);
      record('favorite', active);
    } catch {
      message.textContent = 'Favori kaydedilemedi.';
    }
  });
})();
