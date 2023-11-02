<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Completion Progress block runtime configured styles.
 *
 * @package    block_completion_progress
 * @copyright  2020 Jonathon Fowler
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_DEBUG_DISPLAY', true);
define('NO_MOODLE_COOKIES', true);
define('NO_UPGRADE_CHECK', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir.'/filelib.php');

$cachevalue = optional_param('v', -1, PARAM_INT);

$css = '';

// Emit colours configuration.
$colours = [
    'completed' => 'completed_colour',
    'submittedNotComplete' => 'submittednotcomplete_colour',
    'notCompleted' => 'notCompleted_colour',
    'futureNotCompleted' => 'futureNotCompleted_colour',
];
foreach ($colours as $classname => $stringkey) {
    $colour = get_config('block_completion_progress', $stringkey) ?:
        get_string($stringkey, 'block_completion_progress');
    $css .= ".block_completion_progress .progressBarCell.$classname { ";
    $css .= "background-color: $colour;";
    $css .= " }\n";
}

if ($cachevalue < 0) {
    send_content_uncached($css, 'styles.css');
} else {
    send_file($css, 'styles.css', null, 0, true, false, '', false, ['immutable' => true]);
}
