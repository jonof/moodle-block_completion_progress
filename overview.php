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

// Needed for progress bar output when sorting by percentage.
define('NO_OUTPUT_BUFFERING', true);

require_once(dirname(__DIR__, 2) . '/config.php');
require_once($CFG->dirroot . '/notes/lib.php');

use block_completion_progress\completion_progress;
use block_completion_progress\output\overview_filter;
use block_completion_progress\table\overview;
use block_completion_progress\table\overview_filterset;
use core_table\local\filter\filter;
use core_table\local\filter\integer_filter;

// Gather form data.
$instanceid = required_param('instanceid', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);
$perpage  = optional_param('perpage', 20, PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);

// Determine course and contexts.
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$blockinstance = $DB->get_record('block_instances', ['id' => $instanceid, 'blockname' => 'completion_progress'], '*', MUST_EXIST);
$context = context_course::instance($courseid);
$blockcontext = context_block::instance($instanceid);
if (!$blockcontext->is_child_of($context, false) && !$blockcontext->is_child_of(context_course::instance(SITEID), false)) {
    throw new moodle_exception('errorinvalidblockinstanceid', 'block_completion_progress');
}

// Set up page parameters.
$strtitle = get_string('overview', 'block_completion_progress');
$PAGE->set_course($course);
$PAGE->set_url('/blocks/completion_progress/overview.php', ['instanceid' => $instanceid, 'courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title("$course->shortname: $strtitle");
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add($strtitle, $PAGE->url);
$PAGE->set_pagelayout('report');

// Check user is logged in and capable of accessing the Overview.
require_login($course, false);
require_capability('block/completion_progress:overview', $context);

$cachevalue = debugging() ? -1 : (int)get_config('block_completion_progress', 'cachevalue');
$PAGE->requires->css('/blocks/completion_progress/css.php?v=' . $cachevalue);
$PAGE->requires->js_call_amd('block_completion_progress/progressbar', 'init');
$PAGE->requires->js_call_amd('block_completion_progress/overview', 'init', [$course->id, note_get_state_names()]);

$output = $PAGE->get_renderer('block_completion_progress');

$progress = (new completion_progress($course))->for_overview()->for_block_instance($blockinstance);

$studentroles = $DB->get_fieldset_select('role', 'id', 'archetype = ?', ['student']);
$usedstudentroles = array_intersect_key(get_roles_used_in_context($context, false), array_flip($studentroles));
$studentroleid = $usedstudentroles ? array_key_first($usedstudentroles) : 0;

$filterset = new overview_filterset();
$filterset->add_filter(new integer_filter('courseid', filter::JOINTYPE_DEFAULT, [(int)$courseid]));
$filterset->add_filter(new integer_filter('blockinstanceid', filter::JOINTYPE_DEFAULT, [(int)$instanceid]));
if (($filtersetparam = optional_param('filterset', null, PARAM_RAW)) !== null) {
    // We've been passed a filter configuration to apply.
    try {
        $tablefilter = json_decode($filtersetparam, true, 10, JSON_THROW_ON_ERROR);
        if (isset($tablefilter['jointype'], $tablefilter['filters'])) {
            $filterset->set_join_type($tablefilter['jointype']);
            unset($tablefilter['filters']['courseid']);
            unset($tablefilter['filters']['blockinstanceid']);
            foreach ($tablefilter['filters'] as $fname => $fargs) {
                $filterset->add_filter_from_params(
                    clean_param($fargs['name'], PARAM_RAW),
                    clean_param($fargs['jointype'], PARAM_INT),
                    clean_param_array($fargs['values'], PARAM_RAW)
                );
            }
        }
    } catch (JsonException $e) {
        debugging("invalid filterset JSON: {$e->getMessage()}", DEBUG_DEVELOPER);
    }
} else {
    if ($studentroleid) {
        // Default student role filter.
        $filterset->add_filter(new integer_filter('roles', filter::JOINTYPE_DEFAULT, [$studentroleid]));
    }
    if (
        !has_capability('moodle/site:accessallgroups', $context) &&
        ($groupids = array_keys(groups_get_all_groups($course->id, $USER->id)))
    ) {
        // Default groups filter to show members of own groups for people without access to all groups.
        $filterset->add_filter(new integer_filter('groups', filter::JOINTYPE_ANY, $groupids));
    }
}

$table = new overview('block_completion_progress-overview-' . $instanceid);
$table->set_filterset($filterset);
$filter = new overview_filter(
    $context,
    (object) ['jointype' => $filterset->get_join_type(), 'filters' => $filterset->get_filters()],
    $table->uniqueid
);

if ($table->is_downloading($download, 'completion_progress-' . $course->shortname)) {
    $table->out(0, false);
    exit;
}

echo $output->header();
echo $output->heading($strtitle);

if (!$progress->has_activities()) {
    echo get_string('no_activities_message', 'block_completion_progress');
    echo $output->footer();
    exit;
}

if ($table->needs_percentages_computed()) {
    // Compute percentages with a friendly progress indicator if sorting on initial load.
    // If sorting happens on dynamic load, sucks to be the user watching the spinner.
    core_php_time_limit::raise(0);
    $computeprogress = new progress_bar('', 500, true);
    $strcomputeprogress = get_string('computeprogress', 'block_completion_progress');
    $progress->compute_overview_percentages(fn($pct) => $computeprogress->update_full($pct, $strcomputeprogress));
    // Delete the progress bar from the page.
    echo html_writer::script(
        js_writer::function_call(
            '(function(id){document.getElementById(id).remove();})',
            [$computeprogress->get_id()]
        )
    );
}

echo $output->render($filter);
echo $output->render($table);

echo $output->footer();
