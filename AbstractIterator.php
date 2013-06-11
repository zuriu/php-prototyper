<?php

################################################################################
# OBJECT:       AbstractIterator                                               #
# AUTHOR:       Bryan C. Callahan (06/27/2011)                                 #
# DESCRIPTION:  Represents a simple iterator to navigate through collections.  #
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

abstract class AbstractIterator extends AbstractConnector
{
	protected $query;
	protected $itemCount;
	protected $nextCount;

	////////////////////////////////////////////////////////////////////////
	public function __construct(&$connection_owner = NULL)
	{
		parent::__construct($connection_owner);
	}

	////////////////////////////////////////////////////////////////////////
	public function __destruct()
	{
		if (!is_null($this->query)) $this->query->close();
		parent::__destruct();
	}

	////////////////////////////////////////////////////////////////////////
	// Returns whether or not there are more results...
	public function hasNext()
	{
		return ($this->nextCount < $this->itemCount);
	}

	////////////////////////////////////////////////////////////////////////
	// Returns the number of items in the collection...
	public function getItemCount()
	{
		return $this->itemCount;
	}

	////////////////////////////////////////////////////////////////////////
	// Returns number of times taken a new item from the collection...
	public function getNextCount()
	{
		return $this->nextCount;
	}
	
	////////////////////////////////////////////////////////////////////////////
	// Gets the next item in the collection...
	public function getNext()
	{
	}
}

?>
