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
 * Overview table unit tests for block_completion_progress.
 *
 * @package    block_completion_progress
 * @copyright  2021 Jonathon Fowler <fowlerj@usq.edu.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_completion_progress;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot.'/blocks/moodleblock.class.php');
require_once($CFG->dirroot.'/blocks/completion_progress/block_completion_progress.php');

use block_completion_progress\completion_progress;
use block_completion_progress\defaults;

/**
 * Overview table unit tests for block_completion_progress.
 *
 * @package    block_completion_progress
 * @copyright  2021 Jonathon Fowler <fowlerj@usq.edu.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class overview_test extends \block_completion_progress\tests\testcase {
    /**
     * Teacher users.
     * @var array
     */
    private $teachers = [];

    /**
     * Student users.
     * @var array
     */
    private $students = [];

    /**
     * Groups.
     * @var array
     */
    private $groups = [];

    /**
     * Number of students to create.
     */
    const STUDENT_COUNT = 4;

    /**
     * Create a course and add enrol users to it.
     */
    protected function set_up() {
        $this->resetAfterTest(true);

        set_config('enablecompletion', 1);

        $generator = $this->getDataGenerator();

        $this->course = $generator->create_course([
          'enablecompletion' => 1,
        ]);

        $this->teachers[0] = $generator->create_and_enrol($this->course, 'teacher');

        $this->groups[0] = $generator->create_group(['courseid' => $this->course->id]);
        $this->groups[1] = $generator->create_group(['courseid' => $this->course->id]);

        for ($i = 0; $i < self::STUDENT_COUNT; $i++) {
            $status = $i >= 3 ? ENROL_USER_SUSPENDED : null;
            $this->students[$i] = $generator->create_and_enrol($this->course, 'student',
                null, 'manual', 0, 0, $status);

            // Students are put into even/odd groups.
            $generator->create_group_member(['groupid' => $this->groups[$i % 2]->id,
                'userid' => $this->students[$i]->id]);
        }
    }

    /**
     * Convenience function to create a testable instance of an assignment.
     *
     * @param array $params Array of parameters to pass to the generator
     * @return assign Assign class.
     */
    protected function create_assign_instance($params=array()) {
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_assign');
        $params['course'] = $this->course->id;
        $instance = $generator->create_instance($params);
        $cm = get_coursemodule_from_instance('assign', $instance->id);
        $context = \context_module::instance($cm->id);
        return new \assign($context, $cm, $this->course);
    }

    /**
     * Test optional settings' effects on the overview table.
     * @covers \block_completion_progress\table\overview
     */
    public function test_overview_options() {
        global $DB, $PAGE;

        $output = $PAGE->get_renderer('block_completion_progress');

        // Add a block.
        $context = \context_course::instance($this->course->id);
        $blockinfo = [
            'parentcontextid' => $context->id,
            'pagetypepattern' => 'course-view-*',
            'showinsubcontexts' => 0,
            'defaultweight' => 5,
            'timecreated' => time(),
            'timemodified' => time(),
            'defaultregion' => 'side-post',
            'configdata' => base64_encode(serialize((object)[
                'orderby' => defaults::ORDERBY,
                'longbars' => defaults::LONGBARS,
                'progressBarIcons' => 0,    // Non-default.
                'showpercentage' => defaults::SHOWPERCENTAGE,
                'progressTitle' => "",
                'activitiesincluded' => defaults::ACTIVITIESINCLUDED,
            ])),
        ];
        $blockinstance = $this->getDataGenerator()->create_block('completion_progress', $blockinfo);

        $assign = $this->create_assign_instance([
          'submissiondrafts' => 0,
          'completionsubmit' => 1,
          'completion' => COMPLETION_TRACKING_AUTOMATIC
        ]);

        $PAGE->set_url('/');

        // Test inactive student is hidden and 'last in course' column is hidden.
        set_config('showinactive', 0, 'block_completion_progress');
        set_config('showlastincourse', 0, 'block_completion_progress');
        set_config('forceiconsinbar', 0, 'block_completion_progress');
        $progress = (new completion_progress($this->course))->for_overview()->for_block_instance($blockinstance);
        $table = new \block_completion_progress\table\overview($progress, [], 0, true);
        $table->define_baseurl('/');

        ob_start();
        $table->out(30, false);
        $text = ob_get_clean();

        $this->assertStringContainsString('<input id="user'.$this->students[0]->id.'" ', $text);
        $this->assertStringNotContainsString('<input id="user'.$this->students[3]->id.'" ', $text);
        $this->assertStringNotContainsString('col-timeaccess', $text);
        $this->assertStringNotContainsString('barWithIcons', $text);

        // Test inactive student is visible and 'last in course' column is shown.
        set_config('showinactive', 1, 'block_completion_progress');
        set_config('showlastincourse', 1, 'block_completion_progress');
        set_config('forceiconsinbar', 1, 'block_completion_progress');
        $progress = (new completion_progress($this->course))->for_overview()->for_block_instance($blockinstance);
        $table = new \block_completion_progress\table\overview($progress, [], 0, true);
        $table->define_baseurl('/');

        ob_start();
        $table->out(30, false);
        $text = ob_get_clean();

        $this->assertStringContainsString('<input id="user'.$this->students[0]->id.'" ', $text);
        $this->assertStringContainsString('<input id="user'.$this->students[3]->id.'" ', $text);
        $this->assertStringContainsString('col-timeaccess', $text);
        $this->assertStringContainsString('barWithIcons', $text);

        // Test that group filtering works.
        $progress = (new completion_progress($this->course))->for_overview()->for_block_instance($blockinstance);
        $table = new \block_completion_progress\table\overview($progress, [$this->groups[0]->id], 0, true);
        $table->define_baseurl('/');

        ob_start();
        $table->out(30, false);
        $text = ob_get_clean();

        $this->assertStringContainsString('<input id="user'.$this->students[0]->id.'" ', $text);
        $this->assertStringNotContainsString('<input id="user'.$this->students[1]->id.'" ', $text);
        $this->assertStringContainsString('<input id="user'.$this->students[2]->id.'" ', $text);
        $this->assertStringNotContainsString('<input id="user'.$this->students[3]->id.'" ', $text);
    }

    /**
     * Test that the overview table correctly sorts by progress.
     * @covers \block_completion_progress\table\overview
     */
    public function test_overview_percentage_sort() {
        global $DB, $PAGE;

        $PAGE->set_url('/');
        $output = $PAGE->get_renderer('block_completion_progress');
        $generator = $this->getDataGenerator();

        // Add a block.
        $context = \context_course::instance($this->course->id);
        $blockinfo = [
            'parentcontextid' => $context->id,
            'pagetypepattern' => 'course-view-*',
            'showinsubcontexts' => 0,
            'defaultweight' => 5,
            'timecreated' => time(),
            'timemodified' => time(),
            'defaultregion' => 'side-post',
            'configdata' => base64_encode(serialize((object)[
                'orderby' => defaults::ORDERBY,
                'longbars' => defaults::LONGBARS,
                'progressBarIcons' => 0,    // Non-default.
                'showpercentage' => defaults::SHOWPERCENTAGE,
                'progressTitle' => "",
                'activitiesincluded' => defaults::ACTIVITIESINCLUDED,
            ])),
        ];
        $blockinstance = $generator->create_block('completion_progress', $blockinfo);

        $page1 = $generator->create_module('page', [
            'course' => $this->course->id,
            'completion' => COMPLETION_TRACKING_MANUAL
        ]);
        $page1cm = get_coursemodule_from_id('page', $page1->cmid);
        $page2 = $generator->create_module('page', [
            'course' => $this->course->id,
            'completion' => COMPLETION_TRACKING_MANUAL
        ]);
        $page2cm = get_coursemodule_from_id('page', $page2->cmid);

        $completion = new \completion_info($this->course);

        // Set student 2 as having completed both pages.
        $completion->update_state($page1cm, COMPLETION_COMPLETE, $this->students[2]->id);
        $completion->update_state($page2cm, COMPLETION_COMPLETE, $this->students[2]->id);

        // Set student 0 as having completed one page.
        $completion->update_state($page1cm, COMPLETION_COMPLETE, $this->students[0]->id);

        $progress = (new completion_progress($this->course))->for_overview()->for_block_instance($blockinstance);
        $table = new \block_completion_progress\table\overview($progress, [], 0, true);
        $table->set_sortdata([['sortby' => 'progress', 'sortorder' => SORT_DESC]]);
        $table->define_baseurl('/');

        ob_start();
        $table->out(5, false);
        $text = ob_get_clean();

        // Student 2 then Student 0 then Student 1.
        $student0pos = strpos($text, '<input id="user'.$this->students[0]->id.'" ');
        $student1pos = strpos($text, '<input id="user'.$this->students[1]->id.'" ');
        $student2pos = strpos($text, '<input id="user'.$this->students[2]->id.'" ');
        $this->assertGreaterThan($student2pos, $student0pos, 'Student 2 > Student 0');
        $this->assertGreaterThan($student0pos, $student1pos, 'Student 0 > Student 1');
    }
}
