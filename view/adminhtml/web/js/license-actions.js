/**
 * Focus Licensing dashboard actions.
 *
 * Thin transport layer only: posts to the admin action controllers and swaps
 * the re-rendered dashboard HTML into place. All decisions happen server-side.
 */
define([
    'jquery',
    'Magento_Ui/js/modal/confirm',
    'mage/translate'
], function ($, confirm, $t) {
    'use strict';

    return function (config, element) {
        var $root = $(element);

        function showMessage(ok, text) {
            $root.find('[data-role="focus-lic-messages"]').first().html(
                $('<div>')
                    .addClass('focus-lic__message')
                    .addClass(ok ? 'focus-lic__message--ok' : 'focus-lic__message--error')
                    .text(text)
            );
        }

        function renderChecks(response) {
            var $results = $root.find('[data-role="focus-lic-test-results"]').first(),
                $list = $('<ul>').addClass('focus-lic__checks');

            $.each(response.checks || [], function (i, check) {
                $list.append(
                    $('<li>')
                        .addClass(check.ok ? 'focus-lic__check--ok' : 'focus-lic__check--fail')
                        .append($('<strong>').text(check.label))
                        .append($('<span>').text(' — ' + check.detail))
                );
            });
            $results.empty().append($list);
            showMessage(!!response.success, response.message || '');
        }

        function run(action, params) {
            var url = config[action],
                data = $.extend({ form_key: window.FORM_KEY }, params || {});

            if (!url) {
                return;
            }
            $root.addClass('focus-lic--busy');

            if (action === 'test') {
                $.post(url, data)
                    .done(renderChecks)
                    .fail(function () {
                        showMessage(false, $t('The request failed. Refresh the page and try again.'));
                    })
                    .always(function () {
                        $root.removeClass('focus-lic--busy');
                    });

                return;
            }

            $.post(url, data)
                .done(function (response) {
                    if (response && response.html) {
                        var $fresh = $(response.html);

                        $root.replaceWith($fresh);
                        $fresh.trigger('contentUpdated');
                        $fresh.find('[data-role="focus-lic-messages"]').first().html(
                            $('<div>')
                                .addClass('focus-lic__message')
                                .addClass(response.success ? 'focus-lic__message--ok' : 'focus-lic__message--error')
                                .text(response.message || '')
                        );
                    } else {
                        showMessage(!!(response && response.success), (response && response.message) || $t('Done.'));
                        $root.removeClass('focus-lic--busy');
                    }
                })
                .fail(function () {
                    showMessage(false, $t('The request failed. Refresh the page and try again.'));
                    $root.removeClass('focus-lic--busy');
                });
        }

        /**
         * First-run helper: reveal the License Key field further down the same
         * configuration page. The General group ships collapsed, so it has to
         * be opened before the field can be scrolled to or focused.
         */
        $root.on('click', '[data-role="focus-lic-goto-key"]', function () {
            var $field = $('#focus_licensing_general_license_key'),
                $head = $('#focus_licensing_general-head');

            if (!$field.length) {
                return;
            }

            if (!$field.is(':visible') && $head.length) {
                $head.trigger('click');
            }

            if ($field[0].scrollIntoView) {
                $field[0].scrollIntoView({ block: 'center' });
            }
            $field.trigger('focus');
        });

        function trigger(action, confirmText, title, params) {
            if (confirmText) {
                confirm({
                    title: title,
                    content: confirmText,
                    actions: {
                        confirm: function () {
                            run(action, params);
                        }
                    }
                });

                return;
            }
            run(action, params);
        }

        $root.on('click', '[data-role="focus-lic-action"]', function () {
            var $btn = $(this);

            trigger($btn.data('action'), $btn.data('confirm'), $t('Deactivate License'));
        });

        /**
         * Release the production slot held by another domain on this licence.
         * This store keeps running — only Deactivate releases the current domain.
         */
        $root.on('click', '[data-role="focus-lic-release"]', function () {
            var $btn = $(this);

            trigger('release', $btn.data('confirm'), $t('Release Domain Slot'), {
                domain: $btn.data('domain')
            });
        });
    };
});
