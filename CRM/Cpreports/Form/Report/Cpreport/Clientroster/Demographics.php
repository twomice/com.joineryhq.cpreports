<?php
use CRM_Cpreports_ExtensionUtil as E;

class CRM_Cpreports_Form_Report_Cpreport_Clientroster_Demographics extends CRM_Cpreports_Form_Report_Cpreport_Clientroster {

  function __construct() {
    parent::__construct();
    $this->_addFilterServiceDates();

    $this->_columns['civicrm_relationship']['filters']['end_date'] = array(
      'title' => E::ts('End date'),
      'type' => 	CRM_Utils_Type::T_DATE,
      'operatorType' => CRM_Report_Form::OP_DATE,
    );
  }

  public function statistics(&$rows) {
    $statistics = parent::statistics($rows);
    // Get an abbreviated form of the report SQL, and use it to get a count of
    // distinct team contact_ids
    $sqlBase = " {$this->_from} {$this->_where} {$this->_groupBy} {$this->_having}";

    // Total distinct client contacts.
    $query = "SELECT COUNT(DISTINCT {$this->_aliases['civicrm_contact_indiv']}.id) {$sqlBase}";
    $statistics['counts']['total_days'] = array(
      'title' => ts("Total  distinct clients"),
      'value' => CRM_Core_DAO::singleValueQuery($query),
      'type' => CRM_Utils_Type::T_INT  // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
    );

    // Spacer for titles on stats that come under a header.
    $indentPrefix = '&nbsp; &nbsp; ';

    // Section header
      $statistics['counts']['transition_summary_blank'] = array(
      'title' => E::ts('Client Transition Summary'),
      'value' => '',
      'type' => CRM_Utils_Type::T_STRING // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
    );
    $this->_addStatisticActiveStart($statistics, $sqlBase, $indentPrefix);
    $this->_addStatisticEndedDuring($statistics, $sqlBase, $indentPrefix);
    $this->_addStatisticStartedDuring($statistics, $sqlBase, $indentPrefix);
    $this->_addStatisticActiveEnd($statistics, $sqlBase, $indentPrefix);

    // Show disposition stats only if 'disposition' field is displayed
    // (Because then we can be sure the correct custom_value table is joined
    // in the SQL, and we need that for calculations).
    $customFieldId_disposition = CRM_Core_BAO_CustomField::getCustomFieldID('Disposition', 'Participation');
    if (isset($this->_params['fields']["custom_{$customFieldId_disposition}"])) {
      $dispositionTotal = 0;
      // Section header
        $statistics['counts']['disposition_blank'] = array(
        'title' => E::ts('Client Disposition'),
        'value' => '',
        'type' => CRM_Utils_Type::T_STRING // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
      );

      $customField_disposition = civicrm_api3('customField', 'getSingle', [
        'sequential' => 1,
        'id' => $customFieldId_disposition,
        'api.CustomGroup.get' => [],
      ]);
      $customFieldTableName = $customField_disposition['api.CustomGroup.get']['values'][0]['table_name'];
      $customFieldColumnName = $customField_disposition['column_name'];

      // Get all the options for this custom field, so we can list them out.
      $dispositionOptions = CRM_Contact_BAO_Contact::buildOptions('custom_' . $customFieldId_disposition);
      // Cycle through all options, one stat for each.
      foreach ($dispositionOptions as $optionValue => $optionLabel) {
        $query = "SELECT COUNT(DISTINCT {$this->_aliases['civicrm_contact_indiv']}.id) $sqlBase AND {$this->_aliases[$customFieldTableName]}.{$customFieldColumnName} = %1";
        $queryParams = [
          1 => [$optionValue, 'String']
        ];
        $statValue = CRM_Core_DAO::singleValueQuery($query, $queryParams);
        $dispositionTotal += $statValue;
        $statistics['counts']["disposition-{$optionValue}"] = array(
          'title' => ts("{$indentPrefix}{$optionLabel}"),
          'value' => $statValue,
          'type' => CRM_Utils_Type::T_INT  // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
        );
      }
      // Total.
      $statistics['counts']["disposition_total"] = array(
        'title' => $indentPrefix . E::ts("Total"),
        'value' => $dispositionTotal,
        'type' => CRM_Utils_Type::T_INT  // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
      );
    }

    // Show gender stats only if 'gender' field is displayed. (Not for any technical
    // reason, but to be consistent to 'disposition' stats behavior.)
    if (isset($this->_params['fields']['gender_id'])) {
      $genderTotal = 0;
      // Section header
        $statistics['counts']['gender_blank'] = array(
        'title' => E::ts('Client Gender'),
        'value' => '',
        'type' => CRM_Utils_Type::T_STRING // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
      );

      // Get all the options for this custom field, so we can list them out.
      $genderOptions = CRM_Contact_BAO_Contact::buildOptions('gender_id');
      // Cycle through all options, one stat for each.
      foreach ($genderOptions as $optionValue => $optionLabel) {
        $query = "SELECT COUNT(DISTINCT {$this->_aliases['civicrm_contact_indiv']}.id) $sqlBase AND {$this->_aliases['civicrm_contact_indiv']}.gender_id = %1";
        $queryParams = [
          1 => [$optionValue, 'String']
        ];
        $statValue = CRM_Core_DAO::singleValueQuery($query, $queryParams);
        $genderTotal += $statValue;
        $statistics['counts']["gender-{$optionValue}"] = array(
          'title' => ts("{$indentPrefix}{$optionLabel}"),
          'value' => $statValue,
          'type' => CRM_Utils_Type::T_INT  // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
        );
      }
      // Total.
      $statistics['counts']["gender_total"] = array(
        'title' => $indentPrefix . E::ts("Total"),
        'value' => $genderTotal,
        'type' => CRM_Utils_Type::T_INT  // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
      );

      // Also show race stats, only if 'race' field is displayed.
      // Also require that  CRM_Textselect_Util::getAllFieldOptions() exist,
      // because we need it to get the Race options.
      $customFieldId_race = CRM_Core_BAO_CustomField::getCustomFieldID('Race', 'Demographics');
      if (
        method_exists('CRM_Textselect_Util', 'getAllFieldOptions')
        && isset($this->_params['fields']["custom_{$customFieldId_race}"])
      ) {
        // Get all the options for the Race field, so we can list them out.
        $allTextselectOptions = CRM_Textselect_Util::getAllFieldOptions();
        $raceOptions = [];
        if ($raceTextselectOptions = CRM_Utils_Array::value($customFieldId_race, $allTextselectOptions)) {
          foreach ($raceTextselectOptions as $raceTextselectOption) {
            $raceOptions[$raceTextselectOption['value']] = $raceTextselectOption['label'];
          }
        }
        if (!empty($raceOptions)) {
            // Section header
            $statistics['counts']['sex-race_blank'] = array(
            'title' => E::ts('Clients by Sex and Race'),
            'value' => '',
            'type' => CRM_Utils_Type::T_STRING // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
          );

          $customField_race = civicrm_api3('customField', 'getSingle', [
            'sequential' => 1,
            'id' => $customFieldId_race,
            'api.CustomGroup.get' => [],
          ]);
          $raceCustomFieldTableName = $customField_race['api.CustomGroup.get']['values'][0]['table_name'];
          $raceCustomFieldColumnName = $customField_race['column_name'];

          // Cycle through all options, one stat for each.
          foreach ($raceOptions as $raceOptionValue => $raceOptionLabel) {
            foreach ($genderOptions as $genderOptionValue => $genderOptionLabel) {
              $query = "SELECT COUNT(DISTINCT {$this->_aliases['civicrm_contact_indiv']}.id) $sqlBase AND {$this->_aliases[$raceCustomFieldTableName]}.{$raceCustomFieldColumnName} = %1 AND {$this->_aliases['civicrm_contact_indiv']}.gender_id = %2";
              $queryParams = [
                1 => [$raceOptionLabel, 'String'],
                2 => [$genderOptionValue, 'Int']
              ];
              $statistics['counts']["sex-race-{$raceOptionValue}-{$genderOptionValue}"] = array(
                'title' => ts("{$indentPrefix}{$raceOptionLabel}, {$genderOptionLabel}"),
                'value' => CRM_Core_DAO::singleValueQuery($query, $queryParams),
                'type' => CRM_Utils_Type::T_INT  // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
              );
            }
          }
          // Finally add stats for all genders with race: none of the above
          $raceSqlPlaceholders = [];
          $i = 2;
          foreach ($raceOptions as $raceOptionLabel) {
            $raceSqlPlaceholders[] = "%{$i}";
            $queryParams[$i] = [$raceOptionLabel, 'String'];
            $i++;
          }
          foreach ($genderOptions as $genderOptionValue => $genderOptionLabel) {
            $queryParams[1] = [$genderOptionValue, 'Int'];
            $query = "
              SELECT COUNT(DISTINCT {$this->_aliases['civicrm_contact_indiv']}.id)
              $sqlBase
              AND {$this->_aliases['civicrm_contact_indiv']}.gender_id = %1
              AND {$this->_aliases[$raceCustomFieldTableName]}.{$raceCustomFieldColumnName} > ''
              AND {$this->_aliases[$raceCustomFieldTableName]}.{$raceCustomFieldColumnName} NOT IN (" . implode($raceSqlPlaceholders, ',') . ")
            ";
            $statistics['counts']["sex-race_other-{$genderOptionValue}"] = array(
              'title' => ts("{$indentPrefix}Other, {$genderOptionLabel}"),
              'value' => CRM_Core_DAO::singleValueQuery($query, $queryParams),
              'type' => CRM_Utils_Type::T_INT  // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
            );
          }
        }
      }
    }

    // Stats for Age, only if Age column is displayed.
    if (isset($this->_params['fields']["age"])) {
      // Section header
      $statistics['counts']['age_blank'] = array(
        'title' => E::ts('Client age (in years)'),
        'value' => '',
        'type' => CRM_Utils_Type::T_STRING // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
      );
      $ageSql = $this->_columns['civicrm_contact_indiv']['fields']['age']['dbAlias'];
      $ageRanges = [
        [0, 12],
        [13, 19],
        [20, 29],
        [30, 39],
        [40, 49],
        [50, 59],
        [60, 69],
        [70, 79],
        [80, 89],
        [90, NULL],
      ];
      foreach ($ageRanges as $ageRange) {
        list($min, $max) = $ageRange;
        $queryParams = [
          1 => [$min, 'Int'],
        ];
        if (!isset($max)) {
          $ageWhere = " AND $ageSql > %1 ";
          $statLabel = "$min and over";
        }
        else {
          $ageWhere = " AND $ageSql BETWEEN %1 AND %2";
          $queryParams[2] = [$max, 'Int'];
          $statLabel = "$min - $max";
        }

        $query = "
          SELECT COUNT(DISTINCT {$this->_aliases['civicrm_contact_indiv']}.id) {$sqlBase}
          $ageWhere
        ";
        $statistics['counts']['age-'. $min] = array(
          'title' => $indentPrefix . $statLabel,
          'value' => CRM_Core_DAO::singleValueQuery($query, $queryParams),
          'type' => CRM_Utils_Type::T_INT // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
        );

      }
    }

    // Provide diagnosis stats regardless of displayed columns; the table civicrm_value_health_5
    // is always included in the sql for this report.
    // Section header
    $statistics['counts']['diagnosis_blank'] = array(
      'title' => E::ts('Client diagnosis'),
      'value' => '',
      'type' => CRM_Utils_Type::T_STRING // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
    );
    $query = "
      SELECT COUNT(DISTINCT {$this->_aliases['civicrm_contact_indiv']}.id)
      $sqlBase
      AND %1 IN (
        civicrm_value_health_5.{$this->_customFields['diagnosis1']['column_name']},
        civicrm_value_health_5.{$this->_customFields['diagnosis2']['column_name']},
        civicrm_value_health_5.{$this->_customFields['diagnosis3']['column_name']}
      )
    ";
    // Get all the options for this custom field, so we can list them out.
    $diagnosisOptions = CRM_Contact_BAO_Contact::buildOptions('custom_' . $this->_customFields['diagnosis1']['id']);
    // Cycle through all options, one stat for each.
    foreach ($diagnosisOptions as $optionValue => $optionLabel) {
      $queryParams = [
        1 => [$optionValue, 'String'],
      ];
      $statistics['counts']['diagnosis-'. $optionValue] = array(
        'title' => $indentPrefix . $optionLabel,
        'value' => CRM_Core_DAO::singleValueQuery($query, $queryParams),
        'type' => CRM_Utils_Type::T_INT // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
      );
    }

    return $statistics;
  }



}
