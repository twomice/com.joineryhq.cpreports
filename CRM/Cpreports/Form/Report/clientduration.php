<?php
use CRM_Cpreports_ExtensionUtil as E;

class CRM_Cpreports_Form_Report_clientduration extends CRM_Report_Form {

  protected $_customGroupExtends = array('Individual','Contact','Relationship');

  protected $_customGroupGroupBy = FALSE;

  protected $_customFields = array();

  function __construct() {
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

    // Build a list of options for the diagnosis select filter (all diagnosis options)
    $customFieldId_diagnosis1 = CRM_Core_BAO_CustomField::getCustomFieldID('Diagnosis_1', 'Health');
    $customFieldId_diagnosis2 = CRM_Core_BAO_CustomField::getCustomFieldID('Diagnosis_2', 'Health');
    $customFieldId_diagnosis3 = CRM_Core_BAO_CustomField::getCustomFieldID('Diagnosis_3', 'Health');
    $diagnosisOptions = CRM_Core_BAO_CustomField::buildOptions('custom_'. $customFieldId_diagnosis1);

    $this->_customFields['diagnosis1'] = civicrm_api3('customField', 'getSingle', array('id' => $customFieldId_diagnosis1));
    $this->_customFields['diagnosis2'] = civicrm_api3('customField', 'getSingle', array('id' => $customFieldId_diagnosis2));
    $this->_customFields['diagnosis3'] = civicrm_api3('customField', 'getSingle', array('id' => $customFieldId_diagnosis3));

    $this->_columns = array(
      'civicrm_contact_indiv' => array(
        'dao' => 'CRM_Contact_DAO_Contact',
        'fields' => array(
          'sort_name' => array(
            'title' => E::ts('Contact Name'),
            'required' => TRUE,
            'default' => TRUE,
            'no_repeat' => TRUE,
          ),
          'id' => array(
            'no_display' => TRUE,
            'required' => TRUE,
          ),
          'contact_id' => array(
            'title' => E::ts('Contact ID'),
            'name' => 'id',
          ),
          'first_name' => array(
            'title' => E::ts('First Name'),
          ),
          'last_name' => array(
            'title' => E::ts('Last Name'),
          ),
          'middle_name' => array(
            'title' => E::ts('Middle Name'),
          ),
          'gender_id' => array(
            'title' => E::ts('Gender'),
          ),
          'prefix_id' => array(
            'title' => E::ts('Prefix'),
          ),
          'birth_date' => array(
            'title' => E::ts('Date of Birth'),
          ),
          'age' => array(
            'title' => E::ts('Age'),
            'dbAlias' => "TIMESTAMPDIFF(YEAR, contact_indiv_civireport.birth_date, CURDATE())",
            'default' => TRUE,
          ),
        ),
        'filters' => array(
          'sort_name' => array(
            'title' => E::ts('Contact Name'),
            'operator' => 'like',
          ),
          'diagnosis' => array(
            'title' => E::ts('Diagnosis 1, 2 or 3'),
            'pseudofield' => TRUE,
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => $diagnosisOptions,
            'type' =>	CRM_Utils_Type::T_STRING,
          ),
          'age' => array(
            'title' => E::ts('Age'),
            'dbAlias' => "TIMESTAMPDIFF(YEAR, contact_indiv_civireport.birth_date, CURDATE())",
            'type' =>	CRM_Utils_Type::T_INT,
          ),
        ),
        'order_bys' => array(
          'sort_name' => array(
            'title' => E::ts('Contact Name'),
          ),
        ),
        'grouping' => 'contact-fields',
      ),
      'civicrm_contact_team' => array(
        'dao' => 'CRM_Contact_DAO_Contact',
        'fields' => array(
          'organization_name' => array(
            'title' => E::ts('Team Name'),
            'required' => FALSE,
            'default' => TRUE,
            'grouping' => 'team-fields',
          ),
          'nick_name' => array(
            'title' => E::ts('Team Nickname'),
            'required' => FALSE,
            'default' => TRUE,
            'grouping' => 'team-fields',
          ),
          'id' => array(
            'no_display' => TRUE,
            'required' => TRUE,
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
            'dbAlias' => 'contact_team_civireport.nick_name',
            'operator' => 'like',
            'type' =>	CRM_Utils_Type::T_STRING,
          ),
          'nick_name_select' => array(
            'title' => E::ts('Team Nickname'),
            'dbAlias' => 'contact_team_civireport.nick_name',
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => $nickNameOptions,
            'type' =>	CRM_Utils_Type::T_STRING,
          ),
        ),
        'order_bys' => array(
          'organization_name' => array(
            'title' => E::ts('Team Name'),
          ),
        ),
        'grouping' => 'contact-fields',
      ),
      'civicrm_relationship' => array(
        'fields' => array(
          'start_date' => array(
            'title' => E::ts('Start Date'),
            'default' => TRUE,
          ),
          'end_date' => array(
            'title' => E::ts('End Date'),
            'default' => TRUE,
          ),
          'days_active' => array(
            'title' => E::ts('Days Active'),
            'dbAlias' => 'IF (start_date IS NOT NULL, DATEDIFF(IFNULL(end_date, NOW()), start_date), "")',
            'default' => TRUE,
          ),
        ),
        'filters' => array(
          'end_date' => array(
            'title' => E::ts('End Date'),
            'type' => 	CRM_Utils_Type::T_DATE,
            'operatorType' => CRM_Report_Form::OP_DATE,
          ),
        ),
        'grouping' => 'relationship-fields',
      ),
    );
    $this->_groupFilter = TRUE;
    $this->_tagFilter = TRUE;

    parent::__construct();
  }

