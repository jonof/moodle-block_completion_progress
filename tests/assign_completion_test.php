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
 * Assignment activity-related unit tests for Completion Progress block.
 *
 * @package    block_completion_progress
 * @copyright  2020 Jonathon Fowler <fowlerj@usq.edu.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_completion_progress\tests;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/blocks/completion_progress/lib.php');
require_once($CFG->dirroot.'/mod/assign/locallib.php');
require_once($CFG->dirroot.'/mod/assign/tests/fixtures/testable_assign.php');

/**
 * Assignment activity-related unit tests for Completion Progress block.
 *
 * @package    block_completion_progress
 * @copyright  2020 Jonathon Fowler <fowlerj@usq.edu.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_completion_testcase extends \advanced_testcase {
    /**
     * Assert a user's completion status for a course module.
     * @param object $course
     * @param object $student
     * @param object $cm
     * @param integer|string $status
     */
    private function assert_progress_completion($course, $student, $cm, $status) {
        $activities = [ ['id' => $cm->id ]];
        $submissions = block_completion_progress_submissions($course->id, $student->id);
        $completions = block_completion_progress_completions($activities, $student->id,
            $course, $submissions);
        $this->assertEquals(
            [$cm->id => $status],
            $completions
        );
    }

    /**
     * Test completion determination in an Assignment activity with
     * pass/fail enabled.
     */
    public function test_assign_passfail() {
        global $CFG;

        $CFG->enablecompletion = 1;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();

        $course = $generator->create_course([
            'enablecompletion' => 1,
        ]);
        $instance = $generator->create_module('assign', [
            'course' => $course->id,
            'grade' => 100,
            'maxattempts' => -1,
            'attemptreopenmethod' => ASSIGN_ATTEMPT_REOPEN_METHOD_UNTILPASS,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionusegrade' => 1,      // The student must receive a grade to complete.
            'completionexpected' => time() - DAYSECS,
            'teamsubmission' => 0,
        ]);
        $cm = get_coursemodule_from_id('assign', $instance->cmid);

        // Set the passing grade.
        $item = \grade_item::fetch(['courseid' => $course->id, 'itemtype' => 'mod',
            'itemmodule' => 'assign', 'iteminstance' => $instance->id, 'outcomeid' => null]);
        $item->gradepass = 50;
        $item->update();

        $assign = new \mod_assign_testable_assign(
            \context_module::instance($cm->id), $cm, $course);

        $teacher = $generator->create_and_enrol($course, 'editingteacher');

        // Student 1 submits to the activity and gets graded correct.
        $student1 = $generator->create_and_enrol($course, 'student');
        $this->assert_progress_completion($course, $student1, $cm, COMPLETION_INCOMPLETE);
        $this->submit_for_student($student1, $assign);
        $this->assert_progress_completion($course, $student1, $cm, 'submitted');
        $this->grade_student($student1, $assign, $teacher, 75, 0);      // Pass.
        $this->assert_progress_completion($course, $student1, $cm, COMPLETION_COMPLETE_PASS);

        // Student 2 submits to the activity and gets graded incorrect.
        $student2 = $generator->create_and_enrol($course, 'student');
        $this->assert_progress_completion($course, $student2, $cm, COMPLETION_INCOMPLETE);
        $this->submit_for_student($student2, $assign);
        $this->assert_progress_completion($course, $student2, $cm, 'submitted');
        $this->grade_student($student2, $assign, $teacher, 25, 0);      // Fail.
        $this->assert_progress_completion($course, $student2, $cm, COMPLETION_COMPLETE_FAIL);

        // Student 2 then submits again.
        $this->submit_for_student($student2, $assign);
        $this->assert_progress_completion($course, $student2, $cm, 'submitted');
    }

    /**
     * Test completion determination in an Assignment activity with basic completion.
     */
    public function test_assign_basic() {
        global $CFG;

        $CFG->enablecompletion = 1;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();

        $course = $generator->create_course([
            'enablecompletion' => 1,
        ]);
        $instance = $generator->create_module('assign', [
            'course' => $course->id,
            'maxattempts' => -1,
            'attemptreopenmethod' => ASSIGN_ATTEMPT_REOPEN_METHOD_UNTILPASS,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionsubmit' => 1,        // Submission alone is enough to trigger completion.
            'completionexpected' => time() - DAYSECS,
            'teamsubmission' => 0,
        ]);
        $cm = get_coursemodule_from_id('assign', $instance->cmid);

        $assign = new \mod_assign_testable_assign(
            \context_module::instance($cm->id), $cm, $course);

        $teacher = $generator->create_and_enrol($course, 'editingteacher');

        // Student 1 submits to the activity and gets graded correctly.
        $student1 = $generator->create_and_enrol($course, 'student');
        $this->assert_progress_completion($course, $student1, $cm, COMPLETION_INCOMPLETE);
        $this->submit_for_student($student1, $assign);
        $this->assert_progress_completion($course, $student1, $cm, COMPLETION_COMPLETE);
        $this->grade_student($student1, $assign, $teacher, 75, 0);      // Pass.
        $this->assert_progress_completion($course, $student1, $cm, COMPLETION_COMPLETE);

        // Student 2 submits to the activity and gets graded incorrectly.
        $student2 = $generator->create_and_enrol($course, 'student');
        $this->assert_progress_completion($course, $student2, $cm, COMPLETION_INCOMPLETE);
        $this->submit_for_student($student2, $assign);
        $this->assert_progress_completion($course, $student2, $cm, COMPLETION_COMPLETE);
        $this->grade_student($student2, $assign, $teacher, 25, 0);      // Fail.
        $this->assert_progress_completion($course, $student2, $cm, COMPLETION_COMPLETE);

        // Student 2 then submits again.
        $this->submit_for_student($student2, $assign);
        $this->assert_progress_completion($course, $student2, $cm, COMPLETION_COMPLETE);
    }

    /**
     * A data provider supplying each of the possible quiz grade methods.
     * @return array
     */
    public function teamsubmission_provider(): array {
        return [
            'one-per-group' => [ 0, ],
            'per-member'    => [ 1, ],
        ];
    }

    /**
     * Test completion determination in an Assignment activity requiring team submissions,
     * one submission per group.
     *
     * @param integer $requireallteammemberssubmit
     *
     * @dataProvider teamsubmission_provider
     */
    public function test_teamsubmission($requireallteammemberssubmit) {
        global $CFG;

        $CFG->enablecompletion = 1;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();

        $course = $generator->create_course([
            'enablecompletion' => 1,
        ]);

        $grouping1 = $generator->create_grouping(['courseid' => $course->id]);
        $group1 = $generator->create_group(['courseid' => $course->id]);
        $group2 = $generator->create_group(['courseid' => $course->id]);
        $generator->create_grouping_group(['groupingid' => $grouping1->id, 'groupid' => $group1->id]);
        $generator->create_grouping_group(['groupingid' => $grouping1->id, 'groupid' => $group2->id]);

        $instance = $generator->create_module('assign', [
            'course' => $course->id,
            'maxattempts' => -1,
            'attemptreopenmethod' => ASSIGN_ATTEMPT_REOPEN_METHOD_NONE,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionusegrade' => 1,      // The student must receive a grade to complete.
            'completionexpected' => time() - DAYSECS,
            'teamsubmission' => 1,
            'teamsubmissiongroupingid' => $grouping1->id,
            'requireallteammemberssubmit' => $requireallteammemberssubmit,
            'preventsubmissionnotingroup' => 0,
        ]);
        $cm = get_coursemodule_from_id('assign', $instance->cmid);

        $teacher = $generator->create_and_enrol($course, 'editingteacher');

        // Students 1 and 2 are grouped together.
        $student1 = $generator->create_and_enrol($course, 'student');
        $generator->create_group_member(['groupid' => $group1->id, 'userid' => $student1->id]);
        $student2 = $generator->create_and_enrol($course, 'student');
        $generator->create_group_member(['groupid' => $group1->id, 'userid' => $student2->id]);

        // Student 3 is not a group member.
        $student3 = $generator->create_and_enrol($course, 'student');

        $assign = new \mod_assign_testable_assign(
            \context_module::instance($cm->id), $cm, $course);

        if ($requireallteammemberssubmit == 0) {    // One-per-group.
            // Student 1 submits for Group 1.
            $this->assert_progress_completion($course, $student1, $cm, COMPLETION_INCOMPLETE);
            $this->assert_progress_completion($course, $student2, $cm, COMPLETION_INCOMPLETE);
            $this->submit_for_student($student1, $assign);
            $this->assert_progress_completion($course, $student1, $cm, 'submitted');
            $this->assert_progress_completion($course, $student2, $cm, 'submitted');
            $this->grade_student($student1, $assign, $teacher, 75, 0);      // Pass.
            $this->grade_student($student2, $assign, $teacher, 25, 0);      // Fail.
            $this->assert_progress_completion($course, $student1, $cm, COMPLETION_COMPLETE);
            $this->assert_progress_completion($course, $student2, $cm, COMPLETION_COMPLETE);

            // Student 2 submits for themself ungrouped.
            $this->assert_progress_completion($course, $student3, $cm, COMPLETION_INCOMPLETE);
            $this->submit_for_student($student3, $assign);
            $this->assert_progress_completion($course, $student3, $cm, 'submitted');
            $this->grade_student($student3, $assign, $teacher, 75, 0);      // Pass.
            $this->assert_progress_completion($course, $student3, $cm, COMPLETION_COMPLETE);

        } else {
            // Set the passing grade.
            $item = \grade_item::fetch(['courseid' => $course->id, 'itemtype' => 'mod',
                'itemmodule' => 'assign', 'iteminstance' => $instance->id, 'outcomeid' => null]);
            $item->gradepass = 50;
            $item->update();

            // Students 1 and 2 submit individually for Group 1.
            $this->assert_progress_completion($course, $student1, $cm, COMPLETION_INCOMPLETE);
            $this->assert_progress_completion($course, $student2, $cm, COMPLETION_INCOMPLETE);
            $this->submit_for_student($student1, $assign);
            $this->assert_progress_completion($course, $student1, $cm, 'submitted');
            $this->assert_progress_completion($course, $student2, $cm, COMPLETION_INCOMPLETE);
            $this->submit_for_student($student2, $assign);
            $this->assert_progress_completion($course, $student2, $cm, 'submitted');
            $this->grade_student($student1, $assign, $teacher, 75, 0);      // Pass.
            $this->grade_student($student2, $assign, $teacher, 25, 0);      // Fail.
            $this->assert_progress_completion($course, $student1, $cm, COMPLETION_COMPLETE_PASS);
            $this->assert_progress_completion($course, $student2, $cm, COMPLETION_COMPLETE_FAIL);
        }
    }

    /**
     * Submit an assignment.
     * Pinched from mod/assign/tests/generator.php and modified.
     *
     * @param object $student
     * @param assign $assign
     *
     * @copyright  2018 Andrew Nicols <andrew@nicols.co.uk>
     */
    private function submit_for_student($student, $assign) {
        $this->setUser($student);

        $sink = $this->redirectMessages();

        $assign->save_submission((object) [
            'userid' => $student->id,
            'onlinetext_editor' => [
                'itemid' => file_get_unused_draft_itemid(),
                'text' => 'Text',
                'format' => FORMAT_HTML,
            ]
        ], $notices);

        $assign->submit_for_grading((object) [
            'userid' => $student->id,
        ], []);

        $sink->close();
    }

    /**
     * Award a grade to a submission.
     * Pinched from mod/assign/tests/generator.php and modified.
     *
     * @param object $student
     * @param assign $assign
     * @param object $teacher
     * @param integer $grade
     * @param integer $attempt
     *
     * @copyright  2018 Andrew Nicols <andrew@nicols.co.uk>
     */
    private function grade_student($student, $assign, $teacher, $grade, $attempt) {
        global $DB;

        $this->setUser($teacher);

        // Bump all timecreated and timemodified for this user back.
        $DB->execute('UPDATE {assign_submission} ' .
            'SET timecreated = timecreated - 1, timemodified = timemodified - 1 ' .
            'WHERE userid = :userid',
            ['userid' => $student->id]);

        $assign->testable_apply_grade_to_user((object) [ 'grade' => $grade ],
            $student->id, $attempt);
    }
}
