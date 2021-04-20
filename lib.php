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
 * Completion Progress block common configuration and helper functions
 *
 * @package    block_completion_progress
 * @copyright  2016 Michael de Raadt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir.'/completionlib.php');

/**
 * Default number of cells per row in wrap mode.
 */
const DEFAULT_COMPLETIONPROGRESS_WRAPAFTER = 16;

/**
 * Default presentation mode for long bars: squeeze, scroll, or wrap.
 */
const DEFAULT_COMPLETIONPROGRESS_LONGBARS = 'squeeze';

/**
 * Default course name (long/short) to show on Dashboard pages.
 */
const DEFAULT_COMPLETIONPROGRESS_COURSENAMETOSHOW = 'shortname';

/**
 * Default display of inactive students on the overview page.
 */
const DEFAULT_COMPLETIONPROGRESS_SHOWINACTIVE = 0;

/**
 * Default display of student 'last in course' time on overview page.
 */
const DEFAULT_COMPLETIONPROGRESS_SHOWLASTINCOURSE = 1;

/**
 * Default forcing the display of status icons in bar cells.
 */
const DEFAULT_COMPLETIONPROGRESS_FORCEICONSINBAR = 0;

/**
 * Default display of status icons in bar cells.
 */
const DEFAULT_COMPLETIONPROGRESS_PROGRESSBARICONS = 0;

/**
 * Default cell sort order mode: orderbytime or orderbycourse.
 */
const DEFAULT_COMPLETIONPROGRESS_ORDERBY = 'orderbytime';

/**
 * Default display of progress percentage in block.
 */
const DEFAULT_COMPLETIONPROGRESS_SHOWPERCENTAGE = 0;

/**
 * Default choice of activites included: activitycompletion or selectedactivities.
 */
const DEFAULT_COMPLETIONPROGRESS_ACTIVITIESINCLUDED = 'activitycompletion';

/**
 * Finds submissions for a user in a course
 *
 * @param int    $courseid ID of the course
 * @param int    $userid   ID of user in the course, or 0 for all
 * @return array Course module IDs submissions
 */
