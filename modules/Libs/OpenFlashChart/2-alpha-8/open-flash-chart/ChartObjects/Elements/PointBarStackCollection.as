package ChartObjects.Elements {
	import flash.display.Sprite;
	import flash.geom.Point;
	import com.serialization.json.JSON;
	import string.Utils;
	
	public class PointBarStackCollection extends Element {
		
		protected var tip_pos:flash.geom.Point;
		private var vals:Array;
		public var colour:Number;
		protected var group:Number;
		private var total:Number;
		
		public function PointBarStackCollection( index:Number, value:Object, colour:Number, group:Number ) {
			
			this.tooltip = '#total#';
			// this is very similar to a normal
			// PointBarBase but without the mouse
			// over and mouse out events
			this.index = index;
			
			var item:Object;
			
			// a stacked bar has n Y values
			// so this is an array of objects
			this.vals = value as Array;
			
			this.total = 0;
			for each( item in this.vals ) {
				if( item != null ) {
					if( item is Number )
						this.total += item;
					else
						this.total += item.val;
				}
			}
			
			var tmp:String = this.tooltip.replace('#total#', NumberUtils.formatNumber( this.total ));
			this.tooltip = tmp;
		
			this.colour = colour;
			this.group = group;
			this.visible = true;
			
			var prop:String;
			
			var n:Number;	// <-- ugh, leaky variables.
			var bottom:Number = 0;
			var top:Number = 0;
			var odd:Boolean = false;
			var c:Number;

			for each( item in this.vals )
			{
				// is this a null stacked bar group?
				if( item != null )
				{
					c = odd?this.colour:0x909090;
					
					var value:Object = {
						top:		0,		// <-- set this later
						bottom:		bottom,
						colour:		c,		// <-- default colour (may be overriden later)
						total:		this.total,
						tip:		'Total: #total#<br>#val#'
					}

					//
					// a valid item is one of [ Number, Object, null ]
					//
					if( item is Number ) {
						top += item;
					}
					else
					{
						top += item.val;
						if( item.colour )
							value.colour = string.Utils.get_colour(item.colour);
					}
					
					value.top = top;
					
					var p:PointBarStack = new PointBarStack( index, value, group );
					this.addChild( p );
					
					bottom = top;
					odd = !odd;
				}
			}
		}
		

		public override function resize( sc:ScreenCoords, axis:Number ):void {
			for ( var i:Number = 0; i < this.numChildren; i++ )
			{
				var e:Element = this.getChildAt(i) as Element;
				e.resize( sc, axis );
			}
		}
		
		//
		// is the mouse above, inside or below this bar?
		//
		public function inside_2( x:Number ):Element {
			var e:Element;
			//
			// is the mouse over any of the bars in the stack?
			//
			for ( var i:Number = 0; i < this.numChildren; i++ )
			{
				e = this.getChildAt(i) as Element;
				if( e.is_tip ) {
					//
					// LOOK
					//
					return e;
				}
			}
			
			//
			// is the mouse above or below any of them?
			// We only need to check one Element
			//
			
			e = this.getChildAt(0) as Element;
			if ( e.inside( x ) ) {
				//
				// we return "this" collection so the
				// tooltip displays the total
				//
				return this;
			}

			//
			// the mouse is not over us
			//
			return null;
		}
		
		public override function get_tip_pos():Object {
			var e:Element = this.getChildAt(this.numChildren-1) as Element;
			return e.get_tip_pos();
		}
		
/*
 * TODO: fix this
		public override function make_tooltip( key:String ):void
		{
			super.make_tooltip( key );
			var tmp:String = this.tooltip;
			if ( tmp == "_default" ) { tmp = this.tooltip_template; }
			tmp = tmp.replace('#val#', NumberUtils.formatNumber( this.total ));
			this.tooltip = tmp;
		}
*/
		
		public override function get_tooltip():String {
			//
			// is the mouse over one of the bars in this stack?
			//
			
			// tr.ace( this.numChildren );
			for ( var i:Number = 0; i < this.numChildren; i++ )
			{
				var e:Element = this.getChildAt(i) as Element;
				if ( e.is_tip )
				{
					//tr.ace( 'TIP' );
					return e.get_tooltip();
				}
			}
			//
			// the mouse is *near* our stack, so show the 'total' tooltip
			//
			return this.tooltip;
		}
	}
}