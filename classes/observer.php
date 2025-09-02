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
 * Completion Progress block.
 *
 * @package    block_completion_progress
 * @copyright  2025 Jonathon Fowler <fowlerj@usq.edu.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_completion_progress;

/**
 * Completion Progress block event observers.
 *
 * @package    block_completion_progress
 * @copyright  2025 Jonathon Fowler <fowlerj@usq.edu.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Evict cached completion percentages when a course module completion changes.
     * @param core\event\course_module_completion_updated $event the event being observed
     */
    public static function course_module_completion_updated(\core\event\course_module_completion_updated $event) {
        global $DB;

        $coursectx = $event->get_context()->get_course_context(false);
        if (!$coursectx) {
            return;
        }
        $bids = $DB->get_fieldset_select(
            'block_instances',
            'id',
            'blockname = ? AND parentcontextid = ?',
            ['completion_progress', $coursectx->id]
        );
        foreach ($bids as $bid) {
            $DB->delete_records('block_completion_progress', [
                'blockinstanceid' => $bid,
                'userid' => $event->other['relateduserid'],
            ]);
        }
    }

    /**
     * Evict cached completion percentages when a course module is modified.
     * @param core\event\base $event of course_module_{created,deleted,modified} type being observed
     */
    public static function course_module_modified(\core\event\base $event) {
        global $DB;

        $coursectx = $event->get_context()->get_course_context(false);
        if (!$coursectx) {
            return;
        }
        $bids = $DB->get_fieldset_select(
            'block_instances',
            'id',
            'blockname = ? AND parentcontextid = ?',
            ['completion_progress', $coursectx->id]
        );
        foreach ($bids as $bid) {
            $DB->delete_records('block_completion_progress', [
                'blockinstanceid' => $bid,
            ]);
        }
    }
}
