<?php

use CRM_Cpreports_ExtensionUtil as E;

class CRM_Cpreports_Form_Report_Cpreport_Clientcarepartners extends CRM_Cpreports_Form_Report_Cpreport {

  /**
   * @inheritdoc
   */
  protected $_useFilterParticipationDates = TRUE;

  protected $_customGroupExtends = array('Individual', 'Contact', 'Relationship');
  protected $_customGroupGroupBy = FALSE;
  protected $_customFields = array();
  protected $_diagnosisOptions = array();
  protected $_carepartnerNameOptions = array();
  protected $_relationshipTypeLabelOptions = array();
  protected $_tempTableName = 'TEMP_ClientCarepartners';

  public function __construct() {
    // Build a list of options for the nick_name select filter (all existing team nicknames)
    $nickNameOptions = array();
    $dao = CRM_Core_DAO::executeQuery('
      SELECT DISTINCT nick_name
      FROM civicrm_contact
      WHERE
        contact_type = "Organization"
        AND contact_sub_type LIKE "%team%"
        AND nick_name > ""
      ORDER BY nick_name
    ');
    while ($dao->fetch()) {
      $nickNameOptions[$dao->nick_name] = $dao->nick_name;
    }

    // Build a list of options for the diagnosis select filter (all diagnosis options)
    $customFieldId_diagnosis1 = CRM_Core_BAO_CustomField::getCustomFieldID('Diagnosis_1', 'Health');
    $customFieldId_diagnosis2 = CRM_Core_BAO_CustomField::getCustomFieldID('Diagnosis_2', 'Health');
    $customFieldId_diagnosis3 = CRM_Core_BAO_CustomField::getCustomFieldID('Diagnosis_3', 'Health');
    $this->_diagnosisOptions = CRM_Core_BAO_CustomField::buildOptions('custom_' . $customFieldId_diagnosis1);

    $this->_customFields['diagnosis1'] = civicrm_api3('customField', 'getSingle', array('id' => $customFieldId_diagnosis1));
    $this->_customFields['diagnosis2'] = civicrm_api3('customField', 'getSingle', array('id' => $customFieldId_diagnosis2));
    $this->_customFields['diagnosis3'] = civicrm_api3('customField', 'getSingle', array('id' => $customFieldId_diagnosis3));

    $this->_columns = array(
      'civicrm_contact_indiv' => array(
        'dao' => 'CRM_Contact_DAO_Contact',
        'fields' => array(
          'sort_name' => array(
            'title' => E::ts('Contact Name'),
            'required' => TRUE,
            'default' => TRUE,
          ),
          'id' => array(
            'no_display' => TRUE,
            'required' => TRUE,
          ),
          'contact_id' => array(
            'title' => E::ts('Contact ID'),
            'name' => 'id',
          ),
          'first_name' => array(
            'title' => E::ts('First Name'),
          ),
          'last_name' => array(
            'title' => E::ts('Last Name'),
          ),
          'middle_name' => array(
            'title' => E::ts('Middle Name'),
          ),
          'gender_id' => array(
            'title' => E::ts('Gender'),
          ),
          'prefix_id' => array(
            'title' => E::ts('Prefix'),
          ),
          'birth_date' => array(
            'title' => E::ts('Date of Birth'),
          ),
          'age' => array(
            'title' => E::ts('Age'),
            'dbAlias' => "TIMESTAMPDIFF(YEAR, contact_indiv_civireport.birth_date, CURDATE())",
            'default' => TRUE,
          ),
        ),
        'filters' => array(
          'sort_name' => array(
            'title' => E::ts('Contact Name'),
            'operator' => 'like',
          ),
          'diagnosis' => array(
            'title' => E::ts('Diagnosis 1, 2 or 3'),
            'pseudofield' => TRUE,
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => $this->_diagnosisOptions,
            'type' => CRM_Utils_Type::T_STRING,
          ),
          'age' => array(
            'title' => E::ts('Age'),
            'dbAlias' => "TIMESTAMPDIFF(YEAR, contact_indiv_civireport.birth_date, CURDATE())",
            'type' => CRM_Utils_Type::T_INT,
          ),
        ),
        'order_bys' => array(
          'sort_name' => array(
            'title' => E::ts('Contact Name'),
          ),
        ),
        'grouping' => 'contact-fields',
      ),
      $this->_tempTableName => array(
        'fields' => array(
          'carepartner_contact_id' => array(
            'title' => E::ts('CarePartner'),
          ),
          'carepartner_diagnosis_ids' => array(
            'title' => E::ts('CarePartner Diagnosis'),
          ),
          'carepartner_relationship_type_ids' => array(
            'title' => E::ts('CarePartner Relationships'),
          ),
        ),
        'grouping' => 'carepartner-fields',
      ),
    );
    $this->_groupFilter = TRUE;
    $this->_tagFilter = TRUE;

    $this->_columns += CRM_Cpreports_Utils::getAddressColumns();

    parent::__construct();
    $this->_columns['alias_civicrm_value_participation_6']['alias'] = 'alias_civicrm_value_participation_6';
    $this->_columns['alias_civicrm_value_participation_6']['grouping'] = 'civicrm_value_participation_6';
    $this->_columns['alias_civicrm_value_participation_6']['fields']['days_participated'] = [
      'title' => E::ts('Days Participated'),
      'dbAlias' => 'IF (alias_civicrm_value_participation_6_civireport.service_began_3 IS NOT NULL, DATEDIFF(IFNULL(alias_civicrm_value_participation_6_civireport.disposition_date_46, NOW()), alias_civicrm_value_participation_6_civireport.service_began_3), "")',
      'default' => TRUE,
    ];
  }

  public function from() {
    $this->_aliases['civicrm_contact'] = $this->_aliases['civicrm_contact_indiv'];

    $this->_from = "
      FROM  civicrm_contact {$this->_aliases['civicrm_contact_indiv']}
        --  aclFrom:
        {$this->_aclFrom}
        --  ^^ aclFrom ^^
      LEFT JOIN civicrm_value_health_5
        ON civicrm_value_health_5.entity_id = {$this->_aliases['civicrm_contact_indiv']}.id
    ";
    if ($this->isTableSelected('civicrm_address')) {
      $this->_from .= "
        LEFT JOIN civicrm_address {$this->_aliases['civicrm_address']}
          ON {$this->_aliases['civicrm_address']}.is_primary AND {$this->_aliases['civicrm_address']}.contact_id = {$this->_aliases['civicrm_contact_indiv']}.id
      ";
    }
    if ($this->isTableSelected($this->_tempTableName)) {
      $this->_from .= "
        LEFT JOIN {$this->_tempTableName} {$this->_aliases[$this->_tempTableName]}
          ON {$this->_aliases['civicrm_contact_indiv']}.id = {$this->_aliases[$this->_tempTableName]}.client_contact_id
      ";
    }
    $this->_addParticipationDatesFrom('civicrm_contact_indiv');
    $this->_from .= "
      -- end from()

    ";
  }

  public function _addParticipationDatesFrom($contactTableName) {
    parent::_addParticipationDatesFrom($contactTableName);
    if ($this->isTableSelected('alias_civicrm_value_participation_6')) {
      $this->_from .= "
        -- fsda
        LEFT JOIN civicrm_value_participation_6 {$this->_aliases['alias_civicrm_value_participation_6']}
          ON {$this->_aliases['alias_civicrm_value_participation_6']}.entity_id = {$this->_aliases[$contactTableName]}.id
      ";
    }
  }

  /**
   * Build order by clause, appending "_tempTableName.carepartner_sort_name"
   * if _tempTableName is in use.
   */
  public function orderBy() {
    parent::orderBy();
    if ($this->isTableSelected($this->_tempTableName)) {
      $extraOrderBy = "{$this->_aliases[$this->_tempTableName]}.carepartner_sort_name";
      if (empty($this->_orderByArray)) {
        $this->_orderBy = "ORDER BY $extraOrderBy";
      }
      else {
        $this->_orderBy .= ", $extraOrderBy";
      }
    }
  }

  public function storeWhereHavingClauseArray() {
    parent::storeWhereHavingClauseArray();
    if ($this->_params['diagnosis_value']) {
      // Apply "any diagnosis" filter
      $diagnosisOrWheres = array();
      // Define fields for diagnosis 1, 2, and 3, each as a copy of the 'diagnosis' filter
      // field; then manually alter the 'dbAlias' property to use the relevant
      // custom field column.
      $customDiagnosisField1 =
      $customDiagnosisField2 =
      $customDiagnosisField3 =
        $this->_columns['civicrm_contact_indiv']['filters']['diagnosis'];

      $customDiagnosisField1['dbAlias'] = "civicrm_value_health_5.{$this->_customFields['diagnosis1']['column_name']}";
      $customDiagnosisField2['dbAlias'] = "civicrm_value_health_5.{$this->_customFields['diagnosis2']['column_name']}";
      $customDiagnosisField3['dbAlias'] = "civicrm_value_health_5.{$this->_customFields['diagnosis3']['column_name']}";
      // Process each of these filter fields into where clauses.
      $diagnosisOrWheres[] = $this->whereClause($customDiagnosisField1, $this->_params['diagnosis_op'], $this->_params['diagnosis_value'], NULL, NULL);
      $diagnosisOrWheres[] = $this->whereClause($customDiagnosisField2, $this->_params['diagnosis_op'], $this->_params['diagnosis_value'], NULL, NULL);
      $diagnosisOrWheres[] = $this->whereClause($customDiagnosisField3, $this->_params['diagnosis_op'], $this->_params['diagnosis_value'], NULL, NULL);
      // Join these where clauses into a single clause.
      if ($this->_params['diagnosis_op'] == 'in') {
        $andOr = ' OR ';
      }
      else {
        $andOr = ' AND ';
      }
      $this->_whereClauses[] = '(' . implode($andOr, $diagnosisOrWheres) . ')';
    }
  }

  /**
   * Override parent::buildQuery in order to first build some temporary tables.
   */
  public function buildQuery($applyLimit = TRUE) {
    $buildQuerySql = parent::buildQuery($applyLimit);
    if ($this->isTableSelected($this->_tempTableName)) {
      // Uncomment this line to make use of $this->_debug features during dev/debuggging:
      $this->_debug = TRUE;
      $temporary = $this->_debug_temp_table($this->_tempTableName);
      // Create a single temp table to store flattened carepartner relationship data
      // for cient contacts that match report filters.
      $query = "CREATE $temporary TABLE {$this->_tempTableName} (
        id int(10) unsigned NOT NULL AUTO_INCREMENT,
        client_contact_id int(10) unsigned NOT NULL,
        carepartner_contact_id int(10) unsigned NOT NULL,
        carepartner_sort_name varchar(128) NOT NULL,
        carepartner_display_name varchar(128) NOT NULL,
        carepartner_diagnosis_ids varchar(255) NOT NULL,
        carepartner_relationship_type_ids varchar(255) NOT NULL,
        PRIMARY KEY (id),
        KEY client_contact_id (client_contact_id),
        KEY carepartner_contact_id (carepartner_contact_id)
      )";
      CRM_Core_DAO::executeQuery($query);

      // Create a temporary copy of the temp table, so we can use it twice in
      // the next query.
      $query = "create temporary table {$this->_tempTableName}_copy1 select * from {$this->_tempTableName}";
      CRM_Core_DAO::executeQuery($query);

      $query = "INSERT INTO {$this->_tempTableName}_copy1 (
          client_contact_id,
          carepartner_contact_id,
          carepartner_sort_name,
          carepartner_display_name,
          carepartner_diagnosis_ids,
          carepartner_relationship_type_ids
        )
        SELECT
          contact_indiv_civireport.id AS client_contact_id,
          r.contact_id_b,
          rb.sort_name,
          rb.display_name,
          concat(
            health.{$this->_customFields['diagnosis1']['column_name']}, ',',
            health.{$this->_customFields['diagnosis2']['column_name']}, ',',
            health.{$this->_customFields['diagnosis3']['column_name']}
          ),
          -- donot populate carepartner_relationship_type_ids yet, because
          -- it is just too complicated. Easier to write a separate query, below.
          0
        {$this->_from}
        INNER JOIN civicrm_relationship r ON r.relationship_type_id = 11
          AND r.contact_id_a = contact_indiv_civireport.id
            AND r.is_active
            AND (r.end_date IS NULL OR r.end_date > NOW())
        INNER JOIN civicrm_contact rb ON r.contact_id_b = rb.id
        LEFT JOIN civicrm_value_health_5 health ON health.entity_id = r.contact_id_b
        {$this->_where} {$this->_groupBy} {$this->_having} {$this->_orderBy}
        ";
      CRM_Core_DAO::executeQuery($query);
      $this->addToDeveloperTab($query);

      // Move all data from the temporary copy to the actual temp table, so it's correctly populated.
      // the next query.
      $query = "INSERT INTO {$this->_tempTableName} SELECT * FROM {$this->_tempTableName}_copy1";
      CRM_Core_DAO::executeQuery($query);

      // The actual temp table and the temporary copy are now identical; so we can use them both equally in
      // the next query (because they're temporary tables, neither can be referenced twice in one query;
      // see https://dev.mysql.com/doc/refman/8.0/en/temporary-table-problems.html)

      // Populate carepartner_relationship_type_ids.
      $query = "
        UPDATE {$this->_tempTableName} t
        INNER JOIN (
        SELECT
          t.client_contact_id, t.carepartner_contact_id,
          GROUP_CONCAT(
            -- Prepend 'b_a' or 'a_b' to the relationship type id, to indicate relationship direction.
            CONCAT(if(r.contact_id_a = t.client_contact_id, 'b_a', 'a_b'), '_', r.relationship_type_id)
            ORDER BY r.relationship_type_id
          ) AS carepartner_relationship_type_ids
        FROM {$this->_tempTableName}_copy1 t
        INNER JOIN civicrm_relationship r ON r.relationship_type_id != 11
          AND (r.contact_id_a IN (t.client_contact_id, t.carepartner_contact_id) and r.contact_id_b IN (t.client_contact_id, carepartner_contact_id))
          AND r.is_active
          AND (r.end_date IS NULL OR r.end_date > NOW())
        GROUP BY t.client_contact_id, t.carepartner_contact_id
        ) r ON r.client_contact_id = t.client_contact_id
        AND r.carepartner_contact_id = t.carepartner_contact_id
        SET t.carepartner_relationship_type_ids = r.carepartner_relationship_type_ids
      ";
      CRM_Core_DAO::executeQuery($query);
      $this->addToDeveloperTab($query);
    }

