<?php

declare (strict_types=1);
namespace Laminas\Session\Validator;

use function assert;
use function ini_get;
use function is_numeric;
use function is_string;
use const PHP_VERSION_ID;
use function preg_match;
use function session_id;
use function strrpos;
use function substr;
/**
 * session_id validator
 *
 * @final
 */
class Id implements Validator_Interface
{
    /**
     * Session identifier.
     *
     * @deprecated This property will be removed in version 3.0
     *
     * @var string
     */
    protected $id;
    /**
     * Constructor
     *
     * Allows passing the current session_id; if none provided, uses the PHP
     * session_id() function to retrieve it.
     *
     * @param null|string $id
     */
    public function __construct($id = null)
    {
        if ($id === null || $id === '') {
            $id = session_id();
            assert(is_string($id));
        }
        $this->id = $id;
    }
    /**
     * Is the current session identifier valid?
     *
     * Tests that the identifier does not contain invalid characters.
     */
    public function is_valid(): bool
    {
        $id = $this->id;
        $save_handler = ini_get('session.save_handler');
        if ($save_handler === 'cluster') {
            // Zend Server SC, validate only after last dash
            $dash_pos = strrpos($id, '-');
            if ($dash_pos !== false) {
                $id = substr($id, $dash_pos + 1);
            }
        }
        if (PHP_VERSION_ID >= 80400) {
            // PHP 8.4 deprecated session.sid_bits_per_character and set it hard to "4".
            // Old (pre PHP 8.4) session IDs with a higher bitrate are still valid though.
            $hash_bits_per_char = 6;
        } else {
            // Get the session id bits per character INI setting, using 5 if unavailable
            $hash_bits_per_char = ini_get('session.sid_bits_per_character');
            $hash_bits_per_char = is_numeric($hash_bits_per_char) ? (int) $hash_bits_per_char : 5;
        }
        $pattern = match ($hash_bits_per_char) {
            4 => '#^[0-9a-f]*$#',
            6 => '#^[0-9a-zA-Z-,]*$#',
            // 5
            // intentionally fall-through
            default => '#^[0-9a-v]*$#',
        };
        return (bool) preg_match($pattern, $id);
    }
    /**
     * Retrieve token for validating call (session_id)
     *
     * @deprecated This method will be removed in version 3.0
     *
     * @return string
     */
    public function get_data()
    {
        return $this->id;
    }
    /**
     * Return validator name
     */
    public function get_name(): string
    {
        return self::class;
    }
}