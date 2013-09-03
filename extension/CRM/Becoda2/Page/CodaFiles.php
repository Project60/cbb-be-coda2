<?php

require_once 'CRM/Core/Page.php';
require_once 'includes.php';

class CRM_Becoda2_Page_CodaFiles extends CRM_Core_Page {

  function run() {
    CRM_Utils_System::setTitle(ts('Process CODA files'));

    $path = '/var/www/msliga-civi/data/inbox/';

    $mode = isset($_REQUEST['mode']) ? $_REQUEST['mode'] : '';
    if (!$mode)
      $mode = 'list';
    $this->assign('pagemode', $mode);

    switch ($mode) {
      case 'single' :
        $acct = $_REQUEST['a'];
        $this->assign('account', $_REQUEST['a']);

        $fpath = $path . $_REQUEST['p'];
        $this->assign('path', $_REQUEST['p']);
        if (!file_exists($fpath)) {
          $log = array(array('cls' => ' bb red', 'msg' => 'WARNING: could not find file !'));
        } else {
          project::getInstance();
          $p = new ProcessCodaFile();
          $log = $p->process($fpath);
          if (!$this->moveFile($path, $_REQUEST['p'])) {
            $log[] = array('cls' => ' bb red', 'msg' => 'WARNING: could not move file to /processed folder !');
          }
        }
        $this->assign('log', $log);
        break;

      case 'group' :
        $currentAcct = $_REQUEST['a'];
        $this->assign('account', $_REQUEST['a']);

        project::getInstance();
        $p = new ProcessCodaFile();
        $files = scandir($path);
        $log = array();
        foreach ($files as $f) {
          if (substr($f, 0, 1) != '.') {
            list($modified, $acct, $seq) = $this->getDateFast($path . $f);
            if (trim($acct) == $currentAcct) {
              $fpath = $path . $f;
              $logm = $p->process($fpath);
              foreach($logm as $m) $log[] = $m;
              if (!$this->moveFile($path, $f)) {
                $log[] = array('cls' => ' bb red', 'msg' => 'WARNING: could not move file to /processed folder !');
              }
            }
          }
          $this->assign('log', $log);
        }
        break;


      case 'list' :
      default :
        $currentAcct = isset($_REQUEST['a']) ? $_REQUEST['a'] : '';
        $this->assign('currentAccount', trim($currentAcct));

        $files = scandir($path);
        $fs = array();
        foreach ($files as $f) {
          if (substr($f, 0, 1) != '.') {
            list($modified, $acct, $seq) = $this->getDateFast($path . $f);
            $fs[$acct][$modified][$seq] = $f;
          }
        }
        foreach ($fs as $acct => $stmts) {
          foreach ($stmts as $modified => $seqs) {
            ksort($seqs);
          }
          ksort($stmts);
        }
        $this->assign('files', $fs);

        // get bank account description
        $bas = array();
        foreach ($fs as $acct => $ignore) {
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
    }

    global $base_url;
    $this->assign('base_url', $base_url);
    parent::run();
  }

  function getDateFast($path) {
    $f = fopen($path, 'r');
    $row = fgets($f);
    $date = substr($row, 5, 6);
    $date = substr($date, 0, 2) . '/' . substr($date, 2, 2) . '/' . substr($date, 4, 2);
    $row = fgets($f);
    // assume BE IBAN for the demo
    $account = trim(substr($row, 5, 31));
    $seq = substr($row, 2, 3);
    return array($date, $account, $seq);
  }

  /**
   * Move the file after processing
   * 
   * @param type $root
   * @param type $filename
   */
  private function moveFile($root, $filename) {
    $newroot = preg_replace("|(.*)/inbox/$|", "$1/processed/", $root);
    return rename($root . $filename, $newroot . $filename);
  }

}
