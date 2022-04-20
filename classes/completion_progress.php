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
 * Completion Progress block.
 *
 * @package    block_completion_progress
 * @copyright  2016 Michael de Raadt
 * @copyright  2021 Jonathon Fowler <fowlerj@usq.edu.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_completion_progress;

use stdClass;
use completion_info;
use context_course;
use coding_exception;

/**
 * Completion Progress.
 *
 * @package    block_completion_progress
 * @copyright  2016 Michael de Raadt
 * @copyright  2021 Jonathon Fowler <fowlerj@usq.edu.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion_progress implements \renderable {
    /**
     * Sort activities by course order.
     */
    const ORDERBY_COURSE = 'orderbycourse';

    /**
     * Sort activities by expected time order.
     */
    const ORDERBY_TIME = 'orderbytime';

    /**
     * The course.
     * @var object
     */
    protected $course;

    /**
     * The course context.
     * @var context_course
     */
    protected $context;

    /**
     * Completion info for the course.
     * @var completion_info
     */
    protected $completioninfo;

    /**
     * The user.
     * @var object
     */
    protected $user;

    /**
     * Block instance record.
     * @var stdClass
     */
    protected $blockinstance;

    /**
     * Block instance config.
     * @var stdClass
     */
    protected $blockconfig;

    /**
     * List of activities.
     * @var array cmid => obj
     */
    protected $activities = null;

    /**
     * List of visible activities.
     * @var array cmid => obj
     */
    protected $visibleactivities = null;

    /**
     * List of grade exclusions.
     * @var array of: [module-instance-userid, ...]
     */
    protected $exclusions = null;

    /**
     * List of submissions.
     * @var array of arrays: userid => [cmid => obj]
     */
    protected $submissions = null;

    /**
     * List of computed completions.
     * @var array of arrays: userid => [cmid => state]
     */
    protected $completions = null;

    /**
     * Whether exclusions have been loaded for all course users already.
     * @var boolean
     */
    protected $exclusionsforall = false;

    /**
     * Whether submissions have been loaded for all course users already.
     * @var boolean
     */
    protected $submissionsforall = false;

    /**
     * Whether completions have been loaded for all course users already.
     * @var boolean
     */
    protected $completionsforall = false;

    /**
     * Simple bar mode (for overview).
     * @var boolean
     */
    protected $simplebar = false;

    /**
     * Constructor.
     * @param object|int $courseorid
     */
    public function __construct($courseorid) {
        global $CFG;

        require_once($CFG->libdir.'/completionlib.php');

        if (is_object($courseorid)) {
            $this->course = $courseorid;
        } else {
            $this->course = get_course($courseorid);
        }
        $this->context = context_course::instance($this->course->id);
        $this->completioninfo = new completion_info($this->course);
    }

    /**
     * Specialise for a specific user.
     * @param stdClass $user containing minimum of core_user\fields::for_name()
     * @return self
     */
    public function for_user(stdClass $user): self {
        $this->user = $user;

        $this->load_exclusions();
        $this->load_submissions();
        $this->load_completions();
        $this->filter_visible_activities();

        return $this;
    }

    /**
     * Specialise for overview page use.
     * @return self
     */
    public function for_overview() {
        if ($this->user) {
            throw new coding_exception('cannot re-specialise for overview');
        }
        $this->user = null;
        $this->simplebar = true;

        $this->load_exclusions();
        $this->load_submissions();
        $this->load_completions();

        return $this;
    }

    /**
     * Specialise for a particular block instance.
     * @param stdClass $instance Instance record.
     * @param boolean $selectedonly Whether to filter by configured selected items.
     * @return self
     */
    public function for_block_instance(stdClass $instance, $selectedonly = true): self {
        if ($this->blockinstance) {
            throw new coding_exception('cannot re-specialise for a different block instance');
        }
        $this->blockinstance = $instance;
        $this->blockconfig = (object)(array)unserialize(base64_decode($instance->configdata ?? ''));

        $this->load_activities($selectedonly);
        $this->filter_visible_activities();

        return $this;
    }

    /**
     * Return the course object.
     * @return object
     */
    public function get_course(): stdClass {
        return $this->course;
    }

    /**
     * Return the course object.
     * @return context_course
     */
    public function get_context(): context_course {
        return $this->context;
    }

    /**
     * Return the completion info object.
     * @return completion_info
     */
    public function get_completion_info(): completion_info {
        return $this->completioninfo;
    }

    /**
     * Return the user.
     * @return stdClass
     */
    public function get_user(): ?stdClass {
        return $this->user;
    }

    /**
     * Return the simple bar mode.
     * @return boolean
     */
    public function is_simple_bar(): bool {
        return $this->simplebar;
    }

    /**
     * Check whether any activities are available.
     * @return boolean
     */
    public function has_activities(): bool {
        if ($this->activities === null) {
            throw new coding_exception('activities not loaded until for_block_instance() is called');
        }
        return !empty($this->activities);
    }

    /**
     * Return the activities in presentation order.
     * @param string|null $orderoverride
     * @return array
     */
    public function get_activities($orderoverride = null): array {
        if ($this->activities === null) {
            throw new coding_exception('activities not loaded until for_block_instance() is called');
        }
        $order = $orderoverride ?? $this->blockconfig->orderby ?? self::ORDERBY_COURSE;
        usort($this->activities, [$this, 'sorter_' . $order]);
        return $this->activities;
    }

    /**
     * Check whether any visible activities are available.
     * @return boolean
     */
    public function has_visible_activities(): bool {
        if ($this->visibleactivities === null) {
            throw new coding_exception('visible activities not computed until for_block_instance() is called');
        }
        return !empty($this->visibleactivities);
    }

    /**
     * Return the activities visible to the user in presentation order.
     * @param string|null $orderoverride
     * @return array
     */
    public function get_visible_activities($orderoverride = null): array {
        if ($this->visibleactivities === null) {
            throw new coding_exception('visible activities not computed until for_block_instance() is called');
        }
        $order = $orderoverride ?? $this->blockconfig->orderby ?? self::ORDERBY_COURSE;
        usort($this->visibleactivities, [$this, 'sorter_' . $order]);
        return $this->visibleactivities;
    }

    /**
     * Return the exclusions.
     * @return array of modname-modinstance-userid formatted items
     */
    public function get_exclusions(): array {
        return $this->exclusions;
    }

    /**
     * Get block instance.
     * @return stdClass|null
     */
    public function get_block_instance(): ?stdClass {
        return $this->blockinstance;
    }

    /**
     * Get block configuration.
     * @return stdClass
     */
    public function get_block_config(): stdClass {
        return $this->blockconfig;
    }

    /**
     * Get user activity submissions.
     * @return array cmid => info
     */
    public function get_submissions(): array {
        if ($this->submissions === null) {
            throw new coding_exception('submissions not computed until for_user() or for_overview() is called');
        }
        if ($this->user) {
            return $this->submissions[$this->user->id] ?? [];
        } else {
            throw new coding_exception('unimplemented');
        }
    }

    /**
     * Get user activity completion states.
     * @return array cmid => status
     */
    public function get_completions() {
        if ($this->completions === null) {
            throw new coding_exception('completions not computed until for_user() or for_overview() is called');
        }
        if ($this->user) {
            // Filter to visible activities and fill in gaps.
            $completions = $this->completions[$this->user->id] ?? [];
            $ret = [];
            foreach ($this->visibleactivities as $activity) {
                $ret[$activity->id] = $completions[$activity->id] ?? COMPLETION_INCOMPLETE;
            }
            return $ret;
        } else {
            throw new coding_exception('unimplemented');
        }
    }

    /**
     * Calculates an overall percentage of progress.
     * @return integer  Progress value as a percentage
     */
    public function get_percentage(): ?int {
        $completions = $this->get_completions();
        if (count($completions) == 0) {
            return null;
        }

        $completecount = 0;
        foreach ($completions as $complete) {
            if ($complete == COMPLETION_COMPLETE || $complete == COMPLETION_COMPLETE_PASS) {
                $completecount++;
            }
        }

        return (int)round(100 * $completecount / count($this->visibleactivities));
    }

    /**
     * Used to compare two activity entries based on order on course page.
     *
     * @param array $a
     * @param array $b
     * @return integer
     */
    private function sorter_orderbycourse($a, $b): int {
        if ($a->section != $b->section) {
            return $a->section <=> $b->section;
        } else {
            return $a->position <=> $b->position;
        }
    }

    /**
     * Used to compare two activity entries based their expected completion times
     *
     * @param array $a
     * @param array $b
     * @return integer
     */
    private function sorter_orderbytime($a, $b): int {
        if ($a->expected != 0 && $b->expected != 0 && $a->expected != $b->expected) {
            return $a->expected <=> $b->expected;
        } else if ($a->expected != 0 && $b->expected == 0) {
            return -1;
        } else if ($a->expected == 0 && $b->expected != 0) {
            return 1;
        } else {
            return $this->sorter_orderbycourse($a, $b);
        }
    }


    /**
     * Loads activities with completion set in current course.
     *
     * @param boolean $selectedonly Whether to filter by configured selected items.
     * @return array
     */
    protected function load_activities($selectedonly) {
        $modinfo = get_fast_modinfo($this->course, -1);
        $sections = $modinfo->get_sections();
        $selectedonly = $selectedonly && ($this->blockconfig->activitiesincluded ?? '') === 'selectedactivities';
        $selectedcms = $this->blockconfig->selectactivities ?? [];

        $this->activities = [];

        foreach ($modinfo->instances as $module => $cms) {
            $modulename = get_string('pluginname', $module);
            foreach ($cms as $cm) {
                if ($cm->completion == COMPLETION_TRACKING_NONE) {
                    continue;
                }
                if ($selectedonly && !in_array($module.'-'.$cm->instance, $selectedcms)) {
                    continue;
                }

                $this->activities[$cm->id] = (object)[
                    'type'       => $module,
                    'modulename' => $modulename,
                    'id'         => $cm->id,
                    'instance'   => $cm->instance,
                    'name'       => $cm->get_formatted_name(),
                    'expected'   => $cm->completionexpected,
                    'section'    => $cm->sectionnum,
                    'position'   => array_search($cm->id, $sections[$cm->sectionnum]),
                    'url'        => $cm->url instanceof \moodle_url ? $cm->url->out() : '',
                    'context'    => $cm->context,
                    'icon'       => $cm->get_icon_url(),
                    'available'  => $cm->available,
                ];
            }
        }
    }

    /**
     * Filter down the activities to those a user can see.
     */
    protected function filter_visible_activities() {
        global $CFG, $USER;

        if (!$this->user || $this->activities === null) {
            return;
        }

        $this->visibleactivities = [];
        $modinfo = get_fast_modinfo($this->course, $this->user->id);
        $canviewhidden = has_capability('moodle/course:viewhiddenactivities', $this->context, $this->user);

        // Keep only activities that are visible.
        foreach ($this->activities as $key => $activity) {
            $cm = $modinfo->cms[$activity->id];

            // Check visibility in course.
            if (!$cm->visible && !$canviewhidden) {
                continue;
            }

            // Check availability, allowing for visible, but not accessible items.
            if (!empty($CFG->enableavailability)) {
                if ($canviewhidden) {
                    $activity->available = true;
                } else {
                    if (isset($cm->available) && !$cm->available && empty($cm->availableinfo)) {
                        continue;
                    }
                    $activity->available = $cm->available;
                }
            }

            // Check for exclusions.
            if (in_array($activity->type.'-'.$activity->instance.'-'.$this->user->id, $this->exclusions)) {
                continue;
            }

            // Save the visible event.
            $this->visibleactivities[$key] = $activity;
        }
    }

    /**
     * Finds gradebook exclusions for students in the course.
     */
    protected function load_exclusions() {
        global $DB;

        if ($this->exclusionsforall) {
            // Already loaded.
            return;
        }

        $query = "SELECT g.id, i.itemmodule, i.iteminstance, g.userid
                   FROM {grade_grades} g, {grade_items} i
                  WHERE i.courseid = :courseid
                    AND i.id = g.itemid
                    AND g.excluded <> 0";
        $params = ['courseid' => $this->course->id];
        if ($this->user) {
            $query .= " AND g.userid = :userid";
            $params['userid'] = $this->user->id;
        } else {
            // Avoid refetching this info if specialising for user later.
            $this->exclusionsforall = true;
        }

        $this->exclusions = [];
        foreach ($DB->get_records_sql($query, $params) as $rec) {
            $this->exclusions[] = $rec->itemmodule . '-' . $rec->iteminstance . '-' . $rec->userid;
        }
    }

    /**
     * Loads completion information for enrolled users in the course.
     */
    protected function load_completions() {
        global $DB;

        if ($this->completionsforall) {
            // Already loaded.
            return;
        }

        // Somewhat faster than lots of calls to completion_info::get_data($cm, true, $userid)
        // where its cache can't be used because the userid is different.
        $enrolsql = get_enrolled_join($this->context, 'u.id', false);
        $query = "SELECT DISTINCT " . $DB->sql_concat('cm.id', "'-'", 'u.id') . " AS id,
                        u.id AS userid, cm.id AS cmid,
                        COALESCE(cmc.completionstate, :incomplete) AS completionstate
                    FROM {user} u {$enrolsql->joins}
              CROSS JOIN {course_modules} cm
               LEFT JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id AND cmc.userid = u.id
                   WHERE {$enrolsql->wheres}
                     AND cm.course = :courseid
                     AND cm.completion <> :none";
        $params = $enrolsql->params + [
            'courseid' => $this->course->id,
            'incomplete' => COMPLETION_INCOMPLETE,
            'none' => COMPLETION_TRACKING_NONE,
        ];
        if ($this->user) {
            $query .= " AND u.id = :userid";
            $params['userid'] = $this->user->id;
        } else {
            // Avoid refetching this info if specialising for user later.
            $this->completionsforall = true;
        }

        $rset = $DB->get_recordset_sql($query, $params);
        $this->completions = [];
        foreach ($rset as $compl) {
            $submission = $this->submissions[$compl->userid][$compl->cmid] ?? null;

            if ($compl->completionstate == COMPLETION_INCOMPLETE && $submission) {
                $this->completions[$compl->userid][$compl->cmid] = 'submitted';
            } else if ($compl->completionstate == COMPLETION_COMPLETE_FAIL && $submission
                    && !$submission->graded) {
                $this->completions[$compl->userid][$compl->cmid] = 'submitted';
            } else {
                $this->completions[$compl->userid][$compl->cmid] = $compl->completionstate;
            }
        }
        $rset->close();
    }

    /**
     * Find submissions for students in the course.
     */
    protected function load_submissions() {
        global $DB, $CFG;

        if ($this->submissionsforall) {
            // Already loaded.
            return;
        }

        require_once($CFG->dirroot . '/mod/quiz/lib.php');

        $params = [
            'courseid' => $this->course->id,
        ];

        if ($this->user) {
            $assignwhere = 'AND s.userid = :userid';
            $workshopwhere = 'AND s.authorid = :userid';
            $quizwhere = 'AND qa.userid = :userid';

            $params += [
              'userid' => $this->user->id,
            ];
        } else {
            $assignwhere = '';
            $workshopwhere = '';
            $quizwhere = '';

            // Avoid refetching this info if specialising for user later.
            $this->submissionsforall = true;
        }

        // Queries to deliver instance IDs of activities with submissions by user.
        $queries = array (
            [
                // Assignments with individual submission, or groups requiring a submission per user,
                // or ungrouped users in a group submission situation.
                'module' => 'assign',
                'query' => "SELECT ". $DB->sql_concat('s.userid', "'-'", 'c.id') ." AS id,
                             s.userid, c.id AS cmid,
                             MAX(CASE WHEN ag.grade IS NULL OR ag.grade = -1 THEN 0 ELSE 1 END) AS graded
                          FROM {assign_submission} s
                            INNER JOIN {assign} a ON s.assignment = a.id
                            INNER JOIN {course_modules} c ON c.instance = a.id
                            INNER JOIN {modules} m ON m.name = 'assign' AND m.id = c.module
                            LEFT JOIN {assign_grades} ag ON ag.assignment = s.assignment
                                  AND ag.attemptnumber = s.attemptnumber
                                  AND ag.userid = s.userid
                          WHERE s.latest = 1
                            AND s.status = 'submitted'
                            AND a.course = :courseid
                            AND (
                                a.teamsubmission = 0 OR
                                (a.teamsubmission <> 0 AND a.requireallteammemberssubmit <> 0 AND s.groupid = 0) OR
                                (a.teamsubmission <> 0 AND a.preventsubmissionnotingroup = 0 AND s.groupid = 0)
                            )
                            $assignwhere
                        GROUP BY s.userid, c.id",
                'params' => [ ],
            ],

            [
                // Assignments with groups requiring only one submission per group.
                'module' => 'assign',
                'query' => "SELECT ". $DB->sql_concat('s.userid', "'-'", 'c.id') ." AS id,
                             s.userid, c.id AS cmid,
                             MAX(CASE WHEN ag.grade IS NULL OR ag.grade = -1 THEN 0 ELSE 1 END) AS graded
                          FROM {assign_submission} gs
                            INNER JOIN {assign} a ON gs.assignment = a.id
                            INNER JOIN {course_modules} c ON c.instance = a.id
                            INNER JOIN {modules} m ON m.name = 'assign' AND m.id = c.module
                            INNER JOIN {groups_members} s ON s.groupid = gs.groupid
                            LEFT JOIN {assign_grades} ag ON ag.assignment = gs.assignment
                                  AND ag.attemptnumber = gs.attemptnumber
                                  AND ag.userid = s.userid
                          WHERE gs.latest = 1
                            AND gs.status = 'submitted'
                            AND gs.userid = 0
                            AND a.course = :courseid
                            AND (a.teamsubmission <> 0 AND a.requireallteammemberssubmit = 0)
                            $assignwhere
                        GROUP BY s.userid, c.id",
                'params' => [ ],
            ],

            [
                'module' => 'workshop',
                'query' => "SELECT ". $DB->sql_concat('s.authorid', "'-'", 'c.id') ." AS id,
                               s.authorid AS userid, c.id AS cmid,
                               1 AS graded
                             FROM {workshop_submissions} s, {workshop} w, {modules} m, {course_modules} c
                            WHERE s.workshopid = w.id
                              AND w.course = :courseid
                              AND m.name = 'workshop'
                              AND m.id = c.module
                              AND c.instance = w.id
                              $workshopwhere
                          GROUP BY s.authorid, c.id",
                'params' => [ ],
            ],

            [
                // Quizzes with 'first' and 'last attempt' grading methods.
                'module' => 'quiz',
                'query' => "SELECT ". $DB->sql_concat('qa.userid', "'-'", 'c.id') ." AS id,
                           qa.userid, c.id AS cmid,
                           (CASE WHEN qa.sumgrades IS NULL THEN 0 ELSE 1 END) AS graded
                         FROM {quiz_attempts} qa
                           INNER JOIN {quiz} q ON q.id = qa.quiz
                           INNER JOIN {course_modules} c ON c.instance = q.id
                           INNER JOIN {modules} m ON m.name = 'quiz' AND m.id = c.module
                        WHERE qa.state = 'finished'
                          AND q.course = :courseid
                          AND qa.attempt = (
                            SELECT CASE WHEN q.grademethod = :gmfirst THEN MIN(qa1.attempt)
                                        WHEN q.grademethod = :gmlast THEN MAX(qa1.attempt) END
                            FROM {quiz_attempts} qa1
                            WHERE qa1.quiz = qa.quiz
                              AND qa1.userid = qa.userid
                              AND qa1.state = 'finished'
                          )
                          $quizwhere",
                'params' => [
                    'gmfirst' => QUIZ_ATTEMPTFIRST,
                    'gmlast' => QUIZ_ATTEMPTLAST,
                ],
            ],
            [
                // Quizzes with 'maximum' and 'average' grading methods.
                'module' => 'quiz',
                'query' => "SELECT ". $DB->sql_concat('qa.userid', "'-'", 'c.id') ." AS id,
                           qa.userid, c.id AS cmid,
                           MIN(CASE WHEN qa.sumgrades IS NULL THEN 0 ELSE 1 END) AS graded
                         FROM {quiz_attempts} qa
                           INNER JOIN {quiz} q ON q.id = qa.quiz
                           INNER JOIN {course_modules} c ON c.instance = q.id
                           INNER JOIN {modules} m ON m.name = 'quiz' AND m.id = c.module
                        WHERE (q.grademethod = :gmmax OR q.grademethod = :gmavg)
                          AND qa.state = 'finished'
                          AND q.course = :courseid
                          $quizwhere
                       GROUP BY qa.userid, c.id",
                'params' => [
                    'gmmax' => QUIZ_GRADEHIGHEST,
                    'gmavg' => QUIZ_GRADEAVERAGE,
                ],
            ],
        );

        $this->submissions = [];
        foreach ($queries as $spec) {
            $results = $DB->get_records_sql($spec['query'], $params + $spec['params']);
            foreach ($results as $obj) {
                unset($obj->id);
                $this->submissions[$obj->userid][$obj->cmid] = $obj;
            }
        }
    }

}
