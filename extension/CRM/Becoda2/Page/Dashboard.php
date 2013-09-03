<?php

//ini_set('error_reporting',E_ALL);
require_once 'CRM/Core/Page.php';

require_once 'includes.php';


class CRM_Becoda2_Page_Dashboard extends CRM_Core_Page {
  function run() {
    // Example: Set the page-title dynamically; alternatively, declare a static title in xml/Menu/*.xml
    CRM_Utils_System::setTitle(ts('CODA2 Dashboard'));

    $page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 'files';
    
    $this->assign('page', $page);

    // files info
    $path = '/var/www/msliga-civi/data/inbox/';
    $files = scandir($path);
    $this->assign('filecount',count($files)-2);
    
    // statements info
    $sql = "
      SELECT COUNT(*) AS n 
      FROM civicrm_coda_batch 
      WHERE `status`='NEW'
      ";
    $dao = CRM_Core_DAO::executeQuery($sql);
    $dao->fetch();
    $this->assign('batchcount',$dao->n);
    
    parent::run();
    
  }
  
  
    

    /*
    $path = '/var/www/msliga-civi/data/inbox/KBCCDA20120316_171241_508_03180448443905.COD';
    project::getInstance();
    $p = new ProcessCodaFile();
    $p->process($path);
    */
  
}
