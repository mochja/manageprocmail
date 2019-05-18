<?php

require_once __DIR__ . '/vendor/autoload.php';

\Tracy\Debugger::enable(false);

function cToIngo($field)
{
    $map = [
        'header::Subject' => 'Subject',
        'header::From' => 'From',
        'header::To' => 'Destination',
        'header::Cc' => 'Cc',
        'header::ListId' => 'List-ID',
        'body::body' => 'Body',
    ];

    return $map[$field];
}

function typeToIngo($type)
{
    $map = [
        'contains' => 'contains',
        'notcontains' => 'not contain',
        'is' => 'regex',
        'notis' => 'not regex',
        'exists' => 'contains',
        'notexists' => 'not contain',
        'regex' => 'regex',
        'notregex' => 'not regex',
    ];

    return $map[$type];
}

class manageprocmail extends rcube_plugin
{

    /** @var rcmail */
    private $rc;


    /** @var \Nette\Forms\Form */
    private $form;


    private $transport;



    function init()
    {
        $this->rc = rcube::get_instance();

        $this->register_action('plugin.manageprocmail', array($this, 'manageprocmail_actions'));
        $this->register_action('plugin.manageprocmail-editform', array($this, 'manageprocmail_editform'));
        $this->register_action('plugin.manageprocmail-del', array($this, 'manageprocmail_delete'));
        $this->register_action('plugin.manageprocmail-vacation', array($this, 'manageprocmail_vacation'));

        $this->add_texts('localization/', true);

        if ($this->rc->task == 'settings') {
            $this->add_hook('settings_actions', array($this, 'settings_actions'));
        }

        $this->transport = new Ingo_Transport_Flysystem([
            'port' => 2121,

            'username' => 'admin',
            'password' => '123456',
        ]);
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
                ],
                [
                    'action' => 'plugin.manageprocmail-vacation',
                    'class' => 'vacation',
                    'label' => 'vacation',
                    'domain' => 'manageprocmail',
                    'title' => 'vacationtitle',
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
//            'is' => $this->gettext('filteris'),
//            'notis' => $this->gettext('filterisnot'),
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



    function create_form($rules)
    {
        $form = new \Nette\Forms\Form();
        $form->getElementPrototype()->setAttribute('class', 'propform');

        $form->addText('filter_name', $this->gettext('filtername'))
            ->setRequired();

        $form->addRadioList('filter_op', 'For incoming email', array_map([$this, 'gettext'], [
            '1' => 'filterallof',
            '2' => 'filteranyof',
            '0' => 'filterany',
        ]))->setDefaultValue('1');

        // rule
        $rulesContainer = $form->addContainer('rule');

        foreach ($rules as $i => $rule) {
            $container = $this->create_rule_form($rulesContainer, $i);
            $container->setDefaults([
                'rule_header' => $rule['type'],
                'rule_op' => $rule['op'],
                'rule_op_against' => $rule['against'],
            ]);
        }

        $forwardTo = $form->addCheckbox('message_action_forward_to', 'Forward To');
        $moveTo = $form->addCheckbox('message_action_move_to', 'Move To');
        $copyTo = $form->addCheckbox('message_action_copy_to', 'Copy To');

        $folders = $this->rc->get_storage()->list_folders();
        $folders = array_combine($folders, $folders);

        $copyToFolders = $form->addSelect('message_action_copy_to_folder', 'Folder', $folders);
        $copyToFolders
            ->addConditionOn($copyTo, $form::EQUAL, TRUE)
                ->setRequired();

        $moveToFolders = $form->addSelect('message_action_move_to_folder', 'Folder', $folders);
        $moveToFolders
            ->addConditionOn($moveTo, $form::EQUAL, TRUE)
                ->setRequired();

        $forwardToList = $form->addTextArea('forward_to', 'Forward To', 60, 8);
        $forwardToList
            ->addConditionOn($forwardTo, $form::EQUAL, TRUE)
                ->setRequired()
                ->addRule(function($control) {
                    $emails = explode(PHP_EOL, $control->value);

                    if (count($emails) === 0 && !empty($control->value)) {
                        return false;
                    }

                    foreach ($emails as $email) {
                        if (!\Nette\Utils\Validators::isEmail($email))
                            return false;
                    }

                    return true;
                }, 'Some of the email is not valid.')
            ->endCondition();

        $this->rc->output->add_gui_object('move_to_folder_checkbox', $moveTo->getHtmlId());
        $this->rc->output->add_gui_object('copy_to_folder_checkbox', $copyTo->getHtmlId());
        $this->rc->output->add_gui_object('copy_to_folder_list', $copyToFolders->getHtmlId());
        $this->rc->output->add_gui_object('move_to_folder_list', $moveToFolders->getHtmlId());
        $this->rc->output->add_gui_object('forward_to_checkbox', $forwardTo->getHtmlId());
        $this->rc->output->add_gui_object('forward_to_list', $forwardToList->getHtmlId());

        $form->addCheckbox('filter_active', $this->gettext('manageprocmail.active'))
            ->setDefaultValue(true);

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

        return (string) $this->form;
    }


    function generate_script()
    {
        $script = [];

        $filters = [];

        $db = $this->rc->get_dbh();
        $res = $db->query(sprintf('SELECT %s, %s, %s, %s, %s, %s, %s, %s FROM %s WHERE user_id = ?',
            'id',
            $db->quote_identifier('name'),
            $db->quote_identifier('match'),
            'forward_to',
            'copy_to',
            'move_to',
            'enabled',
            'created',
            $db->table_name($this->ID . '_filters')), $this->rc->get_user_id());
        while (($filter = $db->fetch_assoc($res))){
            $filters[] = $filter;
        }

        foreach ($filters as $filter) {
            $ruleScript = [];

            $rules = [];
            $res = $db->query(sprintf('SELECT %s, %s, %s FROM %s WHERE filter_id = ?',
                $db->quote_identifier('type'),
                $db->quote_identifier('op'),
                'against',
                $db->table_name($this->ID . '_rules')), $filter['id']);
            while (($rule = $db->fetch_assoc($res))) {
                $rules[] = $rule;
            }

            if ($filter['forward_to']) {
                $ruleScript[] = $recipe = new Ingo_Script_Procmail_Recipe(
                    array(
                        'action' => 'Ingo_Rule_System_Forward',
                        'action-value' => explode(PHP_EOL, $filter['forward_to']),
                        'disable' => !$filter['enabled']
                    ),
                    []
                );

                if ($filter['copy_to']) {
                    $recipe->addFlag('c');
                }
            }

            if ($filter['move_to'] || $filter['copy_to']) {
                $ruleScript[] = new Ingo_Script_Procmail_Recipe(
                    array(
                        'action' => 'Ingo_Rule_User_Move',
                        'action-value' => $filter['move_to'] || $filter['copy_to'],
                        'disable' => !$filter['enabled']
                    ),
                    []
                );
            }

            $initialScript = $ruleScript;

            switch ($filter['match']) {
                case '1':
                    foreach ($rules as $condition) {
                        foreach ($initialScript as $recipe) {
                            $recipe->addCondition([
                                'case' => 0,
                                'field' => cToIngo($condition['type']),
                                'match' => typeToIngo($condition['op']),
                                'value' => $condition['against'],
                            ]);
                        }
                    }
                    break;
                case '2':
                    foreach ($initialScript as $recipe) {
                        $loop = 0;
                        foreach ($rules as $condition) {
                            $clone = clone $recipe;
                            if ($loop++) {
                                $clone->addFlag('E');
                                $ruleScript[] = $clone;
                                $clone->addCondition([
                                    'case' => 0,
                                    'field' => cToIngo($condition['type']),
                                    'match' => typeToIngo($condition['op']),
                                    'value' => $condition['against'],
                                ]);
                            } else {
                                $recipe->addCondition([
                                    'case' => 0,
                                    'field' => cToIngo($condition['type']),
                                    'match' => typeToIngo($condition['op']),
                                    'value' => $condition['against'],
                                ]);
                            }
                        }
                    }
            }

            $script = array_merge($script, $ruleScript);
        }

        $res = $db->query('SELECT `id`, `from`, `to`, 
            `exceptions`, `subject`, `reason`, `ingorelist`, `days`, `enabled`
            FROM ' . $this->ID . '_vacations WHERE user_id = ? LIMIT 1', $this->rc->get_user_id());
        $vacation = $db->fetch_assoc($res);

        if ($vacation) {
            $recipe = new Ingo_Script_Procmail_Recipe(
                array(
                    'action' => 'Ingo_Rule_System_Vacation',
                    'action-value' => array(
                        'addresses' => array_column($this->rc->user->list_emails(), 'email'),
                        'subject' => $vacation['subject'],
                        'days' => $vacation['days'],
                        'reason' => $vacation['reason'],
                        'ignorelist' => $vacation['ignorelist'],
                        'excludes' => explode(PHP_EOL, $vacation['excludes']),
                        'start' => strtotime($vacation['from']),
                        'end' => strtotime($vacation['to'] . ' 23:59:59'),
                    ),
                    'disable' => !$vacation['enabled']
                ),
                []
            );
            $script[] = $recipe;
        }

        return implode(PHP_EOL, array_map(function($recipe) { return $recipe->generate(); }, $script));
    }



    function manageprocmail_editform()
    {
        $fid = rcube_utils::get_input_value('_fid', rcube_utils::INPUT_GET);

        $db = $this->rc->get_dbh();
        $res = $db->query(sprintf('SELECT %s, %s, %s, %s, %s, %s, %s, %s FROM %s WHERE user_id = ? AND id = ?',
            'id',
            $db->quote_identifier('name'),
            $db->quote_identifier('match'),
            'forward_to',
            'copy_to',
            'move_to',
            'enabled',
            'created',
            $db->table_name($this->ID . '_filters')), $this->rc->get_user_id(), $fid);
        $filter = $db->fetch_assoc($res);

        $rules = [];
        $res = $db->query(sprintf('SELECT %s, %s, %s FROM %s WHERE filter_id = ?',
            $db->quote_identifier('type'),
            $db->quote_identifier('op'),
            'against',
            $db->table_name($this->ID . '_rules')), $fid);
        while (($rule = $db->fetch_assoc($res))){
            $rules[] = $rule;
        }

        \Tracy\Debugger::barDump($rules);

        if ($fid && !$filter) {
            rcube::raise_error([
                'code' => 403,
                'message' => 'permission denied'
            ], false, true);
            return;
        }

        $this->include_script('manageprocmail.js');
        $this->include_script('netteForms.min.js');

        $this->form = $form = $this->create_form($rules ?: [[
            'type' => null,
            'op' => null,
            'against' => null,
        ]]);

        if ($fid) {
            $form->setAction($this->rc->url(array('action' => $this->rc->action, '_fid' => $fid)));
        } else {
            $form->setAction($this->rc->url(array('action' => $this->rc->action)));
        }

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

            \Tracy\Debugger::barDump($values);

            $form['message_action_move_to']->setDisabled((bool)$values['message_action_copy_to']);
            $form['message_action_copy_to']->setDisabled((bool)$values['message_action_move_to']);

            $form['message_action_copy_to_folder']
                ->setDisabled(!(bool)$values['message_action_copy_to']);
            $form['message_action_move_to_folder']
                ->setDisabled(!(bool)$values['message_action_move_to']);

            if (!$values['message_action_move_to'] && !$values['message_action_copy_to'] && !$values['message_action_forward_to']) {
                $form->addError('Please select atleast one action');
            }
        } elseif ($filter) {
            $form->setDefaults([
                'filter_name' => $filter['name'],
                'filter_op' => $filter['match'],

                'message_action_forward_to' => $filter['forward_to'] !== null,
                'forward_to' => $filter['forward_to'],
                'message_action_copy_to' => $filter['copy_to'] !== null,
                'message_action_move_to' => $filter['move_to'] !== null,

                'message_action_move_to_folder' => $filter['move_to'],
                'message_action_copy_to_folder' => $filter['copy_to'],

                'filter_active' => $filter['enabled'],
            ]);
        }

        if ($this->form->isSuccess()) {
            $values = $this->form->getValues(true);

            $sql = 'UPDATE %s SET %s = ?, %s = ?, %s = ?, %s = ?, %s = ?, %s = ? WHERE user_id = ? AND id = ?';

            if (!$fid) {
                $sql = 'INSERT INTO %s (%s, %s, %s, %s, %s, %s, user_id) VALUES (?,?,?,?,?,?,?)';
            }

            $res = $db->query(
                sprintf($sql,
                    $db->table_name($this->ID . '_filters', true),
                    $db->quote_identifier('name'),
                    $db->quote_identifier('match'),
                    $db->quote_identifier('enabled'),
                    $db->quote_identifier('move_to'),
                    $db->quote_identifier('copy_to'),
                    $db->quote_identifier('forward_to')
                ),
                $values['filter_name'],
                $values['filter_op'],
                (int) $values['filter_active'],
                $values['message_action_move_to'] ? $values['message_action_move_to_folder'] : null,
                $values['message_action_copy_to'] ? $values['message_action_copy_to_folder'] : null,
                $values['message_action_forward_to'] ? $values['forward_to'] : null,
                $this->rc->get_user_id(), $fid
            );

            if (!$res) {
                $form->addError('Could not insert rule into database');
            }

            if (!$fid) {
                $fid = $db->insert_id($this->ID . '_filters');
            }

            $res = $db->query('DELETE FROM ' . $this->ID . '_rules WHERE filter_id = ?', $fid);

            if (!$res) {
                $form->addError('Could not insert rule into database');
            }

            foreach ($values['rule'] as $rule) {
                if (!$res) break;

                $res = $db->query('INSERT INTO ' . $this->ID . '_rules (filter_id, `type`, op, against) VALUES (?,?,?,?)',
                    $fid, $rule['rule_header'], $rule['rule_op'], $rule['rule_op_against']);

                if (!$res) {
                    $form->addError('Could not insert rule into database');
                    break;
                }
            }

            \Tracy\Debugger::barDump($values);
            \Tracy\Debugger::barDump($res);

            try {
                $currentScript = $this->transport->getScript();
                $currentScript['script'] = $this->generate_script();

                $this->transport->setScriptActive($currentScript);
            } catch (Exception $e) {
                rcmail::write_log('errors', $e->getMessage());
                $form->addError('Could not update script');
                $res = false;
            }

            if ($res) {
                $this->rc->output->redirect([
                    'action' => $this->rc->action,
                    '_fid' => $fid,
                ]);
                return;
            }
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

        $this->rc->output->send('manageprocmail.manageprocmail');
    }



    function filter_frame($attrib)
    {
        return $this->rc->output->frame($attrib, true);
    }



    function filters_list($attrib)
    {
        $a_show_cols = array('name');

        $db = $this->rc->get_dbh();

        $result = $db->query(sprintf('SELECT id, %s, enabled FROM %s WHERE user_id = ?',
            $db->quote_identifier('name'),
            $db->table_name('manageprocmail_filters', true)), $this->rc->get_user_id());

        $out = $this->rc->table_output($attrib, $result, $a_show_cols, 'id');
        $this->rc->output->add_gui_object('filterslist', $attrib['id']);
        $this->rc->output->include_script('list.js');

        return $out;
    }

    function vacationform($attrib)
    {
        $db = $this->rc->get_dbh();
        $form = new \Nette\Forms\Form();
        $form->setAction($this->rc->url([
            'action' => $this->rc->action,
        ]));
        $form->getElementPrototype()
            ->addAttributes($attrib);

        $form->addText('from', 'From')
            ->getControlPrototype()
            ->setAttribute('class', 'datepicker');
        $form->addText('to', 'To')
            ->getControlPrototype()
            ->setAttribute('class', 'datepicker');

        $form->addText('subject', 'Subject');
        $form->addCheckbox('enabled', 'Enabled');
        $form->addTextArea('reason', 'Reason');

        $form->addSubmit('save', 'Save');

        if (!$form->isSubmitted()) {
            $res = $db->query('SELECT `from`, `to`, subject, reason, enabled FROM '. $this->ID .'_vacations WHERE user_id = ? LIMIT 1', $this->rc->get_user_id());
            $form->setDefaults($db->fetch_assoc($res));
        }

        if ($form->isSuccess()) {
            $values = $form->getValues(true);
            $res = $db->query(<<<SQL
INSERT INTO {$this->ID}_vacations (`from`, `to`, subject, reason, enabled, user_id)
  VALUES (?, ?, ?, ?, ?, ?)
  ON DUPLICATE KEY UPDATE
    `from` = ?,
    `to` = ?,
    `subject` = ?,
    `reason` = ?,
    `enabled` = ?;
SQL
                , $values['from'], $values['to'], $values['subject'], $values['reason'],
                $values['enabled'] ?: 0, $this->rc->get_user_id(),
                $values['from'], $values['to'], $values['subject'], $values['reason'],
                $values['enabled'] ?: 0
            );

            if (!$res) {
                $form->addError('cannot insert vacation');
                $this->rc->output->show_message('cannot store vacation', 'error');
            } else {
                $this->rc->output->show_message('saved', 'confirmation');
            }
        }

        return (string) $form;
    }

    function manageprocmail_vacation()
    {
        $this->register_handler('vacationform', [$this, 'vacationform']);

        $this->rc->output->send('manageprocmail.vacation');
    }


    function manageprocmail_delete()
    {
        $fid = rcube_utils::get_input_value('_fid', rcube_utils::INPUT_GET);

        if ($fid) {
            $db = $this->rc->get_dbh();

            $db->startTransaction();

            $res = $db->query('DELETE FROM '. $this->ID . '_filters WHERE user_id = ? AND id = ?',
                $this->rc->get_user_id(), $fid);

            try {
                $currentScript = $this->transport->getScript();
                $currentScript['script'] = $this->generate_script();

                $this->transport->setScriptActive($currentScript);
                $db->endTransaction();
            } catch (Exception $e) {
                rcmail::write_log('errors', $e->getMessage());
                $res = false;
                $db->rollbackTransaction();
            }

            if (!$res) {
                $this->rc->output->raise_error(404, 'Filter not found!');
            }
        }
    }
}
