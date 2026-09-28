(function () {
  var config = window.featvalueStatusGate;
  if (!config || !config.restUrl) {
    return;
  }

  document.querySelectorAll('[data-fv-status-gate]').forEach(function (root) {
    var button = root.querySelector('[data-fv-resend-verification]');
    var feedback = root.querySelector('[data-fv-resend-feedback]');
    if (!button || !feedback) {
      return;
    }
    var i18n = config.i18n || {};

    button.addEventListener('click', function () {
      button.disabled = true;
      feedback.hidden = false;
      feedback.className = 'fv-embed-status-feedback';
      feedback.textContent = i18n.sending || '';

      fetch(config.restUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: '{}'
      }).then(function (response) {
        return response.json().then(function (body) {
          return { ok: response.ok, body: body || {} };
        }).catch(function () {
          return { ok: response.ok, body: {} };
        });
      }).then(function (result) {
        feedback.className = 'fv-embed-status-feedback';
        if (result.ok && result.body.verification_email_sent) {
          feedback.classList.add('is-success');
          feedback.textContent = i18n.sent || '';
        } else if (result.ok && result.body.verification_email_throttled) {
          feedback.textContent = i18n.throttled || '';
        } else {
          feedback.classList.add('is-error');
          feedback.textContent = i18n.failed || '';
        }
      }).catch(function () {
        feedback.className = 'fv-embed-status-feedback is-error';
        feedback.textContent = i18n.failed || '';
      }).finally(function () {
        button.disabled = false;
      });
    });
  });
})();
