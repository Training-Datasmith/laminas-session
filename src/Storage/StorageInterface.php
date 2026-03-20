<?php

declare (strict_types=1);
namespace Laminas\Session\Storage;

use ArrayAccess;
use Countable;
use Serializable;
use Traversable;
/**
 * Session storage interface
 *
 * Defines the minimum requirements for handling userland, in-script session
 * storage (e.g., the $_SESSION superglobal array).
 *
 * @template TKey of array-key
 * @template TValue
 * @template-extends Traversable<TKey, TValue>
 * @template-extends ArrayAccess<TKey, TValue>
 */
interface Storage_Interface extends Traversable, ArrayAccess, Serializable, Countable
{
    /** @return float */
    public function get_request_access_time();
    /**
     * @param null|int|string $key
     * @return self
     */
    public function lock($key = null);
    /**
     * @param null|int|string $key
     * @return bool
     */
    public function is_locked($key = null);
    /**
     * @param null|int|string $key
     * @return self
     */
    public function unlock($key = null);
    /** @return self */
    public function mark_immutable();
    /** @return bool */
    public function is_immutable();
    /**
     * @param string $key
     * @param mixed $value
     * @param bool $overwriteArray
     * @return self
     */
    public function set_metadata($key, $value, $overwrite_array = false);
    /**
     * @param null|int|string $key
     * @return mixed
     */
    public function get_metadata($key = null);
    /**
     * @param null|int|string $key
     * @return self
     */
    public function clear($key = null);
    /**
     * @return self
     */
    public function from_array(array $array);
    /**
     * @param bool $metadata
     * @return array
     */
    public function to_array($metadata = false);
}