<?php

################################################################################
# CLASS:        AbstractDataType                                               #
# AUTHOR:       Bryan C. Callahan (06/26/2011)                                 #
# DESCRIPTION:  Abstract representation of a persistent data type. A word of   #
#   caution... there be murky reflection in these waters!!                     #
# COPYRIGHT:    Copyright (C) 2009-2012 Bryan C. Callahan                      #
#                                                                              #
# This file is part of Php-prototyper.                                         #
#                                                                              #
# Php-prototyper is free software: you can redistribute it and/or modify it    #
# under the terms of the GNU Lesser General Public License as published by the #
# Free Software Foundation, either version 3 of the License, or (at your       #
# option) any later version.                                                   #
#                                                                              #
# This program is distributed in the hope that it will be useful, but WITHOUT  #
# ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or        #
# FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Lesser General Public License #
# for more details.                                                            #
#                                                                              #
# You should have received a copy of the GNU Lesser General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.
#                                                                           [X]#
################################################################################

abstract class AbstractDataType extends AbstractConnector
{
	protected $MySqlDateFormat = 'Y-m-d H:i:s';

	private $reflect;

	////////////////////////////////////////////////////////////////////////
	public function __construct(&$connectionOwner = NULL)
	{
		$this->reflect = new ReflectionClass($this);
		$this->unpopulate();
		parent::__construct($connectionOwner);
	}

	////////////////////////////////////////////////////////////////////////
	// Resets all of the public member variables to an empty string...
	public function unpopulate()
	{
		$props = $this->reflect->getProperties(ReflectionProperty::IS_PUBLIC);
		foreach ($props as $prop) $prop->setValue($this, '');
		$this->doesExist = false;
	}

	////////////////////////////////////////////////////////////////////////
	// Pull data from the database (return false if not found)...
	public function populate($attribute = NULL, $value = NULL)
	{
		global $init;

		// Snag all the attributes that we need to persist...
		$props = $this->reflect->getProperties(ReflectionProperty::IS_PUBLIC);

		// Find the primary key from the first public attribute...
		if (is_null($attribute) || is_null($value))
		{
			$attribute = $props[0]->getName();
			$value = $this->$attribute;
		}

		// Prepare general query to pull data...
		$stmt = $this->db->prepare("SELECT * From `" . get_class($this) . "` WHERE `$attribute` = ? LIMIT 1;");
		if (!$stmt) $init->abort("Could not populate " . get_class($this) . " data where $attribute = $value");

		// Bind parameters and perform query...
		$stmt->bind_param(AbstractDataType::GetMySqliDataType($value), $value);
		$stmt->execute();
		$stmt->store_result();

		// Determine if record exists (we must have precisely one)...
		$this->doesExist = ($stmt->num_rows == 1);
		if (!$this->doesExist) return false;

		// Bind the results of the query...
		$bindResults = array();
		foreach ($props as $prop)
		{
			$bindResult = $prop->getName();
			$bindResults[] = &$$bindResult;
		}
		if (!@call_user_func_array(array($stmt, 'bind_result'), $bindResults))
			$init->abort('Could not bind to database (ensure ' . get_class($this) . ' matches database attributes)');

		// Bind the data and clean up...
		$stmt->fetch();
		$stmt->close();

		// Maintain member variables...
		foreach ($props as $prop)
		{
			$propName = $prop->getName();
			$this->$propName = &$$propName;
		}

		// Log a debugging event...
		$init->logDebug('Populated ' . get_class($this) . " where $attribute equals $value");

		// Returned the object happily!!...
		return true;
	}

