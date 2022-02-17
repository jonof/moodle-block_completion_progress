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
require_once($CFG->dirroot.'/notes/lib.php');
require_once($CFG->libdir.'/tablelib.php');

use block_completion_progress\completion_progress;

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
$role     = optional_param('role', null, PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);

// Determine course and context.
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance($courseid);

// Get specific block config and context.
$block = $DB->get_record('block_instances', array('id' => $id), '*', MUST_EXIST);
$blockcontext = context_block::instance($id);

$notesallowed = !empty($CFG->enablenotes) && has_capability('moodle/notes:manage', $context);
$messagingallowed = !empty($CFG->messaging) && has_capability('moodle/site:sendmessage', $context);
$bulkoperations = has_capability('moodle/course:bulkmessaging', $context) && ($notesallowed || $messagingallowed);

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
        'role'       => $role,
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

$progress = (new completion_progress($course))
    ->for_overview()
    ->for_block_instance($block);

// Prepare a group selector if there are groups in the course.
$groupids = [];
$groupoptions = [];
if (has_capability('moodle/site:accessallgroups', $context)) {
    $allgroups = groups_get_all_groups($course->id, 0);
    $allgroupings = groups_get_all_groupings($course->id);
    if ($allgroups) {
        $groupoptions[0] = get_string('allparticipants');
    }
} else {
    $allgroups = groups_get_all_groups($course->id, $USER->id);
    $allgroupings = [];
}
foreach ($allgroups as $rec) {
    if ($group == $rec->id) {
        $groupids = [ $rec->id ]; // Selected filter.
    }
    $groupoptions[$rec->id] = format_string($rec->name);
}
foreach ($allgroupings as $rec) {
    if ($group === "g{$rec->id}") { // Selected grouping.
        $groupids = array_keys(groups_get_all_groups($course->id, 0, $rec->id));
    }
    $groupoptions["g{$rec->id}"] = format_string($rec->name);
}
if (!$groupids) {
    $group = 0;
    $PAGE->set_url($PAGE->url, ['group' => $group]);
}

// Prepare the roles menu.
$sql = "SELECT DISTINCT r.id, r.name, r.shortname, r.archetype, r.sortorder
          FROM {role} r, {role_assignments} ra
         WHERE ra.contextid = :contextid
           AND r.id = ra.roleid
        ORDER BY r.sortorder";
$params = ['contextid' => $context->id];
$roles = role_fix_names($DB->get_records_sql($sql, $params), $context);
$roleoptions = array(0 => get_string('allparticipants'));
foreach ($roles as $rec) {
    if ($role === null && $rec->archetype === 'student') {
        $role = $rec->id;  // First student role is the default.
        $PAGE->set_url($PAGE->url, ['role' => $role]);
    }
    $roleoptions[$rec->id] = $rec->localname;
}

// Setup the overview table.
$table = new block_completion_progress\table\overview($progress, $groupids, $role, $bulkoperations);
$table->define_baseurl($PAGE->url);
$table->show_download_buttons_at([]);   // We'll output them ourselves.
$table->is_downloading($download, 'completion_progress-' . $COURSE->shortname);
$table->setup();

if ($download) {
    $table->query_db($perpage);
    $table->start_output();
    $table->build_table();
    $table->finish_output();
    exit;
}

$output = $PAGE->get_renderer('block_completion_progress');

// Start page output.
echo $output->header();
echo $output->heading($title, 2);
echo $output->container_start('block_completion_progress');

// Check if activities/resources have been selected in config.
if (!$progress->has_activities()) {
    echo get_string('no_activities_message', 'block_completion_progress');
    echo $output->container_end();
    echo $output->footer();
    die();
}

echo $output->container_start('progressoverviewmenus');
if ($groupoptions) {
    echo $output->single_select($PAGE->url, 'group', $groupoptions, $group,
        ['' => 'choosedots'], null, ['label' => get_string('groupsvisible')]);
}
if ($roleoptions) {
    echo $output->single_select($PAGE->url, 'role', $roleoptions, $role,
        ['' => 'choosedots'], null, ['label' => get_string('role')]);
}
echo $output->container_end();

// Form for messaging selected participants.
$formattributes = array('action' => $CFG->wwwroot.'/user/action_redir.php', 'method' => 'post', 'id' => 'participantsform');
$formattributes['data-course-id'] = $course->id;
$formattributes['data-table-unique-id'] = 'block-completion_progress-overview-' . $course->id;
echo html_writer::start_tag('form', $formattributes);
echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'returnto', 'value' => s($PAGE->url->out(false))));

// Imitate a 3.9 dynamic table enough to fool the core_user/participants JS code, until
// next time it changes again.
$tabledivattributes = [
    'data-region' => 'core_table/dynamic',
    'data-table-uniqueid' => $formattributes['data-table-unique-id'],
];
echo html_writer::start_div('', $tabledivattributes);

// Render the overview table.
$table->query_db($perpage);
$table->start_output();
$table->build_table();
$table->finish_output();

echo html_writer::end_div();    // Closes the 3.9 imitation table wrapper.

// Output paging controls.
if ($table->totalrows > $perpage || $perpage == SHOW_ALL_PAGE_SIZE) {
    $perpageurl = new moodle_url($PAGE->url, ['page' => 0]);
    if ($perpage < SHOW_ALL_PAGE_SIZE) {
        $perpageurl->param('perpage', SHOW_ALL_PAGE_SIZE);
        echo $output->container(html_writer::link($perpageurl,
            get_string('showall', '', $table->totalrows)), array(), 'showall');
    } else {
        $perpageurl->param('perpage', DEFAULT_PAGE_SIZE);
        echo $output->container(html_writer::link($perpageurl,
            get_string('showperpage', '', DEFAULT_PAGE_SIZE)), array(), 'showall');
    }
}

if ($bulkoperations) {
    echo '<br /><div class="form-inline m-1">';

    $displaylist = array();
    if ($messagingallowed) {
        $displaylist['#messageselect'] = get_string('messageselectadd');
    }
    if ($notesallowed) {
        $displaylist['#addgroupnote'] = get_string('addnewnote', 'notes');
    }

    echo html_writer::tag('label', get_string("withselectedusers"), array('for' => 'formactionid'));
    echo html_writer::select($displaylist, 'formaction', '', array('' => 'choosedots'), array('id' => 'formactionid'));

    echo '<input type="hidden" name="id" value="'.$course->id.'" />';
    echo '<noscript style="display:inline">';
    echo '<div><input type="submit" value="'.get_string('ok').'" /></div>';
    echo '</noscript>';
    echo '</div>';

    $options = new stdClass();
    $options->noteStateNames = note_get_state_names();
    $options->uniqueid = $formattributes['data-table-unique-id'];
    echo '<div class="d-none" data-region="state-help-icon">' . $output->help_icon('publishstate', 'notes') . '</div>';
    $PAGE->requires->js_call_amd('block_completion_progress/overview', 'init', [$options]);
}
echo html_writer::end_tag('form');

echo $table->download_buttons();

// Organise access to JS for progress bars.
$PAGE->requires->js_call_amd('block_completion_progress/progressbar', 'init', [
    'instances' => array($block->id),
]);

echo $output->container_end();
echo $output->footer();
