<?php
/**
 * Built from a copy of CiviCRM's CRM_Report_Form_Activity.
 */

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2019
 */
use CRM_Cpreports_ExtensionUtil as E;

class CRM_Cpreports_Form_Report_Totalcontacthours extends CRM_Report_Form {

  protected $_autoIncludeIndexedFieldsAsOrderBys = 1;

  protected $_selectAliasesTotal = array();

  protected $_customGroupExtends = array(
    'Activity',
  );

  protected $_customGroupGroupBy = FALSE;

  protected $_customFields = array();

  /**
   * @var array
   * list of options for the activity_type_id filter.
   */
  protected $activityTypeIdOptions = array();

  /**
   * @var array
   * Static array for caching display links per team contact_id.
   */
  protected $assignedTeamLinks = [];

  /**
   * This report has not been optimised for group filtering.
   *
   * The functionality for group filtering has been improved but not
   * all reports have been adjusted to take care of it. This report has not
   * and will run an inefficient query until fixed.
   *
   * CRM-19170
   *
   * @var bool
   */
  protected $groupFilterNotOptimised = TRUE;

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->_autoIncludeIndexedFieldsAsOrderBys = 1;

    // There could be multiple contacts. We not clear on which contact id to display.
    // Lets hide it for now.
    $this->_exposeContactID = FALSE;

    // Populate list of options for the activity_type_id filter
    $result = civicrm_api3('OptionValue', 'get', [
      'sequential' => 1,
      'option_group_id' => 'activity_type',
      'name' => ['IN' => ['service hours', 'client service hours', 'legacy service hours']],
    ]);
    foreach ($result['values'] as $value) {
      $this->activityTypeIdOptions[$value['value']] = $value['label'];
    }

    // Build a list of options for the diagnosis select filter (all diagnosis options)
    $customFieldId_diagnosis1 = CRM_Core_BAO_CustomField::getCustomFieldID('Diagnosis_1', 'Health');
    $customFieldId_diagnosis2 = CRM_Core_BAO_CustomField::getCustomFieldID('Diagnosis_2', 'Health');
    $customFieldId_diagnosis3 = CRM_Core_BAO_CustomField::getCustomFieldID('Diagnosis_3', 'Health');
    $diagnosisOptions = CRM_Core_BAO_CustomField::buildOptions('custom_' . $customFieldId_diagnosis1);

    $this->_customFields['diagnosis1'] = civicrm_api3('customField', 'getSingle', array('id' => $customFieldId_diagnosis1));
    $this->_customFields['diagnosis2'] = civicrm_api3('customField', 'getSingle', array('id' => $customFieldId_diagnosis2));
    $this->_customFields['diagnosis3'] = civicrm_api3('customField', 'getSingle', array('id' => $customFieldId_diagnosis3));

