<?php
/**
 * Copyright 2003-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (ASL).  If you
 * did not receive this file, see http://www.horde.org/licenses/apache.
 *
 * @author   Ben Chavet <ben@horde.org>
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/apache ASL
 * @package  Ingo
 */

/**
 * The Ingo_Script_Procmail_Recipe class represents a Procmail recipe.
 *
 * @author   Ben Chavet <ben@horde.org>
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/apache ASL
 * @package  Ingo
 */
class Ingo_Script_Procmail_Recipe implements Ingo_Script_Item
{
    /**
     */
    protected $_action = array();

    /**
     */
    protected $_conditions = array();

    /**
     */
    protected $_disable = '';

    /**
     */
    protected $_flags = [];

    /**
     */
    protected $_params = array(
        'stat' => 'stat',
        'date' => 'date',
        'echo' => 'echo',
        'ls'   => 'ls'
    );

    /**
     */
    protected $_valid = true;


    protected $_level = 0;

    /**
     * Constructs a new procmail recipe.
     *
     * @param array $params        Array of parameters.
     *                               REQUIRED FIELDS:
     *                                'action'
     *                               OPTIONAL FIELDS:
     *                                'action-value' (only used if the
     *                                'action' requires it)
     *                                'disable'
     * @param array $scriptparams  Array of parameters passed to
     *                             Ingo_Script_Procmail.
     */
    public function __construct($params = array(), $scriptparams = array())
    {
        $this->_disable = !empty($params['disable']);
        $this->_params = array_merge($this->_params, $scriptparams);

        $delivery = '';
        if (isset($this->_params['transport'][Ingo::RULE_ALL]['params']['delivery_agent'])) {
            $delivery = '| ' .  $this->_params['transport'][Ingo::RULE_ALL]['params']['delivery_agent'] . ' ';
        }
        if (isset($this->_params['transport'][Ingo::RULE_ALL]['params']['delivery_mailbox_prefix'])) {
            $delivery .= ' ' . $this->_params['transport'][Ingo::RULE_ALL]['params']['delivery_mailbox_prefix'];
        }
        if (!empty($this->_params['transport'][Ingo::RULE_ALL]['params']['date'])) {
            $this->_params['date'] = $this->_params['transport'][Ingo::RULE_ALL]['params']['date'];
        }
        if (!empty($this->_params['transport'][Ingo::RULE_ALL]['params']['echo'])) {
            $this->_params['echo'] = $this->_params['transport'][Ingo::RULE_ALL]['params']['echo'];
        }
        if (!empty($this->_params['transport'][Ingo::RULE_ALL]['params']['ls'])) {
            $this->_params['ls'] = $this->_params['transport'][Ingo::RULE_ALL]['params']['ls'];
        }
        switch ($params['action']) {
        case 'Ingo_Rule_Noop':
            $this->_action[] = '{ }';
            break;
        case 'Ingo_Rule_Mark_As_Read':
            $this->_action[] = '| formail -i "Status: RO"';
            $this->addFlag('W');
            break;
        case 'Ingo_Rule_User_Move':
            $this->_action[] = $delivery
                .= $this->procmailPath($params['action-value']);
            break;

        case 'Ingo_Rule_System_Vacation':
            $days = $params['action-value']['days'];
            $this->_action[] = '{';
            foreach ($params['action-value']['addresses'] as $address) {
                if (empty($address)) {
                    continue;
                }
                $this->_action[] = '  :0';
                $this->_action[] = '  * ^TO_' . $address;
                $this->_action[] = '  {';
                $this->_action[] = '    DATE=`' . $this->_params['date']
                    . ' +%s`';
                if ($days) {
                    $this->_action[] =
                        '    FILEDATE=`test -f ${VACATION_DIR:-.}/\'.vacation-'. $params['action-value']['id'] .'.'
                        . $address . '\' && '
                        . $this->_params['date']
                        . ' -r ${VACATION_DIR:-.}/\'.vacation-'. $params['action-value']['id'] .'.'
                        . $address . '\' +%s | '
                        . 'awk \'{ print $1 + (' . $days * 86400 . ') }\'`';
                    $this->_action[] =
                        '    DUMMY=`test -f ${VACATION_DIR:-.}/\'.vacation-'. $params['action-value']['id'] .'.'
                        . $address . '\' && '
                        . 'test $FILEDATE -le $DATE && '
                        . 'rm ${VACATION_DIR:-.}/\'.vacation-'. $params['action-value']['id'] .'.' . $address . '\'`';
                }
                if (!empty($params['action-value']['start'])) {
                    $this->_action[] = '    START='
                        . $params['action-value']['start'];
                }
                if (!empty($params['action-value']['end'])) {
                    $this->_action[] = '    END='
                        . $params['action-value']['end'];
                }
                $this->_action[] = '';
                $this->_action[] = '    :0 h';
                $this->_action[] = '    SUBJECT=| formail -xSubject:';
                $this->_action[] = '';
                $this->_action[] =
                    '    :0 Wc: ${VACATION_DIR:-.}/vacation-'. $params['action-value']['id'] .'.lock';
                if (!empty($params['action-value']['start']) ||
                    !empty($params['action-value']['end'])) {
                    $test = '    * ?';
                    if (!empty($params['action-value']['start'])) {
                        $test .= ' test $DATE -gt $START';
                    }
                    if (!empty($params['action-value']['start']) &&
                        !empty($params['action-value']['end'])) {
                        $test .= ' &&';
                    }
                    if (!empty($params['action-value']['end'])) {
                        $test .= ' test $END -gt $DATE';
                    }
                    $this->_action[] = $test;
                }
                $this->_action[] = '    {';
                $this->_action[] = '      :0 Wh';
                $this->_action[] = '      * ^TO_' . $address;
                $this->_action[] = '      * !^X-Loop: ' . $address;
                $this->_action[] = '      * !^X-Spam-Flag: YES';
                if (count($params['action-value']['excludes']) > 0) {
                    foreach ($params['action-value']['excludes'] as $exclude) {
                        if (!empty($exclude)) {
                            $this->_action[] = '      * !^From.*' . $exclude;
                        }
                    }
                }
                if ($params['action-value']['ignorelist']) {
                    $this->_action[] = '      * !^FROM_DAEMON';
                }
                $this->_action[] =
                    '      | formail -rD 8192 ${VACATION_DIR:-.}/.vacation-'. $params['action-value']['id'] .'.'
                    . $address;
                $this->_action[] = '      :0 eh';
                $this->_action[] = '      | (formail -rI"Precedence: junk" \\';
                $this->_action[] = '       -a"From: <' . $address . '>" \\';
                $this->_action[] = '       -A"X-Loop: ' . $address . '" \\';
                $reason = Ingo_Rule_System_Vacation::vacationReason(
                    $params['action-value']['reason'],
                    $params['action-value']['start'],
                    $params['action-value']['end']
                );

                $rcube = rcube::get_instance();

                if (rcube_charset::detect($reason, $rcube->config->get('default_charset', RCUBE_CHARSET)) === 'UTF-8') {
                    $this->_action[] = '       -i"Subject: '
                        . Mail_mimePart::encodeHeader('Subject',
                            $params['action-value']['subject'], 'UTF-8')
                        . '" \\';
                    $this->_action[] =
                        '       -i"Content-Transfer-Encoding: quoted-printable" \\';
                    $this->_action[] =
                        '       -i"Content-Type: text/plain; charset=UTF-8" ; \\';
                    $reason = Mail_mimePart::encodeQP($reason);
                } else {
                    $this->_action[] = '       -i"Subject: '
                        . Mail_mimePart::encodeHeader('Subject',
                            $params['action-value']['subject'])
                        . '" ; \\';
                }
                $reason = addcslashes($reason, "\\\n\r\t\"`");
                $this->_action[] = '       ' . $this->_params['echo']
                    . ' -e "' . $reason . '" \\';
                $this->_action[] = '      ) | $SENDMAIL -f' . $address
                    . ' -oi -t';
                $this->_action[] = '      :0';
                $this->_action[] = '      /dev/null';
                $this->_action[] = '    }';
                $this->_action[] = '  }';
            }
            $this->_action[] = '}';
            break;

        case 'Ingo_Rule_System_Forward':
            /* Make sure that we prevent mail loops using 3 methods.
             *
             * First, we call sendmail -f to set the envelope sender to be the
             * same as the original sender, so bounces will go to the original
             * sender rather than to us.  This unfortunately triggers lots of
             * Authentication-Warning: messages in sendmail's logs.
             *
             * Second, add an X-Loop header, to handle the case where the
             * address we forward to forwards back to us.
             *
             * Third, don't forward mailer daemon messages (i.e., bounces).
             * Method 1 above should make this redundant, unless we're sending
             * mail from this account and have a bad forward-to account.
             *
             * Get the from address, saving a call to formail if possible.
             * The procmail code for doing this is borrowed from the
             * Procmail Library Project, http://pm-lib.sourceforge.net/.
             * The Ingo project has the permission to use Procmail Library code
             * under Apache licence v 1.x or any later version.
             * Permission obtained 2006-04-04 from Author Jari Aalto. */
            $this->_action[] = '{';
            $this->_action[] = '  :0 ';
            $this->_action[] = '  *$ ! ^From *\/[^  ]+';
            $this->_action[] = '  *$ ! ^Sender: *\/[^   ]+';
            $this->_action[] = '  *$ ! ^From: *\/[^     ]+';
            $this->_action[] = '  *$ ! ^Reply-to: *\/[^     ]+';
            $this->_action[] = '  {';
            $this->_action[] = '    OUTPUT = `formail -zxFrom:`';
            $this->_action[] = '  }';
            $this->_action[] = '  :0 E';
            $this->_action[] = '  {';
            $this->_action[] = '    OUTPUT = $MATCH';
            $this->_action[] = '  }';
            $this->_action[] = '';

            /* Forward to each address on our list. */
            foreach ($params['action-value'] as $address) {
                if (!empty($address)) {
                    $this->_action[] = '  :0 c';
                    $this->_action[] = '  * !^FROM_MAILER';
                    $this->_action[] = '  * !^X-Loop: to-' . $address;
                    $this->_action[] = '  | formail -A"X-Loop: to-' . $address
                        . '" | $SENDMAIL -oi -f $OUTPUT ' . $address;
                }
            }

            /* In case of mail loop or bounce, store a copy locally.  Note
             * that if we forward to more than one address, only a mail loop
             * on the last address will cause a local copy to be saved.  TODO:
             * The next two lines are redundant (and create an extra copy of
             * the message) if "Keep a copy of messages in this account" is
             * checked. */
            $this->_action[] = '  :0 E'
                . (isset($this->_params['delivery_agent']) ? 'w' : '');
            $this->_action[] = '  ' . $delivery . '$DEFAULT';
            $this->_action[] = '  :0 ';
            $this->_action[] = '  /dev/null';
            $this->_action[] = '}';
            break;

        default:
            $this->_valid = false;
            break;
        }
    }

