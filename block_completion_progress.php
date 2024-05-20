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
 * Completion Progress block definition
 *
 * @package    block_completion_progress
 * @copyright  2016 Michael de Raadt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_completion_progress\completion_progress;
use block_completion_progress\defaults;

/**
 * Completion Progress block class
 *
 * @copyright 2016 Michael de Raadt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_completion_progress extends block_base {

    /**
     * Sets the block title
     *
     * @return void
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_completion_progress');
    }

    /**
     *  we have global config/settings data
     *
     * @return bool
     */
    public function has_config() {
        return true;
    }

    /**
     * Controls the block title based on instance configuration
     *
     * @return bool
     */
    public function specialization() {
        if (isset($this->config->progressTitle) && trim($this->config->progressTitle) != '') {
            $this->title = format_string($this->config->progressTitle);
        }

        // Work around a quirk of in_array('opt', [0 => 0]) returning true on
        // PHP <8.0, causing the configuration form's 'config_group' element to
        // declare all its options 'selected'.
        if (isset($this->config->group)) {
            $this->config->group = (string)$this->config->group;
        }
    }

    /**
     * Controls whether multiple instances of the block are allowed on a page
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return !self::on_site_page($this->page);
    }

    /**
     * Controls whether the block is configurable
     *
     * @return bool
     */
    public function instance_allow_config() {
        return !self::on_site_page($this->page);
    }

    /**
     * Defines where the block can be added
     *
     * @return array
     */
    public function applicable_formats() {
        return [
            'course-view'    => true,
            'site'           => true,
            'mod'            => false,
            'my'             => true,
        ];
    }

    /**
     * Creates the blocks main content
     *
     * @return string
     */
    public function get_content() {
        // If content has already been generated, don't waste time generating it again.
        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = new stdClass;
        $this->content->text = '';
        $this->content->footer = '';
        $barinstances = [];

        // Guests do not have any progress. Don't show them the block.
        if (!isloggedin() || isguestuser()) {
            return $this->content;
        }

        if (self::on_site_page($this->page)) {
            // Draw the multi-bar content for the Dashboard and Front page.
            if (!$this->prepare_dashboard_content($barinstances)) {
                return $this->content;
            }

        } else {
            // Gather content for block on regular course.
            if (!$this->prepare_course_content($barinstances)) {
                return $this->content;
            }
        }

        // Organise access to JS.
        $this->page->requires->js_call_amd('block_completion_progress/progressbar', 'init', [
            'instances' => $barinstances,
        ]);
        $cachevalue = debugging('', DEBUG_DEVELOPER) ? -1 : (int)get_config('block_completion_progress', 'cachevalue');
        $this->page->requires->css('/blocks/completion_progress/css.php?v=' . $cachevalue);

        return $this->content;
    }

    /**
     * Produce content for the Dashboard or Front page.
     * @param array $barinstances receives block instance ids
     * @return boolean false if an early exit
     */
    protected function prepare_dashboard_content(&$barinstances) {
        global $USER, $CFG, $DB;

        $output = $this->page->get_renderer('block_completion_progress');

        if (!$CFG->enablecompletion) {
            $this->content->text .= get_string('completion_not_enabled', 'block_completion_progress');
            return false;
        }

        // Show a message when the user is not enrolled in any courses.
        $courses = enrol_get_my_courses();
        if (($this->page->user_is_editing() || is_siteadmin()) && empty($courses)) {
            $this->content->text = get_string('no_courses', 'block_completion_progress');
            return false;
        }

        $coursenametoshow = get_config('block_completion_progress', 'coursenametoshow') ?:
            defaults::COURSENAMETOSHOW;
        $sql = "SELECT bi.id,
                       COALESCE(bp.visible, 1) AS visible,
                       bi.configdata
                  FROM {block_instances} bi
             LEFT JOIN {block_positions} bp ON bp.blockinstanceid = bi.id
                                           AND ".$DB->sql_like('bp.pagetype', ':pagetype', false)."
                 WHERE bi.blockname = 'completion_progress'
                   AND bi.parentcontextid = :contextid
              ORDER BY COALESCE(bp.region, bi.defaultregion),
                       COALESCE(bp.weight, bi.defaultweight),
                       bi.id";

        foreach ($courses as $course) {
            // Get specific block config and context.
            $courseprogress = new completion_progress($course);
            if (!$course->visible || !$courseprogress->get_completion_info()->is_enabled()) {
                continue;
            }

            $courseprogress->for_user($USER);
            $blockprogresses = [];

            $params = ['contextid' => $courseprogress->get_context()->id, 'pagetype' => 'course-view-%'];
            foreach ($DB->get_records_sql($sql, $params) as $birec) {
                $blockprogress = (clone $courseprogress)->for_block_instance($birec);
                $blockinstance = $blockprogress->get_block_instance();
                $blockconfig = $blockprogress->get_block_config();
                if (!has_capability('block/completion_progress:showbar', context_block::instance($blockinstance->id)) ||
                        !$blockinstance->visible ||
                        !$blockprogress->has_visible_activities()) {
                    continue;
                }
                if (!empty($blockconfig->group) &&
                        !has_capability('moodle/site:accessallgroups', $courseprogress->get_context()) &&
                        !$this->check_group_membership($blockconfig->group, $course->id)) {
                    continue;
                }

                $blockprogresses[$blockinstance->id] = $blockprogress;
                $barinstances[] = $blockinstance->id;
            }

            // Output the Progress Bar.
            if (!empty($blockprogresses)) {
                $courselink = new moodle_url('/course/view.php', ['id' => $course->id]);
                $linktext = html_writer::tag('h3', s(format_string($course->$coursenametoshow)));
                $this->content->text .= html_writer::link($courselink, $linktext);
            }
            foreach ($blockprogresses as $blockprogress) {
                $blockinstance = $blockprogress->get_block_instance();
                $blockconfig = $blockprogress->get_block_config();
                if (($blockconfig->progressTitle ?? '') !== '') {
                    $this->content->text .= html_writer::tag('p', s(format_string($blockconfig->progressTitle)));
                }
                $this->content->text .= $output->render($blockprogress);
            }
        }

        // Show a message explaining lack of bars, but only while editing is on.
        if ($this->page->user_is_editing() && $this->content->text == '') {
            $this->content->text = get_string('no_blocks', 'block_completion_progress');
        }

        return true;
    }

    /**
     * Produce content for a course page.
     * @param array $barinstances receives block instance ids
     * @return boolean false if an early exit
     */
    protected function prepare_course_content(&$barinstances) {
        global $USER, $COURSE, $CFG, $OUTPUT;

        $output = $this->page->get_renderer('block_completion_progress');

        // Check if user is in group for block.
        if (
            !empty($this->config->group) &&
            !has_capability('moodle/site:accessallgroups', $this->context) &&
            !$this->check_group_membership($this->config->group, $COURSE->id)
        ) {
            return false;
        }

        // Check if completion is enabled at site level.
        if (!$CFG->enablecompletion) {
            if (has_capability('moodle/block:edit', $this->context)) {
                $this->content->text .= get_string('completion_not_enabled', 'block_completion_progress');
            }
            return false;
        }

        $progress = new completion_progress($COURSE);

        // Check if completion is enabled at course level.
        if (!$progress->get_completion_info()->is_enabled()) {
            if (has_capability('moodle/block:edit', $this->context)) {
                $this->content->text .= get_string('completion_not_enabled_course', 'block_completion_progress');
            }
            return false;
        }

        $progress->for_user($USER)->for_block_instance($this->instance);

        // Check if any activities/resources have been created.
        if (!$progress->has_visible_activities()) {
            if (has_capability('moodle/block:edit', $this->context)) {
                $this->content->text .= get_string('no_activities_config_message', 'block_completion_progress');
            }
            return false;
        }

        // Display progress bar.
        if (has_capability('block/completion_progress:showbar', $this->context)) {
            $this->content->text .= $output->render($progress);
        }
        $barinstances = [$this->instance->id];

        // Allow teachers to access the overview page.
        if (has_capability('block/completion_progress:overview', $this->context)) {
            $parameters = ['instanceid' => $this->instance->id, 'courseid' => $COURSE->id];
            $url = new moodle_url('/blocks/completion_progress/overview.php', $parameters);
            $label = get_string('overview', 'block_completion_progress');
            $options = ['class' => 'overviewButton'];
            $this->content->text .= $OUTPUT->single_button($url, $label, 'get', $options);
        }

        return true;
    }

    /**
     * Bumps a value to assist in caching of configured colours in css.php.
     */
    public static function increment_cache_value() {
        $value = get_config('block_completion_progress', 'cachevalue') + 1;
        set_config('cachevalue', $value, 'block_completion_progress');
    }

    /**
     * Determines whether the current user is a member of a given group or grouping
     *
     * @param string $group    The group or grouping identifier starting with 'group-' or 'grouping-'
     * @param int    $courseid The ID of the course containing the block instance
     * @return boolean value indicating membership
     */
    private function check_group_membership($group, $courseid) {
        global $USER;

        if ($group === '0') {
            return true;
        } else if ((substr($group, 0, 6) == 'group-') && ($groupid = intval(substr($group, 6)))) {
            return groups_is_member($groupid, $USER->id);
        } else if ((substr($group, 0, 9) == 'grouping-') && ($groupingid = intval(substr($group, 9)))) {
            return array_key_exists($groupingid, groups_get_user_groups($courseid, $USER->id));
        }

        return false;
    }

    /**
     * Checks whether the given page is site-level (Dashboard or Front page) or not.
     *
     * @param moodle_page $page the page to check, or the current page if not passed.
     * @return boolean True when on the Dashboard or Site home page.
     */
    public static function on_site_page($page = null) {
        global $PAGE;   // phpcs:ignore moodle.PHP.ForbiddenGlobalUse.BadGlobal

        $page = $page ?? $PAGE; // phpcs:ignore moodle.PHP.ForbiddenGlobalUse.BadGlobal
        $context = $page->context ?? null;

        if (!$page || !$context) {
            return false;
        } else if ($context->contextlevel === CONTEXT_SYSTEM && $page->requestorigin === 'restore') {
            return false; // When restoring from a backup, pretend the page is course-level.
        } else if ($context->contextlevel === CONTEXT_COURSE && $context->instanceid == SITEID) {
            return true;  // Front page.
        } else if ($context->contextlevel < CONTEXT_COURSE) {
            return true;  // System, user (i.e. dashboard), course category.
        } else {
            return false;
        }
    }

}
