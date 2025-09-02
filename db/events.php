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
 * Completion Progress block event observers
 *
 * @package    block_completion_progress
 * @copyright  2025 Jonathon Fowler <fowlerj@usq.edu.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$observers = [
    [
        'eventname'   => '\core\event\course_module_completion_updated',
        'callback'    => 'block_completion_progress\observer::course_module_completion_updated',
    ],
    [
        'eventname'   => '\core\event\course_module_created',
        'callback'    => 'block_completion_progress\observer::course_module_modified',
    ],
    [
        'eventname'   => '\core\event\course_module_deleted',
        'callback'    => 'block_completion_progress\observer::course_module_modified',
    ],
    [
        'eventname'   => '\core\event\course_module_updated',
        'callback'    => 'block_completion_progress\observer::course_module_modified',
    ],
];
