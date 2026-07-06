// Golden Route Myanmar - UI helpers (non-breaking)
// No PHP logic changes. Safe progressive enhancement.

document.addEventListener('DOMContentLoaded', () => {
  // Enable Bootstrap tooltips if present
  try {
    if (window.bootstrap) {
      document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
        new bootstrap.Tooltip(el);
      });
    }
  } catch (e) {}

  // Auto-dismiss alerts that opt-in (add class: alert-auto)
  document.querySelectorAll('.alert.alert-auto').forEach((el) => {
    setTimeout(() => {
      try {
        if (window.bootstrap) {
          const alert = bootstrap.Alert.getOrCreateInstance(el);
          alert.close();
        } else {
          el.remove();
        }
      } catch (e) {}
    }, 4200);
  });

  // Smooth scroll for in-page anchors
  document.querySelectorAll('a[href^="#"]').forEach((a) => {
    a.addEventListener('click', (ev) => {
      const id = a.getAttribute('href');
      if (!id || id === '#') return;
      const target = document.querySelector(id);
      if (!target) return;
      ev.preventDefault();
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  });
});
// Golden Route Myanmar - UI helpers (non-breaking)
// No PHP logic changes. Safe progressive enhancement.
document.addEventListener('DOMContentLoaded', function () {
  try {
    if (window.bootstrap) {
      document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
      });
    }
  } catch (e) {}

  document.querySelectorAll('.alert.alert-auto').forEach(function (el) {
    setTimeout(function () {
      try {
        if (window.bootstrap) {
          const alert = bootstrap.Alert.getOrCreateInstance(el);
          alert.close();
        } else {
          el.remove();
        }
      } catch (e) {}
    }, 4200);
  });

  document.querySelectorAll('a[href^="#"]').forEach(function (a) {
    a.addEventListener('click', function (ev) {
      const id = a.getAttribute('href');
      if (!id || id === '#') return;
      const target = document.querySelector(id);
      if (!target) return;
      ev.preventDefault();
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  });

  document.querySelectorAll('[data-company-slider]').forEach(function (sliderRoot) {
    const cards = Array.from(sliderRoot.querySelectorAll('.bus-showcase-card'));
    const prevBtn = sliderRoot.querySelector('[data-slider-prev]');
    const nextBtn = sliderRoot.querySelector('[data-slider-next]');
    const dotsWrap = sliderRoot.querySelector('[data-slider-dots]');

    if (!cards.length) return;

    let activeIndex = 0;
    const total = cards.length;
    let autoTimer = null;

    function normalizeDiff(diff) {
      const half = Math.floor(total / 2);
      if (diff > half) diff -= total;
      if (diff < -half) diff += total;
      return diff;
    }

    function buildDots() {
      if (!dotsWrap) return;
      dotsWrap.innerHTML = '';

      cards.forEach(function (_, index) {
        const dot = document.createElement('button');
        dot.type = 'button';
        dot.className = 'bus-showcase-dot';
        dot.setAttribute('aria-label', 'Go to company ' + (index + 1));
        dot.addEventListener('click', function () {
          activeIndex = index;
          render();
          restartAuto();
        });
        dotsWrap.appendChild(dot);
      });
    }

    function render() {
      cards.forEach(function (card, index) {
        const diff = normalizeDiff(index - activeIndex);

        card.classList.remove('is-active', 'is-prev', 'is-next', 'is-far-prev', 'is-far-next');

        if (diff === 0) {
          card.classList.add('is-active');
        } else if (diff === -1) {
          card.classList.add('is-prev');
        } else if (diff === 1) {
          card.classList.add('is-next');
        } else if (diff === -2) {
          card.classList.add('is-far-prev');
        } else if (diff === 2) {
          card.classList.add('is-far-next');
        }
      });

      if (dotsWrap) {
        Array.from(dotsWrap.children).forEach(function (dot, index) {
          dot.classList.toggle('active', index === activeIndex);
        });
      }
    }

    function goNext() {
      activeIndex = (activeIndex + 1) % total;
      render();
    }

    function goPrev() {
      activeIndex = (activeIndex - 1 + total) % total;
      render();
    }

    function startAuto() {
      if (total <= 1) return;
      stopAuto();
      autoTimer = setInterval(goNext, 4500);
    }

    function stopAuto() {
      if (autoTimer) {
        clearInterval(autoTimer);
        autoTimer = null;
      }
    }

    function restartAuto() {
      stopAuto();
      startAuto();
    }

    buildDots();
    render();
    startAuto();

    if (prevBtn) {
      prevBtn.addEventListener('click', function () {
        goPrev();
        restartAuto();
      });
    }

    if (nextBtn) {
      nextBtn.addEventListener('click', function () {
        goNext();
        restartAuto();
      });
    }

    sliderRoot.addEventListener('mouseenter', stopAuto);
    sliderRoot.addEventListener('mouseleave', startAuto);
  });
});

