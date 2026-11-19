(function () {
    'use strict';

    var dialog = document.getElementById('portal-dialog');
    if (!dialog || typeof dialog.showModal !== 'function') return;

    var title = dialog.querySelector('[data-dialog-title]');
    var message = dialog.querySelector('[data-dialog-message]');
    var icon = dialog.querySelector('[data-dialog-icon]');
    var inputWrap = dialog.querySelector('[data-dialog-input-wrap]');
    var input = dialog.querySelector('[data-dialog-input]');
    var inputError = dialog.querySelector('[data-dialog-input-error]');
    var cancelButton = dialog.querySelector('[data-dialog-cancel]');
    var acceptButton = dialog.querySelector('[data-dialog-accept]');
    var pendingResolve = null;

    function showDialog(options) {
        return new Promise(function (resolve) {
            if (pendingResolve && dialog.open) {
                dialog.close();
                pendingResolve(false);
            }
            pendingResolve = resolve;
            var previousFocus = document.activeElement;
            var kind = options.kind || 'confirm';
            var tone = options.tone || 'warning';
            dialog.className = 'portal-dialog portal-dialog-' + tone;
            dialog.dataset.kind = kind;
            title.textContent = options.title || (kind === 'alert' ? 'پیام سامانه' : kind === 'prompt' ? 'تکمیل اطلاعات' : 'تأیید عملیات');
            message.textContent = options.message || '';
            icon.textContent = options.icon || (tone === 'danger' ? '!' : tone === 'success' ? '✓' : kind === 'prompt' ? '✎' : '?');
            cancelButton.hidden = kind === 'alert';
            acceptButton.textContent = options.acceptText || (kind === 'alert' ? 'متوجه شدم' : kind === 'prompt' ? 'ادامه' : 'تأیید');
            cancelButton.textContent = options.cancelText || 'انصراف';
            inputWrap.hidden = kind !== 'prompt';
            input.value = '';
            input.required = kind === 'prompt' && options.required !== false;
            input.placeholder = options.placeholder || '';
            input.maxLength = options.maxLength || 500;
            inputError.textContent = '';

            function finish(value) {
                if (!dialog.open) return;
                dialog.close();
                pendingResolve = null;
                resolve(value);
                if (previousFocus && typeof previousFocus.focus === 'function') previousFocus.focus();
            }

            acceptButton.onclick = function () {
                if (kind === 'prompt') {
                    var value = input.value.trim();
                    if (input.required && !value) {
                        inputError.textContent = 'تکمیل این فیلد الزامی است.';
                        input.focus();
                        return;
                    }
                    finish(value);
                } else {
                    finish(kind === 'alert' ? true : true);
                }
            };
            cancelButton.onclick = function () { finish(kind === 'prompt' ? null : false); };
            dialog.oncancel = function (event) {
                event.preventDefault();
                finish(kind === 'prompt' ? null : false);
            };
            dialog.showModal();
            window.requestAnimationFrame(function () {
                (kind === 'prompt' ? input : acceptButton).focus();
            });
        });
    }

    function showAlert(text, tone) {
        return showDialog({ kind: 'alert', tone: tone || 'info', title: tone === 'danger' ? 'توجه' : 'پیام سامانه', message: String(text || '') });
    }

    function showConfirm(options) {
        return showDialog(options);
    }

    document.addEventListener('submit', async function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        var submitter = event.submitter || null;
        function formOption(name) {
            return form.dataset[name] || (submitter && submitter.dataset[name]) || '';
        }
        if (form.dataset.portalUiBypass === '1') {
            delete form.dataset.portalUiBypass;
            return;
        }

        var promptText = formOption('prompt');
        var confirmText = formOption('confirm');
        var selectionCount = null;
        var selectionSelector = formOption('confirmSelection');
        if (selectionSelector) {
            try { selectionCount = document.querySelectorAll(selectionSelector).length; }
            catch (error) { selectionCount = 0; }
            if (!selectionCount) {
                event.preventDefault();
                await showAlert(formOption('emptyMessage') || 'ابتدا دست‌کم یک مورد را انتخاب کنید.', 'warning');
                return;
            }
        }
        if (!promptText && !confirmText) return;

        event.preventDefault();
        if (promptText) {
            var promptValue = await showDialog({
                kind: 'prompt', tone: 'warning',
                title: formOption('promptTitle') || 'تکمیل اطلاعات',
                message: promptText,
                placeholder: formOption('promptPlaceholder'),
                required: formOption('promptRequired') !== 'false',
                maxLength: Number(formOption('promptMaxlength') || 500)
            });
            if (promptValue === null) return;
            var promptTarget = form.querySelector('[name="' + (formOption('promptTarget') || 'reason') + '"]');
            if (promptTarget) promptTarget.value = promptValue;
        }
        if (confirmText) {
            var formattedMessage = confirmText.replace(/\{count\}/g, String(selectionCount === null ? '' : selectionCount));
            var accepted = await showConfirm({
                kind: 'confirm',
                tone: formOption('confirmTone') || 'warning',
                title: formOption('confirmTitle') || (formOption('confirmTone') === 'danger' ? 'تأیید حذف' : 'تأیید عملیات'),
                message: formattedMessage,
                acceptText: formOption('confirmAccept') || 'تأیید و ادامه',
                cancelText: formOption('confirmCancel') || 'انصراف'
            });
            if (!accepted) return;
        }
        form.dataset.portalUiBypass = '1';
        if (event.submitter) form.requestSubmit(event.submitter);
        else form.requestSubmit();
    }, true);

    document.querySelectorAll('[data-dismiss-alert]').forEach(function (button) {
        button.addEventListener('click', function () {
            var alert = button.closest('.flash-alert');
            if (alert) alert.remove();
        });
    });

    document.querySelectorAll('.flash-alert[data-autohide="true"]').forEach(function (alert) {
        window.setTimeout(function () {
            if (alert.isConnected) {
                alert.classList.add('flash-alert-leaving');
                window.setTimeout(function () { alert.remove(); }, 240);
            }
        }, 6500);
    });
})();
