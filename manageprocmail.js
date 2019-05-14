if (window.rcmail) {
    rcmail.addEventListener('init', function (evt) {
        if (rcmail.env.action.startsWith('plugin.manageprocmail')) {
            if (rcmail.gui_objects.filterslist) {

                rcmail.filters_list = new rcube_list_widget(rcmail.gui_objects.filterslist,
                    {multiselect: false, draggable: false, keyboard: true});

                rcmail.filters_list
                    .addEventListener('select', function (e) {
                        rcmail.manageprocmail_select(e);
                    })
                    .init();
            }

            (function () {
                if (rcmail.gui_objects.forward_to_checkbox) {
                    var $list = $(rcmail.gui_objects.forward_to_list).parent().parent();

                    rcmail.gui_objects.forward_to_checkbox.onclick = function () {
                        $list.toggle();
                    };

                    if (!rcmail.gui_objects.forward_to_checkbox.checked) {
                        $list.hide();
                    } else {
                        $list.show();
                    }
                }
            })();

            var $moveTo = $(rcmail.gui_objects.move_to_folder_checkbox);
            var $copyTo = $(rcmail.gui_objects.copy_to_folder_checkbox);
            (function () {
                if (rcmail.gui_objects.move_to_folder_checkbox) {
                    var $list = $(rcmail.gui_objects.move_to_folder_list);

                    rcmail.gui_objects.move_to_folder_checkbox.onclick = function () {
                        $list.attr('disabled', !rcmail.gui_objects.move_to_folder_checkbox.checked);
                        $copyTo.attr('disabled', rcmail.gui_objects.move_to_folder_checkbox.checked);
                    };

                    $list.attr('disabled', !rcmail.gui_objects.move_to_folder_checkbox.checked);
                    $copyTo.attr('disabled', rcmail.gui_objects.move_to_folder_checkbox.checked);
                }
            })();

            (function () {
                if (rcmail.gui_objects.copy_to_folder_checkbox) {
                    var $list = $(rcmail.gui_objects.copy_to_folder_list);

                    rcmail.gui_objects.copy_to_folder_checkbox.onclick = function () {
                        $list.attr('disabled', !rcmail.gui_objects.copy_to_folder_checkbox.checked);
                        $moveTo.attr('disabled', rcmail.gui_objects.copy_to_folder_checkbox.checked);
                    };

                    $list.attr('disabled', !rcmail.gui_objects.copy_to_folder_checkbox.checked);
                    $moveTo.attr('disabled', rcmail.gui_objects.copy_to_folder_checkbox.checked);
                }
            })();

            rcmail.register_command('plugin.manageprocmail-add', function() {
                rcmail.filters_list.clear_selection();
                rcmail.load_manageprocmailframe();
            }, true);

            rcmail.register_command('plugin.manageprocmail-del', function() {
                var id = rcmail.filters_list.get_single_selection();

                if (id != null) {
                    id = rcmail.filters_list.rows[id].uid;
                    if (confirm('a')) {
                        // TODO: AJAX
                        rcmail.display_message('removed', 'confirmation', 3000);
                        rcmail.filters_list.clear_selection();
                    }
                }
            }, false);
        }
    })
}

// Filter selection
rcube_webmail.prototype.manageprocmail_select = function (list) {
    var id = list.get_single_selection();

    if (id != null) {
        id = list.rows[id].uid;
        this.load_manageprocmailframe('_fid=' + id);
    }

    var has_id = typeof (id) != 'undefined' && id != null;
    this.enable_command('plugin.manageprocmail-del', has_id);
};

// load filter frame
rcube_webmail.prototype.load_manageprocmailframe = function (add_url) {
    if (this.env.contentframe && window.frames && window.frames[this.env.contentframe]) {
        var lock = this.set_busy(true, 'loading');

        target = window.frames[this.env.contentframe];
        target.location.href = this.env.comm_path
            + '&_action=plugin.manageprocmail-editform&_framed=1&_unlock=' + lock
            + (add_url ? ('&' + add_url) : '');
    }
};
