<?php

require_once 'CRM/Core/Page.php';
require_once 'includes.php';

class CRM_Becoda2_Page_CodaBatch extends CRM_Core_Page {

  function run() {
    // Example: Set the page-title dynamically; alternatively, declare a static title in xml/Menu/*.xml
    CRM_Utils_System::setTitle(ts('Process CODA statements'));

    $mode = isset($_REQUEST['mode']) ? $_REQUEST['mode'] : '';
    if (!$mode)
      $mode = 'list';
    $this->assign('pagemode', $mode);


    $sql = "
        SELECT 
          *,
          cb.iban as iban,
          cb.sequence as sequence,
          count(*) as ntx
        FROM 
          civicrm_coda_batch cb
          JOIN civicrm_coda_tx ctx ON ctx.coda_batch_id = cb.id
          JOIN civicrm_bank_account_reference baref ON cb.iban = baref.reference AND baref.reference_type_id = 1
          JOIN civicrm_bank_account ba ON baref.ba_id = ba.id
        WHERE 
          cb.status = 'new'
        GROUP BY 
          cb.id
        ORDER BY
          cb.iban, cb.sequence ASC
        ";
    $dao = CRM_Core_DAO::executeQuery($sql);
    $batches = array();
    while ($dao->fetch()) {
      $acct = $dao->iban;
      $seq = $dao->sequence;
      $batches[$acct][$seq] = get_object_vars($dao);
    }
    $this->assign('batches', $batches);

    // get bank account description
    $bas = array();
    foreach ($batches as $acct => $ignore) {
      $sql = "
          SELECT ba.description AS description
          FROM civicrm_bank_account ba
          JOIN civicrm_bank_account_reference baref ON baref.ba_id = ba.id
          WHERE baref.reference = '$acct'
          ";
      $dao = CRM_Core_DAO::executeQuery($sql);
      $dao->fetch();
      $bas[$acct] = $dao->description;
    }
    $this->assign('banames', $bas);

    global $base_url;
    $this->assign('base_url', $base_url);
    parent::run();
  }

}

