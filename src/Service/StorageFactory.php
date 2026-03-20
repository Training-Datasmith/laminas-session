<?php

declare (strict_types=1);
namespace Laminas\Session\Service;

// phpcs:disable WebimpressCodingStandard.PHP.CorrectClassNameCase
use Interop\Container\Container_Interface;
use function is_array;
use Laminas\Service_Manager\Exception\Service_Not_Created_Exception;
use Laminas\Service_Manager\Factory_Interface;
use Laminas\Service_Manager\Service_Locator_Interface;
use Laminas\Session\Exception\Exception_Interface as SessionException;
use Laminas\Session\Storage\Factory;
use Laminas\Session\Storage\Storage_Interface;
use function sprintf;
/**
 * @final
 */
class Storage_Factory implements Factory_Interface
{
    /**
     * Create session storage object (v3 usage).
     *
     * Uses "session_storage" section of configuration to seed a StorageInterface
     * instance. That array should contain the key "type", specifying the storage
     * type to use, and optionally "options", containing any options to be used in
     * creating the StorageInterface instance.
     *
     * @param string $requestedName
     * @return StorageInterface
     * @throws ServiceNotCreatedException If session_storage is missing, or the
     *         factory cannot create the storage instance.
     */
    public function __invoke(Container_Interface $container, $requested_name, ?array $options = null)
    {
        $config = $container->get('config');
        if (!isset($config['session_storage']) || !is_array($config['session_storage'])) {
            throw new Service_Not_Created_Exception('Configuration is missing a "session_storage" key, or the value of that key is not an array');
        }
        $config = $config['session_storage'];
        if (!isset($config['type'])) {
            throw new Service_Not_Created_Exception('"session_storage" configuration is missing a "type" key');
        }
        $type = $config['type'];
        $options = $config['options'] ?? [];
        try {
            $storage = Factory::factory($type, $options);
        } catch (Session_Exception $e) {
            throw new Service_Not_Created_Exception(sprintf('Factory is unable to create StorageInterface instance: %s', $e->get_message()), $e->get_code(), $e);
        }
        return $storage;
    }
    /**
     * @deprecated This method will be removed in version 3.0
     * Create and return a storage instance (v2 usage).
     *
     * @param null|string $canonicalName
     * @param string $requestedName
     * @return StorageInterface
     */
    public function create_service(Service_Locator_Interface $services, $canonical_name = null, $requested_name = Storage_Interface::class)
    {
        return $this($services, $requested_name);
    }
}