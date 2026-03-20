<?php

declare (strict_types=1);
namespace Laminas\Session\Config;

use function array_key_exists;
use function array_merge;
use function array_shift;
use function assert;
use const E_USER_DEPRECATED;
use function implode;
use function is_array;
use function is_dir;
use function is_numeric;
use function is_readable;
use function is_string;
use function is_writable;
use Laminas\Session\Exception;
use Laminas\Validator\Hostname as HostnameValidator;
use function method_exists;
use function parse_url;
use const PHP_URL_PATH;
use function preg_replace;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function substr;
use Traversable;
use function trigger_error;
use function ucwords;
/**
 * Standard session configuration
 */
class Standard_Config implements Config_Interface, Same_Site_Cookie_Capable_Interface
{
    /**
     * session.name
     *
     * @var string
     */
    protected $name;
    /**
     * session.save_path
     *
     * @var string
     */
    protected $save_path;
    /**
     * session.cookie_lifetime
     *
     * @var int
     */
    protected $cookie_lifetime;
    /**
     * session.cookie_path
     *
     * @var string
     */
    protected $cookie_path;
    /**
     * session.cookie_domain
     *
     * @var string
     */
    protected $cookie_domain;
    /**
     * session.cookie_samesite
     *
     * @var string
     */
    protected $cookie_same_site;
    /**
     * session.cookie_secure
     *
     * @var bool
     */
    protected $cookie_secure;
    /**
     * session.cookie_httponly
     *
     * @var bool
     */
    protected $cookie_http_only;
    /**
     * remember_me_seconds
     *
     * @var int
     */
    protected $remember_me_seconds;
    /**
     * session.use_cookies
     *
     * @var bool
     */
    protected $use_cookies;
    /**
     * All options
     *
     * @var array
     */
    protected $options = [];
    /**
     * Set many options at once
     *
     * If a setter method exists for the key, that method will be called;
     * otherwise, a standard option will be set with the value provided via
     * {@link setOption()}.
     *
     * @param  array|Traversable $options
     * @throws Exception\InvalidArgumentException
     */
    public function set_options($options): static
    {
        if (!is_array($options) && !$options instanceof Traversable) {
            throw new Exception\InvalidArgumentException(sprintf('Parameter provided to %s must be an array or Traversable', __METHOD__));
        }
        foreach ($options as $key => $value) {
            $setter = 'set' . str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));
            if (method_exists($this, $setter)) {
                $this->{$setter}($value);
            } else {
                $this->set_option($key, $value);
            }
        }
        return $this;
    }
    /**
     * Get all options set
     *
     * @return array
     */
    public function get_options()
    {
        return $this->options;
    }
    /**
     * Set an individual option
     *
     * Keys are normalized to lowercase. After setting internally, calls
     * {@link setStorageOption()} to allow further processing.
     *
     * @param  string $option
     * @param  mixed $value
     */
    public function set_option($option, $value): static
    {
        $option = strtolower($option);
        $this->options[$option] = $value;
        $this->set_storage_option($option, $value);
        return $this;
    }
    /**
     * Get an individual option
     *
     * Keys are normalized to lowercase. If the option is not found, attempts
     * to retrieve it via {@link getStorageOption()}; if a value is returned
     * from that method, it will be set as the internal value and returned.
     *
     * Returns null for unfound options
     *
     * @param  string $option
     * @return mixed
     */
    public function get_option($option)
    {
        $option = strtolower($option);
        if (array_key_exists($option, $this->options)) {
            return $this->options[$option];
        }
        $value = $this->get_storage_option($option);
        if (null !== $value) {
            $this->set_option($option, $value);
            return $value;
        }
        return null;
    }
    /**
     * Check to see if an internal option has been set for the key provided.
     *
     * @param  string $option
     */
    public function has_option($option): bool
    {
        $option = strtolower($option);
        return array_key_exists($option, $this->options);
    }
    /**
     * Set storage option in backend configuration store
     *
     * Does nothing in this implementation; others might use it to set things
     * such as INI settings.
     *
     * @param  string $storageName
     * @param  mixed $storageValue
     */
    public function set_storage_option($storage_name, $storage_value): static
    {
        return $this;
    }
    /**
     * Retrieve a storage option from a backend configuration store
     *
     * Used to retrieve default values from a backend configuration store.
     *
     * @param  string $storageOption
     * @return mixed
     */
    public function get_storage_option($storage_option)
    {
    }
    /**
     * Set session.save_path
     *
     * @param  string $savePath
     * @throws Exception\InvalidArgumentException On invalid path.
     */
    public function set_save_path($save_path): static
    {
        if (!is_dir($save_path)) {
            throw new Exception\InvalidArgumentException('Invalid save_path provided; not a directory');
        }
        if (!is_writable($save_path)) {
            throw new Exception\InvalidArgumentException('Invalid save_path provided; not writable');
        }
        $this->save_path = $save_path;
        $this->set_storage_option('save_path', $save_path);
        return $this;
    }
    /**
     * Set session.save_path
     *
     * @return string|null
     */
    public function get_save_path()
    {
        if (null === $this->save_path) {
            $this->save_path = $this->get_storage_option('save_path');
        }
        return $this->save_path;
    }
    /**
     * Set session.name
     *
     * @param  string $name
     * @throws Exception\InvalidArgumentException
     */
    public function set_name($name): static
    {
        $this->name = (string) $name;
        if (empty($this->name)) {
            throw new Exception\InvalidArgumentException('Invalid session name; cannot be empty');
        }
        $this->set_storage_option('name', $this->name);
        return $this;
    }
    /**
     * Get session.name
     *
     * @return null|string
     */
    public function get_name()
    {
        if (null === $this->name) {
            $this->name = $this->get_storage_option('name');
        }
        return $this->name;
    }
    /**
     * Set session.gc_probability
     *
     * @param  int $gcProbability
     * @throws Exception\InvalidArgumentException
     */
    public function set_gc_probability($gc_probability): static
    {
        if (!is_numeric($gc_probability)) {
            throw new Exception\InvalidArgumentException('Invalid gc_probability; must be numeric');
        }
        $gc_probability = (int) $gc_probability;
        if (0 > $gc_probability || 100 < $gc_probability) {
            throw new Exception\InvalidArgumentException('Invalid gc_probability; must be a percentage');
        }
        $this->set_option('gc_probability', $gc_probability);
        $this->set_storage_option('gc_probability', $gc_probability);
        return $this;
    }
    /**
     * Get session.gc_probability
     *
     * @return int
     */
    public function get_gc_probability()
    {
        if (!isset($this->options['gc_probability'])) {
            $this->options['gc_probability'] = $this->get_storage_option('gc_probability');
        }
        return $this->options['gc_probability'];
    }
    /**
     * Set session.gc_divisor
     *
     * @param  int $gcDivisor
     * @throws Exception\InvalidArgumentException
     */
    public function set_gc_divisor($gc_divisor): static
    {
        if (!is_numeric($gc_divisor)) {
            throw new Exception\InvalidArgumentException('Invalid gc_divisor; must be numeric');
        }
        $gc_divisor = (int) $gc_divisor;
        if (1 > $gc_divisor) {
            throw new Exception\InvalidArgumentException('Invalid gc_divisor; must be a positive integer');
        }
        $this->set_option('gc_divisor', $gc_divisor);
        $this->set_storage_option('gc_divisor', $gc_divisor);
        return $this;
    }
    /**
     * Get session.gc_divisor
     *
     * @return int
     */
    public function get_gc_divisor()
    {
        if (!isset($this->options['gc_divisor'])) {
            $this->options['gc_divisor'] = $this->get_storage_option('gc_divisor');
        }
        return $this->options['gc_divisor'];
    }
    /**
     * Set gc_maxlifetime
     *
     * @param  int $gcMaxlifetime
     * @throws Exception\InvalidArgumentException
     */
    public function set_gc_maxlifetime($gc_maxlifetime): static
    {
        if (!is_numeric($gc_maxlifetime)) {
            throw new Exception\InvalidArgumentException('Invalid gc_maxlifetime; must be numeric');
        }
        $gc_maxlifetime = (int) $gc_maxlifetime;
        if (1 > $gc_maxlifetime) {
            throw new Exception\InvalidArgumentException('Invalid gc_maxlifetime; must be a positive integer');
        }
        $this->set_option('gc_maxlifetime', $gc_maxlifetime);
        $this->set_storage_option('gc_maxlifetime', $gc_maxlifetime);
        return $this;
    }
    /**
     * Get session.gc_maxlifetime
     *
     * @return int
     */
    public function get_gc_maxlifetime()
    {
        if (!isset($this->options['gc_maxlifetime'])) {
            $this->options['gc_maxlifetime'] = $this->get_storage_option('gc_maxlifetime');
        }
        return $this->options['gc_maxlifetime'];
    }
    /**
     * Set session.cookie_lifetime
     *
     * @param  int $cookieLifetime
     * @throws Exception\InvalidArgumentException
     */
    public function set_cookie_lifetime($cookie_lifetime): static
    {
        if (!is_numeric($cookie_lifetime)) {
            throw new Exception\InvalidArgumentException('Invalid cookie_lifetime; must be numeric');
        }
        if (0 > $cookie_lifetime) {
            throw new Exception\InvalidArgumentException('Invalid cookie_lifetime; must be a positive integer or zero');
        }
        $this->cookie_lifetime = (int) $cookie_lifetime;
        $this->set_storage_option('cookie_lifetime', $this->cookie_lifetime);
        return $this;
    }
    /**
     * Get session.cookie_lifetime
     *
     * @return int
     */
    public function get_cookie_lifetime()
    {
        if (null === $this->cookie_lifetime) {
            $this->cookie_lifetime = $this->get_storage_option('cookie_lifetime');
        }
        return $this->cookie_lifetime;
    }
    /**
     * Set session.cookie_path
     *
     * @param  string $cookiePath
     * @throws Exception\InvalidArgumentException
     */
    public function set_cookie_path($cookie_path): static
    {
        $path = parse_url($cookie_path, PHP_URL_PATH);
        assert(is_string($path));
        if ($path !== $cookie_path || !str_starts_with($path, '/')) {
            throw new Exception\InvalidArgumentException('Invalid cookie path');
        }
        $this->cookie_path = $cookie_path;
        $this->set_storage_option('cookie_path', $cookie_path);
        return $this;
    }
    /**
     * Get session.cookie_path
     *
     * @return string
     */
    public function get_cookie_path()
    {
        if (null === $this->cookie_path) {
            $this->cookie_path = $this->get_storage_option('cookie_path');
        }
        return $this->cookie_path;
    }
    /**
     * Set session.cookie_domain
     *
     * @param  string $cookieDomain
     * @throws Exception\InvalidArgumentException
     */
    public function set_cookie_domain($cookie_domain): static
    {
        if (!is_string($cookie_domain)) {
            throw new Exception\InvalidArgumentException('Invalid cookie domain: must be a string');
        }
        $validator = new Hostname_Validator(Hostname_Validator::ALLOW_ALL);
        if (!empty($cookie_domain) && !$validator->is_valid($cookie_domain)) {
            throw new Exception\InvalidArgumentException('Invalid cookie domain: ' . implode('; ', $validator->get_messages()));
        }
        $this->cookie_domain = $cookie_domain;
        $this->set_storage_option('cookie_domain', $cookie_domain);
        return $this;
    }
    /**
     * Get session.cookie_domain
     *
     * @return string
     */
    public function get_cookie_domain()
    {
        if (null === $this->cookie_domain) {
            $this->cookie_domain = $this->get_storage_option('cookie_domain');
        }
        return $this->cookie_domain;
    }
    /**
     * Set session.cookie_samesite
     *
     * @param  string $cookieSameSite
     */
    public function set_cookie_same_site($cookie_same_site): static
    {
        $this->cookie_same_site = (string) $cookie_same_site;
        $this->set_storage_option('cookie_samesite', $this->cookie_same_site);
        return $this;
    }
    /**
     * Get session.cookie_samesite
     *
     * @return string
     */
    public function get_cookie_same_site()
    {
        if (null === $this->cookie_same_site) {
            $this->cookie_same_site = $this->get_storage_option('cookie_samesite');
        }
        return $this->cookie_same_site;
    }
    /**
     * Set session.cookie_secure
     *
     * @param  bool $cookieSecure
     */
    public function set_cookie_secure($cookie_secure): static
    {
        $this->cookie_secure = (bool) $cookie_secure;
        $this->set_storage_option('cookie_secure', $this->cookie_secure);
        return $this;
    }
    /**
     * Get session.cookie_secure
     *
     * @return bool|string
     */
    public function get_cookie_secure()
    {
        if (null === $this->cookie_secure) {
            $this->cookie_secure = $this->get_storage_option('cookie_secure');
        }
        return $this->cookie_secure;
    }
    /**
     * Set session.cookie_httponly
     *
     * case sensitive method lookups in setOptions means this method has an
     * unusual casing
     *
     * @param  bool $cookieHttpOnly
     */
    public function set_cookie_http_only($cookie_http_only): static
    {
        $this->cookie_http_only = (bool) $cookie_http_only;
        $this->set_storage_option('cookie_httponly', $this->cookie_http_only);
        return $this;
    }
    /**
     * Get session.cookie_httponly
     *
     * @return bool|string
     */
    public function get_cookie_http_only()
    {
        if (null === $this->cookie_http_only) {
            $this->cookie_http_only = $this->get_storage_option('cookie_httponly');
        }
        return $this->cookie_http_only;
    }
    /**
     * Set session.use_cookies
     *
     * @param  bool $useCookies
     */
    public function set_use_cookies($use_cookies): static
    {
        $this->use_cookies = (bool) $use_cookies;
        $this->set_storage_option('use_cookies', $this->use_cookies);
        return $this;
    }
    /**
     * Get session.use_cookies
     *
     * @return bool
     */
    public function get_use_cookies()
    {
        if (null === $this->use_cookies) {
            $this->use_cookies = $this->get_storage_option('use_cookies');
        }
        return $this->use_cookies;
    }
    /**
     * Set session.entropy_file
     *
     * @deprecated removed in PHP 7.1
     *
     * @param  string $entropyFile
     * @throws Exception\InvalidArgumentException
     */
    public function set_entropy_file($entropy_file): static
    {
        trigger_error('session.entropy_file is removed starting with PHP 7.1', E_USER_DEPRECATED);
        if (!is_readable($entropy_file)) {
            throw new Exception\InvalidArgumentException(sprintf("Invalid entropy_file provided: '%s'; doesn't exist or not readable", $entropy_file));
        }
        $this->set_option('entropy_file', $entropy_file);
        $this->set_storage_option('entropy_file', $entropy_file);
        return $this;
    }
    /**
     * Get session.entropy_file
     *
     * @deprecated removed in PHP 7.1
     *
     * @return string
     */
    public function get_entropy_file()
    {
        trigger_error('session.entropy_file is removed starting with PHP 7.1', E_USER_DEPRECATED);
        if (!isset($this->options['entropy_file'])) {
            $this->options['entropy_file'] = $this->get_storage_option('entropy_file');
        }
        return $this->options['entropy_file'];
    }
    /**
     * set session.entropy_length
     *
     * @deprecated removed in PHP 7.1
     *
     * @param  int $entropyLength
     * @throws Exception\InvalidArgumentException
     */
    public function set_entropy_length($entropy_length): static
    {
        trigger_error('session.entropy_length is removed starting with PHP 7.1', E_USER_DEPRECATED);
        if (!is_numeric($entropy_length)) {
            throw new Exception\InvalidArgumentException('Invalid entropy_length; must be numeric');
        }
        if (0 > $entropy_length) {
            throw new Exception\InvalidArgumentException('Invalid entropy_length; must be a positive integer or zero');
        }
        $this->set_option('entropy_length', $entropy_length);
        $this->set_storage_option('entropy_length', $entropy_length);
        return $this;
    }
    /**
     * Get session.entropy_length
     *
     * @deprecated removed in PHP 7.1
     *
     * @return string
     */
    public function get_entropy_length()
    {
        trigger_error('session.entropy_length is removed starting with PHP 7.1', E_USER_DEPRECATED);
        if (!isset($this->options['entropy_length'])) {
            $this->options['entropy_length'] = $this->get_storage_option('entropy_length');
        }
        return $this->options['entropy_length'];
    }
    /**
     * Set session.cache_expire
     *
     * @param  int $cacheExpire
     * @throws Exception\InvalidArgumentException
     */
    public function set_cache_expire($cache_expire): static
    {
        if (!is_numeric($cache_expire)) {
            throw new Exception\InvalidArgumentException('Invalid cache_expire; must be numeric');
        }
        $cache_expire = (int) $cache_expire;
        if (1 > $cache_expire) {
            throw new Exception\InvalidArgumentException('Invalid cache_expire; must be a positive integer');
        }
        $this->set_option('cache_expire', $cache_expire);
        $this->set_storage_option('cache_expire', $cache_expire);
        return $this;
    }
    /**
     * Get session.cache_expire
     *
     * @return string
     */
    public function get_cache_expire()
    {
        if (!isset($this->options['cache_expire'])) {
            $this->options['cache_expire'] = $this->get_storage_option('cache_expire');
        }
        return $this->options['cache_expire'];
    }
    /**
     * Set session.hash_function
     *
     * @deprecated removed in PHP 7.1
     *
     * @param  string $hashFunction
     * @return mixed
     */
    public function set_hash_function($hash_function)
    {
        trigger_error('session.hash_function is removed starting with PHP 7.1', E_USER_DEPRECATED);
        return $this->set_option('hash_function', $hash_function);
    }
    /**
     * Get session.hash_function
     *
     * @deprecated removed in PHP 7.1
     *
     * @return string
     */
    public function get_hash_function()
    {
        trigger_error('session.hash_function is removed starting with PHP 7.1', E_USER_DEPRECATED);
        return $this->get_option('hash_function');
    }
    /**
     * Set session.hash_bits_per_character
     *
     * @deprecated removed in PHP 7.1
     *
     * @param  int $hashBitsPerCharacter
     * @throws Exception\InvalidArgumentException
     */
    public function set_hash_bits_per_character($hash_bits_per_character): static
    {
        trigger_error('session.hash_bits_per_character is removed starting with PHP 7.1', E_USER_DEPRECATED);
        if (!is_numeric($hash_bits_per_character)) {
            throw new Exception\InvalidArgumentException('Invalid hash bits per character provided');
        }
        $hash_bits_per_character = (int) $hash_bits_per_character;
        $this->set_option('hash_bits_per_character', $hash_bits_per_character);
        $this->set_storage_option('hash_bits_per_character', $hash_bits_per_character);
        return $this;
    }
    /**
     * Get session.hash_bits_per_character
     *
     * @deprecated removed in PHP 7.1
     *
     * @return string
     */
    public function get_hash_bits_per_character()
    {
        trigger_error('session.hash_bits_per_character is removed starting with PHP 7.1', E_USER_DEPRECATED);
        if (!isset($this->options['hash_bits_per_character'])) {
            $this->options['hash_bits_per_character'] = $this->get_storage_option('hash_bits_per_character');
        }
        return $this->options['hash_bits_per_character'];
    }
    /**
     * Set session.sid_length
     *
     * @deprecated see https://wiki.php.net/rfc/deprecations_php_8_4#sessionsid_length_and_sessionsid_bits_per_character
     *
     * @param  int $sidLength
     * @throws Exception\InvalidArgumentException
     */
    public function set_sid_length($sid_length): static
    {
        if (!is_numeric($sid_length) || $sid_length < 22 || $sid_length > 256) {
            throw new Exception\InvalidArgumentException('Invalid length provided');
        }
        $sid_length = (int) $sid_length;
        $this->set_option('sid_length', $sid_length);
        $this->set_storage_option('sid_length', $sid_length);
        return $this;
    }
    /**
     * Get session.sid_length
     *
     * @return string
     */
    public function get_sid_length()
    {
        if (!isset($this->options['sid_length'])) {
            $this->options['sid_length'] = $this->get_storage_option('sid_length');
        }
        return $this->options['sid_length'];
    }
    /**
     * Set session.sid_bits_per_character
     *
     * @param  int $sidBitsPerCharacter
     * @throws Exception\InvalidArgumentException
     */
    public function set_sid_bits_per_character($sid_bits_per_character): static
    {
        if (!is_numeric($sid_bits_per_character)) {
            throw new Exception\InvalidArgumentException('Invalid sid bits per character provided');
        }
        $sid_bits_per_character = (int) $sid_bits_per_character;
        $this->set_option('sid_bits_per_character', $sid_bits_per_character);
        $this->set_storage_option('sid_bits_per_character', $sid_bits_per_character);
        return $this;
    }
    /**
     * Get session.sid_bits_per_character
     *
     * @return string
     */
    public function get_sid_bits_per_character()
    {
        if (!isset($this->options['sid_bits_per_character'])) {
            $this->options['sid_bits_per_character'] = $this->get_storage_option('sid_bits_per_character');
        }
        return $this->options['sid_bits_per_character'];
    }
    /**
     * Set remember_me_seconds
     *
     * @param  int $rememberMeSeconds
     * @throws Exception\InvalidArgumentException
     */
    public function set_remember_me_seconds($remember_me_seconds): static
    {
        if (!is_numeric($remember_me_seconds)) {
            throw new Exception\InvalidArgumentException('Invalid remember_me_seconds; must be numeric');
        }
        $remember_me_seconds = (int) $remember_me_seconds;
        if (1 > $remember_me_seconds) {
            throw new Exception\InvalidArgumentException('Invalid remember_me_seconds; must be a positive integer');
        }
        $this->remember_me_seconds = $remember_me_seconds;
        $this->set_storage_option('remember_me_seconds', $remember_me_seconds);
        return $this;
    }
    /**
     * Get remember_me_seconds
     *
     * @return int
     */
    public function get_remember_me_seconds()
    {
        if (null === $this->remember_me_seconds) {
            $this->remember_me_seconds = $this->get_storage_option('remember_me_seconds');
        }
        return $this->remember_me_seconds;
    }
    /**
     * Cast configuration to an array
     */
    public function to_array(): array
    {
        $extra_opts = ['cookie_domain' => $this->get_cookie_domain(), 'cookie_httponly' => $this->get_cookie_http_only(), 'cookie_lifetime' => $this->get_cookie_lifetime(), 'cookie_path' => $this->get_cookie_path(), 'cookie_samesite' => $this->get_cookie_same_site(), 'cookie_secure' => $this->get_cookie_secure(), 'name' => $this->get_name(), 'remember_me_seconds' => $this->get_remember_me_seconds(), 'save_path' => $this->get_save_path(), 'use_cookies' => $this->get_use_cookies()];
        return array_merge($this->options, $extra_opts);
    }
    /**
     * Intercept get*() and set*() methods
     *
     * Intercepts getters and setters and passes them to getOption() and setOption(),
     * respectively.
     *
     * @param  array $args
     * @return mixed
     * @throws Exception\BadMethodCallException On non-getter/setter method.
     */
    public function __call(string $method, array $args)
    {
        $prefix = substr($method, 0, 3);
        $option = substr($method, 3);
        $key = preg_replace('#(?<=[a-z])([A-Z])#', '_\1', $option);
        assert(is_string($key));
        $key = strtolower($key);
        if ($prefix === 'set') {
            $value = array_shift($args);
            return $this->set_option($key, $value);
        }
        if ($prefix === 'get') {
            return $this->get_option($key);
        }
        throw new Exception\BadMethodCallException(sprintf('Method "%s" does not exist in %s', $method, static::class));
    }
}