// Login form validation and password visibility toggle
document.addEventListener('DOMContentLoaded', function () {
  function setFieldState(input, messageEl, isValid, message) {
    if (!input) return;
    input.classList.remove('is-valid', 'is-invalid');
    if (isValid === true) input.classList.add('is-valid');
    if (isValid === false) input.classList.add('is-invalid');

    if (messageEl) {
      messageEl.classList.remove('is-valid', 'is-invalid');
      if (isValid === true) messageEl.classList.add('is-valid');
      if (isValid === false) messageEl.classList.add('is-invalid');
      messageEl.textContent = message || '';
    }
  }

  function isValidEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value);
  }

  function updateRule(rule, state) {
    if (!rule) return;
    const icon = rule.querySelector('i');
    rule.classList.remove('is-valid', 'is-invalid');

    if (state === true) {
      rule.classList.add('is-valid');
      if (icon) icon.className = 'bi bi-check-circle-fill';
      return;
    }

    if (state === false) {
      rule.classList.add('is-invalid');
      if (icon) icon.className = 'bi bi-x-circle-fill';
      return;
    }

    if (icon) icon.className = 'bi bi-circle';
  }

  document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
    const targetSelector = button.getAttribute('data-target');
    const input = targetSelector ? document.querySelector(targetSelector) : (button.closest('.auth-input-wrap') ? button.closest('.auth-input-wrap').querySelector('input') : null);
    const icon = button.querySelector('i');

    if (!input) return;

    button.addEventListener('click', function () {
      const showPassword = input.type === 'password';
      input.type = showPassword ? 'text' : 'password';
      button.setAttribute('aria-pressed', showPassword ? 'true' : 'false');
      button.setAttribute('aria-label', showPassword ? 'Hide password' : 'Show password');

      if (icon) {
        icon.className = showPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
      }

      input.focus();
    });
  });

  document.querySelectorAll('[data-login-validation]').forEach(function (form) {
    const emailInput = form.querySelector('[data-login-email]');
    const passwordInput = form.querySelector('[data-login-password]');
    const emailMessage = form.querySelector('[data-email-message]');
    const passwordMessage = form.querySelector('[data-password-message]');
    function validateEmail(showEmptyMessage) {
      if (!emailInput) return true;
      const value = emailInput.value.trim();

      if (value === '') {
        setFieldState(emailInput, emailMessage, showEmptyMessage ? false : null, showEmptyMessage ? 'Email address is required.' : '');
        return false;
      }

      if (!isValidEmail(value)) {
        setFieldState(emailInput, emailMessage, false, 'Invalid email format. Example: name@example.com');
        return false;
      }

      setFieldState(emailInput, emailMessage, true, 'Email format looks good.');
      return true;
    }

    function validatePassword(showEmptyMessage) {
      if (!passwordInput) return true;
      const value = passwordInput.value;
      const hasValue = value.length > 0;

      if (!hasValue) {
        setFieldState(passwordInput, passwordMessage, showEmptyMessage ? false : null, showEmptyMessage ? 'Password is required.' : '');
        return false;
      }

      setFieldState(passwordInput, passwordMessage, true, '');
      return true;
    }

    if (emailInput) {
      emailInput.addEventListener('input', function () {
        validateEmail(false);
      });
      emailInput.addEventListener('blur', function () {
        validateEmail(true);
      });
    }

    if (passwordInput) {
      passwordInput.addEventListener('input', function () {
        validatePassword(false);
      });
      passwordInput.addEventListener('blur', function () {
        validatePassword(true);
      });
    }

    validatePassword(false);

    form.addEventListener('submit', function (event) {
      const emailValid = validateEmail(true);
      const passwordValid = validatePassword(true);

      if (!emailValid || !passwordValid) {
        event.preventDefault();
        const firstInvalid = form.querySelector('.auth-control.is-invalid');
        if (firstInvalid) firstInvalid.focus();
      }
    });
  });
});

