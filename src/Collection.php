<?php

namespace Dijix\ModelMaker;


class Collection implements ArrayAccess, Iterator {
	
	public $list;
	public $class;
	private $position;
	
	
	public function __construct($class)
	{
		$this->class = $class;
		$this->position = 0;
	}
	
	public function first()
	{
		return $this->offsetGet(0);
			
	}
	
	public function push($data)
	{
		$this->list[] = $data;
	}

	#
	#	Array access functions
	#
    public function offsetSet($offset, $value) 
	{
		if (is_null($offset)) {
			$this->list[] = $value;
		} else {
			$this->list[$offset] = $value;
		}
    }

    public function offsetExists($offset) 
	{
		return isset($this->list[$offset]);
    }

    public function offsetUnset($offset) 
	{
		if ($this->offsetExists($offset)) {
			unset($this->list[$offset]);
		}
    }

    public function offsetGet($offset) 
	{
		if ($this->offsetExists($offset))
		{
			return $this->makeObject($this->list[$offset]);
			
		} 
		
		return null;
		
    }
	
	#
	#	Iterator functions
	#
	public function rewind() {
	    $this->position = 0;

	}

	public function current() {
	    return $this->offsetGet($this->position);

	}

	public function key() {
	    return $this->position;

	}

	public function next() {
	    ++$this->position;

	}

	public function valid() {
	    return $this->offsetExists($this->position);

	}
		
	# factory function to return instantiated
	# model object which is ceated just-in-time
	private function makeObject($data)
	{
		$class = $this->class;
		$m = new $class();
		$m->fill($data);
		
		return $m;
		
	}
	
}