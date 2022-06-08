<?php

require_once 'buf.php';

class torc
{
	private function exec($cmd, ...$args)
	{
		$cmdline = $cmd;
		foreach ($args as $arg) {
			$cmdline .= ' ' . escapeshellarg($arg);
		}
		// exec("ssh pi transmission-remote -n transmission:transmission $cmdline", $out, $r);
		exec("transmission-remote -n transmission:transmission $cmdline", $out, $r);
		if ($r != 0) {
			throw new Exception("exec failed");
		}
		return $out;
	}

	function add($url)
	{
		$this->exec("-a", $url);
	}

	function ls()
	{
		$out = $this->exec("-l");
		$header = array_shift($out);
		$footer = array_pop($out);
		return array_map('parseLine', $out);
	}

	function getInfo($ids)
	{
		$list = implode(',', $ids);
		$out = $this->exec("-t $list -i");

		// Group output lines by torrent
		$results = [];
		$n = -1;
		foreach ($out as $line) {
			if ($line == 'NAME') {
				$n++;
				$results[$n] = [];
			}
			$results[$n][] = $line;
		}

		// Parse the lines
		$torrents = [];
		foreach ($results as $lines) {
			$torrent = [];
			foreach ($lines as $line) {
				if (!strpos($line, ':')) continue;
				list($k, $v) = array_map('trim', explode(':', trim($line)));
				$torrent[$k] = $v;
			}
			$torrents[] = $torrent;
		}

		// Index by id
		$map = [];
		foreach ($torrents as $t) {
			$map[$t['Id']] = $t;
		}
		foreach ($ids as $id) {
			if (!isset($map[$id])) {
				throw new Exception("no torrent with id '$id'");
			}
		}
		return $map;
	}

	// Takes array of ids, returns array of paths.
	function find($ids)
	{
		$paths = [];

		$torrents = $this->getInfo($ids);
		foreach ($ids as $id) {
			$t = $torrents[$id];
			$dir = $t['Location'];
			$name = $t['Name'];
			assert($dir && $name);
			$paths[] = "$dir/$name";
		}

		return $paths;
	}
}