    /**
     * Adds a flag to the recipe.
     *
     * @param string $flag  String of flags to append to the current flags.
     */
    public function addFlag($flag)
    {
        $this->_flags[] = $flag;
    }

    public function removeFlag($flag)
    {
        $this->_flags = array_diff($this->_flags, [$flag]);
    }

    /**
     * Adds a condition to the recipe.
     *
     * @param array $condition  Array of parameters. Required keys are 'field'
     *                          and 'value'. 'case' is an optional key.
     */
    public function addCondition($condition = array())
    {
//        $flag = !empty($condition['case']) ? 'D' : '';
        $match = isset($condition['match']) ? $condition['match'] : null;
        $string = '';
        $prefix = '';

        switch ($condition['field']) {
        case 'Destination':
            $string = '^TO_';
            break;

        case 'Body':
            $string .= 'B ?? ';
            break;

        default:
            // convert 'field' to PCRE pattern matching
            if (!strpos($condition['field'], ',')) {
                $string = '^' . $condition['field'] . ':';
            } else {
                $string .= '^(' . str_replace(',', '|', $condition['field']) . '):';
            }
            $prefix = ' ';
        }

        $reverseCondition = false;
        switch ($match) {
        case 'not regex':
            $reverseCondition = true;
            // fall through
        case 'regex':
            $string .= $prefix . $condition['value'];
        break;

        case 'not is':
            $reverseCondition = true;
        case 'is':
            $string .= '\ *' .preg_quote($condition['value']) . '$';
            break;
        case 'address':
            $string .= '(.*\<)?' . preg_quote($condition['value']);
            break;

        case 'not begins with':
            $reverseCondition = true;
            // fall through
        case 'begins with':
            $string .= $prefix . preg_quote($condition['value']);
            break;

        case 'not ends with':
            $reverseCondition = true;
            // fall through
        case 'ends with':
            $string .= '.*' . preg_quote($condition['value']) . '$';
            break;

        case 'not contain':
            $reverseCondition = true;
            // fall through
        case 'contains':
        default:
            $string .= '.*' . preg_quote($condition['value']);
            break;
        }

        $this->_conditions[] = array('condition' => ($reverseCondition ? '* !' : '* ') . $string);
    }

