<?php

declare (strict_types=1);
namespace Laminas\Session;

class Module
{
    /**
     * Retrieve default laminas-session config for laminas-mvc context.
     */
    public function get_config(): array
    {
        $provider = new Config_Provider();
        return ['service_manager' => $provider->get_dependency_config(), 'validators' => $provider->get_validator_config()];
    }
}