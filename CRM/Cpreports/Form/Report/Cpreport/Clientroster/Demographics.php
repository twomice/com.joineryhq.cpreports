<?php
use CRM_Cpreports_ExtensionUtil as E;

class CRM_Cpreports_Form_Report_Cpreport_Clientroster_Demographics extends CRM_Cpreports_Form_Report_Cpreport_Clientroster {

  function __construct() {
    parent::__construct();
    $this->_addServiceDatesFilter();
  }
  
  public function statistics(&$rows) {
    $statistics = parent::statistics($rows);
//    // Get an abbreviated form of the report SQL, and use it to get a count of
//    // distinct team contact_ids
//    $sqlBase = " {$this->_from} {$this->_where} {$this->_groupBy} {$this->_having}";
//
//    //Total distinct clients
//    $query = "
//      select count(distinct contact_id_b) from civicrm_relationship where id IN (SELECT {$this->_aliases['civicrm_relationship']}.id {$sqlBase})";
//    $statistics['counts']['total_clients'] = array(
//      'title' => ts("Clients terminating during the period"),
//      'value' => CRM_Core_DAO::singleValueQuery($query),
//      'type' => CRM_Utils_Type::T_INT  // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
//    );
//
//    //Total composite duration of all clients (days)
//    $query = "select sum({$this->_columns['civicrm_relationship']['fields']['days_active']['dbAlias']}) {$sqlBase}";
//    $statistics['counts']['total_days'] = array(
//      'title' => ts("Total of all client duration (days)"),
//      'value' => CRM_Core_DAO::singleValueQuery($query),
//      'type' => CRM_Utils_Type::T_INT  // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
//    );
//
//
//    // Section header
//      $statistics['counts']['average_duration-blank'] = array(
//      'title' => E::ts('Client Transition Summary'),
//      'value' => '',
//      'type' => CRM_Utils_Type::T_STRING // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
//    );
//    //Total distinct clients active at start this period.
//    $startDateWhereClause = 'start_date IS NULL';
//    $startDateParams = CRM_Cpreports_Util::getDateFromTo('start_date', $this->_params);
//    if (!empty($startDateParams['from'])) {
//      $startDateWhereClause .= " OR start_date < '{$startDateParams['from']}'";
//    }
//
//
//    $query = "
//      SELECT
//        COUNT(DISTINCT contact_id_b) FROM
//          civicrm_relationship
//        WHERE
//          ({$startDateWhereClause})
//          AND id IN (
//            SELECT {$this->_aliases['civicrm_relationship']}.id {$sqlBase}
//          )
//    ";
//            dsm($query, 'query');
//    $statistics['counts']['total_clients'] = array(
//      'title' => ts("&nbsp; &nbsp; Clients active at beginning of period"),
//      'value' => CRM_Core_DAO::singleValueQuery($query),
//      'type' => CRM_Utils_Type::T_INT  // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
//    );
//    
//    //Total distinct clients ending during this period.
//    $query = "
//      SELECT
//        COUNT(DISTINCT contact_id_b) FROM
//          civicrm_relationship
//        WHERE
//          end_date IS NOT NULL
//          AND id IN (
//            SELECT {$this->_aliases['civicrm_relationship']}.id {$sqlBase}
//          )
//    ";
//    $statistics['counts']['total_clients'] = array(
//      'title' => ts("&nbsp; &nbsp; Clients terminating during the period"),
//      'value' => CRM_Core_DAO::singleValueQuery($query),
//      'type' => CRM_Utils_Type::T_INT  // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
//    );
//
//         Clients active at beginning of period:             0
//         Clients terminated during period:                 27
//         Clients added during period:                      27
//         Clients active at end of period:                   0

//    $statistics['counts']['average_duration'] = array(
//      'title' => ts("&nbsp;&nbsp;&nbsp;&nbsp;Average client duration (days)"),
//      'value' => ($statistics['counts']['total_clients']['value'] ? ($statistics['counts']['total_days']['value'] / $statistics['counts']['total_clients']['value']) : 0),
//      'type' => CRM_Utils_Type::T_INT  // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
//    );
//
    return $statistics;
  }



}
 