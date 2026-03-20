<?php

declare (strict_types=1);
namespace Laminas\Session\Config;

interface Same_Site_Cookie_Capable_Interface
{
    /**
     * @param string $cookieSameSite
     * @return self
     */
    public function set_cookie_same_site($cookie_same_site);
    /** @return string */
    public function get_cookie_same_site();
}