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

        $this->add_texts('localization/', true);

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
                    'ignorelist' => 1,
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



    function create_rule_form(\Nette\Forms\Container $rulesContainer, $i)
    {
        $ruleContainer = $rulesContainer->addContainer($i);

        $ruleContainer->addSelect('rule_header', 'Header', array_map([$this, 'gettext'], [
            'header::Subject' => 'subject',
            'header::From' => 'from',
            'header::To' => 'to',
            'header::Cc' => 'cc',
            'header::ListId' => 'listid',
            'body::body' => 'body',
        ]));

        $ruleContainer->addSelect('rule_op', 'Operation', [
            'contains' => $this->gettext('filtercontains'),
            'notcontains' => $this->gettext('filternotcontains'),
            'is' => $this->gettext('filteris'),
            'notis' => $this->gettext('filterisnot'),
            'exists' => $this->gettext('filterexists'),
            'notexists' => $this->gettext('filternotexists'),
            'regex' => $this->gettext('filterregex'),
            'notregex' => $this->gettext('filternotregex'),
        ]);

        $ruleContainer->addText('rule_op_against', 'Rule operation against');

        $ruleContainer->addButton('remove', 'X')
            ->getControlPrototype()
            ->setAttribute('class', 'button rule-remove-btn');

        return $ruleContainer;
    }



    function create_form($rules = [])
    {
        $form = new \Nette\Forms\Form();
        $form->getElementPrototype()->setAttribute('class', 'propform');

        $form->addText('filter_name', $this->gettext('filtername'))
            ->setRequired();

        $form->addRadioList('filter_op', 'For incoming email', array_map([$this, 'gettext'], [
            'all' => 'filterallof',
            'any' => 'filteranyof',
            'none' => 'filterany',
        ]));

        // rule
        $rulesContainer = $form->addContainer('rule');

        foreach ($rules as $i => $rule) {
            $this->create_rule_form($rulesContainer, $i);
        }

        $forwardTo = $form->addCheckbox('message_action_forward_to', 'Forward To');
        $moveTo = $form->addCheckbox('message_action_move_to', 'Move To');
        $moveTo->setDisabled(false);
        $copyTo = $form->addCheckbox('message_action_copy_to', 'Copy To');
        $copyTo->setDisabled(false);

        $moveToFolders = $form->addSelect('message_action_move_to_folder', 'Folder', $this->rc->get_storage()->list_folders());
        $moveToFolders
            ->setDisabled()
            ->addConditionOn($moveTo, $form::EQUAL, TRUE)
            ->addRule($form::FILLED);

        $copyToFolders = $form->addSelect('message_action_copy_to_folder', 'Folder', $this->rc->get_storage()->list_folders());
        $copyToFolders
            ->setDisabled()
            ->addConditionOn($copyTo, $form::EQUAL, TRUE)
            ->addRule($form::FILLED);

        $forwardToList = $form->addTextArea('forward_to', 'Forward To', 60, 8);
        $forwardToList
            ->addConditionOn($forwardTo, $form::EQUAL, TRUE)
            ->addRule($form::FILLED);

        $this->rc->output->add_gui_object('move_to_folder_checkbox', $moveTo->getHtmlId());
        $this->rc->output->add_gui_object('copy_to_folder_checkbox', $copyTo->getHtmlId());
        $this->rc->output->add_gui_object('copy_to_folder_list', $copyToFolders->getHtmlId());
        $this->rc->output->add_gui_object('move_to_folder_list', $moveToFolders->getHtmlId());
        $this->rc->output->add_gui_object('forward_to_checkbox', $forwardTo->getHtmlId());
        $this->rc->output->add_gui_object('forward_to_list', $forwardToList->getHtmlId());

        $form->addCheckbox('filter_active', $this->gettext('manageprocmail.active'));

        $form->addSubmit('submit', 'Save')
            ->getControlPrototype()
            ->addAttributes([
                'class' => 'button mainaction',
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
                $table->setAttribute('class', 'propform frm-' . $component->getName());

                foreach ($component->getComponents(false, \Nette\Forms\Container::class) as $c) {
                    $row = \Nette\Utils\Html::el('tr');
                    $row->addHtml(\Nette\Utils\Html::el('td'));
                    foreach ($c->getControls() as $ctr) {
                        $row->addHtml(\Nette\Utils\Html::el('td')->addHtml($ctr->getControl()));
                    }
                    $table->addHtml($row);
                }

                return (string)$table;
            }

            return $component->control;
        } else if (isset($attrib['label'])) {
            return (string)$this->form[$attrib['label']]->label;
        } else if (isset($attrib['render'])) {
            $this->form->fireRenderEvents();
            return $this->form->getRenderer()->render($this->form, $attrib['render']);
        }

        return (string)$this->form;
    }



    function manageprocmail_actions2()
    {
        $this->include_script('manageprocmail.js');
        $this->include_script('netteForms.min.js');

        $this->form = $form = $this->create_form([1]);

        if ($form->isSubmitted()) {
            $values = $form->getHttpData();
            $receivedRules = array_keys($values[$form['rule']->getName()]);

            foreach ($receivedRules as $rule) {
                $ruleContainer = $form['rule']->getComponent($rule, false);

                if (!$ruleContainer) {
                    $this->create_rule_form($form['rule'], $rule);
                }
            }

            $toRemove = [];
            foreach ($form['rule']->getComponents() as $ruleContainer) {
                if (!in_array($ruleContainer->getName(), $receivedRules)) {
                    $toRemove[] = $ruleContainer;
                }
            }

            foreach ($toRemove as $ruleContainer) {
                $form['rule']->removeComponent($ruleContainer);
            }
        }

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
