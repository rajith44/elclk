(function () {
  'use strict';

  var root = document.getElementById('chatbot');

  if (!root) {
    return;
  }

  var sendUrl = root.getAttribute('data-send');
  var toggle = root.querySelector('.chatbot-toggle');
  var closeBtn = root.querySelector('.chatbot-close');
  var body = root.querySelector('#chatbot-body');
  var form = root.querySelector('#chatbot-form');
  var input = root.querySelector('#chatbot-text');
  var busy = false;

  // Persistent session id
  var sessionId = '';
  try {
    sessionId = window.localStorage.getItem('chatbot_session') || '';
  } catch (e) {
    sessionId = '';
  }

  function saveSession(id) {
    if (!id) {
      return;
    }
    sessionId = id;
    try {
      window.localStorage.setItem('chatbot_session', id);
    } catch (e) {
      /* ignore */
    }
  }

  function openWidget() {
    root.classList.add('is-open');
    setTimeout(function () {
      input.focus();
    }, 50);
  }

  function closeWidget() {
    root.classList.remove('is-open');
  }

  toggle.addEventListener('click', function () {
    if (root.classList.contains('is-open')) {
      closeWidget();
    } else {
      openWidget();
    }
  });

  closeBtn.addEventListener('click', closeWidget);

  function scrollDown() {
    body.scrollTop = body.scrollHeight;
  }

  function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function addUser(text) {
    var el = document.createElement('div');
    el.className = 'chatbot-msg chatbot-msg--user';
    el.textContent = text;
    body.appendChild(el);
    scrollDown();
  }

  function addBot(html) {
    var el = document.createElement('div');
    el.className = 'chatbot-msg chatbot-msg--bot';
    el.innerHTML = html;
    body.appendChild(el);
    scrollDown();
  }

  function addProducts(items) {
    var wrap = document.createElement('div');
    wrap.className = 'chatbot-products';

    items.forEach(function (p) {
      var a = document.createElement('a');
      a.className = 'chatbot-product';
      a.href = p.href;

      var brandHtml = p.manufacturer ? '<span class="chatbot-product-brand">' + escapeHtml(p.manufacturer) + '</span>' : '';
      var priceHtml = p.price ? '<span class="chatbot-product-price">' + escapeHtml(p.price) + '</span>' : '';

      a.innerHTML =
        '<img src="' + escapeHtml(p.thumb) + '" alt="' + escapeHtml(p.name) + '"/>' +
        '<span class="chatbot-product-info">' +
        '<span class="chatbot-product-name">' + escapeHtml(p.name) + '</span>' +
        brandHtml +
        priceHtml +
        '</span>';

      wrap.appendChild(a);
    });

    body.appendChild(wrap);
    scrollDown();
  }

  function addHandoff(handoff) {
    var a = document.createElement('a');
    a.className = 'chatbot-handoff';
    a.href = handoff.url;
    a.target = '_blank';
    a.rel = 'noopener';
    a.textContent = handoff.label || 'Chat with a human';
    body.appendChild(a);
    scrollDown();
  }

  function showTyping() {
    var el = document.createElement('div');
    el.className = 'chatbot-typing';
    el.id = 'chatbot-typing';
    el.textContent = '...';
    body.appendChild(el);
    scrollDown();
  }

  function hideTyping() {
    var el = document.getElementById('chatbot-typing');
    if (el) {
      el.parentNode.removeChild(el);
    }
  }

  function renderReply(json) {
    if (json.session_id) {
      saveSession(json.session_id);
    }

    if (json.messages && json.messages.length) {
      json.messages.forEach(function (msg) {
        if (msg.type === 'products') {
          addProducts(msg.items || []);
        } else {
          addBot(msg.text || '');
        }
      });
    }

    if (json.handoff && json.handoff.url) {
      addHandoff(json.handoff);
    }
  }

  function send(message, displayText) {
    if (busy || !message) {
      return;
    }

    busy = true;

    if (displayText !== false) {
      addUser(message);
    }

    // Remove quick-reply buttons once a conversation starts
    var quick = body.querySelector('.chatbot-quick');
    if (quick && displayText !== false) {
      quick.parentNode.removeChild(quick);
    }

    showTyping();

    var data = new FormData();
    data.append('message', message);
    data.append('session_id', sessionId);

    fetch(sendUrl, {
      method: 'POST',
      body: data,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (res) {
        if (!res.ok) {
          throw new Error('HTTP ' + res.status);
        }
        return res.json();
      })
      .then(function (json) {
        hideTyping();
        renderReply(json);
        busy = false;
      })
      .catch(function () {
        hideTyping();
        addBot('Something went wrong. Please try again.');
        busy = false;
      });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var text = input.value.trim();
    if (text === '') {
      return;
    }
    input.value = '';
    send(text);
  });

  // Quick-reply buttons (event delegation so dynamically added ones still work)
  body.addEventListener('click', function (e) {
    var btn = e.target.closest('.chatbot-quick-btn');
    if (!btn) {
      return;
    }
    var intent = btn.getAttribute('data-intent');
    send(intent, false);
  });
})();
