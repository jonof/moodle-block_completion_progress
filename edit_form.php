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

use block_completion_progress\completion_progress;
use block_completion_progress\defaults;

require_once($CFG->dirroot.'/blocks/completion_progress/block_completion_progress.php');

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
        $progress = (new completion_progress($COURSE))->for_block_instance($this->block->instance, false);
        $activities = $progress->get_activities(completion_progress::ORDERBY_COURSE);

        // The My home version is not configurable.
        if (block_completion_progress::on_site_page()) {
            return;
        }

        // Start block specific section in config form.
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        // Control order of items in Progress Bar.
        $expectedbystring = get_string('completionexpected', 'completion');
        $options = array(
            completion_progress::ORDERBY_TIME   => get_string('config_orderby_due_time',
                'block_completion_progress', $expectedbystring),
            completion_progress::ORDERBY_COURSE => get_string('config_orderby_course_order',
                'block_completion_progress'),
        );
        $label = get_string('config_orderby', 'block_completion_progress');
        $mform->addElement('select', 'config_orderby', $label, $options);
        $mform->setDefault('config_orderby', defaults::ORDERBY);
        $mform->addHelpButton('config_orderby', 'how_ordering_works', 'block_completion_progress');

        // Check if all elements have an expect completion by time set.
        $allwithexpected = true;
        foreach ($activities as $activity) {
            if ($activity->expected == 0) {
                $allwithexpected = false;
                break;
            }
        }
        if (!$allwithexpected) {
            $warningstring = get_string('not_all_expected_set', 'block_completion_progress', $expectedbystring);
            $expectedwarning = html_writer::tag('div', $warningstring, array('class' => 'warning'));
            $mform->addElement('static', $expectedwarning, '', $expectedwarning);
        }

        // Control how long bars wrap/scroll.
        $options = array(
            'squeeze' => get_string('config_squeeze', 'block_completion_progress'),
            'scroll' => get_string('config_scroll', 'block_completion_progress'),
            'wrap' => get_string('config_wrap', 'block_completion_progress'),
        );
        $label = get_string('config_longbars', 'block_completion_progress');
        $mform->addElement('select', 'config_longbars', $label, $options);
        $defaultlongbars = get_config('block_completion_progress', 'defaultlongbars') ?: defaults::LONGBARS;
        $mform->setDefault('config_longbars', $defaultlongbars);
        $mform->addHelpButton('config_longbars', 'how_longbars_works', 'block_completion_progress');

        // Allow icons to be turned on/off on the block.
        if (get_config('block_completion_progress', 'forceiconsinbar') !== "1") {
            $mform->addElement('selectyesno', 'config_progressBarIcons',
                               get_string('config_icons', 'block_completion_progress').' '.
                               $OUTPUT->pix_icon('tick', '', 'block_completion_progress', array('class' => 'iconOnConfig')).
                               $OUTPUT->pix_icon('cross', '', 'block_completion_progress', array('class' => 'iconOnConfig')));
            $mform->setDefault('config_progressBarIcons', defaults::PROGRESSBARICONS);
            $mform->addHelpButton('config_progressBarIcons', 'why_use_icons', 'block_completion_progress');
        }

        // Allow progress percentage to be turned on for students.
        $mform->addElement('selectyesno', 'config_showpercentage',
                           get_string('config_percentage', 'block_completion_progress'));
        $mform->setDefault('config_showpercentage', defaults::SHOWPERCENTAGE);
        $mform->addHelpButton('config_showpercentage', 'why_show_precentage', 'block_completion_progress');

        // Allow the block to be visible to a single group or grouping.
        $groups = groups_get_all_groups($COURSE->id);
        $groupings = groups_get_all_groupings($COURSE->id);
        if (!empty($groups) || !empty($groupings)) {
            $options = array();
            $options['0'] = get_string('allparticipants');
            foreach ($groups as $group) {
                $options['group-' . $group->id] = format_string($group->name);
            }
            foreach ($groupings as $grouping) {
                $options['grouping-' . $grouping->id] = format_string($grouping->name);
            }
            $label = get_string('config_group', 'block_completion_progress');
            $mform->addElement('select', 'config_group', $label, $options);
            $mform->setDefault('config_group', '0');
            $mform->addHelpButton('config_group', 'how_group_works', 'block_completion_progress');
            $mform->setAdvanced('config_group', true);
        }

        // Set block instance title.
        $mform->addElement('text', 'config_progressTitle',
                           get_string('config_title', 'block_completion_progress'));
        $mform->setDefault('config_progressTitle', '');
        $mform->setType('config_progressTitle', PARAM_TEXT);
        $mform->addHelpButton('config_progressTitle', 'why_set_the_title', 'block_completion_progress');
        $mform->setAdvanced('config_progressTitle', true);

        // Control which activities are included in the bar.
        $options = array(
            'activitycompletion' => get_string('config_activitycompletion', 'block_completion_progress'),
            'selectedactivities' => get_string('config_selectedactivities', 'block_completion_progress'),
        );
        $label = get_string('config_activitiesincluded', 'block_completion_progress');
        $mform->addElement('select', 'config_activitiesincluded', $label, $options);
        $mform->setDefault('config_activitiesincluded', defaults::ACTIVITIESINCLUDED);
        $mform->addHelpButton('config_activitiesincluded', 'how_activitiesincluded_works', 'block_completion_progress');
        $mform->setAdvanced('config_activitiesincluded', true);

        // Check that there are activities to monitor.
        if (empty($activities)) {
            $warningstring = get_string('no_activities_config_message', 'block_completion_progress');
            $activitieswarning = html_writer::tag('div', $warningstring, array('class' => 'warning'));
            $mform->addElement('static', '', '', $activitieswarning);
        } else {
            $options = array();
            foreach ($activities as $activity) {
                $options[$activity->type.'-'.$activity->instance] = format_string($activity->name);
            }
            $label = get_string('config_selectactivities', 'block_completion_progress');
            $mform->addElement('select', 'config_selectactivities', $label, $options);
            $mform->getElement('config_selectactivities')->setMultiple(true);
            $mform->getElement('config_selectactivities')->setSize(count($activities));
            $mform->setAdvanced('config_selectactivities', true);
            $mform->disabledif('config_selectactivities', 'config_activitiesincluded', 'neq', 'selectedactivities');
            $mform->addHelpButton('config_selectactivities', 'how_selectactivities_works', 'block_completion_progress');
        }
    }
}
