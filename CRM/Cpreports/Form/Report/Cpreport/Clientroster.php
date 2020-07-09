<?php

use CRM_Cpreports_ExtensionUtil as E;

class CRM_Cpreports_Form_Report_Cpreport_Clientroster extends CRM_Cpreports_Form_Report_Cpreport {

  protected $_customGroupExtends = array('Individual', 'Contact');
  protected $_customGroupGroupBy = FALSE;
  protected $_customFields = array();

  function __construct() {
    // Build a list of options for the nick_name select filter (all existing team nicknames)
    $nickNameOptions = array();
    $dao = CRM_Core_DAO::executeQuery(
        '
      SELECT DISTINCT nick_name
      FROM civicrm_contact
      WHERE
        contact_type = "Organization"
        AND contact_sub_type LIKE "%team%"
        AND nick_name > ""
      ORDER BY nick_name
    '
    );
    while ($dao->fetch()) {
      $nickNameOptions[$dao->nick_name] = $dao->nick_name;
    }

    // Build a list of options for the diagnosis select filter (all diagnosis options)
    $customFieldId_diagnosis1 = CRM_Core_BAO_CustomField::getCustomFieldID('Diagnosis_1', 'Health');
    $customFieldId_diagnosis2 = CRM_Core_BAO_CustomField::getCustomFieldID('Diagnosis_2', 'Health');
    $customFieldId_diagnosis3 = CRM_Core_BAO_CustomField::getCustomFieldID('Diagnosis_3', 'Health');
    $diagnosisOptions = CRM_Core_BAO_CustomField::buildOptions('custom_' . $customFieldId_diagnosis1);

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
            'type' => CRM_Utils_Type::T_STRING,
          ),
          'age' => array(
            'title' => E::ts('Age'),
            'dbAlias' => "TIMESTAMPDIFF(YEAR, contact_indiv_civireport.birth_date, CURDATE())",
            'type' => CRM_Utils_Type::T_INT,
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
            'title' => E::ts('Team Name(s)'),
            'required' => FALSE,
            'default' => TRUE,
            'grouping' => 'team-fields',
            'dbAlias'  => "GROUP_CONCAT(DISTINCT contact_team_civireport.organization_name ORDER BY contact_team_civireport.organization_name DESC SEPARATOR '<BR /> ')",
          ),
          'nick_name' => array(
            'title' => E::ts('Team Nickname(s)'),
            'required' => FALSE,
            'default' => TRUE,
            'grouping' => 'team-fields',
            'dbAlias'  => "GROUP_CONCAT(DISTINCT contact_team_civireport.nick_name ORDER BY contact_team_civireport.nick_name DESC SEPARATOR '<BR /> ')",
          ),
        ),
        'filters' => array(
          'organization_name' => array(
            'title' => E::ts('Team Name'),
            'operator' => 'like',
            'type' => CRM_Utils_Type::T_STRING,
          ),
          'nick_name_like' => array(
            'title' => E::ts('Team Nickname'),
            'dbAlias' => 'contact_team_civireport.nick_name',
            'operator' => 'like',
            'type' => CRM_Utils_Type::T_STRING,
          ),
          'nick_name_select' => array(
            'title' => E::ts('Team Nickname'),
            'dbAlias' => 'contact_team_civireport.nick_name',
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => $nickNameOptions,
            'type' => CRM_Utils_Type::T_STRING,
          ),
        ),
        'grouping' => 'contact-fields',
      ),
      'civicrm_address' => array(
        'fields' => array(
          'city' => array(
            'title' => E::ts('City'),
          ),
          'county_id' => array(
            'title' => E::ts('County'),
            'alter_display' => 'alterCountyID',
          ),
        ),
        'filters' => array(
          // This can't be called 'county_id'. If it is, CRM_Report_Form will
          // create a chain-select filter with ALL counties, i.e., if we name  it
          // that then we can't control the available options.
          // So we name it 'the_county_id' and use the `name` parameter to make
          // civireport aware of the correct column to use; this doesn't
          // trigger CRM_Report_Form's "too smart for you" auto-creation of options.
          'the_county_id' => array(
            'name' => 'county_id',
            'title' => E::ts('County'),
            'type' => CRM_Utils_Type::T_INT,
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Core_BAO_Address::buildOptions('county_id', NULL, ['state_province_id' => 1042]),
          ),
        ),
        'grouping' => 'address-fields',
      ),
    );
    $this->_groupFilter = TRUE;
    $this->_tagFilter = TRUE;
    $this->_addFilterParticipationDates();

    parent::__construct();

    $this->_columns['civicrm_value_participation_6']['alias'] = 'alias_civicrm_value_participation_6';
    $this->_columns['civicrm_value_participation_6']['fields']['days_participated'] = [
      'title' => E::ts('Days Participated'),
      'dbAlias' => 'IF (alias_civicrm_value_participation_6_civireport.service_began_3 IS NOT NULL, DATEDIFF(IFNULL(alias_civicrm_value_participation_6_civireport.disposition_date_46, NOW()), alias_civicrm_value_participation_6_civireport.service_began_3), "")',
      'default' => FALSE,
    ];

  }

  function from() {
    $this->_aliases['civicrm_contact'] = $this->_aliases['civicrm_contact_indiv'];

    $this->_from = "
      FROM  civicrm_contact {$this->_aliases['civicrm_contact_indiv']}
        --  aclFrom:
        {$this->_aclFrom}
        --  ^^ aclFrom ^^
      LEFT JOIN civicrm_value_health_5
        ON civicrm_value_health_5.entity_id = {$this->_aliases['civicrm_contact_indiv']}.id
    ";
    if ($this->isTableSelected('civicrm_value_participation_6')) {
      $this->_from .= "
        LEFT JOIN civicrm_value_participation_6 {$this->_aliases['civicrm_value_participation_6']}
          ON {$this->_aliases['civicrm_value_participation_6']}.entity_id = {$this->_aliases['civicrm_contact_indiv']}.id
      ";
    }
    if ($this->isTableSelected('civicrm_contact_team')) {
      $this->_from .= "
        LEFT JOIN civicrm_relationship r
          ON r.contact_id_b  = {$this->_aliases['civicrm_contact_indiv']}.id AND r.relationship_type_id = 18
        LEFT JOIN civicrm_contact {$this->_aliases['civicrm_contact_team']}
          ON {$this->_aliases['civicrm_contact_team']}.id = r.contact_id_a
      ";
    }
    if ($this->isTableSelected('civicrm_address')) {
      $this->_from .= "
        LEFT JOIN civicrm_address {$this->_aliases['civicrm_address']}
          ON {$this->_aliases['civicrm_address']}.is_primary AND {$this->_aliases['civicrm_address']}.contact_id = {$this->_aliases['civicrm_contact_indiv']}.id
      ";
    }
    $this->_addParticipationDatesFrom('civicrm_contact_indiv');
    $this->_from .= "
      -- end from()

    ";
  }

  function groupBy() {
    if ($this->isTableSelected('civicrm_contact_team')) {
      $this->_groupBy = " GROUP BY {$this->_aliases['civicrm_contact_indiv']}.id";
    }
  }


  function storeWhereHavingClauseArray() {
    parent::storeWhereHavingClauseArray();

    // Ensure we only return individuals
    $this->_whereClauses[] = "{$this->_aliases['civicrm_contact_indiv']}.contact_type = 'Individual'";

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
      $this->_whereClauses[] = '(' . implode($andOr, $diagnosisOrWheres) . ')';
    }
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

      if (!$entryFound) {
        break;
      }
    }
  }

}
