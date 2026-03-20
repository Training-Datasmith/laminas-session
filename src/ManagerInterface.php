<?php

declare (strict_types=1);
namespace Laminas\Session;

use Laminas\Event_Manager\Event_Manager_Interface;
use Laminas\Session\Config\Config_Interface as Config;
use Laminas\Session\Save_Handler\Save_Handler_Interface as SaveHandler;
use Laminas\Session\Storage\Storage_Interface as Storage;
/**
 * Session manager interface
 */
interface Manager_Interface
{
    /** @return self */
    public function set_config(Config $config);
    /** @return Config */
    public function get_config();
    /** @return self */
    public function set_storage(Storage $storage);
    /** @return Storage */
    public function get_storage();
    /** @return self */
    public function set_save_handler(Save_Handler $save_handler);
    /** @return SaveHandler */
    public function get_save_handler();
    /** @return bool */
    public function session_exists();
    /** @return void */
    public function start();
    /** @return void */
    public function destroy();
    /** @return void */
    public function write_close();
    /**
     * @param string $name
     * @return self
     */
    public function set_name($name);
    /** @return string */
    public function get_name();
    /**
     * @param int|string $id
     * @return self
     */
    public function set_id($id);
    /** @return int|string */
    public function get_id();
    /** @return self */
    public function regenerate_id();
    /**
     * @param null|int $ttl
     * @return self
     */
    public function remember_me($ttl = null);
    /** @return self */
    public function forget_me();
    /** @return void */
    public function expire_session_cookie();
    /** @return self */
    public function set_validator_chain(Event_Manager_Interface $chain);
    /** @return EventManagerInterface */
    public function get_validator_chain();
    /** @return bool */
    public function is_valid();
}