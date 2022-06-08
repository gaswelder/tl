<?php

/*
 * tl: drives the "torc" command to get more convenient output
 * and ability to "hide" torrents from the output.
 */

require "torc.php";
require "tlist.php";

exit(main($argv));

function usage($prog)
{
	$l = [
		" add <url> add a torrent",
		"-k <id>... remove torrents"
	];
	fwrite(STDERR, "Usage:\n");
	foreach ($l as $s) {
		fwrite(STDERR, "	$prog $s\n");
	}
}

function command_add($args)
{
	if (count($args) != 1) {
		fwrite(STDERR, "Usage: add <url>");
		return 1;
	}
	$torc = new torc();
	$torc->add($args[0]);
}

function remove($ids)
{
	$torc = new torc();
	$paths = $torc->find($ids);
	foreach ($ids as $i => $id) {
		$path = $paths[$i];
		$newpath = dirname($path) . '/__' . basename($path);
		$torc->remove($id);
		rename($path, $newpath);
	}
}
