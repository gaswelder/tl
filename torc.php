<?php

require_once 'buf.php';

function parseLine($line)
{
	// ID     Done       Have  ETA           Up    Down  Ratio  Status       Name
	// 1      100%   10.48 MB  Done         0.0     0.0    2.9  Idle         John Fowles
	// 41     n/a        None  Unknown      0.0     0.0   None  Idle          Books
	$buf = new buf($line);

	$number = function () use ($buf) {
		$buf->read_set(' ');
		return $buf->read_set('0123456789.');
	};

	$word = function () use ($buf) {
		$buf->read_set(' ');
		return $buf->read_set($buf::regex('/\w/'));
	};

	$info['id'] = $number();

	// "Done": 20% | n/a
	$buf->read_set(' ');
	if ($buf->skip_literal('n/a')) {
		$info['done'] = 'n/a';
	} else {
		$info['done'] = $number() . $buf->expect('%');
	}

	// "Have": 10.48 MB | None
	$buf->read_set(' ');
	if ($buf->skip_literal('None')) {
		$info['have'] = 'None';
	} else {
		$info['have'] = $buf->read_set('0123456789.') . $buf->expect(' ') . $buf->until(' ');
	}

	// "ETA": 10 m | Done | Unknown
	$buf->read_set(' ');
	if (is_numeric($buf->peek())) {
		$info['eta'] = $number() . ' ' . $word();
	} else {
		$info['eta'] = $word();
	}
	$info['up'] = $number();
	$info['down'] = $number();
	$info['ratio'] = $number();

	$buf->read_set(' ');
	if ($buf->skip_literal("Up & Down")) {
		$info['status'] = "Up & Down";
	} else {
		$info['status'] = $word();
	}

	$buf->read_set(' ');
	$info['name'] = $buf->rest();
	return $info;
}

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

	function remove($id)
	{
		$this->exec("-t $id -r");
	}
}
