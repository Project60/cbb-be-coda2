<?php

ini_set('error_reporting',E_ALL);
require_once 'CRM/Core/Page.php';

$subp =dirname(__FILE__) . '/..';
require_once $subp.'/reader_old/settings.php';
require_once $subp.'/reader_old/project.php';
require_once $subp.'/reader_old/DBO.php';
require_once $subp.'/reader_old/dao.php';
require_once $subp.'/reader_old/SimpleTable.php';
require_once $subp.'/reader_old/ProcessCodaFile.php';
require_once $subp.'/reader_old/CodaBbanToBic.php';
require_once $subp.'/reader_old/CodaBbanToIban.php';
require_once $subp.'/reader_old/CodaReader_old.php';


class CRM_Becoda2_Page_Dashboard extends CRM_Core_Page {
  function run() {
    // Example: Set the page-title dynamically; alternatively, declare a static title in xml/Menu/*.xml
    CRM_Utils_System::setTitle(ts('CODA2 Dashboard'));

    $page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 'files';
    
    $this->assign('page', $page);

    switch($page) {
      case 'files' : 
        $this->files();
        break;
      case 'stmts' : 
        $this->stmts();
        break;
    }
    parent::run();
    
  }
  
  function files() {
    $fs = array(
      array('name'=>'a.txt','when'=>'2013-08-22'),
      array('name'=>'b.txt','when'=>'2013-08-22'),
      array('name'=>'c.txt','when'=>'2013-08-22'),
      array('name'=>'d.txt','when'=>'2013-08-22'),
    );
    $this->assign('fs',$fs);
    
    $path = '/var/www/msliga-civi/data/KBCCDA20120316_171241_508_03180448443905.COD';
    project::getInstance();
    $p = new ProcessCodaFile();
    $p->process($path);
    
    echo 'Done';
  }
  
  
  function stmts() {
    
  }
}
