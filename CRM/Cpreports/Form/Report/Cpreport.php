<?php

use CRM_Cpreports_ExtensionUtil as E;

class CRM_Cpreports_Form_Report_Cpreport extends CRM_Report_Form {

  protected $_debug = FALSE;
  protected $_autoIncludeIndexedFieldsAsOrderBys = 1;
  protected $_serviceDateTo;
  protected $_serviceDateFrom;

  function beginPostProcess() {
    parent::beginPostProcess();

    // get the acl clauses built before we assemble the query
    $this->buildACLClause($this->_aliases['civicrm_contact_indiv']);
  }

  function storeWhereHavingClauseArray() {
    parent::storeWhereHavingClauseArray();

    // Handle 'service_dates' filter:
    // Convert service_dates 'from' and 'to' params into max start date and min end date, respectively.
    list($from, $to) = $this->getFromTo($this->_params['service_dates_relative'], $this->_params['service_dates_from'], $this->_params['service_dates_to']);
    if ($to) {
      $this->_serviceDateTo = $to;
      $this->_whereClauses[] = "( {$this->_aliases['civicrm_relationship']}.start_date <= {$this->_serviceDateTo} )";
    }
    if ($from) {
      $this->_serviceDateFrom = $from;
      $this->_whereClauses[] = "( {$this->_aliases['civicrm_relationship']}.end_date IS NULL OR {$this->_aliases['civicrm_relationship']}.end_date >= {$this->_serviceDateFrom} )";
    }
  }

  /**
   * Depending on the value of $this->_debug, either indicate that the given
   * table should be temporary, or that it should be created as a regular table
   * for later review. For regular tables, drop the table in case it exists
   * already.
   *
   * @param  <type> $table_name
   * @return string
   */
  function _debug_temp_table($table_name) {
    if ($this->_debug) {
      $query = "DROP TABLE IF EXISTS {$table_name}";
      CRM_Core_DAO::executeQuery($query);
      $temporary = '';
    }
    else {
      $temporary = 'TEMPORARY';
    }
    return $temporary;
  }

  /**
   * Add filter for service_dates to $this->_columns.
   */
  function _addFilterServiceDates() {
    $this->_columns['civicrm_relationship']['filters']['service_dates'] = array(
      'title' => E::ts('Service dates'),
      'pseudofield' => TRUE,
      'type' => CRM_Utils_Type::T_DATE,
      'operatorType' => CRM_Report_Form::OP_DATE,
    );
  }

  /**
   * Add a row for "active at start of date range" to $statistics.
   */
  function _addStatisticActiveStart(&$statistics, $sqlBase, $titlePrefix = '') {
    //Relationships active at start of analysis period
    if (empty($this->_aliases['civicrm_relationship'])) {
      return;
    }
    $activeStartWhere = "";
    if ($this->_serviceDateFrom) {
      $activeStartWhere = "start_date < {$this->_serviceDateFrom} AND ";
      $query = "select count(distinct contact_id_b) from civicrm_relationship where $activeStartWhere id IN (SELECT {$this->_aliases['civicrm_relationship']}.id {$sqlBase})";
      // dsm($query, "-- active start\n");
      $activeStartCount = CRM_Core_DAO::singleValueQuery($query);
    }
    else {
      // No "from" date means the beginning of time, when zero volunteers were active.
      $activeStartCount = 0;
      // dsm(0, 'active_start');
    }
    $statistics['counts']['active_start'] = array(
      'title' => ts("{$titlePrefix}Relationships active at start of analysis period"),
      'value' => $activeStartCount,
      'type' => CRM_Utils_Type::T_INT  // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
    );
  }

  /**
   * Add a row for "ended during date range" to $statistics.
   */
  function _addStatisticEndedDuring(&$statistics, $sqlBase, $titlePrefix = '') {
    //Relationships active at start of analysis period
    if (empty($this->_aliases['civicrm_relationship'])) {
      return;
    }
    //Relationships ended during analysis period
    if ($this->_serviceDateTo) {
      $toDateSql = "{$this->_serviceDateTo}";
    }
    else {
      $toDateSql = 'now()';
    }
    $query = "
      select count(distinct contact_id_b)
      from (
       select contact_id_b, max(ifnull(end_date, now() + interval 1 day)) as max_end_date
       from (
         select {$this->_aliases['civicrm_relationship']}.* {$sqlBase}
        ) t1
        group by contact_id_b
        having max_end_date <= $toDateSql
      ) t2
    ";
    // dsm($query, "-- ended during\n");
    $statistics['counts']['ended_during'] = array(
      'title' => ts("{$titlePrefix}Relationships ended during analysis period"),
      'value' => CRM_Core_DAO::singleValueQuery($query),
      'type' => CRM_Utils_Type::T_INT  // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
    );
  }

