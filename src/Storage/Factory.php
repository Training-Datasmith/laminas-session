<?php

declare (strict_types=1);
// phpcs:disable WebimpressCodingStandard.NamingConventions.AbstractClass.Prefix,Generic.NamingConventions.ConstructorName.OldStyle
namespace Laminas\Session\Storage;

use ArrayAccess;
use function class_exists;
use function class_implements;
use function class_parents;
use function get_debug_type;
use function in_array;
use function is_array;
use function is_string;
use Laminas\Session\Exception;
use Laminas\Stdlib\ArrayObject;
use Laminas\Stdlib\Array_Utils;
use function sprintf;
use Traversable;
abstract class Factory
{
    /**
     * Create and return a StorageInterface instance
     *
     * @param  string                             $type
     * @param  array|Traversable                  $options
     * @return StorageInterface
     * @throws Exception\InvalidArgumentException For unrecognized $type or individual options.
     */
    public static function factory($type, $options = [])
    {
        if (!is_string($type)) {
            throw new Exception\InvalidArgumentException(sprintf('%s expects the $type argument to be a string class name; received "%s"', __METHOD__, get_debug_type($type)));
        }
        if (!class_exists($type)) {
            $class = __NAMESPACE__ . '\\' . $type;
            if (!class_exists($class)) {
                throw new Exception\InvalidArgumentException(sprintf('%s expects the $type argument to be a valid class name; received "%s"', __METHOD__, $type));
            }
            $type = $class;
        }
        if ($options instanceof Traversable) {
            $options = Array_Utils::iterator_to_array($options);
        }
        if (!is_array($options)) {
            throw new Exception\InvalidArgumentException(sprintf('%s expects the $options argument to be an array or Traversable; received "%s"', __METHOD__, get_debug_type($options)));
        }
        $class_parents = class_parents($type);
        $class_implements = class_implements($type);
        return match (true) {
            in_array(Abstract_Session_Array_Storage::class, $class_parents !== false ? $class_parents : []) => static::create_session_array_storage($type, $options),
            $type === Array_Storage::class, in_array(Array_Storage::class, $class_parents !== false ? $class_parents : []) => static::create_array_storage($type, $options),
            in_array(Storage_Interface::class, $class_implements !== false ? $class_implements : []) => new $type($options),
            default => throw new Exception\InvalidArgumentException(sprintf('Unrecognized type "%s" provided; expects a class implementing %s\StorageInterface', $type, __NAMESPACE__)),
        };
    }
    /**
     * Create a storage object from an ArrayStorage class (or a descendent)
     *
     * @param  string       $type
     * @return ArrayStorage
     */
    protected static function create_array_storage($type, array $options)
    {
        $input = [];
        $flags = ArrayObject::ARRAY_AS_PROPS;
        $iterator_class = 'ArrayIterator';
        if (isset($options['input']) && null !== $options['input']) {
            if (!is_array($options['input'])) {
                throw new Exception\InvalidArgumentException(sprintf('%s expects the "input" option to be an array; received "%s"', $type, get_debug_type($options['input'])));
            }
            $input = $options['input'];
        }
        if (isset($options['flags'])) {
            $flags = $options['flags'];
        }
        if (isset($options['iterator_class'])) {
            if (!class_exists($options['iterator_class'])) {
                throw new Exception\InvalidArgumentException(sprintf('%s expects the "iterator_class" option to be a valid class; received "%s"', $type, get_debug_type($options['iterator_class'])));
            }
            $iterator_class = $options['iterator_class'];
        }
        return new $type($input, $flags, $iterator_class);
    }
    /**
     * Create a storage object from a class extending AbstractSessionArrayStorage
     *
     * @return AbstractSessionArrayStorage
     * @throws Exception\InvalidArgumentException If the input option is invalid.
     */
    protected static function create_session_array_storage(string $type, array $options)
    {
        $input = null;
        if (isset($options['input'])) {
            $input = $options['input'];
            if (!is_array($input) && !$input instanceof ArrayAccess) {
                throw new Exception\InvalidArgumentException(sprintf('%s expects the "input" option to be null, an array, or to implement ArrayAccess; received "%s"', $type, get_debug_type($input)));
            }
        }
        return new $type($input);
    }
}