<?php

class manageprocmail extends rcube_plugin
{
    private $rc;
  function init()
  {
    $this->rc = rcube::get_instance();

    $this->register_action('plugin.manageprocmail', array($this, 'manageprocmail_actions'));
    
    $this->register_action('plugin.manageprocmail-action', array($this, 'manageprocmail_actions2'));


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

  function formedit($attrib) {
    $attrib += ['id' => '123asdfasd'];

        $input = new html_inputfield(array('type' => 'text', 'name' => '_name',
            'id' => 'rcmanageprocmalx', 'size' => 30));

         $this->rc->output->add_gui_object('filterform', $attrib['id']);

        $out = $this->rc->output->form_tag(array(
            'action'  => $this->rc->url(array('action' => $this->rc->action, 'a' => 'import')),
            'method'  => 'post',
            'enctype' => 'multipart/form-data') + $attrib,
            $input->show()
        );

        return $out;
  }

  function manageprocmail_actions2() {

            $this->rc->output->add_handlers(array(
      'filterform'      => array($this, 'formedit')));

      $this->rc->output->send('manageprocmail.filteredit');
  }

  function manageprocmail_actions() {
    $this->rc->output->add_handlers(array(
      'filterslist'      => array($this, 'filters_list'),
      'filterframe'      => array($this, 'filter_frame'),
    ));

            // include main js script
        if ($this->rc->output->type == 'html') {
            $this->include_script('manageprocmail.js');
        }

    $result = $this->list_rules();

    // $this->rc->output->command('manageprocmail_listupdate', 'list', array('list' => $result));
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
        $this->rc->output->add_gui_object('filterslist', $attrib['id']);
            $this->rc->output->include_script('list.js');


        return $out;
  }

  function list_rules() {
    return [
      ['id' => '12', 'name' => 'Rule A', 'class' => '']
    ];
  }
}
