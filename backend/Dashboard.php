<?php
header('Content-Type: application/json');

$response = [
    "success" => true,
    "data" => [
        "hours" => [
            ["user" => "Juan", "hours" => 40],
            ["user" => "Pedro", "hours" => 35],
            ["user" => "María", "hours" => 50],
            ["user" => "Ana", "hours" => 28]
        ]
    ]
];

echo json_encode($response);
?>