  /**
   * Add a row for "started during date range" to $statistics.
   */
  function _addStatisticStartedDuring(&$statistics, $sqlBase, $titlePrefix = '') {
    //Relationships active at start of analysis period
    if (empty($this->_aliases['civicrm_relationship'])) {
      return;
    }
    //Relationships begun during analysis period
    if ($this->_serviceDateFrom) {
      $query = "
        select count(distinct contact_id_b)
        from (
         select contact_id_b, min(start_date) as min_start_date
         from (
           select {$this->_aliases['civicrm_relationship']}.* {$sqlBase}
          ) t1
          group by contact_id_b
          having min_start_date >= '{$this->_serviceDateFrom}'
        ) t2
      ";
    }
    else {
      $query = "select count(distinct contact_id_b) {$sqlBase}";
    }
    // dsm($query, "-- begun during\n");
    $statistics['counts']['started_during'] = array(
      'title' => ts("{$titlePrefix}Relationships begun during analysis period"),
      'value' => CRM_Core_DAO::singleValueQuery($query),
      'type' => CRM_Utils_Type::T_INT  // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
    );
  }

  /**
   * Add a row for "active through end of date range" to $statistics.
   */
  function _addStatisticActiveEnd(&$statistics, $sqlBase, $titlePrefix = '') {
    //Relationships active at start of analysis period
    if (empty($this->_aliases['civicrm_relationship'])) {
      return;
    }
    //Relationships active at end of analysis period
    if ($this->_serviceDateTo) {
      $activeEndWhere = "(end_date IS NULL OR end_date > {$this->_serviceDateTo}) AND ";
    }
    else {
      // No "to" date means the end of time, when only volunteers with no end_date will be active
      $activeEndWhere = "(end_date IS NULL) AND ";
    }
    $query = "select count(distinct contact_id_b) from civicrm_relationship where {$activeEndWhere} id IN (SELECT {$this->_aliases['civicrm_relationship']}.id {$sqlBase})";
    // dsm($query, "-- active end\n");
    $statistics['counts']['active_end'] = array(
      'title' => ts("{$titlePrefix}Relationships active at end of analysis period"),
      'value' => CRM_Core_DAO::singleValueQuery($query),
      'type' => CRM_Utils_Type::T_INT  // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
    );
  }

  /**
   * Add many demographic-related stats to $statistics.
   */
  function _addDemographicStats(&$statistics, $sqlBase) {

    // Total distinct client contacts.
    $query = "SELECT COUNT(DISTINCT {$this->_aliases['civicrm_contact_indiv']}.id) {$sqlBase}";
    $statistics['counts']['total_clients'] = array(
      'title' => ts("Total distinct clients"),
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

      $customField_disposition = civicrm_api3(
        'customField', 'getSingle', [
        'sequential' => 1,
        'id' => $customFieldId_disposition,
        'api.CustomGroup.get' => [],
        ]
      );
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
    else {
      // Section header
      $statistics['counts']['disposition_blank'] = array(
        'title' => E::ts('Client Disposition'),
        'value' => E::ts('(Please enable the "Disposition" column to reveal these statistics.)'),
        'type' => CRM_Utils_Type::T_STRING // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
      );
    }

    // Show gender stats regardless of whether 'gender' field is displayed.
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

    // Show race stats, only if 'race' field is displayed.
    // Also require that  CRM_Textselect_Util::getAllFieldOptions() exist,
    // because we need it to get the Race options.
    $customFieldId_race = CRM_Core_BAO_CustomField::getCustomFieldID('Race', 'Demographics');
    if (method_exists('CRM_Textselect_Util', 'getAllFieldOptions') && isset($this->_params['fields']["custom_{$customFieldId_race}"])
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

        $customField_race = civicrm_api3(
          'customField', 'getSingle', [
          'sequential' => 1,
          'id' => $customFieldId_race,
          'api.CustomGroup.get' => [],
          ]
        );
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
    else {
      // Section header
      $statistics['counts']['sex-race_blank'] = array(
        'title' => E::ts('Clients by Sex and Race'),
        'value' => E::ts('(Please enable the "Race" column to reveal these statistics.)'),
        'type' => CRM_Utils_Type::T_STRING // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
      );
    }

    // Stats for Age, regardless of whether Age column is displayed.
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
      $statistics['counts']['age-' . $min] = array(
        'title' => $indentPrefix . $statLabel,
        'value' => CRM_Core_DAO::singleValueQuery($query, $queryParams),
        'type' => CRM_Utils_Type::T_INT // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
      );
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
      $statistics['counts']['diagnosis-' . $optionValue] = array(
        'title' => $indentPrefix . $optionLabel,
        'value' => CRM_Core_DAO::singleValueQuery($query, $queryParams),
        'type' => CRM_Utils_Type::T_INT // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
      );
    }
  }

}
