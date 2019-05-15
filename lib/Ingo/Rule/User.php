<?php
/**
 * Copyright 2015-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (ASL).  If you
 * did not receive this file, see http://www.horde.org/licenses/apache.
 *
 * @category  Horde
 * @copyright 2015-2017 Horde LLC
 * @license   http://www.horde.org/licenses/apache ASL
 * @package   Ingo
 */

/**
 * User-defined rule.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2015-2017 Horde LLC
 * @license   http://www.horde.org/licenses/apache ASL
 * @package   Ingo
 *
 * @property-read boolean $has_flags  True if the rule has any flags set.
 */
class Ingo_Rule_User
extends Ingo_Rule
{
    const COMBINE_ALL = 1;
    const COMBINE_ANY = 2;

    const FLAG_ANSWERED = 1;
    const FLAG_DELETED = 2;
    const FLAG_FLAGGED = 4;
    const FLAG_SEEN = 8;
    const FLAG_AVAILABLE = 16;

    const TEST_HEADER = 1;
    const TEST_SIZE = 2;
    const TEST_BODY = 3;

    const TYPE_TEXT = 1;
    const TYPE_MAILBOX = 2;
    const TYPE_EMPTY = 3;

    public $flags = 0;
    public $label = '';
    public $type = 0;

    public $combine = self::COMBINE_ALL;
    public $conditions = array();
    public $stop = true;
    public $value = '';

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->name = "New Rule";
    }

    /**
     */
    public function __get($name)
    {
        switch ($name) {
        case 'has_flags':
            return (bool) ($this->flags & ~self::FLAG_AVAILABLE);
        }
    }

}
