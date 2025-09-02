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
 * Completion Progress block upgrade steps.
 *
 * @package    block_completion_progress
 * @copyright  2025 Jonathon Fowler <fowlerj@usq.edu.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade steps.
 * @param int $oldversion
 */
function xmldb_block_completion_progress_upgrade($oldversion) {
    global $DB, $OUTPUT;

    $dbman = $DB->get_manager();

    if ($oldversion < 2025011600) {
        $context = context_system::instance();

        $cap = 'block/completion_progress:overview';
        foreach (get_roles_with_capability($cap, CAP_ALLOW, $context) as $role) {
            if ($role->archetype === 'coursecreator') {
                unassign_capability($cap, $role->id, SYSCONTEXTID);
                echo $OUTPUT->notification("'$cap' capability has been unassigned from " .
                    "role '{$role->shortname}' at site level for privacy reasons.", 'info');
            }
        }

        $cap = 'block/completion_progress:addinstance';
        $manageblocksroles = get_roles_with_capability('moodle/site:manageblocks', CAP_ALLOW, $context);
        foreach (get_roles_with_capability($cap, CAP_ALLOW, $context) as $role) {
            if ($role->archetype === 'coursecreator' && !isset($manageblocksroles[$role->id])) {
                unassign_capability($cap, $role->id, SYSCONTEXTID);
                echo $OUTPUT->notification("'$cap' capability has been unassigned from " .
                    "role '{$role->shortname}' at site level for consistency reasons.", 'info');
            }
        }

        upgrade_plugin_savepoint(true, 2025011600, 'block', 'completion_progress');
    }

    if ($oldversion < 2025090200) {
        $dbman->install_from_xmldb_file(__DIR__ . '/install.xml');
        upgrade_plugin_savepoint(true, 2025090200, 'block', 'completion_progress');
    }

    return true;
}
