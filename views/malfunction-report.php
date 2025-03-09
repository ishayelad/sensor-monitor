<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="/assets/css/dataTables.min.css">
    <link rel="stylesheet" href="/assets/css/style.min.css">
    <script src="/assets/js/jquery.min.js"></script>
    <script src="/assets/js/dataTables.min.js"></script>

    <title>Malfunctions Report - Sensor Monitor</title>
</head>

<body>
    <header>
        <a href="/">
            <h2 class="logo">Sensor Monitor</h2>
        </a>
        <div class="actions">
            <a href="/reports/hourly">Hourly Temp. Reports</a>
            <a href="/reports/malfunctions" class="current-page">Malfunctions Report</a>
        </div>
    </header>

    <h1 class="page-title">Malfunctions Report</h1>

    <?php
    $reports = get_malfunctions();
    if ($reports) {
    ?>
        <table class="report-table malfunctions">
            <thead>
                <tr>
                    <th>Sensor #ID</th>
                    <th>Report Time</th>
                    <th>Face</th>
                    <th>Reported Temp.</th>
                    <th>Avgerage Face Temp.</th>
                    <th>Deviation Percentage</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reports as $row) { ?>
                    <tr>
                        <td><?php echo $row['sensor_id']; ?></td>
                        <td><?php echo $row['report_time']; ?></td>
                        <td><?php echo $row['face']; ?></td>
                        <td><?php echo $row['reported_temp']; ?>°</td>
                        <td><?php echo $row['avg_face_temp']; ?>°</td>
                        <td><?php echo $row['deviation_pc']; ?>%</td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>

    <?php } else { ?>
        <p>No reports to show at the moment.</p>
    <?php } ?>

    <script src="/assets/js/main.min.js"></script>
</body>

</html>