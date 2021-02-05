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
		"	list torrents",
		" add <url> add a torrent",
		"-h <id>...	hide torrents",
		"-s <id>...	unhide torrents",
		"-a	list all torrents",
		"-i	list hidden torrents",
		"-r order by seed ratio",
		"-k <id>... remove torrents"
	];
	fwrite(STDERR, "Usage:\n");
	foreach ($l as $s) {
		fwrite(STDERR, "	$prog $s\n");
	}
}

function main($args)
{
	$prog = array_shift($args);
	if (!empty($args) && $args[0] === 'add') {
		array_shift($args);
		return command_add($args);
	}
	return command_list($prog, $args);
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

function command_list($prog, $args)
{
	$visible = true;
	$hidden = false;
	$order = false;

	$tlist = new tlist();

	while (!empty($args) && $args[0][0] == '-') {
		$arg = array_shift($args);
		if ($arg == '-h') {
			return hide($tlist, $args);
		}
		if ($arg == '-s') {
			return show($tlist, $args);
		}
		if ($arg == '-k') {
			return remove($args);
		}
		if ($arg == '-a') {
			$visible = $hidden = true;
			continue;
		}
		if ($arg == '-i') {
			$visible = false;
			$hidden = true;
			continue;
		}
		if ($arg == '-r') {
			$order = true;
			continue;
		}
		fwrite(STDERR, "Unknown flag: $arg\n");
		usage($prog);
		return 1;
	}

	$torc = new torc();
	$list = $torc->ls();

	$list = array_filter($list, function ($a) use ($hidden, $visible, $tlist) {
		if ($hidden && $tlist->has($a['name'])) {
			return true;
		}
		if ($visible && !$tlist->has($a['name'])) {
			return true;
		}
		return false;
	});
	if ($order) {
		usort($list, function ($a, $b) {
			return cmp(floatval($b['ratio']), floatval($a['ratio']));
		});
	}

	out($list);
	return 0;
}

function cmp($a, $b)
{
	if ($a < $b) return -1;
	if ($a > $b) return 1;
	return 0;
}

// Puts torrents with given identifiers in the "hide" list
function hide($tlist, $ids)
{
	$torc = new torc();
	$torrents = $torc->getInfo($ids);
	foreach ($torrents as $t) {
		$tlist->add($t['Name']);
	}
}

// Removes torrents with given identifiers from the "hide" list
function show($tlist, $ids)
{
	$torc = new torc();
	$torrents = $torc->getInfo($ids);
	foreach ($torrents as $t) {
		$tlist->unadd($t['Name']);
	}
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

function out($list)
{
	foreach ($list as $l) {
		$desc = "$l[id]";
		if ($l['eta'] != 'Done') {
			$desc .= " ($l[done])";
		}
		$desc .= "\t$l[up]/$l[down], r=$l[ratio]";
		$desc .= "\t" . $l['name'];

		echo $desc, "\n";
	}
}
