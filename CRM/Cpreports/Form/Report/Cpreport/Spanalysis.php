<?php
use CRM_Cpreports_ExtensionUtil as E;

class CRM_Cpreports_Form_Report_Cpreport_Spanalysis extends CRM_Cpreports_Form_Report_Cpreport {

  protected $_customGroupExtends = array('Individual','Contact','Relationship');

  protected $_customGroupGroupBy = FALSE;

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
        'grouping' => 'relationship-fields',
      ),
      'civicrm_note' => array(
        'fields' => array(
          'note' => array(
            'title' => E::ts('Note'),
            'default' => TRUE,
          ),
        ),
        'grouping' => 'relationship-fields',
      ),
    );
    $this->_addFilterServiceDates();
    $this->_groupFilter = TRUE;
    $this->_tagFilter = TRUE;

    parent::__construct();
  }

  function from() {
    $this->_aliases['civicrm_contact'] = $this->_aliases['civicrm_contact_indiv'];
    
    $this->_from = "
      FROM  civicrm_contact {$this->_aliases['civicrm_contact_indiv']} {$this->_aclFrom}
        INNER JOIN civicrm_relationship {$this->_aliases['civicrm_relationship']}
          ON {$this->_aliases['civicrm_relationship']}.contact_id_b  = {$this->_aliases['civicrm_contact_indiv']}.id
        INNER JOIN civicrm_relationship_type rt
          ON {$this->_aliases['civicrm_relationship']}.relationship_type_id = rt.id
          AND rt.name_a_b = 'Has_team_volunteer'
        INNER JOIN civicrm_contact {$this->_aliases['civicrm_contact_team']}
          ON {$this->_aliases['civicrm_contact_team']}.id = {$this->_aliases['civicrm_relationship']}.contact_id_a
    ";

    if ($this->isTableSelected('civicrm_note')) {
      $this->_from .= "
        LEFT JOIN civicrm_note {$this->_aliases['civicrm_note']}
          ON {$this->_aliases['civicrm_note']}.entity_table = 'civicrm_relationship'
            AND {$this->_aliases['civicrm_note']}.entity_id = {$this->_aliases['civicrm_relationship']}.id
      ";
    }

    $this->_from .= "
      -- end from()

    ";
  }

  function postProcess() {

    $this->beginPostProcess();

    foreach (array('relative', 'from', 'to') as $suffix) {
      $this->_params["end_date_". $suffix] = $this->_params["start_date_". $suffix] = $this->_params["service_dates_". $suffix];
    }

    // get the acl clauses built before we assemble the query
    $this->buildACLClause($this->_aliases['civicrm_contact_indiv']);
    $sql = $this->buildQuery(TRUE);

    $rows = array();
    $this->buildRows($sql, $rows);

    $this->formatDisplay($rows);
    $this->doTemplateAssignment($rows);
    $this->endPostProcess($rows);
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

    $this->_addStatisticActiveStart($statistics, $sqlBase);
    $this->_addStatisticActiveEnd($statistics, $sqlBase);
    $this->_addStatisticStartedDuring($statistics, $sqlBase);
    $this->_addStatisticEndedDuring($statistics, $sqlBase);


    //Net change in active Service Providers
    $statistics['counts']['net_change'] = array(
      'title' => ts("Net change in active Service Providers"),
      'value' => ($statistics['counts']['active_end']['value'] - $statistics['counts']['active_start']['value']),
      'type' => CRM_Utils_Type::T_INT  // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
    );

    //Total Service Providers processed (Active and Terminated)
    $query = "select count(distinct contact_id_b) from civicrm_relationship where id IN (SELECT {$this->_aliases['civicrm_relationship']}.id {$sqlBase})";
    $statistics['counts']['total_processed'] = array(
      'title' => ts("Total Service Providers processed (Active and Terminated)"),
      'value' => CRM_Core_DAO::singleValueQuery($query),
      'type' => CRM_Utils_Type::T_INT  // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
    );

    //Total composite duration of all service providers (days)
    $query = "select sum({$this->_columns['civicrm_relationship']['fields']['days_active']['dbAlias']}) {$sqlBase}";
    $statistics['counts']['total_days'] = array(
      'title' => ts("Total composite duration of all service providers (days)"),
      'value' => CRM_Core_DAO::singleValueQuery($query),
      'type' => CRM_Utils_Type::T_INT  // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
    );

    //Average duration (based on all Service Providers processed
    $statistics['counts']['average_duration'] = array(
      'title' => ts("Average duration (based on all Service Providers processed)"),
      'value' => ($statistics['counts']['total_processed']['value'] ? ($statistics['counts']['total_days']['value'] / $statistics['counts']['total_processed']['value']) : 0),
      'type' => CRM_Utils_Type::T_INT  // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
    );


    return $statistics;
  }

}
