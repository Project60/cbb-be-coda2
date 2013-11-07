<?php
/*
 * From the CODA specification : 
 * 
 * "...
 * Each file containing movement at least consists of records 0, 1, 2, 8 and 9.
 * Records 3 will be included if they give extra information about record 2, 
 * which precedes.
 * 
 * The codes serve to identify the various records :
 *    0 = header record;
 *    1 = old balance;
 *    2 = movement. Part 1 is always mentioned, parts 2 and 3 will be mentioned 
 *        if necessary.
 *    3 = additional information
 *    8 = new balance  
 *    (4) = free communications
 *    9 = trailer record
 * ..."
 * 
 * The Reader instance needs to be able to process the header/footer and balance
 * records to serve the nextBatch() functionality of the File class. 
 * 
 * It aso needs to process the record types 2 and 3 representing movements.
 */

require_once 'CRM/Becoda2/helper/SimpleTable.php';

class CRM_Becoda2_PluginImpl_File {
	/*
	 * Contains the full pathname of the file being processed
	 */
	protected $_pathname;
	protected $_key;	
    protected $codabatches = array();
    protected $codabatch;
    protected $codarecord;
    protected $codarecords = array();
    protected $codabatchrecords = array();
    
    static $coda_batch_fields = array('sequence', 'date_created_by_bank', 'name', 'bic', 'bban', 'iban', 'currency', 'country_code', 'starting_balance', 'ending_balance', 'starting_date', 'ending_date', 'source', 'file', 'extra', 'status', 'count_codarecords');    
	static $coda_tx_fields = array('coda_batch_id', 'sequence', 'coda_batch', 'value_date', 'booking_date', 'name', 'street', 'streetnr', 'streetsuff', 'zipcode', 'city', 'country_code', 'bic', 'bban', 'iban', 'currency', 'amount', 'txncode', 'move_struct_code', 'move_msg', 'customer_ref', 'category_purpose', 'purpose', 'move_detail', 'info_struct_code', 'info_msg', 'info_detail', 'creditorid', 'status', 'extra', 'source');
    protected $nrRecs;
    protected $_codabatch_extra = array();
    protected $_codarecord_extra = array();
    protected $meta=array('nextcode'=>null, 
                          'linkcode'=>null, 
                          'code'=>null, 
                          'expected'=>array(),
                          'seqnr'=>null,
                          'detailnr'=>null);
	
	//default values : use bban2iban filter then needs bic table
	protected $config=array(
		//'bban2iban'=>1,
		'bban2iban'=>0,
		'bic_table'=>'civicrm_bank_bic',
	); 
	public function __construct($file_path, $config=array()) {		
        $this->resetmeta();
		if(!empty($config)){
			$this->config = array_merge($this->config, $config);
		}
        $this->_pathname = $file_path;
        $this->parse($file_path);
    }
    
    /**
	 * Return a CRM_Banking_BAO_BankTransactionBatch instance
	 * 
	 * Every file contains a sequence of batch representations. This function 
	 * iterates over all of them. Returning null is the equivalent of EOF.
	 */
	public function nextBatch() {		
		$codabatch = each($this->codabatches);
		if($codabatch===false){	//no more codabatches
			$this->_key = null;
			return null;
		}
		$codabatch = $codabatch['value'];		
		$iban = $codabatch->iban;		
        if(!empty($iban)){
			$type = 'iban';					
		}else{			
			$type = 'bban';
		}
		$this->_key = $codabatch->$type.':'.$codabatch->sequence;		
        return $codabatch;
    }
    
    /**
	 * Return a CRM_Banking_BAO_BankTransaction instance
	 * 
	 * Every section of the file, which represents an individual CODA file, 
	 * contains a sequence of individual records, some of which represent an
	 * individual BankTransaction. Returning null is the equivalent of EOF. 
	 */
	public function nextRecord() {
		if(is_null($this->_key) || !array_key_exists($this->_key, $this->codabatchrecords)){
			var_dump($this->_key);
			var_dump($this->codabatchrecords);
			throw new Exception('Fatal error in Coda reader');			
		}
		$coda_tx = each($this->codabatchrecords[$this->_key]);
        
		if($coda_tx===false){	//no more coda_tx's
			return null;
		}
		$coda_tx = $coda_tx['value'];
        $coda_tx->coda_batch = $this->_key;
		return $coda_tx;
	}
    
