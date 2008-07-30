package {
	import flash.text.TextField;
	
	public class TextFieldY extends TextField {
		public var y_val:Number;
		
		//
		// mini class to hold the y value of the
		// Y Axis label (so we can position it later )
		//
		public function TextFieldY() {
			super();
			this.y_val = 0;
		}
	}
}