<?php

use CRM_Cpreports_ExtensionUtil as E;

class CRM_Cpreports_Form_Report_Cpreport_Clientroster_Demographics extends CRM_Cpreports_Form_Report_Cpreport_Clientroster {

  function __construct() {
    parent::__construct();
  }

  public function statistics(&$rows) {
    $statistics = parent::statistics($rows);

    // Get an abbreviated form of the report SQL, and use it to get a count of
    // distinct team contact_ids
    $sqlBase = " {$this->_from} {$this->_where} {$this->_groupBy} {$this->_having}";
    $this->_addDemographicStats($statistics, $sqlBase);
    return $statistics;
  }

}
