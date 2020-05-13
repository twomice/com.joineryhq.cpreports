<?php
use CRM_Cpreports_ExtensionUtil as E;

class CRM_Cpreports_Form_Report_Cpreport_Clientroster_Duration extends CRM_Cpreports_Form_Report_Cpreport_Clientroster {

  function __construct() {
    parent::__construct();
    $this->_addFilterServiceDates();
    $this->_columns['civicrm_relationship']['filters']['end_date'] = array(
      'title' => E::ts('End date'),
      'type' => 	CRM_Utils_Type::T_DATE,
      'operatorType' => CRM_Report_Form::OP_DATE,
    );
  }
  
  public function statistics(&$rows) {
    $statistics = parent::statistics($rows);
    // Get an abbreviated form of the report SQL, and provide it as base for statistics queries.
    $sqlBase = " {$this->_from} {$this->_where} {$this->_groupBy} {$this->_having}";

    // Set $this->_serviceDateTo and $this->_serviceDateFrom using end_date
    // filter values, so that $this->_addStatisticEndedDuring() calculates
    // based on that (that method expects to be working with service_date filter
    // values, but this report uses end_date instead.
    list($from, $to) = $this->getFromTo($this->_params['end_date_relative'], $this->_params['end_date_from'], $this->_params['end_date_to']);
    if ($to) {
      $this->_serviceDateTo = $to;
    }
    if ($from) {
      $this->_serviceDateFrom = $from;
    }

    $this->_addStatisticEndedDuring($statistics, $sqlBase);

    //Total composite duration of all clients (days)
    $query = "select sum({$this->_columns['civicrm_relationship']['fields']['days_active']['dbAlias']}) {$sqlBase}";
    $statistics['counts']['total_days'] = array(
      'title' => ts("Total of all client duration (days)"),
      'value' => CRM_Core_DAO::singleValueQuery($query),
      'type' => CRM_Utils_Type::T_INT  // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
    );

    //Average duration (based on all Service Providers processed
    $avgValue = ($statistics['counts']['ended_during']['value'] ? ($statistics['counts']['total_days']['value'] / $statistics['counts']['ended_during']['value']) : 'N/A');
    $statistics['counts']['average_duration'] = array(
      'title' => ts("Average client duration (days)"),
      'value' => $avgValue,
      'type' => (is_numeric($avgValue) ? CRM_Utils_Type::T_INT : CRM_Utils_Type::T_STRING), // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
    );

    return $statistics;
  }

}