    // @todo split the 3 different contact tables into their own array items.
    // this will massively simplify the needs of this report.
    $this->_columns = array(
      'civicrm_contact_assignee' => array(
        'dao' => 'CRM_Contact_DAO_Contact',
        'alias' => 'civicrm_contact_assignee',
        'fields' => array(
          'contact_assignee_id_display' => array(
            'name' => 'id',
            'title' => E::ts('Assignee ID'),
          ),
          'contact_assignee' => array(
            'name' => 'sort_name',
            'title' => E::ts('Assignee Name'),
            'dbAlias' => "civicrm_contact_assignee_civireport.sort_name",
            'default' => TRUE,
          ),
          'contact_assignee_id' => array(
            'name' => 'id',
            'no_display' => TRUE,
            'required' => TRUE,
          ),
        ),
        'filters' => array(
          'contact_assignee' => array(
            'name' => 'sort_name',
            'title' => E::ts('Assignee Name'),
            'operator' => 'like',
            'type' => CRM_Report_Form::OP_STRING,
          ),
          'diagnosis' => array(
            'title' => E::ts('Diagnosis 1, 2 or 3'),
            'pseudofield' => TRUE,
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => $diagnosisOptions,
            'type' => CRM_Utils_Type::T_STRING,
            'dbAlias' => "ARTIFICIALLY MANUFACTURED IN self::storeWhereHavingClauseArray()",
          ),
        ),
        'order_bys' => array(
          'sort_name' => array(
            'title' => E::ts('Assignee Name'),
            'default_weight' => '1',
            'default_is_section' => TRUE,
          ),
        ),
        'grouping' => 'contact-assignee-fields',
      ),
      'civicrm_contact_assignedteam' => array(
        'dao' => 'CRM_Contact_DAO_Contact',
        'alias' => 'civicrm_contact_assignedteam',
        'fields' => array(
          'assigned_team_ids' => array(
            'title' => E::ts('Assigned Team(s)'),
            'dbAlias' => 'GROUP_CONCAT(civicrm_contact_assignedteam_civireport.id)',
          ),
        ),
        'grouping' => 'contact-fields',
      ),
      'civicrm_activity' => array(
        'dao' => 'CRM_Activity_DAO_Activity',
        'fields' => array(
          'id' => array(
            'no_display' => TRUE,
            'title' => E::ts('Activity ID'),
            'required' => TRUE,
          ),
          'activity_type_id' => array(
            'title' => E::ts('Activity Type'),
          ),
          'activity_subject' => array(
            'title' => E::ts('Subject'),
            'default' => TRUE,
          ),
          'activity_date_time' => array(
            'title' => E::ts('Activity Date'),
            'default' => TRUE,
          ),
          'status_id' => array(
            'title' => E::ts('Activity Status'),
            'default' => TRUE,
            'type' => CRM_Utils_Type::T_STRING,
          ),
          'duration' => array(
            'title' => E::ts('Duration'),
            'default' => TRUE,
            'type' => CRM_Utils_Type::T_INT,
          ),
          'location' => array(
            'title' => E::ts('Location'),
            'type' => CRM_Utils_Type::T_STRING,
          ),
          'details' => array(
            'title' => E::ts('Activity Details'),
          ),
          'priority_id' => array(
            'title' => E::ts('Priority'),
            'type' => CRM_Utils_Type::T_STRING,
          ),
        ),
        'filters' => array(
          'activity_date_time' => array(
            'operatorType' => CRM_Report_Form::OP_DATE,
          ),
          'activity_subject' => array('title' => E::ts('Activity Subject')),
          'status_id' => array(
            'title' => E::ts('Activity Status'),
            'type' => CRM_Utils_Type::T_STRING,
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Core_PseudoConstant::activityStatus(),
          ),
          'activity_type_id' => array(
            'title' => E::ts('Activity Type'),
            'type' => CRM_Utils_Type::T_INT,
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => $this->activityTypeIdOptions,
          ),
        ),
        'order_bys' => array(
          'activity_date_time' => array(
            'title' => E::ts('Activity Date'),
            'dbAlias' => 'civicrm_activity_activity_date_time',
          ),
        ),
        'grouping' => 'activity-fields',
        'alias' => 'activity',
      ),
      // Hack to get $this->_alias populated for the table.
      'civicrm_activity_contact' => array(
        'dao' => 'CRM_Activity_DAO_ActivityContact',
        'fields' => array(),
      ),
    );
    $this->_columns += CRM_Cpreports_Utils::getTeamColumns('Target (Serving Team)');

