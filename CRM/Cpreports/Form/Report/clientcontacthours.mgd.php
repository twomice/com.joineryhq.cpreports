<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed
return array(
  0 =>
  array(
    'name' => 'CRM_Cpreports_Form_Report_clientcontacthours',
    'entity' => 'ReportTemplate',
    'params' =>
    array(
      'version' => 3,
      'label' => 'Client Contact Hours',
      'description' => 'Client Contact Hours',
      'class_name' => 'CRM_Cpreports_Form_Report_clientcontacthours',
      'report_url' => 'cpreports/clientcontacthours',
      'component' => '',
    ),
  ),
);
