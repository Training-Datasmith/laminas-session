<?php

declare (strict_types=1);
namespace Laminas\Session\Service;

// phpcs:disable WebimpressCodingStandard.PHP.CorrectClassNameCase
use function array_change_key_case;
use function array_flip;
use function array_key_exists;
use Interop\Container\Container_Interface;
use function is_array;
use Laminas\Service_Manager\Abstract_Factory_Interface;
use Laminas\Service_Manager\Service_Locator_Interface;
use Laminas\Session\Container;
use Laminas\Session\Manager_Interface;
use function strtolower;
/**
 * Session container abstract service factory.
 *
 * Allows creating Container instances, using the ManagerInterface
 * if present. Containers are named in a "session_containers" array in the
 * Config service:
 *
 * <code>
 * return array(
 *     'session_containers' => array(
 *         'SessionContainer\sample',
 *         'my_sample_session_container',
 *         'MySessionContainer',
 *     ),
 * );
 * </code>
 *
 * <code>
 * $container = $services->get('MySessionContainer');
 * </code>
 *
 * @final
 */
class Container_Abstract_Service_Factory implements Abstract_Factory_Interface
{
    /**
     * Cached container configuration
     *
     * @var array
     */
    protected $config;
    /**
     * Configuration key in which session containers live
     *
     * @var string
     */
    protected $config_key = 'session_containers';
    /** @var ManagerInterface */
    protected $session_manager;
    /**
     * Can we create an instance of the given service? (v3 usage).
     *
     * @param string $requestedName
     * @return bool
     */
    public function can_create(Container_Interface $container, $requested_name)
    {
        $config = $this->get_config($container);
        if ($config === []) {
            return false;
        }
        $container_name = $this->normalize_container_name($requested_name);
        return array_key_exists($container_name, $config);
    }
    /**
     * @deprecated This method will be removed in version 3.0
     * Can we create an instance of the given service? (v2 usage)
     *
     * @param string $name
     * @param string $requestedName
     * @return bool
     */
    public function can_create_service_with_name(Service_Locator_Interface $container, $name, $requested_name)
    {
        return $this->can_create($container, $requested_name);
    }
    /**
     * Create and return a named container (v3 usage).
     *
     * @param string $requestedName
     */
    public function __invoke(Container_Interface $container, $requested_name, ?array $options = null): \Laminas\Session\Container
    {
        $manager = $this->get_session_manager($container);
        return new Container($requested_name, $manager);
    }
    /**
     * @deprecated This method will be removed in version 3.0
     * Create and return a named container (v2 usage).
     *
     * @param string $name
     * @param string $requestedName
     * @return Container
     */
    public function create_service_with_name(Service_Locator_Interface $container, $name, $requested_name)
    {
        return $this($container, $requested_name);
    }
    /**
     * Retrieve config from service locator, and cache for later
     *
     * @return array
     */
    protected function get_config(Container_Interface $container)
    {
        if (null !== $this->config) {
            return $this->config;
        }
        if (!$container->has('config')) {
            $this->config = [];
            return $this->config;
        }
        $config = $container->get('config');
        if (!isset($config[$this->config_key]) || !is_array($config[$this->config_key])) {
            $this->config = [];
            return $this->config;
        }
        $config = $config[$this->config_key];
        $config = array_flip($config);
        $this->config = array_change_key_case($config);
        return $this->config;
    }
    /**
     * Retrieve the session manager instance, if any
     *
     * @return null|ManagerInterface
     */
    protected function get_session_manager(Container_Interface $container)
    {
        if ($this->session_manager !== null) {
            return $this->session_manager;
        }
        if ($container->has(Manager_Interface::class)) {
            $this->session_manager = $container->get(Manager_Interface::class);
        }
        return $this->session_manager;
    }
    /**
     * Normalize the container name in order to perform a lookup
     *
     * @param  string $name
     */
    protected function normalize_container_name($name): string
    {
        return strtolower($name);
    }
}