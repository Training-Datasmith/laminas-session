<?php

declare (strict_types=1);
namespace Laminas\Session;

use function array_key_exists;
use function array_merge;
use function assert;
use function constant;
use function defined;
use function headers_sent;
use function is_array;
use function is_string;
use function iterator_to_array;
use Laminas\Event_Manager\Event;
use Laminas\Event_Manager\Event_Manager_Interface;
use Laminas\Stdlib\Array_Utils;
use const PHP_SESSION_ACTIVE;
use function preg_match;
use function register_shutdown_function;
use function session_destroy;
use function session_id;
use function session_name;
use function session_regenerate_id;
use function session_set_save_handler;
use function session_start;
use function session_status;
use function session_write_close;
use function setcookie;
use Traversable;
/**
 * Session ManagerInterface implementation utilizing ext/session
 *
 * @final
 */
class Session_Manager extends Abstract_Manager
{
    /**
     * Default options when a call to {@link destroy()} is made
     * - send_expire_cookie: whether or not to send a cookie expiring the current session cookie
     * - clear_storage: whether or not to empty the storage object of any stored values
     *
     * @deprecated This property will be removed in version 3.0
     *
     * @var array
     */
    protected $default_destroy_options = ['send_expire_cookie' => true, 'clear_storage' => false];
    /**
     * @deprecated This property will be removed in version 3.0
     *
     * @var array Default session manager options
     */
    protected $default_options = ['attach_default_validators' => true];
    /** @var array Default validators */
    protected $default_validators = [Validator\Id::class];
    /** @var string value returned by session_name() */
    protected $name;
    /** @var EventManagerInterface Validation chain to determine if session is valid */
    protected $validator_chain;
    /**
     * Constructor
     *
     * @throws Exception\RuntimeException
     */
    public function __construct(?Config\Config_Interface $config = null, ?Storage\Storage_Interface $storage = null, ?Save_Handler\Save_Handler_Interface $save_handler = null, array $validators = [], array $options = [])
    {
        $options = array_merge($this->default_options, $options);
        if ($options['attach_default_validators']) {
            $validators = array_merge($this->default_validators, $validators);
        }
        parent::__construct($config, $storage, $save_handler, $validators);
        register_shutdown_function([$this, 'writeClose']);
    }
    /**
     * Does a session exist and is it currently active?
     */
    public function session_exists(): bool
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return true;
        }
        /**
         * @var string|false $sid
         */
        $sid = defined('SID') ? constant('SID') : false;
        if ($sid !== false && $this->get_id()) {
            return true;
        }
        if (headers_sent()) {
            return true;
        }
        return false;
    }
    /**
     * Start session
     *
     * if No session currently exists, attempt to start it. Calls
     * {@link isValid()} once session_start() is called, and raises an
     * exception if validation fails.
     *
     * @param bool $preserveStorage        If set to true, current session storage will not be overwritten by the
     *                                     contents of $_SESSION.
     * @throws Exception\RuntimeException
     */
    public function start($preserve_storage = false): void
    {
        if ($this->session_exists()) {
            return;
        }
        $save_handler = $this->get_save_handler();
        if ($save_handler instanceof Save_Handler\Save_Handler_Interface) {
            // register the session handler with ext/session
            $this->register_save_handler($save_handler);
        }
        $old_session_data = [];
        if (isset($_SESSION)) {
            $old_session_data = $_SESSION;
            // convert session data to plain array that’ll be acceptable as
            // ArrayUtils::merge parameter
            if ($old_session_data instanceof Storage\Storage_Interface) {
                $old_session_data = $old_session_data->to_array();
            } elseif ($old_session_data instanceof Traversable) {
                $old_session_data = iterator_to_array($old_session_data);
            }
        }
        session_start();
        if (!empty($old_session_data) && is_array($old_session_data)) {
            $_SESSION = Array_Utils::merge($old_session_data, $_SESSION, true);
        }
        $storage = $this->get_storage();
        // Since session is starting, we need to potentially repopulate our
        // session storage
        if ($storage instanceof Storage\Session_Storage && $_SESSION !== $storage) {
            if (!$preserve_storage) {
                $storage->from_array($_SESSION);
            }
            $_SESSION = $storage;
        } elseif ($storage instanceof Storage\Storage_Initialization_Interface) {
            $storage->init($_SESSION);
        }
        $this->initialize_validator_chain();
        if (!$this->is_valid()) {
            throw new Exception\RuntimeException('Session validation failed');
        }
    }
    /**
     * Create validators, insert reference value and add them to the validator chain
     */
    protected function initialize_validator_chain()
    {
        $validator_chain = $this->get_validator_chain();
        $validator_values = $this->get_storage()->get_metadata('_VALID');
        foreach ($this->validators as $validator) {
            // Ignore validators which are already present in Storage
            if (is_array($validator_values) && array_key_exists($validator, $validator_values)) {
                continue;
            }
            $validator = new $validator(null);
            $validator_chain->attach('session.validate', [$validator, 'isValid']);
        }
    }
    /**
     * Destroy/end a session
     *
     * @param  array $options See {@link $defaultDestroyOptions}
     */
    public function destroy(?array $options = null): void
    {
        // session_destroy() requires active session while method
        // $this->sessionExists() includes other conditions
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        if (null === $options) {
            $options = $this->default_destroy_options;
        } else {
            $options = array_merge($this->default_destroy_options, $options);
        }
        session_destroy();
        if (!headers_sent() && $options['send_expire_cookie']) {
            $this->expire_session_cookie();
        }
        if ($options['clear_storage']) {
            $this->get_storage()->clear();
        }
    }
    /**
     * Write session to save handler and close
     *
     * Once done, the Storage object will be marked as isImmutable.
     */
    public function write_close(): void
    {
        // The assumption is that we're using PHP's ext/session.
        // session_write_close() will actually overwrite $_SESSION with an
        // empty array on completion -- which leads to a mismatch between what
        // is in the storage object and $_SESSION. To get around this, we
        // temporarily reset $_SESSION to an array, and then re-link it to
        // the storage object.
        //
        // Additionally, while you _can_ write to $_SESSION following a
        // session_write_close() operation, no changes made to it will be
        // flushed to the session handler. As such, we now mark the storage
        // object isImmutable.
        $storage = $this->get_storage();
        if (!$storage->is_immutable()) {
            $_SESSION = $storage->to_array(true);
            session_write_close();
            $storage->from_array($_SESSION);
            $storage->mark_immutable();
        }
    }
    /**
     * Attempt to set the session name
     *
     * If the session has already been started, or if the name provided fails
     * validation, an exception will be raised.
     *
     * @param  string $name
     * @throws Exception\InvalidArgumentException
     */
    public function set_name($name): static
    {
        if ($this->session_exists()) {
            throw new Exception\InvalidArgumentException('Cannot set session name after a session has already started');
        }
        if (!preg_match('/^[a-zA-Z0-9]+$/', $name)) {
            throw new Exception\InvalidArgumentException('Name provided contains invalid characters; must be alphanumeric only');
        }
        $this->name = $name;
        session_name($name);
        return $this;
    }
    /**
     * Get session name
     *
     * Proxies to {@link session_name()}.
     *
     * @return string
     */
    public function get_name()
    {
        if (null === $this->name) {
            // If we're grabbing via session_name(), we don't need our
            // validation routine; additionally, calling setName() after
            // session_start() can lead to issues, and often we just need the name
            // in order to do things such as setting cookies.
            $name = session_name();
            assert(is_string($name));
            $this->name = $name;
        }
        return $this->name;
    }
    /**
     * Set session ID
     *
     * Can safely be called in the middle of a session.
     *
     * @param  string $id
     */
    public function set_id($id): static
    {
        if ($this->session_exists()) {
            throw new Exception\RuntimeException('Session has already been started, to change the session ID call regenerateId()');
        }
        session_id($id);
        return $this;
    }
    /**
     * Get session ID
     *
     * Proxies to {@link session_id()}
     */
    public function get_id(): string
    {
        $ret = session_id();
        assert(is_string($ret));
        return $ret;
    }
    /**
     * Regenerate id
     *
     * Regenerate the session ID, using session save handler's
     * native ID generation Can safely be called in the middle of a session.
     *
     * @param  bool $deleteOldSession
     */
    public function regenerate_id($delete_old_session = true): static
    {
        if ($this->session_exists()) {
            session_regenerate_id((bool) $delete_old_session);
        }
        return $this;
    }
    /**
     * Set the TTL (in seconds) for the session cookie expiry
     *
     * Can safely be called in the middle of a session.
     *
     * @param  null|int $ttl
     */
    public function remember_me($ttl = null): static
    {
        if (null === $ttl) {
            $ttl = $this->get_config()->get_remember_me_seconds();
        }
        $this->set_session_cookie_lifetime($ttl);
        return $this;
    }
    /**
     * Set a 0s TTL for the session cookie
     *
     * Can safely be called in the middle of a session.
     */
    public function forget_me(): static
    {
        $this->set_session_cookie_lifetime(0);
        return $this;
    }
    /**
     * Set the validator chain to use when validating a session
     *
     * In most cases, you should use an instance of {@link ValidatorChain}.
     */
    public function set_validator_chain(Event_Manager_Interface $chain): static
    {
        $this->validator_chain = $chain;
        return $this;
    }
    /**
     * Get the validator chain to use when validating a session
     *
     * By default, uses an instance of {@link ValidatorChain}.
     *
     * @return EventManagerInterface
     */
    public function get_validator_chain()
    {
        if (null === $this->validator_chain) {
            $this->set_validator_chain(new Validator_Chain($this->get_storage()));
        }
        return $this->validator_chain;
    }
    /**
     * Is this session valid?
     *
     * Notifies the Validator Chain until either all validators have returned
     * true or one has failed.
     */
    public function is_valid(): bool
    {
        $validator = $this->get_validator_chain();
        $event = new Event();
        $event->set_name('session.validate');
        $event->set_target($this);
        $event->set_params($this);
        $false_result = static fn($test): bool => false === $test;
        $responses = $validator->trigger_event_until($false_result, $event);
        if ($responses->stopped()) {
            // If execution was halted, validation failed
            return false;
        }
        // Otherwise, we're good to go
        return true;
    }
    /**
     * Expire the session cookie
     *
     * Sends a session cookie with no value, and with an expiry in the past.
     */
    public function expire_session_cookie(): void
    {
        $config = $this->get_config();
        if (!$config->get_use_cookies()) {
            return;
        }
        setcookie(
            $this->get_name(),
            // session name
            '',
            ['expires' => $_SERVER['REQUEST_TIME'] - 42000, 'path' => $config->get_cookie_path(), 'domain' => $config->get_cookie_domain(), 'secure' => (bool) $config->get_cookie_secure(), 'httponly' => (bool) $config->get_cookie_http_only()]
        );
    }
    /**
     * Set the session cookie lifetime
     *
     * If a session already exists, destroys it (without sending an expiration
     * cookie), regenerates the session ID, and restarts the session.
     *
     * @param  int $ttl
     * @return void
     */
    protected function set_session_cookie_lifetime($ttl)
    {
        $config = $this->get_config();
        if (!$config->get_use_cookies()) {
            return;
        }
        // Set new cookie TTL
        $config->set_cookie_lifetime($ttl);
        if ($this->session_exists()) {
            // There is a running session so we'll regenerate id to send a new cookie
            $this->regenerate_id();
        }
    }
    /**
     * Register Save Handler with ext/session
     *
     * Since ext/session is coupled to this particular session manager
     * register the save handler with ext/session.
     */
    protected function register_save_handler(Save_Handler\Save_Handler_Interface $save_handler): bool
    {
        return session_set_save_handler($save_handler);
    }
}