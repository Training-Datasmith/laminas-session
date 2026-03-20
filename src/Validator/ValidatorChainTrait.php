<?php

declare (strict_types=1);
namespace Laminas\Session\Validator;

use function array_shift;
use function array_unshift;
use function is_array;
use Laminas\Session\Storage\Storage_Interface;
use Laminas\Stdlib\Callback_Handler;
/**
 * Base trait for validator chain implementations
 *
 * @deprecated Use {@see \Laminas\Session\ValidatorChain} directly
 */
trait Validator_Chain_Trait
{
    /** @var StorageInterface */
    protected $storage;
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
     * @param  string $event
     * @param  callable $callback
     * @param  int $priority
     * @return CallbackHandler|callable
     */
    private function attach_validator($event, $callback, $priority)
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