(() => {
  const key = 'vevak-lang';
  const root = document.documentElement;
  const current = document.body?.dataset.lang || root.lang || 'fr';

  document.querySelectorAll('[data-lang-choice]').forEach((link) => {
    link.addEventListener('click', () => {
      try { localStorage.setItem(key, link.dataset.langChoice); } catch (_) {}
    });
  });

  // Keep French as the canonical root, but help English-speaking first-time visitors.
  if (current === 'fr' && location.pathname.endsWith('/') && !location.pathname.includes('/en/')) {
    let saved = null;
    try { saved = localStorage.getItem(key); } catch (_) {}
    const browser = (navigator.language || '').toLowerCase();
    if (!saved && browser.startsWith('en')) {
      location.replace('./en/');
      return;
    }
  }

  // The tester area is protected server-side. This link only makes the entry point
  // discoverable from the French participation section; it is not the security layer.
  if (current === 'fr') {
    const actions = document.querySelector('.participate-actions');
    if (actions && !actions.querySelector('[data-tester-access]')) {
      const link = document.createElement('a');
      link.className = 'text-link';
      link.href = './test/';
      link.dataset.testerAccess = 'true';
      link.textContent = 'Accès testeurs privés →';
      actions.append(link);
    }
  }
})();


(() => {
  const toggle = document.querySelector('[data-vevak-accessibility-toggle]');
  if (!toggle) return;
  const key = 'vevak-accessible-mode';
  const isEnglish = document.documentElement.lang === 'en';
  const apply = enabled => {
    document.documentElement.dataset.vevakAccessible = String(enabled);
    toggle.setAttribute('aria-pressed', String(enabled));
    toggle.textContent = enabled
      ? (isEnglish ? 'Standard version' : 'Version standard')
      : (isEnglish ? 'Accessible version' : 'Version accessible');
  };
  let saved = false;
  try { saved = localStorage.getItem(key) === 'true'; } catch (_) {}
  apply(saved);
  toggle.addEventListener('click', () => {
    const enabled = document.documentElement.dataset.vevakAccessible !== 'true';
    apply(enabled);
    try { localStorage.setItem(key, String(enabled)); } catch (_) {}
  });
})();
