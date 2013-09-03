<?php

class CRM_Becoda2_PluginImpl_Becoda2 extends CRM_Banking_PluginModel_Importer{

	public $sequence_tolerance=5;
    public static $source = 'Becoda2';
    protected $_ba_ref_types;
    static $_tx_states;
	public $iban_reference=1;	//tochange
	public $bban_reference=0;	//tochange

	public function __construct($config_name) {
        parent::__construct($config_name);
        // read config, set defaults
        $config = $this->_plugin_config;
        $this->_ba_ref_types = banking_helper_optiongroup_id_name_mapping('civicrm_banking.bank_account_reference_type');  
        self::$_tx_states =  banking_helper_optiongroup_id_name_mapping('civicrm_banking.bank_tx_status');
    }
    
    /**
   * the plugin's user readable name
   * 
   * @return string
   */
    static function displayName() {
        return 'CODA 2 Importer';
    }

    /**
     * Report if the plugin is capable of importing files
     * 
     * @return bool
     */
    static function does_import_files() {
        return true;
    }

    /**
     * Report if the plugin is capable of importing streams, i.e. data from a non-file source, e.g. the web
     * 
     * @return bool
     */
    static function does_import_stream() {
        return false;
    }

    /**
     * Test if the given file can be imported
     * 
     * @var 
     * @return TODO: data format? 
     */
    function probe_file($file_path, $params) {
        // TODO: implement
        return is_readable($file_path);
    }
    
    
    /**
   * Import the given file
   * 
   * @return TODO: data format? 
   */
     /*
     * From the CODA specification : 
     * 
     * "...
     * A separate CODA file will be generated for each account (...). These 
     * files will be sent in one single physical file. ...
     * 
     * Each transaction mentioned on the statement of account will be included 
     * into detail into the CODA file. Extra information pertaining to the 
     * movement will be saved in informative records (3). Information which is 
     * not linked to a particular transaction, can be included into free 
     * records (4). These records (4) can be inserted only between the new 
     * balance (8) record and the trailer record (9).
     * ..."
     * 
     * The outer loop described below processes the CODA files in a particular
     * physical file. Each CODA file corresponds to a separate batch of 
     * transactions.
     */
    function import_file($file_path, $params) {        
        
        $config = $this->_plugin_config;        
        $this->reportProgress(0.0, sprintf("Starting to read file '%s'...", $file_path));

        $cf = new CRM_Becoda2_PluginImpl_File($file_path);

        while ($btxb = $cf->nextBatch()) {   
            $this->openTransactionBatch_CodaBatch($btxb);
                        
            while ($btx = $cf->nextRecord()) {
                //todo
                $this->addBtx($btx);
            }
            
            $this->closeTransactionBatch(true);
            //$cf->closeBatch($btxb);
        }

       // $cf->close();

        $this->reportDone();
    }
    
    public function addBtx($coda_tx){    
        
        if(is_null($coda_tx->bban) && is_null($coda_tx->iban)){  //for testing only ; must be replaced
            echo '<BR>'.$coda_tx->iban;
            echo '<BR>'.$coda_tx->bban;
            return null;
        }

        $data_raw=array(
            'name'=>$coda_tx->name,
            'move_msg'=>$coda_tx->move_msg,
            'info_msg'=>$coda_tx->info_msg,                  
        );
        $data_parsed = array(
            'name'=>$coda_tx->name,
            //'street_address'=>$coda_tx->streetname.' '.$coda_tx->streetnumber,
            //'postal_code'=>$coda_tx->postal_code,
            //'city'=>$coda_tx->city,
            'bic'=>$coda_tx->bic,
            'bban'=>$coda_tx->bban,
            'iban'=>$coda_tx->iban,
            'txncode'=>$coda_tx->txncode,
            'customer_ref'=>$coda_tx->customer_ref,
            'move_struct_code'=>$coda_tx->move_struct_code,
            //...todo
        );
        $btxb = $this->_current_transaction_batch;
        $party_bank_account_id = $this->getOrCreateBankAccount($coda_tx);
        $btx = array(
              'version' => 3,
              'debug' => 1,
              'amount' => $coda_tx->amount,
              'bank_reference' => self::$source.' '.$this->_current_transaction_batch_attributes['ba_ref'].' '.sprintf("%08s", $btxb->id).'-'.$btxb->sequence.'-'.$coda_tx->sequence,       
              'value_date' => date('YmdHis', strtotime($coda_tx->value_date)),   
              'booking_date' => date('YmdHis', strtotime($coda_tx->booking_date)),
              'currency' => 'EUR',                          // EUR
              'type_id' => 0,                               // TODO: lookup type ?
              'status_id' => 0, //$tx_status_id,                // todo         
              'data_raw' => json_encode($data_raw),                   
              'data_parsed' => json_encode($data_parsed),   // name, purpose
              'ba_id' => $this->_current_transaction_batch->ba_id, //$this->bank_account_id,                               
              'party_ba_id' => $party_bank_account_id,                          
              'tx_batch_id' => $this->_current_transaction_batch->id,                        
              'sequence' => $btxb->sequence.'-'.$coda_tx->sequence,                             
            );
            
            //$progress = $cnt/$this->cnt_total_tx;
        $progress =0.5;
        $params = array();
        $duplicate = $this->checkAndStoreBTX($btx, $progress, $params);  
    }

