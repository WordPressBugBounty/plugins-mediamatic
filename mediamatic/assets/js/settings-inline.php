<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
(function($){
                var nonce = '<?php echo esc_js($nonce_val); ?>';
                var ajaxUrl = '<?php echo esc_js($ajax_url); ?>';

                // ── CSV Import ──────────────────────────────────
                $('#mediamatic-import-csv-btn').on('click', function () {
                    var file = document.getElementById('mediamatic-import-csv-file').files[0];
                    if (!file) { alert('<?php esc_html_e('Please choose a CSV file first.', 'mediamatic'); ?>'); return; }
                    var mode = $('#mediamatic-import-csv-mode').val();
                    var fd = new FormData();
                    fd.append('action', 'mediamatic_import_csv');
                    fd.append('nonce', nonce);
                    fd.append('import_mode', mode);
                    fd.append('csv_file', file);
                    var $s = $('#mediamatic-import-csv-status').text('Importing…').removeClass('ok err').show();
                    $.ajax({
                        url: ajaxUrl, type: 'POST', data: fd, processData: false, contentType: false,
                        success: function (r) {
                            $s.addClass(r.success ? 'ok' : 'err')
                                .text(r.success ? '✓ Imported ' + r.data.imported + ' folders.' : '✗ ' + r.data);
                        },
                        error: function () { $s.addClass('err').text('Request failed. Please try again.'); }
                    });
                });

                // ── Load backups ────────────────────────────────
                $('#mediamatic-load-backups-btn').on('click', function () {
                    var $btn = $(this).text('Loading…').prop('disabled', true);
                    $.post(ajaxUrl, { action: 'mediamatic_list_backups', nonce: nonce }, function (r) {
                        $btn.text('<?php esc_html_e('Load backups', 'mediamatic'); ?>').prop('disabled', false);
                        var $list = $('#mediamatic-backup-list');
                        if (!r.success || !r.data.length) {
                            $list.html('<p class="mediamatic-backup-empty"><?php esc_html_e('No backups found yet. Backups are created automatically every day.', 'mediamatic'); ?></p>');
                            return;
                        }
                        var html = '<table class="mediamatic-backup-table"><thead><tr>'
                            + '<th><?php esc_html_e('File', 'mediamatic'); ?></th>'
                            + '<th><?php esc_html_e('Date', 'mediamatic'); ?></th>'
                            + '<th><?php esc_html_e('Size', 'mediamatic'); ?></th>'
                            + '<th></th></tr></thead><tbody>';
                        $.each(r.data, function (i, b) {
                            html += '<tr>'
                                + '<td class="user-col" title="' + b.filename + '">' + b.display_name + '</td>'
                                + '<td style="color:#646970;">' + b.date + '</td>'
                                + '<td style="color:#646970;">' + b.size + '</td>'
                                + '<td><button class="button button-small mediamatic-restore-btn" data-file="' + b.filename + '"><?php esc_html_e('Restore', 'mediamatic'); ?></button></td>'
                                + '</tr>';
                        });
                        html += '</tbody></table>';
                        $list.html(html);
                    });
                });

                // ── Restore backup ──────────────────────────────
                $(document).on('click', '.mediamatic-restore-btn', function () {
                    if (!confirm('<?php esc_html_e('Restore this backup? The current folders for that user will be replaced.', 'mediamatic'); ?>')) return;
                    var file = $(this).data('file');
                    var $btn = $(this).text('…').prop('disabled', true);
                    $.post(ajaxUrl, { action: 'mediamatic_restore_backup', nonce: nonce, filename: file }, function (r) {
                        $btn.text('<?php esc_html_e('Restore', 'mediamatic'); ?>').prop('disabled', false);
                        alert(r.success ? '✓ Restored ' + r.data.restored + ' folders.' : '✗ ' + r.data);
                    });
                });
            }) (jQuery);