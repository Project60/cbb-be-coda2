<?php 
/*
 *  A file for each account (reporting is done in the account currency) 
 *  will be generated and sent each banking working day when there has been a movement.
 * 
 *  A codabatch can have more then one account statement (every account statement is a new codabatch record)
 *  it is determinated by a sequence number (and filename)
 *  Each account statement has it own codarecords.
 *  A codarecord is determinated by the codabatch sequence number, its own sequence number and detailnumber
 * 
 */
require_once 'DBO.php';
require_once 'CodaBbanToBic.php';
require_once 'CodaBbanToIban.php';
//require_once 'Debug.php';

class CodaReader_old{   
    public $codabatches = array();
    public $codabatch;
    public $codarecord;
    public $codarecords = array();
    public $codabatchrecords = array();
    static $codabatchscheme;
    static $codarecordscheme;
    public $dbo;

    protected $nrRecs;
    protected $_codabatch_extra = array();
    protected $_codarecord_extra = array();
    protected $meta;        //array('nextcode'=>null, 
    //                              'linkcode'=>null, 
    //                              'code'=>null, 
    //                              'expected'=>array(),
    //                              'seqnr'=>null,
    //                              'detailnr'=>null);

    public function __construct($dbo) {    //$dbinfo=array('dbname'=>'','host'=>'localhost', 'user'=>'root', 'passw'=>'')
        $this->dbo = $dbo;
        self::$codabatchscheme = DBO::getScheme($this->dbo, 'civicrm_coda_batch');
        self::$codarecordscheme = DBO::getScheme($this->dbo, 'civicrm_coda_tx');
        $this->resetmeta();
    }
    
    public function parse($string){
		$lines = preg_split('/\n/', $string);		
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
//		print_r($this->codabatches);
		return $this->codabatches;
	}
        
    public function parseFile($file_path){
        $this->file = $file_path;        
		return $this->parse(file_get_contents(trim($file_path)));
	}
    
    protected function processCodaFile($process_record){
//      echo '<br/>', ($process_record);
		$identification_record = substr($process_record, 0, 1);       
		switch($identification_record) {			
			case '0':   // header record
                $this->resetmeta();
                $this->nrRecs = 0;
				$this->processRecord0($process_record);
                break;			
			case '1':   // old balance
                $this->testSequence(1);
                $this->nrRecs += 1;
				$this->processRecord1($process_record);
				break;			
			case '4':   // free message
				//nothing important
                $this->testSequence(4);
				break;			
			case '8':   //  new balance
                $this->testSequence(8);
                $this->nrRecs += 1;
				$this->processRecord8($process_record);
				break;			
			case '9':   // trailer record
                $this->testSequence(9);
				$this->processRecord9($process_record);                
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
				$this->processRecord21($process_record);
				break;			
			case '22' : //  move article part 2
                $this->testSequence(22);
                $this->nrRecs += 1;
                $this->testNumbering($process_record, 22);
				$this->processRecord22($process_record);
				break;			
			case '23' : //  move article part 3
                $this->testSequence(23);
                $this->nrRecs += 1;
                $this->testNumbering($process_record, 23);
				$this->processRecord23($process_record);
				break;			
			case '31' : //  info article part 1
                $this->resetmeta(31);
                $this->nrRecs += 1;
				$this->processRecord31($process_record);
				break;			
			case '32' : //  info article part 2
                $this->testSequence(32);
                $this->nrRecs += 1;
                $this->testNumbering($process_record, 32);
				$this->processRecord32($process_record);
				break;			
			case '33' : //  info article part 3
                $this->nrRecs += 1;
                $this->testmeta('nextcode', true, 'Coda format error : wrong sequence param nextcode');
                $this->testNumbering($process_record, 33);
				$this->processRecord33($process_record);
				break;
			default:
				break;
		}
	}
    
