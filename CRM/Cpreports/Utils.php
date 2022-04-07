<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
use CRM_Cpreports_ExtensionUtil as E;

/**
 * Description of Utils
 *
 * @author as
 */
class CRM_Cpreports_Utils {

  //put your code here
  public static function getAddressColumns() {
    return array(
      'civicrm_address' => array(
        'filters' => array(
          'address_street_address' => array(
            'title' => E::ts('Street Address'),
            'name' => 'street_address',
          ),
          'address_city' => array(
            'title' => E::ts('City'),
            'name' => 'city',
          ),
          'address_postal_code' => array(
            'title' => E::ts('Postal Code'),
            'name' => 'postal_code',
          ),
          // This can't be called 'county_id'. If it is, CRM_Report_Form will
          // create a chain-select filter with ALL counties, i.e., if we name  it
          // that then we can't control the available options.
          // So we name it 'the_county_id' and use the `name` parameter to make
          // civireport aware of the correct column to use; this doesn't
          // trigger CRM_Report_Form's "too smart for you" auto-creation of options.
          'the_county_id' => array(
            'name' => 'county_id',
            'title' => E::ts('County'),
            'type' => CRM_Utils_Type::T_INT,
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Core_BAO_Address::buildOptions('county_id', NULL, ['state_province_id' => 1042]),
          ),
        ),
        'dao' => 'CRM_Core_DAO_Address',
        'alias' => 'address',
        'fields' => array(
          'address_street_address' => array(
            'title' => E::ts('Street Address'),
            'name' => 'street_address',
          ),
          'address_supplemental_address_1' => array(
            'title' => E::ts('Supplementary Address Field 1'),
            'name' => 'supplemental_address_1',
          ),
          'address_city' => array(
            'title' => E::ts('City'),
            'name' => 'city',
            'operator' => 'like',
          ),
          'address_postal_code' => array(
            'title' => E::ts('Postal Code'),
            'name' => 'postal_code',
          ),
          'address_county_id' => array(
            'title' => E::ts('County'),
            'name' => 'county_id',
          ),
          'address_state_province_id' => array(
            'title' => E::ts('State/Province'),
            'name' => 'state_province_id',
          ),
        ),
      ),
    );
  }
  
  public static function alterDisplayAddress(&$rows) {
    // custom code to alter rows
    $entryFound = FALSE;
    foreach ($rows as $rowNum => $row) {

      if (array_key_exists('civicrm_address_address_state_province_id', $row)) {
        if ($value = $row['civicrm_address_address_state_province_id']) {
          $rows[$rowNum]['civicrm_address_address_state_province_id'] = CRM_Core_PseudoConstant::stateProvince($value, FALSE);
        }
        $entryFound = TRUE;
      }

      if (array_key_exists('civicrm_address_address_county_id', $row)) {
        if ($value = $row['civicrm_address_address_county_id']) {
          $rows[$rowNum]['civicrm_address_address_county_id'] = CRM_Core_PseudoConstant::county($value, FALSE);
        }
        $entryFound = TRUE;
      }
      
      if (!$entryFound) {
        break;
      }
    }
  }

}
