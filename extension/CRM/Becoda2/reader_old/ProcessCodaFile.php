<?php

class ProcessCodaFile{
    
    public $dbo;   
    public $codafiles=array();   //=accountstatements
    public $codafilerecords=array();

    public function __construct() {          
        $this->dbo = project::$codaDBO;
    }
   
    public function parseFile($file_path){        
        $CodaReader = new CodaReader_old($this->dbo);
        $this->codafiles = $CodaReader->parseFile($file_path);
        $this->codafilerecords = $CodaReader->getCodaRecords();
        var_dump($this->codafiles);
        var_dump($this->codafilerecords);
    }
    
    public function process($file_path){
        $this->parseFile($file_path);
        foreach ($this->codafiles as $codafile){
            $coda_batch_id = $this->get_or_create_codafile($codafile);
            //if a previous process was interrupted, clean up and renew the process
            $this->delete_codarecords($coda_batch_id);
            if(!empty($codafile->iban)){
                $key = $codafile->iban;
            }else{
                $key = $codafile->bban;
            }
            $codarecords = $this->codafilerecords[$key.':'.$codafile->sequence];
            $codarecord_qs = new dao('civicrm_coda_tx', 'codaDBO');            
            foreach($codarecords as $codarecord){
                $codarecord->coda_batch_id = $coda_batch_id;               
                $codarecord_qs->setdata($codarecord->getFields());  
                $codarecord_qs->status='NEW';
                $codarecord_qs->create();       
            }
        }
    }
    
    protected function get_or_create_codafile($codafile){
        $qs = new dao('civicrm_coda_batch', 'codaDBO');
        $qs->f('file', $codafile->file)
           ->f('sequence', $codafile->sequence)
           ->f('date_created_by_bank', $codafile->date_created_by_bank)
           ->f('source', $codafile->source);
        //$qs->f('source', $codafile->source)
        //   ->f('sequence', $codafile->sequence);
        $res = $qs->read();        
        $qs->setdata($codafile->getFields());
        $qs->status='NEW';
        if (empty($res)){            
            $pkid = $qs->create();
        }else{      
            $pkid = $res[0][$qs->getPK()];
            $qs->setdata($codafile->getData());
            $qs->update($pkid);
        }
        return $pkid;
    }
    
    protected function delete_codarecords($coda_batch_id){
        $codarecord_qs = new dao('civicrm_coda_tx', 'codaDBO');
        $codarecord_qs->f('coda_batch_id', $coda_batch_id)->delete();
    }
}