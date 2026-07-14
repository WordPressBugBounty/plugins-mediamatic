/**
 * Initializes jQuery UI draggable on WordPress native media items.
 * Works for both Grid View and List View.
 */

export const initDraggable = () => {
    if (typeof jQuery === 'undefined' || !jQuery.ui || !jQuery.ui.draggable) {
        console.warn('Mediamatic: jQuery UI Draggable not loaded.');
        return;
    }

    const makeDraggable = () => {
        // Grid View attachments or List View rows
        const items = jQuery('.mediamatic-active .attachment:not(.mediamatic-draggable), .mediamatic-active .wp-list-table tbody tr:not(.mediamatic-draggable)');

        if (items.length > 0) {
            items.addClass('mediamatic-draggable').draggable({
                cancel: '.check, .media-modal-icon, input, textarea, button, select, a',
                distance: 3,
                appendTo: 'body',
                cursor: 'move',
                cursorAt: { top: 10, left: 10 },
                helper: function () {
                    const $el = jQuery(this);
                    let selectedCount = 1;

                    // Bulk selection logic â€” works in media library and media modal
                    if ($el.hasClass('attachment')) {
                        const $selected = jQuery('.attachment.selected');
                        if ($el.hasClass('selected') && $selected.length > 0) {
                            selectedCount = $selected.length;
                        }
                    } else if ($el.is('tr')) {
                        const $checked = jQuery('.mediamatic-active .wp-list-table tbody tr input[type="checkbox"]:checked');
                        if ($el.find('input[type="checkbox"]').prop('checked') && $checked.length > 0) {
                            selectedCount = $checked.length;
                        }
                    }

                    const $helper = jQuery(`
                        <div class="mediamatic-drag-helper">
                            <span class="mediamatic-drag-count">${selectedCount}</span>
                            ${selectedCount === 1 ? 'file' : 'files'}
                        </div>
                    `);

                    return $helper;
                },
                start: function (event, ui) {
                    jQuery(this).addClass('mediamatic-is-dragging');
                    // Ensure helper is on top of everything including WP modal
                    setTimeout(() => {
                        jQuery('.mediamatic-drag-helper').css('z-index', 999999);
                    }, 0);
                },
                stop: function (event, ui) {
                    jQuery(this).removeClass('mediamatic-is-dragging');
                }
            });
        }
    };

    // Make items draggable immediately and set interval for dynamic Grid items
    makeDraggable();
    setInterval(makeDraggable, 500);
};
