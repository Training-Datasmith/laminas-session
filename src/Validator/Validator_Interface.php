<?php

declare (strict_types=1);
namespace Laminas\Session\Validator;

/**
 * Session validator interface
 */
interface Validator_Interface
{
    /**
     * This method will be called at the beginning of
     * every session to determine if the current environment matches
     * that which was store in the setup() procedure.
     *
     * @return bool
     */
    public function is_valid();
    /**
     * Get data from validator to be used for validation comparisons
     *
     * @deprecated This method will be removed in version 3.0
     *
     * @return mixed
     */
    public function get_data();
    /**
     * Get validator name for use with storing validators between requests
     *
     * @return string
     */
    public function get_name();
}