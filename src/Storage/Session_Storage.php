<?php

declare (strict_types=1);
namespace Laminas\Session\Storage;

use ArrayIterator;
use function is_object;
use Laminas\Stdlib\ArrayObject;
/**
 * Session storage in $_SESSION
 *
 * Replaces the $_SESSION superglobal with an ArrayObject that allows for
 * property access, metadata storage, locking, and immutability.
 *
 * @template TKey of array-key
 * @template TValue
 * @template-extends ArrayStorage<TKey, TValue>
 */
class Session_Storage extends Array_Storage
{
    /**
     * Constructor
     *
     * Sets the $_SESSION superglobal to an ArrayObject, maintaining previous
     * values if any discovered.
     *
     * @param array|null $input
     * @param int        $flags
     * @param string     $iteratorClass
     */
    public function __construct($input = null, $flags = ArrayObject::ARRAY_AS_PROPS, $iterator_class = ArrayIterator::class)
    {
        $reset_session = true;
        if (null === $input && isset($_SESSION)) {
            $input = $_SESSION;
            if (is_object($input) && $_SESSION instanceof ArrayObject) {
                $reset_session = false;
            } elseif (is_object($input) && !$_SESSION instanceof ArrayObject) {
                $input = (array) $input;
            }
        } elseif (null === $input) {
            $input = [];
        }
        parent::__construct($input, $flags, $iterator_class);
        $_SESSION = $this;
    }
    /**
     * Destructor
     *
     * Resets $_SESSION superglobal to an array, by casting object using
     * getArrayCopy().
     */
    public function __destruct()
    {
        $_SESSION = (array) $this->get_array_copy();
    }
    /**
     * Load session object from an existing array
     *
     * Ensures $_SESSION is set to an instance of the object when complete.
     *
     * @param array<TKey, TValue> $array
     * @return $this
     */
    public function from_array(array $array): static
    {
        parent::from_array($array);
        $_SESSION = $this;
        return $this;
    }
    /**
     * Mark object as isImmutable
     *
     * @return $this
     */
    public function mark_immutable(): static
    {
        $this['_IMMUTABLE'] = true;
        return $this;
    }
    /**
     * Determine if this object is isImmutable
     */
    public function is_immutable(): bool
    {
        return isset($this['_IMMUTABLE']) && $this['_IMMUTABLE'];
    }
}