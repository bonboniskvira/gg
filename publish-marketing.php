<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['folder_name']) && isset($_FILES['marketing_files'])) {

    $folder = trim($_POST['folder_name']);

    // Sanitize folder name - allow Czech characters
    $folderSafe = preg_replace('/[^a-zA-Z0-9áčďéěíňóřšťúůýžÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ\-_\s]/', '_', $folder);

    $folderSafe = substr($folderSafe, 0, 50);



    $targetDir = __DIR__ . '/marketing/' . $folderSafe . '/';

    if (!is_dir($targetDir)) {

        mkdir($targetDir, 0777, true);

    }



    foreach ($_FILES['marketing_files']['tmp_name'] as $idx => $tmpName) {

        if ($_FILES['marketing_files']['error'][$idx] === UPLOAD_ERR_OK) {

            $originalName = basename($_FILES['marketing_files']['name'][$idx]);

            $safeName = preg_replace('/[^a-zA-Z0-9áčďéěíňóřšťúůýžÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ\-_.\s]/', '_', $originalName);

            move_uploaded_file($tmpName, $targetDir . $safeName);

        }

    }



    header('Location: index.php#marketing');

    exit;

}

