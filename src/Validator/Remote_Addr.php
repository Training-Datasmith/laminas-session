<?php

declare (strict_types=1);
namespace Laminas\Session\Validator;

use Laminas\Http\Php_Environment\Remote_Address;
use Laminas\Session\Validator\Validator_Interface as SessionValidator;
/**
 * @final
 */
class Remote_Addr implements Session_Validator
{
    /**
     * Internal data.
     *
     * @deprecated This property will be removed in version 3.0
     *
     * @var string
     */
    protected $data;
    /**
     * Whether to use proxy addresses or not.
     *
     * As default this setting is disabled - IP address is mostly needed to increase
     * security. HTTP_* are not reliable since can easily be spoofed. It can be enabled
     * just for more flexibility, but if user uses proxy to connect to trusted services
     * it's his/her own risk, only reliable field for IP address is $_SERVER['REMOTE_ADDR'].
     *
     * @var bool
     */
    protected static $use_proxy = false;
    /**
     * List of trusted proxy IP addresses
     *
     * @var array
     */
    protected static $trusted_proxies = [];
    /**
     * HTTP header to introspect for proxies
     *
     * @var string
     */
    protected static $proxy_header = 'HTTP_X_FORWARDED_FOR';
    /**
     * Constructor
     * get the current user IP and store it in the session as 'valid data'
     *
     * @param null|string $data
     */
    public function __construct($data = null)
    {
        if ($data === null || $data === '') {
            $data = $this->get_ip_address();
        }
        $this->data = $data;
    }
    /**
     * isValid() - this method will determine if the current user IP matches the
     * IP we stored when we initialized this variable.
     */
    public function is_valid(): bool
    {
        return $this->get_ip_address() === $this->get_data();
    }
    /**
     * Changes proxy handling setting.
     *
     * This must be static method, since validators are recovered automatically
     * at session read, so this is the only way to switch setting.
     *
     * @param bool  $useProxy Whether to check also proxied IP addresses.
     */
    public static function set_use_proxy($use_proxy = true): void
    {
        static::$use_proxy = $use_proxy;
    }
    /**
     * Checks proxy handling setting.
     *
     * @return bool Current setting value.
     */
    public static function get_use_proxy()
    {
        return static::$use_proxy;
    }
    /**
     * Set list of trusted proxy addresses
     */
    public static function set_trusted_proxies(array $trusted_proxies): void
    {
        static::$trusted_proxies = $trusted_proxies;
    }
    /**
     * Set the header to introspect for proxy IPs
     *
     * @param  string $header
     */
    public static function set_proxy_header($header = 'X-Forwarded-For'): void
    {
        static::$proxy_header = $header;
    }
    /**
     * Returns client IP address.
     *
     * @return string IP address.
     */
    protected function get_ip_address()
    {
        $remote_address = new Remote_Address();
        $remote_address->set_use_proxy(static::$use_proxy);
        $remote_address->set_trusted_proxies(static::$trusted_proxies);
        $remote_address->set_proxy_header(static::$proxy_header);
        return $remote_address->get_ip_address();
    }
    /**
     * Retrieve token for validating call
     *
     * @deprecated This method will be removed in version 3.0
     *
     * @return string
     */
    public function get_data()
    {
        return $this->data;
    }
    /**
     * Return validator name
     */
    public function get_name(): string
    {
        return self::class;
    }
}