    protected function processRecord0($process_record){
        $scheme_array = self::$codabatchscheme;
        $fieldlist = array_keys($scheme_array['fields']);
		$this->codabatch = new SimpleTable('civicrm_coda_batch', $fieldlist, $scheme_array['pk']);	
        $this->codabatches[] = $this->codabatch;
        $this->codabatch->source = $process_record.'/\n/';
        //$this->codabatch->file = $this->file;        
        $this->codabatch->date_created_by_bank = $this->convertDate(substr($process_record,5,6));
		$this->codabatch->bic = trim(substr($process_record, 60, 11));        
        
        $this->_codabatch_extra = array();
        $idnr = $this->filterWhiteSpace(substr($process_record, 71, 11));   //id v i belg gevestigde rekhouder : 0+ondernemersnr
        if(!empty($idnr) && $idnr!=0){
            $this->_codabatch_extra['ondernemingsnummber'] = $idnr;
        }
        $sepappl = substr($process_record,83,5);
        if($sepappl!='00000'){
            $this->_codabatch_extra['separate_application'] = $sepappl;
        }
 
        $txref = $this->filterWhiteSpace(substr($process_record, 88,16));        
        if(!empty($txref)){
            $this->_codabatch_extra['transaction_reference'] = $txref;
        }
        $txrel = $this->filterWhiteSpace(substr($process_record, 104, 16));
        if(!empty($txref)){
            $this->_codabatch_extra['related_reference'] = $txref;
        }
        $fileref = $this->filterWhiteSpace(substr($process_record, 24, 10));
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

	protected function processRecord1($process_record){
		//get the structure of the bank account
		//0 = belgian account   :    1 = foreign account
		//2 = IBAN of belgian   :    3 = IBAN of foreign
		//different structures of bankaccountnumbers
		switch(substr($process_record, 1, 1)){
			case 0:
				$bankNumberTemp = substr($process_record, 5, 12);
				$this->codabatch->bban = $bankNumberTemp;

				//create IBAN of Belgium number
				$filter = new CodaBbanToIban();
				$this->codabatch->iban = trim($filter->filter(array('bban' => $bankNumberTemp)));               
				$this->codabatch->currency = $this->filterWhiteSpace(substr($process_record, 18, 3));
				$qualificationcode = trim(substr($process_record, 21, 1));
                if(!empty($qualificationcode) && $qualificationcode!=0){
                    $this->_codabatch_extra['qualificationcode'] = $qualificationcode;
                }
				$this->codabatch->country_code = $this->filterWhiteSpace(substr($process_record, 22, 2));
				$extensionzone = $this->filterWhiteSpace($this->filterWhiteSpace(substr($process_record, 27, 15)));   
                if(!empty($extensionzone)){
                    $this->_codabatch_extra['extensionzone'] = $extensionzone;
                }
                $this->_codabatch_extra['bank_account_structure'] = 'bban';
				break;

			case 1:
				$this->codabatch->bban = substr($process_record, 5, 34);
				$this->codabatch->currency = $this->filterWhiteSpace(substr($process_record, 39, 3));
                $this->_codabatch_extra['bank_account_structure'] = 'foreign';
				break;

			case 2:
				$bankNumberTemp = substr($process_record, 5, 31);
				$this->codabatch->iban = trim($bankNumberTemp);

				//creates belgium number of iban
				settype($bankNumberTemp, "string");   

				$this->codabatch->bban = substr($bankNumberTemp, 4);
				////$this->codabatch->extensionzone = $this->filterWhiteSpace(substr($process_record, 36, 3));
				$this->codabatch->currency = $this->filterWhiteSpace(substr($process_record, 39, 3));   
                $this->_codabatch_extra['bank_account_structure'] = 'b iban';
				break;

			case 3:
				//get the bank account number of the coda file
				$this->codabatch->bban = trim(substr($process_record, 5, 34));
				//currency code
				$this->codabatch->currency = $this->filterWhiteSpace(substr($process_record, 39, 3));
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
		$this->codabatch->name = $this->filterWhiteSpace(substr($process_record, 64, 26));

		//get the sequence number of the coda file
		$this->codabatch->sequence = substr($process_record, 125, 3);
        $this->codabatch->source .= $process_record.'/\n/';
        
        $this->setSequenceFlags(1, true, false, array(21));
	}

    //new saldo
	protected function processRecord8($process_record){
        ////$this->codabatch->signNewBalance = substr($process_record, 41,1);
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
        $this->codabatch->source .= $process_record.'/\n/';

        if(!empty($this->codabatch->iban)){
            $this->codabatchrecords[$this->codabatch->iban.':'.$this->codabatch->sequence] = $this->arrayCopy($this->codarecords);
        }else{
            $this->codabatchrecords[$this->codabatch->bban.':'.$this->codabatch->sequence] = $this->arrayCopy($this->codarecords);
        }
        
        
        //check
        $isnextinfo = (int)substr($process_record,127,1);  //rec //er volgt een vrij bericht (geg opname 4)
        
        $this->setSequenceFlags(8, true, false, array(4, 9));
	}
    
    //free msg
    protected function processRecord4($process_record){
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
        $this->_codabatch_extra['free_communication'] = $this->filterWhiteSpace(substr($process_record, 32, 80));
	}
    
    //eindopname : test ; aantal geg opnames ; debetomzet ; creditomzet
	protected function processRecord9($process_record){
        $this->setSequenceFlags(9, false, false, array());
        $nrrecs = substr($process_record, 16, 6);
        if($nrrecs!=$this->nrRecs){
            throw new Exception('Wrong number of process records ; counted :'.$this->nrRecs.' expected :'.$nrrecs);
        }
        $mf = substr($process_record, 127, 1);
        if($mf!=1){
            $this->_codabatch_extra['multiple_file_code'] = $mf;
        }

        $this->_codabatch_extra['debit_movement'] = number_format(substr($process_record,22, 12).'.'.substr($process_record, 34, 2), 2, '.', ''); 
        $this->_codabatch_extra['credit_movement'] = number_format(substr($process_record,37, 12).'.'.substr($process_record, 49, 2), 2, '.', '');
        $this->codabatch->extra = json_encode($this->_codabatch_extra);
        
    }       

    protected function processRecord21($process_record){                              
        
        $scheme_array = self::$codarecordscheme;
        $fieldlist = array_keys($scheme_array['fields']);        
        
		$this->codarecord = new SimpleTable('civicrm_coda_tx', $fieldlist, $scheme_array['pk']);
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
		//$this->codarecord->move_is_structured = $check_Structured;
		if($check_Structured == 0){
            $this->codarecord->move_structured_code = '000';
			$this->codarecord->move_message = $this->filterWhiteSpace(substr($process_record, 62, 53)). " ";
		}
		else{
			$this->codarecord->move_structured_code = substr($process_record, 62, 3);
			$this->codarecord->move_message  = $this->filterWhiteSpace(substr($process_record, 65, 50)); 
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

	protected function processRecord22($process_record)	{		               
		//only add whitespace after unstructured message parts
		$whitespace = "";
		if($this->codarecord->move_structured_code == 0){
			$whitespace = " ";
		}		
		//next part of the message
		$this->codarecord->move_message .= $this->filterWhiteSpace(substr($process_record, 10, 53)) . " ";
        // p 64-98 customer ref or blank
        $this->codarecord->customer_ref = $this->filterWhiteSpace(substr($process_record, 63, 35));
		//BIC of the bank of the debtor
		$this->codarecord->bic = trim(substr($process_record, 98, 11));
        // p118-121 purpose
        $this->codarecord->category_purpose = $this->filterWhiteSpace(substr($process_record, 117, 4));
        $this->codarecord->purpose = $this->filterWhiteSpace(substr($process_record, 121, 4));
        $this->codarecord->source .= $process_record."\n";    
        
        //check
        $isnextmove = (bool)substr($process_record, 125,1); //rec23
        $isnextinfo = (bool)substr($process_record, 127,1);  //rec31
        $expect = array(23);
        $this->setSequenceFlags(22, $isnextmove, $isnextinfo, $expect);
        
        //test sequence- and detailnumber       
        
        
	}


	protected function processRecord23($process_record){             
		//check if it is an iban number or not
		$iban_or_not = substr($process_record, 10, 2);

		$regex = "/\d\d/";
		$regex1 = "/\d\d\d/";

		if (preg_match($regex, $iban_or_not)){
		 	//get the bank number of the transfer, only Belgian !!!
			$bankNumberTemp = substr($process_record, 10, 12);
            $parts = explode(' ', $bankNumberTemp);
            
            $this->codarecord->bban = $bankNumberTemp;
            $this->codarecord->currency = substr($process_record, 23, 3);         			

			//create IBAN of Belgium number
			$filter = new CodaBbanToIban();
			$this->codarecord->iban = trim($filter->filter(array('bban' => $bankNumberTemp)));			            

		}else{
			//get the bank account number of the coda file
			$bankNumberTemp = $this->filterWhiteSpace(substr($process_record, 10, 37));

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
			settype($bankNumberTemp, "string");
            if(count($pieces)>1){
                $this->codarecord->bban = substr($pieces[0], 4);
            }else{
                $this->codarecord->bban = substr($bankNumberTemp, 4);
            }
			
		}

		//get the name of the one that has done the transfer
		$this->codarecord->name = $this->filterWhiteSpace(substr($process_record, 47, 35));

		//next part of the message
		$this->codarecord->move_message .= $this->filterWhiteSpace(substr($process_record, 82, 43));
        
        //SDD structured message
		$move_message = $this->codarecord->move_message;			
		if($this->codarecord->move_structured_code == '127'){   
            //$this->codarecord->debitDate = $this->convertDate(substr($move_message, 0, 6));
			$this->_codarecord_extra['debitDate'] = $this->convertDate(substr($move_message, 0, 6));			
			$this->_codarecord_extra['debitType'] = substr($move_message, 6, 1);
			$this->_codarecord_extra['debitScheme'] = substr($move_message, 7, 1);
			$this->_codarecord_extra['debitStatus'] = substr($move_message, 8, 1);			
			$this->_codarecord_extra['creditorId'] = substr($move_message, 9, 35);
			$this->_codarecord_extra['mandateRef'] = trim(substr($move_message, 44, 35));
			$this->_codarecord_extra['debitCommunication'] = substr($move_message, 79, 65);
            $this->codarecord->extra = json_encode($this->_codarecord_extra);
		}
        $this->codarecord->source .= $process_record."\n";
        
        //check
        $isnextinfo = (bool)substr($process_record,127,1);  //rec31
        $this->setSequenceFlags(23, false, $isnextinfo, array(31));
	}

	protected function processRecord31($process_record){            
		//get the detailNumber of the infoArticle		
		$check_structured = substr($process_record, 39, 1);
       // $this->codarecord->info_is_structured = $check_structured;
		if($check_structured == 0){
            $this->codarecord->info_structured_code = '000';
			$this->codarecord->info_message = $this->filterWhiteSpace(substr($process_record, 40, 73))." ";
		}else{
			$this->codarecord->info_structured_code = substr($process_record, 40, 3);
			$this->codarecord->info_message  = $this->filterWhiteSpace(substr($process_record, 43, 70))." ";
		}
        $this->codarecord->source .= $process_record."\n";
        
        //check
        $isnextmove = (bool)substr($process_record, 125,1); //rec32
        $isnextinfo = (bool)substr($process_record,127,1);  //rec
        $this->setSequenceFlags(31, $isnextmove, $isnextinfo, array(32));
        
        $newseqnr = (int)$this->getSeqNr($process_record);
        $newdetailnr = (int)$this->getDetailNr($process_record);
        if ((int)$this->getmeta('seqnr')!=$newseqnr || (((int)$this->getmeta('detailnr')+1) != $newdetailnr)){
            //testing throw new Exception('Coda format error : wrong sequence- or detailnumber');
        }
        $this->setNumbering($newseqnr, $newdetailnr);
	}

	protected function processRecord32($process_record){                
		$regex = "/\d/";
		$regex1 = "/^([0-9]*)/";                       
		$streetname_number = trim($this->filterWhiteSpace(substr($process_record, 10, 35)));        
		$zipcode_city = $this->filterWhiteSpace(substr($process_record, 45, 70));

		$this->codarecord->info_message  .= $streetname_number." ".$zipcode_city." ";

		$tempArray = array();
		preg_match($regex1, $zipcode_city, $tempArray);

		//length of the string postal_code and cut the postal_code out of the string
		$zip = substr($zipcode_city, 0, strlen($tempArray[0]));
		$city = trim(substr($zipcode_city, strlen($tempArray[0])));
        if($zip>=1000 && $zip<9999){
            $this->codarecord->postal_code = $zip;
        }
        if(strlen($city)>2){
            $this->codarecord->city = $city;
        }		
        
        
		//check if there is an streetname given and a streetnumber
		if((!is_null($streetname_number)) || !empty($streetname_number)){
			//get the address, split them up and place the values in the fields
            $streetname_number = str_replace(array(',','/HOME'), ' ', $streetname_number);
            $streetname_number = $this->filterWhiteSpace(str_replace(explode(' ',$zipcode_city), '', $streetname_number));
			$pieces = explode(' ', $streetname_number);            
			$length_pieces = count($pieces);           
            $number='';

            if(self::hasNumber($pieces[0]) || strlen($pieces[0])<2){
                foreach($pieces as $i=>$part){
                    if(self::hasNumber($part)){ // || strlen($part)<2
                        $number .= $part.' ';
                        unset($pieces[$i]);
                    }
                }
                $this->codarecord->streetnumber = trim(str_replace('//','/',$number),' /');
                $sname = trim(implode(' ',$pieces), ' /');
                if(strlen($sname)>2){
                    $this->codarecord->streetname = $sname;
                }
                
            }elseif(self::hasNumber($pieces[$length_pieces-1]) || strlen($pieces[$length_pieces-1])<3){
                while(1){
                    $top = array_pop($pieces);
                    $strl = strtolower($top);
                    if(self::hasNumber($top) || strlen($top)<3 || $strl=='bus' || $strl=='bis' || $strl=='gvl'){
                        $number = trim($top.' '.$number);
                    }elseif($top==''){
                        continue;
                    }else{
                        $this->codarecord->streetnumber = $this->filterWhiteSpace(str_replace('//','/',$number),' /');
                        $sname = trim(implode(' ',$pieces).' '.$top,' /');
                        if(strlen($sname)>2){
                            $this->codarecord->streetname = $sname;
                        }                      
                        break;
                    }
                    if(is_null($top)){
                        //echo '<BR>'.'top is null !!!!';
                        break;
                    }

                }                    
            }elseif(strpos(strtolower($streetname_number), strtolower($this->codarecord->name))===false){
                $this->codarecord->streetname = implode(' ', $pieces);
            }                          			
		}        

        $this->codarecord->source .= $process_record."\n";
        
        //check
        $isnextmove = (bool)substr($process_record, 125,1); //rec33
        $isnextinfo = (bool)substr($process_record,127,1);  
        $this->setSequenceFlags(32, $isnextmove, $isnextinfo, array(33));

	}

	protected function processRecord33($process_record){               
		//next part of the message
		$this->codarecord->info_message .= $this->filterWhiteSpace(substr($process_record, 10, 90));
        $this->codarecord->source .= $process_record."\n";
        
        //check
        $isnextinfo = (bool)substr($process_record,127,1);  //rec //er volgt een info artikel (gegevensopname 3)
        $this->setSequenceFlags(33, false, $isnextinfo, array(31));
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
        }
        if ($this->meta['linkcode']===true && ($code!=31)){
           // throw new Exception("Coda format error : artcode=$code ; expected next code=31");
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

    public function filterWhiteSpace($string){
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

    public function getCodaFiles(){
        return $this->codabatches;
    }
    
    public function getCodaRecords(){
        return $this->codabatchrecords;
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
    
    public static function hasNumber($str){
        if (preg_match('#[0-9]#',$str)){
            return true;
        }else{
            return false;
        }
    }
}