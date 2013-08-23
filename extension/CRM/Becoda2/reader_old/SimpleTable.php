<?php
/*
 * 
 */
class SimpleTable{
    
    protected $fields = array();
    protected $simpletablename = '';
    protected $_pk=NULL;

    public function __construct($simpletablename, $fields, $pkname=NULL) {
        $this->simpletablename = $simpletablename;
        foreach ($fields as $fieldname){
            $this->fields[$fieldname] = NULL;
        }
        $this->_pk = $pkname;
    }
    
    public function getTableName(){
        return $this->simpletablename;
    }

    public function __get($name) {
        if (array_key_exists($name, $this->fields)){
            return $this->fields[$name];
        }else{
            throw new Exception('field :'.$name.' does not exists');
        }
    }
    
    public function __set($name, $value) {
        if (array_key_exists($name, $this->fields)){
            $this->fields[$name] = $value;
        }else{
            throw new Exception('field :'.$name.' does not exists in '.$this->getTableName());
        }
    }
    
    public function getPK(){
        return $this->_pk;
    }


    public function unsetfield($name){
        if (array_key_exists($name, $this->fields)){
            unset($this->fields[$name]);
        }
    }


    public function setFields(array $fieldarray){
        $this->fields = $fieldarray;
    }

    // return assoc array(fieldname=>value,..) with fieldname in fieldnameslist
    public function selectfields(array $fieldnameslist){
        return array_intersect_key($this->fields, array_flip($fieldnameslist));
    }
    
    // field = (fieldname=>value)
    public function insertvalues(array $fieldlist){
        foreach ($fieldlist as $fieldname => $value){
            $this->$fieldname = $value;
        }
    }

    public function removeNullFields(){
        foreach ($this->fields as $fieldname => $value){
            if ($value===NULL){
                unset($this->fields[$fieldname]);
            }
        }
    }

    public function getFields($fieldlist=array()){
        if (empty($fieldlist)){
            //return $this->fields;
            
            foreach($this->fields as $f=>$v){
                if(!is_null($v)){
                    $fieldlist[] = $f;
                }
            }
        }
        return $this->selectfields($fieldlist);
        
    }
    
    public function getData(){
        return $this->fields;
    }

    public function fields(){
        return $this->fields;
    }
    
    public function toArray(){
        return $this->fields;
    }
    
    // $fieldnamemap = array (fieldnamefrom, fieldnameto)
    public function copyfrom(SimpleTable $table, array $fieldnamemap){
        $selectedfields = $table->selectfields(array_keys($fieldnamemap));
        foreach ($selectedfields as $fieldname => $value){
            $this->$fieldnamemap[$fieldname] = $value;
        }
    }
}