    protected function parse($file_path){	
		$file_path = str_replace('\\', '/', $file_path);
		$lines = file($file_path);		
		$fileparts = explode('/', $file_path);
        $this->file = array_pop($fileparts);
		foreach($lines as $line) {
			if($line){
                $identification_record = substr($line, 0, 1);                 
                if ($identification_record=='2' || $identification_record=='3'){    // 2 = beweginsartikel ; 3 = infoartikel
                    $this->processCodaRecord($line);
                }else{
                    $this->processCodaFile($line);                    
                }                				
			}
		}		
		//return $this->codabatches;
	}   
	
	//temp
	public function getCodaFiles(){
		return $this->codabatches;
	}

	//temp
	public function getCodaRecords(){
		return $this->codabatchrecords;
	}

	      
    protected function processCodaFile($process_record){
		$identification_record = substr($process_record, 0, 1);       
		switch($identification_record) {			
			case '0':   // header record
                $this->resetmeta();
                $this->nrRecs = 0;
				$this->parseRecord0($process_record);
                break;			
			case '1':   // old balance
                $this->testSequence(1);
                $this->nrRecs += 1;
				$this->parseRecord1($process_record);
				break;			
			case '4':   // free message
				//nothing important
                $this->testSequence(4);
				break;			
			case '8':   //  new balance
                $this->testSequence(8);
                $this->nrRecs += 1;
				$this->parseRecord8($process_record);
				break;			
			case '9':   // trailer record
                $this->testSequence(9);
				$this->parseRecord9($process_record);                
				break;
			default:
				break;
		}
	}
    
    protected function processCodaRecord($process_record)	{		
		switch (substr($process_record, 0, 2)) {	//check the first 2 numbers of the record		
			case '21' : //  move article part 1
                $this->resetmeta(21);
                $this->nrRecs += 1;
				$this->parseRecord21($process_record);
				break;			
			case '22' : //  move article part 2
                $this->testSequence(22);
                $this->nrRecs += 1;
                $this->testNumbering($process_record, 22);
				$this->parseRecord22($process_record);
				break;			
			case '23' : //  move article part 3
                $this->testSequence(23);
                $this->nrRecs += 1;
                $this->testNumbering($process_record, 23);
				$this->parseRecord23($process_record);
				break;			
			case '31' : //  info article part 1
                $this->resetmeta(31);
                $this->nrRecs += 1;
				$this->parseRecord31($process_record);
				break;			
			case '32' : //  info article part 2
                $this->testSequence(32);
                $this->nrRecs += 1;
                $this->testNumbering($process_record, 32);
				$this->parseRecord32($process_record);
				break;			
			case '33' : //  info article part 3
                $this->nrRecs += 1;
                $this->testmeta('nextcode', true, 'Coda format error : wrong sequence param nextcode');
                $this->testNumbering($process_record, 33);
				$this->parseRecord33($process_record);
				break;
			default:
				break;
		}
	}
    
    protected function parseRecord0($process_record){        
        $this->codabatch = self::getCodaBatchInstance();
        $this->codabatches[] = $this->codabatch;
		$this->codabatch->status = 'new';		
        $this->codabatch->file = $this->file;        
        $this->codabatch->date_created_by_bank = $this->convertDate(substr($process_record,5,6));
		$this->codabatch->bic = trim(substr($process_record, 60, 11));        
        
        $this->_codabatch_extra = array();
        $idnr = self::filterWhiteSpace(substr($process_record, 71, 11));   //id v i belg gevestigde rekhouder : 0+ondernemersnr
        if(!empty($idnr) && $idnr!=0){
            $this->_codabatch_extra['ondernemingsnummber'] = $idnr;
        }
        $sepappl = substr($process_record,83,5);
        if($sepappl!='00000'){
            $this->_codabatch_extra['separate_application'] = $sepappl;
        }
 
        $txref = self::filterWhiteSpace(substr($process_record, 88,16));        
        if(!empty($txref)){
            $this->_codabatch_extra['transaction_reference'] = $txref;
        }
        $txrel = self::filterWhiteSpace(substr($process_record, 104, 16));
        if(!empty($txref)){
            $this->_codabatch_extra['related_reference'] = $txref;
        }
        $fileref = self::filterWhiteSpace(substr($process_record, 24, 10));
        if(!empty($fileref)){
            $this->_codabatch_extra['file_ref'] = $fileref;
        }
        $version = substr($process_record,127,1);
        if($version!=2){
            throw new Exception('Version not supported :'.$version.' expected 2');
        }
        /*
         * bankid or 0 : 11,3
         *  : 71,11
         */
        $this->codarecords = array();
        $this->setSequenceFlags(0, true, false, array(1));		
	}

