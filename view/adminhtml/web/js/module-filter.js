/**
 * Focus Licensing — commercial modules filter.
 *
 * Presentation only: every row is already rendered by modules.phtml, this just
 * hides the ones that do not match the active chip and the search term. The
 * chip filter and the search term combine, so "Active" plus "pdp" shows only
 * active Pdp modules.
 */
define(['jquery'], function ($) {
    'use strict';

    return function (config, element) {
        var $card = $(element),
            $rows = $card.find('[data-role="focus-lic-module-row"]'),
            $empty = $card.find('[data-role="focus-lic-empty"]').first(),
            filter = 'attention',
            term = '';

        function matches($row) {
            if (term && $row.data('name').toString().indexOf(term) === -1) {
                return false;
            }

            if (filter === 'all') {
                return true;
            }

            if (filter === 'attention') {
                return $row.data('attention').toString() === '1';
            }

            return $row.data('status') === filter;
        }

        function render() {
            var visible = 0;

            $rows.each(function () {
                var $row = $(this),
                    show = matches($row);

                $row.attr('hidden', show ? null : 'hidden');

                if (show) {
                    visible++;
                }
            });

            if (visible === 0) {
                $empty
                    .text($empty.data(filter === 'attention' && !term ? 'msg-attention' : 'msg-none'))
                    .removeAttr('hidden');
            } else {
                $empty.attr('hidden', 'hidden');
            }
        }

        $card.on('click', '[data-role="focus-lic-chip"]', function () {
            var $chip = $(this);

            $card.find('[data-role="focus-lic-chip"]')
                .removeClass('focus-lic__chip--on')
                .attr('aria-pressed', 'false');
            $chip.addClass('focus-lic__chip--on').attr('aria-pressed', 'true');

            filter = $chip.data('filter');
            render();
        });

        $card.on('input', '[data-role="focus-lic-search"]', function () {
            term = $.trim($(this).val()).toLowerCase();
            render();
        });

        render();
    };
});
