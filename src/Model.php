<?php 

namespace Dijix\ModelMaker;

use Carbon\Carbon;

class Model extends Query implements ArrayAccess, Iterator {

	# use this table name 
	protected $table;
	# allow these fields to be populated via fill()
	protected $fillable = array();
	# never show these fields in output
	protected $hidden = array();
	# these fields will be converted to dates
	protected $dates = array('date');
	# query builder
	protected $query;
	# user data array
	protected $data = array();
	# original data array
	protected $original = array();
	# iterator cursor
	private $position;

	
	public function __construct($opts=array()) 
	{
		$this->setTableName();
		Event::fire('constructed', $this);
		
	}

	# fill the model attributes
	public function fill($array=array())
	{
		foreach ($array as $field=>$value)
		{
			#TODO add filter here
			$this->$field = $value;
		}
		
	}

	public function delete()
	{
 	 	Event::fire('before.delete', $this);

		if (isset($this->fillable['deleted_at'])) {
			$this->deleted_at = date("Y-m-d H:i:s");
			$result = $this->update();
		}
		
		# returns boolean
		$query = new Query();
		$result = $query->delete($this->id);
		
		Event::fire('after.create', $this);

		return $result;
		
	}

    public function insert() 
	{
 	 	Event::fire('before.create', $this);
		
		if (isset($this->fillable['created_at'])) {
			$this->created_at = date("Y-m-d H:i:s");
		}
		
		$result = parent::insert($data);
	 	
		Event::fire('after.create', $this);
    	
		return $result;
	
	}
	
    public function update() 
	{
 	 	Event::fire('before.update', $this);
		
		if (isset($this->fillable['updated_at'])) {
			$this->updated_at = date("Y-m-d H:i:s");
		}
		
		$result = parent::update($data);
	 	
		Event::fire('after.update', $this);
    	
		return $result;
	
	}

	public function save($data)
	{
 	 	Event::fire('before.save', $this);

		if (isset($this->id)) {
			$result = $this->query->update($this->toArray());
		} else {
			$result =  $this->query->insert($this->toArray());
		}

		Event::fire('after.save', $this);
    	
		return $result;
		
	}
	
	public function first()
	{
		$query = new Query();
		return $query->getOne();

	}
	
	public function firstOrCreate()
	{
		$query = new Query();
		if ($query->exists($this->data)) {
			
			return $query->getOne();
		}
		
	}
	
	
	
	#
	#	model relationships
	#
	public function belongsTo($class, $key_field)
	{
		$c = new $class();
		return $c->where('id', $this->$key_field)->first();
	
	}
	
	public function hasOne($class, $key_field)
	{
		#TODO test this
		$c = new $class();
		return $c->where($key_field, $this->id)->first();
	
	}
	
	public function hasMany($class, $key_field)
	{
		$c = new $class();
		return $c->where($key_field, $this->id)->fetch();
	
	}

	#
	#	Handle Dirty/Original data attributes 
	#
	public function isDirty($key)
	{
		if (isset($this->original[$key])) {
			return true;
		}
	}
	
	public function getDirty()
	{
		$out = array();
		foreach ($this->original as $key=>$value)
		{
			$out[$key] = $this->data[$key];
		}
		return $out;
	}
	
	public function getOriginal($key) 
	{
		if (isset($this->original[$key])) {
			return $this->original[$key];
		}
	}
	

	#	return data array as json 
	public function __toString()
	{
		$out = array();
		foreach ($this->data as $key=>$value) {
			if ( ! array_key_exists($key, $this->hidden)) {
				$out[$key] = $value;
			}
		}
		
		return json_encode($out);
	
	}


	#
	#	overriding magic methods allows us to store
	#	model attributes in data array
	#
	public function __get($key)
	{
		# check for defined attribute getters
		$getter = 'get'.ucfirst($key).'Attribute'; 
		if (method_exists($this, $getter)) {
			return $this->$getter();
		}
		
		# check for dates
		if (in_array($key, $this->dates)) {
			if (strtotime($this->data[$key])) {
				return new Carbon($this->data[$key]);
			} else {
				return new Carbon();
			}
		}
		
		# then check for and return user data
		if (isset($this->data[$key])) {
			return $this->data[$key];
		}

		if (isset($this->$key)) {
			return $this->$key;
		}
		
		return null;
		
	}

	public function __isset($key)
	{
		return isset($this->data[$key]);
		
	}
	
	public function __set($key, $value)
	{
		# check for defined attribute setters
		$setter = 'set'.ucfirst($key).'Attribute'; 
		if (method_exists($this, $setter)) {
			# save original for 'dirty' checks
			if (isset($this->data[$key])) {
				$this->original[$key] = $this->data[$key];
			} else {
				$this->original[$key] = null;
			}
			return $this->$setter($value);
		}
		
		if (property_exists($this, $key)
		&& $key != $data) {
			# assign model properties if they exist
			$this->$key = $value;
		} else {
			# otherwise it's user data
			$this->data[$key] = $value;
		}
		
	}
	
    public function __unset($key) 
	{
		unset($this->data[$key]);

	}
	
	#
	#	Array access functions
	#
    public function offsetSet($offset, $value) 
	{
		if (is_null($offset)) {
			$this->data[] = $value;
		} else {
			$this->data[$offset] = $value;
		}
    }

    public function offsetExists($offset) 
	{
		return isset($this->data[$offset]);
    }

    public function offsetUnset($offset) 
	{
		if ($this->offsetExists($offset)) {
			unset($this->data[$offset]);
		}
    }

    public function offsetGet($offset) 
	{
		if ($this->offsetExists($offset))
		{
			return $this->data[$offset];
			
		} 
		
		return null;
		
    }
	
	#
	#	Iterator functions
	#
	public function rewind() 
	{
	    $this->position = 0;

	}

	public function current() 
	{
	    return $this->offsetGet($this->getKeyAtIndex($this->position));

	}

	public function key() 
	{
	    return $this->getKeyAtIndex($this->position);

	}

	public function next() 
	{
	    ++$this->position;

	}

	public function valid() 
	{
	    return $this->offsetExists($this->getKeyAtIndex($this->position));

	}
	
	#
	#	magic methods
	#
	// public function __call($method, $args)
	// {
	//
	// }
	
	
	private function getKeyAtIndex($index)
	{
		$keys = array_keys($this->data);
		if (isset($keys[$index])) {
			return $keys[$index];
		}

		return null;

	}
	
	private function setTableName($str=null)
	{
		# if table is not specified try to guess table
		if (empty($str) && empty($this->table))
		{
			$str = basename(get_class($this));
			$this->table(Str::snake($str));
		}
	}
	
}