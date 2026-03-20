<?php

declare (strict_types=1);
namespace Laminas\Session\Save_Handler;

use function is_array;
use Laminas\Session\Exception\InvalidArgumentException;
use Laminas\Stdlib\Abstract_Options;
use function phpversion;
use function strlen;
use function strtolower;
use function version_compare;
/**
 * MongoDB session save handler Options
 *
 * @deprecated This class will be removed without replacement in version 3.0.
 */
class Mongo_Db_Options extends Abstract_Options
{
    /**
     * Database name
     *
     * @var string
     */
    protected $database;
    /**
     * Collection name
     *
     * @var string
     */
    protected $collection;
    /**
     * Save options
     *
     * @see http://php.net/manual/en/mongocollection.save.php
     *
     * @var string
     */
    protected $save_options = ['w' => 1];
    /**
     * Name field
     *
     * @var string
     */
    protected $name_field = 'name';
    /**
     * Data field
     *
     * @var string
     */
    protected $data_field = 'data';
    /**
     * Lifetime field
     *
     * @var string
     */
    protected $lifetime_field = 'lifetime';
    /**
     * Modified field
     *
     * @var string
     */
    protected $modified_field = 'modified';
    /**
     * Use expireAfterSeconds index
     *
     * @var bool
     */
    protected $use_expire_after_seconds_index = false;
    /**
     * {@inheritdoc}
     */
    public function __construct($options = null)
    {
        parent::__construct($options);
        $mongo_version = phpversion('mongo');
        $mongo_version = $mongo_version === false ? '0.0.0' : $mongo_version;
        if ($this->save_options === ['w' => 1] && version_compare($mongo_version, '1.3.0', '<')) {
            $this->save_options = ['safe' => true];
        }
    }
    /**
     * Override AbstractOptions::__set
     *
     * Validates value if save options are being set.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function __set($key, $value)
    {
        if (strtolower($key) !== 'saveoptions') {
            parent::__set($key, $value);
            return;
        }
        if (!is_array($value)) {
            throw new InvalidArgumentException('Expected array for save options');
        }
        $this->set_save_options($value);
    }
    /**
     * Set database name
     *
     * @param string $database
     * @return MongoDBOptions
     * @throws InvalidArgumentException
     */
    public function set_database($database)
    {
        $database = (string) $database;
        if (strlen($database) === 0) {
            throw new InvalidArgumentException('$database must be a non-empty string');
        }
        $this->database = $database;
        return $this;
    }
    /**
     * Get database name
     *
     * @return string
     */
    public function get_database()
    {
        return $this->database;
    }
    /**
     * Set collection name
     *
     * @param string $collection
     * @return MongoDBOptions
     * @throws InvalidArgumentException
     */
    public function set_collection($collection)
    {
        $collection = (string) $collection;
        if (strlen($collection) === 0) {
            throw new InvalidArgumentException('$collection must be a non-empty string');
        }
        $this->collection = $collection;
        return $this;
    }
    /**
     * Get collection name
     *
     * @return string
     */
    public function get_collection()
    {
        return $this->collection;
    }
    /**
     * Set save options
     *
     * @see http://php.net/manual/en/mongocollection.save.php
     *
     * @return MongoDBOptions
     */
    public function set_save_options(array $save_options)
    {
        $this->save_options = $save_options;
        return $this;
    }
    /**
     * Get save options
     *
     * @return string
     */
    public function get_save_options()
    {
        return $this->save_options;
    }
    /**
     * Set name field
     *
     * @param string $nameField
     * @return MongoDBOptions
     * @throws InvalidArgumentException
     */
    public function set_name_field($name_field)
    {
        $name_field = (string) $name_field;
        if (strlen($name_field) === 0) {
            throw new InvalidArgumentException('$nameField must be a non-empty string');
        }
        $this->name_field = $name_field;
        return $this;
    }
    /**
     * Get name field
     *
     * @return string
     */
    public function get_name_field()
    {
        return $this->name_field;
    }
    /**
     * Set data field
     *
     * @param string $dataField
     * @return MongoDBOptions
     * @throws InvalidArgumentException
     */
    public function set_data_field($data_field)
    {
        $data_field = (string) $data_field;
        if (strlen($data_field) === 0) {
            throw new InvalidArgumentException('$dataField must be a non-empty string');
        }
        $this->data_field = $data_field;
        return $this;
    }
    /**
     * Get data field
     *
     * @return string
     */
    public function get_data_field()
    {
        return $this->data_field;
    }
    /**
     * Set lifetime field
     *
     * @param string $lifetimeField
     * @return MongoDBOptions
     * @throws InvalidArgumentException
     */
    public function set_lifetime_field($lifetime_field)
    {
        $lifetime_field = (string) $lifetime_field;
        if (strlen($lifetime_field) === 0) {
            throw new InvalidArgumentException('$lifetimeField must be a non-empty string');
        }
        $this->lifetime_field = $lifetime_field;
        return $this;
    }
    /**
     * Get lifetime Field
     *
     * @return string
     */
    public function get_lifetime_field()
    {
        return $this->lifetime_field;
    }
    /**
     * Set Modified Field
     *
     * @param string $modifiedField
     * @return MongoDBOptions
     * @throws InvalidArgumentException
     */
    public function set_modified_field($modified_field)
    {
        $modified_field = (string) $modified_field;
        if (strlen($modified_field) === 0) {
            throw new InvalidArgumentException('$modifiedField must be a non-empty string');
        }
        $this->modified_field = $modified_field;
        return $this;
    }
    /**
     * Get modified Field
     *
     * @return string
     */
    public function get_modified_field()
    {
        return $this->modified_field;
    }
    /**
     * @return boolean
     */
    public function use_expire_after_seconds_index()
    {
        return $this->use_expire_after_seconds_index;
    }
    /**
     * Enable expireAfterSeconds index.
     *
     * @see http://docs.mongodb.org/manual/tutorial/expire-data/
     *
     * @param boolean $useExpireAfterSecondsIndex
     */
    public function set_use_expire_after_seconds_index($use_expire_after_seconds_index): void
    {
        $this->use_expire_after_seconds_index = (bool) $use_expire_after_seconds_index;
    }
}