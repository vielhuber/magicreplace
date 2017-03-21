<?php
if (php_sapi_name() != 'cli')
{
	die('only command line usage possible');
}
require(getcwd() . '/vendor/autoload.php');
use vielhuber\magicreplace\MagicReplace;

if (!isset($argv) || empty($argv) || !isset($argv[1]) || !isset($argv[2]) || !isset($argv[3]) || !isset($argv[4]))
{
	die('missing options');
}
if (!file_exists(getcwd() . '/' . $argv[1]))
{
	die('missing input');
}
$input = getcwd() . '/' . $argv[1];
if (!file_exists(getcwd() . '/' . $argv[2]))
{
	touch(getcwd() . '/' . $argv[2]);
}
$output = getcwd() . '/' . $argv[2];
$search_replace = [];
foreach ($argv as $argv__key => $argv__value)
{
	if ($argv__key <= 2)
	{
		continue;
	}
	if ($argv__key % 2 == 1 && !isset($argv[ $argv__key + 1 ]))
	{
		continue;
	}
	if ($argv__key % 2 == 0)
	{
		continue;
	}
	$search_replace[ $argv[ $argv__key ] ] = $argv[ $argv__key + 1 ];
}
MagicReplace::run($input, $output, $search_replace);
die('done');