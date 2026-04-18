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