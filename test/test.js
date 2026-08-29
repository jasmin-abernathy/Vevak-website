(() => {
  const WIZARD_KEY = 'vevak-tester-install-v1';
  const TEST_KEY = 'vevak-tester-checklist-v1';

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
