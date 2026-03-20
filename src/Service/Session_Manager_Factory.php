<?php

declare (strict_types=1);
namespace Laminas\Session\Service;

// phpcs:disable WebimpressCodingStandard.PHP.CorrectClassNameCase
use function array_merge;
use function class_exists;
use function get_debug_type;
use Interop\Container\Container_Interface;
use function is_array;
use function is_subclass_of;
use Laminas\Service_Manager\Exception\Service_Not_Created_Exception;
use Laminas\Service_Manager\Factory_Interface;
use Laminas\Service_Manager\Service_Locator_Interface;
use Laminas\Session\Config\Config_Interface;
use Laminas\Session\Container;
use Laminas\Session\Manager_Interface;
use Laminas\Session\Save_Handler\Save_Handler_Interface;
use Laminas\Session\Session_Manager;
use Laminas\Session\Storage\Storage_Interface;
use function sprintf;
/**
 * @final
 */
class Session_Manager_Factory implements Factory_Interface
{
    /**
     * Default configuration for manager behavior
     *
     * @var array
     */
    protected $default_manager_config = ['enable_default_container_manager' => true];
    /**
     * Create session manager object (v3 usage).
     *
     * Will consume any combination (or zero) of the following services, when
     * present, to construct the SessionManager instance:
     *
     * - Laminas\Session\Config\ConfigInterface
     * - Laminas\Session\Storage\StorageInterface
     * - Laminas\Session\SaveHandler\SaveHandlerInterface
     *
     * The first two have corresponding factories inside this namespace. The
     * last, however, does not, due to the differences in implementations, and
     * the fact that save handlers will often be written in userland. As such
     * if you wish to attach a save handler to the manager, you will need to
     * write your own factory, and assign it to the service name
     * "Laminas\Session\SaveHandler\SaveHandlerInterface", (or alias that name
     * to your own service).
     *
     * You can configure limited behaviors via the "session_manager" key of the
     * Config service. Currently, these include:
     *
     * - enable_default_container_manager: whether to inject the created instance
     *   as the default manager for Container instances. The default value for
     *   this is true; set it to false to disable.
     * - validators: ...
     *
     * @param string $requestedName
     * @return SessionManager
     */
    public function __invoke(Container_Interface $container, $requested_name, ?array $options = null)
    {
        $config = null;
        $storage = null;
        $save_handler = null;
        $validators = [];
        $manager_config = $this->default_manager_config;
        $options = [];
        if ($container->has(Config_Interface::class)) {
            $config = $container->get(Config_Interface::class);
            if (!$config instanceof Config_Interface) {
                throw new Service_Not_Created_Exception(sprintf('SessionManager requires that the %s service implement %s; received "%s"', Config_Interface::class, Config_Interface::class, get_debug_type($config)));
            }
        }
        if ($container->has(Storage_Interface::class)) {
            $storage = $container->get(Storage_Interface::class);
            if (!$storage instanceof Storage_Interface) {
                throw new Service_Not_Created_Exception(sprintf('SessionManager requires that the %s service implement %s; received "%s"', Storage_Interface::class, Storage_Interface::class, get_debug_type($storage)));
            }
        }
        if ($container->has(Save_Handler_Interface::class)) {
            $save_handler = $container->get(Save_Handler_Interface::class);
            if (!$save_handler instanceof Save_Handler_Interface) {
                throw new Service_Not_Created_Exception(sprintf('SessionManager requires that the %s service implement %s; received "%s"', Save_Handler_Interface::class, Save_Handler_Interface::class, get_debug_type($save_handler)));
            }
        }
        // Get session manager configuration, if any, and merge with default configuration
        if ($container->has('config')) {
            $config_service = $container->get('config');
            if (isset($config_service['session_manager']) && is_array($config_service['session_manager'])) {
                $manager_config = array_merge($manager_config, $config_service['session_manager']);
            }
            if (isset($manager_config['validators'])) {
                $validators = $manager_config['validators'];
            }
            if (isset($manager_config['options'])) {
                $options = $manager_config['options'];
            }
        }
        $manager_class = class_exists($requested_name) ? $requested_name : Session_Manager::class;
        if (!is_subclass_of($manager_class, Manager_Interface::class)) {
            throw new Service_Not_Created_Exception(sprintf('SessionManager requires that the %s service implement %s', $manager_class, Manager_Interface::class));
        }
        $manager = new $manager_class($config, $storage, $save_handler, $validators, $options);
        // If configuration enables the session manager as the default manager for container
        // instances, do so.
        if (isset($manager_config['enable_default_container_manager']) && $manager_config['enable_default_container_manager']) {
            Container::set_default_manager($manager);
        }
        return $manager;
    }
    /**
     * @deprecated This method will be removed in version 3.0
     * Create a SessionManager instance (v2 usage)
     *
     * @param null|string $canonicalName
     * @param string $requestedName
     * @return SessionManager
     */
    public function create_service(Service_Locator_Interface $services, $canonical_name = null, $requested_name = Session_Manager::class)
    {
        return $this($services, $requested_name);
    }
}