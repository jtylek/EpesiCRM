package ChartObjects.Elements {
	import flash.display.Sprite;
	import flash.geom.Point;
	
	public class PointBarCandle extends PointBarBase {
		private var high:Number;
		private var open:Number;
		private var close:Number;
		private var low:Number;
		private var line_width:Number;
		
		public function PointBarCandle( x:Number, value:Object, line_width:Number, colour:Number, group:Number ) {
			super(x, value, colour, group);
			this.line_width = line_width;
		}
		
		//
		// a candle chart has many values used to display each point
		//
		protected override function parse_value( value:Object ):void {
			var group:Array = value.split(',');
			this.high = Number( group[0] );
			this.open = Number( group[1] );
			this.close = Number( group[2] );
			this.low = Number( group[3] );
		}
		
		public override function resize( sc:ScreenCoords, axis:Number ):void {
			var tmp:Object = sc.get_bar_coords(this._x,this.group);
			this.screen_x = tmp.x;
			
			var bar_bottom:Number = sc.getYbottom( false );
			
			
			var h:Number = sc.get_y_from_val(this.high, axis == 2);
			var o:Number = sc.get_y_from_val(this.open, axis == 2)-h;
			var c:Number = sc.get_y_from_val(this.close, axis == 2)-h;
			var l:Number = sc.get_y_from_val(this.low, axis == 2)-h;

			this.screen_y = h;
			
			this.graphics.clear();
			this.graphics.lineStyle(0, 0, 0);
			
			var line_pos:Number = (tmp.width/2) - (this.line_width/2);
			this.graphics.beginFill( this.colour, 1.0 );
			this.graphics.drawRect( line_pos, 0, this.line_width, o );
			this.graphics.endFill();
			
			
			this.graphics.beginFill( this.colour, 1.0 );
			this.graphics.drawRect( 0, o, tmp.width, c-o );
			this.graphics.endFill();
			
			this.graphics.beginFill( this.colour, 1.0 );
			this.graphics.drawRect( line_pos, c, this.line_width, l-c );
			this.graphics.endFill();
			
			this.y = h;
			this.x = tmp.x;
			
			//
			// tell the tooltip where to show its self
			//
			this.tip_pos = new flash.geom.Point( this.x + (tmp.width / 2), this.y );
		}
	}
}