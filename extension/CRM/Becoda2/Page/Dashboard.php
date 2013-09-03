<?php
require_once 'CRM/Core/Page.php';
require_once 'CRM/Banking/Helpers/OptionValue.php';

class CRM_Becoda2_Page_Dashboard extends CRM_Core_Page {
    
    public function run(){
        // Example: Set the page-title dynamically; alternatively, declare a static title in xml/Menu/*.xml
        CRM_Utils_System::setTitle(ts('Dashboard'));
        // Example: Assign a variable for use in a template
        //$this->assign('currentTime', date('Y-m-d H:i:s'));
        //$this->run_create_ba_msliga();
        $this->Becoda2();
        //$matcher_type_id = banking_helper_optionvalueid_by_groupname_and_name('civicrm_banking.plugin_classes', 'match');
        /*
        $c = new CRM_Banking_BAO_PluginInstance();
        $res = $c::listInstances('match');
        var_dump($res);
         * 
         */
        //$this->testmatch2();
        parent::run();
    }
    
    public function run_new() {
        $pathname = 'C:\xampp\htdocs\civibankinglocal\data\coda\msliga\inbox\KBCCDA20120330_162505_002_03182111382583.COD';

        $cf = new CRM_Becoda2_PluginImpl_File($pathname);
        $res = $cf->nextBatch();
        //var_dump($res);
        $res = $cf->nextBatch();
        var_dump($res);
        /*
        $plugin_list = CRM_Banking_BAO_PluginInstance::listInstances('coda');
        var_dump($plugin_list);
               
         */ 
    }
    
    public function testmatch2(){
        $plugin_list = CRM_Banking_BAO_PluginInstance::listInstances('import');
        $pl = array();
        foreach($plugin_list as $p){
            if($p->plugin_class_id==725){
                $pl[] = $p;
            }
        }
        foreach($pl as $p){
            $c = new CRM_Banking_PluginImpl_Coda($p);
            $params = array();
            $c->import_stream($params);
            
        }
        
    }

    public function testMatch(){
         /*
        $p = array(
            'street_address'=>array('LIKE'=> 'Abdijstraat%',),
            'city'=>'sint-truiden',
            'version'=>3,
        );
        $res = civicrm_api('address','get',$p);
        var_dump($res);        
         */
         
    }

    public function run_create_ba_msliga(){
        require_once 'C:\xampp\htdocs\civibankingdrup\sites\default\files\civicrm\custom\extensions\civicodaextension\CRM\Becoda2\reader_old\ProcessCodaFile.php';

        //$p = project::getInstance();
        $p = project::$codaDBO;
        $sql = 'select id, name, bic, bban, iban from civicrm_coda_batch group by iban';
        $stmt = $p->query($sql);
        $l = $stmt->fetch_all(MYSQLI_ASSOC);
        $rekeningen = array();
        foreach($l as $rec){
            $rekeningen[$rec['iban']] = $rec;
        }

        $sql = 'select * from civicrm_bank_plugin_instance where name like "mijn%" or name like "rekening%"';
        $stmt = $p->query($sql);
        $plugins = $stmt->fetch_all(MYSQLI_ASSOC);
        
        foreach($plugins as $plugin){
            $json = json_decode($plugin['config']);
            
            $iban = $json->account;
            $rekening = $rekeningen[$iban];
            if($rekening['name']!='MS-LIGA-VLAANDEREN VZW'){
                $name = $rekening['name'];
                $sql = 'update civicrm_bank_plugin_instance set name="'.$rekening['name'].'" where id='.$rekening['id'];
                echo '<BR>'.$sql;
                $p->query($sql);
            }else{
                $name = $plugin['name'];
            }
            $sql = 'insert into civicrm_bank_account set description="'.$name.'"';
            echo '<BR>'.$sql;
            $p->query($sql);
            $ba_id = $p->lastInsertId();
            $data=array('bban'=>array('reference'=>$rekening['bban'], 'reference_type_id'=>0, 'ba_id'=>$ba_id),
                        'iban'=>array('reference'=>$rekening['iban'], 'reference_type_id'=>1, 'ba_id'=>$ba_id),
                        'bic'=>array('reference'=>$rekening['bic'], 'reference_type_id'=>2, 'ba_id'=>$ba_id),
                );
            foreach($data as $k=>$r){
                $sql = 'insert into civicrm_bank_account_reference set reference="'.$r['reference'].'", reference_type_id='.$r['reference_type_id'].', ba_id='.$r['ba_id'];
                echo '<BR>'.$sql;
                $p->query($sql);
            }
        }

    }

    public function run_old(){
        require_once 'CRM/Becoda2/reader_old/ProcessCodaFile.php';
        $path = 'C:\xampp\htdocs\civibankinglocal\data\coda\msliga\inbox\KBCCDA20120330_162505_002_03182111382583.COD';
        
        $p = new ProcessCodaFile();
        $p->process($path);
    }
    
    public function Becoda2(){
        //require_once 'CRM/Becoda2/reader_old/Debug.php';
        //Debug::starttimer();
        $p = 'C:\xampp\htdocs\civibankingdrup\sites\default\files\civicrm\custom\extensions\civicodaextension\CRM\Becoda2\reader_old\data\CODA_20130503_162240.COD';
        //$plugin = new CRM_Becoda2_PluginImpl_File($p);
		$plugin_list = CRM_Banking_BAO_PluginInstance::listInstances('becoda2');	
        //$plugin_list = CRM_Banking_BAO_PluginInstance::listInstances('import');	
        //var_dump($plugin_list);
        
		$plugin = $plugin_list[0];
		//var_dump($plugin);
		
		$plugin_instance = $plugin->getInstance();
		//var_dump($plugin_instance);
		$plugin_instance->import_file($p, array('source'=>$p));
        //Debug::stoptimer();
    }
}
