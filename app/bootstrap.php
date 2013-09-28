<?php

use AwR\Exception;
use Silex\Application;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$app = new Application();

$uploadsDir = WEBROOT . '/uploads';


if (is_readable(ROOT . '/app/config/config.php')) {
    require_once ROOT . '/app/config/config.php';
}

// plugins

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path'     => ROOT . '/src/Resources/views',
));

// controllers

$app->match('/', function (Request $request) use ($app, $uploadsDir) {
    $error = '';
    $files = array();
    
    try {
        // making sure uploads dir exists, is readable and writeable
        if (!(is_dir($uploadsDir) && is_writeable($uploadsDir) && is_readable($uploadsDir))) {
            if (file_exists($uploadsDir)) {
                if (is_dir($uploadsDir)) {
                    throw new Exception("Target directory exists and is a folder, but is not readable and writeable.");
                } else {
                    throw new Exception("Target directory exists and is not a folder.");
                }
            } elseif (!mkdir($uploadsDir, 0777, TRUE)) {
                throw new Exception("Can't create target directory.");
            }
        }

        // uploading and converting the file
        if ($request->isMethod('post')) {
            /** @var UploadedFile $uploadedFile */
            $uploadedFile = $request->files->get('file');
            if (!$uploadedFile instanceof UploadedFile) {
                throw new Exception("No file was submitted.");
            }
            if ($uploadError = $uploadedFile->getError()) {
                throw new Exception("Could not upload the file. The error code is: $uploadError.");
            }
            if ('pdf' !== $uploadedFile->guessExtension()) {
                throw new Exception('Should only be PDF file.');
            }
            
            $hash = md5(time() + mt_rand());
            $targetFileName = $hash . '-' . strtolower($uploadedFile->getClientOriginalName());
            $uploadedFile->move($uploadsDir, $targetFileName);
            // explicitly creating temporary folder: this will allow to check status just based on file existance
            if (!mkdir("$uploadsDir/$hash.tmp")) {
                throw new Exception("Can't create temporary folder.");
            }
            exec("nohup bash -c 'cd $uploadsDir; pdf2htmlEX --embed cfijo --dest-dir $hash.tmp \"$targetFileName\"; mv $hash.tmp $hash; [ \"$(ls -A $hash)\" ] || rmdir $hash' > /dev/null 2>&1 &");
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    } catch (FileException $e) {
        $error = $e->getMessage();
    }

    ob_start();
    passthru("ls $uploadsDir | grep -E '\.pdf$'");
    $list = array_filter(explode("\n", ob_get_clean()));
    ob_end_clean();

    foreach ($list as $file) {
        preg_match('/^([^-]*)-(.*\.pdf)$/', $file, $chunks);
        $hash = $chunks[1];
        $data = array();
        $data['filename'] = $chunks[2];
        $data['basename'] = preg_replace('/\.pdf$/', '', $chunks[2]);
        
        if (file_exists("$uploadsDir/$hash.tmp")) {
            $data['status'] = 'processing';
        } elseif (file_exists("$uploadsDir/$hash")) {
            $data['status'] = 'converted';
        } else {
            $data['status'] = 'broken';
        }
        
        $files[$hash] = $data;
    }

    return $app['twig']->render('index.twig', array(
        'error' => $error,
        'files' => $files,
    ));
});

return $app;
