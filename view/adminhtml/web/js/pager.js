define(['jquery', 'mage/loader'], function ($) {
    'use strict';

    return function (config, element) {
        var root = $(element),
            busy = false;

        root.loader({});

        function load(page, focusSelector) {
            var action = root.find('form.admin__data-grid-pager').attr('action'),
                fallback = new URL(action, window.location.href);

            if (busy || !Number.isInteger(page) || page < 1 || page > 100) {
                return;
            }
            busy = true;
            fallback.searchParams.set('related_orders_page', page);
            root.attr('aria-busy', 'true').loader('show');
            root.find('[data-role="pager-error"]').prop('hidden', true);

            $.ajax({
                url: root.attr('data-ajax-url'),
                type: 'GET',
                data: {related_orders_page: page},
                dataType: 'html',
                cache: false,
                timeout: 30000
            }).done(function (html) {
                var fragment = $('<div>').append($.parseHTML(html, document, false)),
                    section = fragment.find('section.stoneholl-related-customer-orders'),
                    input;

                if (!html.trim()) {
                    // The feature was disabled or access was revoked after the initial page load.
                    root.empty();
                    return;
                }
                if (section.length !== 1) {
                    root.find('[data-role="pager-error"]').prop('hidden', false);
                    return;
                }
                root.children().not('.loading-mask').remove();
                root.append(section.children());
                input = root.find('[name="related_orders_page"]');
                if (input.length) {
                    fallback.searchParams.set('related_orders_page', input.val());
                }
                window.history.replaceState(window.history.state, '', fallback.href);
                root.find(focusSelector).first().each(function () {
                    this.focus({preventScroll: true});
                });
            }).fail(function (xhr) {
                if (xhr.status === 401 || xhr.status === 403) {
                    root.find('.admin__data-grid-wrap, .admin__data-grid-header-row').remove();
                }
                root.find('[data-role="pager-error"]').prop('hidden', false);
            }).always(function () {
                busy = false;
                root.attr('aria-busy', 'false').loader('hide');
            });
        }

        root.on('click', 'a[data-page]', function (event) {
            if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
                return;
            }
            event.preventDefault();
            load(Number($(this).attr('data-page')), $(this).hasClass('action-next') ?
                '.action-next' : '.action-previous');
        });

        root.on('submit', 'form.admin__data-grid-pager', function (event) {
            event.preventDefault();
            if (this.checkValidity()) {
                load(Number($(this).find('[name="related_orders_page"]').val()),
                    '[name="related_orders_page"]');
            }
        });
    };
});