	protected function parseRecord1($process_record){
		//get the structure of the bank account
		//0 = belgian account   :    1 = foreign account
		//2 = IBAN of belgian   :    3 = IBAN of foreign
		//different structures of bankaccountnumbers
		$this->codabatch->country_code = 'BE';
		switch(substr($process_record, 1, 1)){
			case 0:				
				$bankNumberTemp = substr($process_record, 5, 12);
				$this->codabatch->bban = $bankNumberTemp;
				
				//create IBAN of Belgium number
				if($this->config['bban2iban']){
					$this->codabatch->iban = $this->CodaBbanToIban(array('bban' => $bankNumberTemp));
				}
				     				
				$this->codabatch->currency = self::filterWhiteSpace(substr($process_record, 18, 3));			
				$qualificationcode = trim(substr($process_record, 21, 1));
                if(!empty($qualificationcode) && $qualificationcode!=0){
                    $this->_codabatch_extra['qualificationcode'] = $qualificationcode;
                }
				//$this->codabatch->country_code = self::filterWhiteSpace(substr($process_record, 22, 2));						
				$extensionzone = self::filterWhiteSpace(self::filterWhiteSpace(substr($process_record, 27, 15)));   
                if(!empty($extensionzone)){
                    $this->_codabatch_extra['extensionzone'] = $extensionzone;
                }
                $this->_codabatch_extra['bank_account_structure'] = 'bban';				 
				break;

			case 1:	
				$this->codabatch->country_code = null;
				$this->codabatch->bban = substr($process_record, 5, 34);
				$this->codabatch->currency = self::filterWhiteSpace(substr($process_record, 39, 3));
                $this->_codabatch_extra['bank_account_structure'] = 'foreign';				
				break;

			case 2:
				$bankNumberTemp = substr($process_record, 5, 31);
				$this->codabatch->iban = trim($bankNumberTemp);				
				
				//creates belgium number of iban
				settype($bankNumberTemp, "string");   

				$this->codabatch->bban = self::filterWhiteSpace(substr($bankNumberTemp, 4));
				////$this->codabatch->extensionzone = self::filterWhiteSpace(substr($process_record, 36, 3));
				$this->codabatch->currency = self::filterWhiteSpace(substr($process_record, 39, 3));   
                $this->_codabatch_extra['bank_account_structure'] = 'be iban';
				$this->codabatch->country_code = 'BE';				
				break;

			case 3:
				//get the bank account number of the coda file
				$this->codabatch->iban = trim(substr($process_record, 5, 34));
				$this->codabatch->country_code = substr($this->codabatch->iban, 0 ,2);
				//currency code
				$this->codabatch->currency = self::filterWhiteSpace(substr($process_record, 39, 3));
                $this->_codabatch_extra['bank_account_structure'] = 'foreign iban';				
				break;

			default:
				break;
                
		}
        
		//sign of the balance ( 0 = credit; 1 = debet )
        $signstarting_balance = substr($process_record, 42, 1);

		//get the old balance amount of the file 
		 $starting_balance = floatval(substr($process_record, 43, 14))/100;
        if($signstarting_balance==1){
            $this->codabatch->starting_balance = -1 * $starting_balance;
        }else{
            $this->codabatch->starting_balance = $starting_balance;
        }

		//get the date of the old balance
		$tempDate = substr($process_record, 58, 6);
		$this->codabatch->starting_date = $this->convertDate($tempDate);
		
		//name account holder
		$this->codabatch->name = self::filterWhiteSpace(substr($process_record, 64, 26));

		//get the sequence number of the coda file
		$this->codabatch->sequence = substr($process_record, 125, 3);
        //$this->codabatch->source .= $process_record.'/\n/';
        
        $this->setSequenceFlags(1, true, false, array(21));
	}

