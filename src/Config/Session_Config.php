<?php

declare (strict_types=1);
namespace Laminas\Session\Config;

use function array_merge;
use function array_search;
use function array_shift;
use function assert;
use function class_exists;
use const E_USER_DEPRECATED;
use const E_WARNING;
use function explode;
use function hash_algos;
use function implode;
use function in_array;
use const INFO_MODULES;
use function ini_get;
use function ini_set;
use function is_a;
use function is_array;
use function is_numeric;
use function is_string;
use Laminas\Session\Exception;
use Laminas\Session\Save_Handler\Save_Handler_Interface;
use function ob_get_clean;
use function ob_start;
use const PHP_SESSION_ACTIVE;
use function preg_match;
use function preg_split;
use function restore_error_handler;
use function session_set_save_handler;
use function session_start;
use function session_status;
use function session_write_close;
use Session_Handler_Interface;
use function set_error_handler;
use function sprintf;
use function str_contains;
use function strtolower;
use function trigger_error;
use function trim;
/**
 * Session configuration proxying to session INI options
 */
class Session_Config extends Standard_Config
{
    /**
     * @internal
     *
     * @var callable
     */
    public static $phpinfo = 'phpinfo';
    /**
     * @internal
     *
     * @var callable
     */
    public static $session_module_name = 'session_module_name';
    /**
     * List of known PHP save handlers.
     *
     * @var null|array
     */
    protected $known_save_handlers;
    /**
     * Used with {@link handleError()}; stores PHP error code
     *
     * @var int
     */
    protected $php_error_code = false;
    /**
     * Used with {@link handleError()}; stores PHP error message
     *
     * @var string
     */
    protected $php_error_message = false;
    /** @var int Default number of seconds to make session sticky, when rememberMe() is called */
    protected $remember_me_seconds = 1209600;
    // 2 weeks
    /**
     * Name of the save handler currently in use. This will either be a PHP
     * built-in save handler name, or the name of a SessionHandlerInterface
     * class being used as a save handler.
     *
     * @var null|string|SaveHandlerInterface
     */
    protected $save_handler;
    /** @var string session.serialize_handler */
    protected $serialize_handler;
    /** @var array Valid cache limiters (per session.cache_limiter) */
    protected $valid_cache_limiters = ['', 'nocache', 'public', 'private', 'private_no_expire'];
    /** @var array Valid hash bits per character (per session.hash_bits_per_character) */
    protected $valid_hash_bits_per_characters = [4, 5, 6];
    /** @var array Valid sid bits per character (per session.sid_bits_per_character) */
    protected $valid_sid_bits_per_characters = [4, 5, 6];
    /** @var array Valid hash functions (per session.hash_function) */
    protected $valid_hash_functions;
    /**
     * Override standard option setting.
     *
     * Provides an overload for setting the save handler.
     *
     * {@inheritDoc}
     */
    public function set_option($option, $value)
    {
        switch (strtolower($option)) {
            case 'save_handler':
                $this->set_php_save_handler($value);
                return $this;
            default:
                return parent::set_option($option, $value);
        }
    }
    /**
     * Set storage option in backend configuration store
     *
     * @param  string $storageName
     * @param  mixed $storageValue
     * @return SessionConfig
     * @throws Exception\InvalidArgumentException
     */
    public function set_storage_option($storage_name, $storage_value)
    {
        switch ($storage_name) {
            case 'remember_me_seconds':
                // do nothing; not an INI option
                return;
            case 'url_rewriter_tags':
                $key = 'url_rewriter.tags';
                break;
            case 'save_handler':
                // Save handlers must be treated differently due to changes
                // introduced in PHP 7.2. Do not alter running INI setting.
                return $this;
            default:
                $key = 'session.' . $storage_name;
                break;
        }
        $ini_get = ini_get($key);
        $storage_value = (string) $storage_value;
        if (false !== $ini_get && $ini_get === $storage_value) {
            return $this;
        }
        $session_requires_restart = false;
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
            $session_requires_restart = true;
        }
        $result = ini_set($key, $storage_value);
        if ($session_requires_restart) {
            session_start();
        }
        if (false === $result) {
            throw new Exception\InvalidArgumentException("'{$key}' is not a valid sessions-related ini setting.");
        }
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
        return match ($storage_option) {
            // No remote storage option; just return the current value
            'remember_me_seconds' => $this->remember_me_seconds,
            'url_rewriter_tags' => ini_get('url_rewriter.tags'),
            // The following all need a transformation on the retrieved value;
            // however they use the same key naming scheme
            'use_cookies', 'use_only_cookies', 'use_trans_sid', 'cookie_httponly' => (bool) ini_get('session.' . $storage_option),
            'save_handler' => $this->save_handler ?? $this->session_module_name(),
            default => ini_get('session.' . $storage_option),
        };
    }
    /**
     * Proxy to setPhpSaveHandler()
     *
     * Prevents calls to `setSaveHandler()` from hitting `setOption()` instead,
     * and thus bypassing the logic of `setPhpSaveHandler()`.
     *
     * @param  string $phpSaveHandler
     * @return SessionConfig
     * @throws Exception\InvalidArgumentException
     */
    public function set_save_handler($php_save_handler)
    {
        return $this->set_php_save_handler($php_save_handler);
    }
    /**
     * Set session.save_handler
     *
     * @param  string $phpSaveHandler
     * @throws Exception\InvalidArgumentException
     */
    public function set_php_save_handler($php_save_handler): static
    {
        $this->save_handler = $this->perform_save_handler_update($php_save_handler);
        $this->options['save_handler'] = $this->save_handler;
        return $this;
    }
    /**
     * Set session.save_path
     *
     * @param  string $savePath
     * @throws Exception\InvalidArgumentException On invalid path.
     */
    public function set_save_path($save_path): static
    {
        if ($this->get_option('save_handler') === 'files') {
            parent::set_save_path($save_path);
        }
        $this->save_path = $save_path;
        $this->set_option('save_path', $save_path);
        return $this;
    }
    /**
     * Set session.serialize_handler
     *
     * @param  string $serializeHandler
     * @throws Exception\InvalidArgumentException
     */
    public function set_serialize_handler($serialize_handler): static
    {
        $serialize_handler = (string) $serialize_handler;
        set_error_handler($this->handle_error(...));
        ini_set('session.serialize_handler', $serialize_handler);
        restore_error_handler();
        if ($this->php_error_code >= E_WARNING) {
            throw new Exception\InvalidArgumentException('Invalid serialize handler specified');
        }
        $this->serialize_handler = $serialize_handler;
        return $this;
    }
    // session.cache_limiter
    /**
     * Set cache limiter
     *
     * @param string $cacheLimiter
     * @throws Exception\InvalidArgumentException
     */
    public function set_cache_limiter($cache_limiter): static
    {
        $cache_limiter = (string) $cache_limiter;
        if (!in_array($cache_limiter, $this->valid_cache_limiters)) {
            throw new Exception\InvalidArgumentException('Invalid cache limiter provided');
        }
        $this->set_option('cache_limiter', $cache_limiter);
        ini_set('session.cache_limiter', $cache_limiter);
        return $this;
    }
    /**
     * Set session.hash_function
     *
     * @deprecated removed in PHP 7.1
     *
     * @param  string|int $hashFunction
     * @throws Exception\InvalidArgumentException
     */
    public function set_hash_function($hash_function): static
    {
        trigger_error('session.hash_function is removed starting with PHP 7.1', E_USER_DEPRECATED);
        $hash_function = (string) $hash_function;
        $valid_hash_functions = $this->get_hash_functions();
        if (!in_array($hash_function, $valid_hash_functions, true)) {
            throw new Exception\InvalidArgumentException('Invalid hash function provided');
        }
        $this->set_option('hash_function', $hash_function);
        ini_set('session.hash_function', $hash_function);
        return $this;
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
        if (!is_numeric($hash_bits_per_character) || !in_array($hash_bits_per_character, $this->valid_hash_bits_per_characters)) {
            throw new Exception\InvalidArgumentException('Invalid hash bits per character provided');
        }
        $hash_bits_per_character = (int) $hash_bits_per_character;
        $this->set_option('hash_bits_per_character', $hash_bits_per_character);
        ini_set('session.hash_bits_per_character', $hash_bits_per_character);
        return $this;
    }
    /**
     * Set session.sid_bits_per_character
     *
     * @deprecated see https://wiki.php.net/rfc/deprecations_php_8_4#sessionsid_length_and_sessionsid_bits_per_character
     *
     * @param  int $sidBitsPerCharacter
     * @throws Exception\InvalidArgumentException
     */
    public function set_sid_bits_per_character($sid_bits_per_character): static
    {
        if (!is_numeric($sid_bits_per_character) || !in_array($sid_bits_per_character, $this->valid_sid_bits_per_characters)) {
            throw new Exception\InvalidArgumentException('Invalid sid bits per character provided');
        }
        $sid_bits_per_character = (int) $sid_bits_per_character;
        $this->set_option('sid_bits_per_character', $sid_bits_per_character);
        ini_set('session.sid_bits_per_character', (string) $sid_bits_per_character);
        return $this;
    }
    /**
     * Retrieve list of valid hash functions
     *
     * @return array
     */
    protected function get_hash_functions()
    {
        if (empty($this->valid_hash_functions)) {
            /**
             * @link http://php.net/manual/en/session.configuration.php#ini.session.hash-function
             * "0" and "1" refer to MD5-128 and SHA1-160, respectively, and are
             * valid in addition to whatever is reported by hash_algos()
             */
            $this->valid_hash_functions = array_merge(['0', '1'], hash_algos());
        }
        return $this->valid_hash_functions;
    }
    /**
     * Handle PHP errors
     *
     * @param  int $code
     * @param  string $message
     * @return void
     */
    protected function handle_error($code, $message)
    {
        $this->php_error_code = $code;
        $this->php_error_message = $message;
    }
    /**
     * Determine what save handlers are available.
     *
     * The only way to get at this information is via phpinfo(), and the output
     * of that function varies based on the SAPI.
     *
     * Strips the handler "user" from the list, as PHP 7.2 does not allow
     * setting that as a handler, because it essentially requires you to have
     * already set a custom handler via `session_set_save_handler()`. It
     * wasn't really valid in prior versions, either; the language simply did
     * not complain previously.
     *
     * @return array
     */
    private function locate_registered_save_handlers()
    {
        if (null !== $this->known_save_handlers) {
            return $this->known_save_handlers;
        }
        if (!preg_match('#Registered save handlers.*#m', $this->get_php_info_for_modules(), $matches)) {
            $this->known_save_handlers = [];
            return $this->known_save_handlers;
        }
        $content = array_shift($matches);
        assert(is_string($content));
        $handlers = str_contains($content, '</td>') ? $this->parse_save_handlers_from_html($content) : $this->parse_save_handlers_from_plain_text($content);
        if (false !== $index = array_search('user', $handlers, true)) {
            unset($handlers[$index]);
        }
        $this->known_save_handlers = $handlers;
        return $this->known_save_handlers;
    }
    /**
     * Perform a session.save_handler update.
     *
     * Determines if the save handler represents a PHP built-in
     * save handler, and, if so, passes that value to session_module_name
     * in order to activate it. The save handler name is then returned.
     *
     * If it is not, it tests to see if it is a SessionHandlerInterface
     * implementation. If the string is a class implementing that interface,
     * it creates an instance of it. In such cases, it then calls
     * session_set_save_handler to activate it. The class name of the
     * handler is returned.
     *
     * In all other cases, an exception is raised.
     *
     * @param string|SessionHandlerInterface $phpSaveHandler
     * @throws Exception\InvalidArgumentException If an error occurs when
     *     setting a PHP session save handler module.
     * @throws Exception\InvalidArgumentException If the $phpSaveHandler
     *     is a string that does not represent a class implementing
     *     SessionHandlerInterface.
     * @throws Exception\InvalidArgumentException If $phpSaveHandler is
     *     a non-string value that does not implement SessionHandlerInterface.
     */
    private function perform_save_handler_update($php_save_handler): string
    {
        if (is_string($php_save_handler)) {
            $known_handlers = $this->locate_registered_save_handlers();
            if (in_array($php_save_handler, $known_handlers, true)) {
                $php_save_handler = strtolower($php_save_handler);
                set_error_handler($this->handle_error(...));
                $this->session_module_name($php_save_handler);
                restore_error_handler();
                if ($this->php_error_code >= E_WARNING) {
                    throw new Exception\InvalidArgumentException(sprintf('Error setting session save handler module "%s": %s', $php_save_handler, $this->php_error_message));
                }
                return $php_save_handler;
            }
            if (!class_exists($php_save_handler) || !is_a($php_save_handler, Session_Handler_Interface::class, true)) {
                throw new Exception\InvalidArgumentException(sprintf('Invalid save handler specified ("%s"); must be one of [%s]' . ' or a class implementing %s', $php_save_handler, implode(', ', $known_handlers), Session_Handler_Interface::class));
            }
            $php_save_handler = new $php_save_handler();
        }
        if (!$php_save_handler instanceof Session_Handler_Interface) {
            throw new Exception\InvalidArgumentException(sprintf('Invalid save handler specified ("%s"); must implement %s', $php_save_handler::class, Session_Handler_Interface::class));
        }
        session_set_save_handler($php_save_handler);
        return $php_save_handler::class;
    }
    /**
     * Grab module information from phpinfo.
     *
     * Requires capturing an output buffer, as phpinfo does not have an option
     * to return the value as a string.
     */
    private function get_php_info_for_modules(): string
    {
        $phpinfo = self::$phpinfo;
        ob_start();
        $phpinfo(INFO_MODULES);
        $ret = ob_get_clean();
        assert(is_string($ret));
        return $ret;
    }
    /**
     * Parse a list of PHP session save handlers from HTML.
     *
     * Format is "<tr><td class="e">Registered save handlers</td><td class="v">{handlers}</td></tr>".
     */
    private function parse_save_handlers_from_html(string $content): array
    {
        if (!preg_match('#<td class="v">(?P<handlers>[^<]+)</td>#', $content, $matches)) {
            return [];
        }
        $handlers = trim($matches['handlers']);
        $ret = preg_split('#\s+#', $handlers);
        assert(is_array($ret));
        return $ret;
    }
    /**
     * Parse a list of PHP session save handlers from plain text.
     *
     * Format is "Registered save handlers => <handlers>".
     *
     * @return array
     */
    private function parse_save_handlers_from_plain_text(string $content)
    {
        [$prefix, $handlers] = explode('=>', $content);
        $handlers = trim($handlers);
        $ret = preg_split('#\s+#', $handlers);
        assert(is_array($ret));
        return $ret;
    }
    /** @return false|string */
    private function session_module_name(?string $module = null)
    {
        $callback = self::$session_module_name;
        // session_module_name behaves differently when passed an explicit
        // `null` than it does when passed no arguments.
        if (null !== $module) {
            return $callback($module);
        }
        return $callback();
    }
}