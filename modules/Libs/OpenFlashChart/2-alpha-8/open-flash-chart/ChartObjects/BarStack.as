package ChartObjects {
	import ChartObjects.Elements.Element;
	import ChartObjects.Elements.PointBarStackCollection;
	import string.Utils;
	import com.serialization.json.JSON;
	
	public class BarStack extends BarBase {
		//private var line_width:Number;
		
		public function BarStack( json:Object, num:Number, group:Number ) {
			super( json, group );
		}
		
		
		protected override function get_element( index:Number, value:Object ): Element {
			
			return new PointBarStackCollection( index, value, this.colour, this.group );
		}
		
		
		public override function closest( x:Number, y:Number ): Object {
			var shortest:Number = Number.MAX_VALUE;
			var ex:Element = null;
			
			for ( var i:Number = 0; i < this.numChildren; i++ )
			{
				// get the collection
				var stack:Element = this.getChildAt(i) as PointBarStackCollection;
				
				// get the first bar in the stack
				var e:Element = stack.getChildAt(0) as Element;
				
				e.is_tip = false;
				
				if( (x > e.x) && (x < e.x+e.width) )
				{
					// mouse is in position 1
					shortest = Math.min( Math.abs( x - e.x ), Math.abs( x - (e.x+e.width) ) );
					ex = stack;
					break;
				}
				else
				{
					// mouse is in position 2
					// get distance to left side and right side
					var d1:Number = Math.abs( x - e.x );
					var d2:Number = Math.abs( x - (e.x+e.width) );
					var min:Number = Math.min( d1, d2 );
					if( min < shortest )
					{
						shortest = min;
						ex = stack;
					}
				}
			}
			var dy:Number = Math.abs( y - ex.y );
			
			return { element:ex, distance_x:shortest, distance_y:dy };
		}
		
		//
		// stacked bar charts will need the Y to figure out which
		// bar in the stack to return
		//
		public override function inside( x:Number, y:Number ):Object {
			var ret:Element = null;
			
			for ( var i:Number = 0; i < this.numChildren; i++ ) {
				
				var e:PointBarStackCollection = this.getChildAt(i) as PointBarStackCollection;
				
				//
				// may return a PointBarStack or null
				//
				ret = e.inside_2(x);
				
				if( ret )
					break;
			}
			
			var dy:Number = 0;
			if ( ret != null )
				dy = Math.abs( y - ret.y );
				
			return { element:ret, distance_y:dy };
		}
	}
}