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
 * Completion unit tests common base for Completion Progress block.
 *
 * @package    block_completion_progress
 * @copyright  2020 Jonathon Fowler <fowlerj@usq.edu.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_completion_progress\tests;

defined('MOODLE_INTERNAL') || die();

global $CFG;

use block_completion_progress\completion_progress;
use block_completion_progress\defaults;

/**
 * Completion unit tests common base for Completion Progress block.
 *
 * @package    block_completion_progress
 * @copyright  2020 Jonathon Fowler <fowlerj@usq.edu.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class completion_testcase extends \block_completion_progress\tests\testcase {
    /**
     * The test course.
     * @var object
     */
    protected $course;

    /**
     * A completion_progress block instance in the test course.
     * @var object
     */
    protected $blockinstance;


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
                'progressBarIcons' => defaults::PROGRESSBARICONS,
                'showpercentage' => defaults::SHOWPERCENTAGE,
                'progressTitle' => "",
                'activitiesincluded' => defaults::ACTIVITIESINCLUDED,
            ])),
        ];
        $this->blockinstance = $this->getDataGenerator()->create_block('completion_progress', $blockinfo);
    }

    /**
     * Assert a user's completion status for a course module.
     * @param object $student
     * @param object $cm
     * @param integer|string $status
     */
    protected function assert_progress_completion($student, $cm, $status) {
        $progress = (new completion_progress($this->course))
                    ->for_user($student)
                    ->for_block_instance($this->blockinstance);
        $completions = $progress->get_completions();
        $this->assertEquals(
            [$cm->id => $status],
            $completions
        );
    }
}
