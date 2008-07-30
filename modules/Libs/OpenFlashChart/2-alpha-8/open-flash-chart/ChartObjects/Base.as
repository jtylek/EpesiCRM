package ChartObjects {
	import flash.display.Sprite;
	import org.flashdevelop.utils.FlashConnect;
	import ChartObjects.Elements.Element;
	
	public class Base extends Sprite {
		
		public var key:String;
		public var font_size:Number;
		
		// accessed by the Keys object to display the key
		public var colour:Number;
		public var line_width:Number;
		public var circle_size:Number;
		
		//
		// hold the Element values, for lines this is an
		// array of string Y values, for Candle it is an
		// array of string 'high,open,low,close' values,
		// for scatter it is 'x,y' etc...
		//
		public var values:Array;
		
		protected var axis:Number;
		
		public function Base()
		{}
		
		public function get_colour(): Number {
			return this.colour;
		}
		
		//
		// whatever sets of data that *may* be attached to the right
		// Y Axis call this to see if they are attached to it or not.
		// All lines, area and bar charts call this.
		//
		protected function which_axis_am_i_attached_to( data:Array, i:Number ): Number {
			//
			// some data sets are attached to the right
			// Y axis (and min max), in the future we
			// may support many axis
			//
			if( data['show_y2'] != undefined )
				if( data['show_y2'] != 'false' )
					if( data['y2_lines'] != undefined )
					{
						var tmp:Array = data.y2_lines.split(",");
						var pos:Number = tmp.indexOf( i.toString() );
						
						if ( pos == -1 )
							return 1;
						else
							return 2;	// <-- this line found in y2_lines, so it is attached to axis 2 (right axis)
					}
					
			return 1;
		}
			
		// called from external interface (JS)
		public function add( val:String, tool_tip:String ):void	{
			this.values.push( val );
		}
		
		// called from external interface (JS)
		public function del():void {
			this.values.shift();
		}
		
		public function get_max_x_value():Number {
			
			var max_index:Number = Number.MIN_VALUE;
			
			for ( var i:Number = 0; i < this.numChildren; i++ ) {
				
				//
				// some of the children will be mask
				// Sprites, so filter those out:
				//
				if( this.getChildAt(i) is Element ) {
					
					var e:Element = this.getChildAt(i) as Element;
					max_index = Math.max( max_index, e.index );

				}
			}
			
			// 0 is a position, so count it:
			return max_index+1;
		}
		
		//
		// this should be overriden
		//
		public function resize( sc:ScreenCoords ):void{}
		
		public function draw( val:String, mc:Object ):void {}
		
		//public function highlight_value():void {}
		
		// public function inside( x:Number, y:Number ):Object { return null }
		
		public function inside( x:Number, y:Number ):Object {
			var ret:Element = null;
			
			for ( var i:Number = 0; i < this.numChildren; i++ ) {
				
				//
				// some of the children will be mask
				// Sprites, so filter those out:
				//
				if( this.getChildAt(i) is Element ) {
					
					var e:Element = this.getChildAt(i) as Element;

					if( e.inside(x) )
					{
						ret = e;
						break;
					}
				}
			}
			
			var dy:Number = 0;
			if ( ret != null )
				dy = Math.abs( y - ret.y );
				
			return { element:ret, distance_y:dy };
		}
		
		public function closest( x:Number, y:Number ): Object {
			var shortest:Number = Number.MAX_VALUE;
			var closest:Element = null;
			var dx:Number;
			
			for ( var i:Number = 0; i < this.numChildren; i++ ) {
			
				//
				// some of the children will will mask
				// Sprites, so filter those out:
				//
				if( this.getChildAt(i) is Element ) {
					
					var e:Element = this.getChildAt(i) as Element;
					e.set_tip( false );
				
					dx = Math.abs( x -e.screen_x );
				
					if( dx < shortest )	{
						shortest = dx;
						closest = e;
					}
				}
			}
			
			var dy:Number = 0;
			if( closest )
				dy = Math.abs( y - closest.y );
				
			return { element:closest, distance_x:shortest, distance_y:dy };
		}
		
		//
		// this is a backup function so if the mouse leaves the
		// movie for some reason without raising the mouse
		// out event (this happens if the user is wizzing the mouse about)
		//
		public function mouse_out():void {
			for ( var i:Number = 0; i < this.numChildren; i++ ) {
				
				// filter out the mask elements in line charts
				if( this.getChildAt(i) is Element ) {
					
					var e:Element = this.getChildAt(i) as Element;
					e.set_tip(false);
				}
			}
		}
		
		
		//
		// index of item (bar, point, pie slice, horizontal bar) may be used
		// to look up its X value (bar,point) or Y value (H Bar) or used as
		// the sequence number (Pie)
		//
		protected function get_element( index:Number, value:Object ): Element {
			return null;
		}
		
		public function add_values():void {
			
			// keep track of the X position (column)
			var index:Number = 0;
			
			for each ( var val:Object in this.values )
			{
				var tmp:Element;
				
				// filter out the 'null' values
				if( val != null )
				{
					tmp = this.get_element( index, val );
					
					if( tmp.line_mask != null )
						this.addChild( tmp.line_mask );
						
					this.addChild( tmp );
				}
				
				index++;
			}
		}
		
	}
}