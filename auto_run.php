<?php
require_once __DIR__ . '/includes/auto_schedule_runner.php';

$result = grm_auto_schedule_runner(true, 30, true);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Auto Schedule Runner</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 40px;
            background: #f5f7fb;
        }

        .box {
            background: #fff;
            padding: 24px;
            border-radius: 12px;
            max-width: 700px;
            margin: auto;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        }

        pre {
            background: #111827;
            color: #e5e7eb;
            padding: 16px;
            border-radius: 8px;
            overflow-x: auto;
        }

        a {
            display: inline-block;
            margin-top: 16px;
            text-decoration: none;
            background: #0d6efd;
            color: #fff;
            padding: 10px 16px;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="box">
        <h2>Auto Schedule Runner Result</h2>

        <pre><?php echo htmlspecialchars(print_r($result, true), ENT_QUOTES, 'UTF-8'); ?></pre>

        <a href="index.php">Back to Home</a>
    </div>
</body>
</html>