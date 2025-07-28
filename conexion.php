<?php

class DBConnectionLocal extends PDO
{
    private $host = 'localhost';
    private $dbname = 'lovi';
    private $user = 'root';
    private $pass = '';
    public function __construct()
    {   parent::__construct('mysql:host='.$this->host.';dbname='.$this->dbname,
        $this->user,
        $this->pass,array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
        $this->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }

    public function SQLQuery($query){
        $args = func_get_args();
        array_shift($args);
        $reponse = parent::prepare($query);
        $reponse->execute($args);
        return $reponse;

    }
}

?>