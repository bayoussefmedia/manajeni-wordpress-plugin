<?php
// Script to manually update the Manajeni URL
require_once('c:/laragon/www/wordpress/wp-load.php');

if (class_exists('Manajeni_XML_Handler')) {
    $xml_handler = new Manajeni_XML_Handler();
    $new_url = 'http://localhost/wordpress';
    
    // Update XML
    $xml_handler->declare_url($new_url);
    
    // Update WordPress Option
    update_option('manajeni_url', $new_url);
    
    echo "URL successfully updated to: " . $new_url;
} else {
    echo "Error: Manajeni_XML_Handler class not found. Make sure the plugin is active.";
}
