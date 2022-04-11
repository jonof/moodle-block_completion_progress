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
 * Workshop activity-related unit tests for Completion Progress block.
 *
 * @package    block_completion_progress
 * @copyright  2020 Jonathon Fowler <fowlerj@usq.edu.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_completion_progress;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/mod/workshop/locallib.php');
require_once($CFG->dirroot.'/mod/workshop/tests/fixtures/testable.php');

use block_completion_progress\completion_progress;
use block_completion_progress\defaults;

/**
 * Workshop activity-related unit tests for Completion Progress block.
 *
 * @package    block_completion_progress
 * @copyright  2020 Jonathon Fowler <fowlerj@usq.edu.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class workshop_completion_test extends \block_completion_progress\tests\completion_testcase {
    /**
     * Test completion determination in a Workshop activity with pass/fail enabled.
     * @covers \block_completion_progress\completion_progress
     */
    public function test_workshop_passfail() {
        $this->setAdminUser();

        $generator = $this->getDataGenerator();

        $instance = $generator->create_module('workshop', [
            'course' => $this->course->id,
            'grade' => 80,
            'gradinggrade' => 20,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionusegrade' => 1,      // The student must receive a grade to complete.
            'completionexpected' => time() - DAYSECS,
        ]);
        $cm = get_coursemodule_from_id('workshop', $instance->cmid);

        // Set the passing grades for submission and assessment.
        $item = \grade_item::fetch(['courseid' => $this->course->id, 'itemtype' => 'mod',
            'itemmodule' => 'workshop', 'iteminstance' => $instance->id, 'itemnumber' => 0,
            'outcomeid' => null]);
        $item->gradepass = 40;
        $item->update();

        $workshop = new \testable_workshop($instance, $cm, $this->course);

        // Student 1 submits to the activity and gets graded correct.
        $student1 = $generator->create_and_enrol($this->course, 'student');
        $this->assert_progress_completion($student1, $cm, COMPLETION_INCOMPLETE);
        $submission1 = $this->submit_for_student($student1, $workshop);
        $this->assert_progress_completion($student1, $cm, 'submitted');
        $this->grade_submission($submission1, $workshop, 75);      // Pass.
        $this->assert_progress_completion($student1, $cm, 'submitted');
        $workshop->switch_phase(\testable_workshop::PHASE_CLOSED);
        $this->assert_progress_completion($student1, $cm, COMPLETION_COMPLETE_PASS);

        // Student 2 submits to the activity and gets graded incorrect.
        $student2 = $generator->create_and_enrol($this->course, 'student');
        $this->assert_progress_completion($student2, $cm, COMPLETION_INCOMPLETE);
        $submission2 = $this->submit_for_student($student2, $workshop);
        $this->assert_progress_completion($student2, $cm, 'submitted');
        $this->grade_submission($submission2, $workshop, 25);      // Fail.
        $this->assert_progress_completion($student2, $cm, 'submitted');
        $workshop->switch_phase(\testable_workshop::PHASE_CLOSED);
        $this->assert_progress_completion($student2, $cm, COMPLETION_COMPLETE_FAIL);
    }

    /**
     * Test completion determination in an Workshop activity with basic completion.
     * @covers \block_completion_progress\completion_progress
     */
    public function test_workshop_basic() {
        $this->setAdminUser();

        $generator = $this->getDataGenerator();

        $instance = $generator->create_module('workshop', [
            'course' => $this->course->id,
            'grade' => 80,
            'gradinggrade' => 20,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionusegrade' => 1,      // The student must receive a grade to complete.
            'completionexpected' => time() - DAYSECS,
        ]);
        $cm = get_coursemodule_from_id('workshop', $instance->cmid);

        $workshop = new \testable_workshop($instance, $cm, $this->course);

        // Student 1 submits to the activity and gets graded correctly.
        $student1 = $generator->create_and_enrol($this->course, 'student');
        $this->assert_progress_completion($student1, $cm, COMPLETION_INCOMPLETE);
        $submission1 = $this->submit_for_student($student1, $workshop);
        $this->assert_progress_completion($student1, $cm, 'submitted');
        $this->grade_submission($submission1, $workshop, 75);      // Pass.
        $this->assert_progress_completion($student1, $cm, 'submitted');
        $workshop->switch_phase(\testable_workshop::PHASE_CLOSED);
        $this->assert_progress_completion($student1, $cm, COMPLETION_COMPLETE);

        // Student 2 submits to the activity and gets graded incorrectly.
        $student2 = $generator->create_and_enrol($this->course, 'student');
        $this->assert_progress_completion($student2, $cm, COMPLETION_INCOMPLETE);
        $submission2 = $this->submit_for_student($student2, $workshop);
        $this->assert_progress_completion($student2, $cm, 'submitted');
        $this->grade_submission($submission2, $workshop, 25);      // Fail.
        $this->assert_progress_completion($student2, $cm, 'submitted');
        $workshop->switch_phase(\testable_workshop::PHASE_CLOSED);
        $this->assert_progress_completion($student2, $cm, COMPLETION_COMPLETE);
    }

    /**
     * Make a submission for a student.
     *
     * @param object $student
     * @param object $workshop
     * @return object
     */
    protected function submit_for_student($student, $workshop) {
        global $DB;

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_workshop');

        $id = $generator->create_submission($workshop->id, $student->id, array(
            'title' => 'Submission',
        ));
        return $DB->get_record('workshop_submissions', ['id' => $id]);
    }

    /**
     * Award a grade to a submission.
     *
     * @param object $submission
     * @param object $workshop
     * @param integer $grade
     * @return object
     */
    protected function grade_submission($submission, $workshop, $grade) {
        $workshop->aggregate_submission_grades_process([
            (object)['submissionid' => $submission->id, 'submissiongrade' => null,
                'weight' => 1, 'grade' => $grade],
        ]);
    }
}
