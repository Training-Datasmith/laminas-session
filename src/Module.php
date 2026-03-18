<?php

declare(strict_types=1);

namespace Laminas\Session;

class Module
{
    /**
     * Retrieve default laminas-session config for laminas-mvc context.
     */
    public function getConfig(): array
    {
        $provider = new ConfigProvider();
        return [
            'service_manager' => $provider->getDependencyConfig(),
            'validators'      => $provider->getValidatorConfig(),
        ];
    }
}
