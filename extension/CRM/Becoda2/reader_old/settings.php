<?php

class settings{
    static $instance;
    static $project = 'coda processing';
    static $civi = array('dbname'=>'dev_msliga_civi', 'host'=>'127.0.0.1', 'user'=>'root', 'passw'=>'');
    static $coda = array('dbname'=>'dev_msliga_civi', 'host'=>'127.0.0.1', 'user'=>'root', 'passw'=>'');
    static $civi_api_path='/var/www/msliga-civi/sites/all/modules/civicrm/api/class.api.php';
    static $civipath='/var/www/msliga-civi/sites/all/modules/civicrm/';
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
        /*
        if(strpos(strtolower($classname), 'coda')!==false){
            $subdir = $path.'/Coda/';
            $res = @include_once $subdir.$classname.'.php';
        }
         * 
         */
    }
}