<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed
return [
  [
    'name' => 'CRM_Cpreports_Form_Report_Totalcontacthours',
    'entity' => 'ReportTemplate',
    'params' => [
      'version' => 3,
      'label' => 'Total Contact Hours',
      'description' => 'Total contact hours',
      'class_name' => 'CRM_Cpreports_Form_Report_Totalcontacthours',
      'report_url' => 'com.joineryhq.cpreports/totalcontacthours',
      'component' => '',
    ],
  ],
];
