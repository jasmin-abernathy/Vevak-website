(() => {
  'use strict';

  const root = document.querySelector('[data-feedback-app]');
  if (!root || !window.fetch || !window.FormData) return;

  const form = root.querySelector('[data-feedback-form]');
  const steps = [...root.querySelectorAll('[data-feedback-step]')];
  const navButtons = [...root.querySelectorAll('[data-feedback-go]')];
  const progress = root.querySelector('[data-feedback-progress]');
  const progressText = root.querySelector('[data-feedback-progress-text]');
  const saveState = root.querySelector('[data-feedback-save-state]');
  const done = root.querySelector('[data-feedback-done]');
  let current = 0;
  let timer = null;
  let saving = false;

  const answerFor = (step) => {
    const name = step.dataset.answerName;
    if (!name) return '';
    const checked = form.querySelector('input[name="' + CSS.escape(name) + '"]:checked');
    return checked ? checked.value : '';
  };

  const updateDone = () => {
    steps.forEach((step, index) => {
      navButtons[index]?.classList.toggle('is-done', answerFor(step) !== '');
    });
  };

  const show = (index) => {
    current = Math.max(0, Math.min(steps.length - 1, index));
    done.hidden = true;
    steps.forEach((step, i) => { step.hidden = i !== current; });
    navButtons.forEach((button, i) => {
      if (i === current) button.setAttribute('aria-current', 'step');
      else button.removeAttribute('aria-current');
    });
    const pct = ((current + 1) / steps.length) * 100;
    progress.style.width = pct + '%';
    progress.parentElement?.setAttribute('aria-valuenow', String(current + 1));
    progressText.textContent = (current + 1) + ' / ' + steps.length;
    steps[current]?.scrollIntoView({
      behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
      block: 'start'
    });
  };

  const save = async (submitted = false) => {
    if (saving) return false;
    saving = true;
    saveState.textContent = submitted ? 'Envoi du questionnaire…' : 'Sauvegarde…';

    const data = new FormData(form);
    data.set('action', 'save');
    data.set('submitted', submitted ? '1' : '0');

    try {
      const response = await fetch(window.location.pathname, {
        method: 'POST',
        body: data,
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
      });
      const payload = await response.json().catch(() => null);
      if (!response.ok || !payload || payload.success !== true) {
        saveState.textContent = payload?.message || 'Sauvegarde impossible pour le moment.';
        return false;
      }
      saveState.textContent = submitted
        ? 'Questionnaire enregistré.'
        : 'Réponses sauvegardées. Tu peux revenir plus tard.';
      updateDone();
      return true;
    } catch (_) {
      saveState.textContent = 'Sauvegarde impossible pour le moment.';
      return false;
    } finally {
      saving = false;
    }
  };

  const scheduleSave = () => {
    window.clearTimeout(timer);
    timer = window.setTimeout(() => { save(false); }, 550);
  };

  form.addEventListener('input', scheduleSave);
  form.addEventListener('change', scheduleSave);
  form.addEventListener('submit', (event) => event.preventDefault());

  root.querySelectorAll('[data-feedback-next]').forEach((button) => {
    button.addEventListener('click', async () => {
      await save(false);
      show(current + 1);
    });
  });

  root.querySelectorAll('[data-feedback-prev]').forEach((button) => {
    button.addEventListener('click', () => show(current - 1));
  });

  navButtons.forEach((button, index) => {
    button.addEventListener('click', async () => {
      await save(false);
      show(index);
    });
  });

  root.querySelector('[data-feedback-finish]')?.addEventListener('click', async () => {
    const ok = await save(true);
    if (!ok) return;
    steps.forEach((step) => { step.hidden = true; });
    navButtons.forEach((button) => button.removeAttribute('aria-current'));
    progress.style.width = '100%';
    progress.parentElement?.setAttribute('aria-valuenow', String(steps.length));
    progressText.textContent = steps.length + ' / ' + steps.length;
    done.hidden = false;
    done.scrollIntoView({
      behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
      block: 'start'
    });
  });

  updateDone();
  const firstIncomplete = steps.findIndex((step) => answerFor(step) === '');
  show(firstIncomplete >= 0 ? firstIncomplete : 0);
})();