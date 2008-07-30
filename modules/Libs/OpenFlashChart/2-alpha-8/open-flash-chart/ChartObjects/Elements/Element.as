package ChartObjects.Elements {
	import flash.display.Sprite;
	import string.Utils;
	import global.Global;
	import flash.events.Event;
	import flash.events.MouseEvent;
	import caurina.transitions.Tweener;
	import caurina.transitions.Equations;
	
	public class Element extends Sprite {
		//
		// for line data
		//
		public var _x:Number;
		public var _y:Number;
		
		public var index:Number;
		
		public var screen_x:Number;
		public var screen_y:Number;
		protected var tooltip:String;
		public var link:String;
		
		public var is_tip:Boolean;
		public var tooltip_template:String = '#val#'
		
		
		public var line_mask:Sprite;
		
		
		
		public function Element() {}
		
		public function resize( sc:ScreenCoords, axis:Number ):void {
	
			this.x = this.screen_x = sc.get_x_from_pos( this._x );
			this.y = this.screen_y = sc.get_y_from_val( this._y, (axis==2) );
		}
		
		// override this
		public function set_tip( b:Boolean ):void {}
		
		//
		// if this is put in the Element constructor, it is
		// called multiple times for some reason :-(
		//
		protected function attach_events():void {
			this.addEventListener(MouseEvent.MOUSE_OVER, this.mouseOver);
			this.addEventListener(MouseEvent.MOUSE_OUT, this.mouseOut);
		}
		
		public function mouseOver(event:Event):void {
			this.pulse();
		}
		
		public function pulse():void {
			// pulse:
			Tweener.addTween(this, {alpha:.5, time:0.4, transition:"linear"} );
			Tweener.addTween(this, {alpha:1,  time:0.4, delay:0.4, onComplete:this.pulse, transition:"linear"});
		}

		public function mouseOut(event:Event):void {
			// stop the pulse, then fade in
			Tweener.removeTweens(this);
			Tweener.addTween(this, { alpha:1, time:0.4, transition:Equations.easeOutElastic } );
		}
		
		//
		// we make good use of global objects here ;-)
		// it reduces the complexity of passing all the
		// data in through the methods
		//
		public function tooltip_replace_global_magics( tip:String ):String {
			var tmp:String = tip;
			//
			// Warning: this is our global singleton
			//
			var g:Global = Global.getInstance();
			//
			// do we want a global tooltip default? I don't think so...
			//
//			var tip:String = g.get_tooltip_string();
			var x_legend:String = g.get_x_legend();
			var x_axis_label:String = g.get_x_label( this.index );

//			tmp = tmp.replace('#key#',key);
			tmp = tmp.replace('#x_label#',x_axis_label);
//			tmp = tmp.replace('#val:time#',_root.formatTime(val));
			tmp = tmp.replace('#x_legend#', x_legend);
			
			return tmp;
		}
		
		public function set_link( s:String ):void {
			this.link = s;
			this.buttonMode = true;
			this.useHandCursor = true;
			this.addEventListener(MouseEvent.MOUSE_UP, this.mouseUp);
		}
		
		private function mouseUp(event:Event):void {
			tr.ace( this.link );
		}
		
		public function get_tip_pos():Object {
			return {x:this.x, y:this.y};
		}
		
		//
		// is the mouse above, inside or below this object?
		//
		public function inside( x:Number ):Boolean {
			return false;
		}
		
		//
		// this may be overriden by Collection objects
		//
		public function get_tooltip():String {
			return this.tooltip;
		}
		
		//public override function toString():String {
		//	return "x :"+ this._x;
		//}
	}
}