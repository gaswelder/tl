<?php

require_once 'buf.php';

function parseLine($line)
{
	// ID     Done       Have  ETA           Up    Down  Ratio  Status       Name
	// 1      100%   10.48 MB  Done         0.0     0.0    2.9  Idle         John Fowles
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
	$info['percent'] = $number();
	$buf->expect('%');

	$buf->read_set(' ');
	$info['have'] = $buf->read_set('0123456789.') . $buf->expect(' ') . $buf->until(' ');

	$info['eta'] = $word();
	$info['up'] = $number();
	$info['down'] = $number();
	$info['ratio'] = $number();
	$info['status'] = $word();

	$buf->read_set(' ');
	$info['name'] = $buf->rest();
	return $info;
}

class torc
{
	private function exec($cmd)
	{
		// exec("ssh pi bash --login torc $cmd", $out, $r);
		exec("torc $cmd", $out, $r);
		if ($r != 0) {
			throw new Exception("torc exec failed");
		}
		return $out;
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
