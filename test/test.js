(() => {
  const WIZARD_KEY = 'vevak-tester-install-v2';
  const TEST_KEY = 'vevak-tester-checklist-v2';
  const BETA_RELEASE_API = 'https://api.github.com/repos/jasmin-abernathy/vevak/releases/tags/beta';
  const BETA_RELEASE_PAGE = 'https://github.com/jasmin-abernathy/vevak/releases/tag/beta';

  const downloadLink = document.querySelector('[data-download]');
  const betaStatus = document.querySelector('[data-beta-status]');
  const downloadMeta = document.querySelector('[data-download-meta]');
  const digestMeta = document.querySelector('[data-download-digest]');
  const currentApkCopy = document.querySelector('[data-current-apk-copy]');
  const openApkCopy = document.querySelector('[data-open-apk-copy]');

  if (downloadLink) {
    fetch(BETA_RELEASE_API, {
      cache: 'no-store',
      headers: { Accept: 'application/vnd.github+json' }
    })
      .then((response) => {
        if (!response.ok) throw new Error(`GitHub API ${response.status}`);
        return response.json();
      })
      .then((release) => {
        const assets = Array.isArray(release.assets) ? release.assets : [];
        const apk = assets.find((asset) => /^VeVak-\d+\.\d+\.\d+-foss-beta-[0-9a-f]{7}\.apk$/i.test(asset.name))
          || assets.find((asset) => asset.name?.toLowerCase().endsWith('.apk'));
        if (!apk?.browser_download_url) throw new Error('APK bêta absente de la release');

        downloadLink.href = apk.browser_download_url;
        downloadLink.textContent = `📱 Télécharger ${apk.name}`;
        downloadLink.removeAttribute('download');
        downloadLink.setAttribute('rel', 'noopener noreferrer');
        downloadLink.setAttribute('referrerpolicy', 'no-referrer');

        const releaseName = release.name || 'VeVak bêta';
        if (betaStatus) betaStatus.innerHTML = `<strong>Dernière bêta :</strong> ${escapeHtml(releaseName)}`;
        if (downloadMeta) {
          downloadMeta.innerHTML = `<strong>Release :</strong> ${escapeHtml(releaseName)}<br><strong>Fichier exact :</strong> <code>${escapeHtml(apk.name)}</code>`;
        }

        const digest = typeof apk.digest === 'string' && apk.digest.startsWith('sha256:')
          ? apk.digest.slice('sha256:'.length)
          : '';
        if (digestMeta) {
          digestMeta.innerHTML = digest
            ? `<strong>SHA-256 :</strong> <code>${escapeHtml(digest)}</code>`
            : '<strong>SHA-256 :</strong> disponible dans la release GitHub.';
        }

        if (currentApkCopy) {
          currentApkCopy.innerHTML = `Utilise le bouton de téléchargement plus haut. Pour cette bêta, le fichier attendu est <code>${escapeHtml(apk.name)}</code>.`;
        }
        if (openApkCopy) {
          openApkCopy.innerHTML = `Ouvre la notification de téléchargement ou le dossier <strong>Téléchargements</strong>, puis touche <code>${escapeHtml(apk.name)}</code>.`;
        }
      })
      .catch(() => {
        downloadLink.href = BETA_RELEASE_PAGE;
        downloadLink.textContent = '📱 Ouvrir la dernière bêta GitHub';
        if (betaStatus) betaStatus.innerHTML = '<strong>Dernière bêta :</strong> vérification automatique indisponible — utilise la release <code>beta</code>.';
        if (downloadMeta) downloadMeta.innerHTML = '<strong>Source :</strong> release GitHub <code>beta</code>. Télécharge l’unique APK FOSS versionnée.';
        if (digestMeta) digestMeta.textContent = '';
      });
  }

  const wizard = document.querySelector('[data-wizard]');
  if (wizard) {
    document.documentElement.classList.add('js');
    const steps = [...wizard.querySelectorAll('[data-step]')];
    const prev = wizard.querySelector('[data-prev]');
    const next = wizard.querySelector('[data-next]');
    const reset = wizard.querySelector('[data-reset-wizard]');
    const progressBar = wizard.querySelector('[data-progress-bar]');
    const progress = wizard.querySelector('.wizard-progress');
    const label = document.getElementById('wizardLabel');
    const shortTitle = document.getElementById('wizardShortTitle');
    const dotsRoot = wizard.querySelector('[data-dots]');
    const complete = wizard.querySelector('[data-complete]');
    const controls = wizard.querySelector('.wizard-controls');

    let saved = { current: 0, done: false };
    try { saved = { ...saved, ...JSON.parse(localStorage.getItem(WIZARD_KEY) || '{}') }; } catch (_) {}

    let current = Number.isInteger(saved.current) ? saved.current : 0;
    current = Math.max(0, Math.min(current, steps.length - 1));
    let done = !!saved.done;

    const dots = steps.map((_, index) => {
      const dot = document.createElement('button');
      dot.type = 'button';
      dot.className = 'wizard-dot';
      dot.setAttribute('aria-label', `Aller à l’étape ${index + 1}`);
      dot.addEventListener('click', () => {
        current = index;
        done = false;
        saveAndRender();
      });
      dotsRoot?.appendChild(dot);
      return dot;
    });

    function save() {
      try { localStorage.setItem(WIZARD_KEY, JSON.stringify({ current, done })); } catch (_) {}
    }

    function render() {
      steps.forEach((step, index) => {
        const active = index === current;
        step.classList.toggle('is-active', active);
        step.setAttribute('aria-hidden', String(!active));
      });
      const percent = steps.length ? ((current + 1) / steps.length) * 100 : 0;
      if (progressBar) progressBar.style.width = `${percent}%`;
      progress?.setAttribute('aria-valuenow', String(current + 1));
      if (label) label.textContent = `Étape ${current + 1} sur ${steps.length}`;
      if (shortTitle) shortTitle.textContent = steps[current]?.dataset.shortTitle || '';
      if (prev) prev.disabled = current === 0;
      if (next) next.textContent = current === steps.length - 1 ? 'VeVak est installé ✓' : 'C’est fait →';
      dots.forEach((dot, index) => {
        dot.classList.toggle('is-active', index === current);
        dot.classList.toggle('is-done', index < current || done);
        dot.setAttribute('aria-current', index === current ? 'step' : 'false');
      });
      if (complete) complete.hidden = !done;
      if (controls) controls.hidden = done;
    }

    function saveAndRender() { save(); render(); }

    prev?.addEventListener('click', () => {
      if (current > 0) {
        current -= 1;
        done = false;
        saveAndRender();
      }
    });

    next?.addEventListener('click', () => {
      if (current < steps.length - 1) {
        current += 1;
        done = false;
        saveAndRender();
        wizard.scrollIntoView({ behavior: 'smooth', block: 'start' });
        return;
      }
      done = true;
      saveAndRender();
      complete?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });

    reset?.addEventListener('click', () => {
      if (!window.confirm('Recommencer le tutoriel d’installation depuis l’étape 1 ?')) return;
      current = 0;
      done = false;
      saveAndRender();
    });

    render();
  }

  const checks = [...document.querySelectorAll('[data-test-check]')];
  if (checks.length) {
    let savedChecks = {};
    try { savedChecks = JSON.parse(localStorage.getItem(TEST_KEY) || '{}'); } catch (_) {}

    const progressText = document.getElementById('testProgressText');
    const progressBar = document.querySelector('[data-test-progress]');
    const progress = document.querySelector('.test-progress');
    const complete = document.querySelector('[data-test-complete]');

    progress?.setAttribute('aria-valuemax', String(checks.length));

    checks.forEach((check) => {
      check.checked = !!savedChecks[check.dataset.testCheck];
      check.addEventListener('change', persistChecks);
    });

    function persistChecks() {
      const state = {};
      checks.forEach((check) => { state[check.dataset.testCheck] = check.checked; });
      try { localStorage.setItem(TEST_KEY, JSON.stringify(state)); } catch (_) {}
      renderChecks();
    }

    function renderChecks() {
      const count = checks.filter((check) => check.checked).length;
      const percent = checks.length ? (count / checks.length) * 100 : 0;
      if (progressText) progressText.textContent = `${count} / ${checks.length} terminés`;
      if (progressBar) progressBar.style.width = `${percent}%`;
      progress?.setAttribute('aria-valuenow', String(count));
      if (complete) complete.hidden = count !== checks.length;
    }

    renderChecks();
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
