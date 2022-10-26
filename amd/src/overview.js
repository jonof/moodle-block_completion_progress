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
 * Completion Progress overview page behaviour.
 *
 * @module     block_completion_progress/overview
 * @copyright  2020 Jonathon Fowler <fowlerj@usq.edu.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core_user/participants'],
    function(Participants) {
        return /** @alias module:block_completion_progress/overview */ {
            /**
             * Initialise the overview page.
             *
             * @param {object} options initialisation options.
             */
            init: function(options) {
                var form = document.getElementById('participantsform');
                var action = document.getElementById('formactionid');

                /**
                 * Manage the activation of the 'With selected users' control.
                 */
                function checkaction() {
                    action.disabled = (form.querySelector('input.usercheckbox:checked') === null);
                }

                Participants.init(options);

                checkaction();
                form.addEventListener('change', checkaction);
            }
        };
    });
