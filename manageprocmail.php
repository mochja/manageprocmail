<?php

class manageprocmail extends rcube_plugin
{

    /** @var rcmail */
    private $rc;

    /** @var \Nette\Forms\Form */
    private $form;



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


    function create_form($attrib = []) {
        $form = new \Nette\Forms\Form();
        $form->getElementPrototype()->addAttributes($attrib);

        // rule
        $form->addRadioList('rule_type', '', [
            'all',
            'any',
            'none'
        ]);

        $form->addSelect('rule_header', null, [
            'subject',
            'sender',
        ]);

        $form->addSelect('rule_action', null, [
            'subject',
            'sender',
        ]);

        $form->addText('rule_against');

        $form->addCheckboxList('message_action', '', [
            'delete',
            'mark_as_read',
            'forward_to',
            'move_to',
            'copy_to',
        ]);

        $form->addSubmit('submit', 'Save')
            ->getControlPrototype()
            ->addAttributes([
                'class' => 'button mainaction'
            ]);

        return $form;
    }



    function formedit($attrib)
    {
        if (isset($attrib['field'])) {
            return (string) $this->form[$attrib['field']]->control;
        } else if (isset($attrib['label'])) {
            return (string) $this->form[$attrib['label']]->label;
        } else if (isset($attrib['render'])) {
            $this->form->fireRenderEvents();
            return $this->form->getRenderer()->render($this->form, $attrib['render']);
        }

        return (string) $this->form;
    }



    function manageprocmail_actions2()
    {
        Tracy\Debugger::barDump(func_get_args());

        $this->form = $this->create_form();
        $this->form->setAction($this->rc->url(array('action' => $this->rc->action)));

        if ($this->form->isSuccess()) {
            $values = $this->form->getValues(true);

            \Tracy\Debugger::barDump($values);
        }

        $this->register_handler('filterform', array($this, 'formedit'));

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


    function manageprocmail_save()
    {

    }
}
