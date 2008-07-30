package {
	
	public class Range
	{
		public var min:Number;
		public var max:Number;
		
		public function Range( min:Number, max:Number )
		{
			this.min = min;
			this.max = max;
		}
		
		public function count():Number {
			return this.max - this.min;
		}
		
		public function toString():String {
			return 'Range : ' + this.min +', ' + this.max;
		}
	}
}