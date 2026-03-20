<?php

declare (strict_types=1);
namespace Laminas\Session\Save_Handler;

use Laminas\Session\Exception;
use Laminas\Stdlib\Abstract_Options;
use function strlen;
/**
 * DbTableGateway Save Handler Options
 *
 * @deprecated This class will be removed without replacement in version 3.0.
 */
class Db_Table_Gateway_Options extends Abstract_Options
{
    /**
     * ID Column
     *
     * @var string
     */
    protected $id_column = 'id';
    /**
     * Name Column
     *
     * @var string
     */
    protected $name_column = 'name';
    /**
     * Data Column
     *
     * @var string
     */
    protected $data_column = 'data';
    /**
     * Lifetime Column
     *
     * @var string
     */
    protected $lifetime_column = 'lifetime';
    /**
     * Modified Column
     *
     * @var string
     */
    protected $modified_column = 'modified';
    /**
     * Set Id Column
     *
     * @param string $idColumn
     * @return DbTableGatewayOptions
     * @throws Exception\InvalidArgumentException
     */
    public function set_id_column($id_column)
    {
        $id_column = (string) $id_column;
        if (strlen($id_column) === 0) {
            throw new Exception\InvalidArgumentException('$idColumn must be a non-empty string');
        }
        $this->id_column = $id_column;
        return $this;
    }
    /**
     * Get Id Column
     *
     * @return string
     */
    public function get_id_column()
    {
        return $this->id_column;
    }
    /**
     * Set Name Column
     *
     * @param string $nameColumn
     * @return DbTableGatewayOptions
     * @throws Exception\InvalidArgumentException
     */
    public function set_name_column($name_column)
    {
        $name_column = (string) $name_column;
        if (strlen($name_column) === 0) {
            throw new Exception\InvalidArgumentException('$nameColumn must be a non-empty string');
        }
        $this->name_column = $name_column;
        return $this;
    }
    /**
     * Get Name Column
     *
     * @return string
     */
    public function get_name_column()
    {
        return $this->name_column;
    }
    /**
     * Set Data Column
     *
     * @param string $dataColumn
     * @return DbTableGatewayOptions
     * @throws Exception\InvalidArgumentException
     */
    public function set_data_column($data_column)
    {
        $data_column = (string) $data_column;
        if (strlen($data_column) === 0) {
            throw new Exception\InvalidArgumentException('$dataColumn must be a non-empty string');
        }
        $this->data_column = $data_column;
        return $this;
    }
    /**
     * Get Data Column
     *
     * @return string
     */
    public function get_data_column()
    {
        return $this->data_column;
    }
    /**
     * Set Lifetime Column
     *
     * @param string $lifetimeColumn
     * @return DbTableGatewayOptions
     * @throws Exception\InvalidArgumentException
     */
    public function set_lifetime_column($lifetime_column)
    {
        $lifetime_column = (string) $lifetime_column;
        if (strlen($lifetime_column) === 0) {
            throw new Exception\InvalidArgumentException('$lifetimeColumn must be a non-empty string');
        }
        $this->lifetime_column = $lifetime_column;
        return $this;
    }
    /**
     * Get Lifetime Column
     *
     * @return string
     */
    public function get_lifetime_column()
    {
        return $this->lifetime_column;
    }
    /**
     * Set Modified Column
     *
     * @param string $modifiedColumn
     * @return DbTableGatewayOptions
     * @throws Exception\InvalidArgumentException
     */
    public function set_modified_column($modified_column)
    {
        $modified_column = (string) $modified_column;
        if (strlen($modified_column) === 0) {
            throw new Exception\InvalidArgumentException('$modifiedColumn must be a non-empty string');
        }
        $this->modified_column = $modified_column;
        return $this;
    }
    /**
     * Get Modified Column
     *
     * @return string
     */
    public function get_modified_column()
    {
        return $this->modified_column;
    }
}