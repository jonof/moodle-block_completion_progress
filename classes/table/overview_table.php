<?php
namespace block_completion_progress\table;

use DateTime;
use context;
use core_table\dynamic as dynamic_table;
use block_completion_progress\table\overview_table_filterset as filterset;
use core_user\output\status_field;
use block_completion_progress\table\overview_table_search as participants_search;
use moodle_url;

defined('MOODLE_INTERNAL') || die;

global $CFG;

require_once($CFG->libdir . '/tablelib.php');
require_once($CFG->dirroot.'/blocks/completion_progress/lib.php');
require_once($CFG->dirroot . '/user/lib.php');

/**
 * Test table class to be put in test_table.php of root of Moodle installation.
 *  for defining some custom column names and proccessing
 * Username and Password feilds using custom and other column methods.
 */
class overview_table extends \table_sql implements dynamic_table {
  /**
   * @var int $courseid The course id
   */
  protected $courseid;

  /**
   * @var string[] The list of countries.
   */
  protected $countries;

  /**
   * @var \stdClass[] The list of groups with membership info for the course.
   */
  protected $groups;

  /**
   * @var string[] Extra fields to display.
   */
  protected $extrafields;

  /**
   * @var \stdClass $course The course details.
   */
  protected $course;

  /**
   * @var  context $context The course context.
   */
  protected $context;

  /**
   * @var \stdClass[] List of roles indexed by roleid.
   */
  protected $allroles;

  /**
   * @var \stdClass[] List of roles indexed by roleid.
   */
  protected $allroleassignments;

  /**
   * @var \stdClass[] Assignable roles in this course.
   */
  protected $assignableroles;

  /**
   * @var \stdClass[] Profile roles in this course.
   */
  protected $profileroles;

  /**
   * @var filterset Filterset describing which participants to include.
   */
  protected $filterset;

  /**
   * @var \stdClass[] $viewableroles
   */
  private $viewableroles;

  /**
   * @var moodle_url $baseurl The base URL for the report.
   */
  public $baseurl;

  /**
   * @var int $instanceid Used to generate table progressbars.
   */
  private $instanceid;

  /**
   * @var int $roleselected Used to filter users by role.
   */
  private $roleselected;
  /**
   * @var string $group Used to filter users by group or grouping.
   */
  private $group;

  private $activities;
  private $exclusions;
  private $block;
  private $submissions;

