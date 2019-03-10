<?php

class manageprocmail extends rcube_plugin
{

    /** @var rcmail */
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



    function settings_actions($args)
    {
        return array_merge($args, [
            'actions' => array_merge($args['actions'], [
                [
                    'action' => 'plugin.manageprocmail',
                    'class' => 'filter',
                    'label' => 'filters',
                    'domain' => 'manageprocmail',
                    'title' => 'filterstitle',
                ]
            ])
        ]);
    }



    function formedit($attrib)
    {
        /** @var rcmail_output_html $output */
        $output = $this->rc->output;
        $attrib += ['id' => 'rule_1'];

        $form = new \Nette\Forms\Form();
        $form->getElementPrototype()->addAttributes($attrib);

        // rule
        $form->addRadioList('rule_type', '', [
            'all',
            'any',
            'none'
        ]);

        $form->addSelect('header', null, [
            'subject',
            'sender',
        ]);

        $form->addSelect('action', null, [
            'subject',
            'sender',
        ]);

        $form->addText('against');

        // rule end

        $form->addCheckboxList('actions', '', [
            'delete',
            'mark_as_read',
            'forward_to',
            'move_to',
            'copy_to',
        ]);

        $output->add_gui_object('filterform', $attrib['id']);

        $form->setAction($this->rc->url(array('action' => $this->rc->action, 'a' => 'import')));

        return (string) $form;
    }



    function manageprocmail_actions2()
    {

        $this->rc->output->add_handlers(array(
            'filterform' => array($this, 'formedit')));

        $this->rc->output->send('manageprocmail.filteredit');
    }



    function manageprocmail_actions()
    {
        $this->rc->output->add_handlers(array(
            'filterslist' => array($this, 'filters_list'),
            'filterframe' => array($this, 'filter_frame'),
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



    function filters_list($attrib)
    {
        $a_show_cols = array('name');

        $result = $this->list_rules();

        $out = $this->rc->table_output($attrib, $result, $a_show_cols, 'id');
        $this->rc->output->add_gui_object('filterslist', $attrib['id']);
        $this->rc->output->include_script('list.js');


        return $out;
    }



    function list_rules()
    {
        return [
            ['id' => '12', 'name' => 'Rule A', 'class' => '']
        ];
    }
}
