<?php

class DBO{
    public $con;
    public $dbname;

    public function __construct($dbinfo=array('dbname'=>'','host'=>'localhost', 'user'=>'root', 'passw'=>'')) {
        $dbname = $dbinfo['dbname'];
        $this->dbname=$dbname;
        
        $this->con = new mysqli($dbinfo['host'], $dbinfo['user'], $dbinfo['passw'], $dbname);
        if (mysqli_connect_errno()) {
            exit('Connect failed: '. mysqli_connect_error());
        }
        $this->con->query('SET NAMES utf8 COLLATE utf8_unicode_ci');
    }
    
    public function __destruct() {
        $this->close();
    }
    
    public function close(){
        $this->con->close();
    }

    public function query($sql){
        $res = $this->con->query($sql);
        if($res===FALSE){
            echo '<BR>Error: '. $con->error;
            throw new Exception($con->error);
        }
        return $res;
    }
    
    public function lastInsertId(){
        return (mysqli_insert_id($this->con));
    }
    
    public static function &getTables($dbo){       
        $stmt = $dbo->con->query('show tables');
        $res = $stmt->fetch_all(MYSQLI_ASSOC);
        $list = array();
        foreach($res as $row){
            $list[] = $row['Tables_in_'.$dbo->dbname];
        }
        return $list;
    }
    
    public static function &getFields($dbo, $table){
        $stmt = $dbo->con->query('show columns from '.$table);        
        $res = $stmt->fetch_all(MYSQLI_ASSOC);
        return $res;
    }
    
    public static function getScheme($dbo, $table){
        $fields = self::getFields($dbo, $table);
        //var_dump($fields);
        $pk=null;
        $result=array('fields'=>array(),'pk'=>null);
        foreach($fields as $fieldinfo){
            $fieldname = $fieldinfo['Field'];
            if($fieldinfo['Key']=='PRI'){
                $result['pk'] = $fieldname;
            }
            $result['fields'][$fieldname] = $fieldinfo;
           
        }
        return $result;
    }

    public static function &getSchemes($dbo){
        $schemes = array();
        $tables = self::getTables($dbo);
        foreach($tables as $table){
            if (!array_key_exists($table, $schemes)){
                $schemes[$table] = array();
                $p = &$schemes[$table];
            }
            $fields = self::getFields($dbo, $table);
            foreach($fields as $fieldinfo){
                $p[$fieldinfo['Field']] = &$fieldinfo;
            }
             
        }
        return $schemes;
    }

    public static function convert($dbo, $table, $charset, $collate){
        $res =$dbo->query("alter table `$table` convert to character set $charset COLLATE $collate");
    }
    
    public static function addField($dbo, $table, $fieldinfo){
        $fields = self::getFields($dbo, $table);
        $fname = $fieldinfo['Field'];
        $index = null;
        foreach($fields as $i=>$info){
            if($info['Field']==$fname){
                $index = $i;
            }
        }
        if(is_null($index)){
            unset($fieldinfo['Field']);
            $arval = array_values($fieldinfo);
            $sql = "alter table $table add $fname ".implode(' ', $arval);
            echo '<BR>'.$sql;
            $dbo->query($sql);
        }
    }
    
    public static function renameField($dbo, $table, $fieldname, $newfieldname, $type){
        $sql = "alter table `$table` change `$fieldname` `$newfieldname` $type";
        echo '<BR>'.$sql;
        $dbo->query($sql);
    }

    public static function paramstr($params, $sep=','){
        $str = '';
        foreach($params as $f=>$v){
            if(!empty($v)){
                $str .= "$f='".mysql_real_escape_string($v)."'".$sep;
            }           
        }
        return trim($str,$sep);
    }
}

