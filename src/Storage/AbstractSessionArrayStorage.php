<?php

declare (strict_types=1);
namespace Laminas\Session\Storage;

use function array_flip;
use function array_key_exists;
use function array_keys;
use function array_replace_recursive;
use ArrayIterator;
use ArrayObject;
use function count;
use function is_array;
use function is_object;
use IteratorAggregate;
use Laminas\Session\Exception;
use function microtime;
use Return_Type_Will_Change;
use function serialize;
use function sprintf;
use function unserialize;
/**
 * Session storage in $_SESSION
 *
 * Replaces the $_SESSION superglobal with an ArrayObject that allows for
 * property access, metadata storage, locking, and immutability.
 *
 * @see ReturnTypeWillChange
 *
 * @template TKey of array-key
 * @template TValue
 * @template-implements IteratorAggregate<TKey, TValue>
 * @template-implements StorageInterface<TKey, TValue>
 */
abstract class Abstract_Session_Array_Storage implements IteratorAggregate, Storage_Interface, Storage_Initialization_Interface
{
    /**
     * Constructor
     *
     * @param array|null $input
     */
    public function __construct($input = null)
    {
        // this is here for B.C.
        $this->init($input);
    }
    /**
     * Initialize Storage
     *
     * @param  array $input
     */
    public function init($input = null): void
    {
        if (null === $input && isset($_SESSION)) {
            $input = $_SESSION;
            if (is_object($input) && !$_SESSION instanceof ArrayObject) {
                $input = (array) $input;
            }
        } elseif (null === $input) {
            $input = [];
        }
        $_SESSION = $input;
        $this->set_request_access_time(microtime(true));
    }
    /**
     * Get Offset
     */
    public function __get(mixed $key): mixed
    {
        return $this->offsetGet($key);
    }
    /**
     * Set Offset
     *
     * @return void
     */
    public function __set(mixed $key, mixed $value)
    {
        $this->offsetSet($key, $value);
    }
    /**
     * Isset Offset
     *
     * @return bool
     */
    public function __isset(mixed $key)
    {
        return $this->offsetExists($key);
    }
    /**
     * Unset Offset
     *
     * @return void
     */
    public function __unset(mixed $key)
    {
        $this->offsetUnset($key);
    }
    /**
     * Offset Exists
     *
     * @return bool
     */
    #[Return_Type_Will_Change]
    public function offsetExists(mixed $key)
    {
        return isset($_SESSION[$key]);
    }
    /**
     * Offset Get
     *
     * @return mixed
     */
    #[Return_Type_Will_Change]
    public function offsetGet(mixed $key)
    {
        return $_SESSION[$key] ?? null;
    }
    /**
     * Offset Set
     */
    #[Return_Type_Will_Change]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $_SESSION[$offset] = $value;
    }
    /**
     * Offset Unset
     */
    #[Return_Type_Will_Change]
    public function offsetUnset(mixed $offset): void
    {
        unset($_SESSION[$offset]);
    }
    /**
     * Count
     *
     * @return int
     */
    #[Return_Type_Will_Change]
    public function count()
    {
        return count($_SESSION);
    }
    /**
     * Seralize
     *
     * @return string
     */
    public function serialize()
    {
        return serialize($_SESSION);
    }
    /**
     * Unserialize
     *
     * @param  string $session
     * @return mixed
     */
    public function unserialize($session)
    {
        return unserialize($session, ['allowed_classes' => false]);
    }
    /** @inheritDoc */
    #[Return_Type_Will_Change]
    public function getIterator()
    {
        return new ArrayIterator($_SESSION);
    }
    /**
     * Load session object from an existing array
     *
     * Ensures $_SESSION is set to an instance of the object when complete.
     *
     * @return SessionStorage
     */
    public function from_array(array $array)
    {
        $ts = $this->get_request_access_time();
        $_SESSION = $array;
        $this->set_request_access_time($ts);
        return $this;
    }
    /**
     * Mark object as isImmutable
     *
     * @return SessionStorage
     */
    public function mark_immutable()
    {
        $_SESSION['_IMMUTABLE'] = true;
        return $this;
    }
    /**
     * Determine if this object is isImmutable
     *
     * @return bool
     */
    public function is_immutable()
    {
        return isset($_SESSION['_IMMUTABLE']) && $_SESSION['_IMMUTABLE'];
    }
    /**
     * Lock this storage instance, or a key within it
     *
     * @param  null|int|string $key
     * @return $this
     */
    public function lock($key = null)
    {
        if (null === $key) {
            $this->set_metadata('_READONLY', true);
            return $this;
        }
        if (isset($_SESSION[$key])) {
            $this->set_metadata('_LOCKS', [$key => true]);
        }
        return $this;
    }
    /**
     * Is the object or key marked as locked?
     *
     * @param  null|int|string $key
     * @return bool
     */
    public function is_locked($key = null)
    {
        if ($this->is_immutable()) {
            // isImmutable trumps all
            return true;
        }
        if (null === $key) {
            // testing for global lock
            return $this->get_metadata('_READONLY');
        }
        $locks = $this->get_metadata('_LOCKS');
        $read_only = $this->get_metadata('_READONLY');
        if ($read_only && !$locks) {
            // global lock in play; all keys are locked
            return true;
        }
        if ($read_only && $locks) {
            return array_key_exists($key, $locks);
        }
        // test for individual locks
        if (!$locks) {
            return false;
        }
        return array_key_exists($key, $locks);
    }
    /**
     * Unlock an object or key marked as locked
     *
     * @param  null|int|string $key
     * @return $this
     */
    public function unlock($key = null)
    {
        if (null === $key) {
            // Unlock everything
            $this->set_metadata('_READONLY', false);
            $this->set_metadata('_LOCKS', false);
            return $this;
        }
        $locks = $this->get_metadata('_LOCKS');
        if (!$locks) {
            if (!$this->get_metadata('_READONLY')) {
                return $this;
            }
            $array = $this->to_array();
            $keys = array_keys($array);
            $locks = array_flip($keys);
            unset($array, $keys);
        }
        if (array_key_exists($key, $locks)) {
            unset($locks[$key]);
            $this->set_metadata('_LOCKS', $locks, true);
        }
        return $this;
    }
    /**
     * Set storage metadata
     *
     * Metadata is used to store information about the data being stored in the
     * object. Some example use cases include:
     * - Setting expiry data
     * - Maintaining access counts
     * - localizing session storage
     * - etc.
     *
     * @param  string                     $key
     * @param  mixed                      $value
     * @param  bool                       $overwriteArray Whether to overwrite or merge array values; by default, merges
     * @return $this
     * @throws Exception\RuntimeException
     */
    public function set_metadata($key, $value, $overwrite_array = false)
    {
        if ($this->is_immutable()) {
            throw new Exception\RuntimeException(sprintf('Cannot set key "%s" as storage is marked isImmutable', $key));
        }
        if (!isset($_SESSION['__Laminas']) || !is_array($_SESSION['__Laminas'])) {
            $_SESSION['__Laminas'] = [];
        }
        if (isset($_SESSION['__Laminas'][$key]) && is_array($value)) {
            if ($overwrite_array) {
                $_SESSION['__Laminas'][$key] = $value;
            } else {
                $_SESSION['__Laminas'][$key] = array_replace_recursive($_SESSION['__Laminas'][$key], $value);
            }
        } else if (null === $value && isset($_SESSION['__Laminas'][$key])) {
            $array = $_SESSION['__Laminas'];
            unset($array[$key]);
            $_SESSION['__Laminas'] = $array;
            unset($array);
        } elseif (null !== $value) {
            $_SESSION['__Laminas'][$key] = $value;
        }
        return $this;
    }
    /**
     * Retrieve metadata for the storage object or a specific metadata key
     *
     * Returns false if no metadata stored, or no metadata exists for the given
     * key.
     *
     * @param  null|int|string $key
     * @return mixed
     */
    public function get_metadata($key = null)
    {
        if (!isset($_SESSION['__Laminas'])) {
            return false;
        }
        if (null === $key) {
            return $_SESSION['__Laminas'];
        }
        if (!array_key_exists($key, $_SESSION['__Laminas'])) {
            return false;
        }
        return $_SESSION['__Laminas'][$key];
    }
    /**
     * Clear the storage object or a subkey of the object
     *
     * @param  null|int|string            $key
     * @return $this
     * @throws Exception\RuntimeException
     */
    public function clear($key = null)
    {
        if ($this->is_immutable()) {
            throw new Exception\RuntimeException('Cannot clear storage as it is marked immutable');
        }
        if (null === $key) {
            $this->from_array([]);
            return $this;
        }
        unset($_SESSION[$key]);
        $this->set_metadata($key, null)->unlock($key);
        return $this;
    }
    /**
     * Retrieve the request access time
     *
     * @return float
     */
    public function get_request_access_time()
    {
        return $this->get_metadata('_REQUEST_ACCESS_TIME');
    }
    /**
     * Set the request access time
     *
     * @param  float        $time
     * @return $this
     */
    protected function set_request_access_time($time)
    {
        $this->set_metadata('_REQUEST_ACCESS_TIME', $time);
        return $this;
    }
    /**
     * Cast the object to an array
     *
     * @param  bool $metaData Whether to include metadata
     * @return array<TKey, TValue>
     */
    public function to_array($meta_data = false)
    {
        if (isset($_SESSION)) {
            $values = $_SESSION;
        } else {
            $values = [];
        }
        if ($meta_data) {
            return $values;
        }
        if (isset($values['__Laminas'])) {
            unset($values['__Laminas']);
        }
        return $values;
    }
    public function __serialize(): array
    {
        return $_SESSION;
    }
}