  /**
   * Render the participants table.
   *
   * @param int $pagesize Size of page for paginated displayed table.
   * @param bool $useinitialsbar Whether to use the initials bar which will only be used if there is a fullname column defined.
   * @param string $downloadhelpbutton
   */
  public function out($pagesize, $useinitialsbar, $downloadhelpbutton = '') {
      global $PAGE, $OUTPUT;

      // Define the headers and columns.
      $headers = [];
      $columns = [];

      $bulkoperations = has_capability('moodle/course:bulkmessaging', $this->context);
      if ($bulkoperations) {
          $mastercheckbox = new \core\output\checkbox_toggleall('participants-table', true, [
              'id' => 'select-all-participants',
              'name' => 'select-all-participants',
              'label' => get_string('selectall'),
              'labelclasses' => 'sr-only',
              'classes' => 'm-1',
              'checked' => false,
          ]);
          $headers[] = $OUTPUT->render($mastercheckbox);
          $columns[] = 'select';
      }

      $lastincourse = get_config('block_completion_progress', 'showlastincourse');

      // Define the list of columns to show.
      $columns[] = 'fullname';
      if ($lastincourse !== '0') {
        $columns[] = 'lastonlinetime';
      }
      $columns[] = 'progressbar';
      $columns[] = 'progress';
      $this->define_columns($columns);

      // Define the titles of columns to show in header.
      $headers[] = 'Fullname';
      if ($lastincourse !== '0') {
        $headers[] = 'Last in course';
      }
      $headers[] = 'Progress Bar';
      $headers[] = 'Progress';
      $this->define_headers($headers);

      // Get the list of users enrolled in the course.
      if ($CFG->branch < 311) {
          $picturefields = \user_picture::fields('u');
      } else {
          $picturefields = core_user\fields::for_userpic()->get_sql('u', false, '', '', false)->selects;
      }

      // Apply group restrictions.
      $params = array();
      $groupjoin = '';
      if ((substr($this->group, 0, 6) == 'group-') && ($groupid = intval(substr($this->group, 6)))) {
          $groupjoin = 'JOIN {groups_members} g ON (g.groupid = :groupselected AND g.userid = u.id)';
          $params['groupselected'] = $groupid;
      } else if ((substr($this->group, 0, 9) == 'grouping-') && ($groupingid = intval(substr($this->group, 9)))) {
          $groupjoin = 'JOIN {groups_members} g ON '.
                       '(g.groupid IN (SELECT DISTINCT groupid FROM {groupings_groups} WHERE groupingid = :groupingselected) '.
                       'AND g.userid = u.id)';
          $params['groupingselected'] = $groupingid;
      } else if ($groupuserid != 0 && !empty($groupidnums)) {
          $groupjoin = 'JOIN {groups_members} g ON (g.groupid IN ('.implode(',', $groupidnums).') AND g.userid = u.id)';
      }

      // Hide Suspended users
      if (get_config('block_completion_progress', 'showinactive') !== "1") {
        $enrolled = get_enrolled_users($this->context, null, null, 'u.id', null, null, null, true);
        $enrolarray = [];
        foreach ($enrolled as $userenrolled) {
          $enrolarray[] = $userenrolled->id;
        }
        $where = 'u.id IN ('. implode(',',$enrolarray) .')';
      } else {
        $where = '1=1';
      }

      // Limit to a specific role, if selected.
      $rolewhere = $this->roleselected != 0 ? "AND a.roleid = $this->roleselected" : '';

      $fields = 'DISTINCT '.$picturefields.', COALESCE(l.timeaccess, 0) AS lastonlinetime, l.courseid';
      $from = '{user} u
      JOIN {role_assignments} a ON (a.contextid = :contextid AND a.userid = u.id '.$rolewhere.') '
      . $groupjoin .
      ' LEFT JOIN {user_lastaccess} l ON (l.courseid = :courseid AND l.userid = u.id)';
      //$where = '1=1';
      $params['contextid'] = $this->context->id;
      $params['courseid'] = $this->course->id;
      // Set role params if required
      if ($this->roleselected != 0) {
        $params['rolewhere'] = $this->roleselected;
      }
      // Set table SQL
      $this->set_sql($fields, $from, $where, $params);
      // Set table count
      $this->set_count_sql("SELECT COUNT(1) FROM (SELECT $fields FROM $from WHERE $where) usercount WHERE 1=1", $params);

      $this->define_baseurl($PAGE->url);
      $this->sortable(true, $uniqueid);
      $this->set_attribute('class', 'overviewTable');
      $this->column_class('select', 'col-select');
      $this->column_class('fullname', 'col-fullname');
      $this->column_class('lastonline', 'col-lastonline');
      $this->column_class('progressbar', 'col-progressbar');
      $this->column_class('progress', 'col-progress');
      $this->no_sorting('select');
      $this->no_sorting('progressbar');
      $this->no_sorting('progress');

      parent::out($pagesize, $useinitialsbar, $downloadhelpbutton);

      // Organise access to JS for progress bars.
      $PAGE->requires->js_call_amd('block_completion_progress/progressbar', 'init', [
          'instances' => array($this->block->id),
          'uniqueid' => $this->uniqueid,
      ]);

  }
  /**
   * Generate the fullname column.
   *
   * @param \stdClass $data
   * @return string
   */
  public function col_fullname($values) {
    global $OUTPUT;
    return $OUTPUT->user_picture($values, array('size' => 35, 'courseid' => $course->id, 'includefullname' => true, 'link'=>true));
  }

