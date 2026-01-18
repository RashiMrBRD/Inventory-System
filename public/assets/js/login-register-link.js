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
  var REGISTER_FORM_HTML = 'PGZvcm0gbWV0aG9kPSJQT1NUIiBhY3Rpb249IiI+PGRpdiBzdHlsZT0ibWFyZ2luLWJvdHRvbToxLjVyZW0iPjxoMyBzdHlsZT0iZm9udC1zaXplOjEuMTI1cm07Zm9udC13ZWlnaHQ6NjAwO21hcmdpbjowIDAgMC41cm0gMCI+Q3JlYXRlIEFjY291bnQ8L2gzPjxwIHN0eWxlPSJmb250LXNpemU6MC44NzVyZW07Y29sb3I6dmFyKC0tdGV4dC1zZWNvbmRhcnkpO21hcmdpbjowIj5GaWxsIGluIHRoZSBkZXRhaWxzIHRvIGNyZWF0ZSB5b3VyIGFjY291bnQ8L3A+PC9kaXY+PGRpdiBjbGFzcz0iZm9ybS1ncm91cCIgc3R5bGU9Im1hcmdpbi1ib3R0b206MXJlbSI+PGxhYmVsIGZvcj0icmVnX2VtYWlsIiBjbGFzcz0iZm9ybS1sYWJlbCIgc3R5bGU9ImRpc3BsYXk6YmxvY2s7bWFyZ2luLWJvdHRvbTowLjVyZW07Zm9udC13ZWlnaHQ6NTAwIj5FbWFpbDwvbGFiZWw+PGlucHV0IHR5cGU9ImVtYWlsIiBpZD0icmVnX2VtYWlsIiBuYW1lPSJlbWFpbCIgY2xhc3M9ImZvcm0taW5wdXQiIHN0eWxlPSJ3aWR0aDoxMDAlO3BhZGRpbmc6MC42MjVyZW07Ym9yZGVyOjFweCBzb2xpZCB2YXIoLS1ib3JkZXItY29sb3IpO2JvcmRlci1yYWRpdXM6dmFyKC0tcmFkaXVzLW1kKTtmb250LXNpemU6MC44NzVyZW0iIHBsYWNlaG9sZGVyPSJFbnRlciB5b3VyIGVtYWlsIiByZXF1aXJlZD48L2Rpdj48ZGl2IGNsYXNzPSJmb3JtLWdyb3VwIiBzdHlsZT0ibWFyZ2luLWJvdHRvbToxcmVtIj48bGFiZWwgZm9yPSJyZWdfdXNlcm5hbWUiIGNsYXNzPSJmb3JtLWxhYmVsIiBzdHlsZT0iZGlzcGxheTpibG9jazttYXJnaW4tYm90dG9tOjAuNXJlbTtmb250LXdlaWdodDo1MDAiPlVzZXJuYW1lPC9sYWJlbD48aW5wdXQgdHlwZT0idGV4dCIgaWQ9InJlZ191c2VybmFtZSIgbmFtZT0idXNlcm5hbWUiIGNsYXNzPSJmb3JtLWlucHV0IiBzdHlsZT0id2lkdGg6MTAwJTtwYWRkaW5nOjAuNjI1cmVtO2JvcmRlcjFweCBzb2xpZCB2YXIoLS1ib3JkZXItY29sb3IpO2JvcmRlci1yYWRpdXM6dmFyKC0tcmFkaXVzLW1kKTtmb250LXNpemU6MC44NzVyZW0iIHBsYWNlaG9sZGVyPSJDaG9vc2UgYSB1c2VybmFtZSIgcmVxdWlyZWQ+PC9kaXY+PGRpdiBjbGFzcz0iZm9ybS1ncm91cCIgc3R5bGU9Im1hcmdpbi1ib3R0b206MXJlbSI+PGxhYmVsIGZvcj0icmVnX3Bhc3N3b3JkIiBjbGFzcz0iZm9ybS1sYWJlbCIgc3R5bGU9ImRpc3BsYXk6YmxvY2s7bWFyZ2luLWJvdHRvbTowLjVyZW07Zm9udC13ZWlnaHQ6NTAwIj5QYXNzd29yZDwvbGFiZWw+PGlucHV0IHR5cGU9InBhc3N3b3JkIiBpZD0icmVnX3Bhc3N3b3JkIiBuYW1lPSJwYXNzd29yZCIgY2xhc3M9ImZvcm0taW5wdXQiIHN0eWxlPSJ3aWR0aDoxMDAlO3BhZGRpbmc6MC42MjVyZW07Ym9yZGVyOjFweCBzb2xpZCB2YXIoLS1ib3JkZXItY29sb3IpO2JvcmRlci1yYWRpdXM6dmFyKC0tcmFkaXVzLW1kKTtmb250LXNpemU6MC44NzVyZW0iIHBsYWNlaG9sZGVyPSJDcmVhdGUgYSBwYXNzd29yZCIgcmVxdWlyZWQ+PC9kaXY+PGRpdiBjbGFzcz0iZm9ybS1ncm91cCIgc3R5bGU9Im1hcmdpbi1ib3R0b206MS41cmVtIj48bGFiZWwgZm9yPSJyZWdfY29uZmlybV9wYXNzd29yZCIgY2xhc3M9ImZvcm0tbGFiZWwiIHN0eWxlPSJkaXNwbGF5OmJsb2NrO21hcmdpbi1ib3R0b206MC41cmVtO2ZvbnQtd2VpZ2h0OjUwMCI+Q29uZmlybSBQYXNzd29yZDwvbGFiZWw+PGlucHV0IHR5cGU9InBhc3N3b3JkIiBpZD0icmVnX2NvbmZpcm1fcGFzc3dvcmQiIG5hbWU9ImNvbmZpcm1fcGFzc3dvcmQiIGNsYXNzPSJmb3JtLWlucHV0IiBzdHlsZT0id2lkdGg6MTAwJTtwYWRkaW5nOjAuNjI1cmVtO2JvcmRlcjFweCBzb2xpZCB2YXIoLS1ib3JkZXItY29sb3IpO2JvcmRlci1yYWRpdXM6dmFyKC0tcmFkaXVzLW1kKTtmb250LXNpemU6MC44NzVyZW0iIHBsYWNlaG9sZGVyPSJDb25maXJtIHlvdXIgcGFzc3dvcmQiIHJlcXVpcmVkPjwvZGl2PjxpbnB1dCB0eXBlPSJoaWRkZW4iIG5hbWU9ImFjdGlvbiIgdmFsdWU9InJlZ2lzdGVyIj48aW5wdXQgdHlwZT0iaGlkZGVuIiBuYW1lPSJjc3JmX3Rva2VuIiB2YWx1ZT0iIj48YnV0dG9uIHR5cGU9InN1Ym1pdCIgY2xhc3M9ImJ0biBidG4tcHJpbWFyeSIgc3R5bGU9IndpZHRoOjEwMCU7cGFkZGluZzowLjc1cmVtO2JhY2tncm91bmQ6dmFyKC0tY29sb3ItcHJpbWFyeSk7Y29sb3I6I2ZmZjtib3JkZXI6bm9uZTtib3JkZXItcmFkaXVzOnZhcigtLXJhZGl1cy1tZCk7Zm9udC1zaXplOjAuODc1cmVtO2ZvbnQtd2VpZ2h0OjUwMDtjdXJzb3I6cG9pbnRlciI+Q3JlYXRlIEFjY291bnQ8L2J1dHRvbj48L2Zvcm0+';

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
    }
  };
  
  window.showLoginForm = function() {
    window.location.href = '/login';
  };
})();
