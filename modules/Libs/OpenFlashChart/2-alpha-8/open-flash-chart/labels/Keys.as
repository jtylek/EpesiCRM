package labels {
	import ChartObjects.Base;
	import ChartObjects.ObjectCollection;
	import flash.display.Sprite;
	import flash.text.TextField;
	import flash.text.TextFormat;
	import org.flashdevelop.utils.FlashConnect;
	
	public class Keys extends Sprite {
		private var _height:Number = 0;
		private var count:Number = 0;
		public var colours:Array;
		
		public function Keys( stuff:ObjectCollection )
		{
			this.colours = new Array();
			
			var key:Number = 0;
			for each( var b:Base in stuff.sets )
			{
				// some lines may not have a key
				if( (b.font_size > 0) && (b.key != '' ) )
				{
					this.make_key( b, key );
					this.colours.push( b.get_colour() );
					key++;
				}
			}
			
			this.count = key;
		}
		
		//
		// this should be in the destructor, but
		// actionscript does not support them :-(
		//
		public function del():void{
		}
		
		// each key is a MovieClip with text on it
		private function make_key( st:Base, c:Number ) : void //st:Style, c:Number )
		{

			var tf:TextField = new TextField();
			
			tf.text = st.key;
			var fmt:TextFormat = new TextFormat();
			fmt.color = st.get_colour();
			fmt.font = "Verdana";
			fmt.size = 12;// st.font_size;
			fmt.align = "left";
			
			tf.setTextFormat(fmt);
			tf.autoSize="left";
		
			this.addChild(tf);
		}
		
		//
		// draw the colour block for the data set
		//
		private function draw_line( x:Number, y:Number, height:Number, colour:Number ):Number {
			y += (height / 2);
			this.graphics.beginFill( colour, 100 );
			this.graphics.drawRect( x, y - 1, 10, 2 );
			this.graphics.endFill();
			return x+12;
		}

		// shuffle the keys into place, keeping note of the total
		// height the key block has taken up
		public function resize( x:Number, y:Number ):void {
			if( this.count == 0 )
				return;
			
			this.x = x;
			this.y = y;
			
			var height:Number = 0;
			var x:Number = 0;
			var y:Number = 0;
			
			this.graphics.clear();
			
			for( var i:Number=0; i<this.numChildren; i++ )
			{
				var width:Number = this.getChildAt(i).width;
				
				if( ( this.x + x + width + 12 ) > this.stage.stageWidth )
				{
					// it is past the edge of the stage, so move it down a line
					x = 0;
					y += this.getChildAt(i).height;
					height += this.getChildAt(i).height;
				}
					
				this.draw_line( x, y, this.getChildAt(i).height, this.colours[i] );
				x += 12;

				this.getChildAt(i).x = x;
				this.getChildAt(i).y = y;
				
				// move next key to the left + some padding between keys
				x += width + 10;
			}
			
			// Ugly code:
			height += this.getChildAt(0).height;
			this._height = height;
		}
		
		public function get_height() : Number {
			return this._height;
		}
	}
}