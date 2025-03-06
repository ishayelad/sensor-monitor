<?php
class DB
{
    public $con;

    function __construct()
    {
        $this->con = $this->getConnection();
    }

    static function getConnection()
    {

        if (isset($GLOBALS['db'])) return $GLOBALS['db'];

        $con = mysqli_connect('mysql', 'root', 'root');
        if (!$con) {
            echo "DB Connection Error<br>";
            echo mysqli_connect_errno() . ": " . mysqli_connect_error();
            die();
        }

        $GLOBALS['db'] = $con;
        return $con;
    }

    /**
     * Create the database on first app usage
     *
     * @return void
     */
    static function initializeDb()
    {
        $db = DB::getConnection();

        $dbCreation = $db->query("CREATE DATABASE IF NOT EXISTS `sensor_data`");
        if (!$dbCreation) {
            die("Error creating database: " . $db->error);
        }
        $db->select_db('sensor_data');
    }

    /**
     * Create the tables on first app usage
     *
     * @return void
     */
    static function initializeTables()
    {
        $db = DB::getConnection();

        $sensors = $db->query("CREATE TABLE IF NOT EXISTS `sensors` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `face` ENUM('north', 'south', 'east', 'west') NOT NULL,
            `last_active` TIMESTAMP NOT NULL,
            `removed` TINYINT DEFAULT 0
        );");

        $sensorReadings = $db->query("CREATE TABLE IF NOT EXISTS `sensor_readings` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `sensor_id` INT NOT NULL,
            `temperature` DOUBLE NOT NULL,
            `malfunctioned` TINYINT DEFAULT 0,
            `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`sensor_id`) REFERENCES sensors(`id`) ON DELETE CASCADE,
            UNIQUE KEY `unique_sensor_timestamp` (`sensor_id`, `timestamp`)
        );");

        $malfunctions = $db->query("CREATE TABLE IF NOT EXISTS `malfunctions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `sensor_id` INT NOT NULL,
            `reading_id` INT NOT NULL,
            `report_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `deviation_pc` DOUBLE NOT NULL,
            FOREIGN KEY (`sensor_id`) REFERENCES sensors(`id`),
            FOREIGN KEY (`reading_id`) REFERENCES sensor_readings(`id`)
        );");

        if (!$sensors || !$sensorReadings || !$malfunctions) die("Error initializing tables: " . $db->error);
    }
}
