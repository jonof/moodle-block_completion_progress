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
 * Completion Progress compatibility shim for 3.6 and below.
 *
 * Moodle 3.7 provides core/checkbox-toggleall, so this implements just
 * enough functionality to fill in the gaps for older versions.
 *
 * @module     block_completion_progress/checkbox_toggleall_compat
 * @package    block_completion_progress
 * @copyright  2020 Jonathon Fowler <fowlerj@usq.edu.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'],
    function($) {
        var masters = $('input[type=checkbox][data-action=toggle][data-toggle=master]');
        masters.click(function() {
            var master = $(this);
            var subords = $('input[type=checkbox][data-action=toggle][data-toggle=slave]' +
                '[data-togglegroup=' + master.data('togglegroup') + ']');
            subords.prop('checked', master.prop('checked'));
        });
    });