    //new saldo
	protected function parseRecord8($process_record){
        $signending_balance = substr($process_record, 41,1);
		//get the new balance amount of the file
		$ending_balance = floatval(substr($process_record, 42, 14))/100;
        if($signending_balance==1){
            $this->codabatch->ending_balance = -1 * $ending_balance;
        }else{
            $this->codabatch->ending_balance = $ending_balance;
        }
		//get the date of the new balance
		$tempDate = substr($process_record, 57, 6);
		$this->codabatch->ending_date = $this->convertDate($tempDate);
        //$this->codabatch->source .= $process_record.'/\n/';
		$key = $this->codabatch->iban;
        if(!empty($key)){
            $this->codabatchrecords[$this->codabatch->iban.':'.$this->codabatch->sequence] = $this->arrayCopy($this->codarecords);
        }else{
            $this->codabatchrecords[$this->codabatch->bban.':'.$this->codabatch->sequence] = $this->arrayCopy($this->codarecords);
        }
        
        
        //check
        $isnextinfo = (int)substr($process_record,127,1);  //rec //er volgt een vrij bericht (geg opname 4)
        
        $this->setSequenceFlags(8, true, false, array(4, 9));
	}
    
    //free msg
    protected function parseRecord4($process_record){
        $seqnumber = substr($process_record, 2, 4);
        $detailnr = substr($process_record, 6, 3);
        $freemsg = substr($process_record, 32, 80);       
        
        //check
        $this->setmeta('code', 8);
        $isnextinfo = (bool)substr($this->codarecord,127,1);  //rec //er volgt een vrij bericht (geg opname 4)
        if($isnextinfo){
            $this->setSequenceFlags(4, true, false, array(4));
        }else{
            $this->setSequenceFlags(4, true, false, array(9));
        }
        $this->_codabatch_extra['free_communication'] = self::filterWhiteSpace(substr($process_record, 32, 80));
	}
    
    //eindopname : test ; aantal geg opnames ; debetomzet ; creditomzet
	protected function parseRecord9($process_record){
        $this->setSequenceFlags(9, false, false, array());
        $nrrecs = substr($process_record, 16, 6);
        if($nrrecs!=$this->nrRecs){
            throw new Exception('Wrong number of process records ; counted :'.$this->nrRecs.' expected :'.$nrrecs);
        }
        $mf = substr($process_record, 127, 1);
        if($mf!=1){
            //$this->_codabatch_extra['multiple_file_code'] = $mf;
        }
        $this->codabatch->count_codarecords = count($this->codarecords);                
        
        $this->_codabatch_extra['debit_movement'] = number_format(substr($process_record,22, 12).'.'.substr($process_record, 34, 2), 2, '.', ''); 
        $this->_codabatch_extra['credit_movement'] = number_format(substr($process_record,37, 12).'.'.substr($process_record, 49, 2), 2, '.', '');
        $this->codabatch->extra = json_encode($this->_codabatch_extra);		
        
    }       

    protected function parseRecord21($process_record){                                     
        $this->codarecord = self::getCodaTxInstance();
        $this->_codarecord_extra = array();
        $this->codarecord->source = '';
		$this->codarecords[] = $this->codarecord;       
		
		$this->codarecord->sequence = $this->getSeqNr($process_record);   //get the serialnumber of the record    		
		$this->codarecord->move_detail = $this->getDetailNr($process_record);     //get the detailNumber of the moveArticle  
		
		$signmovement = substr($process_record, 31, 1); //get the sine of the movement
		$this->codarecord->amount = floatval(substr($process_record, 32, 14))/100;
        if($signmovement==1){
            $this->codarecord->amount = -1 * $this->codarecord->amount;
        }
		//get the date of the transfer
		$this->codarecord->value_date = $this->convertDate(substr($process_record, 47, 6));
        //$this->codarecord->globalisation_code = substr($process_record,124,1);
        
        $this->codarecord->booking_date = $this->convertDate(substr($process_record, 115, 6));
       //verrichtingscode
		//get the coding of the transactions
		//exists out of " 1 2 3 4 5 6 7 8 "
		//1 -> type
		$transactionstype = substr($process_record, 53, 1);

		//2 + 3 -> family
		$transactionsfamily = substr($process_record, 54, 2);

		//4 + 5 -> transaction
		$transactionstransaction = substr($process_record, 56, 2);

		//6 + 7 + 8 -> category
		$transactionsCategory = substr($process_record, 58, 3);
       // end verrichtingscode
        
       $this->codarecord->txncode = $transactionstype.
                                    $transactionsfamily.
                                    $transactionstransaction.
                                    $transactionsCategory;
		//check if its structured or not
		$check_Structured = substr($process_record, 61, 1);
		if($check_Structured == 0){
            $this->codarecord->move_struct_code = '000';
			$this->codarecord->move_msg = self::filterWhiteSpace(substr($process_record, 62, 53)). " ";
		}
		else{
			$this->codarecord->move_struct_code = substr($process_record, 62, 3);
			$this->codarecord->move_msg  = self::filterWhiteSpace(substr($process_record, 65, 50)); 
		}
        $this->codarecord->source .= $process_record."\n";
        
        //set line info params    
        $isnextmove = (bool)substr($process_record, 125,1); //rec22, 23        
        $isnextinfo = (bool)substr($process_record,127,1);  //rec31
        $expect = array(22, 23);
        $this->setSequenceFlags(21, $isnextmove, $isnextinfo, $expect);
        
        //test sequence- and detailnumber
        $newseqnr = (int)$this->getSeqNr($process_record);
        $newdetailnr = (int)$this->getDetailNr($process_record);
        if ($this->getmeta('seqnr')==$newseqnr ){
            if (($this->getmeta('detailnr')+1) != $newdetailnr){
                throw new Exception('Coda format error : wrong sequence- or detailnumber');
            }
            
        }else{
            if (($this->getmeta('seqnr')+1) != $newseqnr){               
                echo "<BR>getmeta seqnr+1=".($this->getmeta('seqnr')+1);
                echo "<BR>newseqnr =".$newseqnr;
                echo "<BR>getmeta detailnr+1=".($this->getmeta('detailnr')+1);
                echo "<BR>newdetailnr =".$newdetailnr;
                throw new Exception('Coda format error : wrong sequence- or detailnumber : seqnr+1'.var_dump($this));
                
            }
        }
        $this->setNumbering($newseqnr, $newdetailnr);
	}

