<?php
$api_token = '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';
$repo_owner = 'andrias97';
$repo_name = 'databasepaket';
$file_path = 'data.json';
$branch = 'main'; // Assuming 'main' is your branch

$github_url = "https://api.github.com/repos/$repo_owner/$repo_name/contents/$file_path?ref=$branch";

$options = [
    "http" => [
        "header" => "Authorization: token $api_token\r\nUser-Agent: PHP"
    ]
];
$context = stream_context_create($options);
$response = file_get_contents($github_url, false, $context);

if ($response === false) {
    die('Error fetching data from GitHub.');
}

$response_data = json_decode($response, true);

if (isset($response_data['content'])) {
    $file_content = base64_decode($response_data['content']);
    $data_array = json_decode($file_content, true);

    if ($data_array === null) {
        die('Error decoding JSON data.');
    }

    // Menampilkan hanya field tertentu
    $filtered_data = [];
    foreach ($data_array as $key => $item) {
        $filtered_data[$key] = [
            "nomor"         => $item["nomor"],
            "tanggal"       => $item["tanggal"],
            "jenis_paket"   => $item["jenis_paket"],
    "nomor_hp"     => substr($item["nomor_hp"], 0, -3) . "***", // Masking 3 digit terakhir
        ];
    }

    header('Content-Type: application/json');
    echo json_encode($filtered_data, JSON_PRETTY_PRINT);
} else {
    die('Error: Content not found.');
}
?>