<?php

require_once 'CRM/Core/Page.php';

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
  }
  
  
  function stmts() {
    
  }
}
