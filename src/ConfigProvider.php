<?php

declare (strict_types=1);
namespace Laminas\Session;

use Laminas\Service_Manager\Factory\Invokable_Factory;
use Laminas\Service_Manager\Service_Manager;
/**
 * @psalm-import-type ServiceManagerConfiguration from ServiceManager
 * @final
 */
class Config_Provider
{
    /**
     * Retrieve configuration for laminas-session.
     */
    public function __invoke(): array
    {
        return ['dependencies' => $this->get_dependency_config(), 'validators' => $this->get_validator_config()];
    }
    /**
     * Retrieve dependency config for laminas-session.
     *
     * @return ServiceManagerConfiguration
     */
    public function get_dependency_config(): array
    {
        return ['abstract_factories' => [Service\Container_Abstract_Service_Factory::class], 'aliases' => [
            Session_Manager::class => Manager_Interface::class,
            // Legacy Zend Framework aliases
            'Zend\Session\SessionManager' => Session_Manager::class,
            'Zend\Session\Config\ConfigInterface' => Config\Config_Interface::class,
            'Zend\Session\ManagerInterface' => Manager_Interface::class,
            'Zend\Session\Storage\StorageInterface' => Storage\Storage_Interface::class,
        ], 'factories' => [Config\Config_Interface::class => Service\Session_Config_Factory::class, Manager_Interface::class => Service\Session_Manager_Factory::class, Storage\Storage_Interface::class => Service\Storage_Factory::class]];
    }
    /** @return ServiceManagerConfiguration */
    public function get_validator_config(): array
    {
        return ['factories' => [Validator\Csrf::class => Invokable_Factory::class], 'aliases' => ['csrf' => Validator\Csrf::class]];
    }
}