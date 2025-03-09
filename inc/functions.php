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

    // Query debugging
    // $query = $sql;
    // foreach ($params as $param) {
    //     $query = preg_replace('/\?/', "'" . addslashes($param) . "'", $sql, 1);
    // }
    // error_log("[DEBUG] " . $query);
    // error_log("[DEBUG] Params: " . json_encode($params));

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
 * @return array|false
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
        AVG(`temperature`) AS avg_temp,
        COUNT(`temperature`) AS reading_count
    FROM `sensor_readings` sr JOIN `sensors` s ON sr.`sensor_id` = s.`id`
    WHERE `timestamp` BETWEEN ? AND ? # Using BETWEEN instead of LIKE for performance
    GROUP BY `face`, DATE_FORMAT(`timestamp`, '%Y-%m-%d %H')
    ORDER BY `face`", $db, [$start, $end]);

    if ($avgs) {
        $faces = [];
        foreach ($avgs as $row) {
            // Sort associative array: ['north' => 25.02, ...]
            $avgTemp = number_format($row['avg_temp'], 2);
            $faces[$row['face']] = ['temp' => $avgTemp, 'reading_count' => $row['reading_count']];

            // Insert/update average reports
            $db->execute_query("INSERT INTO `hourly_averages` (`face`, `hour`, `temperature`) VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE `temperature` = ?", [$row['face'], $start, $avgTemp, $avgTemp]);
        }

        return $faces;
    }

    return false;
}

/**
 * Validate sensor data for malfunctions after inserting to DB, using fresh hourly reports.
 * Document and delete malfunctioned entries to prevent data corruption.
 *
 * @return boolean
 */
function detect_malfunctions($hour, $avgs)
{
    global $db;

    // Extract the start and end of the given hour
    $start = "{$hour}:00:00";
    $end = "{$hour}:59:59";
    $detected = false;

    foreach ($avgs as $face => $avg) {

        // Minimize false-positives by skipping edge-case scenarios where malfunctions were made in the first readings of a given hour,
        // before having enough data for comparison, causing corruption in the average calculation.
        if ($avg['reading_count'] < 5) continue;

        $maxDeviationTemp = number_format($avg['temp'] + (($avg['temp'] / 100) * 20), 2);
        $minDeviationTemp = number_format($avg['temp'] - (($avg['temp'] / 100) * 20), 2);

        $malfunctions = get_query_result("SELECT * FROM `sensor_readings` sr JOIN `sensors` s ON sr.`sensor_id` = s.`id`
        WHERE s.`face` = ? 
        AND `timestamp` BETWEEN ? AND ?
        AND `temperature` NOT BETWEEN ? AND ?", $db, [$face, $start, $end, $minDeviationTemp, $maxDeviationTemp]);

        if (!$malfunctions) continue;
        $detected = true;

        foreach ($malfunctions as $mf) {

            // Calc deviation percentage
            $diffPc = abs(number_format((($mf['temperature'] - $avg['temp']) / abs($avg['temp'])) * 100, 2));
            error_log("[DEBUG] Face: {$face}, Avg: {$avg['temp']}, Reading temp: {$mf['temperature']} ({$diffPc}%), Sensor: {$mf['sensor_id']}");

            // Document & delete malfunctioned sensors
            $db->execute_query("INSERT INTO `malfunctions` (`sensor_id`, `face`, `report_time`, `deviation_pc`, `reported_temp`, `avg_face_temp`)
            VALUES (?, ?, ?, ?, ?, ?)", [$mf['sensor_id'], $face, $mf['timestamp'], $diffPc, $mf['temperature'], $avg['temp']]);
            $db->execute_query("DELETE FROM `sensors` WHERE `id` = {$mf['sensor_id']}");
        }
    }

    return $detected;
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
 * @return string|false
 */
function get_current_time()
{
    global $db;
    $timeQ = get_query_result("SELECT MAX(`last_active`) as `current_time` FROM `sensors`", $db);
    if ($timeQ) return $timeQ[0]['current_time'];

    return false; // DB is probably empty
}

/**
 * Get the past week's aggregated hourly data.
 *
 * @return array|false
 */
function get_hourly_reports($pastWeek = false)
{
    global $db;

    $sql = "SELECT * FROM `hourly_averages`";
    if ($pastWeek) {
        $currentTime = get_current_time();
        $sql .= " WHERE `hour` > DATE_SUB('{$currentTime}', INTERVAL 7 DAY)";
    }
    $sql .= " ORDER BY `hour` DESC";

    $reports = get_query_result($sql, $db);

    return $reports;
}

/**
 * Get all reported malfunctions.
 *
 * @return array|false
 */
function get_malfunctions()
{
    global $db;
    $reports = get_query_result("SELECT * FROM `malfunctions` ORDER BY `report_time` DESC", $db);

    return $reports;
}
