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
 * Completion Progress block overview table.
 *
 * @package    block_completion_progress
 * @copyright  2016 Michael de Raadt
 * @copyright  2021 Jonathon Fowler <fowlerj@usq.edu.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_completion_progress\table;

defined('MOODLE_INTERNAL') || die;

use block_completion_progress\completion_progress;

require_once($CFG->libdir.'/tablelib.php');


/**
 * Completion Progress block overview table.
 *
 * @package    block_completion_progress
 * @copyright  2016 Michael de Raadt
 * @copyright  2021 Jonathon Fowler <fowlerj@usq.edu.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class overview extends \table_sql {
    /**
     * Course progress.
     * @var completion_progress
     */
    private $progress;

    /**
     * Renderer.
     * @var block_completion_progress\output\renderer
     */
    private $output;

    /**
     * Preloaded language strings.
     * @var array
     */
    private $strs = [];

    /**
     * Construct the overview table.
     * @param completion_progress $progress
     * @param array $groups group ids
     * @param integer|null $roleid
     * @param boolean $bulkoperations
     */
    public function __construct(completion_progress $progress, $groups, $roleid, $bulkoperations) {
        global $PAGE;

        $this->progress = $progress;
        $this->output = $PAGE->get_renderer('block_completion_progress');
        $this->strs['strftimedaydatetime'] = get_string('strftimedaydatetime', 'langconfig');
        $this->strs['indeterminate'] = get_string('indeterminate', 'block_completion_progress');
        $this->strs['never'] = get_string('never');

        parent::__construct('block-completion_progress-overview');

        $tablecolumns = [];
        $tableheaders = [];

        if ($bulkoperations) {
            $checkbox = new \core\output\checkbox_toggleall('participants-table', true, [
                'id' => 'select-all-participants',
                'name' => 'select-all-participants',
                'label' => get_string('selectall'),
                'labelclasses' => 'sr-only',
                'checked' => false,
            ]);
            $tablecolumns[] = 'select';
            $tableheaders[] = $this->output->render($checkbox);
        }

        $tablecolumns[] = 'fullname';
        $tableheaders[] = get_string('fullname');

        if (get_config('block_completion_progress', 'showlastincourse') != 0) {
            $tablecolumns[] = 'timeaccess';
            $tableheaders[] = get_string('lastonline', 'block_completion_progress');
        }

        $tablecolumns[] = 'progressbar';
        $tableheaders[] = get_string('progressbar', 'block_completion_progress');
        $tablecolumns[] = 'progress';
        $tableheaders[] = get_string('progress', 'block_completion_progress');

        $this->define_columns($tablecolumns);
        $this->define_headers($tableheaders);
        $this->sortable(true, 'firstname');
        $this->no_sorting('progressbar');

        if ($bulkoperations) {
            $this->column_class('select', 'col-select');
            $this->no_sorting('select');
        }

        $this->set_attribute('class', 'overviewTable');
        $this->column_class('fullname', 'col-fullname');
        $this->column_class('timeaccess', 'col-timeaccess');
        $this->column_class('progressbar', 'col-progressbar');
        $this->column_class('progress', 'col-progress');

        if (class_exists('\core_user\fields')) {
            $picturefields = \core_user\fields::for_userpic()->get_sql('u', false, '', '', false)->selects;
        } else {
            // 3.10 and older.
            $picturefields = \user_picture::fields('u');
        }

        $enroljoin = get_enrolled_with_capabilities_join($this->progress->get_context(), '', '', $groups,
            get_config('block_completion_progress', 'showinactive') == 0);

        $params = $enroljoin->params + ['courseid' => $this->progress->get_course()->id];
        if ($roleid) {
            $rolejoin = "INNER JOIN {role_assignments} ra ON ra.contextid = :contextid AND ra.userid = u.id";
            $rolewhere = "AND ra.roleid = :roleid";
            $params['contextid'] = $this->progress->get_context()->id;
            $params['roleid'] = $roleid;
        } else {
            $rolejoin = $rolewhere = '';
        }

        $this->set_sql(
            "DISTINCT $picturefields, l.timeaccess",
            "{user} u {$enroljoin->joins} {$rolejoin} LEFT JOIN {user_lastaccess} l ON l.userid = u.id AND l.courseid = :courseid",
            "{$enroljoin->wheres} {$rolewhere}",
            $params
        );
    }

    /**
     * If downloading the table data, remove the select and progress bar columns.
     */
    public function setup() {
        if ($this->is_downloading()) {
            unset($this->headers[$this->columns['select']], $this->columns['select']);
            unset($this->headers[$this->columns['progressbar']], $this->columns['progressbar']);
        }
        parent::setup();
    }

    /**
     * Fudge the SQL sort parameters to exclude the runtime computed progress percentage.
     * @return string SQL fragment
     */
    public function get_sql_sort() {
        $sortcols = $this->get_sort_columns();
        unset($sortcols['progress']);
        return self::construct_order_by($sortcols);
    }

    /**
     * Assemble the dataset.
     * @param integer $pagesize
     * @param boolean $useinitialsbar
     */
    public function query_db($pagesize, $useinitialsbar=true) {
        global $DB;

        $sortcols = $this->get_sort_columns();
        if (array_keys($sortcols)[0] === 'progress') {
            // Kludge to sort by the runtime-computed percentage column.
            if ($useinitialsbar && !$this->is_downloading()) {
                $this->initialbars(true);
            }
            list($wsql, $wparams) = $this->get_sql_where();
            if ($wsql) {
                $this->sql->where .= ' AND '.$wsql;
                $this->sql->params = array_merge($this->sql->params, $wparams);
            }
            if (($sort = $this->get_sql_sort())) {
                $sort = "ORDER BY $sort";
            }
            $sql = "SELECT {$this->sql->fields}
                    FROM {$this->sql->from}
                    WHERE {$this->sql->where}
                    {$sort}";
            $rawdata = $DB->get_recordset_sql($sql, $this->sql->params);

            // Compute the percentage for each record and sort.
            $data = [];
            $percents = [];
            foreach ($rawdata as $key => $row) {
                $this->progress->for_user($row);
                $percents[$key] = $this->progress->get_percentage() ?? -1;
                $data[$key] = $row;
            }
            $sortfunc = $sortcols['progress'] === SORT_ASC ? 'asort' : 'arsort';
            $sortfunc($percents);
            $rawdata->close();

            if (!$this->is_downloading()) {
                $pagestart = $this->currpage * $pagesize;
                $percents = array_slice($percents, $pagestart, $pagesize, true);
                $this->pagesize($pagesize, count($data));
            }

            $this->rawdata = [];
            foreach (array_keys($percents) as $key) {
                $this->rawdata[] = $data[$key];
            }
            return;
        }

        parent::query_db($pagesize, $useinitialsbar);
    }

    /**
     * Decorate the row object with user-specific progress for col_*() to use.
     * @param object $row
     * @return object
     */
    public function format_row($row) {
        $this->progress->for_user($row);
        return parent::format_row($row);
    }

    /**
     * Form a select checkbox for the row.
     * @param object $row
     * @return string HTML
     */
    public function col_select($row) {
        $checkbox = new \core\output\checkbox_toggleall('participants-table', false, [
            'classes' => 'usercheckbox',
            'id' => 'user' . $row->id,
            'name' => 'user' . $row->id,
            'checked' => false,
            'label' => get_string('selectitem', 'block_completion_progress',
                fullname($row, has_capability('moodle/site:viewfullnames', $this->progress->get_context()))),
            'labelclasses' => 'accesshide',
        ]);
        return $this->output->render($checkbox);
    }

    /**
     * Adorn the user full name with a user picture.
     * @param object $row
     * @return string HTML
     */
    public function col_fullname($row) {
        if (!$this->is_downloading()) {
            return $this->output->user_picture($row, [
                'courseid' => $this->progress->get_course()->id,
                'includefullname' => true,
            ]);
        } else {
            return parent::col_fullname($row);
        }
    }

    /**
     * Format the time last accessed value.
     * @param object $row
     * @return string HTML
     */
    public function col_timeaccess($row) {
        if ($row->timeaccess == 0) {
            return $this->strs['never'];
        }
        return userdate($row->timeaccess, $this->strs['strftimedaydatetime']);
    }

    /**
     * Produce a progress bar.
     * @param object $row
     * @return string HTML
     */
    public function col_progressbar($row) {
        return $this->output->render($this->progress);
    }

    /**
     * Format the percentage progress column.
     * @param object $row
     * @return string HTML
     */
    public function col_progress($row) {
        $pct = $this->progress->get_percentage();
        if ($pct === null) {
            return $this->strs['indeterminate'];
        }
        return get_string('percents', '', $pct);
    }
}
