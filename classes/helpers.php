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
 * @copyright  2026 Jonathon Fowler <fowlerj@usq.edu.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_completion_progress;

/**
 * Miscellaneous helpers.
 */
abstract class helpers {
    /**
     * Bumps a value to assist in caching of configured colours in css.php.
     */
    public static function increment_cache_value() {
        $value = get_config('block_completion_progress', 'cachevalue') + 1;
        set_config('cachevalue', $value, 'block_completion_progress');
    }

    /**
     * Checks whether the given page is site-level (Dashboard or Front page) or not.
     *
     * @param moodle_page $tpage the page to check, or the current page if not passed.
     * @return boolean True when on the Dashboard or Site home page.
     */
    public static function on_site_page($tpage = null) {
        global $PAGE;

        $tpage = $tpage ?? $PAGE;
        $context = $tpage->context ?? null;

        if (!$tpage || !$context) {
            return false;
        } else if ($context->contextlevel === CONTEXT_SYSTEM && $tpage->requestorigin === 'restore') {
            return false; // When restoring from a backup, pretend the page is course-level.
        } else if ($context->contextlevel === CONTEXT_COURSE && $context->instanceid == SITEID) {
            return true;  // Front page.
        } else if ($context->contextlevel < CONTEXT_COURSE) {
            return true;  // System, user (i.e. dashboard), course category.
        } else {
            return false;
        }
    }
}
