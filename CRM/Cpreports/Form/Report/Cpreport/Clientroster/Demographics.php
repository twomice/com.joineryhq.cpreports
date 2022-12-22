<?php
use CRM_Cpreports_ExtensionUtil as E;

class CRM_Cpreports_Form_Report_Cpreport_Clientroster_Demographics extends CRM_Cpreports_Form_Report_Cpreport_Clientroster {

  public function __construct() {
    parent::__construct();
  }

  public function statistics(&$rows) {
    $statistics = parent::statistics($rows);
    $this->_addDemographicStats($statistics);
    return $statistics;
  }

  public function alterDisplay(&$rows) {
    // Apply whatever is called for in parent method.
    parent::alterDisplay($rows);

    /* Per documentation, this report should show, for "Team Name" and "Team Nickname"
     * fields, all Teams to which the listed Individual has an "Is Team Member"
     * relationship, regardless of the status of that relationship.
     * Making this happen in the report SQL is difficult, considering the
     * structure of this repot class.
     * So instead, for this report only, we'll just retrieve that data here now,
     * format it, and shove it into the $rows array in the right place.
     */
    // Determine the apprpriate line-break for multi-line team column values.
    // (\n on CSV, otherwise html break).
    $lineBreak = (
      ($this->_outputMode == 'csv')
      ? "\n"
      : '<br>'
    );

    // If we're displaying the Team Name or Team Nickname fields,
    // set up and run a query to get all relevant team data for the contacts in $rows.
    if (
      array_key_exists('civicrm_contact_team_organization_name', $rows[0])
      || array_key_exists('civicrm_contact_team_nick_name', $rows[0])
    ) {
      $cids = array_unique(CRM_Utils_Array::collect('civicrm_contact_indiv_id', $rows));
      $cids = array_filter($cids, 'CRM_Utils_Rule::integer');
      $query = '
        select
          r.contact_id_b,
          cteam.display_name,
          cteam.nick_name,
          cteam.id,
          if(
            (! r.is_active)
            or (
              ifnull(r.end_date, now()) < now()
            ),
            0,
            1
          ) as calc_is_active,
          r.end_date,
          r.is_active
        from
          civicrm_relationship r
          inner join civicrm_contact cteam on cteam.id = r.contact_id_a
          and r.relationship_type_id = 18
        where
          r.contact_id_b in ('. implode(',', $cids) .')
        order by
          calc_is_active desc,
          cteam.display_name

      ';
      $dao = CRM_Core_DAO::executeQuery($query);
      // Define an array of orgs per contact; this array is keyed to individual
      // contact_id, and each element is an array of $dao->toArray() output.
      $orgs = [];
      while ($dao->fetch()) {
        $orgs[$dao->contact_id_b][] = $dao->toArray();
      }
    }

    // Now process eacah row to insert the correct Team Name or Team Nick Name values.
    foreach ($rows as $rowNum => $row) {
      if (array_key_exists('civicrm_contact_team_organization_name', $row)) {
        // Clear any link functionality for this column.
        $rows[$rowNum]['civicrm_contact_team_organization_name_link'] = NULL;
        $rows[$rowNum]['civicrm_contact_team_organization_name_hover'] = NULL;

        $teamNameValues = [];
        foreach ($orgs[$row['civicrm_contact_indiv_id']] as $org) {
          $teamNameText = $org['display_name'];
          if ($org['calc_is_active'] == '0') {
            $teamNameText .= ' (inactive)';
          }
          $url = CRM_Utils_System::url("civicrm/contact/view",
            'reset=1&cid=' . $org['id'],
            TRUE
          );
          $teamNameText = '<a href="'. $url .'">' . $teamNameText .'</a>';
          $teamNameValues[] = $teamNameText;
        }
        $rows[$rowNum]['civicrm_contact_team_organization_name'] = implode($lineBreak, $teamNameValues);
        $entryFound = TRUE;
      }

      if (array_key_exists('civicrm_contact_team_nick_name', $row)) {
        // Clear any link functionality for this column.
        $rows[$rowNum]['civicrm_contact_team_nick_name_link'] = NULL;
        $rows[$rowNum]['civicrm_contact_team_nick_name_hover'] = NULL;

        $teamNickNameValues = [];
        foreach ($orgs[$row['civicrm_contact_indiv_id']] as $org) {
          $teamNickNameText = $org['nick_name'];
          if ($org['calc_is_active'] == '0') {
            $teamNickNameText .= ' (inactive)';
          }
          $url = CRM_Utils_System::url("civicrm/contact/view",
            'reset=1&cid=' . $org['id'],
            TRUE
          );
          $teamNickNameText = '<a href="'. $url .'">' . $teamNickNameText .'</a>';
          $teamNickNameValues[] = $teamNickNameText;
        }
        $rows[$rowNum]['civicrm_contact_team_nick_name'] = implode('<br>', $teamNickNameValues);
        $entryFound = TRUE;
      }

      if (!$entryFound) {
        break;
      }
    }
  }

}
