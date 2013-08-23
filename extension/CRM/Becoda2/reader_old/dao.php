<?php
/*
 * $d = new dao('leden');
 * 
 */

class dao{
    
    public $table;
    public $fields;
    public $scheme;

    public $pk;
    public $dbo;
    public $data=array();
    public $filter=array();
    protected $prepq=array();

    public function __construct($table='', $dbo_name='dbo'){            
        $this->dbo = project::$$dbo_name;
        $this->table = $table;
        $this->init();
        $this->prepare();
    }
    
    protected function prepare(){
        $con = $this->dbo->con;
        $sql = "select * from ".  $this->table." where ".$this->pk."=?";
        $this->prepq['getRec'] = $con->prepare($sql);        
    }           

    public function init(){
        if(!isset($this->scheme)){
            $this->scheme = DBO::getScheme($this->dbo, $this->table);
        }
        $this->pk = $this->scheme['pk'];
        $fields = $this->scheme['fields'];
        $this->fields = array(); 
        foreach($fields as $fieldname => $info){
            $this->fields[$fieldname] = null;
        }
        
    }

    public function __get($field){
        $this->getField($field);
    }
    
    public function __set($field, $value) {
        $this->setField($field, $value);
    }
    
    public function getRec($pkvalue){
        $stmt = $this->prepq['getRec'];
        $res = $stmt->bind_param('i', $pkvalue);
        $res = $stmt->execute();
        $res = $stmt->get_result();
        $rec = $res->fetch_assoc();
        return $rec;
    }
    
    public function getPK(){
        return $this->pk;
    }
    
    public function getData(){
        return $this->data;
    }

    public function setField($field, $value){
        if (array_key_exists($field, $this->fields)){
            $this->data[$field] = $value;
        }else{
            var_dump($this->fields);
            var_dump($this->data);
            die();
            throw new Exception("setField :$field, does not exists");
        }
        
    }

    public function getField($field){
        if (array_key_exists($field, $this->data)){
            return $this->data[$field];
        }else{
            throw new Exception("getField :$field, does not exists");
        }
    }

    public function reset(){
        //unset($this->data);
        $this->data = array();
        //unset($this->filter);
        $this->filter = array();
    }
    
    public function setdata($data){
        $this->reset();
        foreach($data as $field => $value){
            $this->setField($field, $value);
        }
        return $this;
    }

    //set filter
    public function f($field, $value, $op='='){
        $this->filter[]=array('field'=>$field, 'value'=>$value, 'op'=>$op);
        return $this;
    }
    
    public function setFilters($filters){
        foreach($filters as $f=>$v){
            $this->f($f,$v);
        }
        return $this;
    }

    //get filter string
    protected function fstring(){
        $str=(empty($this->filter)?'':' where ');
        foreach ($this->filter as $f){
            $value = $f['value'];
            if(is_array($value)){
                if(!empty($value)){
                    $str .= "`{$f['field']}` in (";
                    foreach($value as $v){
                        $str .= "'".$v."',";
                    }
                    $str = trim($str,',').") AND";
                }
            }elseif(strpos(strtolower($f['op']),'null')!==false){
                $str .= "`{$f['field']}` {$f['op']} AND";
            }else{
                $str .= "`{$f['field']}` {$f['op']} '". mysql_real_escape_string($f['value'])."' AND ";
            }
            
        }
        return trim($str,' AND ');
    }

    //return empty, list of records or 1 record if limit==1
    public function read($limit=0){
        $sql = 'select * from `'.$this->table .'` '.$this->fstring().($limit>0?' limit '.$limit:'');   
        //echo '<BR>'.$sql;
        $rs = $this->dbo->query($sql);
        if ($rs===false){
            echo '<BR>'.$sql;
            throw new Exception('error in dao read ');
        }
        if($limit==1){
            return $rs->fetch_assoc();
        }else{
            return $rs->fetch_all(MYSQLI_ASSOC);
        }                      
    }

        //store to db
    public function save(){
        $pk = $this->getPK();
        if (isset($pk) && isset($this->data[$pk]) && !empty($this->data[$pk])){
            $this->update($this->data[$pk]);
            return $this->data[$pk];
        }else{
            return $this->create();
        }           
    }
    
    public function create(){
        $pk = $this->getPK();
        $this->setCreated();
        $this->setModified();
        $sql = 'insert into `'.$this->table.'` set '.self::paramstr($this->data, $pk);
        //echo '<BR>'.$sql;
        $rs  = $this->dbo->query($sql);
        if ($rs===false){
            echo '<BR>sql :'.$sql;
            throw new Exception('db insert error'.get_class($this).' '.var_dump($this->dbo->errorInfo()));
        }
        $id = $this->dbo->lastInsertId();
        return $id;
    }
    
    protected function setCreated(){
        if (array_key_exists('created', $this->fields)){
            $this->data['created'] = date('Y-m-d H:i:s');
        }
    }

    protected function setModified(){
        if (array_key_exists('modified', $this->fields)){
            $this->data['modified'] = date('Y-m-d H:i:s');
        }
    }
    
    //todo updateAll

    public function update($id=null){
        $pk = $this->getPK();
        if(!isset($id)){
            $id = $this->getField($pk);
        }
        if(empty($id)&&$id!=0){
            var_dump($this);
            throw new Exception('error update : '.$pk.' is not set');
        }
        $this->setModified();       
                
        $sql = 'update '.$this->table.' set '.self::paramstr($this->data, $pk);  
        if(is_array($id)){
            $sql .= " where `$pk` in (".implode(',', $id).")";
        }else{
            $sql .= " where `$pk`=".$id;
        }
        //echo '<BR>'.$sql;
        $rs = $this->dbo->query($sql);
        if ($rs===false){
            throw new Exception('db update error'.get_class($this).' '.var_dump($this->dbo->errorInfo()));
        }
    }
    
    public function delete(){
        if(!empty($this->filter)){
            $sql = 'delete from '.$this->table.' '.$this->fstring();
            $this->dbo->query($sql);
        }
    }

    public function insertOrUpdate(){
        $res = $this->read();
        if ($res===false){
            throw new Exception('db read error '.  get_class($this).' '.var_dump($this->dbo->errorInfo()));
        }elseif (empty($res)){
            $this->insert();
        }else{
            $pk = $this->getPK();
            $this->update($res[$pk]);
        }
    }
    
    //returns id
    public function getOrCreate(){
        $res = $this->read();
        if ($res===false){
            throw new Exception('db read error '.  get_class($this).' '.var_dump($this->dbo->errorInfo()));
        }elseif (empty($res)){
            $res = $this->insert();           
        }else{
            $res = $res[$this->getPK()];
        }
        return $res;
    }
    
    public static function getInstance($table){
        $daoclass = 'dao'.ucfirst($table);
        if(!class_exists($daoclass)){
            $instance = new dao('',$table);
        }else{
            $instance = new $daoclass();
        }
        return $instance;
    }
    
    public static function paramstr($data, $pk){
        $str = '';
        foreach ($data as $k => $v){
            if($k!=$pk){
                $str .= "`$k`='". mysql_real_escape_string($v)."', ";
            }           
        }
       return trim($str,', ');
    }
   
}