    public function openTransactionBatch_CodaBatch(&$codabatch) {
        if (isset($this->_current_transaction_batch)) {
            $this->reportProgress($progress, 
                  ts("Internal error: trying to open BTX batch before closing an old one."), 
                  CRM_Banking_PluginModel_Base::REPORT_LEVEL_ERROR);
            return;
        }
        
        // get a bank account reference for this codabatch
        if(!empty($codabatch->iban)){
			$type = 'iban';					
		}else{
			$type = 'bban';
		}
		$this->_key = $codabatch->$type.':'.$codabatch->sequence;
		$reftype = $type.'_reference';
       
		$params = array('reference'=>$codabatch->$type, 'reference_type_id'=>  $this->$reftype, 'version'=>3);
		$res = civicrm_api('BankingAccountReference', 'get', $params);       
        if($res['count']!=1){
            throw new Exception('Banking account reference '.$codabatch->$type.' not found');            
        }
        $ba_ref = $res['values'][$res['id']];
        
        // get or create a bank transaction batch
        $reference = $codabatch->$type.':'.$codabatch->sequence.' '.$codabatch->file;
        $bank_tx_batch = new CRM_Banking_BAO_BankTransactionBatch();
        $bank_tx_batch->get('reference', $reference);        
		//array('sequence', 'date_created_by_bank', 'name', 'bic', 'bban', 'iban', 
		//'currency', 'country_code', 'starting_balance', 'ending_balance', 'starting_date', 'ending_date', 'source', 'file', 'extra', 'status');
		$bank_tx_batch->issue_date = date('YmdHis');
		$bank_tx_batch->bank_date = date('Ymd', strtotime($codabatch->date_created_by_bank));
		//$reference = $config->account.' '.$this->_current_coda_batch->sequence.' '.$this->_current_coda_batch->file;		
		$bank_tx_batch->reference = $this->_key.' '.$codabatch->file;
		$bank_tx_batch->sequence = $codabatch->sequence;
		$bank_tx_batch->starting_balance = $codabatch->starting_balance;
		$bank_tx_batch->ending_balance = 0; //$codabatch->ending_balance;
		$bank_tx_batch->currency = $codabatch->currency;
		$bank_tx_batch->tx_count = $codabatch->count_codarecords; //count($this->codarecords[$this->_key]);
		$bank_tx_batch->starting_date = date('YmdHis', strtotime($codabatch->starting_date));
		$bank_tx_batch->ending_date = date('YmdHis', strtotime($codabatch->ending_date));
		$bank_tx_batch->ba_id = $ba_ref['ba_id'];	   
		$bank_tx_batch->source = self::$source;
		
        // set status Out of Sync or new
        $bank_tx_batch->status = 'new';	
        $prev_seq_btxb = self::getPrevBankTxBatch($bank_tx_batch->bank_date, $bank_tx_batch->sequence, $bank_tx_batch->ba_id, $bank_tx_batch->source);        
		if(!empty($prev_seq_btxb)){
			if($bank_tx_batch->starting_balance!=$prev_seq_btxb->ending_balance){
				$bank_tx_batch->status = 'oos';
			}
		}
        $this->_current_transaction_batch = $bank_tx_batch;
        $this->_current_transaction_batch_attributes = array();
        
        $this->_current_transaction_batch->save();
        
        $this->_current_transaction_batch_attributes['isnew'] = TRUE;
        $this->_current_transaction_batch_attributes['sum'] = 0;
        $this->_current_transaction_batch_attributes['ba_ref'] = $codabatch->$type;
        
    }
    
