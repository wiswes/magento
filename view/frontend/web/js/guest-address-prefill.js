/**
 * Guest checkout address pre-fill bridge.
 *
 * Magento's stock guest-checkout flow treats the shipping/billing form as
 * the source-of-truth — there's no path that pulls server-side
 * `shippingAddressFromData` back into the field-level KO observables for
 * guests. Each field's `links.value` binding subscribes to changes on the
 * form's data provider, but pushing data into the provider after init
 * (which is what `GuestQuoteAddressConfigProvider` ends up doing) doesn't
 * propagate to the per-field `value()` observables for the multi-line
 * `street` group, and inconsistently for region-related fields.
 *
 * This module force-sets each field element's `value()` directly via
 * uiRegistry once the form has rendered. Field-by-field rather than via
 * the source so the multi-input street group's children pick it up.
 *
 * No-op on non-checkout pages (no `shippingAddressFromData`) and no-op
 * when the user has already started typing (we don't clobber drafts).
 */
require(['uiRegistry'], function (registry) {
    'use strict';

    var FIELDSET = 'checkout.steps.shipping-step.shippingAddress.shipping-address-fieldset';

    function applyValue(field, value) {
        if (value === null || value === undefined || value === '') {
            return;
        }
        var el = registry.get(FIELDSET + '.' + field);
        if (!el || typeof el.value !== 'function') {
            return;
        }
        // Don't clobber a value the user already typed.
        var current = el.value();
        if (current && String(current).length > 0) {
            return;
        }
        el.value(value);
    }

    function applyStreet(streetArr) {
        if (!Array.isArray(streetArr)) {
            return;
        }
        streetArr.forEach(function (line, idx) {
            applyValue('street.' + idx, line);
        });
    }

    function prefillFrom(addr) {
        if (!addr) {
            return;
        }
        Object.keys(addr).forEach(function (key) {
            if (key === 'street') {
                applyStreet(addr.street);
                return;
            }
            applyValue(key, addr[key]);
        });
    }

    var cfg = window.checkoutConfig || {};
    var shipping = cfg.shippingAddressFromData;

    if (!shipping) {
        return;
    }

    // Wait until the address fieldset's children are registered. The
    // form is registered top-down asynchronously; ask uiRegistry to
    // resolve the deepest field we'll touch (street.0) before pushing.
    registry.get(FIELDSET + '.firstname', function () {
        registry.get(FIELDSET + '.street.0', function () {
            prefillFrom(shipping);
        });
    });
});
