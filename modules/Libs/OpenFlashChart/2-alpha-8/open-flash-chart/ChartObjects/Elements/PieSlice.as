package ChartObjects.Elements {
	import flash.display.Sprite;
	import flash.geom.Matrix;
	import flash.geom.Point;
	import flash.text.TextField;
	import flash.text.TextFormat;
	import flash.events.Event;
	import flash.events.MouseEvent;
	import caurina.transitions.Tweener;
	import caurina.transitions.Equations;
	import ChartObjects.Pie;
	
	public class PieSlice extends Element {
		
		private var TO_RADIANS:Number = Math.PI / 180;
		private var colour:Number;
		public var slice_angle:Number;
		private var border_width:Number;
		public var angle:Number;
		public var is_over:Boolean;
		private var animate:Boolean;
		public var value:Number;
		
		public function PieSlice( slice_start:Number, slice_angle:Number, value:Number, tip:String, colour:Number, animate:Boolean ) {
			this.colour = colour;
			this.slice_angle = slice_angle;
			this.border_width = 1;
			this.angle = slice_start;
			this.alpha = 0.5;
			this.animate = animate;
			this.value = value;
			
			this.tooltip = this.replace_magic_values( tip );
			
			this.attach_events();
		}
		
		public override function mouseOver(event:Event):void {
			Tweener.addTween(this, { alpha:1, time:0.6, transition:Equations.easeOutCirc } );
			this.is_over = true;
			this.dispatchEvent( new ShowTipEvent( this.slice_angle ) );
			//this.dispatchEvent( new MouseEvent( MouseEvent.MIDDLE_CLICK ) );
			tr.ace('event dispatched');
			
			//event.stopImmediatePropagation();
		}

		public override function mouseOut(event:Event):void {
			Tweener.addTween(this, { alpha:0.5, time:0.8, transition:Equations.easeOutElastic } );
			this.is_over = false;
		}
		
		//
		// may be called by the MOUSE_LEAVE
		//
		public override function set_tip( b:Boolean ):void {
			if ( !b )
			{
				Tweener.addTween(this, { alpha:0.5, time:0.8, transition:Equations.easeOutElastic } );
				this.is_over = false;
			}
		}
		
		//
		// for most objects this is handled in Element,
		// and this tip is displayed just above that object,
		// but for PieSlice we want the tooltip to follow
		// the mouse:
		//
		public override function get_tip_pos():Object {
			var p:flash.geom.Point = this.localToGlobal( new flash.geom.Point(this.mouseX, this.mouseY) );
			return {x:p.x,y:p.y};
		}

		private function replace_magic_values( t:String ): String {
			
			t = t.replace('#val#', NumberUtils.formatNumber( this.value ));
			t = this.tooltip_replace_global_magics( t );
			return t;
		}
		
		//
		// the axis makes no sense here, let's override with null and write our own.
		//
		public override function resize( sc:ScreenCoords, axis:Number ): void { }
		public function pie_resize( sc:ScreenCoords, radius:Number): void {
			
			this.x = sc.get_center_x();
			this.y = sc.get_center_y();
			
			var label_line_length:Number = 10;
			
			this.graphics.clear();
			
			//line from center to edge
			this.graphics.lineStyle( this.border_width, this.colour, 1 );

			//if the user selected the charts to be gradient filled do gradients
			if( true )//this.gradientFill == 'true' )
			{
				//set gradient fill
				var colors:Array = [this.colour, this.colour];
				var alphas:Array = [100, 50];
				var ratios:Array = [100,255];
				//var matrix:Matrix = new Matrix( this.rad * 2, 0, 50, 0, this.rad * 2, 0, -3, 3, 1 );
				this.graphics.beginGradientFill("radial", colors, alphas, ratios );// , matrix);
			}
			else
				this.graphics.beginFill(this.colour, 1);
			
			this.graphics.moveTo(0, 0);
			this.graphics.lineTo(radius, 0);
			
			var angle:Number = 4;
			var a:Number = Math.tan((angle/2)*TO_RADIANS);
			
			var i:Number = 0;
			var endx:Number;
			var endy:Number;
			var ax:Number;
			var ay:Number;
				
			//draw curve segments spaced by angle
			for ( i = 0; i + angle < this.slice_angle; i += angle) {
				endx = radius*Math.cos((i+angle)*TO_RADIANS);
				endy = radius*Math.sin((i+angle)*TO_RADIANS);
				ax = endx+radius*a*Math.cos(((i+angle)-90)*TO_RADIANS);
				ay = endy+radius*a*Math.sin(((i+angle)-90)*TO_RADIANS);
				this.graphics.curveTo(ax, ay, endx, endy);
			}
			
	
			//when aproaching end of slice, refine angle interval
			angle = 0.08;
			a = Math.tan((angle/2)*TO_RADIANS);
			
			for ( ; i+angle < slice_angle; i+=angle) {
				endx = radius*Math.cos((i+angle)*TO_RADIANS);
				endy = radius*Math.sin((i+angle)*TO_RADIANS);
				ax = endx+radius*a*Math.cos(((i+angle)-90)*TO_RADIANS);
				ay = endy+radius*a*Math.sin(((i+angle)-90)*TO_RADIANS);
				this.graphics.curveTo(ax, ay, endx, endy);
			}
	
			//close slice
			this.graphics.endFill();
			this.graphics.lineTo(0, 0);
			
			this.draw_label_line( radius, label_line_length, this.slice_angle );
			// return;
			
			if( this.animate )
			{
				if ( this.rotation != this.angle )	// <-- have we already rotated this slice?
					Tweener.addTween(this, { rotation:this.angle, time:1.4, transition:Equations.easeOutCirc } );
			}
			else
			{
				this.rotation = this.angle;
			}
		}
		
		// draw the line from the pie slice to the label
		private function draw_label_line( rad:Number, tick_size:Number, slice_angle:Number ):void {
			//draw line
			this.graphics.lineStyle( 1, this.colour, 100 );
			//move to center of arc
			this.graphics.moveTo(rad*Math.cos(slice_angle/2*TO_RADIANS), rad*Math.sin(slice_angle/2*TO_RADIANS));

			//final line positions
			var lineEnd_x:Number = (rad+tick_size)*Math.cos(slice_angle/2*TO_RADIANS);
			var lineEnd_y:Number = (rad+tick_size)*Math.sin(slice_angle/2*TO_RADIANS);
			this.graphics.lineTo(lineEnd_x, lineEnd_y);
		}
		
		private function create_label( label:String ):TextField {
			var tf:TextField = new TextField();
			
			tf.text = label;
			// legend_tf._rotation = 3.6*value.bar_bottom;
			
			var fmt:TextFormat = new TextFormat();
			fmt.color = 0;// this.style.get( 'color' );
			fmt.font = "Verdana";
			fmt.size = 10;// this.style.get( 'font-size' );
			fmt.align = "center";
			tf.setTextFormat(fmt);
			//tf.autoSize = true;
			tf.autoSize = "left";
			
			return tf;
		}
		
		private function move_label( rad:Number, x:Number, y:Number, ang:Number ):Boolean {
			
			var tf:TextField = this.getChildAt(0) as TextField;
			//text field position
//			var legend_x:Number = x+rad*Math.cos((ang)*3.6*TO_RADIANS);
//			var legend_y:Number = y+rad*Math.sin((ang)*3.6*TO_RADIANS);
			var legend_x:Number = rad*Math.cos((ang)*3.6*TO_RADIANS);
			var legend_y:Number = rad*Math.sin((ang)*3.6*TO_RADIANS);
			
			//if legend stands to the right side of the pie
//			if(legend_x<0)
//				legend_x -= tf.width;
					
			//if legend stands on upper half of the pie
//			if(legend_y<0)
//				legend_y -= tf.height;
			
			tf.x = legend_x;
			tf.y = legend_y + Math.random() * 20;

			tr.ace('--');
			tr.ace(this);
			tr.ace( tf.x );
			tr.ace( tf.y );
			tr.ace( tf.text );
			
			// is this label outside the stage?
			if( (tf.x>0) && (tf.y>0) && (tf.y+tf.height<this.stage.stageHeight ) && (tf.x+tf.width<this.stage.stageWidth) )
				return false;
			else
				return true;
		}
		
		public override function toString():String {
			return "PieSlice: "+ this.get_tooltip();
		}
	}
}