<?php

namespace Emanueletoffolon\MysqlIseed;

use Exception;
use PDO;

class ISeed {

    protected $connection;
    protected $dsn;
    protected $username;
    protected $password;
    protected $options;


    protected $db_tables = [];
    protected $tables = [];

    protected $path_files;

    public function __construct($config=[]){

        if (!isset($config['host'])){
            throw new \RuntimeException("Missing parameter: host");
        }
        if (!isset($config['db'])){
            throw new \RuntimeException("Missing parameter: db");
        }
        if (!isset($config['username'])){
            throw new \RuntimeException("Missing parameter: username");
        }
        if (!isset($config['password'])){
            throw new \RuntimeException("Missing parameter: password");
        }

        $charset = isset($config['charset']) ? $config['charset'] : 'utf8';
        $options = isset($config['options']) ? $config['options'] : [];

        $this->dsn = "mysql:host=".$config['host'].";dbname=".$config['db'].";charset=$charset";
        $this->options = array_merge([
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ],$options);
        $this->username = $config['username'];
        $this->password = $config['password'];
        $this->connection = null;

        $this->path_files = $config['path_files'] ? $config['path_files'] : __DIR__."/seeders/";

        $this->init();
    }

    protected function init(){
        try {
            $this->connection = new PDO($this->dsn, $this->username, $this->password, $this->options);
            $this->dbTables();
        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage(), (int)$e->getCode());
        }
    }

    public function addTables($tables){
        if (is_array($tables)) {
            foreach ($tables as $table) {
                if (!in_array($table, $this->tables, true) && in_array($table, $this->db_tables, true)) {
                    $this->tables[] = $table;
                }
            }
        }else if ($tables === '*'){
            $this->tables = $this->db_tables;
        }
    }

    public function dbTables(){
        $results = null;
        $sql = 'SHOW TABLES';
        if($this->connection){
            $results = $this->connection->query($sql)->fetchAll(PDO::FETCH_COLUMN);
            foreach($results as $table){
                if (!in_array($table, $this->db_tables, true)){
                    $this->db_tables[] = $table;
                }
            }
        }
    }

    protected function getColumns($table){
        $q = $this->connection->prepare("DESCRIBE ".$table);
        $q->execute();
        return $q->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getTables(){
        return $this->tables;
    }

    public function generate(){

        if (!file_exists($this->path_files) && !mkdir($this->path_files, 0777, true) && !is_dir($this->path_files)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $this->path_files));
        }
        foreach($this->tables as $table){
            $columns = $this->getColumns($table);

            switch(true){
                case in_array('data_inserimento', $columns, true):
                    $order = "data_inserimento ASC";
                    break;
                case in_array('data_creazione', $columns, true):
                    $order = "data_creazione ASC";
                    break;
                case in_array('codice', $columns, true):
                    $order = "codice ASC";
                    break;
                case in_array('id_linguaggio', $columns, true):
                    $order = "id_linguaggio ASC";
                    break;
                default:
                    $order = $columns[0]." ASC";
                    break;
            }

            $data = $this->connection->query('SELECT * FROM '.$table." ORDER BY ".$order)->fetchAll(PDO::FETCH_ASSOC);

            file_put_contents($this->path_files.$table.".php",
                "<?php\n\r"
                .$this->exportArray($data)
            );
        }
    }

    protected function exportArray($data){

        $ret = "\$values = [];\n";

        foreach($data as $values){
            $ret .= "\$values[] = ".var_export($values, true).";\n";
        }
        $ret .= "\n\nreturn \$values;";
        return $ret;
    }

    /**
     * @throws Exception
     */
    public function load(){

        $files = array_diff(scandir($this->path_files), array('.', '..'));

        foreach($files as $file){
            $table = pathinfo($file,PATHINFO_FILENAME);
            $this->connection->query('TRUNCATE TABLE '.$table);

            $data = include $this->path_files.$file;
            if (count($data)){
                $placeholders = [];
                $keys = array_keys($data[0]);
                foreach($keys as $key){
                    $placeholders[] = ":".$key;
                }
                $query = "REPLACE INTO ".$table." (".implode(',',$keys).") VALUES (".implode(',',$placeholders).")";
                $stmt= $this->connection->prepare($query);
                try {
                    $this->connection->beginTransaction();
                    foreach ($data as $row){
                        $stmt->execute($row);
                    }
                    $this->connection->commit();
                }catch (Exception $e){
                    $this->connection->rollback();
                    throw $e;
                }
            }
        }

    }

}