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
 * Completion Progress block overview page
 *
 * @package    block_completion_progress
 * @copyright  2018 Michael de Raadt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Include required files.
require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot.'/blocks/completion_progress/lib.php');
//require_once($CFG->dirroot.'/enrol/locallib.php');
require_once($CFG->dirroot.'/notes/lib.php');
require_once($CFG->libdir.'/tablelib.php');
require_once($CFG->dirroot.'/blocks/completion_progress/classes/table/overview_table.php');

use core_table\local\filter\filter;
use core_table\local\filter\integer_filter;
use core_table\local\filter\string_filter;
//use block_completion_progress\table\overview_table_filterset as filterset;

/**
 * Default number of participants per page.
 */
const DEFAULT_PAGE_SIZE = 20;

/**
 * An impractically high number of participants indicating 'all' are to be shown.
 */
const SHOW_ALL_PAGE_SIZE = 5000;

// Gather form data.
$id       = required_param('instanceid', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);
$page     = optional_param('page', 0, PARAM_INT); // Which page to show.
$perpage  = optional_param('perpage', DEFAULT_PAGE_SIZE, PARAM_INT); // How many per page.
$group    = optional_param('group', 0, PARAM_ALPHANUMEXT); // Group selected.

// Determine course and context.
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance($courseid);

$notesallowed = !empty($CFG->enablenotes) && has_capability('moodle/notes:manage', $context);
$messagingallowed = !empty($CFG->messaging) && has_capability('moodle/site:sendmessage', $context);
$bulkoperations = ($CFG->version >= 2017111300.00) &&
    has_capability('moodle/course:bulkmessaging', $context) && (
        $notesallowed || $messagingallowed
    );

// Find the role to display, defaulting to students.
$sql = "SELECT DISTINCT r.id, r.name, r.archetype
          FROM {role} r, {role_assignments} a
         WHERE a.contextid = :contextid
           AND r.id = a.roleid
           AND r.archetype = :archetype";
$params = array('contextid' => $context->id, 'archetype' => 'student');
$studentrole = $DB->get_record_sql($sql, $params);
if ($studentrole) {
    $studentroleid = $studentrole->id;
} else {
    $studentroleid = 0;
}
$roleselected = optional_param('role', $studentroleid, PARAM_INT);

// Get specific block config and context.
$block = $DB->get_record('block_instances', array('id' => $id), '*', MUST_EXIST);
$config = unserialize(base64_decode($block->configdata));
$blockcontext = context_block::instance($id);

// Set up page parameters.
$PAGE->set_course($course);
$PAGE->set_url(
    '/blocks/completion_progress/overview.php',
    array(
        'instanceid' => $id,
        'courseid'   => $courseid,
        'page'       => $page,
        'perpage'    => $perpage,
        'group'      => $group,
        'sesskey'    => sesskey(),
        'role'       => $roleselected,
    )
);
$PAGE->set_context($context);
$title = get_string('overview', 'block_completion_progress');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->navbar->add($title);
$PAGE->set_pagelayout('report');

$cachevalue = debugging() ? -1 : (int)get_config('block_completion_progress', 'cachevalue');
$PAGE->requires->css('/blocks/completion_progress/css.php?v=' . $cachevalue);

// Check user is logged in and capable of accessing the Overview.
require_login($course, false);
require_capability('block/completion_progress:overview', $blockcontext);
confirm_sesskey();

$output = $PAGE->get_renderer('block_completion_progress');

// Start page output.
echo $OUTPUT->header();
echo $OUTPUT->heading($title, 2);
echo $OUTPUT->container_start('block_completion_progress');

// Output group selector if there are groups in the course.
echo $OUTPUT->container_start('progressoverviewmenus');
$groupselected = 0;
$groupuserid = $USER->id;
if (has_capability('moodle/site:accessallgroups', $context)) {
    $groupuserid = 0;
}
$groupids = array();
$groupidnums = array();
$groupingids = array();
$groups = groups_get_all_groups($course->id, $groupuserid);
$groupings = ($groupuserid == 0 ? groups_get_all_groupings($course->id) : []);
if (!empty($groups) || !empty($groupings)) {
    $groupstodisplay = ($groupuserid == 0 ? array(0 => get_string('allparticipants')) : []);
    foreach ($groups as $groupidnum => $groupobject) {
        $groupid = 'group-'.$groupidnum;
        $groupstodisplay[$groupid] = format_string($groupobject->name);
        $groupids[] = $groupid;
        $groupidnums[] = $groupidnum;
    }
    foreach ($groupings as $groupingidnum => $groupingobject) {
        $groupingid = 'grouping-'.$groupingidnum;
        $groupstodisplay[$groupingid] = format_string($groupingobject->name);
        $groupids[] = $groupingid;
    }
    if (!in_array($group, $groupids)) {
        $group = '0';
        $PAGE->url->param('group', $group);
    }
    echo get_string('groupsvisible') . '&nbsp;';
    echo $OUTPUT->single_select($PAGE->url, 'group', $groupstodisplay, $group);
}

// Output the roles menu.
$sql = "SELECT DISTINCT r.id, r.name, r.shortname
          FROM {role} r, {role_assignments} a
         WHERE a.contextid = :contextid
           AND r.id = a.roleid";
$params = array('contextid' => $context->id);
$roles = role_fix_names($DB->get_records_sql($sql, $params), $context);
$rolestodisplay = array(0 => get_string('allparticipants'));
foreach ($roles as $role) {
    $rolestodisplay[$role->id] = $role->localname;
}
echo '&nbsp;' . get_string('role') . '&nbsp;';
echo $OUTPUT->single_select($PAGE->url, 'role', $rolestodisplay, $roleselected);