  function from() {
    $this->_aliases['civicrm_contact'] = $this->_aliases['civicrm_contact_indiv'];
    
    $this->_from = "
      FROM  civicrm_contact {$this->_aliases['civicrm_contact_indiv']}
        --  aclFrom:
        {$this->_aclFrom}
        --  ^^ aclFrom ^^
        INNER JOIN civicrm_relationship {$this->_aliases['civicrm_relationship']}
          ON {$this->_aliases['civicrm_relationship']}.contact_id_b  = {$this->_aliases['civicrm_contact_indiv']}.id
        INNER JOIN civicrm_relationship_type rt
          ON {$this->_aliases['civicrm_relationship']}.relationship_type_id = rt.id
          AND rt.name_a_b = 'Has_team_client'
        INNER JOIN civicrm_contact {$this->_aliases['civicrm_contact_team']}
          ON {$this->_aliases['civicrm_contact_team']}.id = {$this->_aliases['civicrm_relationship']}.contact_id_a
      LEFT JOIN civicrm_value_health_5
        ON civicrm_value_health_5.entity_id = {$this->_aliases['civicrm_contact_indiv']}.id
    ";

    $this->_from .= "
      -- end from()

    ";
  }

  function storeWhereHavingClauseArray() {
    parent::storeWhereHavingClauseArray();
    if ($this->_params['diagnosis_value']) {
      // Apply "any diagnosis" filter
      $diagnosisOrWheres = array();
      // Define fields for diagnosis 1, 2, and 3, each as a copy of the 'diagnosis' filter
      // field; then manually alter the 'dbAlias' property to use the relevant
      // custom field column.
      $customDiagnosisField1 =
      $customDiagnosisField2 =
      $customDiagnosisField3 =
        $this->_columns['civicrm_contact_indiv']['filters']['diagnosis'];
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
      $this->_whereClauses[] = '('. implode($andOr, $diagnosisOrWheres) . ')';
    }
  }

  function validate() {

    parent::validate();

    if (!CRM_Utils_Array::value('diagnosis_value', $this->_submitValues)) {
      $this->_errors['diagnosis_value'] = E::ts('Please specify a value for filter: "Diagnosis 1, 2, or 3"');
    }
    if (
      (
        !CRM_Utils_Array::value('end_date_from', $this->_submitValues)
        || CRM_Utils_Array::value('end_date_from', $this->_submitValues) == '0'
      )
      && !CRM_Utils_Array::value('end_date_relative', $this->_submitValues)
    ) {
      $this->_errors['end_date_relative'] = E::ts('Please specify a value for filter: "End Date"');
    }
    return (0 == count($this->_errors));
  }

