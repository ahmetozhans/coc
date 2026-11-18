(() => {
  const sidebar = document.querySelector('.sidebar nav');
  const workspace = document.getElementById('workspace');
  if (!sidebar || !workspace) return;
  sidebar.querySelector('a[href="/admin/collector"]')?.remove();
  const button = document.createElement('button');
  button.type = 'button';
  button.dataset.section = 'collector';
  button.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 20V7h4V3h3v4h2V3h3v4h4v13H4Z"/><path d="M9 20v-5h6v5"/></svg><span>Otomatik Köy Toplama</span>';
  sidebar.append(button);
  const panel = document.createElement('section');
  panel.dataset.panel = 'collector';
  panel.id = 'studioCollectorPanel';
  panel.hidden = true;
  workspace.append(panel);
  const style = document.createElement('link');
  style.rel = 'stylesheet';
  style.href = '/studio-collector.css?v=1';
  document.head.append(style);
  let currentUrl = '/mahanpro-setup.php';
  let loaded = false;
  let loading = false;

  async function openCollector(url = currentUrl, options = {}) {
    if (loading) return;
    loading = true;
    panel.setAttribute('aria-busy', 'true');
    const status = document.createElement('p');
    status.className = 'muted';
    status.setAttribute('role', 'status');
    status.textContent = options.method === 'POST' ? 'İşlem sürüyor…' : 'Yükleniyor…';
    panel.prepend(status);
    try {
      const response = await fetch(url, {credentials: 'same-origin', cache: 'no-store',
        signal: AbortSignal.timeout(300000), ...options});
      const html = new DOMParser().parseFromString(await response.text(), 'text/html');
      const content = html.querySelector('.setup-shell > .panel') || html.querySelector('body.collector-page main');
      if (!content) throw new Error('Yönetici oturumunu ve yüklediğin kurulum paketini kontrol et.');
      if (html.querySelector('[data-collector-installed]')) {
        currentUrl = '/admin/collector';
        loading = false;
        await openCollector(currentUrl);
        return;
      }
      const base = new URL(response.url, location.origin);
      for (const element of content.querySelectorAll('[href], [src], form')) {
        for (const attribute of ['href', 'src']) {
          const value = element.getAttribute(attribute);
          if (value) element.setAttribute(attribute, new URL(value, base).href);
        }
        if (element.tagName === 'FORM') element.setAttribute('action', new URL(element.getAttribute('action') || base.href, base).href);
      }
      content.querySelectorAll('script, iframe, object, base').forEach(element => element.remove());
      content.querySelectorAll('.panel').forEach(element => element.classList.replace('panel', 'card'));
      panel.replaceChildren(...content.childNodes);
      currentUrl = base.href;
      loaded = true;
      if (options.method === 'POST') document.dispatchEvent(new Event('collectorcontentchanged'));
    } catch (error) {
      const message = document.createElement('p');
      message.setAttribute('role', 'alert');
      message.textContent = error.message;
      const retry = document.createElement('button');
      retry.className = 'button';
      retry.type = 'button';
      retry.textContent = 'Tekrar dene';
      retry.onclick = () => openCollector(currentUrl);
      panel.replaceChildren(message, retry);
    } finally {
      loading = false;
      panel.removeAttribute('aria-busy');
      status.remove();
    }
  }
  panel.addEventListener('click', event => {
    const link = event.target.closest('a[href]');
    if (!link || link.target === '_blank' || event.ctrlKey || event.metaKey || event.shiftKey) return;
    const url = new URL(link.href, location.origin);
    if (url.origin === location.origin && ['/admin/collector', '/mahanpro-setup.php'].includes(url.pathname)) {
      event.preventDefault();
      openCollector(url.href);
    }
  });
  panel.addEventListener('submit', event => {
    event.preventDefault();
    if (loading) return;
    const form = event.target;
    const body = new FormData(form);
    if (event.submitter?.name) body.set(event.submitter.name, event.submitter.value);
    openCollector(form.getAttribute('action') || currentUrl, {method: 'POST', body});
  });
  document.addEventListener('studiosectionchange', event => {
    if (event.detail === 'collector' && !loaded) openCollector();
  });
  document.getElementById('reloadState')?.addEventListener('click', () => {
    if (!panel.hidden) openCollector(currentUrl);
  });
  document.addEventListener('collectorcontentchanged', () => loadState().catch(() => {}));
})();
