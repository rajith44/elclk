(function () {
  'use strict';

  var config = window.snStockNotify || {};
  var subscribeUrl = config.subscribeUrl || '';
  var customer = config.customer || {};
  var labels = config.labels || {};

  var trigger = document.getElementById('sn-notify-trigger');
  var modal = document.getElementById('sn-notify-modal');
  var form = document.getElementById('sn-notify-form');
  var alertBox = document.getElementById('sn-notify-alert');
  var notifyEl = document.getElementById('sn-notify');
  var productNameEl = document.getElementById('sn-notify-product-name');
  var productIdInput = document.getElementById('sn-notify-product-id');
  var introEl = document.getElementById('sn-notify-intro');

  var envelopeIcon = '<i class="fa fa-envelope sn-card-notify__icon" aria-hidden="true"></i>';

  var defaultValues = {
    name: customer.name || '',
    email: customer.email || '',
    telephone: customer.telephone || ''
  };

  var rescanTimer = null;

  function setFieldValue(id, value) {
    var field = document.getElementById(id);

    if (field) {
      field.value = value || '';
    }
  }

  function fillCustomerData() {
    if (!customer.logged) {
      return;
    }

    setFieldValue('sn-notify-name', defaultValues.name);
    setFieldValue('sn-notify-email', defaultValues.email);
    setFieldValue('sn-notify-phone', defaultValues.telephone);
  }

  function setIntroText() {
    if (!introEl) {
      return;
    }

    introEl.textContent = customer.logged
      ? (labels.introLogged || introEl.textContent)
      : (labels.intro || introEl.textContent);
  }

  function openNotifyModal(productId, productName) {
    if (productIdInput) {
      productIdInput.value = productId || '';
    }

    if (productNameEl) {
      productNameEl.textContent = productName || '';
    }

    setIntroText();
    openModal();
  }

  function openModal() {
    if (!modal) {
      return;
    }

    fillCustomerData();

    modal.hidden = false;
    document.body.classList.add('sn-notify-open');
    document.body.style.overflow = 'hidden';

    var focusField = customer.logged && defaultValues.name
      ? document.getElementById('sn-notify-phone')
      : document.getElementById('sn-notify-email');

    if (!focusField || (focusField.id === 'sn-notify-phone' && focusField.offsetParent === null)) {
      focusField = document.getElementById('sn-notify-email');
    }

    if (focusField && !focusField.readOnly) {
      setTimeout(function () {
        focusField.focus();
      }, 100);
    }
  }

  function closeModal() {
    if (!modal) {
      return;
    }

    modal.hidden = true;
    document.body.classList.remove('sn-notify-open');
    document.body.style.overflow = '';

    if (alertBox) {
      alertBox.hidden = true;
      alertBox.textContent = '';
      alertBox.className = 'sn-notify-form__alert';
    }

    fillCustomerData();
  }

  function showAlert(message, type) {
    if (!alertBox) {
      return;
    }

    alertBox.textContent = message;
    alertBox.className = 'sn-notify-form__alert is-' + type;
    alertBox.hidden = false;
  }

  function getCardProductName(card) {
    var nameEl = card.querySelector('.name a');

    return nameEl ? nameEl.textContent.trim() : '';
  }

  function getCardProductId(card) {
    var productId = card.getAttribute('data-product-id');

    if (productId) {
      return productId;
    }

    var notifyBtn = card.querySelector('.sn-card-notify[data-product-id]');

    if (notifyBtn) {
      return notifyBtn.getAttribute('data-product-id') || '';
    }

    var actionBtn = card.querySelector('.btn-compare, .btn-wishlist, .btn-cart, .btn-quickview');

    if (!actionBtn) {
      return '';
    }

    var onclick = actionBtn.getAttribute('onclick') || '';
    var match = onclick.match(/(?:compare|wishlist|cart)\.add\(['"]?(\d+)['"]?|quickview\(['"]?(\d+)['"]?/);

    return match ? (match[1] || match[2] || '') : '';
  }

  function isOutOfStockCard(card) {
    return card.classList.contains('out-of-stock')
      || !!card.querySelector('.out-of-stock')
      || !!card.closest('.out-of-stock');
  }

  function createCardNotifyButton(productId, productName) {
    var tooltip = labels.notifyTooltip || 'Notify when available';
    var button = document.createElement('a');

    button.className = 'btn btn-notify sn-card-notify';
    button.setAttribute('role', 'button');
    button.setAttribute('href', 'javascript:;');
    button.setAttribute('aria-label', tooltip);
    button.setAttribute('title', tooltip);
    button.setAttribute('data-product-id', productId);
    button.setAttribute('data-product-name', productName);
    button.innerHTML = '<span class="btn-text">' + envelopeIcon + '</span>';

    return button;
  }

  function initCardIcon(card) {
    if (!card || !isOutOfStockCard(card)) {
      return;
    }

    if (card.querySelector('.sn-card-notify')) {
      return;
    }

    var productId = getCardProductId(card);

    if (!productId) {
      return;
    }

    var compareBtn = card.querySelector('.btn-compare');
    var wishGroup = card.querySelector('.wish-group');
    var buttonGroup = card.querySelector('.button-group');
    var productName = getCardProductName(card);
    var notifyBtn = createCardNotifyButton(productId, productName);

    if (compareBtn) {
      compareBtn.insertAdjacentElement('afterend', notifyBtn);
      return;
    }

    if (wishGroup) {
      wishGroup.appendChild(notifyBtn);
      return;
    }

    if (buttonGroup) {
      buttonGroup.appendChild(notifyBtn);
    }
  }

  function initCardIcons(root) {
    var scope = root || document;

    scope.querySelectorAll('.product-layout').forEach(initCardIcon);
    scope.querySelectorAll('.side-product').forEach(function (sideProduct) {
      initCardIcon(sideProduct.closest('.product-layout') || sideProduct);
    });
  }

  function scheduleRescan() {
    if (rescanTimer) {
      clearTimeout(rescanTimer);
    }

    rescanTimer = setTimeout(function () {
      initCardIcons(document);
    }, 120);
  }

  function placeNotifyButton() {
    if (!notifyEl) {
      return;
    }

    var buttonsWrapper = document.querySelector('.product-info.out-of-stock .button-group-page .buttons-wrapper')
      || document.querySelector('.product-info.out-of-stock .button-group-page');

    if (!buttonsWrapper) {
      return;
    }

    var notifyGroup = document.createElement('div');
    notifyGroup.className = 'sn-notify-group';
    notifyGroup.appendChild(notifyEl);
    buttonsWrapper.appendChild(notifyGroup);
  }

  function bindStaticEvents() {
    document.addEventListener('click', function (event) {
      var cardBtn = event.target.closest('.sn-card-notify');

      if (cardBtn) {
        event.preventDefault();
        event.stopPropagation();
        openNotifyModal(
          cardBtn.getAttribute('data-product-id') || '',
          cardBtn.getAttribute('data-product-name') || ''
        );
        return;
      }
    });

    if (trigger) {
      trigger.addEventListener('click', function () {
        openNotifyModal(config.productId, config.productName);
      });
    }

    document.querySelectorAll('[data-sn-close]').forEach(function (el) {
      el.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && modal && !modal.hidden) {
        closeModal();
      }
    });

    document.addEventListener('shown.bs.tab', scheduleRescan);
    document.addEventListener('shown.bs.collapse', scheduleRescan);
    window.addEventListener('load', scheduleRescan);
  }

  function bindFormSubmit() {
    if (!form) {
      return;
    }

    form.addEventListener('submit', function (event) {
      event.preventDefault();

      if (!subscribeUrl) {
        showAlert('Unable to subscribe right now.', 'error');
        return;
      }

      fillCustomerData();

      var submitBtn = form.querySelector('.sn-notify-form__submit');
      var productId = productIdInput ? productIdInput.value : '';
      var originalText = submitBtn ? submitBtn.textContent : '';

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Please wait…';
      }

      fetch(subscribeUrl, {
        method: 'POST',
        body: new FormData(form),
        credentials: 'same-origin',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
        .then(function (response) {
          return response.json();
        })
        .then(function (json) {
          if (json.success) {
            showAlert(json.success, 'success');

            if (productIdInput) {
              productIdInput.value = productId;
            }

            fillCustomerData();
            setTimeout(closeModal, 2200);
            return;
          }

          showAlert(json.error || 'Unable to subscribe.', 'error');
        })
        .catch(function () {
          showAlert('Unable to subscribe. Please try again.', 'error');
        })
        .finally(function () {
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
          }
        });
    });
  }

  function observeProductLists() {
    initCardIcons(document);

    if (!window.MutationObserver) {
      return;
    }

    var observer = new MutationObserver(scheduleRescan);
    observer.observe(document.body, { childList: true, subtree: true });
  }

  function init() {
    fillCustomerData();
    bindStaticEvents();
    bindFormSubmit();
    placeNotifyButton();
    observeProductLists();
    scheduleRescan();
    setTimeout(scheduleRescan, 800);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
