<?php
use CRM_Cpreports_ExtensionUtil as E;

class CRM_Cpreports_Form_Report_teams extends CRM_Report_Form {

  protected $_autoIncludeIndexedFieldsAsOrderBys = 1;
  protected $_customGroupExtends = array('Organization','Contact');
  protected $_customGroupGroupBy = FALSE;

  protected $customGroup_teamDetails;
  protected $customFields_teamDetails = [];


  function __construct() {
    // Get metadata for Team_details custom field group, and for 'Team status'
    // custom field in that group.
    $this->customGroup_teamDetails = civicrm_api3('customGroup', 'getSingle', array(
      'name' => 'Team_details',
    ));
    $customFieldsGet = civicrm_api3('customField', 'get', array(
      'custom_group_id' => $this->customGroup_teamDetails['id'],
    ));
    $this->customFields_teamDetails = CRM_Utils_Array::rekey($customFieldsGet['values'], 'name');

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

    $this->_columns = array(
      'civicrm_contact' => array(
        'fields' => array(
          'organization_name' => array(
            'title' => E::ts('Team Name'),
            'default' => TRUE,
          ),
          'id' => array(
            'no_display' => TRUE,
            'required' => TRUE,
          ),
          'nick_name' => array(
            'title' => E::ts('Nickname'),
            'default' => TRUE,
          ),
        ),
        'filters' => array(
          'organization_name' => array(
            'title' => E::ts('Team Name'),
            'operator' => 'like',
            'type' =>	CRM_Utils_Type::T_STRING,
          ),
          'nick_name_like' => array(
            'title' => E::ts('Team Nickname'),
            'dbAlias' => 'contact_civireport.nick_name',
            'operator' => 'like',
            'type' =>	CRM_Utils_Type::T_STRING,
          ),
          'nick_name_select' => array(
            'title' => E::ts('Team Nickname'),
            'dbAlias' => 'contact_civireport.nick_name',
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => $nickNameOptions,
            'type' =>	CRM_Utils_Type::T_STRING,
          ),
        ),
        'order_bys' => array(
          'organization_name' => array(
            'title' => E::ts('Team Name'),
          ),
          'nick_name' => array(
            'title' => E::ts('Nickname'),
          ),
        ),
        'grouping' => 'contact-fields',
      ),
    );
    $this->_groupFilter = TRUE;
    $this->_tagFilter = TRUE;

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

  function from() {
    $this->_from = "
      FROM  civicrm_contact {$this->_aliases['civicrm_contact']} {$this->_aclFrom}
    ";
    $this->_from .= "
      -- end from()

    ";
  }
  public function where() {
    parent::where();
    $this->_where .= "
      AND {$this->_aliases['civicrm_contact']}.contact_type = 'organization'
      AND {$this->_aliases['civicrm_contact']}.contact_sub_type LIKE '%" . CRM_Core_DAO::VALUE_SEPARATOR . 'Team' . CRM_Core_DAO::VALUE_SEPARATOR . "%'
    ";
  }

  function alterDisplay(&$rows) {
    // custom code to alter rows
    $entryFound = FALSE;
    foreach ($rows as $rowNum => $row) {

      if (array_key_exists('civicrm_contact_organization_name', $row) &&
        $rows[$rowNum]['civicrm_contact_organization_name'] &&
        array_key_exists('civicrm_contact_id', $row)
      ) {
        $url = CRM_Utils_System::url("civicrm/contact/view",
          'reset=1&cid=' . $row['civicrm_contact_id'],
          $this->_absoluteUrl
        );
        $rows[$rowNum]['civicrm_contact_organization_name_link'] = $url;
        $rows[$rowNum]['civicrm_contact_organization_name_hover'] = E::ts("View Contact Summary for this Contact.");
        $entryFound = TRUE;
      }
      elseif (array_key_exists('civicrm_contact_nick_name', $row) &&
        $rows[$rowNum]['civicrm_contact_nick_name'] &&
        array_key_exists('civicrm_contact_id', $row)
      ) {
        $url = CRM_Utils_System::url("civicrm/contact/view",
          'reset=1&cid=' . $row['civicrm_contact_id'],
          $this->_absoluteUrl
        );
        $rows[$rowNum]['civicrm_contact_nick_name_link'] = $url;
        $rows[$rowNum]['civicrm_contact_nick_name_hover'] = E::ts("View Contact Summary for this Contact.");
        $entryFound = TRUE;
      }

      if (!$entryFound) {
        break;
      }
    }
  }

  public function statistics(&$rows) {
    $statistics = parent::statistics($rows);
    // Get an abbreviated form of the report SQL, and use it as the base for stats queries.
    $sqlBase = " {$this->_from} {$this->_where} {$this->_groupBy} {$this->_having}";

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
          select {$this->_aliases['civicrm_contact']}.id $sqlBase
        )
      group by td.{$this->customFields_teamDetails['Team_Status']['column_name']}

    ";
    $statusCountsDao = CRM_Core_DAO::executeQuery($statusCountsQuery);
    while ($statusCountsDao->fetch()) {
      $statusStats[$statusCountsDao->statusValue]['count'] = $statusCountsDao->cnt;
    }

    foreach($statusStats as $statusKey => $statusStat) {
      $statistics['counts']['status_' . $statusKey] = array(
        'title' => ts("Team status is '%1'", array(1 => $statusStat['label'])),
        'value' => $statusStat['count'],
        'type' => CRM_Utils_Type::T_INT  // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
      );
    }
    return $statistics;
  }

}
