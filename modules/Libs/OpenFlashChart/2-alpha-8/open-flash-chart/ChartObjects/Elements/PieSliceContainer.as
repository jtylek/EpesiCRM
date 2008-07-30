package ChartObjects.Elements {
	public class PieSliceContainer extends Element {
		
		private var TO_RADIANS:Number = Math.PI / 180;

		//
		// this holds the slice and the text.
		// we want to rotate the slice, but not the text, so
		// this container holds both
		//
		public function PieSliceContainer( slice_start:Number, slice_angle:Number, value:Number, tip:String, colour:Number, label:String, animate:Boolean )
		{
			this.addChild( new PieSlice( slice_start, slice_angle, value, tip, colour, animate ) );
			this.addChild( new PieLabel( label ) );
		}
		
		public function is_over():Boolean {
			var tmp:PieSlice = this.getChildAt(0) as PieSlice;
			return tmp.is_over;
		}
		
		public function get_slice():Element {
			return this.getChildAt(0) as Element;
		}
		
		//
		// because we hold the slice inside this element, pass
		// along the tooltip info:
		//
//		public override function make_tooltip( key:String ):void
//		{
//			var tmp:PieSlice = this.getChildAt(0) as PieSlice;
//			tmp.make_tooltip( key );
//		}
		
		//
		// the axis makes no sense here, let's override with null and write our own.
		//
		public override function resize( sc:ScreenCoords, axis:Number ): void { }
		
		
		public function pie_resize( sc:ScreenCoords, slice_radius:Number ): void {
			var p:PieSlice = this.getChildAt(0) as PieSlice;
			p.pie_resize(sc, slice_radius);

			var l:PieLabel = this.getChildAt(1) as PieLabel;
			l.move_label( slice_radius + 10, p.x, p.y, p.angle+(p.slice_angle/2) );
		}
		
		public function get_radius_offsets() :Object {
			var offset:Object = {top:0, right:0, bottom:0, left:0};
			var p:PieSlice = this.getChildAt(0) as PieSlice;
			var tick_angle:Number = (p.slice_angle / 2) + p.angle;
            var offset_threshold:Number = 30;
			
			tr.ace('p.slice_angle=' + p.slice_angle + ' p.angle' + p.angle + ' tick_angle=' + tick_angle);
			
			if (tick_angle > 90 - offset_threshold && tick_angle < 90 + offset_threshold)
			{
				offset.bottom = (10 * Math.abs(Math.sin(tick_angle * TO_RADIANS))) + 14; // 14 is pad for label height
			}
			else if (tick_angle > 270 - offset_threshold && tick_angle < 270 + offset_threshold)
			{
				offset.top = (10 * Math.abs(Math.sin(tick_angle * TO_RADIANS))) + 14; // 14 is pad for label height
			}

			// need to add labels too right now they are included in the 30 factor above
			//var l:PieLabel = this.getChildAt(1) as PieLabel;
			return offset;
		}
		
		public override function get_tooltip():String {
			var p:PieSlice = this.getChildAt(0) as PieSlice;
			return p.get_tooltip();
		}
	}
}