function block_completion_progress_submissions($courseid, $userid = 0) {
    global $DB, $CFG;

    require_once($CFG->dirroot . '/mod/quiz/lib.php');

    $submissions = array();
    $params = array(
        'courseid' => $courseid,
    );

    if ($userid) {
        $assignwhere = 'AND s.userid = :userid';
        $workshopwhere = 'AND s.authorid = :userid';
        $quizwhere = 'AND qa.userid = :userid';

        $params += [
          'userid' => $userid,
        ];
    } else {
        $assignwhere = '';
        $workshopwhere = '';
        $quizwhere = '';
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

    foreach ($queries as $spec) {
        $results = $DB->get_records_sql($spec['query'], $params + $spec['params']);
        foreach ($results as $id => $obj) {
            $submissions[$id] = $obj;
        }
    }

    ksort($submissions);

    return $submissions;
}

/**
 * Returns the alternate links for teachers
 *
 * @return array URLs and associated capabilities, per activity
 */
function block_completion_progress_modules_with_alternate_links() {
    global $CFG;

    $alternatelinks = array(
        'assign' => array(
            'url' => '/mod/assign/view.php?id=:cmid&action=grading',
            'capability' => 'mod/assign:grade',
        ),
        'feedback' => array(
            // Breaks if anonymous feedback is collected.
            'url' => '/mod/feedback/show_entries.php?id=:cmid&do_show=showoneentry&userid=:userid',
            'capability' => 'mod/feedback:viewreports',
        ),
        'lesson' => array(
            'url' => '/mod/lesson/report.php?id=:cmid&action=reportdetail&userid=:userid',
            'capability' => 'mod/lesson:viewreports',
        ),
        'quiz' => array(
            'url' => '/mod/quiz/report.php?id=:cmid&mode=overview',
            'capability' => 'mod/quiz:viewreports',
        ),
    );

    if ($CFG->version > 2015111604) {
        $alternatelinks['assign']['url'] = '/mod/assign/view.php?id=:cmid&action=grade&userid=:userid';
    }

    return $alternatelinks;
}

/**
 * Returns the activities with completion set in current course
 *
 * @param int    $courseid   ID of the course
 * @param int    $config     The block instance configuration
 * @param string $forceorder An override for the course order setting
 * @return array Activities with completion settings in the course
 */
function block_completion_progress_get_activities($courseid, $config = null, $forceorder = null) {
    $modinfo = get_fast_modinfo($courseid, -1);
    $sections = $modinfo->get_sections();
    $activities = array();
    foreach ($modinfo->instances as $module => $instances) {
        $modulename = get_string('pluginname', $module);
        foreach ($instances as $cm) {
            if (
                $cm->completion != COMPLETION_TRACKING_NONE && (
                    $config == null || (
                        !isset($config->activitiesincluded) || (
                            $config->activitiesincluded != 'selectedactivities' ||
                                !empty($config->selectactivities) &&
                                in_array($module.'-'.$cm->instance, $config->selectactivities))))
            ) {
                $activities[] = array (
                    'type'       => $module,
                    'modulename' => $modulename,
                    'id'         => $cm->id,
                    'instance'   => $cm->instance,
                    'name'       => format_string($cm->name),
                    'expected'   => $cm->completionexpected,
                    'section'    => $cm->sectionnum,
                    'position'   => array_search($cm->id, $sections[$cm->sectionnum]),
                    'url'        => method_exists($cm->url, 'out') ? $cm->url->out() : '',
                    'context'    => $cm->context,
                    'icon'       => $cm->get_icon_url(),
                    'available'  => $cm->available,
                );
            }
        }
    }

    // Sort by first value in each element, which is time due.
    if ($forceorder == 'orderbycourse' || ($config && $config->orderby == 'orderbycourse')) {
        usort($activities, 'block_completion_progress_compare_events');
    } else {
        usort($activities, 'block_completion_progress_compare_times');
    }

    return $activities;
}

/**
 * Used to compare two activities/resources based on order on course page
 *
 * @param array $a array of event information
 * @param array $b array of event information
 * @return <0, 0 or >0 depending on order of activities/resources on course page
 */
function block_completion_progress_compare_events($a, $b) {
    if ($a['section'] != $b['section']) {
        return $a['section'] - $b['section'];
    } else {
        return $a['position'] - $b['position'];
    }
}

/**
 * Used to compare two activities/resources based their expected completion times
 *
 * @param array $a array of event information
 * @param array $b array of event information
 * @return <0, 0 or >0 depending on time then order of activities/resources
 */
function block_completion_progress_compare_times($a, $b) {
    if (
        $a['expected'] != 0 &&
        $b['expected'] != 0 &&
        $a['expected'] != $b['expected']
    ) {
        return $a['expected'] - $b['expected'];
    } else if ($a['expected'] != 0 && $b['expected'] == 0) {
        return -1;
    } else if ($a['expected'] == 0 && $b['expected'] != 0) {
        return 1;
    } else {
        return block_completion_progress_compare_events($a, $b);
    }
}

/**
 * Filters activities that a user cannot see due to grouping constraints
 *
 * @param array  $activities The possible activities that can occur for modules
 * @param array  $userid The user's id
 * @param string $courseid the course for filtering visibility
 * @param array  $exclusions Assignment exemptions for students in the course
 * @return array The array with restricted activities removed
 */
function block_completion_progress_filter_visibility($activities, $userid, $courseid, $exclusions) {
    global $CFG;
    $filteredactivities = array();
    $modinfo = get_fast_modinfo($courseid, $userid);
    $coursecontext = CONTEXT_COURSE::instance($courseid);

    // Keep only activities that are visible.
    foreach ($activities as $activity) {

        $coursemodule = $modinfo->cms[$activity['id']];

        // Check visibility in course.
        if (!$coursemodule->visible && !has_capability('moodle/course:viewhiddenactivities', $coursecontext, $userid)) {
            continue;
        }

        // Check availability, allowing for visible, but not accessible items.
        if (!empty($CFG->enableavailability)) {
            if (has_capability('moodle/course:viewhiddenactivities', $coursecontext, $userid)) {
                $activity['available'] = true;
            } else {
                if (isset($coursemodule->available) && !$coursemodule->available && empty($coursemodule->availableinfo)) {
                    continue;
                }
                $activity['available'] = $coursemodule->available;
            }
        }

        // Check for exclusions.
        if (in_array($activity['type'].'-'.$activity['instance'].'-'.$userid, $exclusions)) {
            continue;
        }

        // Save the visible event.
        $filteredactivities[] = $activity;
    }
    return $filteredactivities;
}

/**
 * Checked if a user has completed an activity/resource
 *
 * @param array $activities  The activities with completion in the course
 * @param int   $userid      The user's id
 * @param int   $course      The course instance
 * @param array $submissions Submissions information, keyed by 'userid-cmid'
 * @return array   an describing the user's attempts based on module+instance identifiers
 */
function block_completion_progress_completions($activities, $userid, $course, $submissions) {
    $completions = array();
    $completioninfo = new completion_info($course);
    $cm = new stdClass();

    foreach ($activities as $activity) {
        $cm->id = $activity['id'];
        $completion = $completioninfo->get_data($cm, true, $userid);
        $submission = $submissions[$userid . '-' . $cm->id] ?? null;

        if ($completion->completionstate == COMPLETION_INCOMPLETE && $submission) {
            $completions[$cm->id] = 'submitted';
        } else if ($completion->completionstate == COMPLETION_COMPLETE_FAIL && $submission
                && !$submission->graded) {
            $completions[$cm->id] = 'submitted';
        } else {
            $completions[$cm->id] = $completion->completionstate;
        }
    }

    return $completions;
}

/**
 * Draws a progress bar
 *
 * @param array    $activities  The activities with completion in the course
 * @param array    $completions The user's completion of course activities
 * @param stdClass $config      The blocks instance configuration settings
 * @param int      $userid      The user's id
 * @param int      $courseid    The course id
 * @param int      $instance    The block instance (to identify it on page)
 * @param bool     $simple      Controls whether instructions are shown below a progress bar
 * @return string  Progress Bar HTML content
 */
function block_completion_progress_bar($activities, $completions, $config, $userid, $courseid, $instance, $simple = false) {
    global $OUTPUT, $CFG, $USER;
    $content = '';
    $now = time();
    $usingrtl = right_to_left();
    $numactivities = count($activities);
    $dateformat = get_string('strftimedate', 'langconfig');
    $alternatelinks = block_completion_progress_modules_with_alternate_links();

    // Get relevant block instance settings or use defaults.
    if (get_config('block_completion_progress', 'forceiconsinbar') !== "1") {
        $useicons = isset($config->progressBarIcons) ? $config->progressBarIcons : DEFAULT_COMPLETIONPROGRESS_PROGRESSBARICONS;
    } else {
        $useicons = true;
    }
    $orderby = isset($config->orderby) ? $config->orderby : DEFAULT_COMPLETIONPROGRESS_ORDERBY;
    $defaultlongbars = get_config('block_completion_progress', 'defaultlongbars') ?: DEFAULT_COMPLETIONPROGRESS_LONGBARS;
    $longbars = isset($config->longbars) ? $config->longbars : $defaultlongbars;
    $displaynow = $orderby == 'orderbytime';
    $showpercentage = isset($config->showpercentage) ? $config->showpercentage : DEFAULT_COMPLETIONPROGRESS_SHOWPERCENTAGE;
    $rowoptions = array('style' => '');
    $cellsoptions = array('style' => '');
    $barclasses = array('barRow');
    $content .= html_writer::start_div('barContainer', ['data-instanceid' => $instance]);

    // Determine the segment width.
    $wrapafter = get_config('block_completion_progress', 'wrapafter') ?: DEFAULT_COMPLETIONPROGRESS_WRAPAFTER;
    if ($wrapafter <= 1) {
        $wrapafter = 1;
    }
    if ($longbars == 'wrap' && $numactivities <= $wrapafter) {
        $longbars = 'squeeze';
    }
    if ($longbars == 'wrap') {
        $rows = ceil($numactivities / $wrapafter);
        if ($rows <= 1) {
            $rows = 1;
        }
        $cellsoptions['style'] = 'flex-basis: calc(100% / ' . ceil($numactivities / $rows) . ');';
        $displaynow = false;
    }
    if ($longbars == 'scroll') {
        $leftpoly = HTML_WRITER::tag('polygon', '', array('points' => '30,0 0,15 30,30', 'class' => 'triangle-polygon'));
        $rightpoly = HTML_WRITER::tag('polygon', '', array('points' => '0,0 30,15 0,30', 'class' => 'triangle-polygon'));
        $content .= HTML_WRITER::tag('svg', $leftpoly, array('class' => 'left-arrow-svg', 'height' => '30', 'width' => '30'));
        $content .= HTML_WRITER::tag('svg', $rightpoly, array('class' => 'right-arrow-svg', 'height' => '30', 'width' => '30'));
    }
    $barclasses[] = 'barMode' . ucfirst($longbars);
    if ($useicons) {
        $barclasses[] = 'barWithIcons';
    }

    // Determine where to put the NOW indicator.
    $nowpos = -1;
    if ($orderby == 'orderbytime' && $longbars != 'wrap' && $displaynow == 1 && !$simple) {
        $barclasses[] = 'barWithNow';

        // Find where to put now arrow.
        $nowpos = 0;
        while ($nowpos < $numactivities && $now > $activities[$nowpos]['expected'] && $activities[$nowpos]['expected'] != 0) {
            $nowpos++;
        }
        $nowstring = get_string('now_indicator', 'block_completion_progress');
        $leftarrowimg = $OUTPUT->pix_icon('left', $nowstring, 'block_completion_progress', array('class' => 'nowicon'));
        $rightarrowimg = $OUTPUT->pix_icon('right', $nowstring, 'block_completion_progress', array('class' => 'nowicon'));
    }

    // Determine links to activities.
    for ($i = 0; $i < $numactivities; $i++) {
        if ($userid != $USER->id &&
            array_key_exists($activities[$i]['type'], $alternatelinks) &&
            has_capability($alternatelinks[$activities[$i]['type']]['capability'], $activities[$i]['context'])
        ) {
            $substitutions = array(
                '/:courseid/' => $courseid,
                '/:eventid/'  => $activities[$i]['instance'],
                '/:cmid/'     => $activities[$i]['id'],
                '/:userid/'   => $userid,
            );
            $link = $alternatelinks[$activities[$i]['type']]['url'];
            $link = preg_replace(array_keys($substitutions), array_values($substitutions), $link);
            $activities[$i]['link'] = $CFG->wwwroot.$link;
        } else {
            $activities[$i]['link'] = $activities[$i]['url'];
        }
    }

    // Start progress bar.
    $content .= html_writer::start_div(implode(' ', $barclasses), $rowoptions);
    $content .= html_writer::start_div('barRowCells', $cellsoptions);
    $counter = 1;
    foreach ($activities as $activity) {
        $complete = $completions[$activity['id']];

        // A cell in the progress bar.
        $cellcontent = '';
        $celloptions = array(
            'class' => 'progressBarCell',
            'data-info-ref' => 'progressBarInfo'.$instance.'-'.$userid.'-'.$activity['id'],
        );
        if ($complete === 'submitted') {
            $celloptions['class'] .= ' submittedNotComplete';

        } else if ($complete == COMPLETION_COMPLETE || $complete == COMPLETION_COMPLETE_PASS) {
            $celloptions['class'] .= ' completed';

        } else if (
            $complete == COMPLETION_COMPLETE_FAIL ||
            (!isset($config->orderby) || $config->orderby == 'orderbytime') &&
            (isset($activity['expected']) && $activity['expected'] > 0 && $activity['expected'] < $now)
        ) {
            $celloptions['class'] .= ' notCompleted';

        } else {
            $celloptions['class'] .= ' futureNotCompleted';
        }
        if (empty($activity['link'])) {
            $celloptions['data-haslink'] = 'false';
        } else if (!empty($activity['available']) || $simple) {
            $celloptions['data-haslink'] = 'true';
        } else if (!empty($activity['link'])) {
            $celloptions['data-haslink'] = 'not-allowed';
        }

        // Place the NOW indicator.
        if ($nowpos >= 0) {
            if ($nowpos == 0 && $counter == 1) {
                $nowcontent = $usingrtl ? $rightarrowimg.$nowstring : $leftarrowimg.$nowstring;
                $cellcontent .= HTML_WRITER::div($nowcontent, 'nowDiv firstNow');
            } else if ($nowpos == $counter) {
                if ($nowpos < $numactivities / 2) {
                    $nowcontent = $usingrtl ? $rightarrowimg.$nowstring : $leftarrowimg.$nowstring;
                    $cellcontent .= HTML_WRITER::div($nowcontent, 'nowDiv firstHalfNow');
                } else {
                    $nowcontent = $usingrtl ? $nowstring.$leftarrowimg : $nowstring.$rightarrowimg;
                    $cellcontent .= HTML_WRITER::div($nowcontent, 'nowDiv lastHalfNow');
                }
            }
        }

        $counter++;
        $content .= HTML_WRITER::div($cellcontent, null, $celloptions);
    }
    $content .= HTML_WRITER::end_div();
    $content .= HTML_WRITER::end_div();
    $content .= HTML_WRITER::end_div();

    // Add the percentage below the progress bar.
    if ($showpercentage == 1 && !$simple) {
        $progress = block_completion_progress_percentage($activities, $completions);
        $percentagecontent = get_string('progress', 'block_completion_progress').': '.$progress.'%';
        $percentageoptions = array('class' => 'progressPercentage');
        $content .= HTML_WRITER::tag('div', $percentagecontent, $percentageoptions);
    }

    // Add the info box below the table.
    $divoptions = array('class' => 'progressEventInfo',
                        'id' => 'progressBarInfo'.$instance.'-'.$userid.'-info');
    $content .= HTML_WRITER::start_tag('div', $divoptions);
    if (!$simple) {
        $content .= get_string('mouse_over_prompt', 'block_completion_progress');
        $content .= ' ';
        $attributes = array (
            'class' => 'accesshide progressShowAllInfo',
        );
        $content .= HTML_WRITER::link('#', get_string('showallinfo', 'block_completion_progress'), $attributes);
    }
    $content .= HTML_WRITER::end_tag('div');

    // Add hidden divs for activity information.
    $stringincomplete = get_string('completion-n', 'completion');
    $stringcomplete = get_string('completed', 'completion');
    $stringpassed = get_string('completion-pass', 'completion');
    $stringfailed = get_string('completion-fail', 'completion');
    $stringsubmitted = get_string('submitted', 'block_completion_progress');
    foreach ($activities as $activity) {
        $completed = $completions[$activity['id']];
        $divoptions = array('class' => 'progressEventInfo',
                            'id' => 'progressBarInfo'.$instance.'-'.$userid.'-'.$activity['id'],
                            'style' => 'display: none;');
        $content .= HTML_WRITER::start_tag('div', $divoptions);

        $text = '';
        $text .= html_writer::empty_tag('img',
                array('src' => $activity['icon'], 'class' => 'moduleIcon', 'alt' => '', 'role' => 'presentation'));
        $text .= s(format_string($activity['name']));
        if (!empty($activity['link']) && (!empty($activity['available']) || $simple)) {
            $content .= $OUTPUT->action_link($activity['link'], $text, null, ['class' => 'action_link']);
        } else {
            $content .= $text;
        }
        $content .= HTML_WRITER::empty_tag('br');
        $altattribute = '';
        if ($completed == COMPLETION_COMPLETE) {
            $content .= $stringcomplete.'&nbsp;';
            $icon = 'tick';
            $altattribute = $stringcomplete;
        } else if ($completed == COMPLETION_COMPLETE_PASS) {
            $content .= $stringpassed.'&nbsp;';
            $icon = 'tick';
            $altattribute = $stringpassed;
        } else if ($completed == COMPLETION_COMPLETE_FAIL) {
            $content .= $stringfailed.'&nbsp;';
            $icon = 'cross';
            $altattribute = $stringfailed;
        } else {
            $content .= $stringincomplete .'&nbsp;';
            $icon = 'cross';
            $altattribute = $stringincomplete;
            if ($completed === 'submitted') {
                $content .= '(' . $stringsubmitted . ')&nbsp;';
                $altattribute .= '(' . $stringsubmitted . ')';
            }
        }
        $content .= $OUTPUT->pix_icon($icon, $altattribute, 'block_completion_progress', array('class' => 'iconInInfo'));
        $content .= HTML_WRITER::empty_tag('br');
        if ($activity['expected'] != 0) {
            $content .= HTML_WRITER::start_tag('div', array('class' => 'expectedBy'));
            $content .= get_string('time_expected', 'block_completion_progress').': ';
            $content .= userdate($activity['expected'], $dateformat, $CFG->timezone);
            $content .= HTML_WRITER::end_tag('div');
        }
        $content .= HTML_WRITER::end_tag('div');
    }

    return $content;
}

/**
 * Calculates an overall percentage of progress
 *
 * @param array $activities   The possible events that can occur for modules
 * @param array $completions The user's attempts on course activities
 * @return int  Progress value as a percentage
 */
function block_completion_progress_percentage($activities, $completions) {
    $completecount = 0;

    foreach ($activities as $activity) {
        if (
            $completions[$activity['id']] == COMPLETION_COMPLETE ||
            $completions[$activity['id']] == COMPLETION_COMPLETE_PASS
        ) {
            $completecount++;
        }
    }

    $progressvalue = $completecount == 0 ? 0 : $completecount / count($activities);

    return (int)round($progressvalue * 100);
}

/**
 * Checks whether the current page is the My home page.
 *
 * @return bool True when on the My home page.
 */
function block_completion_progress_on_site_page() {
    global $SCRIPT, $COURSE;

    return $SCRIPT === '/my/index.php' || $COURSE->id == 1;
}

/**
 * Finds gradebook exclusions for students in a course
 *
 * @param int $courseid The ID of the course containing grade items
 * @param int $userid   The ID of the user whos grade items are being retrieved
 * @return array of exclusions as activity-user pairs
 */
function block_completion_progress_exclusions ($courseid, $userid = null) {
    global $DB;

    $query = "SELECT g.id, ". $DB->sql_concat('i.itemmodule', "'-'", 'i.iteminstance', "'-'", 'g.userid') ." as exclusion
               FROM {grade_grades} g, {grade_items} i
              WHERE i.courseid = :courseid
                AND i.id = g.itemid
                AND g.excluded <> 0";

    $params = array('courseid' => $courseid);
    if (!is_null($userid)) {
        $query .= " AND g.userid = :userid";
        $params['userid'] = $userid;
    }
    $results = $DB->get_records_sql($query, $params);
    $exclusions = array();
    foreach ($results as $value) {
        $exclusions[] = $value->exclusion;
    }
    return $exclusions;
}

/**
 * Determines whether a user is a member of a given group or grouping
 *
 * @param string $group    The group or grouping identifier starting with 'group-' or 'grouping-'
 * @param int    $courseid The ID of the course containing the block instance
 * @param int    $userid   The ID of the user
 * @return boolean value indicating membership
 */
function block_completion_progress_group_membership ($group, $courseid, $userid) {
    if ($group === '0') {
        return true;
    } else if ((substr($group, 0, 6) == 'group-') && ($groupid = intval(substr($group, 6)))) {
        return groups_is_member($groupid, $userid);
    } else if ((substr($group, 0, 9) == 'grouping-') && ($groupingid = intval(substr($group, 9)))) {
        return array_key_exists($groupingid, groups_get_user_groups($courseid, $userid));
    }

    return false;
}