  /**
   * Generate the select column.
   *
   * @param \stdClass $data
   * @return string
   */
  public function col_select($data) {
      global $OUTPUT;

      $checkbox = new \core\output\checkbox_toggleall('participants-table', false, [
          'classes' => 'usercheckbox m-1',
          'id' => 'user' . $data->id,
          'name' => 'user' . $data->id,
          'checked' => false,
          'label' => get_string('selectitem', 'moodle', fullname($data)),
          'labelclasses' => 'accesshide',
      ]);

      return $OUTPUT->render($checkbox);
  }

  /**
   * This function is called for each data row to allow processing of the
   * lastonlinetime value.
   *
   * @param object $values Contains object with all the values of record.
   * @return $string Return last online time.
   */
  function col_lastonlinetime($values) {
    if (empty($values->lastonlinetime)) {
        return $lastonline = get_string('never');
    } else {
        return $lastonline = userdate($values->lastonlinetime);
    }
  }

  /**
   * This function is called for each data row to allow processing of the
   * progressbar value.
   *
   * @param object $values Contains object with all the values of record.
   * @return $string Return user progress bar.
   */
  protected function col_progressbar($values) {
    $useractivities = block_completion_progress_filter_visibility($this->activities, $values->id, $this->courseid, $this->exclusions);
    if (!empty($useractivities)) {
      $submissions = block_completion_progress_submissions($this->courseid, $values->id);
      if ($submissions) {
        $completions = block_completion_progress_completions($useractivities, $values->id, $this->course, $submissions);
      } else {
        $completions = block_completion_progress_completions($useractivities, $values->id, $this->course, '');
      }
      return $progressbar = block_completion_progress_bar($useractivities, $completions, $this->$config, $values->id, $this->courseid,
        $this->instanceid, true);
    } else {
      return $progressbar = get_string('no_visible_activities_message', 'block_completion_progress');
      $progressvalue = 0;
      $progress = '?';
    }
  }

  /**
   * This function is called for each data row to allow processing of the
   * progress value.
   *
   * @param object $values Contains object with all the values of record.
   * @return $string Return progress percentage.
   */
  protected function col_progress($values) {
    $useractivities = block_completion_progress_filter_visibility($this->activities, $values->id, $this->courseid, $this->$exclusions);
    if (!empty($useractivities)) {
      $completions = block_completion_progress_completions($useractivities, $values->id, $this->course, $values->submissions);
      $progressvalue = block_completion_progress_percentage($useractivities, $completions);
      return $progress = $progressvalue.'%';
    } else {
      $progressbar = get_string('no_visible_activities_message', 'block_completion_progress');
      $progressvalue = 0;
      return $progress = '?';
    }
  }

  /**
   * Set filters and build table structure.
   *
   * @param filterset $filterset The filterset object to get the filters from.
   */
  public function set_filterset(filterset $filterset): void {
    global $DB;
    // Get the context.
    $this->courseid = $filterset->get_filter('courseid')->current();
    $this->course = get_course($this->courseid);
    $this->context = \context_course::instance($this->courseid, MUST_EXIST);
    $this->config = $CFG;
    $this->activities = block_completion_progress_get_activities($this->courseid, $this->config);
    $this->exclusions = block_completion_progress_exclusions($this->courseid);
    $this->instanceid = $filterset->get_filter('instanceid')->current();
    $this->block = $DB->get_record('block_instances', array('id' => $this->instanceid), '*', MUST_EXIST);
    $this->roleselected = $filterset->get_filter('roles')->current();
    if ($filterset->has_filter('group')) {
      $this->group = $filterset->get_filter('group')->current();
    }

    // Process the filterset.
    parent::set_filterset($filterset);
  }

  /**
   * Guess the base url for the participants table.
   */
  public function guess_base_url(): void {
    $this->baseurl = new moodle_url('/blocks/completion_progress/overview.php', ['id' => $this->courseid]);
  }

  /**
   * Get the context of the current table.
   *
   * Note: This function should not be called until after the filterset has been provided.
   *
   * @return context
   */
  public function get_context(): context {
      return $this->context;
  }
}
