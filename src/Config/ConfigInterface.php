<?php

declare (strict_types=1);
namespace Laminas\Session\Config;

/**
 * Standard session configuration
 */
interface Config_Interface
{
    /**
     * @param array<string, mixed> $options
     * @return void
     */
    public function set_options($options);
    /** @return array<string, mixed> */
    public function get_options();
    /**
     * @param string $option
     * @return void
     */
    public function set_option($option, mixed $value);
    /**
     * @param string $option
     * @return mixed
     */
    public function get_option($option);
    /**
     * @param string $option
     * @return bool
     */
    public function has_option($option);
    /** @return array */
    public function to_array();
    /**
     * @param string $name
     * @return void
     */
    public function set_name($name);
    /** @return string */
    public function get_name();
    /**
     * @param string $savePath
     * @return void
     */
    public function set_save_path($save_path);
    /** @return string */
    public function get_save_path();
    /**
     * @param int $cookieLifetime
     * @return void
     */
    public function set_cookie_lifetime($cookie_lifetime);
    /** @return int */
    public function get_cookie_lifetime();
    /**
     * @param string $cookiePath
     * @return void
     */
    public function set_cookie_path($cookie_path);
    /** @return string */
    public function get_cookie_path();
    /**
     * @param string $cookieDomain
     * @return void
     */
    public function set_cookie_domain($cookie_domain);
    /** @return string */
    public function get_cookie_domain();
    /**
     * @param bool $cookieSecure
     * @return void
     */
    public function set_cookie_secure($cookie_secure);
    /** @return bool */
    public function get_cookie_secure();
    /**
     * @param bool $cookieHttpOnly
     * @return void
     */
    public function set_cookie_http_only($cookie_http_only);
    /** @return bool */
    public function get_cookie_http_only();
    /**
     * @param bool $useCookies
     * @return void
     */
    public function set_use_cookies($use_cookies);
    /** @return bool */
    public function get_use_cookies();
    /**
     * @param int $rememberMeSeconds
     * @return void
     */
    public function set_remember_me_seconds($remember_me_seconds);
    /** @return int */
    public function get_remember_me_seconds();
}