	protected function parseRecord22($process_record)	{		               
		//only add whitespace after unstructured message parts
		$whitespace = "";
		if($this->codarecord->move_struct_code == 0){
			$whitespace = " ";
		}		
		//next part of the message
		$this->codarecord->move_msg .= self::filterWhiteSpace(substr($process_record, 10, 53)) . " ";
        // p 64-98 customer ref or blank
        $this->codarecord->customer_ref = self::filterWhiteSpace(substr($process_record, 63, 35));
		//BIC of the bank of the debtor
		$this->codarecord->bic = trim(substr($process_record, 98, 11));
        // p118-121 purpose
        $this->codarecord->category_purpose = self::filterWhiteSpace(substr($process_record, 117, 4));
        $this->codarecord->purpose = self::filterWhiteSpace(substr($process_record, 121, 4));
        $this->codarecord->source .= $process_record."\n";    
        
        //check
        $isnextmove = (bool)substr($process_record, 125,1); //rec23
        $isnextinfo = (bool)substr($process_record, 127,1);  //rec31
        $expect = array(23);
        $this->setSequenceFlags(22, $isnextmove, $isnextinfo, $expect);                      
	}

	protected function parseRecord23($process_record){             
		//check if it is an iban number or not
		$iban_or_not = substr($process_record, 10, 2);

		$regex = "/\d\d/";
		$regex1 = "/\d\d\d/";
		
		//tochange
		if (preg_match($regex, $iban_or_not)){
		 	//get the bank number of the transfer, only Belgian !!!
			$bankNumberTemp = substr($process_record, 10, 12);
            $parts = explode(' ', $bankNumberTemp);
            
            $this->codarecord->bban = $bankNumberTemp;
            $this->codarecord->currency = substr($process_record, 23, 3);         			

			//create IBAN of Belgium number
			if($this->config['bban2iban']){
				$this->codarecord->iban = $this->CodaBbanToIban(array('bban' => $bankNumberTemp));
			}								

		}else{
			//get the bank account number of the coda file
			$bankNumberTemp = self::filterWhiteSpace(substr($process_record, 10, 37));

			$pieces = explode(" ", $bankNumberTemp);                           

			if (!preg_match($regex1, $pieces[0])){
				$this->codarecord->iban = "";
				$this->codarecord->currency = trim($pieces[0]);
			}else{
				$this->codarecord->iban = trim($pieces[0]);
                if (isset($pieces[1]))
                    $this->codarecord->currency = trim($pieces[1]);  				
			}

			//creates belgium number of iban
			settype($bankNumberTemp, "string");	//??
            if(count($pieces)>1){
                $this->codarecord->bban = substr($pieces[0], 4);
            }else{
                $this->codarecord->bban = substr($bankNumberTemp, 4);
            }
			
			
		}
		$this->codarecord->country_code = 'BE';
		if(!empty($this->codarecord->iban)){
			$this->codarecord->country_code = substr($this->codarecord->iban, 0,2);
		}

		//get the name of the one that has done the transfer
		$this->codarecord->name = self::filterWhiteSpace(substr($process_record, 47, 35));			

		//next part of the message
		$this->codarecord->move_msg .= self::filterWhiteSpace(substr($process_record, 82, 43));
        
        //SDD structured message
		$move_msg = $this->codarecord->move_msg;			
		if($this->codarecord->move_struct_code == '127'){   			
            //$this->codarecord->debitDate = $this->convertDate(substr($move_msg, 0, 6));
			$this->_codarecord_extra['debitDate'] = $this->convertDate(substr($move_msg, 0, 6));			
			$this->_codarecord_extra['debitType'] = substr($move_msg, 6, 1);
			$this->_codarecord_extra['debitScheme'] = substr($move_msg, 7, 1);
			$this->_codarecord_extra['debitStatus'] = substr($move_msg, 8, 1);			
			$this->_codarecord_extra['creditorId'] = substr($move_msg, 9, 35);
			$this->_codarecord_extra['mandateRef'] = trim(substr($move_msg, 44, 35));
			$this->_codarecord_extra['debitCommunication'] = substr($move_msg, 79, 65);      
			                  
		}		
        $this->codarecord->source .= $process_record."\n";
		
		$this->codarecord->extra = json_encode($this->_codarecord_extra);

		
        //check
        $isnextinfo = (bool)substr($process_record,127,1);  //rec31
        $this->setSequenceFlags(23, false, $isnextinfo, array(31));
	}

