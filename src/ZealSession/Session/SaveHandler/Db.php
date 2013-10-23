<?php

namespace ZealSession\Session\SaveHandler;

use Zend\Session\SaveHandler\SaveHandlerInterface;
use Zend\Db\Sql\Expression as DbExpression;
use Zend\Db\Sql\Sql;

class Db implements SaveHandlerInterface
{
    /**
     * Write session if it has data
     */
    const WRITE_IF_DATA = 1;

    /**
     * Write session if the data has changed from the original data
     */
    const WRITE_IF_CHANGED = 2;

    /**
     * Always write session data
     */
    const WRITE_ALWAYS = 3;

    /**
     * Never write session data
     */
    const WRITE_NEVER = 4;

    /**
     * Zend DB adapter
     *
     * @var Zend\Db\Adapter\AdapterInterface
     */
    protected $db;

    /**
     * The write mode that's in use (defaults to WRITE_IF_DATA)
     *
     * @var integer
     */
    static protected $writeMode = 1;

    /**
     * The initially loaded session data (used for comparision to new data)
     * @var array
     */
    protected $existingSession;


    /**
     * Setter for the DB adapter
     *
     * @param Zend\Db\Adapter\AdapterInterface $db
     */
    public function setDb($db)
    {
        $this->db = $db;

        return $this;
    }

    /**
     * Getter for the DB adapter
     *
     * @return Zend\Db\Adapter\AdapterInterface
     */
    public function getDb()
    {
        return $this->db;
    }

    /**
     * Open Session - retrieve resources (not required for DB adapter)
     *
     * @param string $save_path
     * @param string $name
     */
    public function open($save_path, $name)
    {
        return true;
    }

    /**
     * Close session
     *
     * @return boolean
     */
    public function close()
    {
        return true;
    }

    /**
     * Read session data
     *
     * @param string $id
     * @return string
     */
    public function read($id)
    {
        if ($id) {
            $result = $this->getDb()->query('SELECT * FROM sessions WHERE sessionID = ?', array($id));
            if ($result) {
                $this->existingSession = $result->current();
            } else {
                $this->existingSession = false;
            }

            if ($this->existingSession) {
                if (strtotime($this->existingSession['expires']) < time()) {
                    // session has expired, remove the row
                    $this->destroy($id);
                    $this->existingSession = null;

                } else {
                    return $this->existingSession['data'];
                }
            }
        }

        return '';
    }

    /**
     * Write session data
     *
     * @param string $id
     * @param string $data
     * @return boolean
     */
    public function write($id, $data)
    {
        $saveSession = false;
        switch (self::$writeMode) {
            case self::WRITE_ALWAYS:
                $saveSession = true;
                break;

            case self::WRITE_IF_DATA:
                $saveSession = ($data || !empty($this->existingSession['data']));
                break;

            case self::WRITE_IF_CHANGED:
                $saveSession = ($data != $this->existingSession['data']);
                break;

            case self::WRITE_NEVER:
                // do nothing
                break;
        }

        // don't save the session data if _REQUEST_ACCESS_TIME (which ZF adds by
        // defaut) is the only thing in it. Hopefully this won't be required in
        // ZF 2.3. See also ZF issue #4332
        if (!empty($data)) {
            $dataParts = explode('|', $data);
            if (count($dataParts) == 2) {
                $realData = unserialize($dataParts[1]);
                if (is_array($realData) && count($realData) == 1 && isset($realData['_REQUEST_ACCESS_TIME'])) {
                    if (self::$writeMode == self::WRITE_IF_DATA) {
                        $saveSession = false;
                    }
                }
            }
        }

        if ($saveSession) {
            $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null; // FIXME

            $lifetime = max(3600, ini_get('session.cookie_lifetime'));
            if (!empty($this->existingSession)) {
                $existingLifetime = strtotime($this->existingSession['expires']) - strtotime($this->existingSession['lastModified']);
                if ($lifetime < $existingLifetime) {
                    $lifetime = $existingLifetime;
                }
            }

            $expires = date('Y-m-d H:i:s', time() + $lifetime);

            $sessionData = array(
                'lastModified' => date('Y-m-d H:i:s'),
                'expires' => $expires,
                'ip' => new DbExpression('INET_ATON(?)', array($ip))
            );

            if (!empty($this->existingSession)) {
                if ($data != $this->existingSession['data']) {
                    $sessionData['data'] = $data;
                }

                if ($id == $this->existingSession['sessionID']) {
                    $sql = new Sql($this->getDb());

                    $update = $sql->update('sessions');
                    $update->set($sessionData)
                           ->where(array('sessionID' => $this->existingSession['sessionID']));

                    $statement = $sql->prepareStatementForSqlObject($update);
                    $statement->execute();

                } else {
                    // create a session with the new sessionID
                    $sessionData['sessionID'] = $id;

                    $sql = new Sql($this->getDb());

                    $insert = $sql->insert('sessions');
                    $insert->values($sessionData);

                    $statement = $sql->prepareStatementForSqlObject($insert);
                    $statement->execute();

                    // drop the expiry date on the old one - not sure why this isn't required?
                }

            } else {
                $sessionData['sessionID'] = $id;
                $sessionData['data'] = $data;

                $sql = new Sql($this->getDb());

                $insert = $sql->insert('sessions');
                $insert->values($sessionData);

                $statement = $sql->prepareStatementForSqlObject($insert);
                $statement->execute();
            }
        }

        return true;
    }

    /**
     * Destroy Session - remove data from resource for
     * given session id
     *
     * @param string $id
     */
    public function destroy($id)
    {
        $sql = new Sql($this->getDb());

        $delete = $sql->delete('sessions');
        $delete->where(array('sessionID' => $id));

        $statement = $sql->prepareStatementForSqlObject($delete);
        $statement->execute();
    }

    /**
     * Garbage Collection - remove sessions older than $maxlifetime (seconds)
     *
     * This function does nothing because GC is handled by cron
     *
     * @param int $maxlifetime
     */
    public function gc($maxlifetime)
    {

    }

    /**
     * Set the write mode (should be one of the defined class constants)
     *
     * @param int $writeMode
     */
    public static function setWriteMode($writeMode)
    {
        self::$writeMode = $writeMode;
    }

    /**
     * Getter for the write mode
     *
     * @return int
     */
    public static function getWriteMode()
    {
        return self::$writeMode;
    }
}
