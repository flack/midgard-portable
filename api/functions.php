<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

function mgd_version() : string
{
    return '1.7.1';
}

function mgd_is_guid($input) : bool
{
    if (!is_string($input)) {
        return false;
    }
    return preg_match('/^[0-9a-f]{21,80}/', $input) === 1;
}
