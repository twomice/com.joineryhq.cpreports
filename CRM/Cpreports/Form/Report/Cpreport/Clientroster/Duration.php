<?php

use CRM_Cpreports_ExtensionUtil as E;

class CRM_Cpreports_Form_Report_Cpreport_Clientroster_Duration extends CRM_Cpreports_Form_Report_Cpreport_Clientroster {

  function __construct() {
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
    return $statistics;
  }

}
