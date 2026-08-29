(() => {
  const WIZARD_KEY = 'vevak-tester-install-v1';
  const TEST_KEY = 'vevak-tester-checklist-v1';
  const APK_DOWNLOAD_URL = 'https://drive.google.com/drive/folders/1CgzsBx0_Lh_TwcjFpFm5KwhYxupj5ZpW';

  const downloadLink = document.querySelector('[data-download]');
  if (downloadLink) {
    downloadLink.href = APK_DOWNLOAD_URL;
    downloadLink.removeAttribute('download');
    downloadLink.setAttribute('target', '_blank');
    downloadLink.setAttribute('rel', 'noopener noreferrer');
    downloadLink.setAttribute('referrerpolicy', 'no-referrer');
    downloadLink.textContent = '📱 Ouvrir le téléchargement VeVak';

    const downloadSection = document.getElementById('telechargement');
    const downloadIntro = downloadSection?.querySelector('.lead-small');
    const downloadMeta = downloadSection?.querySelector('.tester-meta');

    if (downloadIntro) {
      downloadIntro.textContent = 'La dernière APK de test est publiée dans le dossier Drive VeVak. Ouvre-le et télécharge le fichier .apk le plus récent : une nouvelle version peut remplacer rapidement la précédente.';
    }

    if (downloadMeta) {
      downloadMeta.innerHTML = '<strong>Source :</strong> dossier Drive VeVak<br><strong>À télécharger :</strong> le fichier <code>.apk</code> le plus récent (0.3.1 ou plus récent pour le test de résilience)';
    }

    const firstInstallStep = document.querySelector('[data-step][data-short-title="Télécharger le fichier"] .step-copy');
    if (firstInstallStep) {
      const firstParagraph = firstInstallStep.querySelector('p:not(.step-kicker)');
      const normalBox = firstInstallStep.querySelector('.normal-box');

      if (firstParagraph) {
        firstParagraph.innerHTML = 'Appuie sur <strong>« Ouvrir le téléchargement VeVak »</strong>. Dans le dossier Drive, choisis le fichier <code>.apk</code> le plus récent puis lance son téléchargement. Ton navigateur peut te prévenir qu’un fichier APK peut présenter un risque.';
      }

      if (normalBox) {
        normalBox.innerHTML = '<strong>✅ Ici, c’est attendu.</strong> Tu viens toi-même de demander l’APK depuis le dossier de test VeVak : confirme uniquement ce téléchargement.';
      }
    }

    const secondInstallStep = document.querySelector('[data-step][data-short-title="Ouvrir l\'APK"] .step-copy');
    if (secondInstallStep) {
      const secondParagraph = secondInstallStep.querySelector('p:not(.step-kicker)');
      const helpParagraph = secondInstallStep.querySelector('.help-box p');

      if (secondParagraph) {
        secondParagraph.innerHTML = 'Appuie sur la notification de téléchargement, ou ouvre l’application <strong>Fichiers / Mes fichiers / Téléchargements</strong>, puis touche le fichier VeVak qui se termine par <code>.apk</code>.';
      }

      if (helpParagraph) {
        helpParagraph.innerHTML = 'Ouvre ton application de fichiers puis le dossier <strong>Téléchargements / Downloads</strong>. Cherche le fichier VeVak qui se termine par <code>.apk</code>. Tu peux aussi rouvrir le dossier Drive depuis le haut de cette page.';
      }
    }
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

  // 0.3.1 regression checks: these are injected before the generic checklist logic so they use
  // the same local persistence and progress handling as the original tests.
  const checklist = document.querySelector('[data-checklist]');
  if (checklist && !document.querySelector('[data-test-check="trusted-wifi-location-off"]')) {
    checklist.insertAdjacentHTML(
      'beforeend',
      `<label>
        <input type="checkbox" data-test-check="trusted-wifi-location-off">
        <span>
          <strong>Je teste « Maison » avec Localisation coupée</strong>
          <small>Enregistre le Wi-Fi de confiance avec Localisation activée, reste connecté au même Wi-Fi, coupe ensuite le bouton Android « Localisation » et fais une nouvelle demande SMS. VeVak doit encore répondre avec le lieu de confiance.</small>
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

      const feedbackLink = document.createElement('a');
      feedbackLink.className = 'button primary';
      feedbackLink.href = 'retours/';
      feedbackLink.textContent = '📝 Faire le questionnaire de retour';

      feedbackActions.appendChild(feedbackLink);
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
