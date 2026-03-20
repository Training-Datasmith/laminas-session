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