<?php

class tlist
{
	private $list;

	function __construct()
	{
		$this->list = $this->load();
	}

	private function load()
	{
		$path = 'tl.list';
		if (!file_exists($path)) return [];
		return array_map('trim', file($path));
	}

	private function save()
	{
		$path = 'tl.list';
		file_put_contents($path, implode("\n", $this->list));
	}

	function add($name)
	{
		if ($this->has($name)) return false;
		$this->list[] = $name;
		$this->save();
		return true;
	}

	function unadd($name)
	{
		$pos = array_search($name, $this->list);
		if ($pos === false) return false;
		array_splice($this->list, $pos, 1);
		$this->save();
		return true;
	}

	function has($name)
	{
		return in_array($name, $this->list);
	}
}
