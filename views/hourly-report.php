<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="/assets/css/dataTables.min.css">
    <link rel="stylesheet" href="/assets/css/style.min.css">
    <script src="/assets/js/jquery.min.js"></script>
    <script src="/assets/js/dataTables.min.js"></script>

    <title>Hourly Report - Sensor Monitor</title>
</head>

<body>
    <header>
        <a href="/">
            <h2 class="logo">Sensor Monitor</h2>
        </a>
        <div class="actions">
            <a href="/reports/hourly" class="current-page">Hourly Temp. Reports</a>
            <a href="/reports/malfunctions">Malfunctions Report</a>
        </div>
    </header>

    <h1 class="page-title">Hourly Temperature Report</h1>

    <?php
    $reports = get_hourly_reports(true);
    if ($reports) {
    ?>
        <table class="report-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Face</th>
                    <th>Temperature</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reports as $row) { ?>
                    <tr>
                        <td><?php echo $row['hour']; ?></td>
                        <td><?php echo $row['face']; ?></td>
                        <td><?php echo $row['temperature']; ?>°</td>
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