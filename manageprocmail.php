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

        $recipe = new Ingo_Script_Procmail_Recipe(
            array(
                'action' => 'Ingo_Rule_System_Vacation',
                'action-value' => array(
                    'addresses' => ['a@a.com'],
                    'subject' => 'aaaa',
                    'days' => 2,
                    'reason' => 'sdfgadfasdf %STARTDATE%',
                    'ignorelist' => [],
                    'excludes' => ['a@b.com'],
                    'start' => time(),
                    'end' => time()
                ),
                'disable' => 0
            ),
            []
        );

        Tracy\Debugger::barDump($recipe->generate());
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


    function create_form($rules = []) {
        $form = new \Nette\Forms\Form();
        $form->getElementPrototype()->setAttribute('class', 'propform');

        $form->addRadioList('filter_op', 'Match all incoming emails by following', [
            'all',
            'any',
            'none'
        ]);

        // rule
        $rulesContainer = $form->addContainer('rule');

        foreach ($rules as $i => $rule) {
            $ruleContainer = $rulesContainer->addContainer($i);

            $ruleContainer->addSelect('rule_header', 'Header', [
                'subject',
                'sender',
            ]);

            $ruleContainer->addSelect('rule_op', 'Operation', [
                '==',
                '!=',
            ]);

            $ruleContainer->addText('rule_op_against', 'Rule operation against');

            $ruleContainer->addButton('remove', 'X');
        }

        $form->addCheckbox('message_action_delete', 'Delete');
        $form->addCheckbox('message_action_mark_as_read', 'Mark as Read');
        $form->addCheckbox('message_action_forward_to', 'Forward To');
        $form->addCheckbox('message_action_move_to', 'Move To');
        $form->addCheckbox('message_action_copy_to', 'Copy To');


        $form->addCheckbox('filter_active', $this->gettext('manageprocmail.active'));

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
            $component = $this->form;
            foreach (explode('.', $attrib['field']) as $path) {
                $component = $component[$path];
            }

            if ($component instanceof Nette\Forms\Container) {
                $table = \Nette\Utils\Html::el('table');
                $table->setAttribute('class', 'propform');

                foreach ($component->getComponents(false, \Nette\Forms\Container::class) as $c) {
                    $row = \Nette\Utils\Html::el('tr');
                    $row->addHtml(\Nette\Utils\Html::el('td')->addText('Rule ' . ($c->getName() + 1)));
                    foreach ($c->getControls() as $ctr) {
                        $row->addHtml(\Nette\Utils\Html::el('td')->addHtml($ctr->getControl()));
                    }
                    $table->addHtml($row);
                }

                return (string) $table;
            }

            return $component->control;
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

        $this->form = $form = $this->create_form([
            1, 2, 3
        ]);

        $renderer = $form->getRenderer();

        $this->form->setAction($this->rc->url(array('action' => $this->rc->action)));

        if ($this->form->isSuccess()) {
            $values = $this->form->getValues(true);

            \Tracy\Debugger::barDump($values);
            $values = $form->getHttpData($form::DATA_TEXT, 'sel[]');
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
