<?php

################################################################################
# CLASS:        AbstractConnector                                              #
# AUTHOR:       Bryan C. Callahan (06/26/2011)                                 #
# DESCRIPTION:  Abstract representation of a basic database connection.        #
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

abstract class AbstractConnector
{
	protected $db = NULL;
	protected $doesExist = false;
	private $isConnected = false;
	private $handlesConnection = true;

	////////////////////////////////////////////////////////////////////////
	public function __construct(&$connectionOwner = NULL)
	{
		// We are using Initialize to gain access to configuration, make
		//  sure that the class has been defined...
		if (!class_exists('Initialize'))
			die("Class '" . get_class($this) . "' requires class 'Initialize'.");

		global $init;
		
		// Determine whether this object should handle the connection...
		$this->handlesConnection = is_null($connectionOwner);

		// Connect to database if we're the connection owner...
		if ($this->handlesConnection)
			$this->db = new mysqli($init->getProp('General/Database/Host'),
				$init->getProp('General/Database/Username'),
				$init->getProp('General/Database/Password'),
				$init->getProp('General/Database/Name'));
		else
			$this->db = $connectionOwner->getDatabase();

		// Check if we have a good connection...
		$this->isConnected = ($this->db->connect_errno == 0);
	}
	
	////////////////////////////////////////////////////////////////////////
	public function __destruct()
	{
		// If we own the db connection, close it...
		if ($this->handlesConnection && !is_null($this->db))
			@$this->db->close();
	}

	////////////////////////////////////////////////////////////////////////
	public function getDatabase() { return $this->db; }
	public function getIsConnected() { return $this->isConnected; }
}

?>
