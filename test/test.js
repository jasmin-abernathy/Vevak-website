(() => {
  const WIZARD_KEY = 'vevak-tester-install-v1';
  const TEST_KEY = 'vevak-tester-checklist-v1';
  const BETA_RELEASE_API = 'https://api.github.com/repos/jasmin-abernathy/vevak/releases/tags/beta';
  const BETA_RELEASE_PAGE = 'https://github.com/jasmin-abernathy/vevak/releases/tag/beta';

  const downloadLink = document.querySelector('[data-download]');
  if (downloadLink) {
    downloadLink.href = BETA_RELEASE_PAGE;
    downloadLink.removeAttribute('download');
    downloadLink.setAttribute('rel', 'noopener noreferrer');
    downloadLink.setAttribute('referrerpolicy', 'no-referrer');
    downloadLink.textContent = '📱 Recherche de la dernière bêta…';

    const downloadSection = document.getElementById('telechargement');
    const downloadIntro = downloadSection?.querySelector('.lead-small');
    const downloadMeta = downloadSection?.querySelector('.tester-meta');
    const firstInstallStep = document.querySelector('[data-step][data-short-title="Télécharger le fichier"] .step-copy');
    const firstParagraph = firstInstallStep?.querySelector('p:not(.step-kicker)');
    const normalBox = firstInstallStep?.querySelector('.normal-box');
    const secondInstallStep = document.querySelector('[data-step][data-short-title="Ouvrir l\'APK"] .step-copy');
    const secondParagraph = secondInstallStep?.querySelector('p:not(.step-kicker)');
    const helpParagraph = secondInstallStep?.querySelector('.help-box p');

    if (downloadIntro) {
      downloadIntro.textContent = 'VeVak vérifie directement la release bêta GitHub et sélectionne l’APK exacte du dernier build validé.';
    }
    if (downloadMeta) {
      downloadMeta.innerHTML = '<strong>Source :</strong> GitHub Releases · tag <code>beta</code><br><strong>Fichier :</strong> vérification en cours…';
    }
    if (normalBox) {
      normalBox.innerHTML = '<strong>✅ Ici, c’est attendu.</strong> Le téléchargement vient du dépôt GitHub officiel de VeVak et le nom du fichier inclut désormais la version et le build.';
    }

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

        if (downloadMeta) {
          const releaseName = release.name || 'VeVak bêta';
          downloadMeta.innerHTML = `<strong>Release :</strong> ${releaseName}<br><strong>Fichier exact :</strong> <code>${apk.name}</code>`;
        }
        if (firstParagraph) {
          firstParagraph.innerHTML = `Appuie sur <strong>« Télécharger ${apk.name} »</strong>. Le nom du fichier contient la version et l’identifiant du build pour éviter toute confusion avec une ancienne APK.`;
        }
        if (secondParagraph) {
          secondParagraph.innerHTML = `Appuie sur la notification de téléchargement, ou ouvre <strong>Fichiers / Mes fichiers / Téléchargements</strong>, puis touche <code>${apk.name}</code>.`;
        }
        if (helpParagraph) {
          helpParagraph.innerHTML = `Dans <strong>Téléchargements / Downloads</strong>, cherche exactement <code>${apk.name}</code>. Une APK VeVak portant un autre nom peut être une version précédente.`;
        }
      })
      .catch(() => {
        downloadLink.href = BETA_RELEASE_PAGE;
        downloadLink.textContent = '📱 Ouvrir la dernière release bêta GitHub';
        if (downloadMeta) {
          downloadMeta.innerHTML = '<strong>Vérification automatique indisponible.</strong> Ouvre la release <code>beta</code> et télécharge l’unique fichier <code>.apk</code> dont le nom contient la version et le build.';
        }
        if (firstParagraph) {
          firstParagraph.innerHTML = 'Ouvre la release bêta GitHub puis télécharge <strong>l’unique fichier .apk</strong>. Son nom doit contenir la version VeVak et un identifiant de build.';
        }
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
    try {
      saved = { ...saved, ...JSON.parse(localStorage.getItem(WIZARD_KEY) || '{}') };
    } catch (_) {}

    let current = Number.isInteger(saved.current) ? saved.current : 0;
    current = Math.max(0, Math.min(current, steps.length - 1));
    let done = !!saved.done;

    const dots = steps.map((_, index) => {
      const dot = document.createElement('button');
      dot.type = 'button';
      dot.className = 'wizard-dot';
      dot.setAttribute('aria-label', `Aller à l'étape ${index + 1}`);
      dot.addEventListener('click', () => {
        current = index;
        done = false;
        saveAndRender();
      });
      dotsRoot.appendChild(dot);
      return dot;
    });

    function save() {
      try {
        localStorage.setItem(WIZARD_KEY, JSON.stringify({ current, done }));
      } catch (_) {}
    }

    function render() {
      steps.forEach((step, index) => {
        const active = index === current;
        step.classList.toggle('is-active', active);
        step.setAttribute('aria-hidden', String(!active));
      });

      const percent = ((current + 1) / steps.length) * 100;
      progressBar.style.width = `${percent}%`;
      progress.setAttribute('aria-valuenow', String(current + 1));
      label.textContent = `Étape ${current + 1} sur ${steps.length}`;
      shortTitle.textContent = steps[current].dataset.shortTitle || '';
      prev.disabled = current === 0;
      next.textContent = current === steps.length - 1 ? 'VeVak est installé ✓' : "C'est fait →";

      dots.forEach((dot, index) => {
        dot.classList.toggle('is-active', index === current);
        dot.classList.toggle('is-done', index < current || done);
        dot.setAttribute('aria-current', index === current ? 'step' : 'false');
      });

      complete.hidden = !done;
      controls.hidden = done;
    }

    function saveAndRender() {
      save();
      render();
    }

    prev.addEventListener('click', () => {
      if (current > 0) {
        current -= 1;
        done = false;
        saveAndRender();
      }
    });

    next.addEventListener('click', () => {
      if (current < steps.length - 1) {
        current += 1;
        done = false;
        saveAndRender();
        wizard.scrollIntoView({ behavior: 'smooth', block: 'start' });
        return;
      }

      done = true;
      saveAndRender();
      complete.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });

    reset.addEventListener('click', () => {
      const confirmed = window.confirm("Recommencer le tutoriel d'installation depuis l'étape 1 ?");
      if (!confirmed) return;
      current = 0;
      done = false;
      saveAndRender();
    });

    render();
  }

  const checklist = document.querySelector('[data-checklist]');
  if (checklist && !document.querySelector('[data-test-check="trusted-wifi-location-off"]')) {
    checklist.insertAdjacentHTML(
      'beforeend',
      `<label>
        <input type="checkbox" data-test-check="trusted-wifi-location-off">
        <span>
          <strong>Je teste « Maison » sans activer la localisation</strong>
          <small>Connecte le téléphone au Wi-Fi de la maison, enregistre « Maison » sans activer la localisation puis fais une demande SMS. Tant que cette même connexion Wi-Fi reste active, VeVak doit répondre « Je suis à la maison ».</small>
        </span>
      </label>
      <label>
        <input type="checkbox" data-test-check="remembered-location-off">
        <span>
          <strong>Je teste la dernière position VeVak avec Localisation coupée</strong>
          <small>Après avoir obtenu au moins une vraie position, coupe « Localisation » puis refais une demande hors du raccourci Maison. Si aucun nouveau point n'est possible, VeVak doit pouvoir renvoyer sa dernière position mémorisée en indiquant clairement son âge.</small>
        </span>
      </label>`
    );
  }

  const testHeading = document.querySelector('#test .section-heading h2');
  if (testHeading) {
    testHeading.textContent = '10 petits tests, dont 2 de résilience.';
  }

  const checks = [...document.querySelectorAll('[data-test-check]')];
  if (checks.length) {
    let savedChecks = {};
    try {
      savedChecks = JSON.parse(localStorage.getItem(TEST_KEY) || '{}');
    } catch (_) {}

    const progressText = document.getElementById('testProgressText');
    const progressBar = document.querySelector('[data-test-progress]');
    const progress = document.querySelector('.test-progress');
    const complete = document.querySelector('[data-test-complete]');

    if (progress) {
      progress.setAttribute('aria-valuemax', String(checks.length));
    }

    if (checklist && !document.querySelector('[data-feedback-link]')) {
      const feedbackActions = document.createElement('div');
      feedbackActions.className = 'actions';
      feedbackActions.dataset.feedbackLink = '';

      const resilienceLink = document.createElement('a');
      resilienceLink.className = 'button primary';
      resilienceLink.href = 'retours/resilience-0.3.1.html';
      resilienceLink.textContent = '📍 Retour ciblé résilience';

      const feedbackLink = document.createElement('a');
      feedbackLink.className = 'button secondary';
      feedbackLink.href = 'retours/';
      feedbackLink.textContent = '📝 Questionnaire complet';

      feedbackActions.append(resilienceLink, feedbackLink);
      checklist.insertAdjacentElement('afterend', feedbackActions);
    }

    checks.forEach((check) => {
      check.checked = !!savedChecks[check.dataset.testCheck];
      check.addEventListener('change', persistChecks);
    });

    function persistChecks() {
      const state = {};
      checks.forEach((check) => {
        state[check.dataset.testCheck] = check.checked;
      });
      try {
        localStorage.setItem(TEST_KEY, JSON.stringify(state));
      } catch (_) {}
      renderChecks();
    }

    function renderChecks() {
      const count = checks.filter((check) => check.checked).length;
      const percent = (count / checks.length) * 100;

      checks.forEach((check) => {
        check.closest('label')?.classList.toggle('is-done', check.checked);
      });

      progressText.textContent = `${count} / ${checks.length} terminés`;
      progressBar.style.width = `${percent}%`;
      progress.setAttribute('aria-valuenow', String(count));
      complete.hidden = count !== checks.length;
    }

    renderChecks();
  }
})();
