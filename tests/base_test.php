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
 * Basic unit tests for block_completion_progress.
 *
 * @package    block_completion_progress
 * @copyright  2017 onwards Nelson Moller  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot.'/blocks/completion_progress/lib.php');

if (version_compare(PHPUnit\Runner\Version::id(), '8', '<')) {
    // Moodle 3.8 to 3.9.
    class_alias('block_completion_progress\tests\testcase_phpunit7', 'block_completion_progress\tests\testcase');
} else {
    // Moodle 3.10 onwards.
    class_alias('block_completion_progress\tests\testcase_phpunit8', 'block_completion_progress\tests\testcase');
}

/**
 * Basic unit tests for block_completion_progress.
 *
 * @package    block_completion_progress
 * @copyright  2017 onwards Nelson Moller  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_completion_progress_base_testcase extends block_completion_progress\tests\testcase {
    /**
     * The test course.
     * @var object
     */
    private $course;

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
     * Default number of students to create.
     */
    const DEFAULT_STUDENT_COUNT = 3;

    /**
     * Default number of teachers to create.
     */
    const DEFAULT_TEACHER_COUNT = 1;

    /**
     * Setup function - we will create a course and add an assign instance to it.
     */
    protected function set_up() {
        $this->resetAfterTest(true);

        set_config('enablecompletion', 1);

        $generator = $this->getDataGenerator();

        $this->course = $generator->create_course([
          'enablecompletion' => 1,
        ]);
        $this->teachers = [];
        for ($i = 0; $i < self::DEFAULT_TEACHER_COUNT; $i++) {
            $this->teachers[] = $generator->create_and_enrol($this->course, 'teacher');
        }

        $this->students = array();
        for ($i = 0; $i < self::DEFAULT_STUDENT_COUNT; $i++) {
            $this->students[] = $generator->create_and_enrol($this->course, 'student');
        }
    }

    /**
     * Convenience function to create a testable instance of an assignment.
     *
     * @param array $params Array of parameters to pass to the generator
     * @return assign Assign class.
     */
    protected function create_instance($params=array()) {
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_assign');
        $params['course'] = $this->course->id;
        $instance = $generator->create_instance($params);
        $cm = get_coursemodule_from_instance('assign', $instance->id);
        $context = context_module::instance($cm->id);
        return new assign($context, $cm, $this->course);
    }

    public function test_assign_get_completion_state() {
        global $DB;

        // Add a block.
        $context = context_course::instance($this->course->id);
        $config = (object)[
          'orderby' => "orderbytime",
          'longbars' => "squeeze",
          'progressBarIcons' => "1",
          'showpercentage' => "0",
          'progressTitle' => "",
          'activitiesincluded' => "activitycompletion",
        ];
        $blockinfo = [
          'parentcontextid' => $context->id,
          'pagetypepattern' => 'course-view-*',
          'showinsubcontexts' => 0,
          'defaultweight' => 5,
          'timecreated' => time(),
          'timemodified' => time(),
          'defaultregion' => 'side-post',
          'configdata' => base64_encode(serialize($config)),
        ];

        $blockinstance = $this->getDataGenerator()->create_block('completion_progress', $blockinfo);
        $blockinstanceid = $blockinstance->id;

        $assign = $this->create_instance([
          'submissiondrafts' => 0,
          'completionsubmit' => 1,
          'completion' => COMPLETION_TRACKING_AUTOMATIC
        ]);

        $this->setUser($this->students[0]);

        $result = assign_get_completion_state(
          $this->course,
          $assign->get_course_module(),
          $this->students[0]->id,
          false
        );
        $this->assertFalse($result);

        $submissions = block_completion_progress_submissions($this->course->id, $this->students[0]->id);
        $config = unserialize(base64_decode($blockinfo['configdata']));
        $activities = block_completion_progress_get_activities($this->course->id, $config);

        $completions = block_completion_progress_completions($activities, $this->students[0]->id, $this->course,
          $submissions);

        $text = block_completion_progress_bar(
          $activities,
          $completions,
          $config,
          $this->students[0]->id,
          $this->course,
          $blockinstanceid
        );

        $this->assertStringContainsStringIgnoringCase('assign', $text, '');
        $this->assertStringNotContainsStringIgnoringCase('quiz', $text, '');

        // The status is futureNotCompleted.
        $this->assertStringContainsString('futureNotCompleted', $text, '');

        $submission = $assign->get_user_submission($this->students[0]->id, true);
        $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        $DB->update_record('assign_submission', $submission);

        $result = assign_get_completion_state(
          $this->course,
          $assign->get_course_module(),
          $this->students[0]->id,
          false
        );
        $this->assertTrue($result);

        $submissions = block_completion_progress_submissions($this->course->id, $this->students[0]->id);
        $completions = block_completion_progress_completions($activities, $this->students[0]->id, $this->course,
          $submissions);

        $text = block_completion_progress_bar(
          $activities,
          $completions,
          $config,
          $this->students[0]->id,
          $this->course,
          $blockinstanceid
        );

        // The status is send but not finished.
        $this->assertStringContainsString('submittedNotComplete', $text, '');
    }

    /**
     * Test checking page types.
     */
    public function test_on_site_page() {
        $page = new \moodle_page();
        $page->set_pagetype('site-index');
        $this->assertTrue(block_completion_progress_on_site_page($page));

        $page = new \moodle_page();
        $page->set_pagetype('my-index');
        $this->assertTrue(block_completion_progress_on_site_page($page));

        $page = new \moodle_page();
        $page->set_pagetype('course-view');
        $this->assertFalse(block_completion_progress_on_site_page($page));

        $page = new \moodle_page();
        $this->assertFalse(block_completion_progress_on_site_page($page));
    }
}
