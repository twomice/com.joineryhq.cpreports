<?php
use CRM_Cpreports_ExtensionUtil as E;

class CRM_Cpreports_Form_Report_Cpreport_Clientroster_DurationRelationship extends CRM_Cpreports_Form_Report_Cpreport_Clientroster {

  /**
   * @inheritdoc
   */
  protected $_useFilterRelationshipParticipationDates = TRUE;

  /**
   * @inheritdoc
   */
  protected $_useFilterParticipationDates = FALSE;

  /**
   * @inheritdoc
   */
  protected $_useColumnRelationshipDaysParticipatedAndDerivedStatistics = TRUE;

  public function __construct() {
    parent::__construct();
  }

  public function storeWhereHavingClauseArray() {
    parent::storeWhereHavingClauseArray();

    // Ensure "team client" relationship always exists
    // NOTE: Assumes existence of `civicrm_relationship` as `r` in $this->_from.
    $this->_whereClauses[] = "(r.id IS NOT NULL)";
  }

  public function statistics(&$rows) {
    $statistics = parent::statistics($rows);

    // Set $this->_serviceDateTo and $this->_serviceDateFrom using end_date
    // filter values, so that $this->_addStatisticEndedDuring() calculates
    // based on that (that method expects to be working with service_date filter
    // values, but this report uses end_date instead.
    list($from, $to) = $this->getFromTo($this->_params['end_date_relative'] ?? NULL, $this->_params['end_date_from'] ?? NULL, $this->_params['end_date_to'] ?? NULL);
    if ($to) {
      $this->_serviceDateTo = $to;
    }
    if ($from) {
      $this->_serviceDateFrom = $from;
    }
    $this->_addStatisticRelationshipParticipationEndedDuring($statistics);

    $sqlBase = $this->_getSqlBase();

    //Total "days participated (relationships)" values
    $query = "
      SELECT
        SUM(relationship_days_participated)
      FROM (
        SELECT {$this->_columns['relationship_days_participated']['fields']['relationship_days_participated']['dbAlias']} as relationship_days_participated
            -- sqlbase >>>>
            {$sqlBase}
            -- <<< sqlbase
      ) t
    ";
    $statistics['counts']['total_days'] = array(
      'title' => E::ts("Total of all <em>Days Participated (relationships)</em>"),
      'value' => CRM_Core_DAO::singleValueQuery($query),
      // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
      'type' => CRM_Utils_Type::T_INT,
    );

    //Average duration (based on all Team Clients processed)
    $totalRows = $statistics['counts']['rowsFound']['value'] ?? $statistics['counts']['rowCount']['value'];
    $avgValue = ($statistics['counts']['participation_ended_during']['value'] ? ($statistics['counts']['total_days']['value'] / $totalRows) : 'N/A (none ended during this period)');
    $statistics['counts']['average_duration'] = array(
      'title' => E::ts("Average <em>Days Participated (relationships)</em>"),
      'value' => $avgValue,
      // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
      'type' => (is_numeric($avgValue) ? CRM_Utils_Type::T_INT : CRM_Utils_Type::T_STRING),
    );

    return $statistics;
  }

}
