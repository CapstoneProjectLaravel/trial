<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // API endpoint URL
    $apiUrl = "http://your-laravel-app/api/materials/add";

    // Data to be sent to the API
    $data = [
        'material_id' => $_POST['material_id'],
        // Add other fields here like img, name, category, size, type, color, and other_details
    ];

    // Initialize cURL session
    $ch = curl_init($apiUrl);

    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

    // Execute cURL session and close
    $response = curl_exec($ch);
    curl_close($ch);

    // Handle API response (you can customize this part)
    $responseData = json_decode($response, true);
    if ($responseData && isset($responseData['message'])) {
        echo "Material added successfully: " . $responseData['message'];
    } else {
        echo "Failed to add material. Please try again later.";
    }
}
?>
