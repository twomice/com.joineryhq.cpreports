<?php
use CRM_Cpreports_ExtensionUtil as E;

class CRM_Cpreports_Form_Report_clientcensus extends CRM_Report_Form {

  protected $_autoIncludeIndexedFieldsAsOrderBys = 1;

  protected $_customGroupExtends = array('Individual', 'Contact', 'Relationship');

  protected $customGroup_clientParticipation = array();
  protected $customField_dispositionDate = array();

  protected $_customGroupGroupBy = FALSE;

  public function __construct() {
    // Get metadata for Team_details custom field group, and for 'Team status'
    // custom field in that group.
    $this->customGroup_clientParticipation = civicrm_api3('customGroup', 'getSingle', array(
      'name' => 'Participation',
    ));
    $this->customField_dispositionDate = civicrm_api3('customField', 'getSingle', array(
      'custom_group_id' => $this->customGroup_clientParticipation['id'],
      'name' => 'Disposition_Date',
    ));

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
      'civicrm_contact_indiv' => array(
        'dao' => 'CRM_Contact_DAO_Contact',
        'fields' => array(
          'sort_name' => array(
            'title' => E::ts('Contact Name'),
            'required' => TRUE,
            'default' => TRUE,
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
          'id' => array(
            'no_display' => TRUE,
            'required' => TRUE,
          ),
        ),
        'filters' => array(
          'sort_name' => array(
            'title' => E::ts('Contact Name'),
            'operator' => 'like',
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
            'default' => FALSE,
            'grouping' => 'team-fields',
          ),
          'nick_name' => array(
            'title' => E::ts('Team Nickname'),
            'required' => FALSE,
            'default' => FALSE,
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
        'order_bys' => array(
          'organization_name' => array(
            'title' => E::ts('Team Name'),
          ),
          'nick_name' => array(
            'title' => E::ts('Team Nickname'),
            'default' => TRUE,
            'default_is_section' => TRUE,
          ),
        ),
        'grouping' => 'contact-fields',
      ),
      'civicrm_email' => array(
        'dao' => 'CRM_Core_DAO_Email',
        'fields' => array('email' => NULL),
        'grouping' => 'contact-fields',
      ),
      'civicrm_phone' => array(
        'dao' => 'CRM_Core_DAO_Phone',
        'fields' => array(
          'phone' => array(
            'dbAlias'  => "GROUP_CONCAT(DISTINCT CONCAT(lt.display_name, ' ', pt.label, ': ', phone_civireport.phone) ORDER BY phone_civireport.is_primary DESC SEPARATOR'<br \>')",
          ),
        ),
        'grouping' => 'contact-fields',
      ),
    //      'custom_client_participation' => array(
    //        'alias' => $this->customGroup_clientParticipation['table_name'],
    //        'fields' => array(
    //          $customField_dispositionDate['column_name'] => array(
    //            'title' => E::ts('Team status'),
    //          ),
    //        ),
    //        'filters' => array(
    //          $customField_dispositionDate['column_name'] => array(
    //            'title' => E::ts('Team status'),
    //            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
    //            'options' => CRM_Core_BAO_OptionValue::getOptionValuesAssocArray($customField_dispositionDate['option_group_id']),
    //            'type' => CRM_Utils_Type::T_STRING,
    //          ),
    //        ),
    //        'grouping' => 'team-fields',
    //      ),
      'civicrm_relationship' => array(
        'fields' => array(
          'start_date' => array(
            'title' => E::ts('Start Date'),
            'default' => TRUE,
          ),
          'end_date' => array(
            'title' => E::ts('End Date'),
          ),
        ),
        'grouping' => 'relationship-fields',
      ),
    );
    $this->_groupFilter = TRUE;
    $this->_tagFilter = TRUE;
    $addressOptions = array(
      'fields_excluded' => array(
        'is_primary',
        'name',
        'street_unit',
        'street_number',
        'street_name',
        'county_id',
        'id',
        'location_type_id',
        'country_id',
        'postal_code_suffix',
      ),
      'filters' => array(
        'address_street_address',
      ),
    );
    $this->_columns += $this->getAddressColumns($addressOptions);

    // Remove some unneeded address filters. They clutter the space. Tried but
    // failed finding options to put in $addressOptions to prevent them from
    // being added in the first place.
    unset($this->_columns['civicrm_address']['filters']['address_street_number']);
    unset($this->_columns['civicrm_address']['filters']['address_street_name']);
    unset($this->_columns['civicrm_address']['filters']['address_postal_code_suffix']);
    unset($this->_columns['civicrm_address']['filters']['address_county_id']);

    parent::__construct();

    // Removed this filter, because we'll force it to "is null".
    unset($this->_columns['civicrm_value_participation_6']['filters']["custom_{$this->customField_dispositionDate['id']}"]);

  }

  public function from() {
    $this->_aliases['civicrm_contact'] = $this->_aliases['civicrm_contact_indiv'];

    $this->_from = "
      FROM  civicrm_contact {$this->_aliases['civicrm_contact_indiv']} {$this->_aclFrom}
        INNER JOIN civicrm_relationship {$this->_aliases['civicrm_relationship']}
          ON {$this->_aliases['civicrm_relationship']}.contact_id_b  = {$this->_aliases['civicrm_contact_indiv']}.id
        INNER JOIN civicrm_relationship_type rt
          ON {$this->_aliases['civicrm_relationship']}.relationship_type_id = rt.id
          AND rt.name_a_b = 'Has_team_client'
        INNER JOIN civicrm_contact {$this->_aliases['civicrm_contact_team']}
          ON {$this->_aliases['civicrm_contact_team']}.id = {$this->_aliases['civicrm_relationship']}.contact_id_a
        LEFT JOIN {$this->customGroup_clientParticipation['table_name']} custom_client_participation
          ON custom_client_participation.entity_id = {$this->_aliases['civicrm_contact_indiv']}.id
    ";

    //used when address field is selected
    if ($this->isTableSelected('civicrm_address')) {
      $this->_from .= "
        LEFT JOIN civicrm_address {$this->_aliases['civicrm_address']}
          ON {$this->_aliases['civicrm_contact_indiv']}.id = {$this->_aliases['civicrm_address']}.contact_id
          AND {$this->_aliases['civicrm_address']}.is_primary = 1
      ";
    }
    //used when email field is selected
    if ($this->isTableSelected('civicrm_email')) {
      $this->_from .= "
        LEFT JOIN civicrm_email {$this->_aliases['civicrm_email']}
          ON {$this->_aliases['civicrm_contact_indiv']}.id = {$this->_aliases['civicrm_email']}.contact_id
          AND {$this->_aliases['civicrm_email']}.is_primary = 1
      ";
    }
    //used when phone field is selected
    if ($this->isTableSelected('civicrm_phone')) {
      $this->_from .= "
        LEFT JOIN civicrm_phone {$this->_aliases['civicrm_phone']}
          ON {$this->_aliases['civicrm_contact_indiv']}.id = {$this->_aliases['civicrm_phone']}.contact_id

        left join civicrm_location_type lt on lt.id = phone_civireport.location_type_id
        left join civicrm_option_value pt on pt.value = phone_civireport.phone_type_id and pt.option_group_id = 35
      ";
    }
  }

  public function groupBy() {
    if ($this->isTableSelected('civicrm_phone')) {
      $this->_groupBy = " GROUP BY {$this->_aliases['civicrm_contact_indiv']}.id, {$this->_aliases['civicrm_contact_team']}.id";
    }
  }

  public function storeWhereHavingClauseArray() {
    parent::storeWhereHavingClauseArray();

    // Insist that client disposition date is null
    $this->_whereClauses[] = "( custom_client_participation.{$this->customField_dispositionDate['column_name']} IS NULL )";
  }

  public function beginPostProcess() {
    parent::beginPostProcess();

    // get the acl clauses built before we assemble the query
    $this->buildACLClause($this->_aliases['civicrm_contact_indiv']);
  }

  public function alterDisplay(&$rows) {
    // custom code to alter rows
    $entryFound = FALSE;
    foreach ($rows as $rowNum => $row) {

      if (array_key_exists('civicrm_address_state_province_id', $row)) {
        if ($value = $row['civicrm_address_state_province_id']) {
          $rows[$rowNum]['civicrm_address_state_province_id'] = CRM_Core_PseudoConstant::stateProvince($value, FALSE);
        }
        $entryFound = TRUE;
      }

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
    // distinct individual contact_ids
    $sql = "select {$this->_aliases['civicrm_contact_indiv']}.id {$this->_from} {$this->_where} {$this->_groupBy} {$this->_having}";
    $indivCount = CRM_Core_DAO::singleValueQuery("select count(distinct t.id) as cnt from ($sql) t");

    $statistics['counts']['individuals'] = array(
      'title' => E::ts("Total Client(s)"),
      'value' => $indivCount,
      // e.g. CRM_Utils_Type::T_STRING, defaul.t seems to be integer
      'type' => CRM_Utils_Type::T_INT,
    );
    return $statistics;
  }

}
