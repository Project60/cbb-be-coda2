<?php

class project{
    static $instance;
    static $root;

    static $civiDBO;
    static $codaDBO;
    
    protected function __construct() {
        $this->initialise();
    }      

    public static function getInstance(){
        if(is_null(self::$instance)){
            $path = str_replace('\\', '/', str_replace(__CLASS__, '', dirname(__FILE__)));
            require_once $path.'/settings.php';
            $tmp = explode('/', $path);
            array_pop($tmp);
            self::$root = implode('/',$tmp);              
            settings::instantiate();
            self::$instance = new project(settings::$project);            
        }
        return self::$instance;
    }

    protected function initialise(){
        self::$civiDBO = new DBO(settings::$civi);
        self::$codaDBO = new DBO(settings::$coda);
        //self::$api = civi::instance();
    }           
}