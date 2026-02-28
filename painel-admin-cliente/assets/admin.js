(function ($) {
    var body = $('#pac-items-body');
    var addButton = $('#pac-add-item');
    var template = $('#pac-item-row-template').html() || '';
    var iconModal = $('#pac-icon-modal');
    var iconGrid = $('#pac-icon-grid');
    var iconSearch = $('#pac-icon-search');
    var iconClose = $('#pac-icon-close');
    var i18n = (window.pacPanelSettings && window.pacPanelSettings.i18n) ? window.pacPanelSettings.i18n : {};
    var icons = (window.pacPanelSettings && Array.isArray(window.pacPanelSettings.icons) && window.pacPanelSettings.icons.length)
        ? window.pacPanelSettings.icons
        : ['dashicons-admin-links'];
    var activeIconInput = null;
    var selectedIcon = 'dashicons-admin-links';
    var nextIndex = 0;

    if (!body.length || !addButton.length || !template.length) {
        return;
    }

    if (window.pacPanelSettings && Number.isInteger(parseInt(window.pacPanelSettings.nextIndex, 10))) {
        nextIndex = parseInt(window.pacPanelSettings.nextIndex, 10);
    } else {
        nextIndex = body.find('tr').length;
    }

    function normalizeIcon(icon) {
        if (typeof icon !== 'string') {
            return 'dashicons-admin-links';
        }

        icon = icon.trim().toLowerCase();
        if (icon.indexOf('dashicons-') !== 0) {
            return 'dashicons-admin-links';
        }

        return icon;
    }

    function iconLabel(icon) {
        return icon.replace('dashicons-', '');
    }

    function updateRowIcon(input, icon) {
        var row = input.closest('tr');
        var button = row.find('.pac-open-icon-picker');
        var preview = button.find('.pac-icon-preview');
        var label = button.find('.pac-icon-name');

        input.val(icon);
        preview.attr('class', 'dashicons ' + icon + ' pac-icon-preview');
        label.text(iconLabel(icon));
    }

    function renderIconGrid(filter) {
        var term = (filter || '').trim().toLowerCase();
        var html = '';
        var visibleCount = 0;

        icons.forEach(function (icon) {
            var normalized = normalizeIcon(icon);

            if (term && normalized.indexOf(term) === -1 && iconLabel(normalized).indexOf(term) === -1) {
                return;
            }

            visibleCount += 1;
            html += '<button type="button" class="pac-icon-choice' + (normalized === selectedIcon ? ' is-active' : '') + '" data-icon="' + normalized + '" title="' + normalized + '">';
            html += '<span class="dashicons ' + normalized + '" aria-hidden="true"></span>';
            html += '<span class="screen-reader-text">' + normalized + '</span>';
            html += '</button>';
        });

        if (!visibleCount) {
            html = '<p class="pac-icon-empty">' + (i18n.emptySearch || 'Nenhum icone encontrado.') + '</p>';
        }

        iconGrid.html(html);
    }

    function openIconPicker(input) {
        activeIconInput = input;
        selectedIcon = normalizeIcon(input.val() || 'dashicons-admin-links');

        iconSearch.val('');
        iconSearch.attr('placeholder', i18n.searchPlaceholder || 'Buscar icone...');
        renderIconGrid('');

        iconModal.removeAttr('hidden');
        $('body').addClass('pac-icon-modal-open');
        window.setTimeout(function () {
            iconSearch.trigger('focus');
        }, 0);
    }

    function closeIconPicker() {
        iconModal.attr('hidden', 'hidden');
        $('body').removeClass('pac-icon-modal-open');
        activeIconInput = null;
    }

    addButton.on('click', function (event) {
        event.preventDefault();

        var rowHtml = template.replace(/__INDEX__/g, String(nextIndex));
        body.append(rowHtml);
        nextIndex += 1;
    });

    body.on('click', '.pac-open-icon-picker', function (event) {
        event.preventDefault();
        openIconPicker($(this).closest('tr').find('.pac-icon-input').first());
    });

    body.on('click', '.pac-remove-item', function (event) {
        event.preventDefault();
        $(this).closest('tr').remove();
    });

    iconGrid.on('click', '.pac-icon-choice', function (event) {
        event.preventDefault();

        if (!activeIconInput) {
            return;
        }

        var icon = normalizeIcon($(this).attr('data-icon'));
        updateRowIcon(activeIconInput, icon);
        closeIconPicker();
    });

    iconSearch.on('input', function () {
        renderIconGrid($(this).val());
    });

    iconClose.on('click', function (event) {
        event.preventDefault();
        closeIconPicker();
    });

    iconModal.on('click', function (event) {
        if ($(event.target).is('#pac-icon-modal')) {
            closeIconPicker();
        }
    });

    $(document).on('keydown', function (event) {
        if ('Escape' === event.key && !iconModal.is('[hidden]')) {
            closeIconPicker();
        }
    });
})(jQuery);
