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
 * Completion Progress block renderer.
 *
 * @package    block_completion_progress
 * @copyright  2016 Michael de Raadt
 * @copyright  2020 Jonathon Fowler <fowlerj@usq.edu.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_completion_progress\output;

use block_completion_progress\completion_progress;
use block_completion_progress\defaults;
use plugin_renderer_base;
use html_writer;

/**
 * Completion Progress block renderer.
 *
 * @package    block_completion_progress
 * @copyright  2020 Jonathon Fowler <fowlerj@usq.edu.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {
    /**
     * Generate a progress bar.
     * @param completion_progress $progress
     * @return string HTML
     */
    public function render_completion_progress(completion_progress $progress) {
        global $CFG, $USER;

        $activities = $progress->get_visible_activities();
        $completions = $progress->get_completions();
        $config = $progress->get_block_config();
        $userid = $progress->get_user()->id;
        $courseid = $progress->get_course()->id;
        $instance = $progress->get_block_instance()->id;
        $simple = $progress->is_simple_bar();

        $content = '';
        $now = time();
        $usingrtl = right_to_left();
        $numactivities = count($activities);

        if ($simple && $numactivities == 0) {
            return get_string('no_visible_activities_message', 'block_completion_progress');
        }

        $alternatelinks = [
            'assign' => [
                'url' => '/mod/assign/view.php?id=:cmid&action=grade&userid=:userid',
                'capability' => 'mod/assign:grade',
            ],
            'feedback' => [
                // Breaks if anonymous feedback is collected.
                'url' => '/mod/feedback/show_entries.php?id=:cmid&do_show=showoneentry&userid=:userid',
                'capability' => 'mod/feedback:viewreports',
            ],
            'lesson' => [
                'url' => '/mod/lesson/report.php?id=:cmid&action=reportdetail&userid=:userid',
                'capability' => 'mod/lesson:viewreports',
            ],
            'quiz' => [
                'url' => '/mod/quiz/report.php?id=:cmid&mode=overview',
                'capability' => 'mod/quiz:viewreports',
            ],
        ];

        // Get relevant block instance settings or use defaults.
        if (get_config('block_completion_progress', 'forceiconsinbar') == 0) {
            $useicons = $config->progressBarIcons ?? defaults::PROGRESSBARICONS;
        } else {
            $useicons = true;
        }
        if (($defaultlongbars = get_config('block_completion_progress', 'defaultlongbars')) === false) {
            $defaultlongbars = defaults::LONGBARS;
        }
        $orderby = $config->orderby ?? defaults::ORDERBY;
        $longbars = $config->longbars ?? $defaultlongbars;
        $displaynow = $orderby == completion_progress::ORDERBY_TIME;
        $showpercentage = $config->showpercentage ?? defaults::SHOWPERCENTAGE;

        $rowoptions = ['style' => ''];
        $cellsoptions = ['style' => ''];
        $barclasses = ['barRow'];

        $content .= html_writer::start_div('barContainer', ['data-instanceid' => $instance]);

        // Determine the segment width.
        $wrapafter = get_config('block_completion_progress', 'wrapafter') ?: defaults::WRAPAFTER;
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
            $leftpoly = html_writer::tag('polygon', '', ['points' => '30,0 0,15 30,30', 'class' => 'triangle-polygon']);
            $rightpoly = html_writer::tag('polygon', '', ['points' => '0,0 30,15 0,30', 'class' => 'triangle-polygon']);
            $content .= html_writer::tag('svg', $leftpoly, ['class' => 'left-arrow-svg', 'height' => '30', 'width' => '30']);
            $content .= html_writer::tag('svg', $rightpoly, ['class' => 'right-arrow-svg', 'height' => '30', 'width' => '30']);
        }
        $barclasses[] = 'barMode' . ucfirst($longbars);
        if ($useicons) {
            $barclasses[] = 'barWithIcons';
        }

        // Determine where to put the NOW indicator.
        $nowpos = -1;
        if ($orderby == 'orderbytime' && $longbars != 'wrap' && $displaynow && !$simple) {
            $barclasses[] = 'barWithNow';

            // Find where to put now arrow.
            $nowpos = 0;
            while ($nowpos < $numactivities && $now > $activities[$nowpos]->expected && $activities[$nowpos]->expected != 0) {
                $nowpos++;
            }
            $nowstring = get_string('now_indicator', 'block_completion_progress');
            $leftarrowimg = $this->pix_icon('left', $nowstring, 'block_completion_progress', ['class' => 'nowicon']);
            $rightarrowimg = $this->pix_icon('right', $nowstring, 'block_completion_progress', ['class' => 'nowicon']);
        }

        // Determine links to activities.
        for ($i = 0; $i < $numactivities; $i++) {
            if ($userid != $USER->id &&
                array_key_exists($activities[$i]->type, $alternatelinks) &&
                has_capability($alternatelinks[$activities[$i]->type]['capability'], $activities[$i]->context)
            ) {
                $substitutions = [
                    '/:courseid/' => $courseid,
                    '/:eventid/'  => $activities[$i]->instance,
                    '/:cmid/'     => $activities[$i]->id,
                    '/:userid/'   => $userid,
                ];
                $link = $alternatelinks[$activities[$i]->type]['url'];
                $link = preg_replace(array_keys($substitutions), array_values($substitutions), $link);
                $activities[$i]->link = $CFG->wwwroot.$link;
            } else {
                $activities[$i]->link = $activities[$i]->url;
            }
        }

        // Start progress bar.
        $content .= html_writer::start_div(implode(' ', $barclasses), $rowoptions);
        $content .= html_writer::start_div('barRowCells', $cellsoptions);
        $counter = 1;
        foreach ($activities as $activity) {
            $complete = $completions[$activity->id] ?? null;

            // A cell in the progress bar.
            $cellcontent = '';
            $celloptions = [
                'class' => 'progressBarCell',
                'data-info-ref' => 'progressBarInfo'.$instance.'-'.$userid.'-'.$activity->id,
            ];
            if ($complete === 'submitted') {
                $celloptions['class'] .= ' submittedNotComplete';

            } else if ($complete == COMPLETION_COMPLETE || $complete == COMPLETION_COMPLETE_PASS) {
                $celloptions['class'] .= ' completed';

            } else if (
                $complete == COMPLETION_COMPLETE_FAIL ||
                (!isset($config->orderby) || $config->orderby == 'orderbytime') &&
                (isset($activity->expected) && $activity->expected > 0 && $activity->expected < $now)
            ) {
                $celloptions['class'] .= ' notCompleted';

            } else {
                $celloptions['class'] .= ' futureNotCompleted';
            }
            if (empty($activity->link)) {
                $celloptions['data-haslink'] = 'false';
            } else if (!empty($activity->available) || $simple) {
                $celloptions['data-haslink'] = 'true';
            } else if (!empty($activity->link)) {
                $celloptions['data-haslink'] = 'not-allowed';
            }

            // Place the NOW indicator.
            if ($nowpos >= 0) {
                if ($nowpos == 0 && $counter == 1) {
                    $nowcontent = $usingrtl ? $rightarrowimg.$nowstring : $leftarrowimg.$nowstring;
                    $cellcontent .= html_writer::div($nowcontent, 'nowDiv firstNow');
                } else if ($nowpos == $counter) {
                    if ($nowpos < $numactivities / 2) {
                        $nowcontent = $usingrtl ? $rightarrowimg.$nowstring : $leftarrowimg.$nowstring;
                        $cellcontent .= html_writer::div($nowcontent, 'nowDiv firstHalfNow');
                    } else {
                        $nowcontent = $usingrtl ? $nowstring.$leftarrowimg : $nowstring.$rightarrowimg;
                        $cellcontent .= html_writer::div($nowcontent, 'nowDiv lastHalfNow');
                    }
                }
            }

            $counter++;
            $content .= html_writer::div($cellcontent, null, $celloptions);
        }
        $content .= html_writer::end_div(); // ... barRowCells
        $content .= html_writer::end_div(); // ... $barclasses
        $content .= html_writer::end_div(); // ... barContainer

        // Add the percentage below the progress bar.
        if ($showpercentage && !$simple) {
            $progress = $progress->get_percentage();
            $percentagecontent = get_string('progress', 'block_completion_progress').': '.$progress.'%';
            $percentageoptions = ['class' => 'progressPercentage'];
            $content .= html_writer::tag('div', $percentagecontent, $percentageoptions);
        }

        // Add the info box below the table.
        $divoptions = [
            'class' => 'progressEventInfo',
            'id' => 'progressBarInfo'.$instance.'-'.$userid.'-info',
        ];
        $content .= html_writer::start_tag('div', $divoptions);
        if (!$simple) {
            $content .= get_string('mouse_over_prompt', 'block_completion_progress');
            $content .= ' ';
            $attributes = ['class' => 'accesshide progressShowAllInfo'];
            $content .= html_writer::link('#', get_string('showallinfo', 'block_completion_progress'), $attributes);
        }
        $content .= html_writer::end_tag('div');

        // Add hidden divs for activity information.
        $strincomplete = get_string('completion-n', 'completion');
        $strcomplete = get_string('completed', 'completion');
        $strpassed = get_string('completion-pass', 'completion');
        $strfailed = get_string('completion-fail', 'completion');
        $strsubmitted = get_string('submitted', 'block_completion_progress');
        $strdateformat = get_string('strftimedate', 'langconfig');
        $strtimeexpected = get_string('time_expected', 'block_completion_progress');

        foreach ($activities as $activity) {
            $completed = $completions[$activity->id] ?? null;
            $divoptions = [
                'class' => 'progressEventInfo',
                'id' => 'progressBarInfo'.$instance.'-'.$userid.'-'.$activity->id,
                'style' => 'display: none;',
            ];
            $content .= html_writer::start_tag('div', $divoptions);

            $text = '';
            $text .= html_writer::empty_tag('img',
                    ['src' => $activity->icon, 'class' => 'moduleIcon', 'alt' => '', 'role' => 'presentation']);
            $text .= $activity->name;
            if (!empty($activity->link) && (!empty($activity->available) || $simple)) {
                $attrs = ['class' => 'action_link'];
                if (!empty($activity->onclick)) {
                    $attrs['onclick'] = $activity->onclick;
                }
                $content .= $this->action_link($activity->link, $text, null, $attrs);
            } else {
                $content .= $text;
            }
            $content .= html_writer::empty_tag('br');
            $altattribute = '';
            if ($completed == COMPLETION_COMPLETE) {
                $content .= $strcomplete.'&nbsp;';
                $icon = 'tick';
                $altattribute = $strcomplete;
            } else if ($completed == COMPLETION_COMPLETE_PASS) {
                $content .= $strpassed.'&nbsp;';
                $icon = 'tick';
                $altattribute = $strpassed;
            } else if ($completed == COMPLETION_COMPLETE_FAIL) {
                $content .= $strfailed.'&nbsp;';
                $icon = 'cross';
                $altattribute = $strfailed;
            } else {
                $content .= $strincomplete .'&nbsp;';
                $icon = 'cross';
                $altattribute = $strincomplete;
                if ($completed === 'submitted') {
                    $content .= '(' . $strsubmitted . ')&nbsp;';
                    $altattribute .= '(' . $strsubmitted . ')';
                }
            }
            $content .= $this->pix_icon($icon, $altattribute, 'block_completion_progress', ['class' => 'iconInInfo']);
            $content .= html_writer::empty_tag('br');
            if ($activity->expected != 0) {
                $content .= html_writer::start_tag('div', ['class' => 'expectedBy']);
                $content .= $strtimeexpected.': ';
                $content .= userdate($activity->expected, $strdateformat, $CFG->timezone);
                $content .= html_writer::end_tag('div');
            }
            $content .= html_writer::end_tag('div');
        }

        return $content;
    }
}
