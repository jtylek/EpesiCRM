package ChartObjects.Elements {
	import flash.display.Sprite;
	import ChartObjects.Elements.Element;
	import caurina.transitions.Tweener;
	import caurina.transitions.Equations;
	
	public class PointScatter extends Element {
		public var radius:Number;
		
		public function PointScatter( value:Object, colour:Number, size:Number ) {
			this.tooltip_template = '(#x#,#y#) at #size#';
			this._x = value.x;
			this._y = value.y;
			this.is_tip = false;
			
			this.graphics.beginFill( colour, 1 );

			this.radius = value['dot-size'];//== null ? 2 : value['dot-size'];
			
			this.graphics.drawCircle( 0, 0, this.radius );
			this.graphics.drawCircle( 0, 0, this.radius - 1 );
			this.graphics.endFill();

		}
/* TODO: fix this
		public override function make_tooltip( key:String ):void
		{
			super.make_tooltip( key );
			var tmp:String = this.tooltip;
			if ( tmp == "_default" ) { tmp = this.tooltip_template; }
			tmp = tmp.replace('#x#', NumberUtils.formatNumber(this._x));
			tmp = tmp.replace('#y#', NumberUtils.formatNumber(this._y));
			tmp = tmp.replace('#size#', NumberUtils.formatNumber(this.radius));
			this.tooltip = tmp;
		}
*/
		
		public override function set_tip( b:Boolean ):void {
			if ( b )
			{
				if ( !this.is_tip )
				{
					Tweener.addTween(this, {scaleX:1.3, time:0.4, transition:"easeoutbounce"} );
					Tweener.addTween(this, {scaleY:1.3, time:0.4, transition:"easeoutbounce"} );
				}
				this.is_tip = true;
			}
			else
			{
				Tweener.removeTweens(this);
				this.scaleX = 1;
				this.scaleY = 1;
				this.is_tip = false;
			}
		}
		
		public override function resize( sc:ScreenCoords, axis:Number ): void {
			//
			// Look: we have a real X value, so get its screen location:
			//
			this.x = this.screen_x = sc.get_x_from_val( this._x );
			this.y = this.screen_y = sc.get_y_from_val( this._y, (axis==2) );
		}
		
		//
		// is the mouse above, inside or below this point?
		//
		public override function inside( x:Number ):Boolean {
			return (x > (this.x-(this.radius/2))) && (x < (this.x+(this.radius/2)));
		}
	}
}