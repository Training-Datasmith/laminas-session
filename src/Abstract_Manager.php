<?php

declare (strict_types=1);
namespace Laminas\Session;

use function class_exists;
use Laminas\Session\Config\Config_Interface as Config;
use Laminas\Session\Config\Session_Config;
use Laminas\Session\Manager_Interface as Manager;
use Laminas\Session\Save_Handler\Save_Handler_Interface as SaveHandler;
use Laminas\Session\Storage\Session_Array_Storage;
use Laminas\Session\Storage\Storage_Interface as Storage;
use function sprintf;
/**
 * Base ManagerInterface implementation
 *
 * Defines common constructor logic and getters for Storage and Configuration
 */
abstract class Abstract_Manager implements Manager
{
    protected ?\Laminas\Session\Config\Config_Interface $config;
    /**
     * Default configuration class to use when no configuration provided
     *
     * @var string
     */
    protected $default_config_class = Session_Config::class;
    protected ?\Laminas\Session\Storage\Storage_Interface $storage;
    /**
     * Default storage class to use when no storage provided
     *
     * @var string
     */
    protected $default_storage_class = Session_Array_Storage::class;
    protected ?\Laminas\Session\Save_Handler\Save_Handler_Interface $save_handler;
    /**
     * Constructor
     *
     * @throws Exception\RuntimeException
     */
    public function __construct(?Config $config = null, ?Storage $storage = null, ?Save_Handler $save_handler = null, protected array $validators = [])
    {
        // init config
        if ($config === null) {
            if (!class_exists($this->default_config_class)) {
                throw new Exception\RuntimeException(sprintf('Unable to locate config class "%s"; class does not exist', $this->default_config_class));
            }
            $config = new $this->default_config_class();
            if (!$config instanceof Config) {
                throw new Exception\RuntimeException(sprintf('Default config class %s is invalid; must implement %s\Config\ConfigInterface', $this->default_config_class, __NAMESPACE__));
            }
        }
        $this->config = $config;
        // init storage
        if ($storage === null) {
            if (!class_exists($this->default_storage_class)) {
                throw new Exception\RuntimeException(sprintf('Unable to locate storage class "%s"; class does not exist', $this->default_storage_class));
            }
            $storage = new $this->default_storage_class();
            if (!$storage instanceof Storage) {
                throw new Exception\RuntimeException(sprintf('Default storage class %s is invalid; must implement %s\Storage\StorageInterface', $this->default_config_class, __NAMESPACE__));
            }
        }
        $this->storage = $storage;
        // save handler
        if ($save_handler !== null) {
            $this->save_handler = $save_handler;
        }
    }
    /**
     * Set configuration object
     *
     * @return AbstractManager
     */
    public function set_config(Config $config)
    {
        $this->config = $config;
        return $this;
    }
    /**
     * Retrieve configuration object
     *
     * @return Config
     */
    public function get_config()
    {
        return $this->config;
    }
    /**
     * Set session storage object
     *
     * @return AbstractManager
     */
    public function set_storage(Storage $storage)
    {
        $this->storage = $storage;
        return $this;
    }
    /**
     * Retrieve storage object
     *
     * @return Storage
     */
    public function get_storage()
    {
        return $this->storage;
    }
    /**
     * Set session save handler object
     *
     * @return AbstractManager
     */
    public function set_save_handler(Save_Handler $save_handler)
    {
        $this->save_handler = $save_handler;
        return $this;
    }
    /**
     * Get SaveHandler Object
     *
     * @return SaveHandler
     */
    public function get_save_handler()
    {
        return $this->save_handler;
    }
}