echo $OUTPUT->container_end();

//Create Table Filters
$filterset = new block_completion_progress\table\overview_table_filterset();
$filterset->add_filter(new integer_filter('roles', filter::JOINTYPE_DEFAULT, [(int)$roleselected]));
$filterset->add_filter(new string_filter('group', filter::JOINTYPE_DEFAULT, [(string)$group]));
$filterset->add_filter(new integer_filter('courseid', filter::JOINTYPE_DEFAULT, [(int)$course->id]));
$filterset->add_filter(new integer_filter('instanceid', filter::JOINTYPE_DEFAULT, [(int)$id]));
$tableid = 'mod-block-completion-progress-overview';
$table = new block_completion_progress\table\overview_table($tableid);

echo '<div class="userlist">';

// Do this so we can get the total number of rows.
ob_start();
$table->set_filterset($filterset);
$table->out($perpage, true);
$tablehtml = ob_get_contents();
ob_end_clean();

echo html_writer::start_tag('form', [
    'action' => 'action_redir.php',
    'method' => 'post',
    'id' => 'participantsform',
    'data-course-id' => $course->id,
    'data-table-unique-id' => $table->uniqueid,
    'data-table-default-per-page' => ($perpage < DEFAULT_PAGE_SIZE) ? $perpage : DEFAULT_PAGE_SIZE,
]);
echo '<div>';
echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
echo '<input type="hidden" name="returnto" value="'.s($PAGE->url->out(false)).'" />';

echo html_writer::tag(
    'p',
    get_string('countparticipantsfound', 'core_user', $table->totalrows),
    [
        'data-region' => 'participant-count',
    ]
);

echo $tablehtml;

$perpageurl = new moodle_url('/blocks/completion_progress/overview.php', [
      'instanceid' => $id,
      'courseid'   => $courseid,
      'page'       => $page,
      'perpage'    => $perpage,
      'group'      => $group,
      'sesskey'    => sesskey(),
      'role'       => $roleselected,
]);
$perpagesize = DEFAULT_PAGE_SIZE;
$perpagevisible = false;
$perpagestring = '';

if ($perpage == SHOW_ALL_PAGE_SIZE && $table->totalrows > DEFAULT_PAGE_SIZE) {
    $perpageurl->param('perpage', $table->totalrows);
    $perpagesize = SHOW_ALL_PAGE_SIZE;
    $perpagevisible = true;
    $perpagestring = get_string('showperpage', '', DEFAULT_PAGE_SIZE);
} else if ($table->get_page_size() < $table->totalrows) {
    $perpageurl->param('perpage', SHOW_ALL_PAGE_SIZE);
    $perpagesize = SHOW_ALL_PAGE_SIZE;
    $perpagevisible = true;
    $perpagestring = get_string('showall', '', $table->totalrows);
}

$perpageclasses = '';
if (!$perpagevisible) {
    $perpageclasses = 'hidden';
}
echo $OUTPUT->container(html_writer::link(
    $perpageurl,
    $perpagestring,
    [
        'data-action' => 'showcount',
        'data-target-page-size' => $perpagesize,
        'class' => $perpageclasses,
    ]
), [], 'showall');

$bulkoptions = (object) [
    'uniqueid' => $table->uniqueid,
];

if ($bulkoperations) {
    echo '<br /><div class="buttons"><div class="form-inline">';

    echo html_writer::start_tag('div', array('class' => 'btn-group'));

    if ($table->get_page_size() < $table->totalrows) {
        // Select all users, refresh table showing all users and mark them all selected.
        $label = get_string('selectalluserswithcount', 'moodle', $table->totalrows);
        echo html_writer::empty_tag('input', [
            'type' => 'button',
            'id' => 'checkall',
            'class' => 'btn btn-secondary',
            'value' => $label,
            'data-target-page-size' => $table->totalrows,
        ]);
    }
    echo html_writer::end_tag('div');
    $displaylist = array();
    if (!empty($CFG->messaging) && has_all_capabilities(['moodle/site:sendmessage', 'moodle/course:bulkmessaging'], $context)) {
        $displaylist['#messageselect'] = get_string('messageselectadd');
    }
    if (!empty($CFG->enablenotes) && has_capability('moodle/notes:manage', $context) && $context->id != $frontpagectx->id) {
        $displaylist['#addgroupnote'] = get_string('addnewnote', 'notes');
    }

    $selectactionparams = array(
        'id' => 'formactionid',
        'class' => 'ml-2',
        'data-action' => 'toggle',
        'data-togglegroup' => 'participants-table',
        'data-toggle' => 'action',
        'disabled' => 'disabled'
    );
    $label = html_writer::tag('label', get_string("withselectedusers"),
            ['for' => 'formactionid', 'class' => 'col-form-label d-inline']);
    $select = html_writer::select($displaylist, 'formaction', '', ['' => 'choosedots'], $selectactionparams);
    echo html_writer::tag('div', $label . $select);

    echo '<input type="hidden" name="id" value="' . $course->id . '" />';
    echo '<div class="d-none" data-region="state-help-icon">' . $OUTPUT->help_icon('publishstate', 'notes') . '</div>';
    echo '</div></div></div>';

    $bulkoptions->noteStateNames = note_get_state_names();
}
echo '</form>';

$PAGE->requires->js_call_amd('core_user/participants', 'init', [$bulkoptions]);
echo '</div>';  // Userlist.
echo $OUTPUT->container_end();
echo $OUTPUT->footer();
