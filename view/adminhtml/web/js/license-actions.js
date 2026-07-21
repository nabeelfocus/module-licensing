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

        function run(action) {
            var url = config[action];

            if (!url) {
                return;
            }
            $root.addClass('focus-lic--busy');

            if (action === 'test') {
                $.post(url, { form_key: window.FORM_KEY })
                    .done(renderChecks)
                    .fail(function () {
                        showMessage(false, $t('The request failed. Refresh the page and try again.'));
                    })
                    .always(function () {
                        $root.removeClass('focus-lic--busy');
                    });

                return;
            }

            $.post(url, { form_key: window.FORM_KEY })
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

        $root.on('click', '[data-role="focus-lic-action"]', function () {
            var $btn = $(this),
                action = $btn.data('action'),
                confirmText = $btn.data('confirm');

            if (confirmText) {
                confirm({
                    title: $t('Deactivate License'),
                    content: confirmText,
                    actions: {
                        confirm: function () {
                            run(action);
                        }
                    }
                });

                return;
            }
            run(action);
        });
    };
});
