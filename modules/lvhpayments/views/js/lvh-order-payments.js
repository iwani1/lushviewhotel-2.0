/**
 * Lush View Hotel - Back-Office Canonical Payment Selector & Reference Guard
 *
 * Replaces QloApps' free-text payment method inputs on AdminOrders (both New Order
 * and Order View Add-Payment forms) with standardized Ghana hospitality tenders
 * and enforces Transaction ID / Reference entry on electronic & comp payments.
 */
(function () {
    'use strict';

    function getMethods() {
        if (window.LVH_PAYMENT_METHODS && window.LVH_PAYMENT_METHODS.length) {
            return window.LVH_PAYMENT_METHODS;
        }
        return [
            { label: 'Cash (GHS)', requires_ref: false, placeholder: 'Optional receipt / note' },
            { label: 'MTN MoMo', requires_ref: true, placeholder: 'Required: MTN MoMo Transaction ID' },
            { label: 'Telecel Cash', requires_ref: true, placeholder: 'Required: Telecel Cash Transaction ID' },
            { label: 'AT Money', requires_ref: true, placeholder: 'Required: AT Money Transaction ID' },
            { label: 'POS / Card', requires_ref: true, placeholder: 'Required: POS Slip / Approval Code' },
            { label: 'Bank Transfer', requires_ref: true, placeholder: 'Required: Bank Transfer Reference' },
            { label: 'Corporate / Pay Later', requires_ref: false, placeholder: 'Company name or LPO reference' }
        ];
    }

    function findTxInput(selectEl) {
        var form = selectEl.closest ? selectEl.closest('form') : null;
        if (form) {
            var inForm = form.querySelector('input[name="payment_transaction_id"], #payment_transaction_id');
            if (inForm) {
                return inForm;
            }
        }
        return document.getElementById('payment_transaction_id');
    }

    function syncTxRequirement(selectEl) {
        var methods = getMethods();
        var chosen = null;
        for (var i = 0; i < methods.length; i++) {
            if (methods[i].label === selectEl.value) {
                chosen = methods[i];
                break;
            }
        }
        var txInput = findTxInput(selectEl);
        if (!txInput || !chosen) {
            return;
        }
        txInput.placeholder = chosen.placeholder || '';
        if (chosen.requires_ref) {
            txInput.setAttribute('required', 'required');
            txInput.style.borderColor = txInput.value.trim() ? '#16a34a' : '#d97706';
        } else {
            txInput.removeAttribute('required');
            txInput.style.borderColor = '';
        }
    }

    function upgradeInputToSelect(inputEl) {
        if (!inputEl || inputEl.tagName === 'SELECT' || inputEl.getAttribute('data-lvh-upgraded') === '1') {
            return;
        }
        var methods = getMethods();
        var select = document.createElement('select');
        select.name = inputEl.name;
        if (inputEl.id) {
            select.id = inputEl.id;
        }
        select.className = inputEl.className || 'form-control';
        select.setAttribute('data-lvh-upgraded', '1');

        var currentVal = (inputEl.value || '').trim();
        var matched = false;

        for (var i = 0; i < methods.length; i++) {
            var opt = document.createElement('option');
            opt.value = methods[i].label;
            opt.textContent = methods[i].label + (methods[i].requires_ref ? ' (Ref Required)' : '');
            if (currentVal && currentVal.toLowerCase() === methods[i].label.toLowerCase()) {
                opt.selected = true;
                matched = true;
            }
            select.appendChild(opt);
        }

        if (!matched) {
            select.selectedIndex = 0;
        }

        inputEl.parentNode.replaceChild(select, inputEl);

        select.addEventListener('change', function () {
            syncTxRequirement(select);
        });

        var txInput = findTxInput(select);
        if (txInput) {
            txInput.addEventListener('input', function () {
                syncTxRequirement(select);
            });
        }

        var form = select.closest ? select.closest('form') : null;
        if (form && !form.getAttribute('data-lvh-tx-guard')) {
            form.setAttribute('data-lvh-tx-guard', '1');
            form.addEventListener('submit', function (ev) {
                var activeSelect = form.querySelector('select[data-lvh-upgraded="1"]');
                if (!activeSelect) {
                    return;
                }
                // Only block if this submit is actually posting a payment or creating an order
                var methodsList = getMethods();
                var rule = null;
                for (var j = 0; j < methodsList.length; j++) {
                    if (methodsList[j].label === activeSelect.value) {
                        rule = methodsList[j];
                        break;
                    }
                }
                var tx = findTxInput(activeSelect);
                if (rule && rule.requires_ref && tx && tx.offsetParent !== null && !tx.value.trim()) {
                    ev.preventDefault();
                    ev.stopPropagation();
                    tx.focus();
                    alert('Lush View Hotel Policy: Please enter the ' + rule.placeholder.replace('Required: ', '') + ' for ' + rule.label + '.');
                }
            });
        }

        syncTxRequirement(select);
    }

    function scanAndUpgrade() {
        var targets = document.querySelectorAll(
            'input#payment_module_name, input[name="payment_module_name"], input[name="payment_method"]'
        );
        for (var i = 0; i < targets.length; i++) {
            upgradeInputToSelect(targets[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scanAndUpgrade);
    } else {
        scanAndUpgrade();
    }
    setTimeout(scanAndUpgrade, 800);
})();