	////////////////////////////////////////////////////////////////////////
	// Saves all private member variables to database...
	// Note: This function inserts if the object doesn't exist and updates 
	//  if it does.
	public function update($attribute = NULL)
	{
		global $init;

		// Snag all the attributes that we need to persist...
		$props = $this->reflect->getProperties(ReflectionProperty::IS_PUBLIC);

		// Find the primary key from the first public attribute...
		if (is_null($attribute))
		{
			$attribute = $props[0]->getName();
		}

		// Prepare the insert/update query...
		if (!$this->doesExist)
		{
			$query = 'INSERT INTO `' . get_class($this) . '` (';

			// Skip the primary key attribute (wherever it is) since 
			//  we place that one at the end just for the sake of 
			//  optimizing the bind params call at the end of this 
			//  block...
			for ($i = 0; $i < count($props); $i++)
			{
				if ($props[$i]->getName() == $attribute) continue;
				$query .= '`' . $props[$i]->getName() . '`,';
			}
			$query .= '`' . $attribute . '`,';
			$query = trim($query, ', ') . ') VALUES (';

			for ($i = 0; $i < count($props); $i++) $query .= '?,';
			$query = trim($query, ',') . ');';
		}
		else
		{
			$query = 'UPDATE `' . get_class($this) . '` SET ';

			// Skip the primary key attribute (use it in where)...
			for ($i = 0; $i < count($props); $i++)
			{
				if ($props[$i]->getName() == $attribute) continue;
				$query .= '`' . $props[$i]->getName() . '`=?, ';
			}
			$query = trim($query, ', ') . ' ';

			// Finish the clause...
			$query .= 'WHERE `' . $attribute . '` = ? LIMIT 1;';
		}

		// Log the query for debugging...
		$init->logDebug(get_class($this) . ' update with query: ' . $query);

		// Update or insert user information...
		$stmt = $this->db->prepare($query);
		if (!$stmt) $init->abort('Could not update ' . get_class($this) . ' data');

		// Assemble data types string...
		$dataTypes = '';
		for ($i = 0; $i < count($props); $i++)
		{
			if ($props[$i]->getName() == $attribute) continue;
			$dataTypes .= AbstractDataType::GetMySqliDataType($props[$i]->getValue($this));
		}
		$dataTypes .= AbstractDataType::GetMySqliDataType($this->$attribute);

		// Bind data types...
		$bindParams[] = $dataTypes;
		for ($i = 0; $i < count($props); $i++)
		{
			if ($props[$i]->getName() == $attribute) continue;
			$bindParam = $props[$i]->getName();
			$bindParams[] = &$$bindParam;
		}
		$bindParam = $attribute;
		$bindParams[] = &$$bindParam;
		call_user_func_array(array($stmt, 'bind_param'), $bindParams);

		// Fill the bound variables with data...
		foreach ($props as $prop)
		{
			$propName = $prop->getName();
			$$propName = $this->$propName;
		}

		// Execute update...
		$stmt->execute();
		if ($stmt->errno == 1062) $init->abort('Could not update ' . get_class($this) . ' data because it is a duplicate entry');
		if ($stmt->errno != 0) $init->abort('Could not update ' . get_class($this) . ' data (' . $stmt->errno . ')');

		// If the data type has an ID and we've inserted, maintain it...
		// Note: This code should be deprecated because we're using Uuid for PK.
		//if ($this->reflect->hasProperty('Id'))
		//{
		//	$idProp = $this->reflect->getProperty('Id');
		//	if (!is_numeric($idProp->getValue($this))) $idProp->setValue($this, $this->db->insert_id);
		//}

		// Clean up statement and maintain specialty variables...
		$stmt->close();

		// If we made it here, we can guarantee that the record exists...
		$this->doesExist = true;
	}

	////////////////////////////////////////////////////////////////////////
	// Immediately removes the record from the database...
	// Note: This function will produce a warning if the data doesn't exist. 
	//  This is an attempt to save a query (instead of bricking) because if  
	//  we checked $this->doesExist we would first need to run populate.
	public function remove()
	{
		global $init;

		// If the we don't think the data is in the database, log a warning...
		if (!$this->doesExist) $init->logError('An attempt to remove ' . get_class($this) . ' data made on a nonexistent object');

		// Get the first property (this is our primary key so we'll delete 
		//  according to it)...
		$props = $this->reflect->getProperties(ReflectionProperty::IS_PUBLIC);
		$propName = $props[0]->getName();

		// Prepare our error message...
		$errorMessage = 'Could not remove ' . get_class($this) . ' data where ';
		$errorMessage .= $propName . ' = ' . $this->$propName;

		// Prepare removal statement...
		$query = 'DELETE FROM `' . get_class($this) . '` WHERE `' . $propName . '` = ? LIMIT 1;';
		$stmt = $this->db->prepare($query);
		if (!$stmt) $init->abort($errorMessage);

		// Bind the primary key variable and remove data...
		$stmt->bind_param(AbstractDataType::GetMySqliDataType($this->$propName), $this->$propName);
		if (!$stmt->execute()) $init->abort($errorMessage);
		$stmt->close();

		// Make a note and clean up...
		$init->logDebug(get_class($this) . ' data has been removed where ' . $propName . ' equals ' . $this->$propName);
		$this->unpopulate();
	}

	////////////////////////////////////////////////////////////////////////
	public static function GetMySqliDataType($variable)
	{
		if (is_int($variable)) return 'i';
		if (is_float($variable)) return 'd';
		if (is_string($variable)) return 's';
		return 'b';
	}
}
?>
