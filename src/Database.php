<?php

class Database {

    private $_connection;
    private static $_instance;

    private function __construct($connection)
    {
        $this->_connection = $connection;
        $this->_connection->query("SET NAMES 'utf8'");
        $this->_connection->query("SET CHARACTER SET 'utf8'");
        $this->_connection->query("SET SESSION collation_connection = 'utf8_general_ci'");
    }

    public static function getInstance()
    {
        if(!self::$_instance) {
            trigger_error(json_encode(['errors' => true, 'description' => 'MySQL cant have connection object']), E_USER_ERROR);
        }

        return self::$_instance;
    }

    public static function createInstance($mysql)
    {
        self::$_instance = new self($mysql);
    }

    private function __clone() { }

    public function loadData($SQL, $params = array())
    {

        $query = $this->_connection->prepare($SQL);

        if($query === FALSE){
            trigger_error(json_encode(['errors' => true, 'description' => 'Prepare SQL error']), E_USER_ERROR);
        }

        if(count($params) > 0) {
            $query->execute($params);
        } else {
            $query->execute();
        }

        if($query === FALSE){
            trigger_error(json_encode(['errors' => true, 'description' => 'Execute SQL error']), E_USER_ERROR);
        }

        $answer['data'] = [];

        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $answer['data'][] = $row;
        }

        $answer['affected_rows'] = $query->rowCount();
        $answer['count'] = count($answer['data']);

        $query = null;

        return $answer;
    }

}