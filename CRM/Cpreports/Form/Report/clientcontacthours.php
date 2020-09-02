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

class CRM_Cpreports_Form_Report_clientcontacthours extends CRM_Report_Form {

  protected $_autoIncludeIndexedFieldsAsOrderBys = 1;

  protected $_selectAliasesTotal = array();

  protected $_customGroupExtends = array(
    'Activity',
  );

  protected $_customGroupGroupBy = FALSE;

  protected $_customFields = array();

  // list of options for the activity_type_id filter.
  protected $activityTypeIdOptions = array();

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

    // Build a list of options for the nick_name select filter (all existing team nicknames)
    $nickNameOptions = array();
    $dao = CRM_Core_DAO::executeQuery('
        SELECT DISTINCT nick_name
      FROM civicrm_contact
      WHERE
        contact_type = "Organization"
        AND contact_sub_type LIKE "%team%"
        AND nick_name > ""
      ORDER BY nick_name
    ');
    while ($dao->fetch()) {
      $nickNameOptions[$dao->nick_name] = $dao->nick_name;
    }

    // Populate list of options for the activity_type_id filter
    $activityType = civicrm_api3('optionValue', 'get', [
      'sequential' => 1,
      'name' => 'Service hours',
      'option_group_id' => "activity_type",
    ]);
    $this->activityTypeIdOptions[$activityType['values'][0]['value']] = $activityType['values'][0]['label'];
    $activityType = civicrm_api3('optionValue', 'get', [
      'sequential' => 1,
      'name' => 'Client Service Hours',
      'option_group_id' => "activity_type",
    ]);
    $this->activityTypeIdOptions[$activityType['values'][0]['value']] = $activityType['values'][0]['label'];

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
      'civicrm_contact_target' => array(
        'dao' => 'CRM_Contact_DAO_Contact',
        'alias' => 'civicrm_contact_target',
        'fields' => array(
          'contact_target' => array(
            'name' => 'sort_name',
            'title' => E::ts('Target (Team) Name'),
            'dbAlias' => "civicrm_contact_target_civireport.sort_name",
            'default' => TRUE,
          ),
          'contact_target_id' => array(
            'name' => 'id',
            'no_display' => TRUE,
            'required' => TRUE,
          ),
        ),
        'filters' => array(
          'contact_target' => array(
            'name' => 'sort_name',
            'title' => E::ts('Target (Team) Name'),
            'operator' => 'like',
            'type' => CRM_Report_Form::OP_STRING,
          ),
          'nick_name_like' => array(
            'title' => E::ts('Team Nickname'),
            'dbAlias' => 'civicrm_contact_target_civireport.nick_name',
            'operator' => 'like',
            'type' => CRM_Utils_Type::T_STRING,
          ),
          'nick_name_select' => array(
            'title' => E::ts('Team Nickname'),
            'dbAlias' => 'civicrm_contact_target_civireport.nick_name',
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => $nickNameOptions,
            'type' => CRM_Utils_Type::T_STRING,
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
      INNER JOIN civicrm_contact {$this->_aliases['civicrm_contact_target']}
        ON {$this->_aliases['civicrm_activity_contact']}_target.contact_id = {$this->_aliases['civicrm_contact_target']}.id

      INNER JOIN civicrm_activity_contact {$this->_aliases['civicrm_activity_contact']}_assignee
        ON {$this->_aliases['civicrm_activity']}.id = {$this->_aliases['civicrm_activity_contact']}_assignee.activity_id AND
          {$this->_aliases['civicrm_activity_contact']}_assignee.record_type_id = {$assigneeID}
      INNER JOIN civicrm_contact {$this->_aliases['civicrm_contact_assignee']}
        ON {$this->_aliases['civicrm_activity_contact']}_assignee.contact_id = {$this->_aliases['civicrm_contact_assignee']}.id

      LEFT JOIN civicrm_value_health_5
        ON civicrm_value_health_5.entity_id = {$this->_aliases['civicrm_contact_assignee']}.id
      --  aclFrom:
      {$this->_aclFrom}
      --  ^^ aclFrom ^^
    ";
  }

  public function storeWhereHavingClauseArray() {
    parent::storeWhereHavingClauseArray();
    // Limit this report to 'service hours' activities (type_id = 56)
    $this->_whereClauses[] = "{$this->_aliases['civicrm_activity']}.activity_type_id IN (". implode(', ', array_keys($this->activityTypeIdOptions)).")";

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
      $query = "select civicrm_contact_assignee_sort_name,  sum(civicrm_activity_duration) as ct from ($sql) as subquery group by civicrm_contact_assignee_sort_name";
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

      if (array_key_exists('civicrm_contact_target_contact_target', $row)) {
        if ($cid = $row['civicrm_contact_target_contact_target_id']) {
          if ($viewLinks) {
            $url = CRM_Utils_System::url("civicrm/contact/view",
              'reset=1&cid=' . $cid,
              $this->_absoluteUrl
            );
            $rows[$rowNum]['civicrm_contact_target_contact_target_link'] = $url;
            $rows[$rowNum]['civicrm_contact_target_contact_target_hover'] = $onHover;
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

    $distinctContactCountQuery = "
      select count(distinct t.id)
      from (
        select civicrm_contact_assignee_civireport.id $sqlBase
      ) t
    ";
    $statistics['counts']['contact_count_total'] = array(
      'title' => ts('Total distinct contacts'),
      'value' => CRM_Core_DAO::singleValueQuery($distinctContactCountQuery),
      // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
      'type' => CRM_Utils_Type::T_INT,
    );

    // Section header
    $statistics['counts']['contact_count_blank'] = array(
      'title' => E::ts('Distinct contacts per activity type'),
      'value' => '',
      // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
      'type' => CRM_Utils_Type::T_STRING,
    );

    $indentPrefix = '&nbsp; &nbsp; ';

    foreach($this->activityTypeIdOptions as $activityTypeId => $activityTypeLabel) {
      $activityTypeCountQuery = "
        select count(distinct t.id)
        from (
          select civicrm_contact_assignee_civireport.id, activity_civireport.activity_type_id $sqlBase
        ) t
        where t.activity_type_id = %1
      ";
      $activityTypeCountParams = array(
        '1' => array($activityTypeId, 'Int'),
      );
      $statistics['counts']['contact_count_'. $activityTypeId] = array(
        'title' => $indentPrefix . $activityTypeLabel,
        'value' => CRM_Core_DAO::singleValueQuery($activityTypeCountQuery, $activityTypeCountParams),
        // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
        'type' => CRM_Utils_Type::T_INT,
      );
    }

    $totalMinutesQuery = "
      select sum(t.duration)
      from (
        select activity_civireport.duration $sqlBase
      ) t
    ";

    $statistics['counts']['total_duration'] = array(
      'title' => ts("Total duration"),
      'value' => CRM_Core_DAO::singleValueQuery($totalMinutesQuery),
      // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
      'type' => CRM_Utils_Type::T_INT,
    );

    return $statistics;
  }

  public function _getSqlBase() {
    return " {$this->_from} {$this->_where} {$this->_groupBy} {$this->_having}";
  }

}
