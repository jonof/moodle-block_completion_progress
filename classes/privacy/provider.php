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
 * @copyright  2018 Michael de Raadt
 * @copyright  2025 Jonathon Fowler <fowlerj@usq.edu.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_completion_progress\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;

/**
 * Privacy class for requesting user data.
 *
 * @package    block_completion_progress
 * @copyright  2018 Mihail Geshoski <mihail@moodle.com>
 * @copyright  2025 Jonathon Fowler <fowlerj@usq.edu.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\metadata\provider
{
    /**
     * Returns meta data about this system.
     *
     * @param   collection $collection The initialised collection to add items to.
     * @return  collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('block_completion_progress', [
            'blockinstanceid' => 'privacy:metadata:block_completion_progress:blockinstanceid',
            'userid' => 'privacy:metadata:block_completion_progress:userid',
            'percentage' => 'privacy:metadata:block_completion_progress:percentage',
            'timemodified' => 'privacy:metadata:block_completion_progress:timemodified',
        ], 'privacy:metadata:block_completion_progress:tableexplanation');
        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param   int         $userid     The user to search.
     * @return  contextlist $contextlist  The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT ctx.id
                FROM {block_completion_progress} b
                JOIN {user} u
                    ON b.userid = u.id
                JOIN {context} ctx
                    ON ctx.instanceid = u.id
                        AND ctx.contextlevel = :contextlevel
                WHERE b.userid = :userid";

        $params = ['userid' => $userid, 'contextlevel' => CONTEXT_USER];

        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, $params);
        return $contextlist;
    }

    /**
     * Get the list of users within a specific context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_user) {
            return;
        }

        $sql = "SELECT userid
                  FROM {block_completion_progress}
                 WHERE userid = ?";
        $params = [$context->instanceid];
        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        $pctdata = [];
        $results = static::get_records($contextlist->get_user()->id);
        foreach ($results as $result) {
            $pctdata[] = (object) [
                'blockinstanceid' => $result->blockinstanceid,
                'userid' => $result->userid,
                'percentage' => $result->percentage,
                'timemodified' => transform::datetime($result->timemodified),
            ];
        }
        if (!empty($pctdata)) {
            $data = (object) [
                'percentages' => $pctdata,
            ];
            \core_privacy\local\request\writer::with_context($contextlist->current())->export_data(
                [get_string('pluginname', 'block_completion_progress')],
                $data
            );
        }
    }

    /**
     * Delete all use data which matches the specified deletion_criteria.
     *
     * @param   \context $context A user context.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        if ($context instanceof \context_user) {
            static::delete_data($context->instanceid);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();

        if ($context instanceof \context_user) {
            static::delete_data($context->instanceid);
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist    $contextlist    The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        static::delete_data($contextlist->get_user()->id);
    }

    /**
     * Delete data related to a userid.
     *
     * @param  int $userid The user ID
     */
    protected static function delete_data($userid) {
        global $DB;

        $DB->delete_records('block_completion_progress', ['userid' => $userid]);
    }

    /**
     * Get records related to this plugin and user.
     *
     * @param  int $userid The user ID
     * @return array An array of records.
     */
    protected static function get_records($userid) {
        global $DB;

        return $DB->get_records('block_completion_progress', ['userid' => $userid]);
    }
}
