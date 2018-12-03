<?php

class manageprocmail extends rcube_plugin
{
    private $rc;
  function init()
  {
    $this->rc = rcube::get_instance();

    $this->register_action('plugin.manageprocmail', array($this, 'manageprocmail_actions'));

    if ($this->rc->task == 'settings') {
        $this->add_hook('settings_actions', array($this, 'settings_actions'));
    }
  }

  function settings_actions($args) {
    return array_merge($args, [ 'actions' => array_merge($args['actions'], [
            [
                'action' => 'plugin.manageprocmail',
                'class'  => 'filter',
                'label'  => 'filters',
                'domain' => 'manageprocmail',
                'title'  => 'filterstitle',
            ]
        ])
    ]);
  }

  function manageprocmail_actions() {
    $this->rc->output->add_handlers(array(
      'filterslist'      => array($this, 'filters_list'),
      'filterframe'      => array($this, 'filter_frame'),
    ));

    $result = $this->list_rules();

    $this->rc->output->command('manageprocmail_listupdate', 'list', array('list' => $result));
    // $this->rc->output->set_pagetitle($this->plugin->gettext('filters'));
    $this->rc->output->send('manageprocmail.manageprocmail');
  }

    function filter_frame($attrib)
    {
        return $this->rc->output->frame($attrib, true);
    }

  function filters_list($attrib) {
       $a_show_cols = array('name');

        $result = $this->list_rules();

        $out = $this->rc->table_output($attrib, $result, $a_show_cols, 'id');

        return $out;
  }

  function list_rules() {
    return [
      ['id' => '12', 'name' => 'Rule A', 'class' => '']
    ];
  }
}
