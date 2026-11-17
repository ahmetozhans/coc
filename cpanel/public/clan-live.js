'use strict';

(() => {
  const form = document.getElementById('clanForm');
  if (!form) return;

  const input = document.getElementById('clanTag');
  const result = document.getElementById('clanResult');
  let active;
  const words = {
    tr: {
      invalid: 'Geçersiz klan etiketi. Kullanılabilen karakterler: 0289PYLQGRJCUV',
      loading: 'Klan bilgileri yükleniyor…',
      not_found: 'Bu etiketle klan bulunamadı.',
      busy: 'Çok fazla sorgu var. Biraz sonra tekrar dene.',
      api_access: 'Canlı klan verilerine erişilemiyor.',
      not_configured: 'Canlı klan sorgusu henüz yapılandırılmadı.',
      unavailable: 'Canlı klan bilgileri geçici olarak alınamadı.',
      open: 'Klanı oyunda aç ↗', level: 'Klan seviyesi', members: 'Üye', points: 'Klan puanı',
    },
    en: {
      invalid: 'Invalid clan tag. Allowed characters: 0289PYLQGRJCUV',
      loading: 'Loading clan details…',
      not_found: 'No clan was found with this tag.',
      busy: 'Too many lookups. Try again shortly.',
      api_access: 'Live clan data cannot be accessed.',
      not_configured: 'Live clan lookup has not been configured yet.',
      unavailable: 'Live clan details are temporarily unavailable.',
      open: 'Open clan in game ↗', level: 'Clan level', members: 'Members', points: 'Clan points',
    },
  };

  form.onsubmit = async (event) => {
    event.preventDefault();
    active?.abort();
    active = undefined;
    const language = typeof I18n !== 'undefined' && I18n.language === 'tr' ? 'tr' : 'en';
    const copy = words[language];
    const tag = input.value.trim().toUpperCase().replace(/^#/, '').replaceAll('O', '0');
    if (!/^[0289PYLQGRJCUV]{3,15}$/.test(tag)) {
      result.textContent = copy.invalid;
      return;
    }

    const link = document.createElement('a');
    link.href = 'https://link.clashofclans.com/en?action=OpenClanProfile&tag=' + encodeURIComponent('#' + tag);
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
    link.textContent = copy.open;
    const status = document.createElement('p');
    status.textContent = copy.loading;
    result.replaceChildren(status, link);

    const request = new AbortController();
    active = request;
    const timeout = setTimeout(() => request.abort(), 9000);
    try {
      const response = await fetch('/api/clan?tag=' + encodeURIComponent(tag), {signal: request.signal});
      const data = await response.json();
      if (active !== request || request.signal.aborted) return;
      if (!response.ok) {
        status.textContent = Object.hasOwn(copy, data.error) ? copy[data.error] : copy.unavailable;
        return;
      }
      const title = document.createElement('strong');
      title.textContent = data.name + ' (' + data.tag + ')';
      const format = new Intl.NumberFormat(language);
      const summary = document.createElement('p');
      summary.textContent = [
        copy.level + ' ' + format.format(data.level),
        copy.members + ' ' + format.format(data.members),
        copy.points + ' ' + format.format(data.points),
      ].join(' · ');
      result.replaceChildren(title, summary, link);
    } catch {
      if (active === request) status.textContent = copy.unavailable;
    } finally {
      clearTimeout(timeout);
    }
  };
})();
