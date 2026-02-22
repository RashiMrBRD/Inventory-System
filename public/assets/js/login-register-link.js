(function() {
  'use strict';
  
  // Check if on demo domain
  var isDemoDomain = window.location.hostname === 'demo.rashlink.eu.org';
  if (typeof window.debugLog === 'function') {
    window.debugLog('[Registration Link] isDemoDomain:', isDemoDomain);
  }

  function getInviteToken() {
    try {
      var params = new URLSearchParams(window.location.search);
      return params.get('invite') || params.get('invite_token') || '';
    } catch (e) {
      return '';
    }
  }

  function isRegisterRoute() {
    return window.location.pathname.replace(/\/+$/, '') === '/register';
  }

  var inviteValidationTimer = null;

  function getRegistrationFormContainer() {
    var loginForm = document.querySelector('.login-form');
    if (!loginForm) return null;
    if (loginForm.tagName && loginForm.tagName.toLowerCase() === 'form') {
      return loginForm;
    }
    var innerForm = loginForm.querySelector('form');
    return innerForm || loginForm;
  }

  function ensureInviteToken(token) {
    if (!token) return;
    var formContainer = getRegistrationFormContainer();
    if (!formContainer) return;
    var input = formContainer.querySelector('input[name="invite_token"]');
    if (!input) {
      input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'invite_token';
      formContainer.insertBefore(input, formContainer.firstChild);
    }
    input.value = token;
  }

  function normalizeInviteMessage(message) {
    var safeMessage = (message || '').toString();
    if (safeMessage.toLowerCase().indexOf('maximum uses') !== -1) {
      return 'Invalid invitation key';
    }
    return safeMessage || 'Invalid invitation key';
  }

  function setInviteError(message) {
    var formContainer = getRegistrationFormContainer();
    if (!formContainer) return;
    var existing = formContainer.querySelector('.invite-error');
    if (!message) {
      if (existing && existing.parentNode) {
        existing.parentNode.removeChild(existing);
      }
      return;
    }
    if (!existing) {
      existing = document.createElement('div');
      existing.className = 'invite-error';
      existing.style.margin = '0 0 1rem 0';
      existing.style.fontSize = '0.875rem';
      existing.style.color = '#ef4444';
      formContainer.insertBefore(existing, formContainer.firstChild);
    }
    existing.textContent = message;
  }

  function setInviteDisabled(disabled) {
    var formContainer = getRegistrationFormContainer();
    if (!formContainer) return;
    var submitButton = formContainer.querySelector('button[type="submit"]') || formContainer.querySelector('button');
    if (!submitButton) return;
    submitButton.disabled = !!disabled;
    submitButton.style.opacity = disabled ? '0.6' : '';
    submitButton.style.cursor = disabled ? 'not-allowed' : '';
  }

  function validateInviteToken(token, callback) {
    if (!token) {
      if (typeof callback === 'function') {
        callback(null);
      }
      return;
    }
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '/api/invites.php?action=validate&token=' + encodeURIComponent(token), true);
    xhr.onreadystatechange = function() {
      if (xhr.readyState !== 4) return;
      if (xhr.status >= 200 && xhr.status < 300) {
        try {
          var data = JSON.parse(xhr.responseText);
          callback(data);
        } catch (e) {
          callback({ success: false, message: 'Invalid invitation key' });
        }
      } else {
        callback({ success: false, message: 'Invalid invitation key' });
      }
    };
    xhr.send();
  }

  function handleInviteValidation(token) {
    if (!token) {
      setInviteError('');
      setInviteDisabled(false);
      return;
    }
    setInviteDisabled(true);
    validateInviteToken(token, function(result) {
      if (!result || result.success === false) {
        var message = normalizeInviteMessage(result && result.message ? result.message : '');
        setInviteError(message);
        setInviteDisabled(true);
        return;
      }
      setInviteError('');
      setInviteDisabled(false);
    });
  }

  function scheduleInviteValidation(token) {
    if (inviteValidationTimer) {
      clearTimeout(inviteValidationTimer);
    }
    inviteValidationTimer = setTimeout(function() {
      handleInviteValidation(token);
    }, 300);
  }

  function ensureInviteField(token) {
    var allowInvitations = document.body.getAttribute('data-allow-invitations') === 'true';
    if (!allowInvitations) {
      return;
    }
    var formContainer = getRegistrationFormContainer();
    if (!formContainer) return;

    var inviteGroup = formContainer.querySelector('[data-invite-field="true"]');
    var inviteInput = formContainer.querySelector('input[name="invite_token"]');

    if (!inviteGroup) {
      inviteGroup = document.createElement('div');
      inviteGroup.className = 'form-group';
      inviteGroup.setAttribute('data-invite-field', 'true');
      inviteGroup.style.marginBottom = '1rem';

      var label = document.createElement('label');
      label.setAttribute('for', 'reg_invite_token');
      label.className = 'form-label';
      label.style.display = 'block';
      label.style.marginBottom = '0.5rem';
      label.style.fontWeight = '500';
      label.textContent = 'Invitation Key';
      inviteGroup.appendChild(label);

      if (!inviteInput) {
        inviteInput = document.createElement('input');
      } else if (inviteInput.parentNode) {
        inviteInput.parentNode.removeChild(inviteInput);
      }

      inviteInput.type = 'text';
      inviteInput.id = 'reg_invite_token';
      inviteInput.name = 'invite_token';
      inviteInput.className = 'form-input';
      inviteInput.style.width = '100%';
      inviteInput.style.padding = '0.625rem';
      inviteInput.style.border = '1px solid var(--border-color)';
      inviteInput.style.borderRadius = 'var(--radius-md)';
      inviteInput.style.fontSize = '0.875rem';
      inviteInput.placeholder = 'Enter invitation key';
      inviteGroup.appendChild(inviteInput);

      var emailInput = formContainer.querySelector('input[name="email"]');
      var insertAfter = null;
      if (emailInput) {
        var parent = emailInput.parentNode;
        while (parent && parent !== formContainer) {
          if (parent.classList && parent.classList.contains('form-group')) {
            insertAfter = parent;
            break;
          }
          parent = parent.parentNode;
        }
      }

      if (insertAfter && insertAfter.parentNode === formContainer) {
        formContainer.insertBefore(inviteGroup, insertAfter.nextSibling);
      } else {
        formContainer.insertBefore(inviteGroup, formContainer.firstChild);
      }
    }

    if (inviteGroup) {
      inviteInput = inviteGroup.querySelector('input[name="invite_token"]') || inviteInput;
    }

    if (inviteInput && token) {
      inviteInput.value = token;
    }

    if (inviteInput && !inviteInput.getAttribute('data-invite-listener')) {
      inviteInput.setAttribute('data-invite-listener', 'true');
      inviteInput.addEventListener('input', function() {
        scheduleInviteValidation(inviteInput.value.trim());
      });
      inviteInput.addEventListener('blur', function() {
        handleInviteValidation(inviteInput.value.trim());
      });
    }
  }
  
  function addRegisterLink() {
    // Only add link if not first run
    var isFirstRun = window.location.search.includes('isFirstRun=true');
    if (typeof window.debugLog === 'function') {
      window.debugLog('[Registration Link] isFirstRun:', isFirstRun);
    }
    if (isFirstRun) return;
    
    // Never show registration link on demo domain
    if (isDemoDomain) return;
    
    // Check if allow_registration setting is enabled
    // This is set via a data attribute on the body element
    var allowRegistration = document.body.getAttribute('data-allow-registration') === 'true';
    if (typeof window.debugLog === 'function') {
      window.debugLog('[Registration Link] data-allow-registration attribute:', document.body.getAttribute('data-allow-registration'));
      window.debugLog('[Registration Link] allowRegistration:', allowRegistration);
    }
    
    // If attribute is not set yet, wait and try again
    if (!document.body.hasAttribute('data-allow-registration')) {
      if (typeof window.debugLog === 'function') {
        window.debugLog('[Registration Link] Attribute not set yet, retrying...');
      }
      setTimeout(addRegisterLink, 100);
      return;
    }
    
    var inviteToken = getInviteToken();
    var forceRegistration = !!inviteToken || isRegisterRoute();

    if (!allowRegistration && !forceRegistration) {
      if (typeof window.debugLog === 'function') {
        window.debugLog('[Registration Link] Registration not allowed, skipping');
      }
      return;
    }
    
    // Wait for login form to appear
    var loginForm = document.querySelector('.login-form');
    if (typeof window.debugLog === 'function') {
      window.debugLog('[Registration Link] loginForm found:', !!loginForm);
    }
    if (!loginForm) {
      setTimeout(addRegisterLink, 100);
      return;
    }
    
    // Check if link already exists
    if (document.querySelector('.login-footer')) {
      if (typeof window.debugLog === 'function') {
        window.debugLog('[Registration Link] Link already exists');
      }
      return;
    }
    
    if (typeof window.debugLog === 'function') {
      window.debugLog('[Registration Link] Adding registration link');
    }
    // Create and add registration link
    var footer = document.createElement('div');
    footer.className = 'login-footer';
    footer.innerHTML = '<p>Don\'t have an account? <a href="#" onclick="showRegistrationForm();return false;">Register</a></p>';
    loginForm.parentNode.insertBefore(footer, loginForm.nextSibling);
    if (typeof window.debugLog === 'function') {
      window.debugLog('[Registration Link] Link added to DOM');
      window.debugLog('[Registration Link] Link element:', footer);
      window.debugLog('[Registration Link] Link visible:', footer.offsetParent !== null);
      window.debugLog('[Registration Link] Link display:', window.getComputedStyle(footer).display);
    }
  }

  function autoShowRegistration() {
    var inviteToken = getInviteToken();
    var forceRegistration = !!inviteToken || isRegisterRoute();
    if (!forceRegistration) return;

    var loginForm = document.querySelector('.login-form');
    if (!loginForm) {
      setTimeout(autoShowRegistration, 100);
      return;
    }

    if (typeof window.showRegistrationForm === 'function') {
      window.showRegistrationForm();
      ensureInviteField(inviteToken);
      ensureInviteToken(inviteToken);
      handleInviteValidation(inviteToken);
    }
  }
  
  // Wait for page to be visible
  function waitForVisibility() {
    if (document.documentElement.style.visibility === 'visible') {
      if (typeof window.debugLog === 'function') {
        window.debugLog('[Registration Link] Page visible, adding link...');
      }
      setTimeout(addRegisterLink, 100);
      setTimeout(autoShowRegistration, 150);
    } else {
      setTimeout(waitForVisibility, 50);
    }
  }
  
  // Start waiting
  waitForVisibility();
  
  // Obfuscated registration form HTML (base64 encoded to prevent easy reading)
  var REGISTER_FORM_HTML = 'PGZvcm0gbWV0aG9kPSJQT1NUIiBhY3Rpb249IiIgaWQ9InJlZ2lzdGVyRm9ybSIgbm92YWxpZGF0ZT48ZGl2IHN0eWxlPSJtYXJnaW4tYm90dG9tOjEuNXJlbSI+PGgzIHN0eWxlPSJmb250LXNpemU6MS4xMjVyZW07Zm9udC13ZWlnaHQ6NjAwO21hcmdpbjowIDAgMC41cmVtIDAiPkNyZWF0ZSBBY2NvdW50PC9oMz48cCBzdHlsZT0iZm9udC1zaXplOjAuODc1cmVtO2NvbG9yOnZhcigtLXRleHQtc2Vjb25kYXJ5KTttYXJnaW46MCI+RmlsbCBpbiB0aGUgZGV0YWlscyB0byBjcmVhdGUgeW91ciBhY2NvdW50PC9wPjwvZGl2PjxkaXYgY2xhc3M9ImZvcm0tZ3JvdXAiIHN0eWxlPSJtYXJnaW4tYm90dG9tOjFyZW0iPjxsYWJlbCBmb3I9InJlZ19lbWFpbCIgY2xhc3M9ImZvcm0tbGFiZWwiIHN0eWxlPSJkaXNwbGF5OmJsb2NrO21hcmdpbi1ib3R0b206MC41cmVtO2ZvbnQtd2VpZ2h0OjUwMCI+RW1haWw8L2xhYmVsPjxkaXYgY2xhc3M9ImVtYWlsLWlucHV0LXdyYXBwZXIiIHN0eWxlPSJwb3NpdGlvbjpyZWxhdGl2ZSI+PGlucHV0IHR5cGU9ImVtYWlsIiBpZD0icmVnX2VtYWlsIiBuYW1lPSJlbWFpbCIgY2xhc3M9ImZvcm0taW5wdXQiIHN0eWxlPSJ3aWR0aDoxMDAlO3BhZGRpbmc6MC42MjVyZW0gMi41cmVtIDAuNjI1cmVtIDAuNjI1cmVtO2JvcmRlcjoxcHggc29saWQgdmFyKC0tYm9yZGVyLWNvbG9yKTtib3JkZXItcmFkaXVzOnZhcigtLXJhZGl1cy1tZCk7Zm9udC1zaXplOjAuODc1cmVtIiBwbGFjZWhvbGRlcj0iRW50ZXIgeW91ciBlbWFpbCI+PHNwYW4gY2xhc3M9ImVtYWlsLXZhbGlkYXRpb24taWNvbiIgc3R5bGU9InBvc2l0aW9uOmFic29sdXRlO3JpZ2h0OjAuNzVyZW07dG9wOjUwJTt0cmFuc2Zvcm06dHJhbnNsYXRlWSgtNTAlKTtkaXNwbGF5Om5vbmU7d2lkdGg6MS4yNXJlbTtoZWlnaHQ6MS4yNXJlbTsiPjwvc3Bhbj48L2Rpdj48ZGl2IGNsYXNzPSJlbWFpbC12YWxpZGF0aW9uLW1lc3NhZ2UiIHN0eWxlPSJmb250LXNpemU6MC43NXJlbTttYXJnaW4tdG9wOjAuMjVyZW07aGVpZ2h0OjFyZW07ZGlzcGxheTpub25lOyI+PC9kaXY+PC9kaXY+PGRpdiBjbGFzcz0iZm9ybS1ncm91cCIgc3R5bGU9Im1hcmdpbi1ib3R0b206MXJlbSI+PGxhYmVsIGZvcj0icmVnX3VzZXJuYW1lIiBjbGFzcz0iZm9ybS1sYWJlbCIgc3R5bGU9ImRpc3BsYXk6YmxvY2s7bWFyZ2luLWJvdHRvbTowLjVyZW07Zm9udC13ZWlnaHQ6NTAwIj5Vc2VybmFtZTwvbGFiZWw+PGlucHV0IHR5cGU9InRleHQiIGlkPSJyZWdfdXNlcm5hbWUiIG5hbWU9InVzZXJuYW1lIiBjbGFzcz0iZm9ybS1pbnB1dCIgc3R5bGU9IndpZHRoOjEwMCU7cGFkZGluZzowLjYyNXJlbTtib3JkZXI6MXB4IHNvbGlkIHZhcigtLWJvcmRlci1jb2xvcik7Ym9yZGVyLXJhZGl1czp2YXIoLS1yYWRpdXMtbWQpO2ZvbnQtc2l6ZTowLjg3NXJlbSIgcGxhY2Vob2xkZXI9IkNob29zZSBhIHVzZXJuYW1lIiByZXF1aXJlZD48L2Rpdj48ZGl2IGNsYXNzPSJmb3JtLWdyb3VwIiBzdHlsZT0ibWFyZ2luLWJvdHRvbToxcmVtIj48bGFiZWwgZm9yPSJyZWdfcGFzc3dvcmQiIGNsYXNzPSJmb3JtLWxhYmVsIiBzdHlsZT0iZGlzcGxheTpibG9jazttYXJnaW4tYm90dG9tOjAuNXJlbTtmb250LXdlaWdodDo1MDAiPlBhc3N3b3JkPC9sYWJlbD48ZGl2IGNsYXNzPSJwYXNzd29yZC1pbnB1dC13cmFwcGVyIiBzdHlsZT0icG9zaXRpb246cmVsYXRpdmUiPjxpbnB1dCB0eXBlPSJwYXNzd29yZCIgaWQ9InJlZ19wYXNzd29yZCIgbmFtZT0icGFzc3dvcmQiIGNsYXNzPSJmb3JtLWlucHV0IiBzdHlsZT0id2lkdGg6MTAwJTtwYWRkaW5nOjAuNjI1cmVtIDIuNXJlbSAwLjYyNXJlbSAwLjYyNXJlbTtib3JkZXI6MXB4IHNvbGlkIHZhcigtLWJvcmRlci1jb2xvcik7Ym9yZGVyLXJhZGl1czp2YXIoLS1yYWRpdXMtbWQpO2ZvbnQtc2l6ZTowLjg3NXJlbSIgcGxhY2Vob2xkZXI9IkNyZWF0ZSBhIHBhc3N3b3JkIj48c3BhbiBjbGFzcz0icGFzc3dvcmQtdG9nZ2xlLWljb24iIHN0eWxlPSJwb3NpdGlvbjphYnNvbHV0ZTtyaWdodDowLjc1cmVtO3RvcDo1MCU7dHJhbnNmb3JtOnRyYW5zbGF0ZVkoLTUwJSk7Y3Vyc29yOnBvaW50ZXI7Y29sb3I6dmFyKC0tdGV4dC1zZWNvbmRhcnkpOyI+PHN2ZyB2aWV3Qm94PSIwIDAgMjQgMjQiIGZpbGw9Im5vbmUiIHN0cm9rZT0iY3VycmVudENvbG9yIiBzdHJva2Utd2lkdGg9IjIiIHN0eWxlPSJ3aWR0aDoxLjI1cmVtO2hlaWdodDoxLjI1cmVtOyI+PHBhdGggZD0iTTEgMTJzNC04IDExLTggMTEgOCAxMSA4LTQgOC0xMSA4LTExLTgtMTEtOHoiLz48Y2lyY2xlIGN4PSIxMiIgY3k9IjEyIiByPSIzIi8+PC9zdmc+PC9zcGFuPjwvZGl2PjxkaXYgY2xhc3M9InBhc3N3b3JkLXN0cmVuZ3RoLWJhciIgc3R5bGU9Im1hcmdpbi10b3A6MC41cmVtO2hlaWdodDo0cHg7YmFja2dyb3VuZDojZTVlN2ViO2JvcmRlci1yYWRpdXM6MnB4O292ZXJmbG93OmhpZGRlbjsiPjxkaXYgY2xhc3M9InBhc3N3b3JkLXN0cmVuZ3RoLWZpbGwiIHN0eWxlPSJoZWlnaHQ6MTAwJTt3aWR0aDowJTt0cmFuc2l0aW9uOmFsbCAwLjNzIGVhc2U7Ij48L2Rpdj48L2Rpdj48ZGl2IGNsYXNzPSJwYXNzd29yZC1zdHJlbmd0aC1tZXNzYWdlIiBzdHlsZT0iZm9udC1zaXplOjAuNzVyZW07bWFyZ2luLXRvcDowLjI1cmVtO2hlaWdodDoxcmVtOyI+PC9kaXY+PHVsIGNsYXNzPSJwYXNzd29yZC1yZXF1aXJlbWVudHMiIHN0eWxlPSJmb250LXNpemU6MC43NXJlbTttYXJnaW4tdG9wOjAuNXJlbTtwYWRkaW5nLWxlZnQ6MXJlbTtjb2xvcjp2YXIoLS10ZXh0LXNlY29uZGFyeSk7Ij48bGkgZGF0YS1yZXE9Imxlbmd0aCIgc3R5bGU9Im1hcmdpbi1ib3R0b206MC4yNXJlbTsiPkF0IGxlYXN0IDggY2hhcmFjdGVyczwvbGk+PGxpIGRhdGEtcmVxPSJ1cHBlcmNhc2UiIHN0eWxlPSJtYXJnaW4tYm90dG9tOjAuMjVyZW07Ij5PbmUgdXBwZXJjYXNlIGxldHRlcjwvbGk+PGxpIGRhdGEtcmVxPSJsb3dlcmNhc2UiIHN0eWxlPSJtYXJnaW4tYm90dG9tOjAuMjVyZW07Ij5PbmUgbG93ZXJjYXNlIGxldHRlcjwvbGk+PGxpIGRhdGEtcmVxPSJudW1iZXIiIHN0eWxlPSJtYXJnaW4tYm90dG9tOjAuMjVyZW07Ij5PbmUgbnVtYmVyPC9saT48L3VsPjwvZGl2PjxkaXYgY2xhc3M9ImZvcm0tZ3JvdXAiIHN0eWxlPSJtYXJnaW4tYm90dG9tOjFyZW0iPjxsYWJlbCBmb3I9InJlZ19jb25maXJtX3Bhc3N3b3JkIiBjbGFzcz0iZm9ybS1sYWJlbCIgc3R5bGU9ImRpc3BsYXk6YmxvY2s7bWFyZ2luLWJvdHRvbTowLjVyZW07Zm9udC13ZWlnaHQ6NTAwIj5Db25maXJtIFBhc3N3b3JkPC9sYWJlbD48ZGl2IGNsYXNzPSJjb25maXJtLXBhc3N3b3JkLWlucHV0LXdyYXBwZXIiIHN0eWxlPSJwb3NpdGlvbjpyZWxhdGl2ZSI+PGlucHV0IHR5cGU9InBhc3N3b3JkIiBpZD0icmVnX2NvbmZpcm1fcGFzc3dvcmQiIG5hbWU9ImNvbmZpcm1fcGFzc3dvcmQiIGNsYXNzPSJmb3JtLWlucHV0IiBzdHlsZT0id2lkdGg6MTAwJTtwYWRkaW5nOjAuNjI1cmVtIDIuNXJlbSAwLjYyNXJlbSAwLjYyNXJlbTtib3JkZXI6MXB4IHNvbGlkIHZhcigtLWJvcmRlci1jb2xvcik7Ym9yZGVyLXJhZGl1czp2YXIoLS1yYWRpdXMtbWQpO2ZvbnQtc2l6ZTowLjg3NXJlbSIgcGxhY2Vob2xkZXI9IkNvbmZpcm0geW91ciBwYXNzd29yZCI+PHNwYW4gY2xhc3M9ImNvbmZpcm0tcGFzc3dvcmQtaWNvbiIgc3R5bGU9InBvc2l0aW9uOmFic29sdXRlO3JpZ2h0OjAuNzVyZW07dG9wOjUwJTt0cmFuc2Zvcm06dHJhbnNsYXRlWSgtNTAlKTtkaXNwbGF5Om5vbmU7d2lkdGg6MS4yNXJlbTtoZWlnaHQ6MS4yNXJlbTsiPjwvc3Bhbj48L2Rpdj48ZGl2IGNsYXNzPSJjb25maXJtLXBhc3N3b3JkLW1lc3NhZ2UiIHN0eWxlPSJmb250LXNpemU6MC43NXJlbTttYXJnaW4tdG9wOjAuMjVyZW07aGVpZ2h0OjFyZW07ZGlzcGxheTpub25lOyI+PC9kaXY+PC9kaXY+PGlucHV0IHR5cGU9ImhpZGRlbiIgbmFtZT0iYWN0aW9uIiB2YWx1ZT0icmVnaXN0ZXIiPjxpbnB1dCB0eXBlPSJoaWRkZW4iIG5hbWU9ImNzcmZfdG9rZW4iPjxidXR0b24gdHlwZT0ic3VibWl0IiBjbGFzcz0iYnRuIGJ0bi1wcmltYXJ5IHctZnVsbCIgaWQ9InJlZ1N1Ym1pdEJ0biI+PHNwYW4gY2xhc3M9ImJ0bi10ZXh0Ij5DcmVhdGUgQWNjb3VudDwvc3Bhbj48c3BhbiBjbGFzcz0ic3Bpbm5lciBzcGlubmVyLXNtIiBzdHlsZT0iZGlzcGxheTpub25lO3dpZHRoOjFyZW07aGVpZ2h0OjFyZW07Ym9yZGVyLXdpZHRoOjJweDsiPjwvc3Bhbj48L2J1dHRvbj48L2Zvcm0+Cg==';

  // Helper function to decode base64
  function decodeBase64(str) {
    return decodeURIComponent(escape(window.atob(str)));
  }

  // Make functions globally available
  window.showRegistrationForm = function() {
    var loginForm = document.querySelector('.login-form');
    if (loginForm) {
      loginForm.innerHTML = decodeBase64(REGISTER_FORM_HTML);
      var footer = document.querySelector('.login-footer');
      if (footer) {
        footer.innerHTML = '<p>Already have an account? <a href="#" onclick="showLoginForm();return false;">Sign in</a></p>';
      }
      var csrfInput = loginForm.querySelector('input[name="csrf_token"]');
      if (csrfInput) {
        var cookies = document.cookie.split(';');
        for (var i = 0; i < cookies.length; i++) {
          var cookie = cookies[i].trim();
          if (cookie.indexOf('csrf_token=') === 0) {
            csrfInput.value = cookie.substring('csrf_token='.length);
            break;
          }
        }
      }
      var inviteToken = getInviteToken();
      ensureInviteField(inviteToken);
      ensureInviteToken(inviteToken);
      handleInviteValidation(inviteToken);
      setupRegFormLoading();
      setupEmailValidation();
      setupPasswordValidation();
    }
  };
  
  // Email validation with visual feedback
  function setupEmailValidation() {
    var emailInput = document.getElementById('reg_email');
    var validationIcon = document.querySelector('.email-validation-icon');
    var validationMessage = document.querySelector('.email-validation-message');
    
    if (!emailInput) return;
    
    var validationTimer = null;
    
    function validateEmail(email) {
      if (!email || email.trim() === '') {
        return { valid: false, message: '', state: 'empty' };
      }
      
      // Basic email regex pattern
      var emailPattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
      
      if (!emailPattern.test(email)) {
        // Check what's wrong
        if (email.indexOf('@') === -1) {
          return { valid: false, message: 'Email must contain @', state: 'invalid' };
        }
        if (email.indexOf('@') === 0) {
          return { valid: false, message: 'Email must have a part before @', state: 'invalid' };
        }
        var parts = email.split('@');
        if (parts.length > 1 && parts[1].indexOf('.') === -1) {
          return { valid: false, message: 'Email domain must contain a dot', state: 'invalid' };
        }
        return { valid: false, message: 'Please enter a valid email address', state: 'invalid' };
      }
      
      return { valid: true, message: 'Valid email address', state: 'valid' };
    }
    
    function updateValidationUI(result) {
      if (!emailInput) return;
      
      var wrapper = emailInput.closest('.email-input-wrapper') || emailInput.parentElement;
      var icon = wrapper ? wrapper.querySelector('.email-validation-icon') : null;
      var msg = document.querySelector('.email-validation-message');
      
      // Reset classes
      emailInput.classList.remove('email-valid', 'email-invalid', 'email-checking');
      emailInput.style.borderColor = '';
      
      if (result.state === 'empty') {
        if (icon) icon.style.display = 'none';
        if (msg) {
          msg.style.display = 'none';
          msg.textContent = '';
        }
        emailInput.style.borderColor = '';
        return;
      }
      
      if (result.state === 'checking') {
        emailInput.classList.add('email-checking');
        emailInput.style.borderColor = '#3b82f6';
        if (icon) {
          icon.style.display = 'inline-block';
          icon.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2" style="animation: spin 1s linear infinite; width: 1rem; height: 1rem;"><circle cx="12" cy="12" r="10" stroke-dasharray="32" stroke-dashoffset="32"/></svg>';
        }
        if (msg) {
          msg.style.display = 'block';
          msg.style.color = '#3b82f6';
          msg.textContent = 'Checking...';
        }
        return;
      }
      
      if (result.valid) {
        emailInput.classList.add('email-valid');
        emailInput.style.borderColor = '#10b981';
        if (icon) {
          icon.style.display = 'inline-block';
          icon.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" style="width: 1rem; height: 1rem;"><path d="M20 6L9 17l-5-5"/></svg>';
        }
        if (msg) {
          msg.style.display = 'block';
          msg.style.color = '#10b981';
          msg.textContent = result.message;
        }
      } else {
        emailInput.classList.add('email-invalid');
        emailInput.style.borderColor = '#ef4444';
        if (icon) {
          icon.style.display = 'inline-block';
          icon.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" style="width: 1rem; height: 1rem;"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>';
        }
        if (msg) {
          msg.style.display = 'block';
          msg.style.color = '#ef4444';
          msg.textContent = result.message;
        }
      }
    }
    
    function handleEmailInput() {
      var email = emailInput.value;
      
      // Clear previous timer
      if (validationTimer) {
        clearTimeout(validationTimer);
      }
      
      // Show checking state immediately if there's content
      if (email.trim() !== '') {
        updateValidationUI({ state: 'checking' });
      }
      
      // Debounce validation
      validationTimer = setTimeout(function() {
        var result = validateEmail(email);
        updateValidationUI(result);
      }, 300);
    }
    
    // Add input event listener
    emailInput.addEventListener('input', handleEmailInput);
    
    // Also validate on blur
    emailInput.addEventListener('blur', function() {
      if (validationTimer) {
        clearTimeout(validationTimer);
      }
      var result = validateEmail(emailInput.value);
      updateValidationUI(result);
    });
  }
  
  // Password validation with strength indicator and confirmation
  function setupPasswordValidation() {
    var passwordInput = document.getElementById('reg_password');
    var confirmPasswordInput = document.getElementById('reg_confirm_password');
    var strengthBar = document.querySelector('.password-strength-bar');
    var strengthFill = document.querySelector('.password-strength-fill');
    var strengthMessage = document.querySelector('.password-strength-message');
    var confirmPasswordIcon = document.querySelector('.confirm-password-icon');
    var confirmPasswordMessage = document.querySelector('.confirm-password-message');
    var requirements = document.querySelectorAll('.password-requirements li');
    var toggleIcon = document.querySelector('.password-toggle-icon');
    
    if (!passwordInput) return;
    
    var validationTimer = null;
    
    // Password toggle visibility
    if (toggleIcon) {
      toggleIcon.addEventListener('click', function() {
        var type = passwordInput.type === 'password' ? 'text' : 'password';
        passwordInput.type = type;
        toggleIcon.innerHTML = type === 'password' 
          ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:1.25rem;height:1.25rem;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>'
          : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:1.25rem;height:1.25rem;"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.97 9.97 0 0112 4c7 0 11 8 11 8a18.45 18.45 0 01-5.06 5.94M1 1l22 22"/></svg>';
      });
    }
    
    function checkPasswordStrength(password) {
      var requirementsMet = {
        length: password.length >= 8,
        uppercase: /[A-Z]/.test(password),
        lowercase: /[a-z]/.test(password),
        number: /[0-9]/.test(password)
      };
      
      var score = 0;
      if (requirementsMet.length) score++;
      if (requirementsMet.uppercase) score++;
      if (requirementsMet.lowercase) score++;
      if (requirementsMet.number) score++;
      
      // Bonus for longer passwords
      if (password.length >= 12) score += 0.5;
      if (password.length >= 16) score += 0.5;
      
      // Bonus for special characters
      if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) score += 1;
      
      return {
        score: Math.min(score, 5),
        requirementsMet: requirementsMet,
        percentage: Math.min((score / 5) * 100, 100)
      };
    }
    
    function updateStrengthUI(result) {
      // Update requirements list
      requirements.forEach(function(li) {
        var req = li.getAttribute('data-req');
        if (result.requirementsMet[req]) {
          li.style.color = '#10b981';
          li.innerHTML = li.innerHTML.replace(/^[-•]\s*/, '') + ' <span style="color:#10b981;">✓</span>';
        } else {
          li.style.color = 'var(--text-secondary)';
          // Remove checkmark if present
          li.innerHTML = li.innerHTML.replace(/\s*<span[^>]*>✓<\/span>/g, '');
        }
      });
      
      // Update strength bar
      if (strengthBar && strengthFill) {
        strengthBar.style.display = 'block';
        strengthFill.style.width = result.percentage + '%';
        
        if (result.percentage <= 25) {
          strengthFill.style.backgroundColor = '#ef4444';
        } else if (result.percentage <= 50) {
          strengthFill.style.backgroundColor = '#f59e0b';
        } else if (result.percentage <= 75) {
          strengthFill.style.backgroundColor = '#3b82f6';
        } else {
          strengthFill.style.backgroundColor = '#10b981';
        }
      }
      
      // Update strength message
      if (strengthMessage) {
        strengthMessage.style.display = 'block';
        if (result.percentage <= 25) {
          strengthMessage.textContent = 'Weak password';
          strengthMessage.style.color = '#ef4444';
        } else if (result.percentage <= 50) {
          strengthMessage.textContent = 'Fair password';
          strengthMessage.style.color = '#f59e0b';
        } else if (result.percentage <= 75) {
          strengthMessage.textContent = 'Good password';
          strengthMessage.style.color = '#3b82f6';
        } else {
          strengthMessage.textContent = 'Strong password';
          strengthMessage.style.color = '#10b981';
        }
      }
    }
    
    function checkPasswordMatch() {
      if (!confirmPasswordInput || !confirmPasswordMessage) return;
      
      var password = passwordInput.value;
      var confirmPassword = confirmPasswordInput.value;
      
      if (confirmPassword === '') {
        confirmPasswordMessage.style.display = 'none';
        if (confirmPasswordIcon) confirmPasswordIcon.style.display = 'none';
        confirmPasswordInput.style.borderColor = '';
        return;
      }
      
      confirmPasswordMessage.style.display = 'block';
      
      if (password === confirmPassword) {
        confirmPasswordMessage.textContent = 'Passwords match';
        confirmPasswordMessage.style.color = '#10b981';
        confirmPasswordInput.style.borderColor = '#10b981';
        if (confirmPasswordIcon) {
          confirmPasswordIcon.style.display = 'inline-block';
          confirmPasswordIcon.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" style="width:1rem;height:1rem;"><path d="M20 6L9 17l-5-5"/></svg>';
        }
      } else {
        confirmPasswordMessage.textContent = 'Passwords do not match';
        confirmPasswordMessage.style.color = '#ef4444';
        confirmPasswordInput.style.borderColor = '#ef4444';
        if (confirmPasswordIcon) {
          confirmPasswordIcon.style.display = 'inline-block';
          confirmPasswordIcon.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" style="width:1rem;height:1rem;"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>';
        }
      }
    }
    
    function handlePasswordInput() {
      var password = passwordInput.value;
      
      if (validationTimer) {
        clearTimeout(validationTimer);
      }
      
      if (password === '') {
        if (strengthBar) strengthBar.style.display = 'none';
        if (strengthMessage) strengthMessage.style.display = 'none';
        requirements.forEach(function(li) {
          li.style.color = 'var(--text-secondary)';
          li.innerHTML = li.innerHTML.replace(/\s*<span[^>]*>✓<\/span>/g, '');
        });
        return;
      }
      
      validationTimer = setTimeout(function() {
        var result = checkPasswordStrength(password);
        updateStrengthUI(result);
        checkPasswordMatch();
      }, 150);
    }
    
    passwordInput.addEventListener('input', handlePasswordInput);
    
    if (confirmPasswordInput) {
      confirmPasswordInput.addEventListener('input', checkPasswordMatch);
    }
  }
  
  // Setup loading animation for registration form
  function setupRegFormLoading() {
    var form = document.getElementById('registerForm');
    if (!form) return;
    form.addEventListener('submit', function(e) {
      var btn = document.getElementById('regSubmitBtn');
      if (!btn) return;
      var btnText = btn.querySelector('.btn-text');
      var spinner = btn.querySelector('.spinner');
      if (btn.disabled) return;
      btn.disabled = true;
      btn.style.opacity = '0.8';
      btn.style.cursor = 'wait';
      if (btnText) btnText.style.opacity = '0';
      if (spinner) spinner.style.display = 'inline-block';
    });
  }
  
  window.showLoginForm = function() {
    window.location.href = '/login';
  };
})();
