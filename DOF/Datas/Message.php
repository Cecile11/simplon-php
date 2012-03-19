<?php
/*
	Copyright © 2011 Rubén Schaffer Levine and Luca Lauretta <http://simplonphp.org/>
	
	This file is part of “SimplOn PHP”.
	
	“SimplOn PHP” is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation version 3 of the License.
	
	“SimplOn PHP” is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
	
	You should have received a copy of the GNU General Public License
	along with “SimplOn PHP”.  If not, see <http://www.gnu.org/licenses/>.
*/
namespace DOF\Datas;

class Message extends Data
{
	protected
	
		$view = true,

		$create = true,

		$update = true,

		$search = true,

		$list = false,

		$required = false,

		$fetch = false;
	
	
	
	
	function showInput($fill)
	{ return $this->showView(); }
	
	
	public function doRead()
	{}
	
	public function doCreate()
	{}
		
	public function doUpdate()
	{}

	public function doSearch()
	{}	
	
	
}