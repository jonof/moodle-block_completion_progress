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
 * Completion Progress block configuration form definition
 *
 * @package    block_completion_progress
 * @copyright  2016 Michael de Raadt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/blocks/completion_progress/lib.php');

/**
 * Completion Progress block config form class
 *
 * @copyright 2016 Michael de Raadt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_completion_progress_edit_form extends block_edit_form {
    /**
     * Settings specific to this block.
     *
     * @param moodleform $mform
     */
    protected function specific_definition($mform) {
        global $COURSE, $OUTPUT;
        $activities = block_completion_progress_get_activities($COURSE->id, null, 'orderbycourse');
        $numactivies = count($activities);

        // The My home version is not configurable.
        if (block_completion_progress_on_site_page()) {
            return;
        }

        // Start block specific section in config form.
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        // Allow progress percentage to be turned on for students.
        $mform->addElement('selectyesno', 'config_showpercentage',
            get_string('config_percentage', 'block_completion_progress'));
        $mform->setDefault('config_showpercentage', DEFAULT_COMPLETIONPROGRESS_SHOWPERCENTAGE);
        $mform->addHelpButton('config_showpercentage', 'why_show_precentage', 'block_completion_progress');

        $mform->addElement('hidden', 'config_activitiesincluded', $activitieslabel);
        $mform->setDefault('config_activitiesincluded', DEFAULT_COMPLETIONPROGRESS_ACTIVITIESINCLUDED);

        // Check that there are activities to monitor.
        if (empty($activities)) {
            $warningstring = get_string('no_activities_config_message', 'block_completion_progress');
            $activitieswarning = HTML_WRITER::tag('div', $warningstring, array('class' => 'warning'));
            $mform->addElement('static', '', '', $activitieswarning);
        } else {
            $activitiestoinclude = array();
            foreach ($activities as $index => $activity) {
                $activitiestoinclude[$activity['type'].'-'.$activity['instance']] = $activity['name'];
            }

            $mform->addHelpButton('config_selectactivities', 'how_selectactivities_works', 'block_completion_progress');

            $options = array(
                'multiple' => true
            );
            $selectactivitieslabel = get_string('config_selectactivities', 'block_completion_progress');
            $mform->addElement('autocomplete', 'config_selectactivities', $selectactivitieslabel , $activitiestoinclude, $options);


        }
    }
}
