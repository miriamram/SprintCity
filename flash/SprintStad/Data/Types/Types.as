﻿package SprintStad.Data.Types 
{
	import SprintStad.Data.Data;
	import SprintStad.Data.Demand.Demand;
	import SprintStad.Data.IDataCollection;
	import SprintStad.Debug.Debug;
	public class Types implements IDataCollection
	{
		private var types:Array = new Array();
		
		public function Types() 
		{
			
		}
		
		public function PostConstruct():void
		{
			for each (var type:Type in types)
			{
				type.PostConstruct();
			}
		}
		
		public function AddType(type:Type):void
		{
			types.push(type);
		}
		
		public function GetType(index:int):Type
		{
			return types[index];
		}
		
		public function GetTypeById(id:int):Type
		{
			for each (var type:Type in types)
				if (type.id == id)
					return type;
			return null;
		}
		
		public function getIndex(type:Type):int
		{
			for each (var t:Type in types)
			{
				if (type.id == t.id)
					return types.indexOf(t);
			}
			return -1;
		}
		
		public function GetTypeCount():int
		{
			return types.length;
		}
		
		public function GetTypesOfCategory(category:String):Array
		{
			var result:Array = new Array();
			for each (var type:Type in types)
			{
				if (type.type == category)
					result.push(type);
			}
			return result;
		}
		
		public function Clear():void
		{
			types = new Array();
		}
		
		public function ParseXML(xmlData:XML):void
		{
			Clear();
			
			var type:Type = new Type();
			var typeXml:XML = null;
			var index = 0;
			typeXml = xmlData.type[index];
			
			while (typeXml != null)
			{
				for each (var xml:XML in typeXml.children()) 
				{
					if (xml.name() == "demands")
					{
						type.ParseXML(xml);
					}
					else
					{
						type[xml.name()] = xml;
					}
				}
				AddType(type);
				type = new Type();
				index++;
				typeXml = xmlData.type[index];
			}
		}
	}
}