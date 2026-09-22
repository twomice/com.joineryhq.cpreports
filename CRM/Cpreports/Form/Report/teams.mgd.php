<?php

// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed
return [
  0 =>
  [
    'name' => 'CRM_Cpreports_Form_Report_teams',
    'entity' => 'ReportTemplate',
    'params' =>
    [
      'version' => 3,
      'label' => 'Team Listing',
      'description' => '',
      'class_name' => 'CRM_Cpreports_Form_Report_teams',
      'report_url' => 'cpreports/teams',
      'component' => '',
    ],
  ],
];
