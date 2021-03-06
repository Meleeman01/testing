<?php

require 'vendor/autoload.php';

use Aws\S3\S3Client;

// Instantiate an S3 client
$s3 = S3Client::factory(array
(
    'profile' => 'default'
));

// Define available commands
$commands = array('help', 'buckets', 'objects', 'upload', 'copy', 'delete', 'move', 'quit');

// Basic information about each command
$help = array
(
    'help' => array
    (
        "This is a basic demonstration of Amazon's S3 API using readline in PHP.",
        "For more information on a command, you can say help [command].",
        "",
        " - Available commands are: %s"
    ),

    'buckets' => array
    (
        "List all buckets in your account."
    ),

    'objects' => array
    (
        "List all objects in a bucket.",
        "",
        " - Usage: objects [bucket]"
    ),

    'upload' => array
    (
        "Upload a file from your computer.",
        "If your bucket, path, or filename has spaces you should put it in quotes!",
        "",
        " - Usage: upload [path/on/computer] [bucket/path/filename]"
    ),

    'copy' => array
    (
        "Copy a file. Can be used to copy files within a bucket, or between buckets." ,
        "If your bucket, path, or filename has spaces you should put it in quotes!",
        "",
        " - Usage: copy [bucket/from/filename] [bucket/to/filename]"
    ),

    'delete' => array
    (
        "Delete a file.",
        "If your bucket, path, or filename has spaces you should put it in quotes!",
        "",
        " - Usage: delete [bucket/path/filename]"
    ),

    'move' => array
    (
        "Move a file. Equivalent to copying a file and then deleting the source.",
        "If your bucket, path, or filename has spaces you should put it in quotes!",
        "",
        " - Usage: move [bucket/from/filename] [bucket/to/filename]"
    ),

    'quit' => array
    (
        "Quit this application."
    )
);

// Function to format and display help messages
function display_help($message, $replacement)
{
    // Combine message into single string
    $message = implode("\n", $message);

    // Apply replacements
    if(is_array($replacement))
        $message = vprintf($message, $replacement);
    else
        $message = sprintf($message, $replacement);

    // Generate a seperating line based on the length of the last line
    $message = explode("\n", $message);

    $length = strlen(end($message));
    $seperator = str_repeat("-", $length);
    $message[] = $seperator;

    // Output final message
    echo "\n".implode("\n", $message)."\n";
}

// Display initial help message
display_help($help['help'], implode(", ", $commands));

// Function for processing commands from user input
function process_command($line)
{
    preg_match_all("/(?:'.*?'|\".*?\"|\S+)/", $line, $matches);

    foreach($matches[0] as $key => $match)
    {
        $matches[0][$key] = preg_replace("/(^[\"']|[\"']$)/", "", $match);
    }

    $input = $matches[0];
    $command = array_shift($input);
    return array($command, $input);
}

// Function to process paths into buckets and keys
function process_path($path)
{
    $path = explode("/", $path);
    $bucket = array_shift($path);

    return array($bucket, implode("/", $path));
}

// Loop forever to read input from the user
$running = true;
while($running)
{
    $line = readline("> ");
    list($command, $input) = process_command($line);

    if(in_array($command, $commands))
    {
        if($command == "help")
        {
            $topic = array_shift($input);

            if(empty($topic))
                $topic = 'help';

            if(isset($help[$topic]))
            {
                display_help($help[$topic], implode(", ", $commands));
            }
            else
            {
                echo "Sorry! No help exists for that topic.\n";
            }
        }
        elseif($command == "buckets")
        {
            echo "Loading...\n";

            $response = $s3->listBuckets();
            
            foreach ($response['Buckets'] as $bucket)
            {
                echo "| Bucket: {$bucket['Name']} ";
                echo "| Created: {$bucket['CreationDate']} \n";
            }
        }
        elseif($command == "objects")
        {
            $iterator = $s3->getIterator('ListObjects', array('Bucket' => $input[0]));

            foreach ($iterator as $object)
            {
                echo $object['Key'] . "\n";
            }
        }
        elseif($command == "upload")
        {
            list($bucket, $key) = process_path($input[1]);

            $s3->putObject(array
            (
                'Bucket'     => $bucket,
                'Key'        => $key,
                'SourceFile' => $input[0],
            ));

            echo "File '{$input[0]}' uploaded to '{$bucket}/{$key}'! \n";
        }
        elseif($command == "copy")
        {
            list($bucket, $key) = process_path($input[1]);

            $s3->copyObject(array(
                'Bucket'     => $bucket,
                'Key'        => $key,
                'CopySource' => $input[0],
            ));

            echo "File '{$input[0]}' copied to '{$bucket}/{$key}'! \n";
        }
        elseif($command == "delete")
        {
            list($bucket, $key) = process_path($input[0]);

            $s3->deleteObject(array
            (
                'Bucket' => $bucket,
                'Key'    => $key
            ));

            echo "File '{$bucket}/{$key}' deleted! \n";
        }
        elseif($command == "move")
        {
            list($sourceBucket, $sourceKey) = process_path($input[0]);
            list($destBucket, $destKey) = process_path($input[1]);

            $s3->copyObject(array(
                'Bucket'     => $destBucket,
                'Key'        => $destKey,
                'CopySource' => $input[0],
            ));

            $s3->deleteObject(array
            (
                'Bucket' => $sourceBucket,
                'Key'    => $sourceKey
            ));

            echo "File '{$input[0]}' moved to '{$destBucket}/{$destKey}'! \n";
        }
        elseif($command == "quit")
        {
            $running = false;
        }
    }
    else
    {
        echo "Sorry! That's not a valid command, please type 'help' for more information.\n";
    }
}

echo "\nBye!\n\n";

?>
