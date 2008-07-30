
package  {
	import ChartObjects.Elements.Element;
	import ChartObjects.Elements.PieSlice;
	import ChartObjects.Factory;
	import ChartObjects.ObjectCollection;
	import flash.events.Event;
	import flash.events.MouseEvent;
	import flash.display.Sprite;
	import flash.net.URLLoader;
	import flash.net.URLRequest;
	import flash.display.StageAlign;
    import flash.display.StageScaleMode;
	import labels.*;
	import string.Utils;
	import global.Global;
	import com.serialization.json.JSON;
	import flash.external.ExternalInterface;
	import flash.ui.ContextMenu;
	import flash.ui.ContextMenuItem;
	import flash.events.IOErrorEvent;
	import flash.events.ContextMenuEvent;
	
//	import flash.text.TextField;
	
	// from example code
	import flash.display.LoaderInfo;
//	import flash.display.Loader;
	
	import com.adobe.images.JPGEncoder;
	import flash.display.BitmapData;
	import flash.utils.ByteArray;
	import flash.net.URLRequestHeader;
	import flash.net.URLRequestMethod;
	import flash.net.URLLoaderDataFormat;
	
	
	public class main extends Sprite {
		
		private var title:Title = null;
		private var x_labels:XAxisLabels;
		private var x_axis:XAxis;
		private var x_legend:XLegend;
		private var y_axis:YAxisBase;
		private var y_axis_right:YAxisBase;
		private var y_legend:YLegendBase;
		private var y_legend_2:YLegendBase;
		private var keys:Keys;
		private var obs:ObjectCollection;
		public var tool_tip_wrapper:String;
		private var sc:ScreenCoords;
		private var tooltip:Tooltip;
		private var background:Background;
		private var ok:Boolean;
		
		public function main() {

			var loading:String = 'Loading data...';
			var parameters:Object = LoaderInfo(this.loaderInfo).parameters;
			if( parameters['loading'] )
				loading = parameters['loading'];
				
			var l:Loading = new Loading(loading);
			this.addChild( l );

			// so we can rotate text:
			//this.embedFonts = true;

			// Right click menu:
//			var cm:ContextMenu = new ContextMenu();
//			cm.addEventListener(ContextMenuEvent.MENU_SELECT, onContextMenuHandler);
//			cm.hideBuiltInItems();
//			var fs:ContextMenuItem = new ContextMenuItem("Show Full Screen" );
//			//fs.addEventListener(ContextMenuEvent.MENU_ITEM_SELECT, onShowFullScreen);
//			cm.customItems.push( fs );


			if( !this.find_data() )
			{
				// no data found -- debug mode?
				try {
					var file:String = "../data-files/horizontal-bar-chart.txt";
					this.load_external_file( file );
				}
				catch (e:Error) {
					this.show_error( 'Loading test data\n'+file+'\n'+e.message );
				}
			}
			
			// inform javascript that it can call our load method
			ExternalInterface.addCallback("load", load);
			
			// inform javascript that it can call our save_image method
			ExternalInterface.addCallback("save_image", save_image);
			
			// tell the web page that we are ready
			ExternalInterface.call("ofc_ready");
			
			this.ok = false;
			this.set_the_stage();
		}
		
		//
		// External interface called by Javascript to
		// save the flash as an image
		//
		public function save_image( url:String, callback:String, debug:Boolean ):void {
			
			tr.ace('@@@@-- Saving image --@@@@@');

			var quality:Number = 90;

			var jpgSource:BitmapData = new BitmapData(this.width, this.height);
			jpgSource.draw(this);
			var jpgEncoder:JPGEncoder = new JPGEncoder(quality);
			var jpgStream:ByteArray = jpgEncoder.encode(jpgSource);
			var header:URLRequestHeader = new URLRequestHeader("Content-type", "application/octet-stream");

			//Make sure to use the correct path to jpg_encoder_download.php
			var jpgURLRequest:URLRequest = new URLRequest(url);
			
			jpgURLRequest.requestHeaders.push(header);
			jpgURLRequest.method = URLRequestMethod.POST;
			jpgURLRequest.data = jpgStream;

			if( debug )
			{
				// debug the PHP:
				flash.net.navigateToURL(jpgURLRequest, "_blank");
			}
			else
			{
				var loader:URLLoader = new URLLoader();
				loader.dataFormat = URLLoaderDataFormat.BINARY;
				loader.addEventListener(Event.COMPLETE, function(e:Event):void {
					tr.ace('Svaed image to:');
					tr.ace( url );
					//
					// when the upload has finished call the user
					// defined javascript function/method
					//
					ExternalInterface.call(callback);
					});
					
				loader.load( jpgURLRequest );
			}
		}

		
		private function onContextMenuHandler(event:ContextMenuEvent):void
		{
		}
		
		//
		// try to find some data to load,
		// check the URL for a file name,
		//
		//
		public function find_data(): Boolean {
						
			// var all:String = ExternalInterface.call("window.location.href.toString");
			var vars:String = ExternalInterface.call("window.location.search.substring", 1);
			
			if( vars != null )
			{
				var p:Array = vars.split( '&' );
				for each ( var v:String in p )
				{
					if( v.indexOf( 'ofc=' ) > -1 )
					{
						var tmp:Array = v.split('=');
						tr.ace( 'Found external file:' + tmp[1] );
						this.load_external_file( tmp[1] );
						//
						// LOOK:
						//
						return true;
					}
				}
			}
			
			var parameters:Object = LoaderInfo(this.loaderInfo).parameters;
			
			if( parameters['data-file'] )
			{
				tr.ace( 'Found parameter:' + parameters['data-file'] );
				this.load_external_file( parameters['data-file'] );
				//
				// LOOK:
				//
				return true;
				
			}
			
			var json_string:* = ExternalInterface.call( 'open_flash_chart_data' );
			
			if( json_string != null )
			{
				if( json_string is String )
				{
					this.parse_json( json_string );
					return true;
				}
			}
			
			return false;
		}
		
		private function load_external_file( file:String ):void {
			//
			// LOAD THE DATA
			//
			var loader:URLLoader = new URLLoader();
			loader.addEventListener( IOErrorEvent.IO_ERROR, this.ioError );
			loader.addEventListener( Event.COMPLETE, xmlLoaded );
			
			var request:URLRequest = new URLRequest(file);
			loader.load(request);
		}
		
		private function ioError( e:IOErrorEvent ):void {
			this.show_error( 'Open Flash Chart\nIO ERROR\nLoading test data\n' + e.text );
		}
		
		private function show_error( msg:String ):void {
			
			// remove the 'loading data...' msg:
			this.removeChildAt(0);
			this.addChild( new ErrorMsg( msg ) );
		}

		public function get_x_legend() : XLegend {
			return this.x_legend;
		}
		
		private function set_the_stage():void {

			// tell flash to align top left, and not to scale
			// anything (we do that in the code)
			this.stage.align = StageAlign.TOP_LEFT;
			//
			// ----- RESIZE ----
			//
			// noScale: now we can pick up resize events
			this.stage.scaleMode = StageScaleMode.NO_SCALE;
            this.stage.addEventListener(Event.ACTIVATE, this.activateHandler);
            this.stage.addEventListener(Event.RESIZE, this.resizeHandler);
			this.stage.addEventListener(Event.MOUSE_LEAVE, this.mouseOut);
			
			
			
			//this.stage.addEventListener( ShowTipEvent.SHOW_TIP_TYPE, this.show_tip );
			this.stage.addEventListener( ShowTipEvent.SHOW_TIP_TYPE, this.show_tip );
			this.addEventListener( MouseEvent.MOUSE_OVER, this.show_tip2 );
			//this.stage.addEventListener( Event..MIDDLE_CLICK, this.show_tip );
		}
		
		private function show_tip( event:ShowTipEvent ):void {
			tr.ace( 'show_tip: over '+event.pos );
		}
		
		private function show_tip2( event:MouseEvent ):void {
			tr.ace( 'over ' + event.target );
			
			this.mouseMove( event );
		}
		
		private function mouseMove( event:Event ):void {
//			if( this.sc.get_x_pos( this.mouseX ) > this.sc.get_x_pos(0) )

			// tr.ace('move ' + Math.random().toString());
			switch( this.tooltip.get_tip_style() ) {
				case Tooltip.CLOSEST:
					this.mouse_move_closest( event );
					break;
					
				case Tooltip.FOLLOW:
					this.mouse_move_follow( event as MouseEvent );
					break;
			}
		}
		
		private function mouse_move_follow( event:MouseEvent ):void {

			if( event.target is PieSlice )
				this.tooltip.draw( event.target as Element );
			else
				this.tooltip.hide();
		}
		
		private function mouse_move_closest( event:Event ):void {

			var e:Element = this.obs.mouse_move( this.mouseX, this.mouseY );
			this.tooltip.closest( e );
		}
		
		private function activateHandler(event:Event):void {
            tr.ace("activateHandler: " + event);
        }

        private function resizeHandler(event:Event):void {
            // FlashConnect.trace("resizeHandler: " + event);
            this.resize();
        }
		
		//
		// pie charts are simpler to resize, they don't
		// have all the extras (X,Y axis, legends etc..)
		//
		private function resize_pie(): void {
			this.addEventListener(MouseEvent.MOUSE_MOVE, this.mouseMove);
			
			this.background.resize();
			this.title.resize();
			
			// this object is used in the mouseMove method
			this.sc = new ScreenCoords(
				this.title.get_height(), 0, this.stage.stageWidth, this.stage.stageHeight,
				null, null, null, 0, 0, false, false, false );
			this.obs.resize( sc );
			
			// TODO: hook into the mouse move events for tooltips
		}
		
		private function resize():void {
			if ( !this.ok )
				return;			// <-- something is wrong
		
			if ( this.obs.has_pie() )
				this.resize_pie();
			else
				this.resize_chart();
		}
			
		private function resize_chart(): void {
			
			//
			// we want to show the tooltip closest to
			// items near the mouse, so hook into the
			// mouse move event:
			//
			this.addEventListener(MouseEvent.MOUSE_MOVE, this.mouseMove);
	
			// FlashConnect.trace("stageWidth: " + stage.stageWidth + " stageHeight: " + stage.stageHeight);
			this.background.resize();
			this.title.resize();
			
			var left:Number   = this.y_legend.get_width() /*+ this.y_labels.get_width()*/ + this.y_axis.get_width();
			
			this.keys.resize( left, this.title.get_height() );
				
			var top:Number = this.title.get_height() + this.keys.get_height();
			
			var bottom:Number = this.stage.stageHeight;
			bottom -= (this.x_labels.get_height() + this.x_legend.get_height() + this.x_axis.get_height());
			
			var right:Number = this.stage.stageWidth;
			right -= this.y_legend_2.get_width();
			//right -= this.y_labels_right.get_width();
			right -= this.y_axis_right.get_width();
			
			// this object is used in the mouseMove method
			this.sc = new ScreenCoords(
				top, left, right, bottom,
				this.y_axis.get_range(),
				this.y_axis_right.get_range(),
				this.x_axis.get_range(),
				this.x_labels.first_label_width(),
				this.x_labels.last_label_width(),
				false,
				this.x_axis.offset, this.y_axis.offset );
			
			this.sc.set_bar_groups(this.obs.groups);
			
			this.x_labels.resize(
				sc,
				this.stage.stageHeight-(this.x_legend.get_height()+this.x_labels.get_height())	// <-- up from the bottom
				);
				
			this.x_axis.resize( sc );
			this.y_axis.resize( this.y_legend.get_width(), sc );
			this.y_axis_right.resize( 0, sc );
			this.x_legend.resize( sc );
			this.y_legend.resize();
			this.y_legend_2.resize();
				
			this.obs.resize( sc );
		}
		
		private function mouseOut(event:Event):void {
			
			if( this.tooltip != null )
				this.tooltip.hide();
			
			if( this.obs != null )
				this.obs.mouse_out();
        }
		
		//
		// an external interface, used by javascript to
		// pass in a JSON string
		//
		public function load( s:String ):void {
			this.parse_json( s );
		}

		//
		// JSON is loaded from an external URL
		//
		private function xmlLoaded(event:Event):void {
			var loader:URLLoader = URLLoader(event.target);
			this.parse_json( loader.data );
		}
		
		//
		// we have data! parse it and make the chart
		//
		private function parse_json( json_string:String ):void {
			
			// tr.ace(json_string);
			
			var ok:Boolean = false;
			
			try {
				var json:Object = JSON.deserialize( json_string );
				ok = true;
			}
			catch (e:Error) {
				// remove the 'loading data...' msg:
				this.removeChildAt(0);
				this.addChild( new JsonErrorMsg( json_string as String, e ) );
			}
			
			//
			// don't catch these errors:
			//
			if( ok )
			{
				// remove 'loading data...' msg:
				this.removeChildAt(0);
				this.build_chart( json );
			}
		}
		
		private function build_chart( json:Object ):void {
			
			tr.ace('----');
			tr.ace(JSON.serialize(json));
			tr.ace('----');
			
			// init singletons:
			NumberFormat.getInstance( json );
			NumberFormat.getInstanceY2( json );

			//
			this.tooltip	= new Tooltip( json.tooltip );
			var g:Global = Global.getInstance();
			g.set_tooltip_string( this.tooltip.tip_text );
			//
		
			this.background	= new Background( json );
			this.title		= new Title( json.title );
			this.obs		= Factory.MakeChart( json );
			
			
			this.addChild( this.background );
			
			if( !this.obs.has_pie() )
				this.build_chart_background( json );
			else
			{
				// PIE charts default to FOLLOW tooltips
				this.tooltip.set_tip_style( Tooltip.FOLLOW );
			}

			// these are added in the Flash Z Axis order
			this.addChild( this.title );
			for each( var set:Sprite in this.obs.sets )
				this.addChild( set );
			this.addChild( this.tooltip );

			this.ok = true;
			this.resize();
		}
		
		//
		// PIE charts don't have this.
		// build grid, axis, legends and key
		//
		private function build_chart_background( json:Object ):void {
			
			var g:Global = Global.getInstance();
			
			this.x_legend		= new XLegend( json.x_legend );
			// this is needed for tooltips
			g.x_legend = this.x_legend;
			
			this.y_legend		= new YLegendLeft( json );
			this.y_legend_2		= new YLegendRight( json );
			this.x_axis			= new XAxis( json.x_axis );
			
			
			// var y_ticks:YTicks = new YTicks( json );
			
			this.y_axis			= new YAxisLeft( json );
			this.y_axis_right	= new YAxisRight( json );
			this.x_labels		= new XAxisLabels( json );
			
			if( !this.x_axis.range_set() )
			{
				//
				// the user has not told us how long the X axis
				// is, so we figure it out:
				//
				if( this.x_labels.need_labels ) {
					//
					// No X Axis labels set:
					//
					this.x_axis.set_range( this.obs.get_min_x(), this.obs.get_max_x() );
					this.x_labels.auto_label( this.x_axis.get_range() );
				}
				else
				{
					//
					// X Axis labels used, even so, make the chart
					// big enough to show all values
					//
					this.x_axis.set_range(
						this.obs.get_min_x(),
						Math.max( this.x_labels.count(), this.obs.get_max_x() ) );
				}
			}

			// this is needed by all the elements tooltip
			g.x_labels = this.x_labels;
			
			
			this.keys = new Keys( this.obs );
			
			this.addChild( this.x_legend );
			this.addChild( this.y_legend );
			this.addChild( this.y_legend_2 );
			this.addChild( this.x_labels );
			this.addChild( this.y_axis );
			this.addChild( this.y_axis_right );
			this.addChild( this.x_axis );
			this.addChild( this.keys );
		}
		
		public function format_y_axis_label( val:Number ): String {
//			if( this._y_format != undefined )
//			{
//				var tmp:String = _root._y_format.replace('#val#',_root.format(val));
//				tmp = tmp.replace('#val:time#',_root.formatTime(val));
//				tmp = tmp.replace('#val:none#',String(val));
//				tmp = tmp.replace('#val:number#', NumberUtils.formatNumber (Number(val)));
//				return tmp;
//			}
//			else
				return NumberUtils.format(val,2,true,true,false);
		}


	}
	
}
