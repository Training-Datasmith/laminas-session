<?php

declare (strict_types=1);
namespace Laminas\Session;

use function array_shift;
use function array_unshift;
use function is_array;
use Laminas\Event_Manager\Event_Manager;
use Laminas\Session\Storage\Storage_Interface;
use Laminas\Session\Validator\Validator_Interface;
class Validator_Chain extends Event_Manager
{
    public function __construct(protected Storage_Interface $storage)
    {
        parent::__construct();
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
     * @param string   $eventName
     * @param int      $priority
     * @return callable
     */
    public function attach($event_name, callable $listener, $priority = 1)
    {
        return $this->attach_validator($event_name, $listener, $priority);
    }
    /**
     * Retrieve session storage object
     *
     * @return StorageInterface
     */
    public function get_storage()
    {
        return $this->storage;
    }
    /**
     * Internal implementation for attaching a listener to the
     * session validator chain.
     *
     * @param string   $event
     * @param callable $callback
     * @param int      $priority
     * @return callable
     */
    private function attach_validator($event, array|callable $callback, $priority)
    {
        $context = null;
        if ($callback instanceof Validator_Interface) {
            $context = $callback;
        } elseif (is_array($callback)) {
            $test = array_shift($callback);
            if ($test instanceof Validator_Interface) {
                $context = $test;
            }
            array_unshift($callback, $test);
        }
        if ($context instanceof Validator_Interface) {
            $data = $context->get_data();
            $name = $context->get_name();
            $this->get_storage()->set_metadata('_VALID', [$name => $data]);
        }
        return parent::attach($event, $callback, $priority);
    }
}