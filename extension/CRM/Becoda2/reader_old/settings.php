<?php

class settings{
    static $instance;
    static $project = 'coda processing';
    static $civi = array('dbname'=>'civibanking', 'host'=>'localhost', 'user'=>'root', 'passw'=>'');
    static $coda = array('dbname'=>'civibanking', 'host'=>'localhost', 'user'=>'root', 'passw'=>'');
    static $civi_api_path='/../administrator/components/com_civicrm/civicrm/api/class.api.php';
    static $civipath='/../administrator/components/com_civicrm/';
    static $codapath = 'data/coda';

    public static function instantiate(){
        if(!isset(self::$instance)){
            self::$instance = new settings();
        }
    }
    
    protected function __construct() {
        $this->registerClassLoader();
    }

    protected static function registerClassLoader(){        
         spl_autoload_register(array('settings', 'load'));
    }
    
    public static function load($classname){
        $path = project::$root;
        if(strpos(strtolower($classname), 'coda')!==false){
            $subdir = $path.'/Coda/';
            $res = @include_once $subdir.$classname.'.php';
        }
    }
}