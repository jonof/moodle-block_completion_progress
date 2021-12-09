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
 * Quiz activity-related unit tests for Completion Progress block.
 *
 * @package    block_completion_progress
 * @copyright  2020 Jonathon Fowler <fowlerj@usq.edu.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_completion_progress\tests;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/mod/quiz/lib.php');
require_once($CFG->dirroot.'/mod/quiz/locallib.php');

use block_completion_progress\completion_progress;
use block_completion_progress\defaults;

/**
 * Quiz activity-related unit tests for Completion Progress block.
 *
 * @package    block_completion_progress
 * @copyright  2020 Jonathon Fowler <fowlerj@usq.edu.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_completion_testcase extends \block_completion_progress\tests\completion_testcase_base {
    /**
     * A data provider supplying each of the possible quiz grade methods.
     * @return array
     */
    public function grademethod_provider(): array {
        return [
            'QUIZ_GRADEHIGHEST' => [ QUIZ_GRADEHIGHEST, ],
            'QUIZ_GRADEAVERAGE' => [ QUIZ_GRADEAVERAGE, ],
            'QUIZ_ATTEMPTFIRST' => [ QUIZ_ATTEMPTFIRST, ],
            'QUIZ_ATTEMPTLAST' => [ QUIZ_ATTEMPTLAST, ],
        ];
    }

    /**
     * Test completion determination in a Quiz activity with
     * pass/fail enabled.
     *
     * @param integer $grademethod
     *
     * @dataProvider grademethod_provider
     */
    public function test_quiz_passfail($grademethod) {
        $generator = $this->getDataGenerator();

        $instance = $generator->create_module('quiz', [
            'course' => $this->course->id,
            'grade' => 100,
            'sumgrades' => 100,
            'layout' => '1,0',  // One question.
            'attempts' => -1,
            'grademethod' => $grademethod,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionusegrade' => 1,      // Student must receive a grade to complete.
            'completionexpected' => time() - DAYSECS,
        ]);
        $cm = get_coursemodule_from_id('quiz', $instance->cmid);

        // Set the passing grade.
        $item = \grade_item::fetch(['courseid' => $this->course->id, 'itemtype' => 'mod',
            'itemmodule' => 'quiz', 'iteminstance' => $instance->id, 'outcomeid' => null]);
        $item->gradepass = 50;
        $item->update();

        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('essay', null, [
            'category' => $cat->id,
            'name' => 'Pass-fail essay question',
            'defaultmark' => 100,
            'responserequired' => 1,
            'attachmentsrequired' => 0,
            'responseformat' => 'editor',
        ]);
        quiz_add_quiz_question($question->id, $instance, 1);

        $teacher = $generator->create_and_enrol($this->course, 'editingteacher');

        // Student 1 submits to the activity and gets graded correctly.
        $student1 = $generator->create_and_enrol($this->course, 'student');
        $this->assert_progress_completion($student1, $cm, COMPLETION_INCOMPLETE);
        $attempt = $this->submit_for_student($student1, $instance, 1);
        $this->assert_progress_completion($student1, $cm, 'submitted');
        $this->mark_student($attempt, $teacher, 75);      // Pass.
        $this->assert_progress_completion($student1, $cm, COMPLETION_COMPLETE_PASS);

        // Student 2 submits to the activity and gets graded incorrectly.
        $student2 = $generator->create_and_enrol($this->course, 'student');
        $this->assert_progress_completion($student2, $cm, COMPLETION_INCOMPLETE);
        $attempt = $this->submit_for_student($student2, $instance, 1);
        $this->assert_progress_completion($student2, $cm, 'submitted');
        $this->mark_student($attempt, $teacher, 25);      // Fail.
        $this->assert_progress_completion($student2, $cm, COMPLETION_COMPLETE_FAIL);

        // Student 2 then submits again.
        $attempt = $this->submit_for_student($student2, $instance, 2);
        switch ($grademethod) {
            case QUIZ_GRADEHIGHEST:
                $this->assert_progress_completion($student2, $cm, 'submitted');
                break;
            case QUIZ_GRADEAVERAGE:
                $this->assert_progress_completion($student2, $cm, 'submitted');
                break;
            case QUIZ_ATTEMPTFIRST:
                $this->assert_progress_completion($student2, $cm, COMPLETION_COMPLETE_FAIL);
                break;
            case QUIZ_ATTEMPTLAST:
                $this->assert_progress_completion($student2, $cm, 'submitted');
                break;
        }
    }

    /**
     * Test completion determination in an Assignment activity with basic completion.
     *
     * @param integer $grademethod
     *
     * @dataProvider grademethod_provider
     */
    public function test_quiz_basic($grademethod) {
        $generator = $this->getDataGenerator();

        $instance = $generator->create_module('quiz', [
            'course' => $this->course->id,
            'grade' => 100,
            'sumgrades' => 100,
            'layout' => '1,0',  // One question.
            'attempts' => -1,
            'grademethod' => $grademethod,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionusegrade' => 1,      // Student must receive a grade to complete.
            'completionexpected' => time() - DAYSECS,
        ]);
        $cm = get_coursemodule_from_id('quiz', $instance->cmid);

        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('essay', null, [
            'category' => $cat->id,
            'name' => 'Basic essay question',
            'defaultmark' => 100,
            'responserequired' => 1,
            'attachmentsrequired' => 0,
            'responseformat' => 'editor',
        ]);
        quiz_add_quiz_question($question->id, $instance, 1);

        $teacher = $generator->create_and_enrol($this->course, 'editingteacher');

        // Student 1 submits to the activity and gets graded correct.
        $student1 = $generator->create_and_enrol($this->course, 'student');
        $this->assert_progress_completion($student1, $cm, COMPLETION_INCOMPLETE);
        $attempt = $this->submit_for_student($student1, $instance, 1);
        $this->assert_progress_completion($student1, $cm, 'submitted');
        $this->mark_student($attempt, $teacher, 75);      // Pass.
        $this->assert_progress_completion($student1, $cm, COMPLETION_COMPLETE);

        // Student 2 submits to the activity and gets graded incorrect.
        $student2 = $generator->create_and_enrol($this->course, 'student');
        $this->assert_progress_completion($student2, $cm, COMPLETION_INCOMPLETE);
        $attempt = $this->submit_for_student($student2, $instance, 1);
        $this->assert_progress_completion($student2, $cm, 'submitted');
        $this->mark_student($attempt, $teacher, 25);      // Fail.
        $this->assert_progress_completion($student2, $cm, COMPLETION_COMPLETE);

        // Student 2 then submits again.
        $attempt = $this->submit_for_student($student2, $instance, 2);
        switch ($grademethod) {
            case QUIZ_GRADEHIGHEST:
                $this->assert_progress_completion($student2, $cm, COMPLETION_COMPLETE);
                break;
            case QUIZ_GRADEAVERAGE:
                $this->assert_progress_completion($student2, $cm, COMPLETION_COMPLETE);
                break;
            case QUIZ_ATTEMPTFIRST:
                $this->assert_progress_completion($student2, $cm, COMPLETION_COMPLETE);
                break;
            case QUIZ_ATTEMPTLAST:
                $this->assert_progress_completion($student2, $cm, 'submitted');
                break;
        }
    }

    /**
     * Submit a quiz attempt.
     * @param object $student
     * @param object $quiz
     * @param integer $attemptnumber
     * @return quiz_attempt
     */
    private function submit_for_student($student, $quiz, $attemptnumber) {
        $quizobj = \quiz::create($quiz->id, $student->id);
        $attempt = quiz_prepare_and_start_new_attempt($quizobj, $attemptnumber, null, false, [], [], $student->id);
        $attemptobj = \quiz_attempt::create($attempt->id);

        // Save a response for the essay in the first slot.
        $qa = $attemptobj->get_question_attempt(1);
        $qa->process_action([
            'answer'         => 'Response',
            'answerformat'   => FORMAT_HTML,
        ], null, $student->id);

        // Finish the attempt.
        $attemptobj->process_attempt(time(), true, false, 1);

        return $attemptobj;
    }

    /**
     * Mark the first question of an attempt.
     * @param quiz_attempt $attemptobj
     * @param object $teacher
     * @param integer $mark
     */
    private function mark_student($attemptobj, $teacher, $mark) {
        global $DB;

        $this->setUser($teacher);

        $quba = $attemptobj->get_question_usage();
        $quba->get_question_attempt(1)->manual_grade(
                'Comment', $mark, FORMAT_HTML);
        \question_engine::save_questions_usage_by_activity($quba);

        $update = new \stdClass();
        $update->id = $attemptobj->get_attemptid();
        $update->timemodified = time();
        $update->sumgrades = $quba->get_total_mark();
        $DB->update_record('quiz_attempts', $update);
        quiz_save_best_grade($attemptobj->get_quiz(), $attemptobj->get_userid());

        $this->setUser(null);
    }
}
