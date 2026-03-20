<?php

declare (strict_types=1);
namespace Laminas\Session\Save_Handler;

use function array_replace;
use function floor;
use function ini_get;
use Laminas\Session\Exception\InvalidArgumentException;
use function microtime;
use Mongo_Db\BSON\Binary;
use Mongo_Db\BSON\Int64;
use Mongo_Db\BSON\Utc_Date_Time;
use Mongo_Db\Client as MongoClient;
use Mongo_Db\Collection as MongoCollection;
use Return_Type_Will_Change;
use function time;
/**
 * MongoDB session save handler
 *
 * @deprecated This class will be removed without replacement in version 3.0.
 *
 * @see ReturnTypeWillChange
 */
class Mongo_Db implements Save_Handler_Interface
{
    /**
     * MongoCollection instance
     *
     * @var MongoCollection
     */
    protected $mongo_collection;
    /**
     * Session name
     *
     * @var string
     */
    protected $session_name;
    /**
     * Session lifetime
     *
     * @var int
     */
    protected $lifetime;
    /**
     * MongoDB session save handler options
     */
    protected \Laminas\Session\Save_Handler\Mongo_Db_Options $options;
    /**
     * Constructor
     *
     * @param MongoClient $mongoClient
     * @throws InvalidArgumentException
     */
    public function __construct(protected $mongo_client, Mongo_Db_Options $options)
    {
        if (null === $database = $options->get_database()) {
            throw new InvalidArgumentException('The database option cannot be empty');
        }
        if (null === $collection = $options->get_collection()) {
            throw new InvalidArgumentException('The collection option cannot be empty');
        }
        $this->options = $options;
    }
    /**
     * Open session
     *
     * @param string $path
     * @param string $name
     * @return bool
     */
    #[Return_Type_Will_Change]
    public function open($path, $name)
    {
        // Note: session save path is not used
        $this->session_name = $name;
        $this->lifetime = (int) ini_get('session.gc_maxlifetime');
        $this->mongo_collection = $this->mongo_client->select_collection($this->options->get_database(), $this->options->get_collection());
        $this->mongo_collection->create_index([$this->options->get_modified_field() => 1], $this->options->use_expire_after_seconds_index() ? ['expireAfterSeconds' => $this->lifetime] : []);
        return true;
    }
    /**
     * Close session
     *
     * @return bool
     */
    #[Return_Type_Will_Change]
    public function close()
    {
        return true;
    }
    /**
     * Read session data
     *
     * @param string $id
     * @return string
     */
    #[Return_Type_Will_Change]
    public function read($id)
    {
        $session = $this->mongo_collection->find_one(['_id' => $id, $this->options->get_name_field() => $this->session_name]);
        if (null !== $session) {
            // check if session has expired if index is not used
            if (!$this->options->use_expire_after_seconds_index()) {
                $timestamp = $session[$this->options->get_lifetime_field()];
                $timestamp += floor((string) $session[$this->options->get_modified_field()] / 1000);
                // session expired
                if ($timestamp <= time()) {
                    $this->destroy($id);
                    return '';
                }
            }
            return $session[$this->options->get_data_field()]->get_data();
        }
        return '';
    }
    /**
     * Write session data
     *
     * @param string $id
     * @param string $data
     * @return bool
     */
    #[Return_Type_Will_Change]
    public function write($id, $data)
    {
        $save_options = array_replace($this->options->get_save_options(), ['upsert' => true, 'multiple' => false]);
        $criteria = ['_id' => $id, $this->options->get_name_field() => $this->session_name];
        $new_obj = ['$set' => [$this->options->get_data_field() => new Binary((string) $data, Binary::TYPE_GENERIC), $this->options->get_lifetime_field() => $this->lifetime, $this->options->get_modified_field() => new Utc_Date_Time(new Int64((string) floor(microtime(true) * 1000.0)))]];
        /* Note: a MongoCursorException will be thrown if a record with this ID
         * already exists with a different session name, since the upsert query
         * cannot insert a new document with the same ID and new session name.
         * This should only happen if ID's are not unique or if the session name
         * is altered mid-process.
         */
        $result = $this->mongo_collection->update_one($criteria, $new_obj, $save_options);
        return $result->is_acknowledged();
    }
    /**
     * Destroy session
     *
     * @param string $id
     * @return bool
     */
    #[Return_Type_Will_Change]
    public function destroy($id)
    {
        $result = $this->mongo_collection->delete_one(['_id' => $id, $this->options->get_name_field() => $this->session_name], $this->options->get_save_options());
        return $result->is_acknowledged();
    }
    /**
     * Garbage collection
     *
     * Note: MongoDB 2.2+ supports TTL collections, which may be used in place
     * of this method by indexing the "modified" field with an
     * "expireAfterSeconds" option. Regardless of whether TTL collections are
     * used, consider indexing this field to make the remove query more
     * efficient.
     *
     * @see http://docs.mongodb.org/manual/tutorial/expire-data/
     *
     * @param int $maxlifetime
     * @return bool
     */
    #[Return_Type_Will_Change]
    public function gc($maxlifetime)
    {
        /* Note: unlike DbTableGateway, we do not use the lifetime field in
         * each document. Doing so would require a $where query to work with the
         * computed value (modified + lifetime) and be very inefficient.
         */
        $microseconds = floor(microtime(true) * 1000.0) - (float) $maxlifetime * 1000.0;
        $result = $this->mongo_collection->delete_many([$this->options->get_modified_field() => ['$lt' => new Utc_Date_Time(new Int64((string) $microseconds))]], $this->options->get_save_options());
        return $result->is_acknowledged();
    }
}