<?PHP
    class Database
    {
        // Singleton object. Leave $me alone.
        private static $me;

        public $db;
        public $host;
        public $name;
        public $username;
        public $password;
        public $dieOnError;
        public $queries;
        public $result;

        public $redirect = false;

        // Singleton constructor
        private function __construct($connect = false)
        {
            $Config = Config::getConfig();

            $this->host       = $Config->dbHost;
            $this->name       = $Config->dbName;
            $this->username   = $Config->dbUsername;
            $this->password   = $Config->dbPassword;
            $this->dieOnError = $Config->dbDieOnError;

            $this->db = false;
            $this->queries = array();

            if($connect === true)
                $this->connect();
        }

        // Waiting (not so) patiently for 5.3.0...
        public static function __callStatic($name, $args)
        {
            return self::$me->__call($name, $args);
        }

        // Get Singleton object
        public static function getDatabase($connect = true)
        {
            if(is_null(self::$me))
                self::$me = new Database($connect);
            return self::$me;
        }

        // Do we have a valid database connection?
        public function isConnected()
        {
            return is_resource($this->db) && get_resource_type($this->db) == 'mysql link';
        }

        // Do we have a valid database connection and have we selected a database?
        public function databaseSelected()
        {
            if(!$this->isConnected()) return false;
            $result = mysql_list_tables($this->name, $this->db);
            return is_resource($result);
        }

        public function connect()
        {
            $this->db = mysql_connect($this->host, $this->username, $this->password) or $this->notify();
            if($this->db === false) return false;
            mysql_select_db($this->name, $this->db) or $this->notify();
            return $this->isConnected();
        }

        public function query($sql, $args_to_prepare = null, $exception_on_missing_args = true)
        {
            if(!$this->isConnected()) $this->connect();

            // Allow for prepared arguments. Example:
            // query("SELECT * FROM table WHERE id = :id", array('id' => $some_val));
            if(is_array($args_to_prepare))
            {
                foreach($args_to_prepare as $name => $val)
                {
                    if (!is_int($val))
                        $val = $this->quote($val);
                    $sql = str_replace(":$name", $val, $sql, $count);
                    if($exception_on_missing_args && (0 == $count))
                        throw new Exception(":$name was not found in prepared SQL query.");
                }
            }

            $this->queries[] = $sql;
            $this->result = mysql_query($sql, $this->db) or $this->notify();
            return $this->result;
        }

        // Returns the number of rows.
        // You can pass in nothing, a string, or a db result
        public function numRows($arg = null)
        {
            $result = $this->resulter($arg);
            return ($result !== false) ? mysql_num_rows($result) : false;
        }

        // Returns true / false if the result has one or more rows
        public function hasRows($arg = null)
        {
            $result = $this->resulter($arg);
            return is_resource($result) && (mysql_num_rows($result) > 0);
        }

        // Returns the number of rows affected by the previous operation
        public function affectedRows()
        {
            if(!$this->isConnected()) return false;
            return mysql_affected_rows($this->db);
        }

        // Returns the auto increment ID generated by the previous insert statement
        public function insertId()
        {
            if(!$this->isConnected()) return false;
            $id = mysql_insert_id($this->db);
            if($id === 0 || $id === false)
                return false;
            else
                return $id;
        }

        // Returns a single value.
        // You can pass in nothing, a string, or a db result
        public function getValue($arg = null)
        {
            $result = $this->resulter($arg);
            return $this->hasRows($result) ? mysql_result($result, 0, 0) : false;
        }

        // Returns an array of the first value in each row.
        // You can pass in nothing, a string, or a db result
        public function getValues($arg = null)
        {
            $result = $this->resulter($arg);
            if(!$this->hasRows($result)) return array();

            $values = array();
            mysql_data_seek($result, 0);
            while($row = mysql_fetch_array($result, MYSQL_ASSOC))
                $values[] = array_pop($row);
            return $values;
        }

        // Returns the first row.
        // You can pass in nothing, a string, or a db result
        public function getRow($arg = null)
        {
            $result = $this->resulter($arg);
            return $this->hasRows() ? mysql_fetch_array($result, MYSQL_ASSOC) : false;
        }

        // Returns an array of all the rows.
        // You can pass in nothing, a string, or a db result
        public function getRows($arg = null)
        {
            $result = $this->resulter($arg);
            if(!$this->hasRows($result)) return array();

            $rows = array();
            mysql_data_seek($result, 0);
            while($row = mysql_fetch_array($result, MYSQL_ASSOC))
                $rows[] = $row;
            return $rows;
        }

        // Escapes a value and wraps it in single quotes.
        public function quote($var)
        {
            if(!$this->isConnected()) $this->connect();
            return "'" . $this->escape($var) . "'";
        }

        // Escapes a value.
        public function escape($var)
        {
            if(!$this->isConnected()) $this->connect();
            return mysql_real_escape_string($var, $this->db);
        }

        public function numQueries()
        {
            return count($this->queries);
        }

        public function lastQuery()
        {
            if($this->numQueries() > 0)
                return $this->queries[$this->numQueries() - 1];
            else
                return false;
        }

        private function notify()
        {
            $err_msg = mysql_error($this->db);
            error_log($err_msg);

            if($this->dieOnError === true)
            {
                echo "<p style='border:5px solid red;background-color:#fff;padding:5px;'><strong>Database Error:</strong><br/>$err_msg</p>";
                echo "<p style='border:5px solid red;background-color:#fff;padding:5px;'><strong>Last Query:</strong><br/>" . $this->lastQuery() . "</p>";
                echo "<pre>";
                debug_print_backtrace();
                echo "</pre>";
                exit;
            }

            if(is_string($this->redirect))
            {
                header("Location: {$this->redirect}");
                exit;
            }
        }

        // Takes nothing, a MySQL result, or a query string and returns
        // the correspsonding MySQL result resource or false if none available.
        private function resulter($arg = null)
        {
            if(is_null($arg) && is_resource($this->result))
                return $this->result;
            elseif(is_resource($arg))
                return $arg;
            elseif(is_string($arg))
            {
                $this->query($arg);
                if(is_resource($this->result))
                    return $this->result;
                else
                    return false;
            }
            else
                return false;
        }
    }