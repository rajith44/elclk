(function () {
  'use strict';

  var config = window.ssStoreSelector || {};
  var switchUrl = config.switchUrl || '';

  function selectRegion(region, button) {
    if (!region || !switchUrl) {
      return;
    }

    var loading = document.getElementById('ss-loading');
    var cards = document.querySelectorAll('.ss-region-card');

    if (loading) {
      loading.hidden = false;
    }

    cards.forEach(function (card) {
      card.disabled = true;
    });

    if (button) {
      button.classList.add('is-loading');
    }

    var body = new URLSearchParams();
    body.append('region', region);

    fetch(switchUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: body.toString(),
      credentials: 'same-origin'
    })
      .then(function (response) {
        return response.json();
      })
      .then(function (json) {
        if (json.redirect) {
          window.location.href = json.redirect;
          return;
        }

        throw new Error(json.error || 'Unable to switch store');
      })
      .catch(function () {
        if (loading) {
          loading.hidden = true;
        }

        cards.forEach(function (card) {
          card.disabled = false;
        });

        if (button) {
          button.classList.remove('is-loading');
        }
      });
  }

  document.querySelectorAll('.ss-region-card').forEach(function (card) {
    card.addEventListener('click', function () {
      selectRegion(card.getAttribute('data-region'), card);
    });
  });

  document.querySelectorAll('.ss-switcher__option').forEach(function (option) {
    option.addEventListener('click', function () {
      if (option.classList.contains('is-active')) {
        closeSwitcherPanel();
        return;
      }

      selectRegion(option.getAttribute('data-region'), option);
    });
  });

  var toggle = document.getElementById('ss-switcher-toggle');
  var panel = document.getElementById('ss-switcher-panel');

  function closeSwitcherPanel() {
    if (!toggle || !panel) {
      return;
    }

    toggle.setAttribute('aria-expanded', 'false');
    panel.hidden = true;
  }

  function openSwitcherPanel() {
    if (!toggle || !panel) {
      return;
    }

    toggle.setAttribute('aria-expanded', 'true');
    panel.hidden = false;
  }

  if (toggle && panel) {
    toggle.addEventListener('click', function (event) {
      event.stopPropagation();

      if (panel.hidden) {
        openSwitcherPanel();
      } else {
        closeSwitcherPanel();
      }
    });

    document.addEventListener('click', function (event) {
      if (!panel.hidden && !event.target.closest('.ss-switcher')) {
        closeSwitcherPanel();
      }
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        closeSwitcherPanel();
      }
    });
  }
})();
