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
 * Compatibility shim for older PHPunit versions.
 *
 * @package    block_completion_progress
 * @copyright  2020 Jonathon Fowler <fowlerj@usq.edu.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_completion_progress\tests;

defined('MOODLE_INTERNAL') || die();

/**
 * Compatibility shim for older PHPunit versions.
 *
 * @package    block_completion_progress
 * @copyright  2020 Jonathon Fowler <fowlerj@usq.edu.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class testcase_phpunit6 extends \advanced_testcase {
// @codingStandardsIgnoreStart
    /**
     * See PHPUnit\Framework\TestCase::setUp().
     */
    protected function setUp() {
        $this->set_up();
    }

    /**
     * Adapts PHPUnit 7's assertStringContainsString for PHPUnit 6.
     * @param string $needle
     * @param string $haystack
     * @param string $message
     */
    function assertStringContainsString(string $needle, string $haystack, string $message = '') {
        return $this->assertContains($needle, $haystack, $message, false);
    }

    /**
     * Adapts PHPUnit 7's assertStringContainsStringIgnoringCase for PHPUnit 6.
     * @param string $needle
     * @param string $haystack
     * @param string $message
     */
    function assertStringContainsStringIgnoringCase(string $needle, string $haystack, string $message = '') {
        return $this->assertContains($needle, $haystack, $message, true);
    }

    /**
     * Adapts PHPUnit 7's assertStringNotContainsString for PHPUnit 6.
     * @param string $needle
     * @param string $haystack
     * @param string $message
     */
    function assertStringNotContainsString(string $needle, string $haystack, string $message = '') {
        return $this->assertNotContains($needle, $haystack, $message, false);
    }

    /**
     * Adapts PHPUnit 7's assertStringNotContainsStringIgnoringCase for PHPUnit 6.
     * @param string $needle
     * @param string $haystack
     * @param string $message
     */
    function assertStringNotContainsStringIgnoringCase(string $needle, string $haystack, string $message = '') {
        return $this->assertNotContains($needle, $haystack, $message, true);
    }
// @codingStandardsIgnoreEnd
}
