<?php

declare (strict_types=1);
namespace Laminas\Session\Save_Handler;

use Laminas\Cache\Storage\Clear_Expired_Interface as ClearExpiredCacheStorage;
use Laminas\Cache\Storage\Storage_Interface as CacheStorage;
use Return_Type_Will_Change;
/**
 * Cache session save handler
 *
 * @see ReturnTypeWillChange
 *
 * @final
 */
class Cache implements Save_Handler_Interface
{
    /**
     * Session Save Path
     *
     * @deprecated This property will no longer be needed in the future and will therefore be removed in version 3.0.
     *
     * @var string
     */
    protected $session_save_path;
    /**
     * Session Name
     *
     * @deprecated This property will no longer be needed in the future and will therefore be removed in version 3.0.
     *
     * @var string
     */
    protected $session_name;
    /**
     * The cache storage
     *
     * @var CacheStorage
     */
    protected $cache_storage;
    /**
     * Constructor
     */
    public function __construct(Cache_Storage $cache_storage)
    {
        $this->set_cache_storage($cache_storage);
    }
    /**
     * Open Session
     *
     * @param string $path
     * @param string $name
     * @return bool
     */
    #[Return_Type_Will_Change]
    public function open($path, $name)
    {
        $this->session_save_path = $path;
        $this->session_name = $name;
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
        return (string) $this->get_cache_storage()->get_item($id);
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
        return $this->get_cache_storage()->set_item($id, $data);
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
        $this->get_cache_storage()->get_item($id, $exists);
        if (!(bool) $exists) {
            return true;
        }
        return (bool) $this->get_cache_storage()->remove_item($id);
    }
    /**
     * Garbage Collection
     *
     * @param int $maxlifetime
     * @return bool
     */
    #[Return_Type_Will_Change]
    public function gc($maxlifetime)
    {
        $cache = $this->get_cache_storage();
        if ($cache instanceof Clear_Expired_Cache_Storage) {
            return $cache->clear_expired();
        }
        return true;
    }
    /**
     * Set cache storage
     *
     * @deprecated This method will no longer be needed in the future and will therefore be removed in version 3.0.
     */
    public function set_cache_storage(Cache_Storage $cache_storage): static
    {
        $this->cache_storage = $cache_storage;
        return $this;
    }
    /**
     * Get cache storage
     *
     * @deprecated This method will no longer be needed in the future and will therefore be removed in version 3.0.
     *
     * @return CacheStorage
     */
    public function get_cache_storage()
    {
        return $this->cache_storage;
    }
    /**
     * @deprecated Misspelled method - use getCacheStorage() instead. Will be removed in version 3.0
     *
     * @return CacheStorage
     */
    public function get_cache_storge()
    {
        return $this->get_cache_storage();
    }
}