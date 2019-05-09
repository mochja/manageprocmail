<?php
/**
 * Copyright 2002-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (ASL).  If you
 * did not receive this file, see http://www.horde.org/licenses/apache.
 *
 * @category  Horde
 * @copyright 2002-2017 Horde LLC
 * @license   http://www.horde.org/licenses/apache ASL
 * @package   Ingo
 */

/**
 * Ingo base class.
 *
 * @author    Mike Cochrane <mike@graftonhall.co.nz>
 * @author    Jan Schneider <jan@horde.org>
 * @category  Horde
 * @copyright 2002-2017 Horde LLC
 * @license   http://www.horde.org/licenses/apache ASL
 * @package   Ingo
 */
class Ingo
{
    /**
     * Define the key to use to indicate a user-defined header is requested.
     */
    const USER_HEADER = '++USER_HEADER++';

    /**
     * Only filter unseen messages.
     */
    const FILTER_UNSEEN = 1;

    /**
     * Only filter seen messages.
     */
    const FILTER_SEEN = 2;

    /**
     * Constants for rule types.
     */
    const RULE_ALL = 0;
    const RULE_FILTER = 1;
    const RULE_BLACKLIST = 2;
    const RULE_WHITELIST = 3;
    const RULE_VACATION = 4;
    const RULE_FORWARD = 5;
    const RULE_SPAM = 6;
}
