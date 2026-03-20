<?php

declare (strict_types=1);
namespace Laminas\Session;

use function array_filter;
use function array_flip;
use function array_keys;
use function array_map;
use ArrayIterator;
use function is_array;
use function is_object;
use function is_scalar;
use Laminas\Session\Manager_Interface as Manager;
use Laminas\Session\Storage\Storage_Interface as Storage;
use Laminas\Stdlib\ArrayObject;
use function preg_match;
use function time;
use Traversable;
/**
 * Session storage container
 *
 * Allows for interacting with session storage in isolated containers, which
 * may have their own expiries, or even expiries per key in the container.
 * Additionally, expiries may be absolute TTLs or measured in "hops", which
 * are based on how many times the key or container were accessed.
 *
 * @template TKey of string
 * @template TValue
 * @template-extends ArrayObject<TKey, TValue>
 */
abstract class Abstract_Container extends ArrayObject
{
    /**
     * Container name
     *
     * @var string
     */
    protected $name;
    /** @var Manager */
    protected $manager;
    /**
     * Default manager class to use if no manager has been provided
     *
     * @var string
     */
    protected static $manager_default_class = Session_Manager::class;
    /**
     * Default manager to use when instantiating a container without providing a ManagerInterface
     *
     * @var Manager
     */
    protected static $default_manager;
    /**
     * Default value to return by reference from offsetGet
     *
     * @var mixed
     */
    private $default_value;
    /**
     * Constructor
     *
     * Provide a name ('Default' if none provided) and a ManagerInterface instance.
     *
     * @param  null|string                        $name
     * @throws Exception\InvalidArgumentException
     */
    public function __construct($name = 'Default', ?Manager $manager = null)
    {
        if (!preg_match('/^[a-z0-9][a-z0-9_\\\\]+$/i', (string) $name)) {
            throw new Exception\InvalidArgumentException('Name passed to container is invalid; must consist of alphanumerics, backslashes and underscores only');
        }
        $this->name = $name;
        $this->set_manager($manager);
        // Create namespace
        parent::__construct([], ArrayObject::ARRAY_AS_PROPS);
        // Start session
        $this->get_manager()->start();
    }
    /**
     * Set the default ManagerInterface instance to use when none provided to constructor
     */
    public static function set_default_manager(?Manager $manager = null): void
    {
        static::$default_manager = $manager;
    }
    /**
     * Get the default ManagerInterface instance
     *
     * If none provided, instantiates one of type {@link $managerDefaultClass}
     *
     * @return Manager
     * @throws Exception\InvalidArgumentException If invalid manager default class provided.
     */
    public static function get_default_manager()
    {
        if (null === static::$default_manager) {
            $manager = new static::$manager_default_class();
            if (!$manager instanceof Manager) {
                throw new Exception\InvalidArgumentException('Invalid default manager type provided; must implement ManagerInterface');
            }
            static::$default_manager = $manager;
        }
        return static::$default_manager;
    }
    /**
     * Get container name
     *
     * @return string
     */
    public function get_name()
    {
        return $this->name;
    }
    /**
     * Set session manager
     *
     * @return Container
     * @throws Exception\InvalidArgumentException
     */
    protected function set_manager(?Manager $manager = null)
    {
        if (null === $manager) {
            $manager = static::get_default_manager();
        }
        $this->manager = $manager;
        return $this;
    }
    /**
     * Get manager instance
     *
     * @return Manager
     */
    public function get_manager()
    {
        return $this->manager;
    }
    /**
     * Get session storage object
     *
     * Proxies to ManagerInterface::getStorage()
     *
     * @return Storage
     */
    protected function get_storage()
    {
        return $this->get_manager()->get_storage();
    }
    /**
     * Create a new container object on which to act
     *
     * @return ArrayObject
     */
    protected function create_container()
    {
        return new ArrayObject([], ArrayObject::ARRAY_AS_PROPS);
    }
    /**
     * Verify container namespace
     *
     * Checks to see if a container exists within the Storage object already.
     * If not, one is created; if so, checks to see if it's an ArrayObject.
     * If not, it raises an exception; otherwise, it returns the Storage
     * object.
     *
     * @param  bool                       $createContainer Whether or not to create the container for the namespace
     * @return Storage|null               Returns null only if $createContainer is false
     * @throws Exception\RuntimeException
     */
    protected function verify_namespace($create_container = true)
    {
        $storage = $this->get_storage();
        $name = $this->get_name();
        if (!isset($storage[$name])) {
            if (!$create_container) {
                return;
            }
            $storage[$name] = $this->create_container();
        }
        if (!is_array($storage[$name]) && !$storage[$name] instanceof Traversable) {
            throw new Exception\RuntimeException('Container cannot write to storage due to type mismatch');
        }
        return $storage;
    }
    /**
     * Determine whether a given key needs to be expired
     *
     * Returns true if the key has expired, false otherwise.
     *
     * @param  null|string $key
     * @return bool
     */
    protected function expire_keys($key = null)
    {
        $storage = $this->verify_namespace();
        $name = $this->get_name();
        // Return early if key not found
        if (null !== $key && !isset($storage[$name][$key])) {
            return true;
        }
        if ($this->expire_by_expiry_time($storage, $name, $key)) {
            return true;
        }
        if ($this->expire_by_hops($storage, $name, $key)) {
            return true;
        }
        return false;
    }
    /**
     * Expire a key by expiry time
     *
     * Checks to see if the entire container has expired based on TTL setting,
     * or the individual key.
     *
     * @param  string  $name    Container name
     * @param  string  $key     Key in container to check
     * @return bool
     */
    protected function expire_by_expiry_time(Storage $storage, $name, $key)
    {
        $metadata = $storage->get_metadata($name);
        // Global container expiry
        if (is_array($metadata) && isset($metadata['EXPIRE']) && $_SERVER['REQUEST_TIME'] > $metadata['EXPIRE']) {
            unset($metadata['EXPIRE']);
            $storage->set_metadata($name, $metadata, true);
            $storage[$name] = $this->create_container();
            return true;
        }
        // Expire individual key
        if (null !== $key && is_array($metadata) && isset($metadata['EXPIRE_KEYS']) && isset($metadata['EXPIRE_KEYS'][$key]) && $_SERVER['REQUEST_TIME'] > $metadata['EXPIRE_KEYS'][$key]) {
            unset($metadata['EXPIRE_KEYS'][$key]);
            $storage->set_metadata($name, $metadata, true);
            unset($storage[$name][$key]);
            return true;
        }
        // Find any keys that have expired
        if (null === $key && is_array($metadata) && isset($metadata['EXPIRE_KEYS'])) {
            foreach (array_keys($metadata['EXPIRE_KEYS']) as $key) {
                if ($_SERVER['REQUEST_TIME'] > $metadata['EXPIRE_KEYS'][$key]) {
                    unset($metadata['EXPIRE_KEYS'][$key]);
                    if (isset($storage[$name][$key])) {
                        unset($storage[$name][$key]);
                    }
                }
            }
            $storage->set_metadata($name, $metadata, true);
            return true;
        }
        return false;
    }
    /**
     * Expire key by session hops
     *
     * Determines whether the container or an individual key within it has
     * expired based on session hops
     *
     * @param  string  $name
     * @param  string  $key
     * @return bool
     */
    protected function expire_by_hops(Storage $storage, $name, $key)
    {
        $ts = $storage->get_request_access_time();
        $metadata = $storage->get_metadata($name);
        // Global container expiry
        if (is_array($metadata) && isset($metadata['EXPIRE_HOPS']) && $ts > $metadata['EXPIRE_HOPS']['ts']) {
            $metadata['EXPIRE_HOPS']['hops']--;
            if (-1 === $metadata['EXPIRE_HOPS']['hops']) {
                unset($metadata['EXPIRE_HOPS']);
                $storage->set_metadata($name, $metadata, true);
                $storage[$name] = $this->create_container();
                return true;
            }
            $metadata['EXPIRE_HOPS']['ts'] = $ts;
            $storage->set_metadata($name, $metadata, true);
            return false;
        }
        // Single key expiry
        if (null !== $key && is_array($metadata) && isset($metadata['EXPIRE_HOPS_KEYS']) && isset($metadata['EXPIRE_HOPS_KEYS'][$key]) && $ts > $metadata['EXPIRE_HOPS_KEYS'][$key]['ts']) {
            $metadata['EXPIRE_HOPS_KEYS'][$key]['hops']--;
            if (-1 === $metadata['EXPIRE_HOPS_KEYS'][$key]['hops']) {
                unset($metadata['EXPIRE_HOPS_KEYS'][$key]);
                $storage->set_metadata($name, $metadata, true);
                unset($storage[$name][$key]);
                return true;
            }
            $metadata['EXPIRE_HOPS_KEYS'][$key]['ts'] = $ts;
            $storage->set_metadata($name, $metadata, true);
            return false;
        }
        // Find all expired keys
        if (null === $key && is_array($metadata) && isset($metadata['EXPIRE_HOPS_KEYS'])) {
            foreach (array_keys($metadata['EXPIRE_HOPS_KEYS']) as $key) {
                if ($ts > $metadata['EXPIRE_HOPS_KEYS'][$key]['ts']) {
                    $metadata['EXPIRE_HOPS_KEYS'][$key]['hops']--;
                    if (-1 === $metadata['EXPIRE_HOPS_KEYS'][$key]['hops']) {
                        unset($metadata['EXPIRE_HOPS_KEYS'][$key]);
                        $storage->set_metadata($name, $metadata, true);
                        unset($storage[$name][$key]);
                        continue;
                    }
                    $metadata['EXPIRE_HOPS_KEYS'][$key]['ts'] = $ts;
                }
            }
            $storage->set_metadata($name, $metadata, true);
            return false;
        }
        return false;
    }
    /**
     * Store a value within the container
     *
     * @param  string $offset
     * @param  mixed  $value
     */
    public function offsetSet($offset, $value): void
    {
        $this->expire_keys($offset);
        $storage = $this->verify_namespace();
        $name = $this->get_name();
        $storage[$name][$offset] = $value;
    }
    /**
     * Determine if the key exists
     *
     * @param  string $key
     * @return bool
     */
    public function offsetExists($key)
    {
        // If no container exists, we can't inspect it
        if (null === $storage = $this->verify_namespace(false)) {
            return false;
        }
        $name = $this->get_name();
        // Return early if the key isn't set
        if (!isset($storage[$name][$key])) {
            return false;
        }
        $expired = $this->expire_keys($key);
        return !$expired;
    }
    /**
     * Retrieve a specific key in the container
     *
     * @param  string $key
     * @return mixed
     */
    public function &offsetGet($key)
    {
        if (!$this->offsetExists($key)) {
            return $this->default_value;
        }
        $storage = $this->get_storage();
        $name = $this->get_name();
        $ret =& $storage[$name][$key];
        return $ret;
    }
    /**
     * Unset a single key in the container
     *
     * @param  string $offset
     */
    public function offsetUnset($offset): void
    {
        if (!$this->offsetExists($offset)) {
            return;
        }
        $storage = $this->get_storage();
        $name = $this->get_name();
        unset($storage[$name][$offset]);
    }
    /** @inheritDoc */
    public function exchange_array($input)
    {
        // handle arrayobject, iterators and the like:
        if (is_object($input) && ($input instanceof ArrayObject || $input instanceof \ArrayObject)) {
            $input = $input->get_array_copy();
        }
        if (!is_array($input)) {
            $input = (array) $input;
        }
        $storage = $this->verify_namespace();
        $name = $this->get_name();
        $old = $storage[$name];
        $storage[$name] = $input;
        /** @psalm-var array<TKey, TValue> */
        return $old instanceof ArrayObject ? $old->get_array_copy() : $old;
    }
    /** @inheritDoc */
    public function getIterator()
    {
        $this->expire_keys();
        $storage = $this->get_storage();
        $container = $storage[$this->get_name()];
        if ($container instanceof Traversable) {
            return $container;
        }
        return new ArrayIterator($container);
    }
    /**
     * Set expiration TTL
     *
     * Set the TTL for the entire container, a single key, or a set of keys.
     *
     * @param  int                                $ttl  TTL in seconds
     * @param  string|array|null                  $vars
     * @return Container
     * @throws Exception\InvalidArgumentException
     */
    public function set_expiration_seconds($ttl, $vars = null)
    {
        $storage = $this->get_storage();
        $ts = time() + $ttl;
        if (is_scalar($vars) && null !== $vars) {
            $vars = (array) $vars;
        }
        if (null === $vars) {
            $this->expire_keys();
            // first we need to expire global key, since it can already be expired
            $data = ['EXPIRE' => $ts];
        } elseif (is_array($vars)) {
            // Cannot pass "$this" to a lambda
            $container = $this;
            // Filter out any items not in our container
            $expires = array_filter($vars, $container->offsetExists(...));
            // Map item keys => timestamp
            $expires = array_flip($expires);
            $expires = array_map(static fn(): float|int => $ts, $expires);
            // Create metadata array to merge in
            $data = ['EXPIRE_KEYS' => $expires];
        } else {
            throw new Exception\InvalidArgumentException('Unknown data provided as second argument to ' . __METHOD__);
        }
        $storage->set_metadata($this->get_name(), $data);
        return $this;
    }
    /**
     * Set expiration hops for the container, a single key, or set of keys
     *
     * @param  int                                $hops
     * @param  null|string|array                  $vars
     * @throws Exception\InvalidArgumentException
     * @return Container
     */
    public function set_expiration_hops($hops, $vars = null)
    {
        $storage = $this->get_storage();
        $ts = $storage->get_request_access_time();
        if (is_scalar($vars)) {
            $vars = (array) $vars;
        }
        if (null === $vars) {
            $this->expire_keys();
            // first we need to expire global key, since it can already be expired
            $data = ['EXPIRE_HOPS' => ['hops' => $hops, 'ts' => $ts]];
        } elseif (is_array($vars)) {
            // Cannot pass "$this" to a lambda
            $container = $this;
            // FilterInterface out any items not in our container
            $expires = array_filter($vars, $container->offsetExists(...));
            // Map item keys => timestamp
            $expires = array_flip($expires);
            $expires = array_map(static fn(): array => ['hops' => $hops, 'ts' => $ts], $expires);
            // Create metadata array to merge in
            $data = ['EXPIRE_HOPS_KEYS' => $expires];
        } else {
            throw new Exception\InvalidArgumentException('Unknown data provided as second argument to ' . __METHOD__);
        }
        $storage->set_metadata($this->get_name(), $data);
        return $this;
    }
    /** @inheritDoc */
    public function get_array_copy()
    {
        $storage = $this->verify_namespace();
        $container = $storage[$this->get_name()];
        /** @psalm-var array<TKey, TValue> */
        return $container instanceof ArrayObject ? $container->get_array_copy() : $container;
    }
}