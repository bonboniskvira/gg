<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_name'])) {

    $name = trim($_POST['contact_name']);

    $phone = trim($_POST['contact_phone'] ?? '');

    $email = trim($_POST['contact_email'] ?? '');

    $position = trim($_POST['contact_position'] ?? '');

    $category = trim($_POST['contact_category'] ?? 'other');

    $colorOverride = trim($_POST['contact_color_override'] ?? '');

    $notes = trim($_POST['contact_notes'] ?? '');



    // Basic validation

    if (empty($name)) {

        echo "Jméno je povinné.";

        exit;

    }



    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {

        echo "Neplatný e-mail.";

        exit;

    }



    // Valid categories

    $validCategories = ['management', 'central', 'sales', 'support', 'hr', 'suppliers', 'other'];

    if (!in_array($category, $validCategories)) {

        $category = 'other'; // Default if invalid

    }



    // Valid override colors (empty string means use automatic color)

    $validColors = ['', '#e74c3c', '#3498db', '#f39c12', '#9b59b6', '#2ecc71', '#34495e', '#16a085', '#e67e22'];

    if (!in_array($colorOverride, $validColors)) {

        $colorOverride = ''; // Default to automatic if invalid

    }



    // Create directory structure if it doesn't exist

    $contactsDir = __DIR__ . '/contacts/';

    $categoryDir = $contactsDir . $category . '/';



    if (!is_dir($contactsDir)) {

        mkdir($contactsDir, 0777, true);

    }



    if (!is_dir($categoryDir)) {

        mkdir($categoryDir, 0777, true);

    }



    // Create contact data

    $contactData = [

        'name' => $name,

        'phone' => $phone,

        'email' => $email,

        'position' => $position,

        'color_override' => $colorOverride,

        'notes' => $notes,

        'date_added' => time(),

        'category' => $category

    ];



    // Generate filename from sanitized name and current timestamp

    $safeName = preg_replace('/[^a-z0-9]+/', '-', strtolower($name));

    $filename = $categoryDir . time() . '-' . $safeName . '.json';



    // Save contact data

    file_put_contents($filename, json_encode($contactData, JSON_PRETTY_PRINT));



    // Redirect back to contacts page

    header('Location: index.php#contacts');

    exit;

}



// If we get here, something went wrong

echo "Chyba při ukládání kontaktu.";

