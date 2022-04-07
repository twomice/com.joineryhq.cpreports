<?php
use CRM_Cpreports_ExtensionUtil as E;

class CRM_Cpreports_Form_Report_teams extends CRM_Report_Form {

  protected $_autoIncludeIndexedFieldsAsOrderBys = 1;
  protected $_customGroupExtends = array('Organization', 'Contact');
  protected $_customGroupGroupBy = FALSE;

  protected $customGroup_teamDetails;
  protected $customFields_teamDetails = [];

  public function __construct() {
    // Get metadata for Team_details custom field group, and for 'Team status'
    // custom field in that group.
    $this->customGroup_teamDetails = civicrm_api3('customGroup', 'getSingle', array(
      'name' => 'Team_details',
    ));
    $customFieldsGet = civicrm_api3('customField', 'get', array(
      'custom_group_id' => $this->customGroup_teamDetails['id'],
    ));
    $this->customFields_teamDetails = CRM_Utils_Array::rekey($customFieldsGet['values'], 'name');

    $this->_columns = array(
      'civicrm_contact' => array(),
    );
    $this->_groupFilter = TRUE;
    $this->_tagFilter = TRUE;

    $this->_columns += CRM_Cpreports_Utils::getTeamColumns();

    parent::__construct();

    // Set some custom fields for default display.
    $teamDetailsFields =& $this->_columns[$this->customGroup_teamDetails['table_name']]['fields'];
    $defaultFieldNames = array(
      'Beginning_Date',
      'Ending_Date',
      'Team_Status',
    );
    foreach ($defaultFieldNames as $defaultFieldName) {
      $fieldId = $this->customFields_teamDetails[$defaultFieldName]['id'];
      $teamDetailsFields["custom_{$fieldId}"]['default'] = TRUE;
    }
  }

  public function from() {
    $this->_from = "
      FROM
        civicrm_contact {$this->_aliases['civicrm_contact_team']}
        INNER JOIN civicrm_contact {$this->_aliases['civicrm_contact']} ON {$this->_aliases['civicrm_contact']}.id = {$this->_aliases['civicrm_contact_team']}.id
        {$this->_aclFrom}
    ";
    $this->_from .= "
      -- end from()

    ";
  }

  public function where() {
    parent::where();
    $this->_where .= "
      AND {$this->_aliases['civicrm_contact_team']}.contact_type = 'organization'
      AND {$this->_aliases['civicrm_contact_team']}.contact_sub_type LIKE '%" . CRM_Core_DAO::VALUE_SEPARATOR . 'Team' . CRM_Core_DAO::VALUE_SEPARATOR . "%'
    ";
  }

  public function alterDisplay(&$rows) {
    CRM_Cpreports_Utils::alterDisplayTeam($rows);
  }

  public function statistics(&$rows) {
    return;
    $statistics = parent::statistics($rows);
    // Get an abbreviated form of the report SQL, and use it as the base for stats queries.
    $sqlBase = $this->_getSqlBase();

    // Get all available 'team status' option values; we'll make a statistic for each one.
    $statusValuesQuery = "
      select ov.value, ov.label
      from
      civicrm_option_value ov
      inner join civicrm_option_group og
        on og.id = ov.option_group_id
      where
        og.id = {$this->customFields_teamDetails['Team_Status']['option_group_id']};
    ";
    $statusValuesDao = CRM_Core_DAO::executeQuery($statusValuesQuery);
    $statusStats = [];
    while ($statusValuesDao->fetch()) {
      $statusStats[$statusValuesDao->value] = array(
        'count' => 0,
        'label' => $statusValuesDao->label,
      );
    }

    // Get a per-team-status count statistic across report results.
    $statusCountsQuery = "
      select {$this->customFields_teamDetails['Team_Status']['column_name']} as statusValue, count(*) as cnt
      from
      civicrm_contact c
      inner join {$this->customGroup_teamDetails['table_name']} td
        on td.entity_id = c.id
      where
        c.id in (
          select {$this->_aliases['civicrm_contact_team']}.id $sqlBase
        )
      group by td.{$this->customFields_teamDetails['Team_Status']['column_name']}

    ";
    $statusCountsDao = CRM_Core_DAO::executeQuery($statusCountsQuery);
    while ($statusCountsDao->fetch()) {
      $statusStats[$statusCountsDao->statusValue]['count'] = $statusCountsDao->cnt;
    }

    foreach ($statusStats as $statusKey => $statusStat) {
      $statistics['counts']['status_' . $statusKey] = array(
        'title' => E::ts("Team status is '%1'", array(1 => $statusStat['label'])),
        'value' => $statusStat['count'],
        // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
        'type' => CRM_Utils_Type::T_INT,
      );
    }
    return $statistics;
  }

  public function _getSqlBase() {
    return " {$this->_from} {$this->_where} {$this->_groupBy} {$this->_having}";
  }

}