	protected function parseRecord31($process_record){            
		//get the detailNumber of the infoArticle		
		$check_structured = substr($process_record, 39, 1);
		if($check_structured == 0){
            $this->codarecord->info_struct_code = '000';
			$this->codarecord->info_msg = self::filterWhiteSpace(substr($process_record, 40, 73))." ";
		}else{
			$this->codarecord->info_struct_code = substr($process_record, 40, 3);
			$this->codarecord->info_msg  = self::filterWhiteSpace(substr($process_record, 43, 70))." ";			
		}
        $this->codarecord->source .= $process_record."\n";
        
        //check
        $isnextmove = (bool)substr($process_record, 125,1); //rec32
        $isnextinfo = (bool)substr($process_record,127,1);  //rec
        $this->setSequenceFlags(31, $isnextmove, $isnextinfo, array(32));
        
        $newseqnr = (int)$this->getSeqNr($process_record);
        $newdetailnr = (int)$this->getDetailNr($process_record);
        if ((int)$this->getmeta('seqnr')!=$newseqnr || (((int)$this->getmeta('detailnr')+1) != $newdetailnr)){
            throw new Exception('Coda format error : wrong sequence- or detailnumber');
			//echo '<BR>Coda format error : wrong sequence- or detailnumber';
        }
        $this->setNumbering($newseqnr, $newdetailnr);
		
		
	}

	protected function parseRecord32($process_record){ 
		$this->codarecord->info_msg  .= self::filterWhiteSpace(substr($process_record, 10, 105));				
        $this->codarecord->source .= $process_record."\n";   
		
        //check
        $isnextmove = (bool)substr($process_record, 125,1); //rec33
        $isnextinfo = (bool)substr($process_record,127,1);  
        $this->setSequenceFlags(32, $isnextmove, $isnextinfo, array(33));
		
		//todo move this to a neutral place
		switch ($this->codarecord->info_struct_code) {
			case '001':
				$address = $this->parseAddressInfoMsg($process_record);
				$this->codarecord->zipcode = $address['postal_code'];
				$this->codarecord->city = $address['city'];
				$this->codarecord->streetsuff = $address['street_number_suffix'];
				$this->codarecord->streetnr = $address['street_number'];
				$this->codarecord->street = $address['street_name'];				
				break;

			default:
				break;
		}

	}

	protected function parseRecord33($process_record){               
		//next part of the message
		$this->codarecord->info_msg .= self::filterWhiteSpace(substr($process_record, 10, 90));
        $this->codarecord->source .= $process_record."\n";
        
        //check
        $isnextinfo = (bool)substr($process_record,127,1);  
        $this->setSequenceFlags(33, false, $isnextinfo, array(31));	
				
	}
	