    /**
     * Generates procmail code to represent the recipe.
     *
     * @return string  Procmail code to represent the recipe.
     */
    public function generate($opts = [])
    {
        $nest = 0;
        $prefix = '';
        $text = array();

        if (!$this->_valid) {
            return '';
        }

        // Set the global flags for the whole rule, each condition
        // will add its own (such as Body or Case Sensitive)
        $global = $this->getFlags();
        $text[] = ':0 ' . $global . (isset($this->_params['delivery_agent']) ? 'w' : '');
        foreach ($this->_conditions as $condition) {
            $text[] = $condition['condition'];
        }

        if (--$nest > 0) {
            $prefix = str_repeat('  ', $nest);
        }
        foreach ($this->_action as $val) {
            $text[] = $prefix . $val;
        }

        for ($i = $nest; $i > 0; $i--) {
            $text[] = str_repeat('  ', $i - 1) . '}';
        }

        if ($this->_level) {
            $text = array_map(function ($line) use ($opts) {
                return str_repeat('  ', $opts['level']) . $line;
            }, $text);
        }

        if ($this->_disable) {
            $code = '';
            foreach ($text as $val) {
                $comment = new Ingo_Script_Procmail_Comment($val);
                $code .= $comment->generate() . "\n";
            }
            return $code;
        }

        return implode("\n", $text);
    }

