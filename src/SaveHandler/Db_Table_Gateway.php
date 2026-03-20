<?php

declare (strict_types=1);
namespace Laminas\Session\Save_Handler;

use function ini_get;
use Laminas\Db\Table_Gateway\Table_Gateway;
use Return_Type_Will_Change;
use function sprintf;
use function time;
/**
 * DB Table Gateway session save handler
 *
 * @deprecated This class will be removed without replacement in version 3.0.
 *
 * @see ReturnTypeWillChange
 */
class Db_Table_Gateway implements Save_Handler_Interface
{
    /**
     * Session Save Path
     *
     * @var string
     */
    protected $session_save_path;
    /**
     * Session Name
     *
     * @var string
     */
    protected $session_name;
    /**
     * Lifetime
     *
     * @var int
     */
    protected $lifetime;
    /**
     * Constructor
     */
    public function __construct(
        /**
         * Laminas Db Table Gateway
         */
        protected Table_Gateway $table_gateway,
        /**
         * DbTableGateway Options
         */
        protected Db_Table_Gateway_Options $options
    )
    {
    }
    /**
     * Open Session
     *
     * @param  string $path
     * @param  string $name
     * @return bool
     */
    #[Return_Type_Will_Change]
    public function open($path, $name)
    {
        $this->session_save_path = $path;
        $this->session_name = $name;
        $this->lifetime = ini_get('session.gc_maxlifetime');
        return true;
    }
    /**
     * Close session
     *
     * @return bool
     */
    #[Return_Type_Will_Change]
    public function close()
    {
        return true;
    }
    /**
     * Read session data
     *
     * @param string $id
     * @param bool $destroyExpired Optional; true by default
     * @return string
     */
    #[Return_Type_Will_Change]
    public function read($id, $destroy_expired = true)
    {
        $row = $this->table_gateway->select([$this->options->get_id_column() => $id, $this->options->get_name_column() => $this->session_name])->current();
        if ($row) {
            if ($row->{$this->options->get_modified_column()} + $row->{$this->options->get_lifetime_column()} > time()) {
                return (string) $row->{$this->options->get_data_column()};
            }
            if ($destroy_expired) {
                $this->destroy($id);
            }
        }
        return '';
    }
    /**
     * Write session data
     *
     * @param string $id
     * @param string $data
     * @return bool
     */
    #[Return_Type_Will_Change]
    public function write($id, $data)
    {
        $data = [$this->options->get_modified_column() => time(), $this->options->get_data_column() => (string) $data];
        $rows = $this->table_gateway->select([$this->options->get_id_column() => $id, $this->options->get_name_column() => $this->session_name])->current();
        if ($rows) {
            return (bool) $this->table_gateway->update($data, [$this->options->get_id_column() => $id, $this->options->get_name_column() => $this->session_name]);
        }
        $data[$this->options->get_lifetime_column()] = $this->lifetime;
        $data[$this->options->get_id_column()] = $id;
        $data[$this->options->get_name_column()] = $this->session_name;
        return (bool) $this->table_gateway->insert($data);
    }
    /**
     * Destroy session
     *
     * @param  string $id
     * @return bool
     */
    #[Return_Type_Will_Change]
    public function destroy($id)
    {
        $this->table_gateway->delete([$this->options->get_id_column() => $id, $this->options->get_name_column() => $this->session_name]);
        return true;
    }
    /**
     * Garbage Collection
     *
     * @param int $maxlifetime
     * @return true
     */
    #[Return_Type_Will_Change]
    public function gc($maxlifetime)
    {
        $platform = $this->table_gateway->get_adapter()->get_platform();
        return (bool) $this->table_gateway->delete(sprintf('%s < %d', $platform->quote_identifier($this->options->get_modified_column()), time() - $this->lifetime));
    }
}