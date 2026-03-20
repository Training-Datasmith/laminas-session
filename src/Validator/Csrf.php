<?php

declare (strict_types=1);
namespace Laminas\Session\Validator;

use function assert;
use function explode;
use function hash_equals;
use function is_array;
use function is_string;
use Laminas\Session\Container;
use Laminas\Validator\Abstract_Validator;
use function md5;
use function random_bytes;
use function sprintf;
use function str_replace;
use function strtr;
/**
 * @psalm-type OptionsArgument = array{
 *     name?: non-empty-string,
 *     salt?: non-empty-string,
 *     session?: Container,
 *     timeout?: ?int,
 * }
 * @final
 */
final class Csrf extends Abstract_Validator
{
    /**
     * Error codes
     *
     * @const string
     */
    public const NOT_SAME = 'notSame';
    /**
     * Error messages
     *
     * @var array<string, string>
     */
    protected array $message_templates = [self::NOT_SAME => 'The form submitted did not originate from the expected site'];
    /**
     * Actual hash used.
     */
    private ?string $hash = null;
    /**
     * Name of CSRF element (used to create non-colliding hashes)
     *
     * @var non-empty-string
     */
    private string $name = 'csrf';
    /**
     * Salt for CSRF token
     *
     * @var non-empty-string
     */
    private string $salt = 'salt';
    private ?Container $session = null;
    /**
     * TTL for CSRF token
     */
    private int|null $timeout = 300;
    /** @param OptionsArgument $options */
    public function __construct(array $options = [])
    {
        parent::__construct($options);
    }
    /**
     * Does the provided token match the one generated?
     *
     * @param array<string, mixed>|null $context
     */
    public function is_valid(mixed $value, array|null $context = null): bool
    {
        if (!is_string($value)) {
            return false;
        }
        $this->set_value($value);
        $token_id = $this->get_token_id_from_hash($value);
        $hash = $this->get_validation_token($token_id);
        $token_from_value = $this->get_token_from_hash($value);
        $token_from_hash = $this->get_token_from_hash($hash);
        if ($token_from_value === null || $token_from_hash === null || !hash_equals($token_from_hash, $token_from_value)) {
            $this->error(self::NOT_SAME);
            return false;
        }
        return true;
    }
    /**
     * Set CSRF name
     *
     * @deprecated This method will be removed in version 3.0
     *
     * @param non-empty-string $name
     */
    public function set_name(string $name): void
    {
        $this->name = $name;
    }
    /**
     * Get CSRF name
     *
     * @deprecated This method will be removed in version 3.0
     *
     * @return non-empty-string
     */
    public function get_name(): string
    {
        return $this->name;
    }
    /**
     * Set session container
     *
     * @deprecated This method will be removed in version 3.0
     */
    public function set_session(Container $session): void
    {
        $this->session = $session;
        if ($this->hash !== null) {
            $this->init_csrf_token();
        }
    }
    /**
     * Get session container
     *
     * Instantiate session container if none currently exists
     *
     * @deprecated This method will be removed in version 3.0
     */
    public function get_session(): Container
    {
        if (null === $this->session) {
            $this->session = new Container($this->get_session_name());
        }
        return $this->session;
    }
    /**
     * Salt for CSRF token
     *
     * @deprecated This method will be removed in version 3.0
     *
     * @param non-empty-string $salt
     */
    public function set_salt(string $salt): void
    {
        $this->salt = $salt;
    }
    /**
     * Retrieve salt for CSRF token
     *
     * @deprecated This method will be removed in version 3.0
     *
     * @return non-empty-string
     */
    public function get_salt(): string
    {
        return $this->salt;
    }
    /**
     * Retrieve CSRF token
     *
     * If no CSRF token currently exists, or should be regenerated,
     * generates one.
     *
     * @deprecated This method will be removed in version 3.0
     */
    public function get_hash(bool $regenerate = false): string
    {
        if (null === $this->hash || $regenerate) {
            $this->generate_hash();
        }
        assert($this->hash !== null);
        return $this->hash;
    }
    /**
     * Get session namespace for CSRF token
     *
     * Generates a session namespace based on salt, element name, and class.
     */
    public function get_session_name(): string
    {
        return str_replace('\\', '_', self::class) . '_' . $this->get_salt() . '_' . strtr($this->get_name(), ['[' => '_', ']' => '']);
    }
    /**
     * Set timeout for CSRF session token
     *
     * @deprecated This method will be removed in version 3.0
     */
    public function set_timeout(int|null $ttl): void
    {
        $this->timeout = $ttl;
    }
    /**
     * Get CSRF session token timeout
     *
     * @deprecated This method will be removed in version 3.0
     */
    public function get_timeout(): int|null
    {
        return $this->timeout;
    }
    /**
     * Initialize CSRF token in session
     */
    private function init_csrf_token(): void
    {
        $session = $this->get_session();
        $timeout = $this->get_timeout();
        if (null !== $timeout) {
            $session->set_expiration_seconds($timeout);
        }
        $hash = $this->get_hash();
        $token = $this->get_token_from_hash($hash);
        $token_id = $this->get_token_id_from_hash($hash);
        assert(is_string($token_id));
        $token_list = $session->token_list ?? [];
        assert(is_array($token_list));
        $token_list[$token_id] = $token;
        $session->token_list = $token_list;
        $session->hash = $hash;
        // @todo remove this, left for BC
    }
    /**
     * Generate CSRF token
     *
     * Generates CSRF token and stores both in {@link $hash} and element value.
     */
    private function generate_hash(): void
    {
        $token = md5($this->get_salt() . random_bytes(32) . $this->get_name());
        $this->hash = $this->format_hash($token, $this->generate_token_id());
        $this->set_value($this->hash);
        $this->init_csrf_token();
    }
    private function generate_token_id(): string
    {
        return md5(random_bytes(32));
    }
    /**
     * Get validation token
     *
     * Retrieve token from session, if it exists.
     */
    private function get_validation_token(string|null $token_id = null): string|null
    {
        $session = $this->get_session();
        /**
         * if no tokenId is passed we revert to the old behaviour
         *
         * @todo remove, here for BC
         */
        if ($token_id === null && isset($session->hash) && is_string($session->hash)) {
            return $session->hash;
        }
        if ($token_id !== null && isset($session->token_list[$token_id]) && is_string($session->token_list[$token_id])) {
            return $this->format_hash($session->token_list[$token_id], $token_id);
        }
        return null;
    }
    private function format_hash(string $token, string $token_id): string
    {
        return sprintf('%s-%s', $token, $token_id);
    }
    private function get_token_from_hash(?string $hash): ?string
    {
        if (null === $hash) {
            return null;
        }
        $data = explode('-', $hash);
        return $data[0] ?: null;
    }
    private function get_token_id_from_hash(string $hash): ?string
    {
        $data = explode('-', $hash);
        if (!isset($data[1])) {
            return null;
        }
        return $data[1];
    }
}