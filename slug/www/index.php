<?php
/**
 * FILE: index.php
 * 
 * Include and execute SLUG PHP API, 
 * then call exec() to execute the routed command,
 * which outputs the return of the command as JSON 
 * 
 */

include 'slug.php';
$jsonFileLocation = '/slug.json';
$jsonFileNamespace = 'org';
$outputEarlyErrors = false;
$trail = new \Org\SLUG\Slug($jsonFileLocation, $jsonFileNamespace, array(), $outputEarlyErrors);

$trail->exec();

exit();

?>