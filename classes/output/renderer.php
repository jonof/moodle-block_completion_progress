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
 * @copyright  2020 Jonathon Fowler <fowlerj@usq.edu.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_completion_progress\output;

defined('MOODLE_INTERNAL') || die;

use plugin_renderer_base;
use html_writer;
use block_completion_progress\checkbox_toggleall_compat;

/**
 * Completion Progress block renderer.
 *
 * @package    block_completion_progress
 * @copyright  2020 Jonathon Fowler <fowlerj@usq.edu.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {
    /**
     * Generate HTML to represent a checkbox in a toggle group for old Moodle releases.
     *
     * @param checkbox_toggleall_compat $renderable
     * @return string
     */
    public function render_checkbox_toggleall_compat(checkbox_toggleall_compat $renderable) {
        $inputattribs = [
            'id' => $renderable->options['id'],
            'name' => $renderable->options['name'],
            'type' => 'checkbox',
            'class' => $renderable->options['classes'] ?? '',
            'data-action' => 'toggle',
            'data-toggle' => $renderable->ismaster ? 'master' : 'slave',
            'data-togglegroup' => $renderable->togglegroup,
        ];
        if (!empty($renderable->options['checked'])) {
            $inputattribs['checked'] = 'checked';
        }
        if ($renderable->ismaster) {
            $inputattribs += [
                'data-toggle-selectall' => get_string('selectall'),
                'data-toggle-deselectall' => get_string('deselectall'),
            ];
        }
        $labelattribs = [
            'for' => $renderable->options['id'],
            'class' => $renderable->options['labelclasses'] ?? '',
        ];
        return html_writer::empty_tag('input', $inputattribs) .
            html_writer::tag('label', $renderable->options['label'], $labelattribs);
    }
}