    /**
     * Returns a procmail-ready mailbox path, converting IMAP folder
     * pathname conventions as necessary.
     *
     * @param string $folder  The IMAP folder name.
     *
     * @return string  The procmail mailbox path.
     */
    public function procmailPath($folder)
    {
        /* NOTE: '$DEFAULT' here is a literal, not a PHP variable. */
        if (empty($folder) || ($folder == 'INBOX')) {
            return '$DEFAULT';
        }
        if (isset($this->_params)) {
            if ($this->_params['path_style'] == 'maildir') {
                if (substr($folder, 0, 6) == 'INBOX.') {
                    $folder = substr($folder, 6);
                }
                $mbox = rcube_charset::convert($folder, RCUBE_CHARSET, 'UTF7-IMAP');
                return '".' . $mbox . '/"';
            }
            if ($this->_params['path_style'] == 'mboxutf7') {
                $mbox = rcube_charset::convert($folder, RCUBE_CHARSET, 'UTF7-IMAP');
                return '"' . $mbox . '"';
            }
        }
        return str_replace(' ', '\ ', escapeshellcmd($folder));
    }

    function isDisabled()
    {
        return $this->_disable;
    }

    /**
     * @return int
     */
    public function getLevel()
    {
        return $this->_level;
    }

    /**
     * @param int $level
     */
    public function setLevel($level)
    {
        $this->_level = $level;
    }

    public function getFlags()
    {
        return implode('', $this->_flags);
    }
}
