package ChartObjects.Elements {
	import flash.display.Sprite;
	import flash.events.Event;
	import flash.events.MouseEvent;
	import caurina.transitions.Tweener;
	import caurina.transitions.Equations;
	import com.serialization.json.JSON;
	
	
	public class PointHBar extends Element
	{
		private var _right:Number;
		private var _left:Number;
		protected var _width:Number;
		
		public var colour:Number;
		protected var group:Number;
		
		public function PointHBar( index:Number, value:Object, colour:Number, group:Number )
		{
			super();
			this.tooltip_template = '#val#';
			//
			// we use the index of this bar to find its Y position
			//
			this.index = index;
			//
			// horizontal bar: value = X Axis position
			// we'll use the ScreenCoords object to go [value -> x location]
			//
			//var result:* = JSON.deserialize( value );
			
			this._left = value.left ? value.left : 0;
			this._right = value.right ? value.right : 0;
			
			this.colour = colour;
			this.group = group;
			this.visible = true;
			
			this.alpha = 0.5;
			
			this.addEventListener(MouseEvent.MOUSE_OVER, this.mouseOver);
			this.addEventListener(MouseEvent.MOUSE_OUT, this.mouseOut);
			
		}

	/* TODO: fix this
	 *
		public override function make_tooltip( key:String ):void
		{
			super.make_tooltip( key );
			var tmp:String = this.tooltip;
			if ( tmp == "_default" ) { tmp = this.tooltip_template; }
			tmp = tmp.replace('#val#', NumberUtils.formatNumber( this._right - this._left ));
			this.tooltip = tmp;
		}
	*/
		
		public override function mouseOver(event:Event):void {
			Tweener.addTween(this, { alpha:1, time:0.6, transition:Equations.easeOutCirc } );
		}

		public override function mouseOut(event:Event):void {
			Tweener.addTween(this, { alpha:0.5, time:0.8, transition:Equations.easeOutElastic } );
		}
		
		public override function resize( sc:ScreenCoords, axis:Number ):void {
			
			var tmp:Object = sc.get_horiz_bar_coords( this.index, this.group );
			
			var left:Number  = sc.get_x_from_val( this._left );
			var right:Number = sc.get_x_from_val( this._right );
			var width:Number = right - left;
			
			this.graphics.clear();
			this.graphics.beginFill( this.colour, 1.0 );
			this.graphics.drawRect( 0, 0, width, tmp.width );
			this.graphics.endFill();
			
			this.x = left;
			this.y = tmp.y;
		}
		
		public function get_max_x_value():Number {
			return this._right;
		}
	}
}