    /**
     * This will close a previously opened transaction batch, see openTransactionBatch
     *
     * If you pass $store=FALSE as a parameter, the currently open batch will be dismissed
     */
    function closeTransactionBatch($store=TRUE) {
        if (is_null($this->_current_transaction_batch)){
            $this->reportProgress($progress, 
                  ts("Internal error: trying to close a nonexisting BTX batch."), 
                  CRM_Banking_PluginModel_Base::REPORT_LEVEL_ERROR);
            return;
        }
        if ($store) {

            // check if the sums are correct:
            if ($this->_current_transaction_batch->ending_balance) {
              $sum_in_bao = $this->_current_transaction_batch->ending_balance - $this->_current_transaction_batch->starting_balance;
              $deviation = $sum_in_bao - $this->_current_transaction_batch_attributes['sum'];
              $correct_value = $this->_current_transaction_batch->starting_balance + $this->_current_transaction_batch_attributes['sum'];
              if (abs($deviation) > 0.005) {
                // there is a (too big) deviation!
                if ($this->_current_transaction_batch->ending_balance) { // only log if it was set
                  $this->reportProgress($progress, 
                        sprintf(ts("Adjusted ending balance from %s to %s!"), $this->_current_transaction_batch->ending_balance, $correct_value),
                        CRM_Banking_PluginModel_Base::REPORT_LEVEL_WARN);
                }
                $this->_current_transaction_batch->ending_balance = $correct_value;
              }
        } else if ($this->_current_transaction_batch->starting_balance!=NULL) {
            // set the calculated ending balance only if the was a starting balance set
            $this->_current_transaction_batch->ending_balance = $this->_current_transaction_batch->starting_balance + $this->_current_transaction_batch_attributes['sum'];
        }

        // set the dates
        if (!$this->_current_transaction_batch->starting_date && isset($this->_current_transaction_batch_attributes['starting_date']))
          $this->_current_transaction_batch->starting_date = $this->_current_transaction_batch_attributes['starting_date'];
        if (!$this->_current_transaction_batch->ending_date && isset($this->_current_transaction_batch_attributes['ending_date']))
          $this->_current_transaction_batch->ending_date = $this->_current_transaction_batch_attributes['ending_date'];
        
        // set the bank reference
        if (!$this->_current_transaction_batch->reference && isset($this->_current_transaction_batch_attributes['references']))
          $this->_current_transaction_batch->reference = md5($this->_current_transaction_batch_attributes['references']);

        // set the sequence //cm//
        if (!$this->_current_transaction_batch->sequence && isset($this->_current_transaction_batch_attributes['sequence']))
          $this->_current_transaction_batch->sequence = $this->_current_transaction_batch_attributes['sequence'];
        
        $this->_current_transaction_batch->save();
        
        $btxb = $this->_current_transaction_batch;
        $next_seq_btxb = self::getNextBankTxBatch($btxb->bank_date, $btxb->sequence, $btxb->ba_id, $btxb->source);		
		if(!empty($next_seq_btxb)){
			if($btxb->ending_balance!=$next_seq_btxb->starting_balance){
				$next_seq_btxb->status = 'oos';
			}else{
				$next_seq_btxb->status = 'new';
			}
            $fields = $next_seq_btxb->toArray();
            CRM_Banking_BAO_BankTransactionBatch::add($fields);
                   
		}

      } else if ($this->_current_transaction_batch_attributes['isnew']) {
        // since thif ($this->_current_transaction_batch_attributes['isnew']) {e batch object had to be created in order to get the ID, we would have to
        //  delete it here, if the user didn't want to keep it.
        $this->_current_transaction_batch->delete();
      }
      $this->_current_transaction_batch = NULL;
        
    }
    
      
  public static function getPrevBankTxBatch($bank_date, $sequence, $ba_id, $source){
      $year = date('Y', strtotime($bank_date));
	  $seqnr = (int)$sequence;
	  if(($seqnr-1)==0){
		  $seqnr=1000;
		  $year -= 1;
	  }
	  $sql = 'select * from civicrm_bank_tx_batch 
		      where YEAR(bank_date)="'.$year.'" and 
				    sequence<'.$seqnr.' and 
					ba_id='.$ba_id.' and
				    source="'.mysql_real_escape_string($source).'" order by sequence DESC limit 1';      
      
	  $bank_tx_batch = CRM_Core_DAO::executeQuery($sql);
      $ok = $bank_tx_batch->fetch();	 
	  if($ok===false){
		  return null;
	  }else{
		  return $bank_tx_batch;
	  }
  }
  
