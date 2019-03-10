if (window.rcmail) {
    rcmail.addEventListener('init', function (evt) {

        rcmail.register_command('plugin.manageprocmail-save', function() {}, true);

        if (rcmail.env.action.startsWith('plugin.manageprocmail')) {

            if (rcmail.gui_objects.filterslist) {
                // alert('ok');

                rcmail.filters_list = new rcube_list_widget(rcmail.gui_objects.filterslist,
                    {multiselect: false, draggable: true, keyboard: true});

                rcmail.filters_list
                    .addEventListener('select', function (e) {
                        rcmail.manageprocmail_select(e);
                    })
                    //.addEventListener('dragstart', function(e) { rcmail.manageprocmail_dragstart(e); })
                    //.addEventListener('dragend', function(e) { rcmail.manageprocmail_dragend(e); })
                    .addEventListener('initrow', function (row) {
                        row.obj.onmouseover = function () {
                            rcmail.manageprocmail_focus_filter(row);
                        };
                        row.obj.onmouseout = function () {
                            rcmail.manageprocmail_unfocus_filter(row);
                        };
                    })
                    .init();
            }
        }
    })

    rcmail.addEventListener('plugin.manageprocmail-save', function (response) {
        alert('ok')
        console.log(response)
    });
}

// Filter selection
rcube_webmail.prototype.manageprocmail_select = function (list) {
    var id = list.get_single_selection();

    if (id != null) {
        id = list.rows[id].uid;
        this.load_manageprocmailframe('_fid=' + id);
    }

    var has_id = typeof (id) != 'undefined' && id != null;
    this.enable_command('plugin.manageprocmail-act', 'plugin.manageprocmail-del', has_id);
};

// load filter frame
rcube_webmail.prototype.load_manageprocmailframe = function (add_url, reset) {
    // if (reset)
    // this.reset_filters_list();

    if (this.env.contentframe && window.frames && window.frames[this.env.contentframe]) {
        var lock = this.set_busy(true, 'loading');

        target = window.frames[this.env.contentframe];
        target.location.href = this.env.comm_path
            + '&_action=plugin.manageprocmail-action&_framed=1&_unlock=' + lock
            + (add_url ? ('&' + add_url) : '');
    }
};
