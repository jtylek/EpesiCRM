package ChartObjects {
	import ChartObjects.Elements.Element;

	public class ObjectCollection
	{
		public var sets:Array;
		public var groups:Number;
		
		public function ObjectCollection() {
			this.sets = new Array();
		}
		
		public function add( set:Base ): void {
			this.sets.push( set );
		}
		
		//
		// TODO: for scatter charts we can't assume 0
		//
		public function get_min_x():Number {
			return 0;
		}
		
		public function get_max_x():Number {
			var max:Number = -1;

			for each( var o:Base in this.sets )
				max = Math.max( max, o.get_max_x_value() );

			return max;
		}
		
		// get x, y co-ords of vals
		public function resize( sc:ScreenCoords ):void {
			for each ( var o:Base in this.sets )
				o.resize( sc );
		}
		
		public function mouse_out():void {
			for each( var s:Base in this.sets )
				s.mouse_out();
		}
		
		private function inside( x:Number, y:Number ):Element {
			var o:Object;
			var s:Base;
			
			var inside:Array = new Array();
			for each( s in this.sets )
			{
				o = s.inside( x, y );
				if( o.element!=null )
					inside.push( o );
			}
				
			if ( inside.length > 0 )
			{
				// the mouse is above or below all of these
				// so choose the closest along the Y
				var e:Object = inside[0];
				var f:Object;
				
				for each( f in inside )
					if( f.distance_y < e.distance_y )
						e = f;
				
				for each( f in inside )
					if ( f != e )
						f.element.set_tip( false );
					else
						if( e && e.element )	// <-- pie charts do not return an element
							e.element.set_tip( true );
					
				// tr.ace('inside '+inside.length+'   '+Math.random());
				return e.element;
			}
			
			return null;
		}
		
		private function closest( x:Number, y:Number ):Element {
			var o:Object;
			var s:Base;
			
			// get closest points from each data set
			var closest:Array = new Array();
			for each( s in this.sets )
				closest.push( s.closest( x, y ) );
			
			// find closest point along X axis
			var min:Number = Number.MAX_VALUE;
			for each( o in closest )
				min = Math.min( min, o.distance_x );
				
			//
			// now select all points that are the
			// min (see above) distance along the X axis
			//
			var xx:Object = {element:null, distance_x:Number.MAX_VALUE, distance_y:Number.MAX_VALUE };
			for each( o in closest ) {
				
				if( o.distance_x == min )
				{
					// these share the same X position, so choose
					// the closest to the mouse in the Y
					if( o.distance_y < xx.distance_y )
						xx = o;
				}
			}
			
			// pie charts may not return an element
			if( xx.element )
				xx.element.set_tip( true );
				
			return xx.element;
		}
		
		/*
		
		hollow
		  line --> ------O---------------O-----
				
			             +-----+
			             |  B  |
			       +-----+     |   +-----+
			       |  A  |     |   |  C  +- - -
			       |     |     |   |     |  D
			       +-----+-----+---+-----+- - -
			                1    2
			
		*/
		public function mouse_move( x:Number, y:Number ):Element {
			//
			// is the mouse over, above or below a
			// bar or point? For grouped bar charts,
			// two bars will share an X co-ordinate
			// and be the same distance from the
			// mouse. For example, if the mouse is
			// in position 1 in diagram above. This
			// filters out all items that are not
			// above or below the mouse:
			//
			var e:Element = this.inside(x, y);
			
			if ( !e )
			{
				//
				// no Elements are above or below the mouse,
				// so we select the BEST item to show (mouse
				// is in position 2)
				//
				e = this.closest(x, y);
			}
			
			return e;
		}
		
		//
		// are we resizing a PIE chart?
		//
		public function has_pie():Boolean {
			
			if ( this.sets.length > 0 && ( this.sets[0] is Pie ) )
				return true;
			else
				return false;
		}
	}
}