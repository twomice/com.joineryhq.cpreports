<?php

require_once 'cpreports.civix.php';
use CRM_Cpreports_ExtensionUtil as E;

/**
 * Implements hook_civicrm_alterReportVar().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterReportVar
 */
function cpreports_civicrm_alterReportVar($varType, &$var, $reportForm) {
  if ($varType == 'columns') {
    // Insert nickname filter in certain reports.
    switch(get_class($reportForm)) {
      case 'CRM_Report_Form_Contact_Summary':
      case 'CRM_Report_Form_Contact_Detail':
        // Shorthand variable for nickname filter/field properties
        $nickNameFilterFieldProperties = array(
          'nick_name' => array(
            'name' => 'nick_name',
            'title' => ts('Nickname'),
          ),
        );
        // We'll insert this filter after the 'sort_name' filter, so first find out the correct position.
        $nickNameFilterPosition = array_search('sort_name', array_keys($var['civicrm_contact']['filters'])) + 1;
        // Insert filter at that position.
        $var['civicrm_contact']['filters'] =
          array_slice($var['civicrm_contact']['filters'], 0, $nickNameFilterPosition, true) +
          $nickNameFilterFieldProperties +
          array_slice($var['civicrm_contact']['filters'], $nickNameFilterPosition, NULL, true);

        // We'll insert field after the 'sort_name' field , so first find out the correct position.
        $nickNameFieldPosition = array_search('sort_name', array_keys($var['civicrm_contact']['fields'])) + 1;
        // Insert field at that position.
        $var['civicrm_contact']['fields'] =
          array_slice($var['civicrm_contact']['fields'], 0, $nickNameFieldPosition, true) +
          $nickNameFilterFieldProperties +
          array_slice($var['civicrm_contact']['fields'], $nickNameFieldPosition, NULL, true);

        $reportForm->addToDeveloperTab("-- Nickname field and filter added by extension com.joineryhq.cpreports");
        break;
    }
  }
}

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function cpreports_civicrm_config(&$config) {
  _cpreports_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function cpreports_civicrm_install() {
  _cpreports_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function cpreports_civicrm_enable() {
  _cpreports_civix_civicrm_enable();
}

// --- Functions below this ship commented out. Uncomment as required. ---

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_preProcess
 */
// function cpreports_civicrm_preProcess($formName, &$form) {

// } // */

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_navigationMenu
 */
// function cpreports_civicrm_navigationMenu(&$menu) {
//   _cpreports_civix_insert_navigation_menu($menu, 'Mailings', array(
//     'label' => E::ts('New subliminal message'),
//     'name' => 'mailing_subliminal_message',
//     'url' => 'civicrm/mailing/subliminal',
//     'permission' => 'access CiviMail',
//     'operator' => 'OR',
//     'separator' => 0,
//   ));
//   _cpreports_civix_navigationMenu($menu);
// } // */
