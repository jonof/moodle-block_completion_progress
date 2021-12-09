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

defined('MOODLE_INTERNAL') || die;

/**
 * Completion Progress defaults.
 *
 * @package    block_completion_progress
 * @copyright  2016 Michael de Raadt
 * @copyright  2021 Jonathon Fowler <fowlerj@usq.edu.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class defaults {
    /**
     * Default number of cells per row in wrap mode.
     */
    const WRAPAFTER = 16;

    /**
     * Default presentation mode for long bars: squeeze, scroll, or wrap.
     */
    const LONGBARS = 'squeeze';

    /**
     * Default course name (long/short) to show on Dashboard pages.
     */
    const COURSENAMETOSHOW = 'shortname';

    /**
     * Default display of inactive students on the overview page.
     */
    const SHOWINACTIVE = 0;

    /**
     * Default display of student 'last in course' time on overview page.
     */
    const SHOWLASTINCOURSE = 1;

    /**
     * Default forcing the display of status icons in bar cells.
     */
    const FORCEICONSINBAR = 0;

    /**
     * Default display of status icons in bar cells.
     */
    const PROGRESSBARICONS = 0;

    /**
     * Default cell sort order mode: orderbytime or orderbycourse.
     */
    const ORDERBY = 'orderbytime';

    /**
     * Default display of progress percentage in block.
     */
    const SHOWPERCENTAGE = 0;

    /**
     * Default choice of activites included: activitycompletion or selectedactivities.
     */
    const ACTIVITIESINCLUDED = 'activitycompletion';
}