  function beginPostProcess() {
    parent::beginPostProcess();

    // get the acl clauses built before we assemble the query
    $this->buildACLClause($this->_aliases['civicrm_contact_indiv']);
  }

  function alterDisplay(&$rows) {
    // custom code to alter rows
    $entryFound = FALSE;
    foreach ($rows as $rowNum => $row) {

      if (array_key_exists('civicrm_contact_indiv_gender_id', $row)) {
        if ($value = $row['civicrm_contact_indiv_gender_id']) {
          $rows[$rowNum]['civicrm_contact_indiv_gender_id'] = CRM_Core_PseudoConstant::getLabel('CRM_Contact_DAO_Contact', 'gender_id', $value);
        }
        $entryFound = TRUE;
      }

      if (array_key_exists('civicrm_contact_indiv_prefix_id', $row)) {
        if ($value = $row['civicrm_contact_indiv_prefix_id']) {
          $rows[$rowNum]['civicrm_contact_indiv_prefix_id'] = CRM_Core_PseudoConstant::getLabel('CRM_Contact_DAO_Contact', 'prefix_id', $value);
        }
        $entryFound = TRUE;
      }

      if (array_key_exists('civicrm_contact_indiv_sort_name', $row) &&
        $rows[$rowNum]['civicrm_contact_indiv_sort_name'] &&
        array_key_exists('civicrm_contact_indiv_id', $row)
      ) {
        $url = CRM_Utils_System::url("civicrm/contact/view",
          'reset=1&cid=' . $row['civicrm_contact_indiv_id'],
          $this->_absoluteUrl
        );
        $rows[$rowNum]['civicrm_contact_indiv_sort_name_link'] = $url;
        $rows[$rowNum]['civicrm_contact_indiv_sort_name_hover'] = E::ts("View Contact Summary for this Contact.");
        $entryFound = TRUE;
      }

      if (array_key_exists('civicrm_contact_team_organization_name', $row) &&
        $rows[$rowNum]['civicrm_contact_team_organization_name'] &&
        array_key_exists('civicrm_contact_team_id', $row)
      ) {
        $url = CRM_Utils_System::url("civicrm/contact/view",
          'reset=1&cid=' . $row['civicrm_contact_team_id'],
          $this->_absoluteUrl
        );
        $rows[$rowNum]['civicrm_contact_team_organization_name_link'] = $url;
        $rows[$rowNum]['civicrm_contact_team_organization_name_hover'] = E::ts("View Contact Summary for this Contact.");
        $entryFound = TRUE;
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
    $sqlBase = " {$this->_from} {$this->_where} {$this->_groupBy} {$this->_having}";

    //Total distinct clients
    $query = "select count(distinct contact_id_b) from civicrm_relationship where id IN (SELECT {$this->_aliases['civicrm_relationship']}.id {$sqlBase})";
    $statistics['counts']['total_clients'] = array(
      'title' => ts("Clients terminating during the period"),
      'value' => CRM_Core_DAO::singleValueQuery($query),
      'type' => CRM_Utils_Type::T_INT  // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
    );

    //Total composite duration of all clients (days)
    $query = "select sum({$this->_columns['civicrm_relationship']['fields']['days_active']['dbAlias']}) {$sqlBase}";
    $statistics['counts']['total_days'] = array(
      'title' => ts("Total of all client duration (days)"),
      'value' => CRM_Core_DAO::singleValueQuery($query),
      'type' => CRM_Utils_Type::T_INT  // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
    );

    //Average duration (based on all Service Providers processed
    $statistics['counts']['average_duration'] = array(
      'title' => ts("Average client duration (days)"),
      'value' => ($statistics['counts']['total_clients']['value'] ? ($statistics['counts']['total_days']['value'] / $statistics['counts']['total_clients']['value']) : 0),
      'type' => CRM_Utils_Type::T_INT  // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
    );


    return $statistics;
  }

}
