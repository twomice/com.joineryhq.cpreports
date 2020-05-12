<?php
use CRM_Cpreports_ExtensionUtil as E;

class CRM_Cpreports_Form_Report_Cpreport extends CRM_Report_Form {

  protected $_serviceDateTo;
  protected $_serviceDateFrom;
  
  function _addServiceDatesFilter() {
    $this->_columns['civicrm_relationship']['filters']['service_dates'] = array(
      'title' => E::ts('Service dates'),
      'pseudofield' => TRUE,
      'type' => 	CRM_Utils_Type::T_DATE,
      'operatorType' => CRM_Report_Form::OP_DATE,
    );
  }
  
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
}

