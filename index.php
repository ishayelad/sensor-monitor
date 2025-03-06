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

    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html');
});

/**
 * Receive sensor data. Array of objects is accepted as well as singular.
 */
$app->post('/sensor-reading', function (Request $request, Response $response) {

    $data = json_decode($request->getBody());
    if (is_array($data)) {

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
        }
        
        $save['saved_count'] = count($data);
        $response->getBody()->write(json_encode($save));
        return $response->withHeader('Content-Type', 'application/json');
    } else { // Singular

        // Data validation
        if (
            !isset($data->id, $data->timestamp, $data->temperature, $data->face) ||
            !is_numeric($data->id) ||
            !is_numeric($data->temperature) ||
            !is_numeric($data->timestamp) ||
            !in_array($data->face, ['north', 'south', 'east', 'west'])
        ) {
            $error = ['error' => 'Invalid input data', 'data' => $data];
            $response->getBody()->write(json_encode($error));
            return $response->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }

        $save = save_sensor_reading((array)$data);

        $response->getBody()->write(json_encode($save));
        if ($save['success']) {
            return $response->withHeader('Content-Type', 'application/json');
        } else {
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    }
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
