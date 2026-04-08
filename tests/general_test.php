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
 * General unit tests for block_completion_progress.
 *
 * @package    block_completion_progress
 * @copyright  2017 onwards Nelson Moller  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_completion_progress;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
require_once($CFG->dirroot . '/blocks/completion_progress/block_completion_progress.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

use block_completion_progress\completion_progress;
use block_completion_progress\defaults;
use block_completion_progress\helpers;

/**
 * General unit tests for block_completion_progress.
 *
 * @package    block_completion_progress
 * @copyright  2017 onwards Nelson Moller  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class general_test extends \advanced_testcase {
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
     * A course object.
     * @var object
     */
    private $course = null;

    /**
     * Number of students to create.
     */
    const STUDENT_COUNT = 4;

    /**
     * Create a course and add enrol users to it.
     */
    protected function setUp(): void {
        parent::setUp();

        $this->resetAfterTest(true);

        set_config('enablecompletion', 1);

        $generator = $this->getDataGenerator();

        $this->course = $generator->create_course([
          'enablecompletion' => 1,
        ]);

        $this->teachers[0] = $generator->create_and_enrol($this->course, 'teacher');

        for ($i = 0; $i < self::STUDENT_COUNT; $i++) {
            $status = $i >= 3 ? ENROL_USER_SUSPENDED : null;
            $this->students[$i] = $generator->create_and_enrol(
                $this->course,
                'student',
                null,
                'manual',
                0,
                0,
                $status
            );
        }
    }

    /**
     * Convenience function to create a testable instance of an assignment.
     *
     * @param array $params Array of parameters to pass to the generator
     * @return assign Assign class.
     */
    protected function create_assign_instance($params) {
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_assign');
        $params['course'] = $this->course->id;
        $instance = $generator->create_instance($params);
        $cm = get_coursemodule_from_instance('assign', $instance->id);
        $context = \context_module::instance($cm->id);
        return new \assign($context, $cm, $this->course);
    }

    /**
     * Check that a student's excluded grade hides the activity from the student's progress bar.
     * @covers \block_completion_progress\completion_progress
     */
    public function test_grade_excluded(): void {
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
        $blockinstance = $this->getDataGenerator()->create_block('completion_progress', $blockinfo);

        $assign = $this->create_assign_instance([
          'submissiondrafts' => 0,
          'completionsubmit' => 1,
          'completion' => COMPLETION_TRACKING_AUTOMATIC,
        ]);

        $gradeitem = \grade_item::fetch([
            'courseid' => $this->course->id,
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
            'iteminstance' => $assign->get_course_module()->instance,
        ]);

        // Set student 1's grade to be excluded.
        $grade = $gradeitem->get_grade($this->students[1]->id);
        $grade->set_excluded(1);

        // Student 0 ought to see the activity.
        $progress = (new completion_progress($this->course))
                    ->for_user($this->students[0])
                    ->for_block_instance($blockinstance);
        $this->assertEquals(
            [$assign->get_course_module()->id => COMPLETION_INCOMPLETE],
            $progress->get_completions()
        );

        // Student 1 ought not see the activity.
        $progress = (new completion_progress($this->course))
                    ->for_user($this->students[1])
                    ->for_block_instance($blockinstance);
        $this->assertEquals([], $progress->get_completions());
    }

    /**
     * Test checking of pages at site-level or not.
     * @covers \block_completion_progress
     */
    public function test_on_site_page(): void {
        global $PAGE;

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_assign');
        $instance = $generator->create_instance(['course' => $this->course->id]);
        $cm = get_coursemodule_from_instance('assign', $instance->id);

        // Front page.
        $page = new \moodle_page();
        $page->set_pagetype('site-index');
        $page->set_context(\context_course::instance(SITEID));
        $this->assertTrue(helpers::on_site_page($page), 'front page');

        // Dashboard.
        $page = new \moodle_page();
        $page->set_pagetype('my-index');
        $page->set_context(\context_user::instance(get_admin()->id));
        $this->assertTrue(helpers::on_site_page($page), 'dashboard');

        // Course.
        $page = new \moodle_page();
        $page->set_pagetype('course-view-topics');
        $page->set_context(\context_course::instance($this->course->id));
        $this->assertFalse(helpers::on_site_page($page), 'course');

        // Activity, possible by making a course block viewable on all page types.
        $page = new \moodle_page();
        $page->set_pagetype('mod-assign-grader');
        $page->set_context(\context_module::instance($cm->id));
        $this->assertFalse(helpers::on_site_page($page), 'activity');

        // AJAX-loaded fragment within a course module context.
        $page = new \moodle_page();
        $page->set_pagetype('site-index');
        $page->set_context(\context_module::instance($cm->id));
        $this->assertFalse(helpers::on_site_page($page), 'ajax');

        // An uninitialised page. This has a default system context.
        $page = new \moodle_page();
        $this->assertTrue(helpers::on_site_page($page), 'uninitialised');

        // Something very unusual.
        $PAGE = null;
        $this->assertFalse(helpers::on_site_page(null), 'oddity');
    }

    /**
     * Test that asynchronous course copy preserves all expected block instances.
     * @covers \restore_completion_progress_block_task
     */
    public function test_course_copy(): void {
        global $DB;

        $this->setAdminUser();

        $context = \context_course::instance($this->course->id);
        $generator = $this->getDataGenerator();
        $group = $generator->create_group(['courseid' => $this->course->id, 'idnumber' => 'g1']);
        $block1data = [
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
                'progressBarIcons' => 0, // Non-default.
                'showpercentage' => defaults::SHOWPERCENTAGE,
                'progressTitle' => "Instance 1",
                'activitiesincluded' => defaults::ACTIVITIESINCLUDED,
                'group' => 'group-' . $group->id,
            ])),
        ];
        $generator->create_block('completion_progress', $block1data);
        $block2data = [
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
                'progressBarIcons' => 0, // Non-default.
                'showpercentage' => defaults::SHOWPERCENTAGE,
                'progressTitle' => "Instance 2",
                'activitiesincluded' => defaults::ACTIVITIESINCLUDED,
            ])),
        ];
        $generator->create_block('completion_progress', $block2data);

        $mdata = new \stdClass();
        $mdata->courseid = $this->course->id;
        $mdata->fullname = $this->course->fullname . ' Copy';
        $mdata->shortname = $this->course->shortname . ' Copy';
        $mdata->category = $this->course->category;
        $mdata->visible = 1;
        $mdata->startdate = $this->course->startdate;
        $mdata->enddate = $this->course->enddate;
        $mdata->idnumber = $this->course->idnumber . '_copy';
        $mdata->userdata = 0;

        if (method_exists('\copy_helper', 'process_formdata')) {
            // Moodle 3.11 or higher.
            $copydata = \copy_helper::process_formdata($mdata);
            \copy_helper::create_copy($copydata);
        } else {
            // Moodle 3.10 or older.
            $backupcopy = new \core_backup\copy\copy($mdata);
            $backupcopy->create_copy();
        }

        $now = time();
        $task = \core\task\manager::get_next_adhoc_task($now);
        $this->assertInstanceOf('\\core\\task\\asynchronous_copy_task', $task);
        $this->expectOutputRegex("/Course copy/");
        $task->execute();
        \core\task\manager::adhoc_task_complete($task);

        $copy = $DB->get_record('course', ['idnumber' => $mdata->idnumber]);
        $context = \context_course::instance($copy->id);
        $copygroup = groups_get_group_by_idnumber($copy->id, 'g1');

        $blocks = $DB->get_records('block_instances', [
            'blockname' => 'completion_progress',
            'parentcontextid' => $context->id,
        ]);
        $this->assertCount(2, $blocks);

        array_walk($blocks, function ($record) {
            $record->config = unserialize(base64_decode($record->configdata));
        });
        $copyblockmap = array_flip(array_map(function ($record) {
            return $record->config->progressTitle;
        }, $blocks));

        // Ensure both block instances were copied.
        $this->assertArrayHasKey('Instance 1', $copyblockmap);
        $this->assertArrayHasKey('Instance 2', $copyblockmap);

        // Ensure the configured group got remapped by the copy.
        $this->assertEquals('group-' . $copygroup->id, $blocks[$copyblockmap['Instance 1']]->config->group);
    }

    /**
     * Test course modules view urls.
     * @covers \block_completion_progress\completion_progress
     */
    public function test_view_urls(): void {
        // Add a block.
        $context = \context_course::instance($this->course->id);
        $blockinstance = $this->getDataGenerator()->create_block('completion_progress', [
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
        ]);

        $pageinstance = $this->getDataGenerator()->create_module('page', [
            'course' => $this->course->id,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        $labelinstance = $this->getDataGenerator()->create_module('label', [
            'course' => $this->course->id,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        $modinfo = get_fast_modinfo($this->course);
        $pagecm = $modinfo->get_cm($pageinstance->cmid);

        $progress = (new completion_progress($this->course))
                    ->for_user($this->students[0])
                    ->for_block_instance($blockinstance);
        $activities = $progress->get_activities();
        $this->assertEquals($pagecm->url->out(), $activities[0]->url);
        $this->assertEquals('', $activities[1]->url);
    }

    /** @var int Section state mask. */
    const SECTION_MASK            = 0b00001111;
    /** @var int Section visibility - visible. */
    const SECTION_VISIBLE         = 0b00000000;
    /** @var int Section visibility - hidden. */
    const SECTION_HIDDEN          = 0b00000001;
    /** @var int Section availability bit - visible if unavailable. */
    const SECTION_AVVISIBLE_BIT   = 0b00000000;
    /** @var int Section availability bit - hidden if unavailable. */
    const SECTION_AVHIDDEN_BIT    = 0b00000010;
    /** @var int Section availability bit - past-dated condition. */
    const SECTION_AVPAST_BIT      = 0b00000100;
    /** @var int Section availability bit - future-dated condition. */
    const SECTION_AVFUTURE_BIT    = 0b00001000;
    /** @var int Section availability - past-dated condition, visible if unavailable. */
    const SECTION_AVPASTVISIBLE   = (self::SECTION_AVVISIBLE_BIT | self::SECTION_AVPAST_BIT);
    /** @var int Section availability - past-dated condition, hidden if unavailable. */
    const SECTION_AVPASTHIDDEN    = (self::SECTION_AVHIDDEN_BIT | self::SECTION_AVPAST_BIT);
    /** @var int Section availability - future-dated condition, visible if unavailable. */
    const SECTION_AVFUTUREVISIBLE = (self::SECTION_AVVISIBLE_BIT | self::SECTION_AVFUTURE_BIT);
    /** @var int Section availability - future-dated condition, hidden if unavailable. */
    const SECTION_AVFUTUREHIDDEN  = (self::SECTION_AVHIDDEN_BIT | self::SECTION_AVFUTURE_BIT);

    /** @var int Activity state mask. */
    const ACTIVITY_MASK            = 0b11110000;
    /** @var int Activity visibility - visible. */
    const ACTIVITY_VISIBLE         = 0b00000000;
    /** @var int Activity visibility - hidden but available. */
    const ACTIVITY_STEALTH         = 0b00010000;
    /** @var int Activity availability bit - visible if unavailable. */
    const ACTIVITY_AVVISIBLE_BIT   = 0b00000000;
    /** @var int Activity availability bit - hidden if unavailable. */
    const ACTIVITY_AVHIDDEN_BIT    = 0b00100000;
    /** @var int Activity availability bit - past-dated condition. */
    const ACTIVITY_AVPAST_BIT      = 0b01000000;
    /** @var int Activity availability bit - future-dated condition. */
    const ACTIVITY_AVFUTURE_BIT    = 0b10000000;
    /** @var int Activity availability - past-dated condition, visible if unavailable. */
    const ACTIVITY_AVPASTVISIBLE   = (self::ACTIVITY_AVVISIBLE_BIT | self::ACTIVITY_AVPAST_BIT);
    /** @var int Activity availability - past-dated condition, hidden if unavailable. */
    const ACTIVITY_AVPASTHIDDEN    = (self::ACTIVITY_AVHIDDEN_BIT | self::ACTIVITY_AVPAST_BIT);
    /** @var int Activity availability - future-dated condition, visible if unavailable. */
    const ACTIVITY_AVFUTUREVISIBLE = (self::ACTIVITY_AVVISIBLE_BIT | self::ACTIVITY_AVFUTURE_BIT);
    /** @var int Activity availability - future-dated condition, hidden if unavailable. */
    const ACTIVITY_AVFUTUREHIDDEN  = (self::ACTIVITY_AVHIDDEN_BIT | self::ACTIVITY_AVFUTURE_BIT);

    /** @var int Visibility expectation - absent from bar. */
    const EXPECT_ABSENT = 0;
    /** @var int Visibility expectation - present in bar. */
    const EXPECT_PRESENT = 1;
    /** @var int Visibility expectation - present in bar without being linked to the cm. */
    const EXPECT_UNLINKED = 2;

    /**
     * A data provider supplying each of the possible combinations of activity or section
     * visibility and availability, and the expectation of what should be shown in the bar.
     * @return array
     */
    public static function visibility_and_availability_provider(): array {
        return [
            'Section: future, hidden / Activity: none = absent' => [
                self::SECTION_AVFUTUREHIDDEN | self::ACTIVITY_VISIBLE,
                self::EXPECT_ABSENT,
            ],
            'Section: future, hidden / Activity: future, hidden = absent' => [
                self::SECTION_AVFUTUREHIDDEN | self::ACTIVITY_AVFUTUREHIDDEN,
                self::EXPECT_ABSENT,
            ],
            'Section: future, hidden / Activity: past, hidden = absent' => [
                self::SECTION_AVFUTUREHIDDEN | self::ACTIVITY_AVPASTHIDDEN,
                self::EXPECT_ABSENT,
            ],
            'Section: future, hidden / Activity: future, visible = absent' => [
                self::SECTION_AVFUTUREHIDDEN | self::ACTIVITY_AVFUTUREVISIBLE,
                self::EXPECT_ABSENT,
            ],
            'Section: future, hidden / Activity: past, visible = absent' => [
                self::SECTION_AVFUTUREHIDDEN | self::ACTIVITY_AVPASTVISIBLE,
                self::EXPECT_ABSENT,
            ],

            'Section: past, hidden / Activity: none = present' => [
                self::SECTION_AVPASTHIDDEN | self::ACTIVITY_VISIBLE,
                self::EXPECT_PRESENT,
            ],
            'Section: past, hidden / Activity: future, hidden = absent' => [
                self::SECTION_AVPASTHIDDEN | self::ACTIVITY_AVFUTUREHIDDEN,
                self::EXPECT_ABSENT,
            ],
            'Section: past, hidden / Activity: past, hidden = present' => [
                self::SECTION_AVPASTHIDDEN | self::ACTIVITY_AVPASTHIDDEN,
                self::EXPECT_PRESENT,
            ],
            'Section: past, hidden / Activity: future, visible = unlinked' => [
                self::SECTION_AVPASTHIDDEN | self::ACTIVITY_AVFUTUREVISIBLE,
                self::EXPECT_UNLINKED,
            ],
            'Section: past, hidden / Activity: past, visible = present' => [
                self::SECTION_AVPASTHIDDEN | self::ACTIVITY_AVPASTVISIBLE,
                self::EXPECT_PRESENT,
            ],

            'Section: future, visible / Activity: none = absent' => [
                self::SECTION_AVFUTUREVISIBLE | self::ACTIVITY_VISIBLE,
                self::EXPECT_ABSENT,
            ],
            'Section: future, visible / Activity: future, hidden = absent' => [
                self::SECTION_AVFUTUREVISIBLE | self::ACTIVITY_AVFUTUREHIDDEN,
                self::EXPECT_ABSENT,
            ],
            'Section: future, visible / Activity: past, hidden = absent' => [
                self::SECTION_AVFUTUREVISIBLE | self::ACTIVITY_AVPASTHIDDEN,
                self::EXPECT_ABSENT,
            ],
            'Section: future, visible / Activity: future, visible = absent' => [
                self::SECTION_AVFUTUREVISIBLE | self::ACTIVITY_AVFUTUREVISIBLE,
                self::EXPECT_ABSENT,
            ],
            'Section: future, visible / Activity: past, visible = absent' => [
                self::SECTION_AVFUTUREVISIBLE | self::ACTIVITY_AVPASTVISIBLE,
                self::EXPECT_ABSENT,
            ],

            'Section: past, visible / Activity: none = present' => [
                self::SECTION_AVPASTVISIBLE | self::ACTIVITY_VISIBLE,
                self::EXPECT_PRESENT,
            ],
            'Section: past, visible / Activity: future, hidden = absent' => [
                self::SECTION_AVPASTVISIBLE | self::ACTIVITY_AVFUTUREHIDDEN,
                self::EXPECT_ABSENT,
            ],
            'Section: past, visible / Activity: past, hidden = present' => [
                self::SECTION_AVPASTVISIBLE | self::ACTIVITY_AVPASTHIDDEN,
                self::EXPECT_PRESENT,
            ],
            'Section: past, visible / Activity: future, visible = unlinked' => [
                self::SECTION_AVPASTVISIBLE | self::ACTIVITY_AVFUTUREVISIBLE,
                self::EXPECT_UNLINKED,
            ],
            'Section: past, visible / Activity: past, visible = present' => [
                self::SECTION_AVPASTVISIBLE | self::ACTIVITY_AVPASTVISIBLE,
                self::EXPECT_PRESENT,
            ],

            'Section: none / Activity: none = present' => [
                self::SECTION_VISIBLE | self::ACTIVITY_VISIBLE,
                self::EXPECT_PRESENT,
            ],
            'Section: none / Activity: stealth = present' => [
                self::SECTION_VISIBLE | self::ACTIVITY_STEALTH,
                self::EXPECT_PRESENT,
            ],
        ];
    }

    /**
     * Test that various combinations of activity or section visibility and availability
     * behave as expected.
     * @param int $inputconfig
     * @param int $expected
     * @covers \block_completion_progress\completion_progress
     * @dataProvider visibility_and_availability_provider
     */
    public function test_visibility_and_availability($inputconfig, $expected): void {
        $now = time();
        $pasttime = strtotime('-1 month', $now);
        $futuretime = strtotime('+1 month', $now);
        $this->mock_clock_with_frozen($now);

        $generator = $this->getDataGenerator();

        $section = $generator->create_course_section([
            'course' => $this->course->id,
            'section' => 1,
        ]);
        $sectionupdates = [];
        if ($inputconfig & self::SECTION_HIDDEN) {
            $sectionupdates['visible'] = 0;
        }
        if ($inputconfig & (self::SECTION_AVPAST_BIT | self::SECTION_AVFUTURE_BIT)) {
            $sectionupdates['availability'] = [
                'op' => '&',
                'c' => [['type' => 'date', 'd' => '>=', 't' => null]],
                'showc' => [true],
            ];
            if ($inputconfig & self::SECTION_AVHIDDEN_BIT) {
                $sectionupdates['availability']['showc'] = [false]; // Hidden bit set.
            }
            if ($inputconfig & self::SECTION_AVPAST_BIT) {
                $sectionupdates['availability']['c'][0]['t'] = $pasttime;
            } else if ($inputconfig & self::SECTION_AVFUTURE_BIT) {
                $sectionupdates['availability']['c'][0]['t'] = $futuretime;
            } else {
                throw new \coding_exception('expected either past or future');
            }
            $sectionupdates['availability'] = json_encode($sectionupdates['availability']);
        }
        if ($sectionupdates) {
            course_update_section($this->course, $section, $sectionupdates);
        }

        $activityvisibleoncourse = 1;
        if ($inputconfig & self::ACTIVITY_STEALTH) {
            $activityvisibleoncourse = 0;
        }
        $activityavailability = null;
        if ($inputconfig & (self::ACTIVITY_AVPAST_BIT | self::ACTIVITY_AVFUTURE_BIT)) {
            $activityavailability = [
                'op' => '&',
                'c' => [['type' => 'date', 'd' => '>=', 't' => null]],
                'showc' => [true],
            ];
            if ($inputconfig & self::ACTIVITY_AVHIDDEN_BIT) {
                $activityavailability['showc'] = [false]; // Hidden bit set.
            }
            if ($inputconfig & self::ACTIVITY_AVPAST_BIT) {
                $activityavailability['c'][0]['t'] = $pasttime;
            } else if ($inputconfig & self::ACTIVITY_AVFUTURE_BIT) {
                $activityavailability['c'][0]['t'] = $futuretime;
            } else {
                throw new \coding_exception('expected either past or future');
            }
            $activityavailability = json_encode($activityavailability);
        }

        $activity = $this->getDataGenerator()->create_module('page', [
            'course' => $this->course->id,
            'section' => $section->sectionnum,
            'completion' => COMPLETION_TRACKING_MANUAL,
            'visibleoncoursepage' => $activityvisibleoncourse,
            'availability' => $activityavailability,
        ]);

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
        $blockinstance = $generator->create_block('completion_progress', $blockinfo);

        $block = new completion_progress($this->course);
        $block->for_block_instance($blockinstance);
        $block->for_user($this->students[0]);

        $visibles = $block->get_visible_activities();
        if ($expected == self::EXPECT_ABSENT) {
            $this->assertEmpty($visibles);
        } else if ($expected == self::EXPECT_PRESENT) {
            $this->assertCount(1, $visibles);
            $this->assertTrue($visibles[0]->available);
        } else if ($expected == self::EXPECT_UNLINKED) {
            $this->assertCount(1, $visibles);
            $this->assertFalse($visibles[0]->available);
        } else {
            throw new \coding_exception('unexpected expected value');
        }
    }
}
