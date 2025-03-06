<?php

/**
 * Simplify parsing of SELECT queries
 *
 * @param string $sql
 * @param mysqli $db
 * @return array|false
 */
function get_query_result($sql, $db, $params = [])
{

    $q = $db->execute_query($sql, $params);
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
            ON DUPLICATE KEY UPDATE `last_active` = COALESCE((SELECT MAX(`timestamp`) FROM `sensor_readings` WHERE `sensor_id` = ?), FROM_UNIXTIME(?))",
        [
            $data['id'],
            $data['face'],
            $data['timestamp'],
            $data['id'],
            $data['timestamp']
        ]
    );
    return $insertion;
}

/**
 * Calculate hourly average temp for each face of the building.
 * Calculate the current hour's average only, to prevent unnacessary calculations and boost performance on data reading.
 *
 * @return void
 */
function calc_faces_avg($hourDate)
{

    // Extract the start and end of the given hour
    $start = "{$hourDate}:00:00";
    $end = "{$hourDate}:59:59";

    global $db;
    $avgs = get_query_result("SELECT 
        `face`,
        DATE_FORMAT(`timestamp`, '%Y-%m-%d %H') AS hour,
        AVG(`temperature`) AS avg_temp
    FROM `sensor_readings` sr JOIN `sensors` s ON sr.`sensor_id` = s.`id`
    WHERE `timestamp` BETWEEN ? AND ? # Using BETWEEN instead of LIKE for performance
    GROUP BY `face`, DATE_FORMAT(`timestamp`, '%Y-%m-%d %H')
    ORDER BY `face`", $db, [$start, $end]);

    if ($avgs) {
        $faces = [];
        foreach ($avgs as $row) {
            // Sort associative array: ['north' => 25.02, ...]
            $avgTemp = number_format($row['avg_temp'], 2);
            $faces[$row['face']] = $avgTemp;

            // Insert/update average reports
            $db->execute_query("INSERT INTO `hourly_averages` (`face`, `hour`, `temperature`) VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE `temperature` = ?", [$row['face'], $start, $avgTemp, $avgTemp]);
        }

        return $faces;
    }

    return false;
}

/**
 * Detect malfunctioning sensors
 *
 * @return void
 */
function detect_malfunctions()
{
    global $db;
}

/**
 * Detect & delete inactive sensors, along with their sensor_readings (automatic cascade)
 *
 * @return boolean
 */
function detect_inactive_sensors()
{
    $currentTime = get_current_time();
    if (!$currentTime) return;

    global $db;
    return $db->execute_query("DELETE FROM `sensors` WHERE `last_active` < DATE_SUB(?, INTERVAL 24 HOUR)", [$currentTime]);
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
    $timeQ = get_query_result("SELECT MAX(`last_active`) as `current_time` FROM `sensors`", $db);
    if ($timeQ) return $timeQ[0]['current_time'];

    return false; // DB is probably empty
}
