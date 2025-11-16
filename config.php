<?php
// config.php - change these values
return [
    'db'=>[
        'host'=>'localhost',
        'user'=>'root',
        'pass'=>'',      // set your DB password
        'name'=>'hotel_app',
        'port'=>3306
    ],
    // Simple admin credentials fallback (only used if admins table isn't used)
    'admin_user'=>'admin',
    'admin_pass'=>'admin123',
    // Upload folders (relative)
    'upload_dir'=>'public/uploads',
    // GST default
    'default_gst'=>18.00
];



