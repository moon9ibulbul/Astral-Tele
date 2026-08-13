<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

use Aws\S3\S3Client;

function getS3Client() {
    global $config;
    
    return new S3Client([
        'version' => 'latest',
        'region'  => $config['s3']['region'],
        'endpoint' => $config['s3']['endpoint'],
        'use_path_style_endpoint' => true,
        'credentials' => [
            'key'    => $config['s3']['key'],
            'secret' => $config['s3']['secret'],
        ],
    ]);
}

function uploadToS3($file, $destinationKey) {
    global $config;
    $s3 = getS3Client();
    
    try {
        $result = $s3->putObject([
            'Bucket' => $config['s3']['bucket'],
            'Key'    => $destinationKey,
            'SourceFile' => $file,
            'ACL'    => 'public-read', // Or handle appropriately
        ]);
        
        return $result['ObjectURL'];
    } catch (Aws\Exception\AwsException $e) {
        // Output error message if fails
        error_log($e->getMessage());
        return false;
    }
}