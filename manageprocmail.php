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
        'header::ReplyTo' => 'Reply-To',
        'body::body' => 'Body',
    ];

    return $map[$field];
}

function typeToIngo($type)
{
    $map = [
        'contains' => 'contains',
        'notcontains' => 'not contain',
        'is' => 'is',
        'notis' => 'not is',
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

    /**
     * @var Latte\Engine
     */
    private $latte;


    /** @var array */
    private $params;

    private $view;



    function init()
    {
        $this->rc = rcube::get_instance();

        $this->latte = new Latte\Engine;
        $this->latte->setTempDirectory($this->rc->config->get('temp_dir', sys_get_temp_dir()));
        $this->latte->setLoader(new \Latte\Loaders\FileLoader(__DIR__ . DIRECTORY_SEPARATOR . $this->local_skin_path() . DIRECTORY_SEPARATOR . 'templates'));
        Nette\Bridges\FormsLatte\FormMacros::install($this->latte->getCompiler());

        $this->register_action('plugin.manageprocmail', array($this, 'manageprocmail_actions'));
        $this->register_action('plugin.manageprocmail-editform', array($this, 'manageprocmail_editform'));
        $this->register_action('plugin.manageprocmail-del', array($this, 'manageprocmail_delete'));
        $this->register_action('plugin.manageprocmail-vacation-del', array($this, 'manageprocmail_vacation_delete'));
        $this->register_action('plugin.manageprocmail-vacation', array($this, 'manageprocmail_vacation'));
        $this->register_action('plugin.manageprocmail-vacation-editform', array($this, 'manageprocmail_vacation_editform'));

        $this->register_action('plugin.manageprocmail-replace-script', array($this, 'manageprocmail_replace_script'));
        $this->register_action('plugin.manageprocmail-append-script', array($this, 'manageprocmail_append_script'));
        $this->register_action('plugin.manageprocmail-prened-script', array($this, 'manageprocmail_prepend_script'));

        $this->add_texts('localization/', true);

        if ($this->rc->task == 'settings') {
            $this->add_hook('settings_actions', array($this, 'settings_actions'));
        }

        $this->load_config('config.inc.php.dist');
        $this->load_config();

        $transportConfig = $this->rc->config->get('manageprocmail_transport', []);

        if ($transportConfig['username'] === null) {
            $transportConfig['username'] = $this->rc->get_user_name();
            $transportConfig['password'] = $this->rc->get_user_password();
        }

        $this->transport = new Ingo_Transport_Flysystem($transportConfig);

        $this->register_handler('template', [$this, 'render_template']);

        // Trash folder
//        \Tracy\Debugger::barDump($this->rc->config->get('trash_mbox'));

    }


    function render_template() {
        $template = $this->latte->createTemplate($this->view, $this->params);

        return $template->capture([$template, 'render']);
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
            'header::ReplyTo' => 'replyto',
            'body::body' => 'body',
        ]));

        $op = $ruleContainer->addSelect('rule_op', 'Operation', [
            'contains' => $this->gettext('filtercontains'),
            'notcontains' => $this->gettext('filternotcontains'),
            'is' => $this->gettext('filteris'),
            'notis' => $this->gettext('filterisnot'),
            'exists' => $this->gettext('filterexists'),
            'notexists' => $this->gettext('filternotexists'),
            'regex' => $this->gettext('filterregex'),
            'notregex' => $this->gettext('filternotregex'),
        ]);

        $regexValidator = function(\Nette\Forms\IControl $control) {
            return preg_match('~' . str_replace('~', '\~', $control->getValue()) . '~', null) !== false;
        };
        $ruleContainer->addText('rule_op_against', 'Rule operation against')
            ->addConditionOn($op, \Nette\Forms\Form::EQUAL, 'regex')
                ->addRule($regexValidator, 'invalid regex')
            ->addConditionOn($op, \Nette\Forms\Form::EQUAL, 'notregex')
                ->addRule($regexValidator, 'invalid regex');

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

        /** @var rcube_imap $storage */
        $storage = $this->rc->get_storage();

        $folders = array_filter($storage->list_folders(), function($folder) use ($storage) {
            \Tracy\Debugger::barDump($storage->folder_data($folder), $folder);

            return true;
        });
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


    function generate_script($currentScript = [])
    {
        if (!isset($currentScript['script'])) {
            $currentScript['script'] = '';
        }
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
            `exceptions`, `subject`, `reason`, `ignorelist`, `days`, `enabled`
            FROM ' . $this->ID . '_vacations WHERE user_id = ?', $this->rc->get_user_id());

        while (($vacation = $db->fetch_assoc($res))) {
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
        \Tracy\Debugger::barDump($vacation);

        $content = implode(PHP_EOL, array_map(function($recipe) { return $recipe->generate(); }, $script));

        $header = (new Ingo_Script_Procmail_Comment('Procmail script generated by Ingo (hash:'. md5(trim($content)) .')', false, true))->generate();
        $footer = (new Ingo_Script_Procmail_Comment('== END OF GENERATED CONTENT'))->generate();

        $script = implode(PHP_EOL, [$header, $content, $footer]);

        $contentOfCurrentScript = \Nette\Utils\Strings::normalizeNewLines($currentScript['script']);
        $lengthOfCurrentScript = strlen($contentOfCurrentScript);

        if (preg_match_all('/\(hash:([a-f0-9]{32})\) #####$/m', $contentOfCurrentScript, $matches, PREG_OFFSET_CAPTURE) !== false) {
            $contentStart = strrpos(
                $contentOfCurrentScript,
                PHP_EOL,
                ($lengthOfCurrentScript - $matches[1][0][1]) * -1
            ) ?: 0;
            $contentEnd = strpos($contentOfCurrentScript, PHP_EOL . '# == END OF GENERATED CONTENT', $contentStart);

            if ($contentStart === false || $contentEnd === false) {
                return $script;
            }

            $start = substr($currentScript['script'], 0, $contentStart);
            $end = substr($currentScript['script'], min($contentEnd + 30, $lengthOfCurrentScript));

            return (strlen($start) > 0 ? $start . PHP_EOL : '') . $script . $end;
        }

        return $script;
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
                $newEntry = true;
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

            try {
                $currentScript = $this->transport->getScript();
                $currentScript['script'] = $this->generate_script($currentScript);

                $this->transport->setScriptActive($currentScript);
            } catch (Exception $e) {
                rcmail::write_log('errors', $e->getMessage());
                $form->addError('Could not update script');
                $res = false;
            }

            if ($res) {
                $this->rc->output->show_message('successfullysaved', 'confirmation');
                $this->rc->output->command('parent.update_filter_row', [
                    'name' => $values['filter_name'],
                    'enabled' => $values['filter_active'] ?: 0,
                    'id' => $fid
                ], isset($newEntry) ? false : $fid);
            }
        }

        $this->register_handler('filterform', array($this, 'formedit'));

        $this->rc->output->send('manageprocmail.filteredit');
    }


    function check_script_presence()
    {
        $currentScript = $this->transport->getScript();

        if ($currentScript === false || empty($currentScript['script'])) {
            return true;
        }

        $script = \Nette\Utils\Strings::normalizeNewLines($currentScript['script']);

        // (hash:8e17e22990ff2191e752a2fe1baa66b9) #####
        if (preg_match_all('/\(hash:([a-f0-9]{32})\) #####$/m', $script, $matches, PREG_OFFSET_CAPTURE) !== false) {
            if (count($matches[1]) > 1) {
                // TODO: Handle error
                return false;
            }

            $hash = $matches[1][0][0];
            $contentStart = strpos($script, PHP_EOL, $matches[1][0][1]);
            $contentEnd = strpos($script, PHP_EOL . '# == END OF GENERATED CONTENT', $contentStart);
            if ($contentStart === false || $contentEnd === false) {
                // TODO: Handle error
                return false;
            }
            $content = trim(substr($script, $contentStart, $contentEnd - $contentStart));

            \Tracy\Debugger::barDump($hash);
            \Tracy\Debugger::barDump($content);
            \Tracy\Debugger::barDump(md5($content));
            if ($hash !== md5($content)) {
                // TODO: handle error
                return false;
            }
        }

        return true;
    }


    function link_handler($attrib = [])
    {
        return (string) \Nette\Utils\Html::el('a')
            ->href($this->rc->url([
                'action' => $attrib['action'],
            ]))->setText($attrib['value']);
    }


    function manageprocmail_replace_script()
    {
        try {
            $currentScript = $this->transport->getScript();
            $currentScript['script'] = $this->generate_script([
                'script' => '',
            ]);

            $this->transport->setScriptActive($currentScript);
        } catch (Exception $e) {
            rcmail::write_log('errors', $e->getMessage());
            return;
        }

        $this->rc->output->redirect([
            'action' => 'plugin.manageprocmail',
        ]);
    }


    function manageprocmail_prepend_script()
    {
        try {
            $currentScript = $this->transport->getScript();
            $currentScript['script'] = $currentScript['script'] . PHP_EOL . $this->generate_script();

            $this->transport->setScriptActive($currentScript);
        } catch (Exception $e) {
            rcmail::write_log('errors', $e->getMessage());
            return;
        }

        $this->rc->output->redirect([
            'action' => 'plugin.manageprocmail',
        ]);
    }


    function manageprocmail_append_script()
    {
        try {
            $currentScript = $this->transport->getScript();
            $currentScript['script'] = $this->generate_script() . $currentScript['script'];

            $this->transport->setScriptActive($currentScript);
        } catch (Exception $e) {
            rcmail::write_log('errors', $e->getMessage());
            return;
        }

        $this->rc->output->redirect([
            'action' => 'plugin.manageprocmail',
        ]);
    }


    function manageprocmail_actions()
    {
        // include main js script
        if ($this->rc->output->type == 'html') {
            $this->include_script('manageprocmail.js');
        }

        if (!$this->check_script_presence()) {

            $this->rc->output->add_handlers([
                'link' => [$this, 'link_handler'],
            ]);

            $this->rc->output->send('manageprocmail.scriptfail');
            return;
        }

        $this->rc->output->add_handlers(array(
            'filterslist' => array($this, 'filters_list'),
            'filterframe' => array($this, 'filter_frame'),
        ));

        $this->rc->output->send('manageprocmail.manageprocmail');
    }


    function vacation_frame($attrib)
    {
        return $this->rc->output->frame($attrib, true);
    }



    function filter_frame($attrib)
    {
        return $this->rc->output->frame($attrib, true);
    }



    function filters_list($attrib)
    {
        $a_show_cols = array('name');

        $db = $this->rc->get_dbh();

        $res = $db->query(sprintf('SELECT id, `name`, enabled FROM %s WHERE user_id = ?',
            $db->table_name($this->ID . '_filters', true)), $this->rc->get_user_id());

        $items = [];
        while ($filter = $db->fetch_assoc($res)) {
            $items[] = [
                'id' => $filter['id'],
                'name' => \Nette\Utils\Html::el('span')
                    ->setAttribute('style', 'height: 1em; width: 1em; background-color: #' . ($filter['enabled'] ? '27ae60' : 'e74c3c') . '; border-radius: 50%; display: inline-block') . '&nbsp;' . \Nette\Utils\Html::el('span')->setText($filter['name']),
            ];
        }

        $out = $this->rc->table_output($attrib, $items, $a_show_cols, 'id');
        $this->rc->output->add_gui_object('filterslist', $attrib['id']);
        $this->rc->output->include_script('list.js');

        return $out;
    }


    function vacation_list($attrib)
    {
        $a_show_cols = array('name');

        $db = $this->rc->get_dbh();

        $res = $db->query(sprintf('SELECT id, `subject`, enabled FROM %s WHERE user_id = ?',
            $db->table_name($this->ID . '_vacations', true)), $this->rc->get_user_id());

        $items = [];
        while ($filter = $db->fetch_assoc($res)) {
            $items[] = [
                'id' => $filter['id'],
                'name' => \Nette\Utils\Html::el('span')
                        ->setAttribute('style', 'height: 1em; width: 1em; background-color: #' . ($filter['enabled'] ? '27ae60' : 'e74c3c') . '; border-radius: 50%; display: inline-block') . '&nbsp;' . \Nette\Utils\Html::el('span')->setText($filter['subject']),
            ];
        }

        $out = $this->rc->table_output($attrib, $items, $a_show_cols, 'id');
        $this->rc->output->add_gui_object('vacationslist', $attrib['id']);
        $this->rc->output->include_script('list.js');

        return $out;
    }

    function vacationform($id)
    {
        $db = $this->rc->get_dbh();
        $form = new \Nette\Forms\Form();
        if ($id) {
            $form->setAction($this->rc->url(array('action' => $this->rc->action, '_fid' => $id)));
        } else {
            $form->setAction($this->rc->url(array('action' => $this->rc->action)));
        }

        $form->addText('from', 'From')
            ->getControlPrototype()
            ->setAttribute('class', 'datepicker');
        $form->addText('to', 'To')
            ->getControlPrototype()
            ->setAttribute('class', 'datepicker');

        $form->addText('subject', 'Subject');
        $form->addCheckbox('enabled', 'Enabled');
        $form->addTextArea('reason', 'Reason', 80, 15);

        $form->addSubmit('save', 'Save');

        if (!$form->isSubmitted()) {
            $res = $db->query('SELECT `from`, `to`, subject, reason, enabled FROM '. $this->ID .'_vacations WHERE user_id = ? AND id = ? LIMIT 1', $this->rc->get_user_id(), $id);
            $vacation = $db->fetch_assoc($res);
            if (!$vacation && $id) {
                rcube::raise_error([
                    'code' => 403,
                    'message' => 'permission denied'
                ], false, true);

                return;
            }
            $form->setDefaults($vacation ?: []);
        }

        if ($form->isSuccess()) {
            $values = $form->getValues(true);
            $db->startTransaction();

            if ($id) {
                $sql = <<<SQL
UPDATE {$this->ID}_vacations SET 
    `from` = ?, `to` = ?, subject = ?, reason = ?, enabled = ? WHERE user_id = ? AND id = ?
SQL;
            } else {
                $sql = <<<SQL
INSERT INTO {$this->ID}_vacations (`from`, `to`, subject, reason, enabled, user_id, id) VALUES (?, ?, ?, ?, ?, ?, ?)
SQL;
            }

            $res = $db->query($sql, $values['from'], $values['to'], $values['subject'], $values['reason'],
                $values['enabled'] ?: 0, $this->rc->get_user_id(), $id
            );

            $newId = $db->insert_id("{$this->ID}_vacations");

            try {
                $currentScript = $this->transport->getScript();
                $currentScript['script'] = $this->generate_script($currentScript);

                $this->transport->setScriptActive($currentScript);
                $db->endTransaction();
            } catch (Exception $e) {
                rcmail::write_log('errors', $e->getMessage());
                $res = false;
            }

            if (!$res) {
                $db->rollbackTransaction();
                $form->addError('cannot insert vacation');
                $this->rc->output->show_message('cannot store vacation', 'error');
            } else {
                $this->rc->output->show_message('successfullysaved', 'confirmation');
                $this->rc->output->command('parent.update_vacation_row', [
                    'subject' => $values['subject'],
                    'enabled' => $values['enabled'] ?: 0,
                    'id' => $newId ?: $id
                ], $id);
            }
        }

        return $form;
    }

    function manageprocmail_vacation()
    {
        if ($this->rc->output->type == 'html') {
            $this->include_script('manageprocmail.js');
        }

        if (!$this->check_script_presence()) {

            $this->rc->output->add_handlers([
                'link' => [$this, 'link_handler'],
            ]);

            $this->rc->output->send('manageprocmail.scriptfail');
            return;
        }

        $this->rc->output->add_handlers(array(
            'vacationslist' => array($this, 'vacation_list'),
            'vacationframe' => array($this, 'vacation_frame'),
        ));

        $this->rc->output->send('manageprocmail.vacation');
    }


    function manageprocmail_vacation_editform()
    {
        $fid = rcube_utils::get_input_value('_fid', rcube_utils::INPUT_GET);

        $this->view = 'vacation.latte';
        $this->params = [
            'vacationForm' => $this->vacationform($fid),
        ];

        $this->rc->output->send('manageprocmail.vacationform');
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
                $currentScript['script'] = $this->generate_script($currentScript);

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


    function manageprocmail_vacation_delete()
    {
        $fid = rcube_utils::get_input_value('_fid', rcube_utils::INPUT_GET);

        if ($fid) {
            $db = $this->rc->get_dbh();

            $db->startTransaction();

            $res = $db->query('DELETE FROM '. $this->ID . '_vacations WHERE user_id = ? AND id = ?',
                $this->rc->get_user_id(), $fid);

            try {
                $currentScript = $this->transport->getScript();
                $currentScript['script'] = $this->generate_script($currentScript);

                $this->transport->setScriptActive($currentScript);
                $db->endTransaction();
            } catch (Exception $e) {
                rcmail::write_log('errors', $e->getMessage());
                $res = false;
                $db->rollbackTransaction();
            }

            if (!$res) {
                $this->rc->output->raise_error(404, 'Vacation not found!');
            }
        }
    }
}
