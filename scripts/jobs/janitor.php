<?php

use \Xi\Janitor;

// Define path to application directory
defined('APPLICATION_PATH')
    || define('APPLICATION_PATH',
              realpath(dirname(__FILE__) . '/../../application'));

// // Define application environment
defined('APPLICATION_ENV')
    || define('APPLICATION_ENV',
              (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV')
                                         : 'production'));
                                         
set_include_path(realpath(APPLICATION_PATH . '/../library'));

try {
    
    require_once "Zend/Application.php";

    // Create application
    $application = new \Zend_Application(
        APPLICATION_ENV,
        APPLICATION_PATH . '/configs/application.ini'
    );

    // Set default bootstraps (for all janitors)
    Janitor\AbstractJanitor::setDefaultBootstraps(array());

    // Add namespaces
    Janitor\AbstractJanitor::addNamespace('\My\Janitor');

    // Create janitor from args
    $janitor = Janitor\AbstractJanitor::createJanitorFromArguments($application, $argv);
    
    // Initialize and inject log for janitor    
    $log = new \Zend_Log();
    $log->addWriter(new \Zend_Log_Writer_Stream(realpath(APPLICATION_PATH . '/../data/logs') . '/janitor.log'));
    $janitor->setLog($log);
    
    $ret = $janitor->run();
            
} catch(Exception $e) {

    echo $e;
    
    die('Janitor fails');
}

            

