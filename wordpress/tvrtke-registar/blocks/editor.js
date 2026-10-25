(function (blocks, element, i18n) {
    'use strict';
    var el = element.createElement;
    blocks.registerBlockType('tvrtke-registar/pretraga', {
        edit: function () {
            return el('div', { className: 'components-placeholder', role: 'note' },
                el('strong', null, i18n.__('TVRTKE pretraga', 'tvrtke-registar')),
                el('p', null, i18n.__('Javna tražilica prikazuje se na objavljenoj stranici. Povežite API u Postavke → TVRTKE.', 'tvrtke-registar'))
            );
        },
        save: function () { return null; }
    });
}(window.wp.blocks, window.wp.element, window.wp.i18n));
