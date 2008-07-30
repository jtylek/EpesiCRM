package {
	import flash.display.Sprite;
	import string.Utils;
	
	public class YAxisBase extends Sprite {
		protected var _width:Number=0;
		protected var steps:Number;
		
		protected var stroke:Number;
		protected var tick_length:Number;
		protected var colour:Number;
		public var offset:Boolean;
		protected var grid_colour:Number;
		
		public var style:Object;
		
		protected var labels:YAxisLabelsBase;
		
		function YAxisBase( json:Object, name:String )
		{
	
			//
			// If we set this.style in the parent, then
			// access it here it is null, but if we do
			// this hack then it is OK:
			//
			this.style = this.get_style();
			
			if( json[name] )
				object_helper.merge_2( json[name], this.style );
				
			
			this.colour = Utils.get_colour( style.colour );
			this.grid_colour = Utils.get_colour( style['grid-colour'] );
			this.stroke = style.stroke;
			this.tick_length = style['tick-length'];
			
			this.offset = style.offset;
			
			this._width = this.stroke + this.tick_length;
		}
		
		public function get_style():Object { return null;  }
		
		//
		// may be called by the labels
		//
		public function set_y_max( m:Number ):void {
			this.style.max = m;
		}
		
		public function get_range():Range {
			return new Range( this.style.min, this.style.max );
		}
		
		public function resize( label_pos:Number, sc:ScreenCoords ):void {
		}
		
		public function get_width():Number {
			return this._width + this.labels.width;
		}
		
	}
}