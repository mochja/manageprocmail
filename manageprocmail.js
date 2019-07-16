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

            if (rcmail.gui_objects.vacationslist) {

                rcmail.vacations_list = new rcube_list_widget(rcmail.gui_objects.vacationslist,
                    {multiselect: false, draggable: false, keyboard: true});

                rcmail.vacations_list
                    .addEventListener('select', function (e) {
                        rcmail.manageprocmail_select_vacation(e);
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

            if (rcmail.gui_objects.move_to_folder_checkbox) {
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
            }

            rcmail.register_command('plugin.manageprocmail-add', function () {
                rcmail.filters_list.clear_selection();
                rcmail.load_manageprocmailframe();
            }, true);

            rcmail.register_command('plugin.manageprocmail-add-vacation', function () {
                rcmail.vacations_list.clear_selection();
                rcmail.load_manageprocmailvacationframe();
            }, true);

            rcmail.register_command('plugin.manageprocmail-del', function () {
                var id = rcmail.filters_list.get_single_selection();

                if (id != null) {
                    id = rcmail.filters_list.rows[id].uid;
                    if (confirm('Are you sure?')) {
                        var lock = rcmail.set_busy(true, 'loading');

                        rcmail.http_get('plugin.manageprocmail-del', {_fid: id}, lock);
                        rcmail.filters_list.clear_selection();
                        rcmail.filters_list.remove_row(id);
                        rcmail.filters_list.select_first();
                    }
                }
            }, false);

            rcmail.register_command('plugin.manageprocmail-vacation-del', function () {
                var id = rcmail.vacations_list.get_single_selection();

                if (id != null) {
                    id = rcmail.vacations_list.rows[id].uid;
                    if (confirm('Are you sure?')) {
                        var lock = rcmail.set_busy(true, 'loading');

                        rcmail.http_get('plugin.manageprocmail-vacation-del', {_fid: id}, lock);
                        rcmail.vacations_list.clear_selection();
                        rcmail.vacations_list.remove_row(id);
                        rcmail.vacations_list.select_first();
                    }
                }
            }, false);
        }
    })
}

// Vacation selection
rcube_webmail.prototype.manageprocmail_select_vacation = function (list) {
    var id = list.get_single_selection();

    if (id != null) {
        id = list.rows[id].uid;
        this.load_manageprocmailvacationframe(id);
    }

    var has_id = typeof (id) != 'undefined' && id != null;
    this.enable_command('plugin.manageprocmail-vacation-del', has_id);
};

// Filter selection
rcube_webmail.prototype.manageprocmail_select = function (list) {
    var id = list.get_single_selection();

    if (id != null) {
        id = list.rows[id].uid;
        this.load_manageprocmailframe(id);
    }

    var has_id = typeof (id) != 'undefined' && id != null;
    this.enable_command('plugin.manageprocmail-del', has_id);
};

// load filter frame
rcube_webmail.prototype.load_manageprocmailframe = function (id) {
    var win;
    if ((win = this.get_frame_window(this.env.contentframe))) {
        var lock = this.set_busy(true, 'loading');

        this.location_href($.extend({}, {
            _action:'plugin.manageprocmail-editform',
            _unlock:lock,
            _framed:1
        }, id && {
            _fid: id
        }), win);
    }
};

// load vacation frame
rcube_webmail.prototype.load_manageprocmailvacationframe = function (id) {
    var win;
    if ((win = this.get_frame_window(this.env.contentframe))) {
        var lock = this.set_busy(true, 'loading');

        this.location_href($.extend({}, {
            _action:'plugin.manageprocmail-vacation-editform',
            _unlock:lock,
            _framed:1
        }, id && {
            _fid: id
        }), win);
    }
};

rcube_webmail.prototype.update_filter_row = function(response, oldkey)
{
    var list = this.filters_list;
    var col = create_activity_circle(response.enabled) + '&nbsp;' + $('<span>').text(response.name)[0].outerHTML;

    if (list && oldkey) {
        list.update_row(oldkey, [ col ], response.id, true);
    }
    else if (list) {
        list.insert_row({
            id:'rcmrow'+response.id,
            cols:[ { className:'name', innerHTML: col } ] });
        list.select(response.id);
    }
};

rcube_webmail.prototype.update_vacation_row = function(response, oldkey)
{
    var list = this.vacations_list;
    var col = create_activity_circle(response.enabled) + '&nbsp;' + $('<span>').text(response.subject)[0].outerHTML;

    if (list && oldkey) {
        list.update_row(oldkey, [ col ], response.id, true);
    }
    else if (list) {
        list.insert_row({
            id:'rcmrow'+response.id,
            cols:[ { className:'name', innerHTML: col } ] });
        list.select(response.id);
    }
};

function create_activity_circle(enabled) {
    return $('<span>').css({
        'height': '1em',
        'width': '1em',
        'backgroundColor': enabled ? '#27ae60' : '#e74c3c',
        'borderRadius': '50%',
        'display': 'inline-block'
    })[0].outerHTML;
}