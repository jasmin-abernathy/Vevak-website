(() => {
  'use strict';

  const form = document.querySelector('[data-tester-form]');
  if (!form || !window.fetch || !window.FormData) return;

  const email = form.querySelector('input[name="email"]');
  const deviceModel = form.querySelector('input[name="device_model"]');
  const androidVersion = form.querySelector('input[name="android_version"]');
  const simSetup = form.querySelector('select[name="sim_setup"]');
  const consent = form.querySelector('input[name="consent"]');
  const feedbackConsent = form.querySelector('input[name="feedback_consent"]');
  const submit = form.querySelector('button[type="submit"]');
  const status = form.querySelector('[data-tester-status]');
  const defaultLabel = submit ? submit.textContent : '';
  let sending = false;

  const messages = {
    invalid: 'Vérifie ton adresse e-mail avant de continuer.',
    profile: 'Indique le modèle du téléphone, la version Android et la configuration SIM.',
    consent: 'Confirme que tu souhaites utiliser ces informations pour participer aux tests.',
    feedback: 'Confirme que tu participeras au questionnaire de retour après le test.',
    server: 'Ta demande n’a pas pu être enregistrée. Réessaie dans un instant.'
  };

  function announce(message, kind = 'error') {
    if (!status) return;
    status.textContent = message;
    status.dataset.state = kind;
    status.hidden = false;
  }

  function clearStatus() {
    if (!status) return;
    status.textContent = '';
    status.dataset.state = '';
    status.hidden = true;
  }

  function setSending(active) {
    sending = active;
    if (!submit) return;
    submit.disabled = active;
    submit.setAttribute('aria-disabled', String(active));
    submit.textContent = active ? 'Envoi en cours…' : defaultLabel;
  }

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (sending) return;
    clearStatus();

    if (!email || !email.value.trim() || !email.validity.valid) {
      announce(messages.invalid);
      email?.focus();
      return;
    }
    if (
      !deviceModel || !deviceModel.value.trim()
      || !androidVersion || !androidVersion.value.trim()
      || !simSetup || !simSetup.value
    ) {
      announce(messages.profile);
      if (!deviceModel?.value.trim()) deviceModel?.focus();
      else if (!androidVersion?.value.trim()) androidVersion?.focus();
      else simSetup?.focus();
      return;
    }
    if (!consent || !consent.checked) {
      announce(messages.consent);
      consent?.focus();
      return;
    }
    if (!feedbackConsent || !feedbackConsent.checked) {
      announce(messages.feedback);
      feedbackConsent?.focus();
      return;
    }

    setSending(true);
    try {
      const response = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
      });
      let payload = null;
      try {
        payload = await response.json();
      } catch (_) {
        payload = null;
      }
      if (!response.ok || !payload || payload.success !== true) {
        announce(payload?.message || messages.server);
        return;
      }

      announce(payload.message, 'success');
      form.reset();
    } catch (_) {
      announce(messages.server);
    } finally {
      setSending(false);
    }
  });
})();