    public static function getNextBankTxBatch($bank_date, $sequence, $ba_id, $source){
        $year = date('Y', strtotime($bank_date));
        $seqnr = (int)$sequence;
        if(($seqnr+1)==1000){
            $seqnr=0;
            $year += 1;
        }
        $sql = 'select * from civicrm_bank_tx_batch 
                where YEAR(bank_date)="'.$year.'" and 
                      sequence>'.$seqnr.' and 
                      ba_id='.$ba_id.' and
                      source="'.mysql_real_escape_string($source).'" order by sequence ASC limit 1';      
        $bank_tx_batch = CRM_Core_DAO::executeQuery($sql);
        $ok = $bank_tx_batch->fetch();
        if($ok===false){
            return null;
        }else{
            return $bank_tx_batch;
        }
    }
    
    /**
     * Test if the configured source is available and ready
     * 
     * @var 
     * @return TODO: data format?
     */
    function probe_stream($params) {
        return false;
    }

    /**
     * Import the given file
     * 
     * @return TODO: data format? 
     */
    function import_stream($params) {
          $this->reportDone(ts("Importing streams not supported by this plugin."));
    }
    
    public static function getTxStatusId($name){
        $name = strtolower($name);
        if(array_key_exists($name, self::$_tx_states)){
            return self::$_tx_states[$name]['id'];
        }else{
            return null;
        }

    }
    
    public function getOrCreateBankAccount(&$coda_tx){
    $refs = array();
    if(isset($coda_tx->iban) && !empty($coda_tx->iban)){
        $refs['iban'] = $coda_tx->iban;
    }
    if(isset($coda_tx->bban) && !empty($coda_tx->bban)){
        $refs['bban'] = $coda_tx->bban;
    }
    if(empty($refs)){

    }

    $bank_account_refs = array();
    foreach($refs as $type=>$ref){        
        $breftypeid = $this->_ba_ref_types[$type]['value'];    
        $result = civicrm_api('BankingAccountReference', 'get', array('reference_type_id'=>$breftypeid, 'reference'=>$ref, 'version'=>3));

        if($result['count']==1){
            $bank_account_refs[$type] = $result['values'][$result['id']];            
        }
    }

    if(empty($bank_account_refs)){  //create a new bank account
        $bank_account = new CRM_Banking_BAO_BankAccount();
        $bank_account->description = $coda_tx->name;
        $data_raw = array(
            'name'=>$coda_tx->name,
            'info_msg'=>$coda_tx->info_msg,
        );
        $data_parsed = array(
            'name' => $coda_tx->name,
            'street_address' => trim($coda_tx->street .' '.$coda_tx->streetnr),
            'postal_code' => $coda_tx->zipcode,
            'city' => $coda_tx->city,
            'country_code' => $coda_tx->country_code,
            'bic' => $coda_tx->bic,
        );
        $bank_account->created_date = date('YmdHis');
        $bank_account->modified_date = date('YmdHis');
        $bank_account->data_raw = json_encode($data_raw);
        $bank_account->data_parsed = json_encode($data_parsed);
        $bank_account->save();
        /*
        $ma = new CRM_banking_demo_matchAddress($bank_account);
        
        if($ma->searchAddress()){
            $ma->updateDataParsed();
        }         
         */
    }else{    
        $ba_ref = reset($bank_account_refs);
        
        $result = civicrm_api('BankingAccount', 'get', array('id'=>$ba_ref['ba_id'], 'version'=>3));        
        $bank_account = (object) $result['values'][$result['id']];         
    }

    if(isset($coda_tx->bic) && !empty($coda_tx->bic)){
        $params = array(
            'reference_type_id' => $this->_ba_ref_types['bic']['value'],
            'reference' => $coda_tx->bic,
            'ba_id' => $bank_account->id,
            'version' => 3,
        );
        $result = civicrm_api('BankingAccountReference', 'get', $params);        
        if($result['count']==0){
            $refs['bic'] = $coda_tx->bic;
        }       
    }   

    foreach($refs as $type=>$ref){
        if(!array_key_exists($type, $bank_account_refs)){                
            $bank_account_ref = new CRM_Banking_BAO_BankAccountReference();
            $bank_account_ref->reference = $coda_tx->$type;
            $bank_account_ref->reference_type_id = $this->_ba_ref_types[$type]['value'];
            $bank_account_ref->ba_id = $bank_account->id;
            $bank_account_ref->save();
        }
    }
    return $bank_account->id;    
  }
    

}