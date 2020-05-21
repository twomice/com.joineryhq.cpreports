<?php
use CRM_Cpreports_ExtensionUtil as E;

class CRM_Cpreports_Form_Report_Cpreport extends CRM_Report_Form {

  protected $_autoIncludeIndexedFieldsAsOrderBys = 1;
  protected $_serviceDateTo;
  protected $_serviceDateFrom;
  
  function storeWhereHavingClauseArray() {
    parent::storeWhereHavingClauseArray();

    // Handle 'service_dates' filter:
    // Convert service_dates 'from' and 'to' params into max start date and min end date, respectively.
    list($from, $to) = $this->getFromTo($this->_params['service_dates_relative'], $this->_params['service_dates_from'], $this->_params['service_dates_to']);
    if ($to) {
      $this->_serviceDateTo = $to;
      $this->_whereClauses[] = "( start_date <= {$this->_serviceDateTo} )";
    }
    if ($from) {
      $this->_serviceDateFrom = $from;
      $this->_whereClauses[] = "( end_date IS NULL OR end_date >= {$this->_serviceDateFrom} )";
    }
  }

  function _addFilterServiceDates() {
    $this->_columns['civicrm_relationship']['filters']['service_dates'] = array(
      'title' => E::ts('Service dates'),
      'pseudofield' => TRUE,
      'type' => 	CRM_Utils_Type::T_DATE,
      'operatorType' => CRM_Report_Form::OP_DATE,
    );
  }

  function _addStatisticActiveStart(&$statistics, $sqlBase, $titlePrefix = '') {
    //Relationships active at start of analysis period
    if (empty($this->_aliases['civicrm_relationship'])) {
      return;
    }
    $activeStartWhere = "";
    if ($this->_serviceDateFrom) {
      $activeStartWhere = "start_date < {$this->_serviceDateFrom} AND ";
      $query = "select count(distinct contact_id_b) from civicrm_relationship where $activeStartWhere id IN (SELECT {$this->_aliases['civicrm_relationship']}.id {$sqlBase})";
      // dsm($query, "-- active start\n");
      $activeStartCount = CRM_Core_DAO::singleValueQuery($query);
    }
    else {
      // No "from" date means the beginning of time, when zero volunteers were active.
      $activeStartCount = 0;
      // dsm(0, 'active_start');
    }
    $statistics['counts']['active_start'] = array(
      'title' => ts("{$titlePrefix}Relationships active at start of analysis period"),
      'value' => $activeStartCount,
      'type' => CRM_Utils_Type::T_INT  // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
    );

  }

  function _addStatisticEndedDuring(&$statistics, $sqlBase, $titlePrefix = '') {
    //Relationships active at start of analysis period
    if (empty($this->_aliases['civicrm_relationship'])) {
      return;
    }
    //Relationships ended during analysis period
    if ($this->_serviceDateTo) {
      $toDateSql = "{$this->_serviceDateTo}";
    }
    else {
      $toDateSql = 'now()';
    }
    $query = "
      select count(distinct contact_id_b)
      from (
       select contact_id_b, max(ifnull(end_date, now() + interval 1 day)) as max_end_date
       from (
         select {$this->_aliases['civicrm_relationship']}.* {$sqlBase}
        ) t1
        group by contact_id_b
        having max_end_date <= $toDateSql
      ) t2
    ";
    // dsm($query, "-- ended during\n");
    $statistics['counts']['ended_during'] = array(
      'title' => ts("{$titlePrefix}Relationships ended during analysis period"),
      'value' => CRM_Core_DAO::singleValueQuery($query),
      'type' => CRM_Utils_Type::T_INT  // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
    );
  }

  function _addStatisticStartedDuring(&$statistics, $sqlBase, $titlePrefix = '') {
    //Relationships active at start of analysis period
    if (empty($this->_aliases['civicrm_relationship'])) {
      return;
    }
    //Relationships begun during analysis period
    if ($this->_serviceDateFrom) {
      $query = "
        select count(distinct contact_id_b)
        from (
         select contact_id_b, min(start_date) as min_start_date
         from (
           select {$this->_aliases['civicrm_relationship']}.* {$sqlBase}
          ) t1
          group by contact_id_b
          having min_start_date >= '{$this->_serviceDateFrom}'
        ) t2
      ";
    }
    else {
      $query = "select count(distinct contact_id_b) {$sqlBase}";
    }
    // dsm($query, "-- begun during\n");
    $statistics['counts']['started_during'] = array(
      'title' => ts("{$titlePrefix}Relationships begun during analysis period"),
      'value' => CRM_Core_DAO::singleValueQuery($query),
      'type' => CRM_Utils_Type::T_INT  // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
    );
  }

  function _addStatisticActiveEnd(&$statistics, $sqlBase, $titlePrefix = '') {
    //Relationships active at start of analysis period
    if (empty($this->_aliases['civicrm_relationship'])) {
      return;
    }
    //Relationships active at end of analysis period
    if ($this->_serviceDateTo) {
      $activeEndWhere = "(end_date IS NULL OR end_date > {$this->_serviceDateTo}) AND ";
    }
    else {
      // No "to" date means the end of time, when only volunteers with no end_date will be active
      $activeEndWhere = "(end_date IS NULL) AND ";
    }
    $query = "select count(distinct contact_id_b) from civicrm_relationship where {$activeEndWhere} id IN (SELECT {$this->_aliases['civicrm_relationship']}.id {$sqlBase})";
    // dsm($query, "-- active end\n");
    $statistics['counts']['active_end'] = array(
      'title' => ts("{$titlePrefix}Relationships active at end of analysis period"),
      'value' => CRM_Core_DAO::singleValueQuery($query),
      'type' => CRM_Utils_Type::T_INT  // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
    );

  }
  
}