    // Now that we've built the temp tables, return the original report query SQL.
    return $buildQuerySql;
  }

  public function alterDisplay(&$rows) {

    CRM_Cpreports_Utils::alterDisplayAddress($rows);

    // custom code to alter rows
    $entryFound = FALSE;
    foreach ($rows as $rowNum => $row) {

      if (array_key_exists('civicrm_contact_indiv_gender_id', $row)) {
        if ($value = $row['civicrm_contact_indiv_gender_id']) {
          $rows[$rowNum]['civicrm_contact_indiv_gender_id'] = CRM_Core_PseudoConstant::getLabel('CRM_Contact_DAO_Contact', 'gender_id', $value);
        }
        $entryFound = TRUE;
      }

      if (array_key_exists('civicrm_contact_indiv_prefix_id', $row)) {
        if ($value = $row['civicrm_contact_indiv_prefix_id']) {
          $rows[$rowNum]['civicrm_contact_indiv_prefix_id'] = CRM_Core_PseudoConstant::getLabel('CRM_Contact_DAO_Contact', 'prefix_id', $value);
        }
        $entryFound = TRUE;
      }

      if (array_key_exists('civicrm_contact_indiv_sort_name', $row)
        && $rows[$rowNum]['civicrm_contact_indiv_sort_name']
        && array_key_exists('civicrm_contact_indiv_id', $row)
      ) {
        $url = CRM_Utils_System::url("civicrm/contact/view",
          'reset=1&cid=' . $row['civicrm_contact_indiv_id'],
          $this->_absoluteUrl
        );
        $rows[$rowNum]['civicrm_contact_indiv_sort_name_link'] = $url;
        $rows[$rowNum]['civicrm_contact_indiv_sort_name_hover'] = E::ts("View Contact Summary for this Contact.");
        $entryFound = TRUE;
      }

      if (array_key_exists('TEMP_ClientCarepartners_carepartner_contact_id', $row)
        && $rows[$rowNum]['TEMP_ClientCarepartners_carepartner_contact_id']
      ) {
        $url = CRM_Utils_System::url("civicrm/contact/view",
          'reset=1&cid=' . $row['TEMP_ClientCarepartners_carepartner_contact_id'],
          $this->_absoluteUrl
        );
        $rows[$rowNum]['TEMP_ClientCarepartners_carepartner_contact_id'] = $this->_getCarepartnerName($rows[$rowNum]['TEMP_ClientCarepartners_carepartner_contact_id']);
        $rows[$rowNum]['TEMP_ClientCarepartners_carepartner_contact_id_link'] = $url;
        $rows[$rowNum]['TEMP_ClientCarepartners_carepartner_contact_id_hover'] = E::ts("View Contact Summary for this Contact.");
        $entryFound = TRUE;
      }

      if (array_key_exists('TEMP_ClientCarepartners_carepartner_diagnosis_ids', $row)
        && $rows[$rowNum]['TEMP_ClientCarepartners_carepartner_diagnosis_ids']
      ) {
        $diagnosisIds = array_flip(explode(',', $rows[$rowNum]['TEMP_ClientCarepartners_carepartner_diagnosis_ids']));
        $diagnoses = array_intersect_key($this->_diagnosisOptions, $diagnosisIds);
        $rows[$rowNum]['TEMP_ClientCarepartners_carepartner_diagnosis_ids'] = implode(';<BR />', $diagnoses);
        $entryFound = TRUE;
      }

      if (array_key_exists('TEMP_ClientCarepartners_carepartner_relationship_type_ids', $row)
        && $rows[$rowNum]['TEMP_ClientCarepartners_carepartner_relationship_type_ids']
      ) {
        $relationshipTypeKeys = explode(',', $rows[$rowNum]['TEMP_ClientCarepartners_carepartner_relationship_type_ids']);
        $relationshipTypeLabels = [];
        foreach ($relationshipTypeKeys as $relationshipTypeKey) {
          $relationshipTypeLabels[] = $this->_getRelationshipTypeLabel($relationshipTypeKey);
        }
        $rows[$rowNum]['TEMP_ClientCarepartners_carepartner_relationship_type_ids'] = implode(';<BR />', $relationshipTypeLabels);
        $entryFound = TRUE;
      }

      if (!$entryFound) {
        break;
      }
    }

    // Hide duplicate values per primary contact, if any of the 'carePartner' columns are included.
    $carePartnerColumnNames = [
      'TEMP_ClientCarepartners_carepartner_contact_id',
      'TEMP_ClientCarepartners_carepartner_contact_id_link',
      'TEMP_ClientCarepartners_carepartner_contact_id_hover',
      'TEMP_ClientCarepartners_carepartner_diagnosis_ids',
      'TEMP_ClientCarepartners_carepartner_relationship_type_ids',
    ];
    if (!empty(array_intersect_key($rows[0], array_flip($carePartnerColumnNames)))) {
      $prevContactId = '';
      foreach ($rows as &$row) {
        $cid = CRM_Utils_Array::value('civicrm_contact_indiv_id', $row);
        if ($cid == $prevContactId) {
          foreach (array_keys($row) as $key) {
            if (!in_array(
                $key, $carePartnerColumnNames
              )
            ) {
              $row[$key] = '';
            }
          }
        }
        $prevContactId = $cid;
      }
    }
  }

  public function statistics(&$rows) {
    $statistics = parent::statistics($rows);

    $indentPrefix = '&nbsp; &nbsp; ';

    $this->_addDemographicStats($statistics);

    if ($this->isTableSelected($this->_tempTableName)) {
      // Distinct carepartners count
      $query = "SELECT COUNT(DISTINCT carepartner_contact_id) FROM {$this->_tempTableName}";
      $statistics['counts']['total_carepartners'] = array(
        'title' => E::ts('Total distinct CarePartners'),
        'value' => CRM_Core_DAO::singleValueQuery($query),
        // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
        'type' => CRM_Utils_Type::T_INT,
      );

      // Section header
      $statistics['counts']['relationship_types_blank'] = array(
        'title' => E::ts('CarePartner relationship types'),
        'value' => '',
        // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
        'type' => CRM_Utils_Type::T_STRING,
      );

      // CarePartner relationship
      $query = "
        SELECT r.relationship_type_id, if(r.contact_id_a = t.client_contact_id, rt.label_b_a, rt.label_a_b) as label, count(*) cnt
        FROM {$this->_tempTableName} t
          INNER JOIN civicrm_relationship r ON r.relationship_type_id != 11
            AND (r.contact_id_a IN (t.client_contact_id, t.carepartner_contact_id) and r.contact_id_b IN (t.client_contact_id, carepartner_contact_id))
            AND r.is_active
            AND (r.end_date IS NULL OR r.end_date > NOW())
          INNER JOIN civicrm_relationship_type rt ON rt.id = r.relationship_type_id
        GROUP BY label
      ";
      $dao = CRM_Core_DAO::executeQuery($query);
      while ($dao->fetch()) {
        $statistics['counts']['relationship_types_' . $dao->relationship_type_id] = array(
          'title' => $indentPrefix . E::ts($dao->label),
          'value' => $dao->cnt,
          // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
          'type' => CRM_Utils_Type::T_INT,
        );
      }
    }
    else {
      // Section header
      $statistics['counts']['relationship_types_blank'] = array(
        'title' => E::ts('CarePartner relationship types'),
        'value' => E::ts('Please enable the "CarePartner Relationships" column to reveal these statistics.'),
        // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
        'type' => CRM_Utils_Type::T_STRING,
      );
    }

    // CarePartner diagnosis
    if ($this->isTableSelected($this->_tempTableName)) {
      // Section header
      $statistics['counts']['carepartner_diagnosis_blank'] = array(
        'title' => E::ts('CarePartner diagnosis'),
        'value' => '',
        // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
        'type' => CRM_Utils_Type::T_STRING,
      );
      $query = "
        SELECT COUNT(DISTINCT t.carepartner_contact_id)
        FROM {$this->_tempTableName} t
          INNER JOIN civicrm_value_health_5 ON t.carepartner_contact_id = civicrm_value_health_5.entity_id
        AND %1 IN (
          civicrm_value_health_5.{$this->_customFields['diagnosis1']['column_name']},
          civicrm_value_health_5.{$this->_customFields['diagnosis2']['column_name']},
          civicrm_value_health_5.{$this->_customFields['diagnosis3']['column_name']}
        )
      ";
      // Get all the options for this custom field, so we can list them out.
      // Cycle through all options, one stat for each.
      foreach ($this->_diagnosisOptions as $optionValue => $optionLabel) {
        $queryParams = [
          1 => [$optionValue, 'String'],
        ];
        $statistics['counts']['carepartner_diagnosis-' . $optionValue] = array(
          'title' => $indentPrefix . $optionLabel,
          'value' => CRM_Core_DAO::singleValueQuery($query, $queryParams),
          // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
          'type' => CRM_Utils_Type::T_INT,
        );
      }
    }
    else {
      // Section header
      $statistics['counts']['carepartner_diagnosis_blank'] = array(
        'title' => E::ts('CarePartner diagnosis'),
        'value' => E::ts('Please enable the "CarePartner Diagnosis" column to reveal these statistics.'),
        // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
        'type' => CRM_Utils_Type::T_STRING,
      );
    }

    return $statistics;
  }

  protected function _getCarepartnerName($carepartnerId) {
    if (empty($this->_carepartnerNameOptions)) {
      $query = "
        SELECT DISTINCT carepartner_contact_id, carepartner_display_name
        FROM {$this->_tempTableName}
      ";
      $dao = CRM_Core_DAO::executeQuery($query);
      while ($dao->fetch()) {
        $this->_carepartnerNameOptions[$dao->carepartner_contact_id] = $dao->carepartner_display_name;
      }
    }
    return $this->_carepartnerNameOptions[$carepartnerId];
  }

  protected function _getRelationshipTypeLabel($relationshipTypeKey) {
    if (empty($this->_relationshipTypeLabelOptions)) {
      $query = "
        SELECT CONCAT('a_b_', id) as relationshipTypeKey, label_a_b as relationshipTypeLabel
          FROM civicrm_relationship_type
        UNION
        SELECT CONCAT('b_a_', id) as relationshipTypeKey, label_b_a as relationshipTypeLabel
          FROM civicrm_relationship_type
      ";
      $dao = CRM_Core_DAO::executeQuery($query);
      while ($dao->fetch()) {
        $this->_relationshipTypeLabelOptions[$dao->relationshipTypeKey] = $dao->relationshipTypeLabel;
      }
    }
    return $this->_relationshipTypeLabelOptions[$relationshipTypeKey];
  }

}