	public static function parseAddressInfoMsg($process_record){     
		$streetstr = trim(self::filterWhiteSpace(substr($process_record,0,35)));     
		$original = $streetstr;
		$zipcode_city = trim(self::filterWhiteSpace(substr($process_record,35,70)));
		$parts = explode(' ', $zipcode_city);
		$zip = array_shift($parts);
		//$land = 'BE';
		if(strtolower(self::top($parts))=='nederland'){		//tochange
			//$land = 'NL';
			array_pop($parts);
		}
		foreach($parts as $i=>$part){
			if(substr($part,0,1)=='('){
				unset($parts[$i]);
			}
		}
		$city = implode(' ',$parts);
		$repl = array(',', '/');
		$streetstr = trim(self::filterWhiteSpace(str_replace($repl, ' ', $streetstr)));
		$parts = explode(' ', $streetstr);
		$cnt = count($parts);
		$streetname = $streetnr = $streetsuffix = '';
		if($cnt>0){
			$first = $parts[0];			
			if(self::hasNumber($first)){			// first streetnr then streetname
				$streetnr = array_shift($parts);
				$streetsuffixar = array();
				foreach($parts as $i=>$part){
					if(self::hasNumber($part) || in_array(strtolower($part),array('a','b','c','d','e','f','bus','boite'))){
						$streetsuffixar[] = $part;
						unset($parts[$i]);
					}else{
						break;
					}
				}
				$streetsuffix = implode(' ', $streetsuffixar);
				$streetname = implode(' ', $parts);				
			}else{									// streetnr part last
				$streetar=array();
				foreach($parts as $i=>$part){
					if(self::hasNumber($part)&& !empty($streetar)){
						break;
					}else{
						$streetar[] = $part;
						unset($parts[$i]);
					}
				}
				$streetname = implode(' ',$streetar);
				$streetnr = array_shift($parts);
				$streetsuffix = implode(' ',$parts);				
			}
		}		
		return array(
			'address_string'=> $process_record,
			'street_address'=>(empty($streetname)?null:trim(self::filterWhiteSpace($streetname.' '.$streetnr.' '.$streetsuffix))),
			'street_name'=>empty($streetname)?null:$streetname,
			'street_number'=>empty($streetnr)?null:$streetnr,
			'street_number_suffix'=>empty($streetsuffix)?null:substr($streetsuffix,0,8),
			'postal_code'=>empty($zip)?null:$zip,
			'city'=>empty($city)?null:$city,
		);		
	}
	
	protected function getSeqNr($line){
        return substr($line, 2, 4);
    }

    protected function getDetailNr($line){
        return substr($line, 6, 4);
    }

    protected function resetmeta($code=null){
        if (!is_null($code)){
            $this->meta['nextcode']=false;
            $this->meta['linkcode']=false;
            $this->meta['code']=$code;
            $this->meta['expect']=array();            
        }else{
            $this->meta=array('nextcode'=>false, 
                              'linkcode'=>false, 
                              'code'=>null, 
                              'expect'=>array(),
                              'seqnr'=>0,               //starts at 0001
                              'detailnr'=>-1,           //starts at 000
                          );
        }
        
    }

    protected function setmeta($key, $value){
        $this->meta[$key]=$value;
    }
    
    protected function getmeta($key){
        if (array_key_exists($key, $this->meta)){
            return $this->meta[$key];
        }else{
            throw new Exception('getmeta wrong key :'.$key.  var_dump($this->meta));
        }
        
    }
    
    protected function testmeta($key, $value, $msg){
        if ($this->getmeta($key)===$value){
            throw new Exception($msg);
        }
    }

    protected function setSequenceFlags($current, $next, $link, $expect){
        $this->setmeta('code', $current);
        $this->setmeta('nextcode', $next);
        $this->setmeta('linkcode', $link);
        $this->setmeta('expect', $expect);
    }

    protected function testSequence($code){
        if ($this->meta['nextcode']===true && !in_array($code, $this->meta['expect'])){
            //testing throw new Exception("Coda format error : artcode=$code ; expected next code in".  var_dump($possible_next));
			echo '<BR>'."Coda format error : artcode=$code ; expected next code in".  var_dump($possible_next);
        }
        if ($this->meta['linkcode']===true && ($code!=31)){
           // throw new Exception("Coda format error : artcode=$code ; expected next code=31");
			echo '<BR>'."Coda format error : artcode=$code ; expected next code=31";
        }
    }
    
    protected function setNumbering($seqnr, $detailnr){        
        $this->setmeta('seqnr', $seqnr);
        $this->setmeta('detailnr', $detailnr);
    }

