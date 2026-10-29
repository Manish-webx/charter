/* Worldwide Charters — enquiry form: category preselection, validation and submission. */
(function () {
  'use strict';

  var form = document.getElementById('enquiry-form');
  if (!form) return;

  var statusBox = document.getElementById('form-status');
  var submitBtn = document.getElementById('submit-btn');
  var submitLabel = submitBtn.querySelector('.btn-label');
  var category = form.elements.category;
  var FALLBACK_EMAIL = 'worldwidecharterservice@gmail.com';
  var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

  // Spam time-trap: record when the visitor first saw the form.
  form.elements.form_started.value = String(Math.floor(Date.now() / 1000));

  // Preselect the category from ?service=<id>, accepting only values that exist in the list.
  var params = new URLSearchParams(window.location.search);
  var requested = params.get('service');
  if (requested) {
    var allowed = Array.prototype.some.call(category.options, function (o) {
      return o.value && o.value === requested;
    });
    if (allowed) category.value = requested;
  }

  // Result of a no-JavaScript submission redirected back by the server.
  if (params.get('sent') === '1') {
    showStatus('success', 'Thank you — your enquiry has been sent. Our team will get back to you by email.');
  } else if (params.get('error')) {
    showStatus('danger', 'Sorry, your enquiry could not be sent. Please try again, or email us at ' + mailLink() + '.');
  }

  function mailLink() {
    return '<a href="mailto:' + FALLBACK_EMAIL + '">' + FALLBACK_EMAIL + '</a>';
  }

  function showStatus(type, html) {
    var icon = type === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill';
    statusBox.className = 'alert form-status is-visible alert-' + type;
    statusBox.innerHTML =
      '<svg class="bi" aria-hidden="true"><use href="assets/img/icons.svg#' + icon + '"></use></svg><div>' + html + '</div>';
    statusBox.focus();
  }

  function clearStatus() {
    statusBox.className = 'alert form-status';
    statusBox.innerHTML = '';
  }

  function fieldError(field) {
    var v = field.value.trim();
    switch (field.name) {
      case 'name':
        return v.length < 2 ? 'Please enter your full name.' : '';
      case 'email':
        if (!v) return 'Please enter your email address.';
        return EMAIL_RE.test(v) ? '' : 'Please enter a valid email address, for example name@company.com.';
      case 'phone':
        return v && !field.checkValidity() ? 'Please enter a valid phone number using digits, spaces and + ( ) - only.' : '';
      case 'category':
        return v ? '' : 'Please choose the category that best fits your enquiry.';
      case 'message':
        return v.length < 10 ? 'Please enter a message of at least 10 characters.' : '';
      case 'consent':
        return field.checked ? '' : 'Please confirm that we may use your details to respond.';
      default:
        return '';
    }
  }

  function setFieldState(field, message) {
    var feedback = document.getElementById(field.name + '-error');
    if (message) {
      field.classList.add('is-invalid');
      field.setAttribute('aria-invalid', 'true');
      if (feedback) feedback.textContent = message;
    } else {
      field.classList.remove('is-invalid');
      field.removeAttribute('aria-invalid');
    }
  }

  var validated = ['name', 'email', 'phone', 'category', 'message', 'consent'].map(function (n) {
    return form.elements[n];
  });

  // Re-check a field as the visitor corrects it (only after it has been flagged).
  validated.forEach(function (field) {
    var evt = field.type === 'checkbox' || field.tagName === 'SELECT' ? 'change' : 'input';
    field.addEventListener(evt, function () {
      if (field.classList.contains('is-invalid')) setFieldState(field, fieldError(field));
    });
    field.addEventListener('blur', function () {
      if (field.value || field.classList.contains('is-invalid')) setFieldState(field, fieldError(field));
    });
  });

  function setLoading(loading) {
    submitBtn.disabled = loading;
    form.setAttribute('aria-busy', loading ? 'true' : 'false');
    var spinner = submitBtn.querySelector('.spinner-border');
    if (loading && !spinner) {
      spinner = document.createElement('span');
      spinner.className = 'spinner-border';
      spinner.setAttribute('aria-hidden', 'true');
      submitBtn.insertBefore(spinner, submitLabel);
    } else if (!loading && spinner) {
      spinner.remove();
    }
    submitLabel.textContent = loading ? 'Sending…' : 'Send Enquiry';
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    clearStatus();

    var firstInvalid = null;
    var count = 0;
    validated.forEach(function (field) {
      var msg = fieldError(field);
      setFieldState(field, msg);
      if (msg) {
        count++;
        if (!firstInvalid) firstInvalid = field;
      }
    });
    if (firstInvalid) {
      showStatus('danger', 'Please correct the ' + (count === 1 ? 'highlighted field' : count + ' highlighted fields') + ' and try again.');
      firstInvalid.focus();
      return;
    }

    setLoading(true);
    fetch(form.action, {
      method: 'POST',
      body: new FormData(form),
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    })
      .then(function (res) {
        return res.json().catch(function () { return { ok: false }; }).then(function (data) {
          return { status: res.status, data: data };
        });
      })
      .then(function (result) {
        var data = result.data || {};
        if (result.status === 200 && data.ok) {
          form.reset();
          form.elements.form_started.value = String(Math.floor(Date.now() / 1000));
          showStatus('success', 'Thank you — your enquiry has been sent. Our team will get back to you by email.');
          return;
        }
        if (data.errors) {
          var first = null;
          Object.keys(data.errors).forEach(function (name) {
            var field = form.elements[name];
            if (field && field.name) {
              setFieldState(field, data.errors[name]);
              if (!first) first = field;
            }
          });
        }
        var msg = data.message ? escapeHtml(data.message) : 'Sorry, your enquiry could not be sent.';
        showStatus('danger', msg + ' If the problem continues, please email us at ' + mailLink() + '.');
      })
      .catch(function () {
        showStatus('danger', 'Sorry, we could not reach the server. Please check your connection and try again, or email us at ' + mailLink() + '.');
      })
      .then(function () {
        setLoading(false);
      });
  });

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
})();
