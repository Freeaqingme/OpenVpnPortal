<?php
/**
 * Simple database class. Handles the transactions between the website
 * and the sqlite database, for bruteforcing and logging.
 */
class DB 
{

	private $handle=null;
	
    /**
     * Construction function opens the sqlite database and declares the handle
     * to this object.
     */
	function __construct() 
    {
        try {
            $this->handle = new PDO('sqlite:'.APP_PATH.'/db/sqlite.db');
            if (!$this->handle)  {
                throw new Exception('PDO SQLITE handle could not be created.');
            }
            $this->handle->setAttribute(PDO::ATTR_ERRMODE, 
                                        PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            // Print PDOException message
            echo $e->getMessage();
        }                             
	}
	
    /**
     * Installs the database with the given queries in this function. Should only
     * be called once, if the database is gone!
     */
	function install() 
    {
        try {
            $createQuery = 	"CREATE TABLE logins (Id integer PRIMARY KEY," . 
                    "ipaddress varchar(39) NOT NULL, logged_time datetime, username varchar(100))";
            $this->handle->exec($createQuery);
                    
            $createQuery = "CREATE INDEX logged_time_index ON logins (logged_time)";
            $this->handle->exec($createQuery);
        } catch(PDOException $e) {
            // Print PDOException message
            echo $e->getMessage();
        }
        
	}
	
    /**
     * Store a login action in the database, combined with the IP and datetime.
     *
     * @param string $username   <The username that tried to log in>
     */
	function putLogin($username) 
    {
        try {
            $insert = "INSERT INTO logins (ipaddress,logged_time,username)
                    VALUES (:ip, DATETIME('now'), :username)";
            $stmt = $this->handle->prepare($insert);
         
            // Bind parameters to statement variables
            $stmt->bindParam(':ip', $_SERVER["REMOTE_ADDR"]);
            $stmt->bindParam(':username', sqlite_escape_string($username));
            
            $stmt->execute();
            
        } catch(PDOException $e) {
            // Print PDOException message
            echo $e->getMessage();
        }
	}
	
    /**
     * Get the number of logins from this ip, in the last $minutes minutes.
     *
     * @param int $minutes <Number of minutes>
     * @return int         <Number of login attempts in last $minutes minutes>
     */
	function getLoginsSince($minutes) 
    {
        try {
            $result = $this->handle->query("SELECT COUNT(*) as times
                        FROM logins 
                        WHERE logged_time >= DATETIME('now', '-" . ($minutes*60) . " seconds') AND ipaddress='" . $_SERVER["REMOTE_ADDR"] . "'")->fetch();
            
            return $result[0];
        } catch(PDOException $e) {
            // Print PDOException message
            echo $e->getMessage();
        }
    }
}