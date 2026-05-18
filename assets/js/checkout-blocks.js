(function () {
    'use strict';

    var registry = window.wc && window.wc.wcBlocksRegistry;
    if (!registry) {
        console.warn('[QvaPay] wc-blocks-registry not available; gateway will not appear in Blocks checkout.');
        return;
    }

    var getSetting = (window.wc && window.wc.wcSettings && window.wc.wcSettings.getSetting)
        ? window.wc.wcSettings.getSetting
        : function (_, fallback) { return fallback; };

    var settings = getSetting('qvapay_data', {});

    var el = window.wp.element.createElement;
    var __ = (window.wp.i18n && window.wp.i18n.__) ? window.wp.i18n.__ : function (s) { return s; };

    function Label() {
        var children = [el('span', { key: 'title' }, settings.title || __('QvaPay', 'qvapay-for-woocommerce'))];
        if (settings.icon) {
            children.unshift(el('img', {
                key: 'icon',
                src: settings.icon,
                alt: '',
                width: 24,
                height: 24,
                style: { marginRight: 8, verticalAlign: 'middle' }
            }));
        }
        return el('span', { className: 'qvapay-label' }, children);
    }

    function Content() {
        return el('p', { className: 'qvapay-description' },
            settings.description || __('Serás redirigido a QvaPay para completar el pago con tu balance.', 'qvapay-for-woocommerce')
        );
    }

    registry.registerPaymentMethod({
        name: 'qvapay',
        label: el(Label, null),
        content: el(Content, null),
        edit: el(Content, null),
        canMakePayment: function () { return true; },
        ariaLabel: settings.title || 'QvaPay',
        supports: { features: settings.supports || ['products'] }
    });
})();
