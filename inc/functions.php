<?php

/**
 * Simplify parsing of SELECT queries
 *
 * @param string $sql
 * @param mysqli $db
 * @return array|false
 */
function get_query_result($sql, $db)
{

    $q = $db->query($sql);
    if ($q) {
        $res = $q->fetch_all(MYSQLI_ASSOC);
        if (is_array($res)) return $res;
    }

    return false;
}

/**
 * Insert sensor data to the `sensor_readings` table.
 * Includes sensor existence validation.
 *
 * @param array $data
 * @return array $response
 */
function save_sensor_reading($data)
{
    global $db;
    if (!check_sensor_exists($data)) return ['success' => false, 'error' => 'Cannot create sensor: ' . $db->error];

    // Insert reading + avoid duplications & errors
    $insertion = $db->execute_query(
        "INSERT IGNORE INTO `sensor_readings` (`sensor_id`, `timestamp`, `temperature`) VALUES (?, FROM_UNIXTIME(?), ?)",
        [
            $data['id'],
            $data['timestamp'],
            $data['temperature']
        ]
    );

    if ($insertion) {
        return ['success' => true, 'msg' => 'Sensor data saved successfully.'];
    } else {
        return ['success' => false, 'error' => 'Cannot create sensor_reading row: ' . $db->error];
    }
}

/**
 * Insert new sensor to the `sensors` table, or update its `last_active` value otherwise.
 *
 * @param array $data
 * @return boolean
 */
function check_sensor_exists($data)
{
    global $db;
    $insertion = $db->execute_query(
        "INSERT INTO `sensors` (`id`, `face`, `last_active`) VALUES (?, ?, FROM_UNIXTIME(?)) 
            ON DUPLICATE KEY UPDATE `last_active` = (SELECT MAX(`timestamp`) FROM `sensor_readings` WHERE `sensor_id` = ?)",
        [
            $data['id'],
            $data['face'],
            $data['timestamp'],
            $data['id']
        ]
    );
    return $insertion;
}

/**
 * Calculate hourly average temp for each face of the building
 *
 * @return void
 */
function calc_faces_avg()
{
    global $db;
    $avgs = get_query_result("SELECT 
        min(DATE_FORMAT(`timestamp`, '%Y-%m-%d %H:00:00')) AS hour,
        `face`,
        TRUNCATE(AVG(`temperature`), 2) AS avg_temp,
        count(*) as count
    FROM `sensor_readings` sr JOIN `sensors` s ON sr.`sensor_id` = s.`id`
    GROUP BY `face`, DATE_FORMAT(`timestamp`, '%Y-%m-%d %H')
    ORDER BY `hour` ASC, `face`", $db);
}

/**
 * Calculate hourly average temp for a sensor
 *
 * @return void
 */
function calc_sensor_avg()
{
    global $db;
    $avgs = get_query_result("SELECT 
        min(DATE_FORMAT(`timestamp`, '%Y-%m-%d %H:00:00')) AS hour,
        `face`,
        TRUNCATE(AVG(`temperature`), 2) AS avg_temp,
        count(*) as count
    FROM `sensor_readings` sr JOIN `sensors` s ON sr.`sensor_id` = s.`id`
    GROUP BY `face`, DATE_FORMAT(`timestamp`, '%Y-%m-%d %H')
    ORDER BY `hour` ASC, `face`", $db);
}

function detect_inactive_sensors()
{
    $currentTime = get_current_time();
    if (!$currentTime) return;


}

/**
 * Get the pseudo-time for the current moment.
 * Current time is assumed to be the last sensor reading time.
 *
 * @return void
 */
function get_current_time()
{
    global $db;
    $timeQ = get_query_result("SELECT MAX(`last_active`) current_time FROM `sensors`", $db);
    if ($timeQ) return $timeQ[0]['current_time'];

    return false; // DB is probably empty
}