// Register form validation for Unit Test 2
// Covers full name, email, phone number, password confirmation and responsive password visibility toggle.
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-register-validation]').forEach(function (form) {
    const nameInput = form.querySelector('[data-register-name]');
    const emailInput = form.querySelector('[data-register-email]');
    const phoneInput = form.querySelector('[data-register-phone]');
    const passwordInput = form.querySelector('[data-register-password]');
    const confirmInput = form.querySelector('[data-register-confirm-password]');

    const nameMessage = form.querySelector('[data-name-message]');
    const emailMessage = form.querySelector('[data-email-message]');
    const phoneMessage = form.querySelector('[data-phone-message]');
    const passwordMessage = form.querySelector('[data-password-message]');
    const confirmMessage = form.querySelector('[data-confirm-password-message]');
    const passwordRuleElements = form.querySelectorAll('[data-password-rule]');

    function setFieldState(input, messageEl, state, message) {
      if (!input) return;
      input.classList.remove('is-valid', 'is-invalid');

      if (state === true) input.classList.add('is-valid');
      if (state === false) input.classList.add('is-invalid');

      if (messageEl) {
        messageEl.classList.remove('is-valid', 'is-invalid');
        if (state === true && message) messageEl.classList.add('is-valid');
        if (state === false) messageEl.classList.add('is-invalid');
        messageEl.textContent = message || '';
      }
    }

    function validEmail(value) {
      return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value);
    }

    function validName(value) {
      return /^[\p{L}\s.'-]+$/u.test(value);
    }

    function validateName(showMessage) {
      if (!nameInput) return true;
      const value = nameInput.value.trim();

      if (value === '') {
        setFieldState(nameInput, nameMessage, showMessage ? false : null, showMessage ? 'Full Name field is required.' : '');
        return false;
      }

      if (!validName(value)) {
        setFieldState(nameInput, nameMessage, false, 'Full Name should contain letters only.');
        return false;
      }

      setFieldState(nameInput, nameMessage, true, 'Full Name is accepted.');
      return true;
    }

    function validateEmail(showMessage) {
      if (!emailInput) return true;
      const value = emailInput.value.trim();

      if (value === '') {
        setFieldState(emailInput, emailMessage, showMessage ? false : null, showMessage ? 'Email field is required.' : '');
        return false;
      }

      if (!validEmail(value)) {
        setFieldState(emailInput, emailMessage, false, 'Please enter a valid email address.');
        return false;
      }

      setFieldState(emailInput, emailMessage, true, 'Email address is accepted.');
      return true;
    }

    function validatePhone(showMessage) {
      if (!phoneInput) return true;
      const value = phoneInput.value.trim();

      if (value === '') {
        setFieldState(phoneInput, phoneMessage, showMessage ? false : null, showMessage ? 'Phone Number field is required.' : '');
        return false;
      }

      if (!/^[0-9]+$/.test(value)) {
        setFieldState(phoneInput, phoneMessage, false, 'Phone Number should contain numbers only.');
        return false;
      }

      if (!/^09[0-9]{7,9}$/.test(value)) {
        setFieldState(phoneInput, phoneMessage, false, 'Please enter a valid phone number.');
        return false;
      }

      setFieldState(phoneInput, phoneMessage, true, 'Phone Number is accepted.');
      return true;
    }

    function updatePasswordRules(value, showInvalid) {
      if (!passwordRuleElements || passwordRuleElements.length === 0) return;

      const checks = {
        length: value.length >= 8,
        letter: /[A-Za-z]/.test(value),
        number: /[0-9]/.test(value)
      };

      passwordRuleElements.forEach(function (rule) {
        const ruleName = rule.getAttribute('data-password-rule');
        const passed = Boolean(checks[ruleName]);
        const icon = rule.querySelector('i');

        rule.classList.toggle('is-valid', passed);
        rule.classList.toggle('is-invalid', showInvalid && !passed);

        if (icon) {
          if (passed) {
            icon.className = 'bi bi-check-circle-fill';
          } else if (showInvalid) {
            icon.className = 'bi bi-x-circle-fill';
          } else {
            icon.className = 'bi bi-circle';
          }
        }
      });
    }

    function validatePassword(showMessage) {
      if (!passwordInput) return true;
      const value = passwordInput.value;

      if (value === '') {
        updatePasswordRules(value, showMessage);
        setFieldState(passwordInput, passwordMessage, showMessage ? false : null, showMessage ? 'Password field is required.' : '');
        return false;
      }

      const checks = [
        { passed: value.length >= 8, message: 'Password must be at least 8 characters.' },
        { passed: /[A-Za-z]/.test(value), message: 'Password must contain at least one letter.' },
        { passed: /[0-9]/.test(value), message: 'Password must contain at least one number.' }
      ];
      const failedCheck = checks.find(function (check) { return !check.passed; });

      updatePasswordRules(value, Boolean(failedCheck));

      if (failedCheck) {
        setFieldState(passwordInput, passwordMessage, false, failedCheck.message);
        return false;
      }

      setFieldState(passwordInput, passwordMessage, true, 'Password is accepted.');
      return true;
    }

    function validateConfirmPassword(showMessage) {
      if (!confirmInput) return true;
      const value = confirmInput.value;
      const passwordValue = passwordInput ? passwordInput.value : '';

      if (value === '') {
        setFieldState(confirmInput, confirmMessage, showMessage ? false : null, showMessage ? 'Confirm Password field is required.' : '');
        return false;
      }

      if (passwordValue !== '' && value !== passwordValue) {
        setFieldState(confirmInput, confirmMessage, false, 'Passwords do not match.');
        return false;
      }

      setFieldState(confirmInput, confirmMessage, true, 'Passwords match.');
      return true;
    }

    if (nameInput) {
      nameInput.addEventListener('input', function () { validateName(false); });
      nameInput.addEventListener('blur', function () { validateName(true); });
    }

    if (emailInput) {
      emailInput.addEventListener('input', function () { validateEmail(false); });
      emailInput.addEventListener('blur', function () { validateEmail(true); });
    }

    if (phoneInput) {
      phoneInput.addEventListener('input', function () { validatePhone(false); });
      phoneInput.addEventListener('blur', function () { validatePhone(true); });
    }

    if (passwordInput) {
      passwordInput.addEventListener('input', function () {
        validatePassword(false);
        if (confirmInput && confirmInput.value !== '') validateConfirmPassword(true);
      });
      passwordInput.addEventListener('blur', function () { validatePassword(true); });
    }

    if (confirmInput) {
      confirmInput.addEventListener('input', function () { validateConfirmPassword(false); });
      confirmInput.addEventListener('blur', function () { validateConfirmPassword(true); });
    }

    form.addEventListener('submit', function (event) {
      const isNameValid = validateName(true);
      const isEmailValid = validateEmail(true);
      const isPhoneValid = validatePhone(true);
      const isPasswordValid = validatePassword(true);
      const isConfirmValid = validateConfirmPassword(true);

      if (!isNameValid || !isEmailValid || !isPhoneValid || !isPasswordValid || !isConfirmValid) {
        event.preventDefault();
        const firstInvalid = form.querySelector('.auth-control.is-invalid');
        if (firstInvalid) firstInvalid.focus();
      }
    });
  });
});
