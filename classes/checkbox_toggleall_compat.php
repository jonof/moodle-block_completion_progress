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
 * Compatibility for toggle-all checkbox groups in Moodle 3.7 or below.
 *
 * Moodle 3.8 provides \core\output\checkbox_toggleall, so this implements
 * just enough of its interface to fill in the gaps for older Moodle editions.
 *
 * @package    block_completion_progress
 * @copyright  2020 Jonathon Fowler <fowlerj@usq.edu.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_completion_progress;

defined('MOODLE_INTERNAL') || die;

/**
 * A checkbox in a toggled group.
 *
 * @package block_completion_progress
 * @copyright  2020 Jonathon Fowler <fowlerj@usq.edu.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class checkbox_toggleall_compat implements \renderable {
    /**
     * The name of the checkbox grouping.
     * @var string
     */
    public $togglegroup;

    /**
     * Whether the checkbox has subordinates (true), or is subordinate (false).
     * @var string
     */
    public $ismaster;

    /**
     * Additional options.
     * @var array
     */
    public $options;

    /**
     * Class constructor.
     *
     * @param string $togglegroup the checkbox grouping name.
     * @param boolean $ismaster whether the checkbox has subordinates or not.
     * @param array $options additional options.
     */
    public function __construct($togglegroup, $ismaster, $options = []) {
        $this->togglegroup = $togglegroup;
        $this->ismaster = $ismaster;
        $this->options = $options;
    }
}
