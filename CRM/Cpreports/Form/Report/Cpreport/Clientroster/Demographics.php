<?php

use CRM_Cpreports_ExtensionUtil as E;

class CRM_Cpreports_Form_Report_Cpreport_Clientroster_Demographics extends CRM_Cpreports_Form_Report_Cpreport_Clientroster {

  function __construct() {
    parent::__construct();
  }

  public function statistics(&$rows) {
    $statistics = parent::statistics($rows);
    $this->_addDemographicStats($statistics);
    return $statistics;
  }

}
