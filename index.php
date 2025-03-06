<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/inc/init.php'; // App initialization

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

$app = AppFactory::create();


// Routes Definition

/**
 * Dashboard page
 */
$app->get('/', function (Request $request, Response $response) {
    ob_start();
    include('./views/dashboard.php');
    $html = ob_get_clean();
    detect_inactive_sensors();

    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html');
});

/**
 * Receive sensor data. Array of objects is accepted as well as singular.
 */
$app->post('/sensor-reading', function (Request $request, Response $response) {

    $data = json_decode($request->getBody());
    $reportHours = []; // Accumulate hours from all incoming reports to calculate their averages, and allow retroactive data insertion.
    if (!is_array($data)) $data = [$data];

    foreach ($data as $row) {
        // Data validation
        if (
            !isset($row->id, $row->timestamp, $row->temperature, $row->face) ||
            !is_numeric($row->id) ||
            !is_numeric($row->temperature) ||
            !is_numeric($row->timestamp) ||
            !in_array($row->face, ['north', 'south', 'east', 'west'])
        ) {
            $error = ['error' => 'Invalid input data', 'data' => $row];
            $response->getBody()->write(json_encode($error));
            return $response->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }

        $save = save_sensor_reading((array)$row);

        if (!$save['success']) {
            $response->getBody()->write(json_encode($save));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $hourDate = date('Y-m-d H', $row->timestamp);
        $reportHours[] = $hourDate;
    }

    $save['saved_count'] = count($data);
    $reportHours = array_unique($reportHours);
    foreach ($reportHours as $reportHour) {
        calc_faces_avg($reportHour); // Calculate hourly avg's for newly inserted timestamps
    }
    detect_inactive_sensors(); // DELETE inactive sensors

    $response->getBody()->write(json_encode($save));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/reports/hourly', function (Request $request, Response $response) {

    calc_hourly_avg();
    ob_start();
    include('./views/hourly-report.php');
    $html = ob_get_clean();

    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html');
});

$app->run();
