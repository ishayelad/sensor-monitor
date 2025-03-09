<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="/assets/css/style.min.css">

    <title>Dashboard - Sensor Monitor</title>
</head>

<body>
    <header>
        <a href="/">
            <h2 class="logo">Sensor Monitor</h2>
        </a>
        <div class="actions">
            <a href="/reports/hourly">Hourly Temp. Reports</a>
            <a href="/reports/malfunctions">Malfunctions Report</a>
        </div>
    </header>

    <h1 class="page-title">Dashboard</h1>

    <p>Use /sensor-reading GET endpoint to send your JSON data. Accepting arrays as well (recommended for performance).</p>
    <p><b>Insertion flow explanation:</b></p>
    <ul>
        <li>Simple data validation</li>
        <li>Create new `sensors` entries for first time data, and insert readings into `sensor_readings`.</li>
        <li>Calculate hourly averages at the end of an API request. Collect and calculate the sensor reading hours of the current insertion to avoid unnecessary calculations.</li>
        <li>Detect malfunctions based on the hourly averages, report the malfunctions (`malfunctions` table + report time), and DELETE ALL DATA of a malfunctioned sensor (as instructed via WhatsApp).</li>
        <li>Re-run hourly averages after deleting malfunctioned sensors to prevent data corruption.</li>
        <li>Detect inactive sensors (24-hour activity) and DELETE all their data (as instructed via WhatsApp).</li>
    </ul>
    <p>Being a simulation rather than a realtime app - the current time is assumed to be the last sensor reading time.</p>
    <p>Tables have primary key validations, foreign keys and indexes.</p>
    <p>Project is fairly well documented using comments.</p>
    <p>Performance could've been more optimized, but I figured this assignment was not meant to consume too much efforts, and should be limited to no longer than a workday.</p>
    <p>Flow covers as many edge-cases I could cover within given time, but not all of them.</p>

</body>

</html>