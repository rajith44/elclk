(function () {
  'use strict';

  var config = window.csContactConfig || {};
  var modal = document.getElementById('cs-contact-modal');
  var form = document.getElementById('cs-contact-form');
  var alertBox = document.getElementById('cs-modal-alert');
  var submitBtn = document.getElementById('cs-form-submit');
  var storeInput = document.getElementById('cs-form-store');
  var sendingFromInput = document.getElementById('cs-form-sending-from');
  var titleEl = document.getElementById('cs-modal-title-text');
  var eyebrowEl = document.getElementById('cs-modal-eyebrow-text');
  var mapEl = document.getElementById('cs-contact-map');
  var mapInstance = null;
  var markerLayers = {};

  if (!modal || !form || !config.send) {
    return;
  }

  function refreshCaptcha() {
    var captchaInput = form.querySelector('input[name="captcha"]');
    var captchaImg = form.querySelector('#captcha img');

    if (captchaInput) {
      captchaInput.value = '';
    }

    if (captchaImg && config.captchaUrl) {
      captchaImg.src = config.captchaUrl + (config.captchaUrl.indexOf('?') > -1 ? '&' : '?') + 'r=' + Date.now();
    }
  }

  function openModal(store, storeName, buttonLabel, sendingFrom) {
    alertBox.hidden = true;
    alertBox.className = 'cs-modal__alert';
    form.reset();
    storeInput.value = store;

    if (sendingFromInput) {
      sendingFromInput.value = sendingFrom || '';
    }

    document.getElementById('cs-form-name').value = form.dataset.defaultName || '';
    document.getElementById('cs-form-email').value = form.dataset.defaultEmail || '';

    if (titleEl) {
      titleEl.textContent = storeName;
    }

    if (eyebrowEl) {
      eyebrowEl.textContent = buttonLabel;
    }

    refreshCaptcha();
    modal.hidden = false;
    document.documentElement.classList.add('cs-modal-open');
    document.getElementById('cs-form-name').focus();
  }

  function closeModal() {
    modal.hidden = true;
    document.documentElement.classList.remove('cs-modal-open');
  }

  function showAlert(message, type) {
    alertBox.hidden = false;
    alertBox.className = 'cs-modal__alert is-' + type;
    alertBox.textContent = message;
  }

  function initMap() {
    if (!mapEl || !window.L || !config.markers || !config.markers.length) {
      return;
    }

    mapInstance = L.map(mapEl, {
      scrollWheelZoom: false
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(mapInstance);

    var bounds = [];

    config.markers.forEach(function (marker) {
      var latLng = [marker.latitude, marker.longitude];
      var layer = L.marker(latLng).addTo(mapInstance);
      var popupHtml = '<strong>' + marker.name + '</strong>';

      if (marker.address) {
        popupHtml += '<br>' + marker.address;
      }

      if (marker.maps_link) {
        popupHtml += '<br><a href="' + marker.maps_link + '" target="_blank" rel="noopener">Get directions</a>';
      }

      layer.bindPopup(popupHtml);
      markerLayers[marker.code] = layer;
      bounds.push(latLng);
    });

    if (bounds.length === 1) {
      mapInstance.setView(bounds[0], 14);
    } else if (bounds.length > 1) {
      mapInstance.fitBounds(bounds, { padding: [48, 48] });
    }

    document.querySelectorAll('[data-cs-map-focus]').forEach(function (button) {
      button.addEventListener('click', function () {
        var code = button.getAttribute('data-cs-map-focus');
        var layer = markerLayers[code];

        if (!layer || !mapInstance) {
          return;
        }

        mapInstance.setView(layer.getLatLng(), 15, { animate: true });
        layer.openPopup();
      });
    });
  }

  document.querySelectorAll('[data-cs-open-modal]').forEach(function (button) {
    button.addEventListener('click', function () {
      openModal(
        button.getAttribute('data-store'),
        button.getAttribute('data-store-name'),
        button.getAttribute('data-store-label'),
        button.getAttribute('data-sending-from')
      );
    });
  });

  modal.querySelectorAll('[data-cs-close-modal]').forEach(function (el) {
    el.addEventListener('click', closeModal);
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && !modal.hidden) {
      closeModal();
    }
  });

  form.addEventListener('click', function (event) {
    if (event.target && event.target.closest('#captcha img')) {
      refreshCaptcha();
    }
  });

  form.addEventListener('submit', function (event) {
    event.preventDefault();

    alertBox.hidden = true;
    submitBtn.disabled = true;

    var body = new URLSearchParams(new FormData(form));

    fetch(config.send, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: body.toString()
    })
      .then(function (response) {
        return response.text().then(function (text) {
          try {
            return JSON.parse(text);
          } catch (error) {
            throw new Error('Unexpected server response.');
          }
        });
      })
      .then(function (json) {
        if (json.error) {
          var messages = [];

          Object.keys(json.error).forEach(function (key) {
            messages.push(json.error[key]);
          });

          showAlert(messages.join(' '), 'error');
          refreshCaptcha();
          return;
        }

        if (json.success) {
          showAlert(json.success, 'success');
          form.querySelector('textarea[name="enquiry"]').value = '';

          window.setTimeout(closeModal, 1800);
        }
      })
      .catch(function () {
        showAlert('Your message could not be sent. Please try again.', 'error');
        refreshCaptcha();
      })
      .finally(function () {
        submitBtn.disabled = false;
      });
  });

  form.dataset.defaultName = document.getElementById('cs-form-name').value;
  form.dataset.defaultEmail = document.getElementById('cs-form-email').value;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMap);
  } else {
    initMap();
  }
})();
