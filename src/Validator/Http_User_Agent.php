<?php

declare (strict_types=1);
namespace Laminas\Session\Validator;

/**
 * @final
 */
class Http_User_Agent implements Validator_Interface
{
    /**
     * Internal data
     *
     * @deprecated This property will be removed in version 3.0
     *
     * @var string
     */
    protected $data;
    /**
     * Constructor
     * get the current user agent and store it in the session as 'valid data'
     *
     * @param string|null $data
     */
    public function __construct($data = null)
    {
        if ($data === null || $data === '') {
            $data = $_SERVER['HTTP_USER_AGENT'] ?? null;
        }
        $this->data = $data;
    }
    /**
     * isValid() - this method will determine if the current user agent matches the
     * user agent we stored when we initialized this variable.
     */
    public function is_valid(): bool
    {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        return $user_agent === $this->get_data();
    }
    /**
     * Retrieve the User-Agent string captured at session start.
     *
     * @deprecated This method will be removed in version 3.0; access the stored UA string another way.
     * @return string|null The captured HTTP_USER_AGENT value, or null if none was available.
     */
    public function get_data(): ?string
    {
        return $this->data;
    }

    /**
     * Return the fully-qualified class name as the validator identifier.
     *
     * @return string The validator name (FQCN of this class).
     */
    public function get_name(): string
    {
        return self::class;
    }
}