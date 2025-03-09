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
            INDEX `idx_sensors_last_active` (`last_active`)
        )");

        $sensorReadings = $db->query("CREATE TABLE IF NOT EXISTS `sensor_readings` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `sensor_id` INT NOT NULL,
            `temperature` DOUBLE NOT NULL,
            `malfunctioned` TINYINT DEFAULT 0,
            `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`sensor_id`) REFERENCES sensors(`id`) ON DELETE CASCADE,
            UNIQUE KEY `unique_sensor_timestamp` (`sensor_id`, `timestamp`),
            INDEX `idx_sensor_readings_time_temp` (`timestamp`, `temperature`)
        )");

        $malfunctions = $db->query("CREATE TABLE IF NOT EXISTS `malfunctions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `sensor_id` INT NOT NULL,
            `face` ENUM('north', 'south', 'east', 'west') NOT NULL,
            `report_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `reported_temp` DOUBLE NOT NULL,
            `avg_face_temp` DOUBLE NOT NULL,
            `deviation_pc` DOUBLE NOT NULL
        )");

        $hourlyAvgs = $db->query("CREATE TABLE IF NOT EXISTS `hourly_averages` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `face` ENUM('north', 'south', 'east', 'west') NOT NULL,
            `hour` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `temperature` DOUBLE NOT NULL,
            UNIQUE KEY `unique_face_hour` (`face`, `hour`),
            INDEX `idx_hourly_averages_face_hour_temp` (`face`, `hour`, `temperature`)
        )");

        if (!$sensors || !$sensorReadings || !$malfunctions || !$hourlyAvgs) die("Error initializing tables: " . $db->error);
    }
}
