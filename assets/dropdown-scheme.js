(function ($) {
    'use strict';
    if (!$) return;
    // Every plugin shortcode uses this shared presentation root.
    const owners = '.ishi-theme-account select';
    const originals = new WeakMap();
    function restore(select) {
        const saved = originals.get(select);
        if (!saved) return;
        saved.forEach(({node, classes}) => {
            node.classList.remove('scheme_light');
            classes.forEach(name => node.classList.add(name));
        });
        originals.delete(select);
    }
    $(document).on('select2:open.ishiDropdownScheme', owners, function () {
        if (!window.QWERY_STORAGE) return;
        restore(this);
        const instance = $(this).data('select2');
        // SelectWoo and Select2 expose their owning instance on the source select.
        // Never select a global open dropdown or alter the closed field/body.
        if (!instance || !instance.$dropdown) return;
        const dropdowns = instance.$dropdown.find('.select2-dropdown').addBack('.select2-dropdown');
        const saved = [];
        dropdowns.each(function () {
            const classes = Array.from(this.classList).filter(name => name.indexOf('scheme_') === 0);
            classes.forEach(name => this.classList.remove(name));
            this.classList.add('scheme_light');
            saved.push({node: this, classes});
        });
        originals.set(this, saved);
    });
    $(document).on('select2:close.ishiDropdownScheme', owners, function () { restore(this); });
}(window.jQuery));
