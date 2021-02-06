<?php
use CRM_Cpreports_ExtensionUtil as E;

class CRM_Cpreports_Form_Report_Cpreport_Clientroster_Duration extends CRM_Cpreports_Form_Report_Cpreport_Clientroster {

  public function __construct() {
    parent::__construct();
  }

  public function statistics(&$rows) {
    $statistics = parent::statistics($rows);

    // Set $this->_serviceDateTo and $this->_serviceDateFrom using end_date
    // filter values, so that $this->_addStatisticEndedDuring() calculates
    // based on that (that method expects to be working with service_date filter
    // values, but this report uses end_date instead.
    list($from, $to) = $this->getFromTo($this->_params['end_date_relative'] ?? NULL, $this->_params['end_date_from'] ?? NULL, $this->_params['end_date_to'] ?? NULL);
    if ($to) {
      $this->_serviceDateTo = $to;
    }
    if ($from) {
      $this->_serviceDateFrom = $from;
    }
    $this->_addStatisticParticipationEndedDuring($statistics);

    $sqlBase = $this->_getSqlBase();

    //Total composite duration of all clients (days)
    $query = "
      select sum({$this->_columns['alias_civicrm_value_participation_6']['fields']['days_participated']['dbAlias']})
      from (
      select contact_indiv_civireport.id as contact_id
      -- sqlbase >>>>
      {$sqlBase}
      -- <<< sqlbase
    ) t
    inner join civicrm_value_participation_6 alias_civicrm_value_participation_6_civireport ON alias_civicrm_value_participation_6_civireport.entity_id = t.contact_id
    ";
    $statistics['counts']['total_days'] = array(
      'title' => E::ts("Total of all client duration (days)"),
      'value' => CRM_Core_DAO::singleValueQuery($query),
      // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
      'type' => CRM_Utils_Type::T_INT,
    );

    //Average duration (based on all Team Clients processed)
    $totalRows = $statistics['counts']['rowsFound']['value'] ?? $statistics['counts']['rowCount']['value'];
    $avgValue = ($statistics['counts']['participation_ended_during']['value'] ? ($statistics['counts']['total_days']['value'] / $totalRows) : 'N/A (none ended during this period)');
    $statistics['counts']['average_duration'] = array(
      'title' => E::ts("Average client duration (days)"),
      'value' => $avgValue,
      // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
      'type' => (is_numeric($avgValue) ? CRM_Utils_Type::T_INT : CRM_Utils_Type::T_STRING),
    );

    return $statistics;
  }

}
