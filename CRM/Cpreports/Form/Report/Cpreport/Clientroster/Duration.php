<?php
use CRM_Cpreports_ExtensionUtil as E;

class CRM_Cpreports_Form_Report_Cpreport_Clientroster_Duration extends CRM_Cpreports_Form_Report_Cpreport_Clientroster {

  function __construct() {
    parent::__construct();
    $this->_addServiceDatesFilter();
  }
  
  public function statistics(&$rows) {
    $statistics = parent::statistics($rows);
    // Get an abbreviated form of the report SQL, and use it to get a count of
    // distinct team contact_ids
    $sqlBase = " {$this->_from} {$this->_where} {$this->_groupBy} {$this->_having}";

    //Total distinct clients ending during this period.
    $query = "
      SELECT
        COUNT(DISTINCT contact_id_b) FROM
          civicrm_relationship
        WHERE
          end_date IS NOT NULL
          AND id IN (
            SELECT {$this->_aliases['civicrm_relationship']}.id {$sqlBase}
          )
    ";
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
