﻿package SprintStad.State 
{
	import flash.display.Bitmap;
	import flash.display.DisplayObject;
	import flash.display.MovieClip;
	import flash.events.Event;
	import flash.events.MouseEvent;
	import flash.net.URLLoader;
	import flash.net.URLRequest;
	import flash.net.URLVariables;
	import SprintStad.Calculators.StationStatsCalculator;
	import SprintStad.Calculators.StationTypeCalculator;
	import SprintStad.Data.Data;
	import SprintStad.Data.DataLoader;
	import SprintStad.Data.Program.Program;
	import SprintStad.Data.Round.Round;
	import SprintStad.Data.Station.Station;
	import SprintStad.Data.Station.StationInstance;
	import SprintStad.Data.Types.Type;
	import SprintStad.Data.Types.Types;
	import SprintStad.Debug.Debug;
	import SprintStad.Drawer.AreaBarDrawer;
	import SprintStad.Drawer.ProgramEditor;
	import SprintStad.Drawer.ProgramSlider;
	public class ResultState implements IState
	{		
		private var parent:SprintStad = null;
		private var loadCount:int = 0;
		private var editor:ProgramEditor;
		private var pastStationInstance:StationInstance;
		private var currentStationInstance:StationInstance;
		
		private var barCenterMasterplan:AreaBarDrawer;
		private var barPastArea:AreaBarDrawer;
		private var barPastTransformArea:AreaBarDrawer;
		private var barCurrentArea:AreaBarDrawer;
		private var barCurrentTransformArea:AreaBarDrawer;
		
		public function ResultState(parent:SprintStad) 
		{
			this.parent = parent;
		}
			
		private function DrawUI(station:Station):void
		{
			// draw stuff
			var view:MovieClip = parent.result_movie;
			
			// station sign
			view.board.name_field.text = station.name;
			view.board.region_field.text = station.region;
			view.board.town_field.text = station.town;
			
			// background
			view.sheet.addChild(station.imageData);
			
			// graph masterplan
			barCenterMasterplan.DrawBar(
				station.program.area_home, 
				station.program.area_work, 
				station.program.area_leisure, 
				station.GetTotalTransformArea() - station.program.area_home - station.program.area_work - station.program.area_leisure,
				0);

			// update editor
			editor.SetStation(station);
			
			// left info
			pastStationInstance = StationInstance.CreateInitial(station);
			DrawStationInfo(pastStationInstance, view.past_info, barPastArea, barPastTransformArea, station.GetRound(0).name);
			
			// right info
			currentStationInstance = StationInstance.Create(station);
			DrawStationInfo(currentStationInstance, view.current_info, barCurrentArea, barCurrentTransformArea, "2030");
		}
		
		private function DrawStationInfo(station:StationInstance, 
			clip:MovieClip, area_bar:AreaBarDrawer, transform_area_bar:AreaBarDrawer, 
			title:String)
		{
			var top:Array = StationTypeCalculator.GetStationTypeTop(station);
			var bitmap:Bitmap;
			
			clip.title.text = title;
			
			clip.station_type_1_percent.text = top[0].similarity + "%";
			clip.station_type_1_name.text = top[0].stationType.name;
			bitmap = new Bitmap(top[0].stationType.imageData);
			bitmap.width = 100;
			bitmap.height = 100;
			clip.station_type_1_image.addChild(bitmap);
			
			clip.station_type_2_percent.text = top[1].similarity + "%";
			clip.station_type_2_name.text = top[1].stationType.name;
			bitmap = new Bitmap(top[1].stationType.imageData);
			bitmap.width = 100;
			bitmap.height = 100;
			clip.station_type_2_image.addChild(bitmap);
			
			clip.station_type_3_percent.text = top[2].similarity + "%";
			clip.station_type_3_name.text = top[2].stationType.name;
			bitmap = new Bitmap(top[2].stationType.imageData);
			bitmap.width = 100;
			bitmap.height = 100;
			clip.station_type_3_image.addChild(bitmap);
			
			area_bar.DrawBar(
				station.area_cultivated_home,
				station.area_cultivated_work,
				station.area_cultivated_mixed, 
				station.area_undeveloped_urban,
				station.area_undeveloped_rural);
			clip.area.text = "(" + Math.round(
				station.area_cultivated_home +
				station.area_cultivated_work +
				station.area_cultivated_mixed + 
				station.area_undeveloped_urban + 
				station.area_undeveloped_rural) + " ha.)";
			transform_area_bar.DrawBar(
				station.transform_area_cultivated_home, 
				station.transform_area_cultivated_work, 
				station.transform_area_cultivated_mixed, 
				station.transform_area_undeveloped_urban,
				station.transform_area_undeveloped_mixed);
			clip.transform_area.text = "(" + Math.round( 
				station.transform_area_cultivated_home + 
				station.transform_area_cultivated_work + 
				station.transform_area_cultivated_mixed +  
				station.transform_area_undeveloped_urban +
				station.transform_area_undeveloped_mixed) + " ha.)";
				
			clip.amount_travelers.text = StationStatsCalculator.GetTravelersStats(station);
			clip.amount_citizens.text = int(station.count_home_total * Data.Get().GetConstants().average_citizens_per_home);
			clip.amount_workers.text = int(station.count_work_total * Data.Get().GetConstants().average_workers_per_bvo);
			clip.amount_houses.text = Math.round(station.count_home_total);
			clip.bvo_work.text = Math.round(station.count_work_total);
		}
		
		private function GetCurrentRound():Round
		{
			return parent.GetCurrentStation().GetRoundById(Data.Get().current_round_id);
		}
		
		private function OnEditorChange():void
		{
		}
		
		private function OnCancelButton(event:MouseEvent):void
		{
			parent.gotoAndPlay(SprintStad.FRAME_OVERVIEW);
		}
		
		public function OnLoadingDone(data:int):void
		{
			loadCount++;
			if (loadCount >= 2)
			{
				loadCount = 0;
				DrawUI(parent.GetCurrentStation());				
				//remove loading screen
				parent.removeChild(SprintStad.LOADER);
			}
		}
		
		/* INTERFACE SprintStad.State.IState */
		
		public function Activate():void 
		{
			var view:MovieClip = parent.result_movie;
			parent.addChild(SprintStad.LOADER);
			view.cancel_button.buttonMode = true;
			view.cancel_button.addEventListener(MouseEvent.CLICK, OnCancelButton);
			
			// init bar graphs
			barCenterMasterplan = new AreaBarDrawer(view.transform_graph);
			barPastArea = new AreaBarDrawer(view.past_info.area_bar);
			barPastTransformArea = new AreaBarDrawer(view.past_info.transform_area_bar);
			barCurrentArea = new AreaBarDrawer(view.current_info.area_bar);
			barCurrentTransformArea = new AreaBarDrawer(view.current_info.transform_area_bar);
			
			// draw editor
			var types:Types = Data.Get().GetTypes();
			editor = new ProgramEditor(view.program_graph, OnEditorChange);
			
			// display loading screen
			DataLoader.Get().AddJob(DataLoader.DATA_STATIONS, OnLoadingDone);
			DataLoader.Get().AddJob(DataLoader.DATA_CURRENT_ROUND, OnLoadingDone);
		}
		
		public function Deactivate():void
		{
			//parent.intro_movie.removeEventListener(MouseEvent.CLICK, onContinueEvent);
		}
	}
}