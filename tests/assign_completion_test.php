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

namespace block_completion_progress;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/mod/assign/locallib.php');
require_once($CFG->dirroot.'/mod/assign/tests/fixtures/testable_assign.php');

use block_completion_progress\completion_progress;
use block_completion_progress\defaults;

/**
 * Assignment activity-related unit tests for Completion Progress block.
 *
 * @package    block_completion_progress
 * @copyright  2020 Jonathon Fowler <fowlerj@usq.edu.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_completion_test extends \block_completion_progress\tests\completion_testcase {
    /**
     * Test assignment completion state changes.
     * @covers \block_completion_progress\completion_progress
     */
    public function test_assign_get_completion_state() {
        global $DB, $PAGE;

        $output = $PAGE->get_renderer('block_completion_progress');
        $generator = $this->getDataGenerator();

        $instance = $generator->create_module('assign', [
            'course' => $this->course->id,
            'submissiondrafts' => 0,
            'assignsubmission_onlinetext_enabled' => 1,
            'assignsubmission_file_enabled' => 0,
            'completionsubmit' => 1,
            'completionusegrade' => 1,
            'completionpassgrade' => 0,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
        ]);
        $cm = get_coursemodule_from_instance('assign', $instance->id);
        $context = \context_module::instance($cm->id);
        $assign = new \assign($context, $cm, $this->course);
        $completion = new \completion_info($this->course);

        $student1 = $generator->create_and_enrol($this->course, 'student');

        $this->setUser($student1);

        $result = $completion->get_data($assign->get_course_module(), false, $student1->id);
        $this->assertSame(COMPLETION_INCOMPLETE, $result->customcompletion['completionsubmit'], 'no submission');
        $this->assertSame(COMPLETION_INCOMPLETE, $result->completionstate, 'no submission');

        $progress = (new completion_progress($this->course))
                    ->for_user($student1)
                    ->for_block_instance($this->blockinstance);
        $text = $output->render($progress);

        $this->assertStringContainsStringIgnoringCase('assign', $text, 'no submission');
        $this->assertStringNotContainsStringIgnoringCase('quiz', $text, 'no submission');

        // Not yet submitted, nor completed.
        $this->assertStringContainsString('futureNotCompleted', $text, 'no submission');

        $submission = $assign->get_user_submission($student1->id, true);
        $data = (object)[
            'userid' => $student1->id,
            'onlinetext_editor' => ['text' => 'Text', 'format' => FORMAT_PLAIN],
        ];
        $notices = [];
        $this->assertTrue($assign->save_submission($data, $notices), 'submitted awaiting grade');
        $this->assertEmpty($notices, 'submitted awaiting grade');

        $result = $completion->get_data($assign->get_course_module(), false, $student1->id);
        $this->assertSame(COMPLETION_COMPLETE, $result->customcompletion['completionsubmit'], 'submitted awaiting grade');
        $this->assertSame(COMPLETION_INCOMPLETE, $result->completiongrade, 'submitted awaiting grade');
        $this->assertSame(COMPLETION_INCOMPLETE, $result->completionstate, 'submitted awaiting grade');

        $progress = (new completion_progress($this->course))
                    ->for_user($student1)
                    ->for_block_instance($this->blockinstance);
        $text = $output->render($progress);

        // Submitted and awaiting a grade, so not completed.
        $this->assertStringContainsString('submittedNotComplete', $text, 'submitted awaiting grade');
    }

    /**
     * Test completion determination in an Assignment activity with pass/fail enabled.
     * @covers \block_completion_progress\completion_progress
     */
    public function test_assign_passfail() {
        $generator = $this->getDataGenerator();

        $instance = $generator->create_module('assign', [
            'course' => $this->course->id,
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
        $item = \grade_item::fetch(['courseid' => $this->course->id, 'itemtype' => 'mod',
            'itemmodule' => 'assign', 'iteminstance' => $instance->id, 'outcomeid' => null]);
        $item->gradepass = 50;
        $item->update();

        $assign = new \mod_assign_testable_assign(
            \context_module::instance($cm->id), $cm, $this->course);

        $teacher = $generator->create_and_enrol($this->course, 'editingteacher');

        // Student 1 submits to the activity and gets graded correct.
        $student1 = $generator->create_and_enrol($this->course, 'student');
        $this->assert_progress_completion($student1, $cm, COMPLETION_INCOMPLETE);
        $this->submit_for_student($student1, $assign);
        $this->assert_progress_completion($student1, $cm, 'submitted');
        $this->grade_student($student1, $assign, $teacher, 75, 0);      // Pass.
        $this->assert_progress_completion($student1, $cm, COMPLETION_COMPLETE_PASS);

        // Student 2 submits to the activity and gets graded incorrect.
        $student2 = $generator->create_and_enrol($this->course, 'student');
        $this->assert_progress_completion($student2, $cm, COMPLETION_INCOMPLETE);
        $this->submit_for_student($student2, $assign);
        $this->assert_progress_completion($student2, $cm, 'submitted');
        $this->grade_student($student2, $assign, $teacher, 25, 0);      // Fail.
        $this->assert_progress_completion($student2, $cm, COMPLETION_COMPLETE_FAIL);

        // Student 2 then submits again.
        $this->submit_for_student($student2, $assign);
        $this->assert_progress_completion($student2, $cm, 'submitted');
    }

    /**
     * Test completion determination in an Assignment activity with basic completion.
     * @covers \block_completion_progress\completion_progress
     */
    public function test_assign_basic() {
        $generator = $this->getDataGenerator();

        $instance = $generator->create_module('assign', [
            'course' => $this->course->id,
            'maxattempts' => -1,
            'attemptreopenmethod' => ASSIGN_ATTEMPT_REOPEN_METHOD_UNTILPASS,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionsubmit' => 1,        // Submission alone is enough to trigger completion.
            'completionexpected' => time() - DAYSECS,
            'teamsubmission' => 0,
        ]);
        $cm = get_coursemodule_from_id('assign', $instance->cmid);

        $assign = new \mod_assign_testable_assign(
            \context_module::instance($cm->id), $cm, $this->course);

        $teacher = $generator->create_and_enrol($this->course, 'editingteacher');

        // Student 1 submits to the activity and gets graded correctly.
        $student1 = $generator->create_and_enrol($this->course, 'student');
        $this->assert_progress_completion($student1, $cm, COMPLETION_INCOMPLETE);
        $this->submit_for_student($student1, $assign);
        $this->assert_progress_completion($student1, $cm, COMPLETION_COMPLETE);
        $this->grade_student($student1, $assign, $teacher, 75, 0);      // Pass.
        $this->assert_progress_completion($student1, $cm, COMPLETION_COMPLETE);

        // Student 2 submits to the activity and gets graded incorrectly.
        $student2 = $generator->create_and_enrol($this->course, 'student');
        $this->assert_progress_completion($student2, $cm, COMPLETION_INCOMPLETE);
        $this->submit_for_student($student2, $assign);
        $this->assert_progress_completion($student2, $cm, COMPLETION_COMPLETE);
        $this->grade_student($student2, $assign, $teacher, 25, 0);      // Fail.
        $this->assert_progress_completion($student2, $cm, COMPLETION_COMPLETE);

        // Student 2 then submits again.
        $this->submit_for_student($student2, $assign);
        $this->assert_progress_completion($student2, $cm, COMPLETION_COMPLETE);
    }

    /**
     * A data provider supplying each of the possible quiz grade methods.
     * @return array
     */
    public function teamsubmission_provider(): array {
        return [
            'one-per-group' => [ 0 ],
            'per-member'    => [ 1 ],
        ];
    }

    /**
     * Test completion determination in an Assignment activity requiring team submissions,
     * one submission per group.
     *
     * @param integer $requireallteammemberssubmit
     *
     * @covers \block_completion_progress\completion_progress
     * @dataProvider teamsubmission_provider
     */
    public function test_teamsubmission($requireallteammemberssubmit) {
        $generator = $this->getDataGenerator();

        $grouping1 = $generator->create_grouping(['courseid' => $this->course->id]);
        $group1 = $generator->create_group(['courseid' => $this->course->id]);
        $group2 = $generator->create_group(['courseid' => $this->course->id]);
        $generator->create_grouping_group(['groupingid' => $grouping1->id, 'groupid' => $group1->id]);
        $generator->create_grouping_group(['groupingid' => $grouping1->id, 'groupid' => $group2->id]);

        $instance = $generator->create_module('assign', [
            'course' => $this->course->id,
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

        $teacher = $generator->create_and_enrol($this->course, 'editingteacher');

        // Students 1 and 2 are grouped together.
        $student1 = $generator->create_and_enrol($this->course, 'student');
        $generator->create_group_member(['groupid' => $group1->id, 'userid' => $student1->id]);
        $student2 = $generator->create_and_enrol($this->course, 'student');
        $generator->create_group_member(['groupid' => $group1->id, 'userid' => $student2->id]);

        // Student 3 is not a group member.
        $student3 = $generator->create_and_enrol($this->course, 'student');

        $assign = new \mod_assign_testable_assign(
            \context_module::instance($cm->id), $cm, $this->course);

        if ($requireallteammemberssubmit == 0) {    // One-per-group.
            // Student 1 submits for Group 1.
            $this->assert_progress_completion($student1, $cm, COMPLETION_INCOMPLETE);
            $this->assert_progress_completion($student2, $cm, COMPLETION_INCOMPLETE);
            $this->submit_for_student($student1, $assign);
            $this->assert_progress_completion($student1, $cm, 'submitted');
            $this->assert_progress_completion($student2, $cm, 'submitted');
            $this->grade_student($student1, $assign, $teacher, 75, 0);      // Pass.
            $this->grade_student($student2, $assign, $teacher, 25, 0);      // Fail.
            $this->assert_progress_completion($student1, $cm, COMPLETION_COMPLETE);
            $this->assert_progress_completion($student2, $cm, COMPLETION_COMPLETE);

            // Student 2 submits for themself ungrouped.
            $this->assert_progress_completion($student3, $cm, COMPLETION_INCOMPLETE);
            $this->submit_for_student($student3, $assign);
            $this->assert_progress_completion($student3, $cm, 'submitted');
            $this->grade_student($student3, $assign, $teacher, 75, 0);      // Pass.
            $this->assert_progress_completion($student3, $cm, COMPLETION_COMPLETE);

        } else {
            // Set the passing grade.
            $item = \grade_item::fetch(['courseid' => $this->course->id, 'itemtype' => 'mod',
                'itemmodule' => 'assign', 'iteminstance' => $instance->id, 'outcomeid' => null]);
            $item->gradepass = 50;
            $item->update();

            // Students 1 and 2 submit individually for Group 1.
            $this->assert_progress_completion($student1, $cm, COMPLETION_INCOMPLETE);
            $this->assert_progress_completion($student2, $cm, COMPLETION_INCOMPLETE);
            $this->submit_for_student($student1, $assign);
            $this->assert_progress_completion($student1, $cm, 'submitted');
            $this->assert_progress_completion($student2, $cm, COMPLETION_INCOMPLETE);
            $this->submit_for_student($student2, $assign);
            $this->assert_progress_completion($student2, $cm, 'submitted');
            $this->grade_student($student1, $assign, $teacher, 75, 0);      // Pass.
            $this->grade_student($student2, $assign, $teacher, 25, 0);      // Fail.
            $this->assert_progress_completion($student1, $cm, COMPLETION_COMPLETE_PASS);
            $this->assert_progress_completion($student2, $cm, COMPLETION_COMPLETE_FAIL);
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
            ],
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
