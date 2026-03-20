<?php

declare (strict_types=1);
namespace Laminas\Session\Validator;

use Laminas\Event_Manager\Event_Manager;
use Laminas\Session\Storage\Storage_Interface;
use Laminas\Stdlib\Callback_Handler;
/**
 * Abstract validator chain for validating sessions (for use with laminas-eventmanager v3)
 *
 * @deprecated Use {@see \Laminas\Session\ValidatorChain} directly
 */
abstract class Abstract_Validator_Chain_Em3 extends Event_Manager
{
    use Validator_Chain_Trait;
    /**
     * Construct the validation chain
     *
     * Retrieves validators from session storage and attaches them.
     *
     * Duplicated in ValidatorChainEM2 to prevent trait collision with parent.
     */
    public function __construct(Storage_Interface $storage)
    {
        parent::__construct();
        $this->storage = $storage;
        $validators = $storage->get_metadata('_VALID');
        if ($validators) {
            foreach ($validators as $validator => $data) {
                $this->attach_validator('session.validate', [new $validator($data), 'isValid'], 1);
            }
        }
    }
    /**
     * Attach a listener to the session validator chain.
     *
     * @param string $eventName
     * @param int $priority
     * @return CallbackHandler
     */
    public function attach($event_name, callable $listener, $priority = 1)
    {
        return $this->attach_validator($event_name, $listener, $priority);
    }
}