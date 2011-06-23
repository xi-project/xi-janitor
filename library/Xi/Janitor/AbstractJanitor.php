<?php 
/**
 * Xi
 *
 * @category Xi
 * @package  Janitor
 * @license  http://www.opensource.org/licenses/BSD-3-Clause New BSD License
 */

namespace Xi\Janitor;

/**
 * 
 * Abstract base janitor class.
 * 
 * @author pekkis
 *
 */
abstract class AbstractJanitor
{
    /**
     * @var \Zend_Application
     */
    private $application;
    
    /**
     * Log
     * 
     * @var \Zend_Log
     */
    private $log;
        
    
    /**
     * Array of default bootstraps common for all janitors
     * 
     * @var array
     */
    protected static $defaultBootstraps = array();
    
    /**
     * Array of bootstraps for a specific janitor
     * 
     * @var array
     */
    protected $bootstraps = array();

    
    /**
     * Array of console options for all janitors
     * 
     * @var array
     */
    protected static $defaultOptions = array(
        
    );
        
    /**
     * Array of console options for a specific janitor
     * 
     * @var array
     */
    protected $options = array();
    
    
    protected static $namespaces = array();
    
    
    protected $lock = false;
    
    protected $oneInstance = false;
        
    /**
     * Runs a janitor job
     * 
     * @throws \Motors\Janitor\JanitorException On failure 
     */
    abstract protected function runner();
           
    
    public function __construct(\Zend_Application $application, $arguments = array())
    {
        $this->application = $application;
        
        // Merge bootstraps and bootstrap em
        $bootstraps = array_merge(self::getDefaultBootstraps(), $this->bootstraps);
        foreach ($bootstraps as $bootstrap) {
            $application->getBootstrap()->bootstrap($bootstrap);
        }
        
        $options = array_merge(self::$defaultOptions, $this->options);
        
        $opts = new \Zend_Console_Getopt($options, $arguments);
        try {
            $opts->parse();
        } catch (Zend_Console_Getopt_Exception $e) {
            die( $opts->getUsageMessage() );
        }
        
        $this->opts = $opts;
        $this->init();
    }

    
    /**
     * Returns opts
     * 
     * @return \Zend_Console_Getopt
     */
    public function getOpt()
    {
        return $this->opts;
    }
    
    
    /**
     * Returns application
     * 
     * @return \Zend_Application
     */
    public function getApplication()
    {
        return $this->application;
    }
    
    
    /**
     * Override for specific init
     */
    public function init()
    {
        
    }
    
    
    public static function setDefaultBootstraps(array $defaultBootstraps)
    {
        self::$defaultBootstraps = $defaultBootstraps;
    }
    
    
    public static function getDefaultBootstraps()
    {
        return self::$defaultBootstraps;
    }
    
    
    public static function addNamespace($namespace)
    {
        self::$namespaces[] = $namespace;
    }
    
    
    public static function getNamespaces()
    {
        return self::$namespaces;
    }
    
    
    /**
     *
     * Constructs and returns a concrete janitor from arguments
     * 
     * @param \Zend_Application $application
     * @param array $arguments
     * @throws JanitorException
     * @return \Xi\Janitor\AbstractJanitor
     */
    public static function createJanitorFromArguments(\Zend_Application $application, $arguments)
    {
        if (!isset($arguments[1]) || !$arguments[1]) {
            throw new JanitorException('Janitor name may not be empty'); 
        }
                
        $janitorName = $arguments[1];
        
        unset($arguments[1]);
        $arguments = array_values($arguments);
        
        foreach (self::getNamespaces() as $namespace) {
            $className = $namespace . '\\' . $janitorName . 'Janitor';
            
            try {
                \Zend_Loader::loadClass($className);
                break;
            } catch(\Zend_Exception $e) {
                $className = null;
                // Nuttin special
            }
            
        }
        
        if(!$className) {
            throw new JanitorException("Janitor '{$janitorName}' could not be located");
        }
        
        $janitor = new $className($application, $arguments);
        
        return $janitor;
    }
    
    
    public function getName()
    {
        $lus = get_class($this);
        $lus = explode("\\", $lus);
        $lus = array_pop($lus);
        $lus = preg_replace("/Janitor$/", '', $lus);
        return $lus;
    }
    
    
    public function getIdentifier()
    {
        $filter = new \Zend_Filter();
        $filter->addFilter(new \Zend_Filter_Word_SeparatorToSeparator("\\", "___" ));
        $identifier = $filter->filter(get_class($this));
        return $identifier;
    }
    
    
    
    /**
     * Returns whether the janitor is locked
     * 
     * @return boolean
     */
    public function isLocked()
    {
        return file_exists(sys_get_temp_dir() . '/' . $this->getIdentifier() . '.lock');
    }

    
    /**
     * Locks janitor
     */
    public function lock()
    {
        touch(sys_get_temp_dir() . '/' . $this->getIdentifier() . '.lock');
    }
    
    
    /**
     * Unlocks janitor
     */
    public function unlock()
    {
        if (file_exists(sys_get_temp_dir() . '/' . $this->getIdentifier() . '.lock')) {
            unlink(sys_get_temp_dir() . '/' . $this->getIdentifier() . '.lock');
        }
    }
    
    
    /**
     * Sets log
     * 
     * @param \Zend_Log $log
     */
    public function setLog(\Zend_Log $log)
    {
        $this->log = $log;
    }

    
    /**
     * Returns log. If log is not set, returns a null writing log.
     * 
     * @return \Zend_Log
     */
    public function getLog()
    {
        if(!$this->log) {
            $log = new \Zend_Log();
            $log->addWriter(new \Zend_Log_Writer_Null()); 
            $this->log = $log;
        }
        
        return $this->log;
    }
    
    

    public function log($message, $priority)
    {
        $this->getLog()->log("[{$this->getName()}] " . $message, $priority, array('janitor' => $this->getName()));
    }
    
    
    /**
     * Runs janitor
     * 
     * @return boolean Run successfully completed or not
     */
    public function run()
    {
        $this->log("Running in environment '" . APPLICATION_ENV . "'", \Zend_Log::INFO);
        
        if ($this->oneInstance) {
            
            $processes = shell_exec("ps aux | grep 'janitor.php'");
            
            $name = $this->getName();
            $matches = array();
            $instances = preg_match_all("/janitor\.php {$name}/", $processes, $matches);
            
            if ($instances > 2) {
                $this->log("Instance already running. Will not run moar.", \Zend_Log::NOTICE);
                return false; 
            }
        }        
                
        if ($this->lock) {
            if ($this->isLocked()) {
                $this->log("Janitor is locked. Will not run.", \Zend_Log::WARN);
                return false; 
            }
            $this->lock();
        }

        $this->runner();
                
        if ($this->lock) {
            $this->unlock();    
        }
        
        $this->log("Running in environment '" . APPLICATION_ENV . "' completed succesfully.", \Zend_Log::INFO);
        return true;
    }
    
    
    
    
    
}