    parent::__construct();
  }

  /**
   * Build from clause.
   * @todo remove this function & declare the 3 contact tables separately
   */
  public function from() {
    $activityContacts = CRM_Activity_BAO_ActivityContact::buildOptions('record_type_id', 'validate');
    $targetID = CRM_Utils_Array::key('Activity Targets', $activityContacts);
    $assigneeID = CRM_Utils_Array::key('Activity Assignees', $activityContacts);
    $this->_aliases['civicrm_contact'] = $this->_aliases['civicrm_contact_assignee'];

    $this->_from = "
      FROM civicrm_activity {$this->_aliases['civicrm_activity']}
      INNER JOIN civicrm_activity_contact {$this->_aliases['civicrm_activity_contact']}_target
        ON {$this->_aliases['civicrm_activity']}.id = {$this->_aliases['civicrm_activity_contact']}_target.activity_id AND
          {$this->_aliases['civicrm_activity_contact']}_target.record_type_id = {$targetID}
      INNER JOIN civicrm_contact {$this->_aliases['civicrm_contact_team']}
        ON {$this->_aliases['civicrm_activity_contact']}_target.contact_id = {$this->_aliases['civicrm_contact_team']}.id

      INNER JOIN civicrm_activity_contact {$this->_aliases['civicrm_activity_contact']}_assignee
        ON {$this->_aliases['civicrm_activity']}.id = {$this->_aliases['civicrm_activity_contact']}_assignee.activity_id AND
          {$this->_aliases['civicrm_activity_contact']}_assignee.record_type_id = {$assigneeID}
      INNER JOIN civicrm_contact {$this->_aliases['civicrm_contact_assignee']}
        ON {$this->_aliases['civicrm_activity_contact']}_assignee.contact_id = {$this->_aliases['civicrm_contact_assignee']}.id

      LEFT JOIN civicrm_value_health_5
        ON civicrm_value_health_5.entity_id = {$this->_aliases['civicrm_contact_assignee']}.id
    ";
    if ($this->isTableSelected('civicrm_contact_assignedteam')) {
      list($from, $to) = $this->getFromTo($this->_params['activity_date_time_relative'] ?? NULL, $this->_params['activity_date_time_from'] ?? NULL, $this->_params['activity_date_time_to'] ?? NULL);
      $activityDateTimeJoinConditions = [];
      if ($from) {
        $activityDateTimeJoinConditions[] = "(r.end_date IS NULL OR r.end_date >= $from)";
      }
      if ($to) {
        $activityDateTimeJoinConditions[] = "(r.start_date IS NULL OR r.start_date <= $to)";
      }
      if (!empty($activityDateTimeJoinConditions)) {
        $activityDateTimeJoinCondition = ' AND ' . implode(' AND ', $activityDateTimeJoinConditions);
      }
      else {
        $activityDateTimeJoinCondition = '';
      }
      $this->_from .= "
        LEFT JOIN civicrm_relationship r ON
          r.relationship_type_id = 18 -- is_team_client
          AND r.contact_id_b = {$this->_aliases['civicrm_contact_assignee']}.id
          $activityDateTimeJoinCondition
        LEFT JOIN civicrm_contact {$this->_aliases['civicrm_contact_assignedteam']}
          ON {$this->_aliases['civicrm_contact_assignedteam']}.id = r.contact_id_a
      ";
    }
    $this->_from .= "
      -- aclFrom:
      {$this->_aclFrom}
      -- ^^ aclFrom ^^
    ";
  }

  public function storeWhereHavingClauseArray() {
    parent::storeWhereHavingClauseArray();
    // Limit this report to 'service hours' activities (type_id = 56)
    $this->_whereClauses[] = "{$this->_aliases['civicrm_activity']}.activity_type_id IN (" . implode(', ', array_keys($this->activityTypeIdOptions)) . ")";

    if ($this->_params['diagnosis_value']) {
      // Apply "any diagnosis" filter
      $diagnosisOrWheres = array();
      // Define fields for diagnosis 1, 2, and 3, each as a copy of the 'diagnosis' filter
      // field; then manually alter the 'dbAlias' property to use the relevant
      // custom field column.
      $customDiagnosisField1 =
      $customDiagnosisField2 =
      $customDiagnosisField3 =
        $this->_columns['civicrm_contact_assignee']['filters']['diagnosis'];
      $customDiagnosisField1['dbAlias'] = "civicrm_value_health_5.{$this->_customFields['diagnosis1']['column_name']}";
      $customDiagnosisField2['dbAlias'] = "civicrm_value_health_5.{$this->_customFields['diagnosis2']['column_name']}";
      $customDiagnosisField3['dbAlias'] = "civicrm_value_health_5.{$this->_customFields['diagnosis3']['column_name']}";
      // Process each of these filter fields into where clauses.
      $diagnosisOrWheres[] = $this->whereClause($customDiagnosisField1, $this->_params['diagnosis_op'], $this->_params['diagnosis_value'], NULL, NULL);
      $diagnosisOrWheres[] = $this->whereClause($customDiagnosisField2, $this->_params['diagnosis_op'], $this->_params['diagnosis_value'], NULL, NULL);
      $diagnosisOrWheres[] = $this->whereClause($customDiagnosisField3, $this->_params['diagnosis_op'], $this->_params['diagnosis_value'], NULL, NULL);
      // Join these where clauses into a single clause.
      if ($this->_params['diagnosis_op'] == 'in') {
        $andOr = ' OR ';
      }
      else {
        $andOr = ' AND ';
      }
      $this->_whereClauses[] = '(' . implode($andOr, $diagnosisOrWheres) . ')';
    }

  }

  /**
   * Override group by function.
   */
  public function groupBy() {
    $this->_groupBy = CRM_Contact_BAO_Query::getGroupByFromSelectColumns($this->_selectClauses, "{$this->_aliases['civicrm_activity']}.id");
  }

  public function sectionTotals() {
    // Get $select here, because parent::sectionTotals() alters $this->_select
    // (not sure why, but it does).
    $select = str_ireplace('SELECT SQL_CALC_FOUND_ROWS ', 'SELECT ', $this->_select);
    parent::sectionTotals();

    // If we're sorting on contact name, with a header and it's the first section
    // total, AND if we're displaying the duration column, we'll sum up the
    // duration of service records. Othwerwise, we won't bother.
    $sectionAliases = array_keys($this->_sections);
    if (array_key_exists('duration', $this->_params['fields']) && CRM_Utils_Array::value(0, $sectionAliases) == 'civicrm_contact_assignee_sort_name') {
      // parent::sectionTotals() has already assigned values for section header
      // totals in the template. Fetch those from the template, and we'll alter
      // them below; the top-level header totals will be keyed to sort_name.
      $totals = $this->getTemplate()->get_template_vars('sectionTotals');
      // build a query based on this report, with no LIMIT clause.
      $sql = "{$select} {$this->_from} {$this->_where} {$this->_groupBy} {$this->_having} {$this->_orderBy}";
      // Use that sql as a subquery, grouping by sort_name and suming duration.
      $query = "select civicrm_contact_assignee_sort_name, sum(civicrm_activity_duration) as ct from ($sql) as subquery group by civicrm_contact_assignee_sort_name";
      $dao = CRM_Core_DAO::executeQuery($query);
      // For each row, update the header totals for that sort_name.
      while ($dao->fetch()) {
        $sortName = $dao->civicrm_contact_assignee_sort_name;
        if ($total = CRM_Utils_Array::value($sortName, $totals)) {
          $total .= E::ts(" service records; total duration: %1", array(
            1 => $dao->ct,
          ));
          $totals[$sortName] = $total;
        }
      }
      // Re-assign the modified section totals to the template.
      $this->assign('sectionTotals', $totals);
    }
  }

  /**
   * Build ACL clause.
   *
   * @param string $tableAlias
   */
  public function buildACLClause($tableAlias = 'contact_a') {
    //override for ACL( Since Contact may be source
    //contact/assignee or target also it may be null )

    if (CRM_Core_Permission::check('view all contacts')) {
      $this->_aclFrom = $this->_aclWhere = NULL;
      return;
    }

    $session = CRM_Core_Session::singleton();
    $contactID = $session->get('userID');
    if (!$contactID) {
      $contactID = 0;
    }
    $contactID = CRM_Utils_Type::escape($contactID, 'Integer');

    CRM_Contact_BAO_Contact_Permission::cache($contactID);
    $clauses = array();
    foreach ($tableAlias as $k => $alias) {
      $clauses[] = " INNER JOIN civicrm_acl_contact_cache aclContactCache_{$k} ON ( {$alias}.id = aclContactCache_{$k}.contact_id OR {$alias}.id IS NULL ) AND aclContactCache_{$k}.user_id = $contactID ";
    }

    $this->_aclFrom = implode(" ", $clauses);
    $this->_aclWhere = NULL;
  }

  /**
   * Alter display of rows.
   *
   * Iterate through the rows retrieved via SQL and make changes for display purposes,
   * such as rendering contacts as links.
   *
   * @param array $rows
   *   Rows generated by SQL, with an array for each row.
   */
  public function alterDisplay(&$rows) {
    CRM_Cpreports_Utils::alterDisplayTeam($rows);

    $entryFound = FALSE;
    $activityStatus = CRM_Core_PseudoConstant::activityStatus();
    $priority = CRM_Core_PseudoConstant::get('CRM_Activity_DAO_Activity', 'priority_id');
    $viewLinks = FALSE;

    // Would we ever want to retrieve from the form controller??
    $form = $this->noController ? NULL : $this;
    $context = CRM_Utils_Request::retrieve('context', 'Alphanumeric', $form, FALSE, 'report');
    $actUrl = '';

    if (CRM_Core_Permission::check('access CiviCRM')) {
      $viewLinks = TRUE;
      $onHover = E::ts('View Contact Summary for this Contact');
      $onHoverAct = E::ts('View Activity Record');
    }
    foreach ($rows as $rowNum => $row) {
      if (array_key_exists('civicrm_contact_assignee_contact_assignee', $row)) {
        if ($cid = $row['civicrm_contact_assignee_contact_assignee_id']) {
          if ($viewLinks) {
            $url = CRM_Utils_System::url("civicrm/contact/view",
              'reset=1&cid=' . $cid,
              $this->_absoluteUrl
            );
            $rows[$rowNum]['civicrm_contact_assignee_contact_assignee_link'] = $url;
            $rows[$rowNum]['civicrm_contact_assignee_contact_assignee_hover'] = $onHover;
          }
          $entryFound = TRUE;
        }
      }

      if (array_key_exists('civicrm_activity_status_id', $row)) {
        if ($value = $row['civicrm_activity_status_id']) {
          $rows[$rowNum]['civicrm_activity_status_id'] = $activityStatus[$value];
          $entryFound = TRUE;
        }
      }

      if (array_key_exists('civicrm_activity_priority_id', $row)) {
        if ($value = $row['civicrm_activity_priority_id']) {
          $rows[$rowNum]['civicrm_activity_priority_id'] = $priority[$value];
          $entryFound = TRUE;
        }
      }

      if (array_key_exists('civicrm_activity_activity_date_time', $row) &&
        array_key_exists('civicrm_activity_status_id', $row)
      ) {
        if (CRM_Utils_Date::overdue($rows[$rowNum]['civicrm_activity_activity_date_time']) &&
          $activityStatus[$row['civicrm_activity_status_id']] != 'Completed'
        ) {
          $rows[$rowNum]['class'] = "status-overdue";
          $entryFound = TRUE;
        }
      }

      if (array_key_exists('civicrm_activity_activity_type_id', $row)) {
        if ($value = $row['civicrm_activity_activity_type_id']) {
          $rows[$rowNum]['civicrm_activity_activity_type_id'] = $this->activityTypeIdOptions[$value];
          $entryFound = TRUE;
        }
      }

      if (array_key_exists('civicrm_contact_assignedteam_assigned_team_ids', $row)) {
        if ($value = $row['civicrm_contact_assignedteam_assigned_team_ids']) {
          $displayLinks = [];
          $assignedTeamCids = explode(',', $value);
          foreach ($assignedTeamCids as $assignedTeamCid) {
            $displayLinks[] = $this->_getLinkForTeamCid($assignedTeamCid);
          }
          $rows[$rowNum]['civicrm_contact_assignedteam_assigned_team_ids'] = implode('<br />', $displayLinks);
          $entryFound = TRUE;
        }
      }

      if (!$entryFound) {
        break;
      }
    }
  }

  public function statistics(&$rows) {
    $statistics = parent::statistics($rows);

    // Get an abbreviated form of the report SQL, and use it to get a count of
    // distinct team contact_ids
    $sqlBase = $this->_getSqlBase();

    // Define a string for indenting.
    $indentPrefix = '&nbsp; &nbsp; ';

    $distinctContactCountQuery = "
      select count(distinct t.id)
      from (
        select civicrm_contact_assignee_civireport.id $sqlBase
      ) t
    ";
    $statistics['counts']['contact_count_total'] = array(
      'title' => E::ts('Total distinct contacts'),
      'value' => CRM_Core_DAO::singleValueQuery($distinctContactCountQuery),
      // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
      'type' => CRM_Utils_Type::T_INT,
    );

    $totalMinutesQuery = "
      select sum(t.duration)
      from (
        select activity_civireport.duration $sqlBase
      ) t
    ";
    $statistics['counts']['total_duration'] = array(
      'title' => E::ts("Total duration"),
      'value' => CRM_Core_DAO::singleValueQuery($totalMinutesQuery),
      // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
      'type' => CRM_Utils_Type::T_INT,
    );

    // Show 'same team' stats, only if 'Assigned Team(s)' field is displayed.
    if (isset($this->_params['fields']["assigned_team_ids"])) {
      // Section header
      $statistics['counts']['contact_sameteam_count_blank'] = array(
        'title' => E::ts('Contact counts per team assignment'),
        'value' => '',
        // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
        'type' => CRM_Utils_Type::T_STRING,
      );
      $distinctAssignedTeamsContactCountQuery = "
        select count(*) from
        (
          select distinct
            civicrm_contact_assignee_civireport.id as client_id,
            civicrm_contact_assignedteam_civireport.id as assignedteam_id
          $sqlBase
          having assignedteam_id IN (
            select distinct civicrm_contact_team_civireport.id as serving_team_id
            $sqlBase
          )
        ) t
      ";
      $statistics['counts']['distinctAssignedTeamsContactCount'] = array(
        'title' => $indentPrefix . E::ts('Contacts assigned to selected team(s)'),
        'value' => CRM_Core_DAO::singleValueQuery($distinctAssignedTeamsContactCountQuery),
        // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
        'type' => CRM_Utils_Type::T_INT,
      );

      $distinctNotAssignedTeamsContactCountQuery = "
        select count(*) from
        (
          select distinct
            civicrm_contact_assignee_civireport.id as client_id,
            civicrm_contact_assignedteam_civireport.id as assignedteam_id
          $sqlBase
          having assignedteam_id IS NULL OR assignedteam_id NOT IN (
            select distinct civicrm_contact_team_civireport.id as serving_team_id
            $sqlBase
          )
        ) t
      ";
      $statistics['counts']['distinctNotAssignedTeamsContactCount'] = array(
        'title' => $indentPrefix . E::ts('Contacts not assigned to selected team(s)'),
        'value' => CRM_Core_DAO::singleValueQuery($distinctNotAssignedTeamsContactCountQuery),
        // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
        'type' => CRM_Utils_Type::T_INT,
      );

      // Section header
      $statistics['counts']['activity_sameteam_count_blank'] = array(
        'title' => E::ts('Activity counts per team assignment'),
        'value' => E::ts(''),
        // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
        'type' => CRM_Utils_Type::T_STRING,
      );

      $distinctAssignedTeamsActivityCountQuery = "
        select count(*) from
        (
          select distinct
            activity_civireport.id as client_id,
            civicrm_contact_assignedteam_civireport.id as assignedteam_id
            $sqlBase
          having assignedteam_id IN (
            select distinct civicrm_contact_team_civireport.id as serving_team_id
            $sqlBase
          )
        ) t
      ";
      $statistics['counts']['distinctAssignedTeamsActivityCount'] = array(
        'title' => $indentPrefix . E::ts('Activities for contacts assigned to selected team(s)'),
        'value' => CRM_Core_DAO::singleValueQuery($distinctAssignedTeamsActivityCountQuery),
        // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
        'type' => CRM_Utils_Type::T_INT,
      );

      $distinctNotAssignedTeamsActivityCountQuery = "
        select count(*) from
        (
          select distinct
            activity_civireport.id as client_id,
            civicrm_contact_assignedteam_civireport.id as assignedteam_id
            $sqlBase
          having assignedteam_id IS NULL OR assignedteam_id NOT IN (
            select distinct civicrm_contact_team_civireport.id as serving_team_id
            $sqlBase
          )
        ) t
      ";
      $statistics['counts']['distinctNotAssignedTeamsActivityCount'] = array(
        'title' => $indentPrefix . E::ts('Activities for contacts not assigned to selected team(s)'),
        'value' => CRM_Core_DAO::singleValueQuery($distinctNotAssignedTeamsActivityCountQuery),
        // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
        'type' => CRM_Utils_Type::T_INT,
      );

      // Section header
      $statistics['counts']['duration_sameteam_count_blank'] = array(
        'title' => E::ts('Total duration per team assignment'),
        'value' => '',
        // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
        'type' => CRM_Utils_Type::T_STRING,
      );

      $distinctAssignedTeamsDurationQuery = "
        select sum(duration) from
        (
          select
            activity_civireport.duration,
            civicrm_contact_assignedteam_civireport.id as assignedteam_id
            $sqlBase
          having assignedteam_id IN (
            select distinct civicrm_contact_team_civireport.id as serving_team_id
            $sqlBase
          )
        ) t
      ";
      $statistics['counts']['distinctAssignedTeamsDuration'] = array(
        'title' => $indentPrefix . E::ts('Total duration for contacts assigned to selected team(s)'),
        'value' => CRM_Core_DAO::singleValueQuery($distinctAssignedTeamsDurationQuery),
        // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
        'type' => CRM_Utils_Type::T_INT,
      );

      $distinctNotAssignedTeamsDurationQuery = "
        select sum(duration) from
        (
          select
            activity_civireport.duration,
            civicrm_contact_assignedteam_civireport.id as assignedteam_id
            $sqlBase
          having assignedteam_id is null or assignedteam_id not IN (
            select distinct civicrm_contact_team_civireport.id as serving_team_id
            $sqlBase
          )
        ) t
      ";
      $statistics['counts']['distinctNotAssignedTeamsDuration'] = array(
        'title' => $indentPrefix . E::ts('Total duration for contacts not assigned to selected team(s)'),
        'value' => CRM_Core_DAO::singleValueQuery($distinctNotAssignedTeamsDurationQuery),
        // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
        'type' => CRM_Utils_Type::T_INT,
      );

    }
    else {
      // Section header
      $statistics['counts']['contact_sameteam_count_blank'] = array(
        'title' => E::ts('Contact counts per team assignment'),
        'value' => E::ts('(Please enable the "Assigned Team(s)" column to reveal these statistics.)'),
        // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
        'type' => CRM_Utils_Type::T_STRING,
      );
      // Section header
      $statistics['counts']['activity_sameteam_count_blank'] = array(
        'title' => E::ts('Activity counts per team assignment'),
        'value' => E::ts('(Please enable the "Assigned Team(s)" column to reveal these statistics.)'),
        // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
        'type' => CRM_Utils_Type::T_STRING,
      );
      // Section header
      $statistics['counts']['duration_sameteam_count_blank'] = array(
        'title' => E::ts('Total duration per team assignment'),
        'value' => E::ts('(Please enable the "Assigned Team(s)" column to reveal these statistics.)'),
        // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
        'type' => CRM_Utils_Type::T_STRING,
      );
    }

    // Show help-type stats, only if 'Help Type' field is displayed.
    $customFieldId_helpType = CRM_Core_BAO_CustomField::getCustomFieldID('Help_type', 'Service_details');
    if (isset($this->_params['fields']["custom_{$customFieldId_helpType}"])) {
      $helpTypeLabels = CRM_Core_BAO_CustomField::buildOptions('custom_' . $customFieldId_helpType);
      $customField_helpType = civicrm_api3(
        'customField', 'getSingle', [
          'sequential' => 1,
          'id' => $customFieldId_helpType,
          'api.CustomGroup.get' => [],
        ]
      );
      $helpTypeCustomFieldTableName = $customField_helpType['api.CustomGroup.get']['values'][0]['table_name'];
      $helpTypeCustomFieldColumnName = $customField_helpType['column_name'];

      // Section header
      $statistics['counts']['helptype_count_blank'] = array(
        'title' => E::ts('Activity counts per Help Type'),
        'value' => '',
        // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
        'type' => CRM_Utils_Type::T_STRING,
      );

      $helpTypeCountsQuery = "
        SELECT COUNT(*) AS cnt, t.helptype
        FROM (
          SELECT {$this->_aliases[$helpTypeCustomFieldTableName]}.$helpTypeCustomFieldColumnName AS helptype
          $sqlBase
        ) t GROUP BY helptype
      ";
      $dao = CRM_Core_DAO::executeQuery($helpTypeCountsQuery);
      $i = 0;
      while ($dao->fetch()) {
        $statistics['counts']['helptype_count_' . $i++] = array(
          'title' => $indentPrefix . CRM_Utils_Array::value($dao->helptype, $helpTypeLabels, '[NONE SPECIFIED]'),
          'value' => $dao->cnt,
          // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
          'type' => CRM_Utils_Type::T_INT,
        );
      }

      $statistics['counts']['helptype_duration_blank'] = array(
        'title' => E::ts('Total duration per Help Type'),
        'value' => '',
        // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
        'type' => CRM_Utils_Type::T_STRING,
      );

      $helpTypeDurationQuery = "
        SELECT sum(t.duration) as durationsum, t.helptype
        FROM (
          SELECT activity_civireport.duration, value_service_detai_3_civireport.help_type_57 AS helptype
          $sqlBase
        ) t GROUP BY helptype
      ";

      $dao = CRM_Core_DAO::executeQuery($helpTypeDurationQuery);
      $i = 0;
      while ($dao->fetch()) {
        $statistics['counts']['helptype_duration_' . $i++] = array(
          'title' => $indentPrefix . CRM_Utils_Array::value($dao->helptype, $helpTypeLabels, '[NONE SPECIFIED]'),
          'value' => $dao->durationsum,
          // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
          'type' => CRM_Utils_Type::T_INT,
        );
      }

      $statistics['counts']['helptype_frequency_blank'] = array(
        'title' => E::ts('Contact frequency per Help Type'),
        'value' => '',
        // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
        'type' => CRM_Utils_Type::T_STRING,
      );

      $helpTypeFrequencyQuery = "
        select activitycount, count(*) as cnt from (
          select if(count(*) > 9, '10+', count(*)) as activitycount, id, helptype
          from (
          select civicrm_contact_assignee_civireport.id, value_service_detai_3_civireport.help_type_57 as helptype
            $sqlBase
          ) counts
          where helptype = 'hc'
          group by id, helptype
        ) t
        group by activitycount
        order by cast(activitycount as unsigned)
      ";
      $dao = CRM_Core_DAO::executeQuery($helpTypeFrequencyQuery);
      $hcHelpTypeLabel = $helpTypeLabels['HC'];
      $hcHelpTypeCounts = [];
      while ($dao->fetch()) {
        $hcHelpTypeCounts[$dao->activitycount] = $dao->cnt;
      }
      for ($i = 1; $i <= 9; $i++) {
        $statistics['counts']['helptype_frequency_' . $i] = array(
          'title' => $indentPrefix . E::ts("Contacts with %1 \"%2\" activities", [
            1 => $i,
            2 => $hcHelpTypeLabel,
          ]),
          'value' => $hcHelpTypeCounts[$i] ?? 0,
          // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
          'type' => CRM_Utils_Type::T_INT,
        );
      }
      $i = '10+';
      $statistics['counts']['helptype_frequency_' . $i] = array(
        'title' => $indentPrefix . E::ts("Contacts with %1 \"%2\" activities", [
          1 => $i,
          2 => $hcHelpTypeLabel,
        ]),
        'value' => $hcHelpTypeCounts[$i] ?? 0,
        // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
        'type' => CRM_Utils_Type::T_INT,
      );

      $onlySeHelpTypeCountQuery = "
        select count(*) as cnt from (
          select id, group_concat(distinct helptype) as helptypes
          from (
          select civicrm_contact_assignee_civireport.id, value_service_detai_3_civireport.help_type_57 as helptype
            $sqlBase
          ) concats
          group by id
          having helptypes = 'SE'
        ) t
      ";
      $statistics['counts']['helptype_frequency_SE'] = array(
        'title' => $indentPrefix . E::ts("Contacts with only \"%1\" activities", [
          1 => $helpTypeLabels['SE'],
        ]),
        'value' => CRM_Core_DAO::singleValueQuery($onlySeHelpTypeCountQuery),
        // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
        'type' => CRM_Utils_Type::T_INT,
      );

      $onlyXxHelpTypeCountQuery = "
        select count(*) as cnt from (
          select id, group_concat(distinct helptype) as helptypes
          from (
          select civicrm_contact_assignee_civireport.id, value_service_detai_3_civireport.help_type_57 as helptype
            $sqlBase
          ) concats
          group by id
          having helptypes = 'XX'
        ) t
      ";
      $statistics['counts']['helptype_frequency_XX'] = array(
        'title' => $indentPrefix . E::ts("Contacts with only \"%1\" activities", [
          1 => $helpTypeLabels['XX'],
        ]),
        'value' => CRM_Core_DAO::singleValueQuery($onlyXxHelpTypeCountQuery),
        // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
        'type' => CRM_Utils_Type::T_INT,
      );
    }
    else {
      // Section header
      $statistics['counts']['helptype_count_blank'] = array(
        'title' => E::ts('Activity counts per Help Type'),
        'value' => E::ts('(Please enable the "Help Type" column to reveal these statistics.)'),
        // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
        'type' => CRM_Utils_Type::T_STRING,
      );
      $statistics['counts']['helptype_duration_blank'] = array(
        'title' => E::ts('Total duration per Help Type'),
        'value' => E::ts('(Please enable the "Help Type" column to reveal these statistics.)'),
        // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
        'type' => CRM_Utils_Type::T_STRING,
      );
      $statistics['counts']['helptype_frequency_blank'] = array(
        'title' => E::ts('Contact frequency per Help Type'),
        'value' => E::ts('(Please enable the "Help Type" column to reveal these statistics.)'),
        // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
        'type' => CRM_Utils_Type::T_STRING,
      );
    }

    return $statistics;
  }

  public function _getSqlBase() {
    return " {$this->_from} {$this->_where} {$this->_groupBy} {$this->_having}";
  }

  private function _getLinkForTeamCid($assignedTeamCid) {
    if (empty($this->assignedTeamLinks[$assignedTeamCid])) {
      $teamContacts = civicrm_api3('Contact', 'get', [
        'id' => $assignedTeamCid,
        'sequential' => 1,
      ]);
      if ($displayName = $teamContacts['values'][0]['display_name']) {
        $url = CRM_Utils_System::url('/civicrm/contact/view', 'reset=1&cid=' . $assignedTeamCid, TRUE);
        $this->assignedTeamLinks[$assignedTeamCid] = '<a href="' . $url . '">' . $displayName . '</a>';
      }
    }
    return $this->assignedTeamLinks[$assignedTeamCid] ?? NULL;
  }

}
