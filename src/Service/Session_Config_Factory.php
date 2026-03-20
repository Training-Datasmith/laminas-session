<?php

declare (strict_types=1);
namespace Laminas\Session\Service;

// phpcs:disable WebimpressCodingStandard.PHP.CorrectClassNameCase
use function class_exists;
use function get_debug_type;
use Interop\Container\Container_Interface;
use function is_array;
use Laminas\Service_Manager\Exception\Service_Not_Created_Exception;
use Laminas\Service_Manager\Factory_Interface;
use Laminas\Service_Manager\Service_Locator_Interface;
use Laminas\Session\Config\Config_Interface;
use Laminas\Session\Config\Same_Site_Cookie_Capable_Interface;
use Laminas\Session\Config\Session_Config;
use Laminas\Session\Save_Handler\Save_Handler_Interface;
use function sprintf;
/**
 * @final
 */
class Session_Config_Factory implements Factory_Interface
{
    /**
     * Create session configuration object (v3 usage).
     *
     * Uses "session_config" section of configuration to seed a ConfigInterface
     * instance. By default, Laminas\Session\Config\SessionConfig will be used, but
     * you may also specify a specific implementation variant using the
     * "config_class" subkey.
     *
     * @param string $requestedName
     * @return ConfigInterface
     * @throws ServiceNotCreatedException If session_config is missing, or an
     *     invalid config_class is used.
     */
    public function __invoke(Container_Interface $container, $requested_name, ?array $options = null)
    {
        $config = $container->get('config');
        if (!isset($config['session_config']) || !is_array($config['session_config'])) {
            throw new Service_Not_Created_Exception('Configuration is missing a "session_config" key, or the value of that key is not an array');
        }
        $class = Session_Config::class;
        /** @var array{
         *     config_class?: string,
         *     save_handler?: string|SaveHandlerInterface,
         *     cookie_samesite: string
         * } $config
         */
        $config = $config['session_config'];
        if (isset($config['config_class'])) {
            if (!class_exists($config['config_class'])) {
                throw new Service_Not_Created_Exception(sprintf('Invalid configuration class "%s" specified in "config_class" session configuration; ' . 'must be a valid class', $config['config_class']));
            }
            $class = $config['config_class'];
            unset($config['config_class']);
        }
        // We set SaveHandlerInterface as default save_handler if it exists in the container, we do this
        // because SessionManagerFactory does this, and this keeps the configuration consistent
        if (!isset($config['save_handler']) && $container->has(Save_Handler_Interface::class)) {
            $config['save_handler'] = Save_Handler_Interface::class;
        }
        if (isset($config['save_handler']) && $config['save_handler'] === Save_Handler_Interface::class) {
            if (!$container->has($config['save_handler'])) {
                throw new Service_Not_Created_Exception(sprintf('Class %s set as save_handler must be defined in the service manager', $config['save_handler']));
            }
            $save_handler = $container->get($config['save_handler']);
            if (!$save_handler instanceof Save_Handler_Interface) {
                throw new Service_Not_Created_Exception(sprintf('Class %s set as save_handler must implement %s; received "%s"', $save_handler::class, Save_Handler_Interface::class, get_debug_type($save_handler)));
            }
            $config['save_handler'] = $save_handler;
        }
        $session_config = new $class();
        if (!$session_config instanceof Config_Interface) {
            throw new Service_Not_Created_Exception(sprintf('Invalid configuration class "%s" specified in "config_class" session configuration; must implement %s', $class, Config_Interface::class));
        }
        if (isset($config['cookie_samesite']) && !$session_config instanceof Same_Site_Cookie_Capable_Interface) {
            throw new Service_Not_Created_Exception(sprintf('Invalid configuration class "%s". When configuration option "cookie_samesite" is used,' . ' the configuration class must implement %s', $class, Same_Site_Cookie_Capable_Interface::class));
        }
        $session_config->set_options($config);
        return $session_config;
    }
    /**
     * @deprecated This method will be removed in version 3.0
     * Create and return a config instance (v2 usage).
     *
     * @param null|string $canonicalName
     * @param string $requestedName
     * @return ConfigInterface
     */
    public function create_service(Service_Locator_Interface $services, $canonical_name = null, $requested_name = Config_Interface::class)
    {
        return $this($services, $requested_name);
    }
}