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
 * Completion Progress block English language translation
 *
 * @package    block_completion_progress
 * @copyright  2016 Michael de Raadt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Stings for the Config page.
$string['config_default_title'] = 'Completion Progress';
$string['config_group'] = 'Visible only to group';
$string['config_header_monitored'] = 'Monitored';
$string['config_icons'] = 'Use icons in bar';
$string['config_longbars'] = 'How to present long bars';
$string['config_percentage'] = 'Show percentage to students';
$string['config_scroll'] = 'Scroll';
$string['config_squeeze'] = 'Squeeze';
$string['config_title'] = 'Alternate title';
$string['config_orderby'] = 'Order bar by';
$string['config_orderby_due_time'] = '"{$a}" date';
$string['config_orderby_course_order'] = 'Ordering in course';
$string['config_wrap'] = 'Wrap';
$string['config_activitycompletion'] = 'All activities with completion set';
$string['config_selectedactivities'] = 'Selected activities';
$string['config_activitiesincluded'] = 'Activities included';
$string['config_selectactivities'] = 'Select activities';

// Help strings.
$string['why_set_the_title'] = 'Why you might want to set the block instance title?';
$string['why_set_the_title_help'] = '
<p>There can be multiple instances of the Completion Progress block. You may use different Completion Progress blocks to monitor different sets of activities or resources. For instance you could track progress in assignments in one block and quizzes in another. For this reason you can override the default title and set a more appropriate block title for each instance.</p>
';
$string['why_use_icons'] = 'Why you might want to use icons?';
$string['why_use_icons_help'] = '
<p>You may wish to add tick and cross icons in the Completion Progress to make this block more visually accessible for students with colour-blindness.</p>
<p>It may also make the meaning of the block clearer if you believe colours are not intuitive, either for cultural or personal reasons.</p>
';
$string['why_show_precentage'] = 'Why show a progress percentage to students?';
$string['why_show_precentage_help'] = '
<p>It is possible to show an overall percentage of progress to students.</p>
<p>This is calculated as the number of items complete divided by the total number of items in the bar.</p>
<p>The progress percentage appears until the student mouses over an item in the bar.</p>
';
$string['how_ordering_works'] = 'How ordering works';
$string['how_ordering_works_help'] = '
<p>There are two ways items in the Completion Progress can be ordered.</p>
<ul>
    <li><em>"Expect completion on" date</em> (default)<br />
    The expected completion dates of activities/resources are used to order the bar. Where activities/resources don\'t have an expected completion date, ordering in the course is used instead.
    </li>
    <li><em>Ordering in course</em><br />
    Activities/resources are presented in the same order as they are on the course page. When this option is used, expected completion dates are ignored.
    </li>
</ul>
';
$string['how_group_works'] = 'How visible group works';
$string['how_group_works_help'] = '
<p>Selecting a group will limit the display of the this block to that group only.</p>
';
$string['how_longbars_works'] = 'How long bars are presented';
$string['how_longbars_works_help'] = '
<p>When bars exceed a set length, how they can be presented in one of the following ways.</p>
<ul>
    <li>Squeezed into one horizontal bar</li>
    <li>Scrolling sideways to show overflowing bar segments</li>
    <li>Wrapping to show all bar segments on multiple lines</li>
</ul>
<p>Note that when the bar is wrapped, the NOW indicator will not be shown.</p>
';
$string['how_activitiesincluded_works'] = 'How including activities works';
$string['how_activitiesincluded_works_help'] = '
<p>By default, all activities with Activity completion settings set are included in the bar.</p>
<p>You can also manually select activities to be included.</p>
';
$string['how_selectactivities_works'] = 'How including activities works';
$string['how_selectactivities_works_help'] = '
<p>To manually select activities to be include in the bar, ensure that "'.$string['config_activitiesincluded'].'" is set to "'.$string['config_selectedactivities'].'".</p>
<p>Only activities with activity completion settings set can be included.</p>
<p>Hold the CTRL key to select multiple items.</p>
';


// Other terms.
$string['completion_not_enabled'] = 'Completion tracking is not enabled.';
$string['mouse_over_prompt'] = 'Mouse over or touch bar for info.';
$string['no_activities_config_message'] = '
There are no activities or resources with activity completion set or no activities or resources have been selected.
Set activity completion on activities and resources then configure this block.
';
$string['no_activities_message'] = 'No activities or resources are being monitored. Use config to set up monitoring.';
$string['no_visible_activities_message'] = 'None of the monitored events are currently visible.';
$string['now_indicator'] = 'NOW';
$string['pluginname'] = 'Completion Progress';
$string['showallinfo'] = 'Show all info';
$string['time_expected'] = 'Expected';
$string['not_all_expected_set'] = 'Not all activities with completion have an "{$a}" date set.';
$string['submitted'] = 'Submitted';

// Global setting strings
// Default colours that may have different cultural meanings.
// Note that these defaults can be overridden by the block's global settings.
$string['attempted_colour'] = '#73A839';
$string['submittednotcomplete_colour'] = '#FFCC00';
$string['notAttempted_colour'] = '#C71C22';
$string['futureNotAttempted_colour'] = '#025187';
$string['attempted_colour_title'] = 'Attempted colour';
$string['attempted_colour_descr'] = 'HTML Colour code for elements that have been attempted';
$string['submittednotcomplete_colour_title'] = 'Submitted but not complete colour';
$string['submittednotcomplete_colour_descr'] = 'HTML colour code for elements that have been submitted, but are not yet complete';
$string['notattempted_colour_title'] = 'Not-attempted colour';
$string['notattempted_colour_descr'] = 'HTML colour code for current elements that have not yet been attempted';
$string['futurenotattempted_colour_title'] = 'Future Not-attempted colour';
$string['futurenotattempted_colour_descr'] = 'HTML colour code for future elements that have not yet been attemted';
$string['coursenametoshow'] = 'Course name to show on Dashboard';
$string['shortname'] = 'Short course name';
$string['fullname'] = 'Full course name';
$string['showinactive'] = 'Show inactive students in Overview';
$string['wrapafter'] = 'When wrapping, limit rows to';
$string['defaultlongbars'] = 'Default presentation for long bars';

// Overview page strings.
$string['lastonline'] = 'Last in course';
$string['overview'] = 'Overview of students';
$string['progress'] = 'Progress';
$string['progressbar'] = 'Completion Progress';

// For capabilities.
$string['completion_progress:overview'] = 'View course overview of Completion Progresss for all students';
$string['completion_progress:addinstance'] = 'Add a new Completion Progress block';
$string['completion_progress:myaddinstance'] = 'Add a Completion Progress block to My home page';
$string['completion_progress:showbar'] = 'Show the bar in the Completion Progress block';

// For My home page.
$string['no_blocks'] = "No Completion Progress blocks are set up for your courses.";