    protected function incSeqNr(){
        $this->setmeta('seqnr', $this->getmeta('seqnr') + 1);
    }

    protected function incDetailNr(){
        $this->setmeta('detailnr', $this->getmeta('detailnr') + 1);
    }

    protected function testNumbering($line, $code=null){
        $newseqnr = (int)$this->getSeqNr($line);
        $newdetailnr = (int)  $this->getDetailNr($line);
        $seqnr = $this->getmeta('seqnr');
        $detailnr = $this->getmeta('detailnr');
        if ($seqnr != $newseqnr || $detailnr!=$newdetailnr){
            echo '<BR>prev reccode='.$this->getmeta('code');
            echo "<BR>seqnr =$seqnr , newseqnr =$newseqnr ";
            echo "<BR>detailnr =$detailnr , newdetailnr =$newdetailnr ";
           //testing throw new Exception('Coda format error : sequence- and detailnumber ; reccode='.$code);
            
        }
    }

    public static function filterWhiteSpace($string){
		$pattern = array("/^\s+/", "/\s{2,}/", "/\s+\$/");
		$replace = array("", " ", "");
		return preg_replace($pattern, $replace, $string);
	}
	
	// Convert dates(dd-mm-yy) to compatible formate (yyyy-dd-mm)
	protected function convertDate($date){
		sscanf($date, "%2s%2s%2s", $day, $month, $year);
		$date = "20".$year.'-'.$month.'-'.$day;		
		return $date;
	}
 
    public function arrayCopy(array $array ) {
        $result = array();
        foreach( $array as $key => $val ) {
            if( is_array( $val ) ) {
                $result[$key] = arrayCopy( $val );
            } elseif ( is_object( $val ) ) {
                $result[$key] = clone $val;
            } else {
                $result[$key] = $val;
            }
        }
        return $result;
    }
       
    public static function getCodaBatchInstance(){
        return new SimpleTable('civicrm_coda_batch', self::$coda_batch_fields, 'id');
    }
    
    public static function getCodaTxInstance(){
        return new SimpleTable('civicrm_coda_tx', self::$coda_tx_fields, 'id');
    }

    protected function CodaBbanToIban($params){
		if(empty($params['bban'])){
			//throw new Exception('testing : error');
			return null;
		}
		$bban = $params['bban'];
		if(!array_key_exists('bic', $params) || empty($params['bic'])){
			$bic = $this->CodaBbanToBic($bban);
		} else {
			$bic = $params['bic'];
		}
		if(!isset($bic)){
			return null;
		}
		$bban = substr(preg_replace("/\-|\ /", "", $bban), 0, 12);		
		
		$bic = substr(preg_replace("/\ /", "", $bic), 0, 8);
		$countrycode = substr($bic, 4, 2);		
		$tempban = $bban . $countrycode . "00";
		$patternArray = array("/A/","/B/","/C/","/D/","/E/","/F/","/G/","/H/","/I/","/J/",
				"/K/","/L/","/M/","/M/","/N/","/O/","/P/","/Q/","/R/","/S/","/T/","/U/",
				"/V/","/W/","/X/","/Y/","/Z/");

		$replaceArray = range(10, 35);

		//alfa waarden($patternArray) vervangen met numerieke($replaceArray)
		$tempban = preg_replace($patternArray, $replaceArray, $tempban);

		//modulo 97 aftrekken van 98
		$mod = 98 - (bcmod($tempban, "97"));
		if(strLen($mod)==1){
			$mod = "0" . $mod;
		}

		$iban = $countrycode . $mod . $bban;

		return trim($iban);
	}
	
	protected function CodaBbanToBic($bban){
		$bankidnumber = substr($bban, 0, 3);
		$table = $this->config['bic_table'];
        $sql = 'select * from '.mysql_real_escape_string($table).' where T_Identification_Number="'.mysql_real_escape_string($bankidnumber).'"';
		$bic = CRM_Core_DAO::executeQuery($sql);
        $res = $bic->fetch();		
		if(!$res) {
			//throw new Exception("Couldn't find BIC for BBAN: $bban");
			return null;
		}
        $biccode = $bic->Biccode;
		return trim($biccode);
	}    
	
	public static function top(array $ar){
		$cnt = count($ar);
		if($cnt>0){
			return $ar[$cnt-1];
		}else{
			return null;
		}
	}
	
	public static function hasNumber($str){
        if (preg_match('#[0-9]#',$str)){
            return true;
        }else{
            return false;
        }
    }
    
}