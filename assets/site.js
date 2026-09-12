(() => {
  const key = 'vevak-lang';
  const root = document.documentElement;
  const current = document.body?.dataset.lang || root.lang || 'fr';
  const isEnglish = current === 'en';
  const betaApi = 'https://api.github.com/repos/jasmin-abernathy/vevak/releases/tags/beta';
  const betaApkFallback = 'https://github.com/jasmin-abernathy/vevak/releases/download/beta/VeVak-beta.apk';
  const betaReleasePage = 'https://github.com/jasmin-abernathy/vevak/releases/tag/beta';

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

  // The home page is now the normal beta distribution entry point.
  const heroActions = document.querySelector('.hero-copy .actions');
  if (heroActions && !heroActions.querySelector('[data-beta-download]')) {
    const beta = document.createElement('a');
    beta.className = 'button primary';
    beta.href = betaApkFallback;
    beta.dataset.betaDownload = 'true';
    beta.rel = 'noopener noreferrer';
    beta.referrerPolicy = 'no-referrer';
    beta.textContent = isEnglish ? 'Download the Android beta (APK)' : 'Télécharger la bêta Android (APK)';

    const githubCode = heroActions.querySelector('a[href="https://github.com/jasmin-abernathy/vevak"]');
    if (githubCode) githubCode.className = 'button secondary';
    heroActions.prepend(beta);

    const note = document.createElement('p');
    note.className = 'lead-small';
    note.dataset.betaMeta = 'true';
    note.textContent = isEnglish
      ? 'Public FOSS beta · VeVak 0.3.14 · install outside Google Play for early real-device testing.'
      : 'Bêta FOSS publique · VeVak 0.3.14 · installation hors Google Play pour les premiers tests sur téléphone réel.';
    heroActions.insertAdjacentElement('afterend', note);

    fetch(betaApi, {
      cache: 'no-store',
      headers: { Accept: 'application/vnd.github+json' }
    })
      .then((response) => {
        if (!response.ok) throw new Error(`GitHub API ${response.status}`);
        return response.json();
      })
      .then((release) => {
        const assets = Array.isArray(release.assets) ? release.assets : [];
        const stable = assets.find((asset) => asset.name === 'VeVak-beta.apk');
        const versioned = assets.find((asset) => /^VeVak-\d+\.\d+\.\d+-foss-beta-[0-9a-f]{7}\.apk$/i.test(asset.name));
        const apk = stable || versioned;
        if (apk?.browser_download_url) beta.href = apk.browser_download_url;

        const releaseName = release.name || (isEnglish ? 'current beta' : 'bêta actuelle');
        note.innerHTML = isEnglish
          ? `Public FOSS beta · <strong>${escapeHtml(releaseName)}</strong> · install outside Google Play for early real-device testing. <a href="${betaReleasePage}">Release details and checksum →</a>`
          : `Bêta FOSS publique · <strong>${escapeHtml(releaseName)}</strong> · installation hors Google Play pour les premiers tests sur téléphone réel. <a href="${betaReleasePage}">Détails de la release et empreinte →</a>`;
      })
      .catch(() => {
        const details = document.createElement('a');
        details.href = betaReleasePage;
        details.textContent = isEnglish ? 'Release details →' : 'Détails de la release →';
        note.append(' ', details);
      });
  }

  // Keep the Play closed-test signup available, but secondary to the direct APK beta.
  if (current === 'fr') {
    const actions = document.querySelector('.participate-actions');

    if (actions && !actions.querySelector('[data-play-panel-signup]')) {
      const note = document.createElement('p');
      note.className = 'lead-small';
      note.dataset.playPanelSignup = 'true';
      note.textContent = 'Pour tester aussi la distribution Google Play, vous pouvez rejoindre le premier panel fermé avec l’adresse du compte Google utilisée sur le Play Store.';

      const signup = document.createElement('a');
      signup.className = 'button secondary';
      signup.href = 'mailto:contact@lepotager.org?subject=VeVak%20%E2%80%94%20inscription%20au%20panel%20Google%20Play&body=Bonjour%2C%0A%0AJe%20souhaite%20rejoindre%20le%20premier%20panel%20de%20test%20VeVak%20sur%20Google%20Play.%0A%0AAdresse%20du%20compte%20Google%20utilis%C3%A9%20sur%20Google%20Play%20%3A%20%0A%0AMerci%20!';
      signup.dataset.playPanelSignup = 'true';
      signup.textContent = 'S’inscrire au panel Google Play';

      actions.prepend(note, signup);
    }
  }

  function escapeHtml(value) {
    return String(value)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
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


(() => {
  const toggle = document.querySelector('[data-site-menu-toggle]');
  const nav = document.querySelector('[data-site-nav]');
  if (toggle && nav) {
    const close = () => {
      toggle.setAttribute('aria-expanded', 'false');
      nav.dataset.open = 'false';
    };
    toggle.addEventListener('click', () => {
      const open = toggle.getAttribute('aria-expanded') !== 'true';
      toggle.setAttribute('aria-expanded', String(open));
      nav.dataset.open = String(open);
    });
    nav.addEventListener('click', (event) => {
      if (event.target.closest('a')) close();
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') close();
    });
  }

  const value = new URLSearchParams(location.search).get('ref');
  const ref = value === 'potager' || value === 'prestadmin' ? value : 'direct';
  document.documentElement.dataset.siteRef = ref;
  document.querySelectorAll('[data-site-context]').forEach((label) => {
    const english = document.documentElement.lang === 'en';
    label.textContent = ref === 'prestadmin'
      ? (english ? 'with Prestadmin × Le Potager' : 'avec Prestadmin × Le Potager')
      : label.dataset.contextDefault;
  });
  if (ref !== 'direct') {
    document.querySelectorAll('a[href]').forEach((link) => {
      const raw = link.getAttribute('href');
      if (!raw || raw.startsWith('#') || /^(mailto:|tel:|javascript:)/i.test(raw)) return;
      const url = new URL(raw, location.href);
      if (url.origin !== location.origin || url.searchParams.has('ref')) return;
      url.searchParams.set('ref', ref);
      link.href = url.pathname + url.search + url.hash;
    });
  }
})();
